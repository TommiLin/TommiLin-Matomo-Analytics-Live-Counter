<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 * @copyright   Copyright (C) 2026 TommiLin. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;

class Mod_Matomo_CounterInstallerScript
{
    /**
     * Runs on upgrade: removes files left by older versions and clears the module cache.
     */
    public function update($parent)
    {
        $this->removeLegacyFiles();
        $this->clearModuleCache();

        return true;
    }

    /**
     * Runs on first install.
     */
    public function install($parent)
    {
        $this->clearModuleCache();

        return true;
    }

    /**
     * Deletes files that older versions put into global Joomla folders or that this version no longer ships.
     */
    private function removeLegacyFiles()
    {
        $legacyFiles = [
            // Old frontend language files
            JPATH_SITE . '/language/en-GB/en-GB.mod_matomo_counter.ini',
            JPATH_SITE . '/language/en-GB/mod_matomo_counter.ini',
            JPATH_SITE . '/language/uk-UA/uk-UA.mod_matomo_counter.ini',
            JPATH_SITE . '/language/uk-UA/mod_matomo_counter.ini',
            JPATH_SITE . '/language/ru-RU/ru-RU.mod_matomo_counter.ini',
            JPATH_SITE . '/language/ru-RU/mod_matomo_counter.ini',

            // Old backend language files (sys.ini)
            JPATH_ADMINISTRATOR . '/language/en-GB/en-GB.mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/en-GB/mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/uk-UA/uk-UA.mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/uk-UA/mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/ru-RU/ru-RU.mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/ru-RU/mod_matomo_counter.sys.ini',

            // Unused helper of old versions (sent the token in the URL)
            JPATH_SITE . '/modules/mod_matomo_counter/src/Helper/MatomoHelper.php',
        ];

        foreach ($legacyFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        // Old self-made JSON cache files and stray lock files
        foreach (glob(JPATH_CACHE . '/mod_matomo_counter/*.json') ?: [] as $file) {
            @unlink($file);
        }

        foreach (glob(JPATH_CACHE . '/mod_matomo_counter_*.lock') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * Clears the module's cache group through the Joomla Cache API.
     */
    private function clearModuleCache()
    {
        try {
            $handler = Factory::getApplication()->get('cache_handler', '') ?: 'file';

            Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                ->createCacheController('output', ['defaultgroup' => 'mod_matomo_counter', 'caching' => true, 'storage' => $handler])
                ->clean('mod_matomo_counter');
        } catch (\Throwable $e) {
            // A cache problem must never break the installation
        }
    }
}
