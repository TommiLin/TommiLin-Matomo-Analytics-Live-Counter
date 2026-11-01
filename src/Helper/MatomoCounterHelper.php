<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 TommiLin. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace TommiLin\Module\MatomoCounter\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;

/**
 * Matomo API client with Joomla Cache API storage, stale-data fallback and a refresh lock.
 *
 * Everything is fetched in one bulk request and cached as one entry: all numbers are renewed together, and only
 * when Cache Time has passed (or an administrator presses the refresh button).
 *
 * Options: url, site_id, token, cache_time, language (2-letter), verify_ssl, timeout,
 *          window (minutes), rows, segment, compare, strip (text to cut out of page titles, one per line)
 */
class MatomoCounterHelper
{
    /** Seconds to wait before asking Matomo again after a failed request */
    const RETRY_AFTER = 60;

    /** A manual refresh is ignored when the data is younger than this (seconds) */
    const FORCE_MIN_AGE = 5;

    /** Generic "top" blocks: visibility key => API definition */
    const TOP_BLOCKS = [
        // Page titles are far more readable than URLs; flat=1 keeps titles containing "/" from being split into a tree
        'top_pages'     => ['method' => 'Actions.getPageTitles',      'extra' => '&flat=1', 'metric' => 'nb_hits',   'lang' => false],
        'top_referrers' => ['method' => 'Referrers.getReferrerType',  'extra' => '',        'metric' => 'nb_visits', 'lang' => true],
        'top_devices'   => ['method' => 'DevicesDetection.getType',   'extra' => '',        'metric' => 'nb_visits', 'lang' => true],
    ];

    protected $url;
    protected $siteId;
    protected $token;
    protected $cacheTime;
    protected $networkDown = false;
    protected $networkError = null;
    protected $shortLang;
    protected $verifySsl;
    protected $timeout;
    protected $window;
    protected $rows;
    protected $segment;
    protected $compare;
    protected $strip = [];

    public function __construct(array $o)
    {
        $this->url       = rtrim((string) ($o['url'] ?? ''), '/');
        $this->siteId    = (int) ($o['site_id'] ?? 1);
        $this->token     = (string) ($o['token'] ?? '');
        $this->cacheTime = max(10, (int) ($o['cache_time'] ?? 300));
        $this->shortLang = strtolower(substr((string) ($o['language'] ?? 'en'), 0, 2));
        $this->verifySsl = !empty($o['verify_ssl']);
        $this->timeout   = min(15, max(1, (int) ($o['timeout'] ?? 4)));
        $this->window    = in_array((int) ($o['window'] ?? 30), [5, 10, 15, 30, 60], true) ? (int) $o['window'] : 30;
        $this->rows      = min(20, max(1, (int) ($o['rows'] ?? 5)));
        $this->segment   = trim((string) ($o['segment'] ?? ''));
        $this->compare   = !empty($o['compare']);

        foreach (preg_split('/\R/u', (string) ($o['strip'] ?? '')) as $line) {
            $line = trim($line);

            if ($line !== '' && count($this->strip) < 10) {
                $this->strip[] = mb_substr($line, 0, 100);
            }
        }
    }

    /* ------------------------------------------------------------------ *
     * Cache layer
     * ------------------------------------------------------------------ */

    /**
     * Returns the data from cache or from Matomo. Never blocks for long and never returns nothing:
     * on errors it serves stale data when it has some, otherwise an error result.
     */
    public function getData(array $visibility = [], $force = false)
    {
        return $this->getEntry($visibility, $force, $this->getCache());
    }

