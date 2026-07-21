<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 * @copyright   Copyright (C) 2026 TommiLin. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;

class Mod_Matomo_CounterInstallerScript
{
    /**
     * Вызывается при обновлении (upgrade) модуля
     */
    public function update($parent)
    {
        $this->removeLegacyFiles(); // Удаляем старые языковые файлы из корня
        $this->clearJoomlaCache();  // Сбрасываем кэш
    }

    /**
     * Вызывается при первой установке (install) модуля
     */
    public function install($parent)
    {
        $this->clearJoomlaCache();
    }

    /**
     * Удаляет языковые файлы от старых версий модуля из глобальных папок Joomla
     */
    private function removeLegacyFiles()
    {
        // Список возможных старых файлов (с префиксом языка и без него)
        $legacyFiles = [
            // Файлы сайта (Frontend)
            JPATH_SITE . '/language/en-GB/en-GB.mod_matomo_counter.ini',
            JPATH_SITE . '/language/en-GB/mod_matomo_counter.ini',
            JPATH_SITE . '/language/uk-UA/uk-UA.mod_matomo_counter.ini',
            JPATH_SITE . '/language/uk-UA/mod_matomo_counter.ini',
            JPATH_SITE . '/language/ru-RU/ru-RU.mod_matomo_counter.ini',
            JPATH_SITE . '/language/ru-RU/mod_matomo_counter.ini',

            // Файлы админки (Backend - sys.ini)
            JPATH_ADMINISTRATOR . '/language/en-GB/en-GB.mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/en-GB/mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/uk-UA/uk-UA.mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/uk-UA/mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/ru-RU/ru-RU.mod_matomo_counter.sys.ini',
            JPATH_ADMINISTRATOR . '/language/ru-RU/mod_matomo_counter.sys.ini',
        ];

        foreach ($legacyFiles as $file) {
            if (is_file($file)) {
                File::delete($file);
            }
        }
    }

    /**
     * Метод агрессивной очистки кэша системы и языков
     */
    private function clearJoomlaCache()
    {
        try {
            $langCache = Cache::getInstance('callback', ['defaultoptions' => ['cachemethod' => 'callback']]);
            $langCache->clean('_system');

            $comModulesCache = Cache::getInstance('callback', ['defaultoptions' => ['cachemethod' => 'callback']]);
            $comModulesCache->clean('com_modules');

            $conf = Factory::getConfig();
            $cacheClient = Cache::getInstance($conf->get('cache_handler', 'file'), [
                'defaultoptions' => ['cachemethod' => $conf->get('cache_handler', 'file')]
            ]);
            $cacheClient->clean('page');
            $cacheClient->clean('mod_modules');

            if (method_exists(Factory::getApplication(), 'getContainer')) {
                Factory::getApplication()->getContainer()->get(\Joomla\CMS\Extension\ComponentInterface::class)->getContainer()->getCache()->clean();
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки кэша
        }
    }
}