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

use Joomla\CMS\Factory;

class MatomoCounterHelper 
{
    protected $url;
    protected $siteId;
    protected $token;
    protected $cacheTime;
    protected $shortLang;

    public function __construct($url, $siteId, $token, $cacheTime, $langCode = 'site') 
    {
        $this->url       = rtrim($url, '/');
        $this->siteId    = (int) $siteId;
        $this->token     = $token;
        $this->cacheTime = (int) $cacheTime;
        
        // Determine language: if 'site' use current Joomla language, otherwise use forced language parameter
        $lc              = ($langCode === 'site' || empty($langCode)) 
                           ? Factory::getApplication()->getLanguage()->getTag() 
                           : $langCode;

        // Convert to 2-letter ISO code (e.g., uk, en, de, pl)
        $this->shortLang = strtolower(substr($lc, 0, 2));
    }

    protected function getJoomlaLocalTime()
    {
        try {
            $app = Factory::getApplication();
            $tz  = $app->get('offset') ?: 'Europe/Kyiv';
            return Factory::getDate('now', $tz)->format('H:i:s', true);
        } catch (\Exception $e) { 
            return date('H:i:s'); 
        }
    }

    public function getMatomoData($visibility = []) 
    {
        $cacheDir  = JPATH_CACHE . '/mod_matomo_counter';
        $visHash   = !empty($visibility) ? md5(json_encode($visibility)) : 'default';
        
        // Include language in the cache file name to allow proper cache invalidation on language change
        $cacheId   = 'matomo_stats_site_' . $this->siteId . '_' . $this->shortLang . '_' . $visHash . '.json';
        $cacheFile = $cacheDir . '/' . $cacheId;

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheTime)) {
            $age  = time() - filemtime($cacheFile);
            $raw  = @file_get_contents($cacheFile);
            $data = $raw ? json_decode($raw, true) : null;

            if (is_array($data)) {
                $data['debug_info'] = [
                    'source'      => 'CACHE',
                    'connection'  => $data['debug_info']['connection'] ?? 'cURL',
                    'http_code'   => '304',
                    'cache'       => 'HIT',
                    'age'         => $age . ' sec',
                    'memory'      => round(memory_get_peak_usage() / 1024 / 1024, 1) . ' MB',
                    'php_ver'     => PHP_VERSION,
                    'matomo_ver'  => $data['debug_info']['matomo_ver'] ?? 'Unknown',
                    'time'        => $this->getJoomlaLocalTime(),
                    'duration'    => '0 ms',
                    'size'        => round(filesize($cacheFile) / 1024, 2) . ' KB',
                    'status'      => 'Loaded from cache',
                    'api_methods' => $data['debug_info']['api_methods'] ?? []
                ];
                return $data;
            }
        }

        $data = $this->fetchApiData($visibility);

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        if (!isset($data['status']) || $data['status'] !== 'error') {
            $tempFile = $cacheFile . '.' . uniqid('tmp_', true);
            if (@file_put_contents($tempFile, json_encode($data)) !== false) {
                @rename($tempFile, $cacheFile);
            }
        }

        return $data;
    }

    public function fetchApiData($visibility = []) 
    {
        $startTime = microtime(true);
        $langParam = '&language=' . $this->shortLang;

        $data = [
            'online' => 0, 'today' => 0, 'today_views' => 0, 'week' => 0, 'month' => 0,
            'countries' => [], 'top_countries_day' => [], 'top_countries_week' => [], 'top_countries_month' => [],
            'chart7_labels' => [], 'chart7_values' => [], 'chart30_labels' => [], 'chart30_values' => []
        ];
        
        $bulkParams = [];
        $mapping    = [];
        $idx        = 0;

        $bulkParams["urls[$idx]"] = 'method=API.getMatomoVersion';
        $mapping['matomo_version'] = $idx++;

        if ($visibility['online'] ?? true) { 
            $bulkParams["urls[$idx]"] = 'method=Live.getCounters&lastMinutes=3'; 
            $mapping['online'] = $idx++; 
        }
        
        if ($visibility['countries'] ?? true) { 
            // Pass language parameter explicitly to Live.getLastVisitsDetails
            $bulkParams["urls[$idx]"] = 'method=Live.getLastVisitsDetails&filter_limit=100&showColumns=countryCode,countryName,visitTimestamp,lastActionTimestamp' . $langParam; 
            $mapping['countries_online'] = $idx++; 
        }
        
        if ($visibility['top_countries'] ?? true) {
            $bulkParams["urls[$idx]"] = 'method=UserCountry.getCountry&period=day&date=today' . $langParam; 
            $mapping['top_day'] = $idx++;
            
            $bulkParams["urls[$idx]"] = 'method=UserCountry.getCountry&period=range&date=last7' . $langParam; 
            $mapping['top_week'] = $idx++;
            
            $bulkParams["urls[$idx]"] = 'method=UserCountry.getCountry&period=range&date=last30' . $langParam; 
            $mapping['top_month'] = $idx++;
        }

        if (($visibility['today'] ?? true) || ($visibility['today_views'] ?? true)) { 
            $bulkParams["urls[$idx]"] = 'method=VisitsSummary.get&period=day&date=today'; 
            $mapping['summary_today'] = $idx++; 
        }

        if (($visibility['chart'] ?? true) || ($visibility['week'] ?? true)) { 
            $bulkParams["urls[$idx]"] = 'method=VisitsSummary.get&period=day&date=last7'; 
            $mapping['chart7'] = $idx++; 
        }

        if (($visibility['chart'] ?? true) || ($visibility['month'] ?? true)) { 
            $bulkParams["urls[$idx]"] = 'method=VisitsSummary.get&period=day&date=last30'; 
            $mapping['chart30'] = $idx++; 
        }

        // Pass 'language' parameter at top level to force bulk request translation
        $resData = $this->makeRequest(array_merge([
            'module'     => 'API', 
            'method'     => 'API.getBulkRequest', 
            'idSite'     => $this->siteId, 
            'format'     => 'JSON', 
            'token_auth' => $this->token,
            'language'   => $this->shortLang
        ], $bulkParams));

        $res = $resData['response'] ?? null;

        if (!$res || (isset($res['status']) && $res['status'] === 'error')) {
            return ['status' => 'error', 'message' => $res['message'] ?? 'API Error'];
        }

        // Matomo version
        $matomoVersion = $res[$mapping['matomo_version']]['value'] ?? 'Unknown';

        // 1. Process TOP countries first
        $countryTranslations = [];
        $topMap = ['top_day' => 'top_countries_day', 'top_week' => 'top_countries_week', 'top_month' => 'top_countries_month'];

        foreach ($topMap as $mKey => $dKey) {
            if (isset($mapping[$mKey]) && is_array($res[$mapping[$mKey]])) {
                $temp = [];
                foreach ($res[$mapping[$mKey]] as $c) {
                    if (isset($c['code']) && $c['code'] !== 'xx') {
                        $code = strtolower($c['code']);
                        $name = $c['label'] ?? $code;

                        // Cache translation for use in online countries section
                        $countryTranslations[$code] = $name;

                        $temp[$code] = [
                            'name'  => $name, 
                            'count' => (int)($c['nb_visits'] ?? 0)
                        ];
                    }
                }
                
                // Sort descending using standard closure for PHP < 7.4 compatibility
                uasort($temp, function($a, $b) {
                    return $b['count'] - $a['count'];
                });
                
                $data[$dKey] = array_slice($temp, 0, 5, true);
            }
        }

        // 2. Online counter
        if (isset($mapping['online']) && isset($res[$mapping['online']][0]['visits'])) { 
            $data['online'] = (int) $res[$mapping['online']][0]['visits']; 
        }

        // 3. Online countries (Live.getLastVisitsDetails)
        if (isset($mapping['countries_online']) && is_array($res[$mapping['countries_online']])) {
            $onlineCountries = [];
            $now = time();

            foreach ($res[$mapping['countries_online']] as $v) {
                $lastAction = $v['lastActionTimestamp'] ?? $v['visitTimestamp'] ?? 0;
                
                if ($lastAction > ($now - 180)) {
                    $code = !empty($v['countryCode']) ? strtolower($v['countryCode']) : 'unknown';

                    if (isset($countryTranslations[$code])) {
                        $name = $countryTranslations[$code];
                    } else {
                        $name = $v['countryName'] ?? strtoupper($code);
                    }

                    if (!isset($onlineCountries[$code])) {
                        $onlineCountries[$code] = ['name' => $name, 'count' => 0];
                    }
                    $onlineCountries[$code]['count']++;
                }
            }

            // Sort descending using standard closure for PHP < 7.4 compatibility
            uasort($onlineCountries, function($a, $b) {
                return $b['count'] - $a['count'];
            });

            $data['countries'] = array_slice($onlineCountries, 0, 5, true);
        }

        // 4. Today summary
        if (isset($mapping['summary_today']) && is_array($res[$mapping['summary_today']])) { 
            $s = $res[$mapping['summary_today']]; 
            $data['today']       = $s['nb_visits'] ?? 0; 
            $data['today_views'] = $s['nb_actions'] ?? 0; 
        }

        // 5. 7-day chart data
        if (isset($mapping['chart7']) && is_array($res[$mapping['chart7']])) { 
            foreach ($res[$mapping['chart7']] as $d => $m) { 
                $data['chart7_labels'][] = date('d M', strtotime($d)); 
                $data['chart7_values'][] = $m['nb_visits'] ?? 0; 
                $data['week']            += $m['nb_visits'] ?? 0; 
            } 
        }

        // 6. 30-day chart data
        if (isset($mapping['chart30']) && is_array($res[$mapping['chart30']])) { 
            $i = 0; 
            foreach ($res[$mapping['chart30']] as $d => $m) { 
                $data['chart30_labels'][] = ($i % 5 === 0) ? date('d M', strtotime($d)) : ''; 
                $data['chart30_values'][] = $m['nb_visits'] ?? 0; 
                $data['month']           += $m['nb_visits'] ?? 0; 
                $i++; 
            } 
        }

        // Debug info mapping
        $methodNames = [0 => 'API.getMatomoVersion'];
        if (isset($mapping['online']))           $methodNames[$mapping['online']]           = 'Live.getCounters';
        if (isset($mapping['countries_online'])) $methodNames[$mapping['countries_online']] = 'Live.getLastVisitsDetails';
        if (isset($mapping['top_day']))           $methodNames[$mapping['top_day']]           = 'UserCountry day';
        if (isset($mapping['top_week']))          $methodNames[$mapping['top_week']]          = 'UserCountry last7';
        if (isset($mapping['top_month']))         $methodNames[$mapping['top_month']]         = 'UserCountry last30';
        if (isset($mapping['summary_today']))     $methodNames[$mapping['summary_today']]     = 'VisitsSummary.get';
        if (isset($mapping['chart7']))            $methodNames[$mapping['chart7']]            = 'VisitsSummary.get last7';
        if (isset($mapping['chart30']))           $methodNames[$mapping['chart30']]           = 'VisitsSummary.get last30';

        $methodsSizes = [];
        if (is_array($res)) {
            foreach ($res as $index => $methodData) {
                if (isset($methodNames[$index])) {
                    $name = $methodNames[$index];
                    $bytes = strlen(json_encode($methodData));
                    $methodsSizes[$name] = round($bytes / 1024, 1) . ' KB';
                }
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
            'api_methods' => $methodsSizes
        ];

        return $data;
    }

    protected function makeRequest($params) 
    {
        $ch = curl_init("{$this->url}/index.php");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code !== 200 || empty($r)) {
            return ['response' => null, 'http_code' => $code];
        }
        
        return ['response' => json_decode($r, true), 'http_code' => $code];
    }
}