    /**
     * The cache entry: fresh -> use it; expired -> ask Matomo (one process at a time, others get stale data).
     */
    protected function getEntry(array $visibility, $force, $cache)
    {
        $name   = 'all';
        $ttl    = $this->cacheTime;
        $now    = time();
        $id     = $this->cacheId($name, $visibility);
        $cached = $this->readCache($cache, $id);
        $age    = 0;

        if ($cached !== null) {
            $age = $now - (int) ($cached['meta']['fetched_at'] ?? 0);

            if (!empty($cached['meta']['failed'])) {
                if (!$force && $age < self::RETRY_AFTER) {
                    return $this->decorate($cached, 'ERROR (cached)', $age, $name, $ttl);
                }
                $cached = null;
            } elseif ($force) {
                // Manual refresh skips the freshness check, but repeated clicks within seconds are ignored
                if ($age < self::FORCE_MIN_AGE) {
                    return $this->decorate($cached, 'HIT', $age, $name, $ttl);
                }
            } elseif ($age < $ttl) {
                return $this->decorate($cached, 'HIT', $age, $name, $ttl);
            } elseif ((int) ($cached['meta']['retry_after'] ?? 0) > $now) {
                return $this->decorate($cached, 'STALE', $age, $name, $ttl, true);
            }
        }

        $lock = false;

        if ($this->networkDown) {
            // Matomo did not answer a moment ago: do not wait for the second timeout in the same page view
            $fresh = ['status' => 'error', 'error' => $this->networkError];
        } else {
            // Only one request at a time talks to Matomo; the others get stale data
            $lock = $this->acquireLock($id);

            if ($lock === false) {
                if ($cached !== null) {
                    return $this->decorate($cached, 'STALE (refresh running)', $age, $name, $ttl, true);
                }

                return $this->errorResult('busy', null, $name);
            }

            // Somebody may have refreshed the cache while we waited for the lock
            $again = $this->readCache($cache, $id);

            if (!$force && $again !== null && empty($again['meta']['failed'])) {
                $againAge = time() - (int) ($again['meta']['fetched_at'] ?? 0);

                if ($againAge < $ttl) {
                    $this->releaseLock($lock);

                    return $this->decorate($again, 'HIT', $againAge, $name, $ttl);
                }
            }

            try {
                $fresh = $this->fetchApiData($visibility);
            } catch (\Throwable $e) {
                $fresh = ['status' => 'error', 'error' => ['code' => 'connect', 'arg' => $e->getMessage()]];
            }

            if (($fresh['status'] ?? '') === 'error' && ($fresh['error']['code'] ?? '') === 'connect') {
                $this->networkDown  = true;
                $this->networkError = $fresh['error'];
            }
        }

        if (($fresh['status'] ?? '') === 'error') {
            if ($cached !== null) {
                // Keep serving the old data and do not hammer Matomo
                $cached['meta']['retry_after'] = time() + self::RETRY_AFTER;
                $cached['meta']['last_error']  = $fresh['error'] ?? null;
                $this->writeCache($cache, $id, $cached);
                $this->releaseLock($lock);

                $cached['meta']['error'] = $fresh['error'] ?? null;

                return $this->decorate($cached, 'STALE (API error)', time() - (int) ($cached['meta']['fetched_at'] ?? 0), $name, $ttl, true);
            }

            $fresh['meta'] = ['fetched_at' => time(), 'failed' => true, 'error' => $fresh['error'] ?? null, 'part' => $name];
            $this->writeCache($cache, $id, $fresh);
            $this->releaseLock($lock);

            return $this->decorate($fresh, 'ERROR', 0, $name, $ttl);
        }

        $fresh['meta'] = ['fetched_at' => time(), 'error' => null, 'warnings' => $fresh['warnings'] ?? [], 'part' => $name];
        unset($fresh['warnings']);

        $this->writeCache($cache, $id, $fresh);
        $this->releaseLock($lock);

        return $this->decorate($fresh, 'MISS', 0, $name, $ttl);
    }

    protected function defaults()
    {
        return [
            'online' => 0, 'today' => 0, 'today_views' => 0, 'yesterday' => 0, 'week' => 0, 'month' => 0,
            'week_prev' => 0, 'month_prev' => 0,
            'countries' => [], 'cities' => [],
            'chart7_labels' => [], 'chart7_values' => [], 'chart7_dates' => [],
            'chart30_labels' => [], 'chart30_values' => [], 'chart30_dates' => [],
            'flags' => [],
        ];
    }

    protected function cacheId($part, array $visibility)
    {
        return 'stats_' . md5(json_encode([
            6, // data structure version: bump it when cached data changes shape
            $part, $this->url, $this->siteId, $this->shortLang, $this->segment, $visibility, $this->window, $this->rows, $this->compare, $this->strip,
        ]));
    }

