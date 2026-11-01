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
use Joomla\Registry\Registry;
use TommiLin\Module\MatomoCounter\Site\Helper\ModuleRenderer;

require_once __DIR__ . '/src/Helper/Translator.php';
require_once __DIR__ . '/src/Helper/MatomoCounterHelper.php';
require_once __DIR__ . '/src/Helper/ModuleRenderer.php';

/**
 * com_ajax entry point:
 *   index.php?option=com_ajax&module=matomo_counter&method=get&format=json&id=<module id>&Itemid=<menu item>[&force=1]
 *
 * Used only by the administrator's "refresh now" button, so it answers administrators only.
 * The module is still looked up the normal Joomla way (published, access level, menu assignment).
 */
class ModMatomoCounterHelper
{
    public static function getAjax()
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();
        $user  = $app->getIdentity();

        if (!$user || !$user->authorise('core.admin')) {
            throw new \RuntimeException('Forbidden', 403);
        }

        $module = ModuleHelper::getModuleById((string) $input->getInt('id'));

        if (empty($module->id) || $module->module !== 'mod_matomo_counter') {
            throw new \RuntimeException('Module not found', 404);
        }

        $html = ModuleRenderer::render($module, new Registry($module->params), true, $input->getCmd('ml', ''), $input->getInt('force', 0) === 1);

        return ['html' => $html, 'generated' => time()];
    }
}
