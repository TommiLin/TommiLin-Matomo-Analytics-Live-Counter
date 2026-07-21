<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 TommiLin. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use TommiLin\Module\MatomoCounter\Site\Helper\MatomoCounterHelper;

if (file_exists(__DIR__ . '/src/Helper/MatomoCounterHelper.php')) {
    require_once __DIR__ . '/src/Helper/MatomoCounterHelper.php';
}

$matomoUrl     = rtrim($params->get('matomo_url', ''), '/');
$siteId        = $params->get('site_id', 1);
$tokenAuth     = $params->get('token_auth', '');
$cacheTime     = (int) $params->get('cache_time', 300);
$forceLanguage = $params->get('force_language', 'site');

// Get the debug mode value (default 'super')
$debugParam    = $params->get('debug_mode', 'super');

if (empty($matomoUrl) || empty($tokenAuth)) {
    return;
}

// === FORCE LANGUAGE LOGIC FOR JOOMLA STATIC STRINGS ===
if ($forceLanguage !== 'site') {
    $lang = Factory::getApplication()->getLanguage();
    
    if ($lang->getTag() !== $forceLanguage) {
        $reflector = new \ReflectionClass($lang);
        if ($reflector->hasProperty('paths')) {
            $property = $reflector->getProperty('paths');
            $property->setAccessible(true);
            $paths = $property->getValue($lang);
            unset($paths['mod_matomo_counter']);
            $property->setValue($lang, $paths);
        }

        $lang->load('mod_matomo_counter', JPATH_BASE, $forceLanguage, true);
        $lang->load('mod_matomo_counter', __DIR__, $forceLanguage, true);
    }
}
// =========================================================

$user = Factory::getApplication()->getIdentity();
$isAdmin = $user->authorise('core.admin');

// === DEBUG MODE CALCULATION ===
$debugMode = false;
if ($debugParam === '1') {
    $debugMode = true;
} elseif ($debugParam === 'super' && $isAdmin) {
    $debugMode = true;
}
// ==============================================

$checkAccess = function($paramName) use ($params, $isAdmin) {
    $val = (int) $params->get($paramName, 1);
    if ($val === 0) return false;
    if ($val === 2 && !$isAdmin) return false;
    return true;
};

// Form a visibility array (use keys that are understood by the helper)
$visibility = [
    'online'        => $checkAccess('show_online'),
    'today'         => $checkAccess('show_today'),
    'today_views'   => $checkAccess('show_today_views'),
    'yesterday'     => $checkAccess('show_yesterday'),
    'week'          => $checkAccess('show_week'),
    'month'         => $checkAccess('show_month'),
    'countries'     => $checkAccess('show_countries'),
    'top_countries' => $checkAccess('show_top_countries'),
    'chart'         => $checkAccess('show_chart'),
];

if (!array_filter($visibility)) {
    return;
}

// Create an instance of the helper
$helper = new MatomoCounterHelper($matomoUrl, $siteId, $tokenAuth, $cacheTime, $forceLanguage);

// Call the method with a visibility array to optimize the query
// Important: In the helper itself, the `debug_info` collection logic must rely on internal flags,
// or you can force the $debugMode to be passed if the helper supports it.
$stats = $helper->getMatomoData($visibility);

// Connect the layout
require ModuleHelper::getLayoutPath('mod_matomo_counter', $params->get('layout', 'default'));