    protected function getCache()
    {
        try {
            $handler = Factory::getApplication()->get('cache_handler', '') ?: 'file';

            return Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('output', [
                'defaultgroup' => 'mod_matomo_counter',
                'caching'      => true,
                'storage'      => $handler,
                'lifetime'     => 1440, // minutes: old data is kept for a day so it can be served when Matomo is down
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function readCache($cache, $id)
    {
        if (!$cache) {
            return null;
        }

        try {
            $data = $cache->get($id);
        } catch (\Throwable $e) {
            return null;
        }

        return (is_array($data) && isset($data['meta']['fetched_at'])) ? $data : null;
    }

    protected function writeCache($cache, $id, array $data)
    {
        if ($cache) {
            try {
                $cache->store($data, $id);
            } catch (\Throwable $e) {
                // cache problems must never break the page
            }
        }
    }

    /**
     * Non-blocking file lock. Returns a handle, true (locking not possible, go on) or false (busy).
     */
    protected function acquireLock($id)
    {
        $dir = defined('JPATH_CACHE') ? JPATH_CACHE : sys_get_temp_dir();
        $fp  = @fopen($dir . '/mod_matomo_counter_' . substr(md5($id), 0, 12) . '.lock', 'c');

        if (!$fp) {
            return true;
        }

        if (!@flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);

            return false;
        }

        return $fp;
    }

    protected function releaseLock($lock)
    {
        if (is_resource($lock)) {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function errorResult($code, $arg = null, $part = null)
    {
        $error = ['code' => $code, 'arg' => $arg];

        return ['status' => 'error', 'error' => $error, 'meta' => ['fetched_at' => time(), 'error' => $error, 'part' => $part, 'status' => 'ERROR', 'age' => 0]];
    }

    protected function decorate(array $data, $status, $age, $part, $ttl, $stale = false)
    {
        $data['meta']['status'] = $status;
        $data['meta']['age']    = (int) $age;
        $data['meta']['stale']  = $stale || strpos($status, 'STALE') === 0;
        $data['meta']['part']   = $part;

        if ($status === 'MISS') {
            return $data;
        }

        $prev = $data['debug_info'] ?? [];

        $data['debug_info'] = [
            'source'      => 'CACHE',
            'connection'  => $prev['connection'] ?? 'cURL',
            'http_code'   => $prev['http_code'] ?? '-',
            'cache'       => $status,
            'age'         => $age . ' sec',
            'memory'      => round(memory_get_peak_usage() / 1024 / 1024, 1) . ' MB',
            'php_ver'     => PHP_VERSION,
            'matomo_ver'  => $prev['matomo_ver'] ?? 'Unknown',
            'time'        => $this->getJoomlaLocalTime(),
            'duration'    => '0 ms',
            'size'        => $prev['size'] ?? '-',
            'status'      => $status,
            'api_methods' => $prev['api_methods'] ?? [],
        ];

        return $data;
    }

    protected function getJoomlaLocalTime()
    {
        try {
            $tz = Factory::getApplication()->get('offset') ?: 'Europe/Kyiv';

            return Factory::getDate('now', $tz)->format('H:i:s', true);
        } catch (\Throwable $e) {
            return date('H:i:s');
        }
    }

    /* ------------------------------------------------------------------ *
     * Matomo API
     * ------------------------------------------------------------------ */

    /**
     * @param   array  $visibility  Which blocks are shown
     */
    public function fetchApiData(array $visibility = [])
    {
        $startTime = microtime(true);
        $lang      = '&language=' . $this->shortLang;
        $seg       = $this->segment !== '' ? '&segment=' . rawurlencode($this->segment) : '';
        $rows      = $this->rows;
        $on        = function ($key) use ($visibility) {
            return (bool) ($visibility[$key] ?? true);
        };

        $data = $this->defaults();

        $periods = [
            'day'       => 'period=day&date=today',
            'yesterday' => 'period=day&date=yesterday',
            'week'      => 'period=range&date=last7',
            'month'     => 'period=range&date=last30',
        ];

        $bulk    = [];
        $mapping = [];
        $names   = [];
        $idx     = 0;
        $add     = function ($key, $query, $name) use (&$bulk, &$mapping, &$names, &$idx) {
            $bulk["urls[$idx]"] = $query;
            $mapping[$key]      = $idx;
            $names[$idx]        = $name;
            $idx++;
        };

        $add('version', 'method=API.getMatomoVersion', 'API.getMatomoVersion');

        if ($on('online')) {
            $add('online', 'method=Live.getCounters&lastMinutes=' . $this->window . $seg, 'Live.getCounters');
        }

        if ($on('countries') || $on('cities')) {
            $add(
                'live',
                'method=Live.getLastVisitsDetails&filter_limit=500&minTimestamp=' . (time() - $this->window * 60)
                . '&showColumns=countryCode,country,countryFlag,city,region,visitTimestamp,lastActionTimestamp' . $lang . $seg,
                'Live.getLastVisitsDetails'
            );
        }

        $topLimit = '&filter_limit=' . max(15, $rows * 3) . '&filter_sort_column=nb_visits&filter_sort_order=desc';

        foreach ($periods as $p => $periodQuery) {
            if ($on('top_countries')) {
                $add("top_countries_$p", "method=UserCountry.getCountry&$periodQuery" . $topLimit . $lang . $seg, "UserCountry.getCountry $p");
            }

            if ($on('top_cities')) {
                // City names are not translated by Matomo, so no language here: "Unknown" stays easy to filter out
                $add("top_cities_$p", "method=UserCountry.getCity&$periodQuery" . $topLimit . $seg, "UserCountry.getCity $p");
            }

            foreach (self::TOP_BLOCKS as $key => $def) {
                if ($on($key)) {
                    $add(
                        "{$key}_$p",
                        "method={$def['method']}&$periodQuery{$def['extra']}&filter_limit=" . max(10, $rows * 2)
                        . "&filter_sort_column={$def['metric']}&filter_sort_order=desc"
                        . ($def['lang'] ? $lang : '') . $seg,
                        "{$def['method']} $p"
                    );
                }
            }
        }

        if ($on('today') || $on('today_views')) {
            $add('summary_today', 'method=VisitsSummary.get&period=day&date=today' . $seg, 'VisitsSummary.get today');
        }

        if ($on('yesterday')) {
            $add('summary_yesterday', 'method=VisitsSummary.get&period=day&date=yesterday' . $seg, 'VisitsSummary.get yesterday');
        }

        $need7  = $on('chart') || $on('week');
        $need30 = $on('chart') || $on('month');

        if ($this->compare && ($need7 || $need30)) {
            // One request for both periods and the periods before them
            $add('chart60', 'method=VisitsSummary.get&period=day&date=last60' . $seg, 'VisitsSummary.get last60');
        } else {
            if ($need7) {
                $add('chart7', 'method=VisitsSummary.get&period=day&date=last7' . $seg, 'VisitsSummary.get last7');
            }
            if ($need30) {
                $add('chart30', 'method=VisitsSummary.get&period=day&date=last30' . $seg, 'VisitsSummary.get last30');
            }
        }

        $resData = $this->makeRequest(array_merge([
            'module'     => 'API',
            'method'     => 'API.getBulkRequest',
            'idSite'     => $this->siteId,
            'format'     => 'JSON',
            'token_auth' => $this->token,
            'language'   => $this->shortLang,
        ], $bulk));

        if (!empty($resData['error'])) {
            return ['status' => 'error', 'error' => $resData['error']];
        }

        $res = $resData['response'];

        if (isset($res['result']) && $res['result'] === 'error') {
            return ['status' => 'error', 'error' => ['code' => 'api', 'arg' => (string) ($res['message'] ?? '')]];
        }

        $warnings = [];
        $get      = function ($key) use ($res, $mapping, $names, &$warnings) {
            if (!isset($mapping[$key])) {
                return null;
            }

            $r = $res[$mapping[$key]] ?? null;

            if (!is_array($r)) {
                return null;
            }

            if (($r['result'] ?? '') === 'error') {
                $warnings[] = $names[$mapping[$key]] . ': ' . strip_tags((string) ($r['message'] ?? 'error'));

                return null;
            }

            return $r;
        };

        $matomoVersion = ($v = $get('version')) ? ($v['value'] ?? 'Unknown') : 'Unknown';
        $flags         = [];

        // 1. Top countries (also builds the translated country names)
        $countryNames = [];

        foreach (array_keys($periods) as $p) {
            if (($rowsData = $get("top_countries_$p")) === null) {
                continue;
            }

            $temp = [];

            foreach ($rowsData as $c) {
                if (!is_array($c) || !isset($c['code']) || strtolower($c['code']) === 'xx') {
                    continue;
                }

                $code = strtolower((string) $c['code']);

                if (!preg_match('/^[a-z]{2}$/', $code)) {
                    continue;
                }

                $name                = (string) ($c['label'] ?? $code);
                $countryNames[$code] = $name;
                $this->rememberFlag($flags, $code, $c['logo'] ?? null);

                $temp[$code] = ['name' => $name, 'count' => (int) ($c['nb_visits'] ?? 0)];
            }

            $data["top_countries_$p"] = $this->topSlice($temp, $rows);
        }

        // 2. Online counter
        if (($r = $get('online')) !== null && isset($r[0]['visits'])) {
            $data['online'] = (int) $r[0]['visits'];
        }

        // 3. Online countries and cities (one Live.getLastVisitsDetails response)
        if (($live = $get('live')) !== null) {
            $now             = time();
            $onlineCountries = [];
            $onlineCities    = [];

            foreach ($live as $v) {
                if (!is_array($v)) {
                    continue;
                }

                $lastAction = $v['lastActionTimestamp'] ?? $v['visitTimestamp'] ?? 0;

                if ($lastAction <= ($now - $this->window * 60)) {
                    continue;
                }

                $code = strtolower((string) ($v['countryCode'] ?? ''));
                $code = (preg_match('/^[a-z]{2}$/', $code) && $code !== 'xx') ? $code : '';

                if ($code !== '') {
                    $this->rememberFlag($flags, $code, $v['countryFlag'] ?? null);
                }

                $countryName = $this->countryLabel($code, $countryNames[$code] ?? trim((string) ($v['country'] ?? '')));

                if ($on('countries')) {
                    $ck = $code !== '' ? $code : 'unknown';

                    if (!isset($onlineCountries[$ck])) {
                        $onlineCountries[$ck] = ['name' => $code !== '' ? $countryName : '?', 'count' => 0];
                    }
                    $onlineCountries[$ck]['count']++;
                }

                if ($on('cities')) {
                    $city = trim((string) ($v['city'] ?? ''));

                    if ($this->isUnknownCity($city)) {
                        continue;
                    }

                    $region = trim((string) ($v['region'] ?? ''));
                    $key    = $code . '|' . $this->lower($region) . '|' . $this->lower($city);

                    if (!isset($onlineCities[$key])) {
                        $onlineCities[$key] = [
                            'name'  => $city,
                            'code'  => $code,
                            'title' => implode(', ', array_filter([$city, $region, $countryName])),
                            'count' => 0,
                        ];
                    }
                    $onlineCities[$key]['count']++;
                }
            }

            $data['countries'] = $this->topSlice($onlineCountries, $rows);
            $data['cities']    = $this->topSlice($onlineCities, $rows);
        }

        // 4. Top cities
        foreach (array_keys($periods) as $p) {
            if (($rowsData = $get("top_cities_$p")) === null) {
                continue;
            }

            $temp = [];

            foreach ($rowsData as $c) {
                if (!is_array($c)) {
                    continue;
                }

                $city = trim((string) ($c['city_name'] ?? ''));

                if ($city === '' && !empty($c['label'])) {
                    $city = trim(explode(',', (string) $c['label'])[0]);
                }

                if ($this->isUnknownCity($city)) {
                    continue;
                }

                $code = strtolower((string) ($c['country'] ?? $c['code'] ?? ''));
                $code = (preg_match('/^[a-z]{2}$/', $code) && $code !== 'xx') ? $code : '';

                if ($code !== '') {
                    $this->rememberFlag($flags, $code, $c['logo'] ?? null);
                }

                $region  = trim((string) ($c['region_name'] ?? $c['region'] ?? ''));
                $country = trim((string) ($c['country_name'] ?? ''));
                $key     = $code . '|' . $this->lower($region) . '|' . $this->lower($city);

                if (!isset($temp[$key])) {
                    $temp[$key] = ['name' => $city, 'code' => $code, 'title' => implode(', ', array_filter([$city, $region, $country])), 'count' => 0];
                }
                $temp[$key]['count'] += (int) ($c['nb_visits'] ?? 0);
            }

            $data["top_cities_$p"] = $this->topSlice($temp, $rows);
        }

        // 5. Pages, referrers, device types
        foreach (self::TOP_BLOCKS as $key => $def) {
            foreach (array_keys($periods) as $p) {
                if (($rowsData = $get("{$key}_$p")) === null) {
                    continue;
                }

                $temp = [];

                foreach ($rowsData as $c) {
                    if (!is_array($c)) {
                        continue;
                    }

                    // Matomo may return labels HTML-encoded (&amp;, &quot;); the template escapes them once
                    $label = trim(html_entity_decode((string) ($c['label'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    if ($label === '') {
                        continue;
                    }

                    if ($key === 'top_pages') {
                        $label = $this->cleanTitle($label);
                    }

                    $title = isset($c['url']) ? trim(html_entity_decode((string) $c['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : $label;

                    if (!isset($temp[$label])) {
                        $temp[$label] = ['name' => $label, 'title' => $title, 'count' => 0];
                    }
                    $temp[$label]['count'] += (int) ($c[$def['metric']] ?? 0);
                }

                $data["{$key}_$p"] = $this->topSlice($temp, $rows);
            }
        }

        // 6. Summaries
        if (($s = $get('summary_today')) !== null) {
            $data['today']       = (int) ($s['nb_visits'] ?? 0);
            $data['today_views'] = (int) ($s['nb_actions'] ?? 0);
        }

        if (($s = $get('summary_yesterday')) !== null) {
            $data['yesterday'] = (int) ($s['nb_visits'] ?? 0);
        }

        // 7. Chart series
        $series = [];
        $prev   = [7 => 0, 30 => 0];

        if (isset($mapping['chart60'])) {
            $all = $this->parseDays($get('chart60'));
            $cnt = count($all);

            $series[7]  = array_slice($all, -7, 7, true);
            $series[30] = array_slice($all, -30, 30, true);

            if ($cnt >= 14) {
                $prev[7] = array_sum(array_slice($all, -14, 7, true));
            }
            if ($cnt >= 60) {
                $prev[30] = array_sum(array_slice($all, -60, 30, true));
            }
        } else {
            $series[7]  = $this->parseDays($get('chart7'));
            $series[30] = $this->parseDays($get('chart30'));
        }

        foreach ([7 => ['chart7', 'week'], 30 => ['chart30', 'month']] as $n => $keys) {
            foreach ($series[$n] ?? [] as $date => $visits) {
                $data[$keys[0] . '_dates'][]  = $date;
                $data[$keys[0] . '_labels'][] = date('d M', strtotime($date));
                $data[$keys[0] . '_values'][] = $visits;
                $data[$keys[1]]              += $visits;
            }

            $data[$keys[1] . '_prev'] = $prev[$n];
        }

        $data['flags']    = $flags;
        $data['warnings'] = $warnings;

        $sizes = [];

        foreach ($res as $i => $methodData) {
            if (isset($names[$i])) {
                $sizes[$names[$i]] = round(strlen(json_encode($methodData)) / 1024, 1) . ' KB';
            }
        }

        $data['debug_info'] = [
            'source'      => 'API',
            'connection'  => 'cURL',
            'http_code'   => $resData['http_code'] ?? 200,
            'cache'       => 'MISS',
            'age'         => '0 sec',
            'memory'      => round(memory_get_peak_usage() / 1024 / 1024, 1) . ' MB',
            'php_ver'     => PHP_VERSION,
            'matomo_ver'  => $matomoVersion,
            'time'        => $this->getJoomlaLocalTime(),
            'duration'    => round((microtime(true) - $startTime) * 1000) . ' ms',
            'size'        => round(strlen(json_encode($res)) / 1024, 2) . ' KB',
            'status'      => 'Success',
            'api_methods' => $sizes,
        ];

        return $data;
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    /**
     * Country name for a code: the name Matomo gave us (same language as the top list), otherwise the PHP intl
     * name in the module language, otherwise the upper-case code.
     */
    protected function countryLabel($code, $name)
    {
        $name = trim((string) $name);

        if ($code === '' || ($name !== '' && strcasecmp($name, $code) !== 0)) {
            return $name;
        }

        if (class_exists('\\Locale')) {
            $intl = \Locale::getDisplayRegion('-' . strtoupper($code), $this->shortLang);

            if (is_string($intl) && $intl !== '' && strcasecmp($intl, $code) !== 0) {
                return $intl;
            }
        }

        return strtoupper($code);
    }

    protected function parseDays($r)
    {
        $days = [];

        if (is_array($r)) {
            foreach ($r as $date => $m) {
                if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $days[$date] = is_array($m) ? (int) ($m['nb_visits'] ?? 0) : 0;
                }
            }
            ksort($days);
        }

        return $days;
    }

    protected function topSlice(array $items, $limit)
    {
        // Rows without visits (Matomo lists e.g. every device type) are noise
        $items = array_filter($items, function ($item) {
            return (int) ($item['count'] ?? 0) > 0;
        });

        uasort($items, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_slice($items, 0, $limit, true);
    }

    /**
     * Remembers the flag path Matomo itself reports (relative path on the Matomo server).
     */
    protected function rememberFlag(array &$flags, $code, $path)
    {
        if (isset($flags[$code]) || !is_string($path)) {
            return;
        }

        $path = ltrim(trim($path), '/');

        if ($path !== '' && strpos($path, '..') === false && preg_match('#^[A-Za-z0-9_\-./]{1,200}\.(png|svg|gif)$#', $path)) {
            $flags[$code] = $path;
        }
    }

    /**
     * Cuts the configured texts (usually the site name) off the START or the END of a page title, together with the
     * separator next to them ("Title | Site" -> "Title"). The middle of a title is never touched.
     * A title that would become empty (e.g. the home page called just like the site) stays as it is.
     */
    protected function cleanTitle($title)
    {
        if (!$this->strip) {
            return $title;
        }

        $sep   = '[\s|\-–—:·•]*';
        $clean = $title;

        foreach ($this->strip as $text) {
            $q     = preg_quote($text, '/');
            $clean = preg_replace('/^(?:' . $q . ')' . $sep . '|' . $sep . '(?:' . $q . ')$/iu', '', trim($clean)) ?? $clean;
        }

        $clean = trim(preg_replace('/^[\s|\-–—:·•]+|[\s|\-–—:·•]+$/u', '', $clean) ?? $clean);

        return ($clean !== '' && $clean !== $title) ? $clean : $title;
    }

    protected function lower($str)
    {
        return function_exists('mb_strtolower') ? mb_strtolower((string) $str, 'UTF-8') : strtolower((string) $str);
    }

    /**
     * Matomo returns an empty / "Unknown" city when the GeoIP database has no city-level data.
     */
    protected function isUnknownCity($name)
    {
        $n = $this->lower(trim((string) $name));

        return in_array($n, ['', 'unknown', 'xx', 'неизвестно', 'невідомо', 'невідоме', 'неизвестный', 'невідомий'], true);
    }

    /**
     * Returns ['response' => array, 'http_code' => int] or ['error' => ['code' => ..., 'arg' => ...]].
     */
    protected function makeRequest($params)
    {
        if (!function_exists('curl_init')) {
            return ['error' => ['code' => 'curl', 'arg' => null]];
        }

        $ch = curl_init($this->url . '/index.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(3, $this->timeout),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code === 0) {
            return ['error' => ['code' => 'connect', 'arg' => $error ?: 'no response']];
        }

        if ($code !== 200) {
            return ['error' => ['code' => 'http', 'arg' => $code]];
        }

        $json = json_decode($body, true);

        if (!is_array($json)) {
            return ['error' => ['code' => 'json', 'arg' => null]];
        }

        return ['response' => $json, 'http_code' => $code];
    }
}
