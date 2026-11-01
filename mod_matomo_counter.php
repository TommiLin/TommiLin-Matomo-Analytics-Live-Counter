<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 TommiLin. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use TommiLin\Module\MatomoCounter\Site\Helper\ModuleRenderer;

require_once __DIR__ . '/src/Helper/Translator.php';
require_once __DIR__ . '/src/Helper/MatomoCounterHelper.php';
require_once __DIR__ . '/src/Helper/ModuleRenderer.php';

echo ModuleRenderer::render($module, $params);
