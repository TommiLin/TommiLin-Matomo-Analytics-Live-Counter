<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 Your Name. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;
?>
<div class="matomo-counter-wrapper">
    <?php if ($show['online'] === 1) : ?>
        <div class="matomo-item matomo-online">
            <span class="matomo-label"><?php echo Text::_('MOD_MATOMO_COUNTER_METRIC_ONLINE'); ?>:</span>
            <strong class="matomo-value"><?php echo $stats['online']; ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($show['today'] === 1) : ?>
        <div class="matomo-item matomo-today">
            <span class="matomo-label"><?php echo Text::_('MOD_MATOMO_COUNTER_METRIC_TODAY'); ?>:</span>
            <strong class="matomo-value"><?php echo $stats['today']; ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($show['today_views'] === 1) : ?>
        <div class="matomo-item matomo-today-views">
            <span class="matomo-label"><?php echo Text::_('MOD_MATOMO_COUNTER_METRIC_TODAY_VIEWS'); ?>:</span>
            <strong class="matomo-value"><?php echo $stats['today_views']; ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($show['week'] === 1) : ?>
        <div class="matomo-item matomo-week">
            <span class="matomo-label"><?php echo Text::_('MOD_MATOMO_COUNTER_METRIC_WEEK'); ?>:</span>
            <strong class="matomo-value"><?php echo $stats['week']; ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($show['month'] === 1) : ?>
        <div class="matomo-item matomo-month">
            <span class="matomo-label"><?php echo Text::_('MOD_MATOMO_COUNTER_METRIC_MONTH'); ?>:</span>
            <strong class="matomo-value"><?php echo $stats['month']; ?></strong>
        </div>
    <?php endif; ?>
</div>

<style>
/* Styles remain unchanged as they are generic */
.matomo-counter-wrapper { padding: 0.1em; font-family: inherit; font-size: inherit; font-weight: inherit; line-height: inherit; color: inherit; box-sizing: border-box; }
.matomo-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0em; border-bottom: 1px dashed rgba(0, 0, 0, 0.12); padding-bottom: 0em; list-style: none; }
@media (prefers-color-scheme: dark) { .matomo-item { border-bottom-color: rgba(255, 255, 255, 0.15); } }
.matomo-label { color: inherit; font-family: inherit; }
.matomo-value { font-family: inherit; font-size: inherit; font-weight: 700; color: inherit; }
.matomo-online .matomo-value { color: var(--bs-success, #3c8f60); }
</style>
