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
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Builds the module output. Used by the module entry point and by the AJAX refresh (com_ajax).
 */
class ModuleRenderer
{
    /**
     * @param   object                     $module         Module object
     * @param   \Joomla\Registry\Registry  $params         Module parameters
     * @param   boolean                    $innerOnly      True for AJAX: return the content without the wrapper
     * @param   string|null                $requestedLang  Language tag requested by the AJAX call
     * @param   boolean                    $force          Manual refresh (administrators only): ask Matomo now
     */
    public static function render($module, $params, $innerOnly = false, $requestedLang = null, $force = false)
    {
        $app     = Factory::getApplication();
        $user    = $app->getIdentity();
        $isAdmin = $user && $user->authorise('core.admin');
        $force   = $force && $isAdmin;

        $matomoUrl = rtrim(trim((string) $params->get('matomo_url', '')), '/');
        $token     = (string) $params->get('token_auth', '');
        $tag       = Translator::resolveTag($params->get('force_language', 'site'), $requestedLang);
        $t         = new Translator($tag);

        if ($matomoUrl === '' || $token === '') {
            return $isAdmin ? static::wrap($module, $params, $tag, static::alert($t->t('MOD_MATOMO_COUNTER_ERR_NOT_CONFIGURED')), $innerOnly) : '';
        }

        if (!preg_match('#^https?://#i', $matomoUrl)) {
            return $isAdmin ? static::wrap($module, $params, $tag, static::alert(static::errorText($t, ['code' => 'url'])), $innerOnly) : '';
        }

        $debugParam = $params->get('debug_mode', 'super');
        $debugMode  = $debugParam === '1' || ($debugParam === 'super' && $isAdmin);

        $checkAccess = function ($name, $default = 1) use ($params, $isAdmin) {
            $val = (int) $params->get($name, $default);

            return !($val === 0 || ($val === 2 && !$isAdmin));
        };

        $visibility = [
            'online'        => $checkAccess('show_online'),
            'today'         => $checkAccess('show_today'),
            'today_views'   => $checkAccess('show_today_views'),
            'yesterday'     => $checkAccess('show_yesterday'),
            'week'          => $checkAccess('show_week'),
            'month'         => $checkAccess('show_month'),
            'countries'     => $checkAccess('show_countries'),
            'top_countries' => $checkAccess('show_top_countries'),
            'cities'        => $checkAccess('show_cities'),
            'top_cities'    => $checkAccess('show_top_cities'),
            'top_pages'     => $checkAccess('show_top_pages', 0),
            'top_referrers' => $checkAccess('show_top_referrers', 0),
            'top_devices'   => $checkAccess('show_devices', 0),
            'chart'         => $checkAccess('show_chart'),
        ];

        if (!array_filter($visibility)) {
            return '';
        }

        $window = (int) $params->get('online_window', 30);
        $rows   = (int) $params->get('rows_limit', 5);

        $helper = new MatomoCounterHelper([
            'url'        => $matomoUrl,
            'site_id'    => $params->get('site_id', 1),
            'token'      => $token,
            'cache_time' => $params->get('cache_time', 300),
            'language'   => $t->getShortTag(),
            'verify_ssl' => (int) $params->get('verify_ssl', 1),
            'timeout'    => $params->get('request_timeout', 4),
            'window'     => $window,
            'rows'       => $rows,
            'segment'    => $params->get('segment', ''),
            'compare'    => (int) $params->get('compare_previous', 0),
            'strip'      => (string) $params->get('title_strip', ''),
        ]);

        $stats      = $helper->getData($visibility, $force);
        $error      = $stats['meta']['error'] ?? null;
        $errorText  = $error ? static::errorText($t, $error) : '';
        $hasData    = ($stats['status'] ?? '') !== 'error';

        if (!$hasData) {
            // Visitors never see a broken module; administrators see what is wrong
            return $isAdmin ? static::wrap($module, $params, $tag, static::alert($errorText), $innerOnly) : '';
        }

        $chartStyle = $params->get('chart_style', 'line') === 'bars' ? 'bars' : 'line';
        $canRefresh = $isAdmin && (int) ($module->id ?? 0) > 0;
        $window     = in_array($window, [5, 10, 15, 30, 60], true) ? $window : 30;

        ob_start();
        require ModuleHelper::getLayoutPath('mod_matomo_counter', $params->get('layout', 'default'));
        $html = ob_get_clean();

        return static::wrap($module, $params, $tag, $html, $innerOnly);
    }

    protected static function wrap($module, $params, $tag, $html, $innerOnly)
    {
        if ($innerOnly) {
            return $html;
        }

        $app = Factory::getApplication();
        $id  = (int) ($module->id ?? 0);

        $doc = $app->getDocument();

        if ($doc && method_exists($doc, 'getWebAssetManager')) {
            $wa = $doc->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('mod_matomo_counter');
            $wa->useStyle('mod_matomo_counter.counter')->useScript('mod_matomo_counter.counter');
        }

        $scheme  = $params->get('color_scheme', 'auto');
        $user    = $app->getIdentity();
        $isAdmin = $user && $user->authorise('core.admin');

        $class = 'matomo-live-wrap' . ($scheme === 'dark' ? ' matomo-theme-dark' : ($scheme === 'light' ? ' matomo-theme-light' : ''));
        $attrs = '';

        // The AJAX address is only used by the administrator's manual refresh button
        if ($id > 0 && $isAdmin) {
            $url = Uri::root(true) . '/index.php?option=com_ajax&module=matomo_counter&method=get&format=json'
                . '&id=' . $id
                . '&Itemid=' . (int) $app->getInput()->getInt('Itemid')
                . '&ml=' . rawurlencode($tag);

            $attrs .= ' data-ajax-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<div class="' . $class . '" id="matomo-live-' . $id . '"' . $attrs . '>' . $html . '</div>';
    }

    protected static function alert($text)
    {
        return '<div class="matomo-alert" role="alert">' . htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    public static function errorText(Translator $t, $error)
    {
        $arg = $error['arg'] ?? null;

        switch ($error['code'] ?? '') {
            case 'connect':
                return $t->t('MOD_MATOMO_COUNTER_ERR_CONNECT', (string) $arg);
            case 'http':
                return $t->t('MOD_MATOMO_COUNTER_ERR_HTTP', (int) $arg);
            case 'json':
                return $t->t('MOD_MATOMO_COUNTER_ERR_JSON');
            case 'api':
                return $t->t('MOD_MATOMO_COUNTER_ERR_API', (string) $arg);
            case 'curl':
                return $t->t('MOD_MATOMO_COUNTER_ERR_CURL');
            case 'busy':
                return $t->t('MOD_MATOMO_COUNTER_ERR_BUSY');
            case 'url':
                return $t->t('MOD_MATOMO_COUNTER_ERR_URL');
        }

        return $t->t('MOD_MATOMO_COUNTER_ERR_UNKNOWN');
    }
}
