<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 Your Name. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') || die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Factory;

// --- FORCE LANGUAGE OVERRIDE BLOCK ---
// Check if a specific language is forced in module settings
$forceLang = $params->get('force_language', 'site');
if ($forceLang !== 'site') {
    $lang = Factory::getLanguage();
    
    // Define the exact path to the module's local language folder
    $moduleLanguagePath = JPATH_SITE . '/modules/mod_matomo_counter';
    
    // Overwrite loaded language constants with the forced locale
    $lang->load('mod_matomo_counter', $moduleLanguagePath, $forceLang, true);
}
// --------------------------------------

// 1. Get module settings
$matomoUrl = rtrim($params->get('matomo_url'), '/') . '/index.php';
$siteId    = (int) $params->get('site_id', 1);
$tokenAuth = trim($params->get('token_auth'));
$cacheTime = (int) $params->get('cache_time', 300); 

// Read metric visibility preferences (1 = show, 0 = hide)
$show = [
    'online'      => (int) $params->get('show_online', 1),
    'today'       => (int) $params->get('show_today', 1),
    'today_views' => (int) $params->get('show_today_views', 0),
    'week'        => (int) $params->get('show_week', 1),
    'month'       => (int) $params->get('show_month', 0),
];

// Default stats structure
$stats = ['online' => 0, 'today' => 0, 'today_views' => 0, 'week' => 0, 'month' => 0];

// 2. File-based caching configuration
$cacheFile = JPATH_CACHE . '/mod_matomo_counter_id' . $siteId . '.json';
$readFromCache = false;

// Check if cache file exists and is still valid (fresh)
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    $cacheData = json_decode(file_get_contents($cacheFile), true);
    if (is_array($cacheData)) {
        $stats = array_merge($stats, $cacheData);
        $readFromCache = true;
    }
}

// 3. If cache is expired or missing, fetch fresh data from Matomo
if (!$readFromCache && $tokenAuth && $matomoUrl) {
    // Mapping of all potential Matomo API queries
    $allMethods = [
        'online'      => 'method=Live.getCounters&lastMinutes=3',
        'today'       => 'method=VisitsSummary.getUniqueVisitors&period=day&date=today',
        'today_views' => 'method=VisitsSummary.getActions&period=day&date=today',
        'week'        => 'method=VisitsSummary.getVisits&period=range&date=last7',
        'month'       => 'method=VisitsSummary.getVisits&period=range&date=last30',
    ];

    $urlParams = [
        'module' => 'API',
        'method' => 'API.getBulkRequest',
        'idSite' => $siteId,
        'format' => 'json'
    ];

    // Build the bulk request parameters only for active (enabled) metrics
    $activeKeys = [];
    $i = 0;
    foreach ($allMethods as $key => $methodStr) {
        if ($show[$key] === 1) {
            $urlParams["urls[$i]"] = $methodStr;
            $activeKeys[$i] = $key; // Track the response position index
            $i++;
        }
    }

    // Execute API call if at least one metric is requested
    if (!empty($activeKeys)) {
        $apiUrl = $matomoUrl . '?' . http_build_query($urlParams);
        $postFields = ['token_auth' => $tokenAuth];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4); // 4-second fail-safe timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $responseBody) {
            $data = json_decode($responseBody, true);
            if (is_array($data)) {
                // Parse Matomo's response array using tracked indices
                foreach ($activeKeys as $index => $key) {
                    if (isset($data[$index])) {
                        if ($key === 'online') {
                            $stats['online'] = (int) ($data[$index][0]['visits'] ?? 0);
                        } elseif ($key === 'today_views') {
                            $stats['today_views'] = (int) ($data[$index]['value'] ?? 0);
                        } else {
                            $stats[$key] = (int) ($data[$index]['value'] ?? 0);
                        }
                    }
                }
                // Save compiled metrics to cache file
                @file_put_contents($cacheFile, json_encode($stats));
            }
        } else if (file_exists($cacheFile)) {
            // Fallback: use expired cache data if Matomo API is unreachable
            $stats = json_decode(file_get_contents($cacheFile), true) ?? $stats;
        }
    }
}

// Render template output
require ModuleHelper::getLayoutPath('mod_matomo_counter', $params->get('layout', 'default'));