<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 TommiLin. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

$doc = \Joomla\CMS\Factory::getApplication()->getDocument();
$doc->addStyleDeclaration("
    .matomo-live-card {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #ffffff;
        border: 1px solid #f0f2f5;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        max-width: 420px;
        color: #0f172a;
        margin: 0 auto;
        padding: 30px;
    }
    .matomo-card-header { padding: 1px 1px 14px 12px; display: flex; justify-content: space-between; align-items: flex-start; }
    .matomo-card-title { font-size: 15px; font-weight: 700; letter-spacing: 0.3px; color: #0f172a; line-height: 1.3; text-transform: uppercase; margin: 0; }
    .matomo-section { padding: 14px 2px; border-top: 1px solid #f1f5f9; }
    .matomo-metric-row { display: flex; align-items: center; justify-content: space-between; padding: 1px 0; font-size: 14px; color: #475569; }
    .matomo-metric-label { display: flex; align-items: center; gap: 12px; }
    .matomo-metric-value { font-weight: 600; color: #0f172a; }
    .matomo-metric-value.online { color: #10b981; font-weight: 700; }
    .matomo-section-title { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .matomo-badge-count { background: #f1f5f9; color: #475569; font-size: 11px; padding: 1px 7px; border-radius: 4px; }
    .matomo-flag-wrapper { width: 16px; height: 12px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); vertical-align: middle; }
    .matomo-flag-img { width: 16px; height: 12px; object-fit: cover; display: block; }
    .matomo-dot-indicator { width: 6px; height: 6px; background-color: #10b981; border-radius: 50%; display: inline-block; }
    .matomo-dropdown-select { border: 1px solid #e2e8f0; font-size: 11px; padding: 2px 6px; border-radius: 6px; color: #475569; background: #fff; cursor: pointer; }
    .matomo-card-footer { padding: 12px 22px; border-top: 1px solid #f1f5f9; background-color: #fafafa; border-radius: 0 0 12px 12px; }
    .matomo-debug-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; margin-top: 8px; border-top: 1px dashed #e2e8f0; padding-top: 8px; }
    .matomo-debug-item { display: flex; justify-content: space-between; font-size: 11px; }
    .matomo-debug-label { color: #94a3b8; }
    .matomo-debug-value { font-weight: 500; color: #475569; }
    .d-flex { display: flex !important; }
    .align-items-center { align-items: center !important; }
    .gap-2 { gap: 0.5rem !important; }
    .gap-3 { gap: 0.75rem !important; }
    .matomo-chart-dot { cursor: pointer; transition: all 0.15s ease-in-out; }
    .matomo-chart-dot:hover { r: 7.5 !important; fill: #1d4ed8 !important; }
    .matomo-chart-block {
    width: 100%;
    min-width: 0;
    overflow: hidden;}
    .matomo-chart-block svg {
    display: block;
    width: 100%;
    height: auto;}
");


$getSvgPath = function($values, $max) {
    if (empty($values)) return ['points' => [], 'str' => '45,75 370,75'];
    $points = [];
    $startX = 45; // For large font 15px
    $endX = 370;
    $stepX = ($endX - $startX) / (count($values) - 1);
    foreach ($values as $i => $v) {
        $ptX = $startX + ($i * $stepX);
        $ptY = 75 - (($v / ($max ?: 10)) * 60);
        $points[] = "$ptX,$ptY";
    }
    return ['points' => $points, 'str' => implode(' ', $points)];
};

$max7  = !empty($stats['chart7_values']) ? max($stats['chart7_values']) : 10;
$max30 = !empty($stats['chart30_values']) ? max($stats['chart30_values']) : 10;

$path7  = $getSvgPath($stats['chart7_values'], $max7);
$path30 = $getSvgPath($stats['chart30_values'], $max30);
$isConnectionOk = !empty($stats) && isset($stats['chart7_values']) && count($stats['chart7_values']) > 0;
?>

<div class="matomo-section">
        <?php if ($visibility['online']) : ?>
            <div class="matomo-metric-row">
                <div class="matomo-metric-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#10b981"><circle cx="12" cy="12" r="8"/></svg>
                    <span><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_ONLINE'); ?>:</span>
                </div>
                <div class="matomo-metric-value online"><?php echo number_format($stats['online']); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($visibility['today']) : ?>
            <div class="matomo-metric-row">
                <div class="matomo-metric-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_TODAY'); ?>:</span>
                </div>
                <div class="matomo-metric-value"><?php echo number_format($stats['today']); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($visibility['today_views']) : ?>
            <div class="matomo-metric-row">
                <div class="matomo-metric-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <span><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_VIEWS'); ?>:</span>
                </div>
                <div class="matomo-metric-value"><?php echo number_format($stats['today_views']); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($visibility['yesterday'])) : ?>
            <div class="matomo-metric-row">
                <div class="matomo-metric-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_YESTERDAY'); ?>:</span>
                </div>
                <div class="matomo-metric-value"><?php echo number_format($stats['yesterday'] ?? 0); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($visibility['week']) : ?>
            <div class="matomo-metric-row">
                <div class="matomo-metric-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    <span><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_WEEK'); ?>:</span>
                </div>
                <div class="matomo-metric-value"><?php echo number_format($stats['week']); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($visibility['month']) : ?>
            <div class="matomo-metric-row">
                <div class="matomo-metric-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_MONTH'); ?>:</span>
                </div>
                <div class="matomo-metric-value"><?php echo number_format($stats['month']); ?></div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($visibility['countries']) : ?>
        <div class="matomo-section">
            <div class="matomo-section-title">
                <span class="d-flex align-items-center gap-2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748b;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                    <?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_COUNTRIES_TITLE'); ?>
                </span>
                <span class="matomo-badge-count">
                    <?php 
                    $totalOnlineCountries = 0;
                    if (!empty($stats['countries'])) {
                        foreach ($stats['countries'] as $c) {
                            $totalOnlineCountries += ($c['count'] ?? 0);
                        }
                    }
                    echo $totalOnlineCountries; 
                    ?>
                </span>
            </div>

            <?php if (!empty($stats['countries'])) : ?>
                <?php foreach ($stats['countries'] as $code => $country) : ?>
                    <?php $codeClean = strtolower(trim($code)); ?>
                    <div class="matomo-metric-row">
                        <div class="matomo-metric-label">
                            <?php if ($codeClean !== 'unknown' && !empty($codeClean)) : ?>
                                <div class="matomo-flag-wrapper">
                                    <img class="matomo-flag-img" 
                                         src="https://flagcdn.com/16x12/<?php echo $codeClean; ?>.png"
                                         srcset="https://flagcdn.com/32x24/<?php echo $codeClean; ?>.png 2x, https://flagcdn.com/48x36/<?php echo $codeClean; ?>.png 3x"
                                         width="16"
                                         height="12"
                                         alt="<?php echo htmlspecialchars($country['name'] ?? $codeClean); ?>">
                                </div>
                            <?php else: ?>
                                <span class="matomo-flag-wrapper" style="background: #e2e8f0;"></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($country['name'] ?? $codeClean); ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="matomo-dot-indicator"></span>
                            <span class="matomo-metric-value"><?php echo $country['count'] ?? 0; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="matomo-metric-row" style="justify-content: center; color: #94a3b8; font-size: 13px; padding: 10px 0;">
                    <span><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_NO_VISITORS'); ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($visibility['top_countries']) : ?>
        <div class="matomo-section">
            <div class="matomo-section-title">
                <span class="d-flex align-items-center gap-2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                    <?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_TOP_COUNTRIES_TITLE'); ?>
                </span>
                <select class="matomo-dropdown-select" onchange="toggleMatomoTopCountries(this.value)">
                    <option value="day"><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_TODAY1'); ?></option>
                    <option value="yesterday"><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_YESTERDAY_SHORT'); ?></option>
                    <option value="week">7 <?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_DAYS'); ?></option>
                    <option value="month">30 <?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_DAYS'); ?></option>
                </select>
            </div>
            
            <?php 
            $periodsList = [
                'day'   => $stats['top_countries_day'] ?? [],
                'yesterday' => $stats['top_countries_yesterday'] ?? [],
                'week'  => $stats['top_countries_week'] ?? [],
                'month' => $stats['top_countries_month'] ?? []
            ];
            
            foreach ($periodsList as $periodName => $periodData) : 
                $totalPeriodVisits = array_sum(array_column($periodData, 'count'));
                $displayStyle = ($periodName === 'day') ? 'block' : 'none';
            ?>
                <div id="matomo-top-<?php echo $periodName; ?>" class="matomo-top-countries-block" style="display: <?php echo $displayStyle; ?>;">
                    <?php if (!empty($periodData)) : ?>
                        <?php foreach ($periodData as $code => $country) : ?>
                            <?php 
                                $codeClean = strtolower(trim($code)); 
                                $percent = $totalPeriodVisits > 0 ? round(($country['count'] / $totalPeriodVisits) * 100) : 0; 
                            ?>
                            <div class="matomo-country-block" style="margin-bottom: 12px;">
                                <div class="matomo-metric-row" style="padding: 4px 0 2px 0;">
                                    <div class="matomo-metric-label">
                                        <?php if ($codeClean !== 'unknown' && !empty($codeClean)) : ?>
                                            <div class="matomo-flag-wrapper">
                                                <img class="matomo-flag-img" 
                                                     src="https://flagcdn.com/16x12/<?php echo $codeClean; ?>.png"
                                                     srcset="https://flagcdn.com/32x24/<?php echo $codeClean; ?>.png 2x, https://flagcdn.com/48x36/<?php echo $codeClean; ?>.png 3x"
                                                     width="16"
                                                     height="12"
                                                     alt="<?php echo htmlspecialchars($country['name'] ?? $codeClean); ?>">
                                            </div>
                                        <?php else: ?>
                                            <span class="matomo-flag-wrapper" style="background: #e2e8f0;"></span>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($country['name'] ?? $codeClean); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="matomo-metric-value"><?php echo number_format($country['count'] ?? 0); ?></span>
                                        <span style="font-size: 11px; color: #64748b; font-weight: 500; min-width: 28px; text-align: right;"><?php echo $percent; ?>%</span>
                                    </div>
                                </div>
                                <div class="matomo-progress-bg" style="background: #f1f5f9; height: 6px; border-radius: 3px; width: 100%; overflow: hidden; margin-top: 4px;">
                                    <div class="matomo-progress-bar" style="background: #3b82f6; height: 100%; width: <?php echo $percent; ?>%; border-radius: 3px; transition: width 0.3s ease;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="matomo-metric-row" style="justify-content: center; color: #94a3b8; font-size: 13px; padding: 10px 0;">
                            <span><?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_NO_VISITORS'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <script>
        function toggleMatomoTopCountries(period) {
            document.getElementById('matomo-top-day').style.display = 'none';
            document.getElementById('matomo-top-yesterday').style.display = 'none';
            document.getElementById('matomo-top-week').style.display = 'none';
            document.getElementById('matomo-top-month').style.display = 'none';

            if (period === 'day') {
             document.getElementById('matomo-top-day').style.display = 'block';
             } else if (period === 'yesterday') {
              document.getElementById('matomo-top-yesterday').style.display = 'block';
             } else if (period === 'week') {
              document.getElementById('matomo-top-week').style.display = 'block';
             } else if (period === 'month') {
            document.getElementById('matomo-top-month').style.display = 'block';
    }
}
</script>
    <?php endif; ?>

    <?php if ($visibility['chart']) : ?>
        <div class="matomo-section">
            <div class="matomo-section-title">
                <span class="d-flex align-items-center gap-2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748b;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    <?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_CHART_TITLE'); ?>
                </span>
                <select class="matomo-dropdown-select" onchange="toggleMatomoChart(this.value)">
                    <option value="7">7 <?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_DAYS'); ?></option>
                    <option value="30">30 <?php echo \Joomla\CMS\Language\Text::_('MOD_MATOMO_COUNTER_LIVE_DAYS'); ?></option>
                </select>
            </div>
            
            <div id="matomo-chart-7" class="matomo-chart-block pt-2">
                <svg viewBox="0 0 380 90" width="100%" height="80">
                    <line x1="45" y1="15" x2="370" y2="15" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3,3" />
                    <line x1="45" y1="75" x2="370" y2="75" stroke="#e2e8f0" stroke-width="1" />
                    <text x="38" y="20" text-anchor="end" fill="#475569" font-size="15" font-weight="700"><?php echo number_format($max7); ?></text>
                    <text x="38" y="80" text-anchor="end" fill="#475569" font-size="15" font-weight="700">0</text>

                    <path d="M 45,75 L <?php echo $path7['str']; ?> L 370,75 Z" fill="rgba(37,99,235,0.06)"/>
                    <polyline points="<?php echo $path7['str']; ?>" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    
                    <?php if (!empty($path7['points'])) : foreach ($path7['points'] as $i => $p) : $pt = explode(',', $p); ?>
                        <circle cx="<?php echo $pt[0]; ?>" cy="<?php echo $pt[1]; ?>" r="6" fill="#2563eb" stroke="#fff" stroke-width="2" class="matomo-chart-dot">
                            <title><?php echo (!empty($stats['chart7_values'][$i]) ? number_format($stats['chart7_values'][$i]) : 0) . ' ' . \Joomla\CMS\Language\Text::_(''); ?></title>
                        </circle>
                    <?php endforeach; endif; ?>
                </svg>
                <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 9px; padding: 0 4px 0 45px;">
                    <?php if (!empty($stats['chart7_labels'])) : foreach ($stats['chart7_labels'] as $label) : ?>
                        <span><?php echo $label; ?></span>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div id="matomo-chart-30" class="matomo-chart-block pt-2" style="display: none;">
                <svg viewBox="0 0 380 90" width="100%" height="80">
                    <line x1="45" y1="15" x2="370" y2="15" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3,3" />
                    <line x1="45" y1="75" x2="370" y2="75" stroke="#e2e8f0" stroke-width="1" />
                    <text x="38" y="20" text-anchor="end" fill="#475569" font-size="15" font-weight="700"><?php echo number_format($max30); ?></text>
                    <text x="38" y="80" text-anchor="end" fill="#475569" font-size="15" font-weight="700">0</text>

                    <path d="M 45,75 L <?php echo $path30['str']; ?> L 370,75 Z" fill="rgba(37,99,235,0.06)"/>
                    <polyline points="<?php echo $path30['str']; ?>" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    
                    <?php 
                    if (!empty($path30['points'])) : 
                        $total30 = count($stats['chart30_values']);
                        foreach ($path30['points'] as $i => $p) : 
                            $currentVal = $stats['chart30_values'][$i] ?? 0;
                            $prevVal = ($i > 0) ? ($stats['chart30_values'][$i - 1] ?? 0) : 0;
                            $nextVal = ($i < $total30 - 1) ? ($stats['chart30_values'][$i + 1] ?? 0) : 0;

                            if ($currentVal >= $prevVal && $currentVal >= $nextVal && $currentVal > 0) :
                                $pt = explode(',', $p); 
                    ?>
                                <circle cx="<?php echo $pt[0]; ?>" cy="<?php echo $pt[1]; ?>" r="5" fill="#2563eb" stroke="#fff" stroke-width="1.5" class="matomo-chart-dot">
                                    <title><?php echo number_format($currentVal) . ' ' . \Joomla\CMS\Language\Text::_(''); ?></title>
                                </circle>
                            <?php else : ?>
                                <?php $pt = explode(',', $p); ?>
                                <circle cx="<?php echo $pt[0]; ?>" cy="<?php echo $pt[1]; ?>" r="7" fill="transparent" class="matomo-chart-dot">
                                    <title><?php echo number_format($currentVal) . ' ' . \Joomla\CMS\Language\Text::_(''); ?></title>
                                </circle>
                    <?php 
                            endif;
                        endforeach; 
                    endif; 
                    ?>
                </svg>
                <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 9px; padding: 0 4px 0 45px;">
                    <?php if (!empty($stats['chart30_labels'])) : foreach ($stats['chart30_labels'] as $label) : if(!empty($label)): ?>
                        <span><?php echo $label; ?></span>
                    <?php endif; endforeach; endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        function toggleMatomoChart(days) {
            document.getElementById('matomo-chart-7').style.display = (days === '7') ? 'block' : 'none';
            document.getElementById('matomo-chart-30').style.display = (days === '30') ? 'block' : 'none';
        }
        </script>
    <?php endif; ?>

    <?php if (!empty($debugMode)) : ?>
        <div class="matomo-card-footer">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <?php if ($isConnectionOk) : ?>
                        <span class="matomo-dot-indicator status-ok" title="Connection to Matomo established successfully"></span>
                        <span style="color: #10b981; font-weight: 600; font-size: 11px; letter-spacing: 0.5px;">API CONNECTION OK</span>
                    <?php else : ?>
                        <span class="matomo-dot-indicator status-error" title="Connection error! Check the URL, Token or Site ID"></span>
                        <span style="color: #ef4444; font-weight: 600; font-size: 11px; letter-spacing: 0.5px;">API CONNECTION ERROR</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($stats['debug_info'])) : ?>
                <div class="matomo-debug-grid">
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">Source:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['source']; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">Cache:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['cache']; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">Connection:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['connection']; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">Age:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['age']; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">HTTP:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['http_code']; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">Duration:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['duration'] ?? '0 ms'; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">PHP:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['php_ver']; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">Memory:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['memory']; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">Matomo:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['matomo_ver']; ?></span>
                    </div>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label">Size:</span>
                        <span class="matomo-debug-value"><?php echo $stats['debug_info']['size']; ?></span>
                    </div>
                </div>

                <?php if (!empty($stats['debug_info']['api_methods'])) : ?>
                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 8px 0;">
                    <div class="matomo-debug-methods" style="font-family: monospace; font-size: 11px; line-height: 1.6;">
                        <?php foreach ($stats['debug_info']['api_methods'] as $methodName => $methodSize) : ?>
                            <div class="d-flex justify-content-between" style="color: #475569;">
                                <span style="flex-shrink: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 8px;">
                                    <?php echo $methodName; ?>
                                </span>
                                <span style="color: #0f172a; font-weight: 600; flex-shrink: 0;">
                                    <?php echo $methodSize; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>