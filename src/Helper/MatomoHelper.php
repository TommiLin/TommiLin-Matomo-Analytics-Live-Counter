<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 Your Name. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Module\MatomoCounter\Site\Helper;

defined('_JEXEC') || die;

use Joomla\CMS\Http\HttpFactory;

class MatomoHelper
{
    public static function getStats(string $url, int $siteId, string $token)
    {
        // Basic parameters for building the bulk request
        $methods = [
            'online' => 'method=Live.getCounters&lastMinutes=3', // Online status timeout: 3 minutes
            'today'  => 'method=VisitsSummary.getUniqueVisitors&period=day&date=today',
            'week'   => 'method=VisitsSummary.getVisits&period=range&date=last7'
        ];

        // Build the parameter array for bulk submission
        $apiParams = [
            'module'     => 'API',
            'method'     => 'API.getBulkRequest',
            'idSite'     => $siteId,
            'token_auth' => $token,
            'format'     => 'json'
        ];

        $i = 0;
        foreach ($methods as $key => $methodStr) {
            $apiParams["urls[$i]"] = $methodStr;
            $i++;
        }

        try {
            $http = HttpFactory::getHttp();
            $response = $http->get($url . '?' . http_build_query($apiParams), [], 5); // Timeout: 5 minutes

            if ($response && $response->code === 200) {
                $data = json_decode($response->body, true);
                
                if (is_array($data) && isset($data[0])) {
                    return [
                        'online' => (int) ($data[0][0]['visitors'] ?? 0),
                        'today'  => (int) ($data[1]['value'] ?? 0),
                        'week'   => (int) ($data[2]['value'] ?? 0),
                    ];
                }
            }
        } catch (\Exception $e) {
        }

        return ['online' => 0, 'today' => 0, 'week' => 0];
    }
}
