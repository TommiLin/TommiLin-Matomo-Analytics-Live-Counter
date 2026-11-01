<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 TommiLin. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Variables from ModuleRenderer: $module, $params, $t (Translator), $stats, $visibility, $isAdmin,
 * $debugMode, $matomoUrl, $chartStyle (default style), $canRefresh, $errorText, $window, $rows.
 *
 * No inline styles or scripts here on purpose: they live in media/mod_matomo_counter (strict CSP friendly).
 */
defined('_JEXEC') or die;

$uid   = (int) ($module->id ?? 0);
$meta  = $stats['meta'] ?? [];
$e     = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$tr    = function ($key, ...$args) use ($t, $e) { return $e($t->t($key, ...$args)); };
$num   = function ($n) { return number_format((float) $n); };
$f     = function ($v) { return sprintf('%.1F', $v); };

$icon = function ($paths, $class = 'matomo-title-icon') {
    return '<svg class="' . $class . '" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};

$icons = [
    'globe'    => '<circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>',
    'pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>',
    'pie'      => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>',
    'bars'     => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
    'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline>',
    'link'     => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line>',
    'monitor'  => '<rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line>',
    'trend'    => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline>',
];

/* ---------- Flags: served by your own Matomo server (no third-party requests) ---------- */
$flag = function ($code) use ($stats, $matomoUrl, $e) {
    $code = strtolower(trim((string) $code));

    if (!preg_match('/^[a-z]{2}$/', $code) || $code === 'xx') {
        return '<span class="matomo-flag-wrapper matomo-flag-empty"></span>';
    }

    $path = $stats['flags'][$code] ?? ('plugins/Morpheus/icons/dist/flags/' . $code . '.png');

    return '<span class="matomo-flag-wrapper"><img class="matomo-flag-img" src="' . $e($matomoUrl . '/' . ltrim($path, '/'))
        . '" width="16" height="12" alt="" loading="lazy" decoding="async"></span>';
};

$emptyRow = '<div class="matomo-empty">' . $tr('MOD_MATOMO_COUNTER_LIVE_NO_VISITORS') . '</div>';

/* ---------- Simple "online" list ---------- */
$onlineBlock = function ($titleKey, $iconPaths, array $items, callable $labelFn) use ($tr, $num, $e, $icon, $emptyRow) {
    $total = array_sum(array_column($items, 'count'));

    $h  = '<div class="matomo-section"><div class="matomo-section-title"><span class="matomo-title-text">' . $icon($iconPaths) . $tr($titleKey) . '</span>';
    $h .= '<span class="matomo-badge-count">' . $num($total) . '</span></div>';

    if ($items) {
        foreach ($items as $key => $item) {
            list($labelHtml, $title) = $labelFn($key, $item);

            $h .= '<div class="matomo-metric-row" title="' . $e($title) . '"><div class="matomo-metric-label">' . $labelHtml . '</div>'
                . '<div class="matomo-row-end"><span class="matomo-dot-indicator"></span><span class="matomo-metric-value">' . (int) ($item['count'] ?? 0) . '</span></div></div>';
        }
    } else {
        $h .= $emptyRow;
    }

    return $h . '</div>';
};

/* ---------- "Top" list with a period selector and percent bars ---------- */
$topBlock = function ($name, $titleKey, $iconPaths, callable $labelFn) use ($stats, $uid, $tr, $num, $e, $icon, $emptyRow) {
    $periods = ['day', 'yesterday', 'week', 'month'];
    $group   = 'top-' . $name;
    $selId   = 'matomo-sel-' . $uid . '-' . $name;

    $h  = '<div class="matomo-section"><div class="matomo-section-title"><span class="matomo-title-text">' . $icon($iconPaths) . $tr($titleKey) . '</span>';
    $h .= '<select class="matomo-dropdown-select" id="' . $selId . '" data-mmc-switch="' . $group . '" aria-label="' . $tr('MOD_MATOMO_COUNTER_LIVE_PERIOD') . '">';
    $h .= '<option value="day">' . $tr('MOD_MATOMO_COUNTER_LIVE_TODAY1') . '</option>';
    $h .= '<option value="yesterday">' . $tr('MOD_MATOMO_COUNTER_LIVE_YESTERDAY_SHORT') . '</option>';
    $h .= '<option value="week">7 ' . $tr('MOD_MATOMO_COUNTER_LIVE_DAYS') . '</option>';
    $h .= '<option value="month">30 ' . $tr('MOD_MATOMO_COUNTER_LIVE_DAYS') . '</option>';
    $h .= '</select></div>';

    foreach ($periods as $p) {
        $items = array_filter($stats['top_' . $name . '_' . $p] ?? [], function ($item) {
            return (int) ($item['count'] ?? 0) > 0;
        });
        $total = array_sum(array_column($items, 'count'));

        $h .= '<div class="matomo-top-block" data-mmc-group="' . $group . '" data-mmc-key="' . $p . '"' . ($p === 'day' ? '' : ' hidden') . '>';

        if ($items) {
            foreach ($items as $key => $item) {
                $count   = (int) ($item['count'] ?? 0);
                $percent = $total > 0 ? round($count / $total * 100) : 0;

                list($labelHtml, $title) = $labelFn($key, $item);

                $h .= '<div class="matomo-country-block" title="' . $e($title) . '">'
                    . '<div class="matomo-metric-row matomo-top-row"><div class="matomo-metric-label">' . $labelHtml . '</div>'
                    . '<div class="matomo-row-end"><span class="matomo-metric-value">' . $num($count) . '</span><span class="matomo-percent">' . $percent . '%</span></div></div>'
                    . '<svg class="matomo-bar" width="100%" height="6" aria-hidden="true" focusable="false">'
                    . '<rect class="matomo-bar-bg" width="100%" height="6" rx="3"></rect>'
                    . '<rect class="matomo-bar-fg" width="' . $percent . '%" height="6" rx="3"></rect></svg></div>';
            }
        } else {
            $h .= $emptyRow;
        }

        $h .= '</div>';
    }

    return $h . '</div>';
};

/* ---------- Visits chart (line with peak + average, or bars) ---------- */
$monthNames = [];
for ($m = 1; $m <= 12; $m++) {
    $monthNames[$m] = $t->t('MOD_MATOMO_COUNTER_MONTH_' . $m);
}

$buildChart = function ($n, $chartStyle) use ($stats, $monthNames, $params, $t, $e, $tr, $f) {
    $key    = $n === 7 ? 'chart7' : 'chart30';
    $values = array_map('intval', $stats[$key . '_values'] ?? []);
    $count  = count($values);

    if ($count < 2) {
        return '<div class="matomo-empty">' . $tr('MOD_MATOMO_COUNTER_LIVE_NO_VISITORS') . '</div>';
    }

    $dates = $stats[$key . '_dates'] ?? [];
    if (count($dates) !== $count) {
        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $dates[] = date('Y-m-d', strtotime('-' . ($count - 1 - $i) . ' days'));
        }
    }

    $fmtDate = function ($ymd, $withMonth) use ($monthNames) {
        $ts  = strtotime($ymd);
        $day = (int) date('j', $ts);

        return $withMonth ? $day . ' ' . $monthNames[(int) date('n', $ts)] : (string) $day;
    };

    $total = array_sum($values);
    $prev  = (int) ($stats[$n === 7 ? 'week_prev' : 'month_prev'] ?? 0);
    $maxV  = max($values);
    $avg   = $total / $count;

    // Same viewBox, stroke widths and marker sizes for 7 and 30 days
    $W = 320; $H = 150; $L = 30; $R = 8; $T = 16; $B = 22;

    if ($maxV <= 10) {
        $max = max(2, (int) ceil($maxV / 2) * 2);
    } else {
        $pw  = pow(10, floor(log10($maxV)));
        $max = (int) round(10 * $pw);
        foreach ([1.2, 1.6, 2, 3, 4, 5, 6, 8, 10] as $mul) {
            if ($maxV <= $mul * $pw) { $max = (int) round($mul * $pw); break; }
        }
    }

    $yOf = function ($v) use ($T, $H, $B, $max) { return $T + ($H - $T - $B) * (1 - $v / $max); };
    $y0  = $yOf(0);

    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" role="img" aria-label="' . $tr('MOD_MATOMO_COUNTER_LIVE_CHART_TITLE') . '">';

    foreach ([0, $max / 2, $max] as $tick) {
        $y    = $yOf($tick);
        $svg .= '<line class="' . ($tick == 0 ? 'mmc-grid-base' : 'mmc-grid') . '" x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $f($y) . '" y2="' . $f($y) . '"/>';
        $svg .= '<text class="mmc-axis" x="' . ($L - 6) . '" y="' . $f($y + 4) . '" text-anchor="end" font-size="11">' . number_format($tick) . '</text>';
    }

    if ($n <= 7) {
        $labelIdx = range(0, $count - 1);
    } else {
        $labelIdx = [];
        for ($i = $count - 1; $i >= 0; $i -= 7) { $labelIdx[] = $i; }
        $labelIdx = array_reverse($labelIdx);
    }

    if ($chartStyle === 'bars') {
        $band = ($W - $L - $R) / $count;
        $bw   = min(22, $band * 0.62);
        $xOf  = function ($i) use ($L, $band) { return $L + $band * ($i + 0.5); };

        foreach ($values as $i => $v) {
            $y    = $yOf($v);
            $svg .= '<rect class="' . ($i === $count - 1 ? 'mmc-bar-now' : 'mmc-bar-old') . '" x="' . $f($xOf($i) - $bw / 2) . '" y="' . $f($y) . '" width="' . $f($bw) . '" height="' . $f(max(0, $y0 - $y)) . '" rx="2"><title>'
                . $e($fmtDate($dates[$i], true) . ': ' . number_format($v)) . '</title></rect>';
        }
    } else {
        $xOf = function ($i) use ($L, $W, $R, $count) { return $L + ($W - $L - $R) * $i / ($count - 1); };

        $pts = [];
        foreach ($values as $i => $v) { $pts[] = [$xOf($i), $yOf($v)]; }

        // Smooth curve through every point (Catmull-Rom -> Bezier, control points clamped: no overshoot)
        $path = 'M' . $f($pts[0][0]) . ',' . $f($pts[0][1]);
        for ($i = 0; $i < $count - 1; $i++) {
            $p0 = $pts[max($i - 1, 0)];
            $p1 = $pts[$i];
            $p2 = $pts[$i + 1];
            $p3 = $pts[min($i + 2, $count - 1)];
            $lo = min($p1[1], $p2[1]);
            $hi = max($p1[1], $p2[1]);

            $c1x = $p1[0] + ($p2[0] - $p0[0]) / 6;
            $c1y = min(max($p1[1] + ($p2[1] - $p0[1]) / 6, $lo), $hi);
            $c2x = $p2[0] - ($p3[0] - $p1[0]) / 6;
            $c2y = min(max($p2[1] - ($p3[1] - $p1[1]) / 6, $lo), $hi);

            $path .= ' C' . $f($c1x) . ',' . $f($c1y) . ' ' . $f($c2x) . ',' . $f($c2y) . ' ' . $f($p2[0]) . ',' . $f($p2[1]);
        }

        $ya   = $yOf($avg);
        $svg .= '<line class="mmc-avg-line" x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $f($ya) . '" y2="' . $f($ya) . '"/>';
        $svg .= '<path class="mmc-line" d="' . $path . '"/>';

        $pk   = $pts[array_search($maxV, $values, true)];
        $lx   = min(max($pk[0], $L + 24), $W - $R - 24);
        $svg .= '<circle class="mmc-peak-dot" cx="' . $f($pk[0]) . '" cy="' . $f($pk[1]) . '" r="4"/>';
        $svg .= '<text class="mmc-peak-label" x="' . $f($lx) . '" y="' . $f($pk[1] - 9) . '" text-anchor="middle" font-size="11">' . $e($t->t('MOD_MATOMO_COUNTER_LIVE_PEAK') . ' ' . number_format($maxV)) . '</text>';

        foreach ($pts as $i => $pt) {
            $svg .= '<circle class="matomo-hit" cx="' . $f($pt[0]) . '" cy="' . $f($pt[1]) . '" r="9"><title>' . $e($fmtDate($dates[$i], true) . ': ' . number_format($values[$i])) . '</title></circle>';
        }
    }

    foreach ($labelIdx as $i) {
        $isLastLong = ($n > 7 && $i === $count - 1);
        $svg .= '<text class="mmc-axis" x="' . $f($isLastLong ? $W : $xOf($i)) . '" y="' . ($H - 6) . '" text-anchor="' . ($isLastLong ? 'end' : 'middle') . '" font-size="11">' . $e($fmtDate($dates[$i], $n > 7)) . '</text>';
    }

    $svg .= '</svg>';

    $chip = '';
    if ($params->get('compare_previous') && $prev > 0) {
        $pct  = (int) round(($total - $prev) / $prev * 100);
        $chip = '<span class="matomo-chip ' . ($pct >= 0 ? 'matomo-chip-up' : 'matomo-chip-down') . '" title="' . $tr('MOD_MATOMO_COUNTER_LIVE_VS_PREV') . '">' . ($pct >= 0 ? '+' : '') . $pct . '%</span>';
    }

    $avgHtml = '';
    if ($chartStyle === 'line') {
        $avgHtml = '<div class="matomo-chart-avg"><svg width="18" height="2" viewBox="0 0 18 2" aria-hidden="true"><line class="mmc-avg-line" x1="0" y1="1" x2="18" y2="1"/></svg>'
            . $tr('MOD_MATOMO_COUNTER_LIVE_AVG') . ' ' . number_format($avg) . '</div>';
    }

    $header = '<div class="matomo-chart-head"><div><span class="matomo-chart-total">' . number_format($total) . '</span>'
        . '<span class="matomo-chart-caption">' . $tr('MOD_MATOMO_COUNTER_LIVE_VISITS_LABEL') . ' / ' . $n . ' ' . $tr('MOD_MATOMO_COUNTER_LIVE_DAYS') . '</span></div>'
        . $chip . $avgHtml . '</div>';

    return $header . $svg;
};

/* ---------- Admin-only notices ---------- */
$humanAge = function ($sec) {
    $sec = (int) $sec;

    return $sec < 120 ? $sec . ' s' : ($sec < 7200 ? floor($sec / 60) . ' min' : floor($sec / 3600) . ' h');
};

$isConnectionOk = ($stats['status'] ?? '') !== 'error' && empty($meta['stale']);
?>
<?php if ($isAdmin && !empty($meta['stale']) && $errorText) : ?>
    <div class="matomo-alert" role="alert"><?php echo $tr('MOD_MATOMO_COUNTER_NOTICE_STALE', $humanAge($meta['age'] ?? 0), $errorText); ?></div>
<?php elseif ($isAdmin && $errorText) : ?>
    <div class="matomo-alert" role="alert"><?php echo $e($errorText); ?></div>
<?php endif; ?>

<?php if ($isAdmin && !empty($meta['warnings'])) : ?>
    <div class="matomo-alert" role="alert">
        <?php echo $tr('MOD_MATOMO_COUNTER_NOTICE_WARNINGS'); ?>
        <?php foreach (array_slice($meta['warnings'], 0, 3) as $w) : ?>
            <div><?php echo $e($w); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="matomo-section">
    <?php if ($visibility['online']) : ?>
        <div class="matomo-metric-row">
            <div class="matomo-metric-label">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#10b981" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8"/></svg>
                <span><?php echo $tr('MOD_MATOMO_COUNTER_LIVE_ONLINE', $window); ?>:</span>
            </div>
            <div class="matomo-metric-value online"><?php echo $num($stats['online']); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($visibility['today']) : ?>
        <div class="matomo-metric-row">
            <div class="matomo-metric-label">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span><?php echo $tr('MOD_MATOMO_COUNTER_LIVE_TODAY'); ?>:</span>
            </div>
            <div class="matomo-metric-value"><?php echo $num($stats['today']); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($visibility['today_views']) : ?>
        <div class="matomo-metric-row">
            <div class="matomo-metric-label">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" aria-hidden="true" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <span><?php echo $tr('MOD_MATOMO_COUNTER_LIVE_VIEWS'); ?>:</span>
            </div>
            <div class="matomo-metric-value"><?php echo $num($stats['today_views']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($visibility['yesterday'])) : ?>
        <div class="matomo-metric-row">
            <div class="matomo-metric-label">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span><?php echo $tr('MOD_MATOMO_COUNTER_LIVE_YESTERDAY'); ?>:</span>
            </div>
            <div class="matomo-metric-value"><?php echo $num($stats['yesterday'] ?? 0); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($visibility['week']) : ?>
        <div class="matomo-metric-row">
            <div class="matomo-metric-label">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" aria-hidden="true" focusable="false"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                <span><?php echo $tr('MOD_MATOMO_COUNTER_LIVE_WEEK'); ?>:</span>
            </div>
            <div class="matomo-metric-value"><?php echo $num($stats['week']); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($visibility['month']) : ?>
        <div class="matomo-metric-row">
            <div class="matomo-metric-label">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span><?php echo $tr('MOD_MATOMO_COUNTER_LIVE_MONTH'); ?>:</span>
            </div>
            <div class="matomo-metric-value"><?php echo $num($stats['month']); ?></div>
        </div>
    <?php endif; ?>
</div>

<?php
if ($visibility['countries']) {
    echo $onlineBlock('MOD_MATOMO_COUNTER_LIVE_COUNTRIES_TITLE', $icons['globe'], $stats['countries'] ?? [], function ($code, $item) use ($flag, $e) {
        $name = $item['name'] ?? $code;

        return [$flag($code === 'unknown' ? '' : $code) . '<span class="matomo-label-text">' . $e($name) . '</span>', $name];
    });
}

if (!empty($visibility['cities'])) {
    echo $onlineBlock('MOD_MATOMO_COUNTER_LIVE_CITIES_TITLE', $icons['pin'], $stats['cities'] ?? [], function ($key, $item) use ($flag, $e) {
        return [$flag($item['code'] ?? '') . '<span class="matomo-label-text">' . $e($item['name'] ?? '') . '</span>', $item['title'] ?? $item['name'] ?? ''];
    });
}

if ($visibility['top_countries']) {
    echo $topBlock('countries', 'MOD_MATOMO_COUNTER_LIVE_TOP_COUNTRIES_TITLE', $icons['pie'], function ($code, $item) use ($flag, $e) {
        $name = $item['name'] ?? $code;

        return [$flag($code) . '<span class="matomo-label-text">' . $e($name) . '</span>', $name];
    });
}

if (!empty($visibility['top_cities'])) {
    echo $topBlock('cities', 'MOD_MATOMO_COUNTER_LIVE_TOP_CITIES_TITLE', $icons['bars'], function ($key, $item) use ($flag, $e) {
        return [$flag($item['code'] ?? '') . '<span class="matomo-label-text">' . $e($item['name'] ?? '') . '</span>', $item['title'] ?? $item['name'] ?? ''];
    });
}

$plainLabel = function ($key, $item) use ($e) {
    return ['<span class="matomo-label-text">' . $e($item['name'] ?? '') . '</span>', $item['title'] ?? $item['name'] ?? ''];
};

if (!empty($visibility['top_pages'])) {
    echo $topBlock('pages', 'MOD_MATOMO_COUNTER_LIVE_TOP_PAGES_TITLE', $icons['file'], $plainLabel);
}

if (!empty($visibility['top_referrers'])) {
    echo $topBlock('referrers', 'MOD_MATOMO_COUNTER_LIVE_TOP_REFERRERS_TITLE', $icons['link'], $plainLabel);
}

if (!empty($visibility['top_devices'])) {
    echo $topBlock('devices', 'MOD_MATOMO_COUNTER_LIVE_TOP_DEVICES_TITLE', $icons['monitor'], $plainLabel);
}
?>

<?php if ($visibility['chart']) : ?>
    <?php $asBars = $tr('MOD_MATOMO_COUNTER_LIVE_CHART_AS_BARS'); $asLine = $tr('MOD_MATOMO_COUNTER_LIVE_CHART_AS_LINE'); ?>
    <div class="matomo-section matomo-chart-section" data-mmc-chart-style="<?php echo $chartStyle; ?>">
        <div class="matomo-section-title">
            <span class="matomo-title-text"><?php echo $icon($icons['trend']) . $tr('MOD_MATOMO_COUNTER_LIVE_CHART_TITLE'); ?></span>
            <span class="matomo-title-controls">
                <button type="button" class="matomo-icon-btn matomo-chart-toggle" data-mmc-chart-toggle
                        data-label-line="<?php echo $asLine; ?>" data-label-bars="<?php echo $asBars; ?>"
                        title="<?php echo $chartStyle === 'line' ? $asBars : $asLine; ?>" aria-label="<?php echo $chartStyle === 'line' ? $asBars : $asLine; ?>">
                    <span class="matomo-ico-line"><?php echo $icon($icons['trend'], 'matomo-btn-icon'); ?></span>
                    <span class="matomo-ico-bars"><?php echo $icon($icons['bars'], 'matomo-btn-icon'); ?></span>
                </button>
                <select class="matomo-dropdown-select" id="matomo-sel-<?php echo $uid; ?>-chart" data-mmc-switch="chart" aria-label="<?php echo $tr('MOD_MATOMO_COUNTER_LIVE_PERIOD'); ?>">
                    <option value="7">7 <?php echo $tr('MOD_MATOMO_COUNTER_LIVE_DAYS'); ?></option>
                    <option value="30">30 <?php echo $tr('MOD_MATOMO_COUNTER_LIVE_DAYS'); ?></option>
                </select>
            </span>
        </div>

        <?php foreach ([7, 30] as $n) : ?>
            <?php foreach (['line', 'bars'] as $style) : ?>
                <div class="matomo-chart-block" data-mmc-group="chart" data-mmc-key="<?php echo $n; ?>" data-mmc-style="<?php echo $style; ?>"<?php echo $n === 7 ? '' : ' hidden'; ?>><?php echo $buildChart($n, $style); ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($debugMode)) : ?>
    <div class="matomo-card-footer">
        <div class="matomo-status">
            <?php if ($isConnectionOk) : ?>
                <span class="matomo-dot-indicator status-ok"></span>
                <span class="matomo-status-text matomo-status-ok-text">API CONNECTION OK</span>
            <?php else : ?>
                <span class="matomo-dot-indicator status-error"></span>
                <span class="matomo-status-text matomo-status-error-text"><?php echo empty($meta['stale']) ? 'API CONNECTION ERROR' : 'SHOWING STALE DATA'; ?></span>
            <?php endif; ?>

            <?php if (!empty($canRefresh)) : ?>
                <button type="button" class="matomo-icon-btn matomo-refresh-btn" data-mmc-refresh
                        title="<?php echo $tr('MOD_MATOMO_COUNTER_LIVE_REFRESH'); ?>" aria-label="<?php echo $tr('MOD_MATOMO_COUNTER_LIVE_REFRESH'); ?>">
                    <?php echo $icon('<polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>', 'matomo-btn-icon'); ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($stats['debug_info'])) : ?>
            <?php $d = $stats['debug_info']; ?>
            <div class="matomo-debug-grid">
                <?php foreach (['Source' => 'source', 'Cache' => 'cache', 'Connection' => 'connection', 'Age' => 'age', 'HTTP' => 'http_code', 'Duration' => 'duration', 'PHP' => 'php_ver', 'Memory' => 'memory', 'Matomo' => 'matomo_ver', 'Size' => 'size'] as $label => $field) : ?>
                    <div class="matomo-debug-item">
                        <span class="matomo-debug-label"><?php echo $label; ?>:</span>
                        <span class="matomo-debug-value"><?php echo $e($d[$field] ?? '-'); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($d['api_methods'])) : ?>
                <div class="matomo-debug-methods">
                    <?php foreach ($d['api_methods'] as $methodName => $methodSize) : ?>
                        <div class="matomo-debug-method">
                            <span class="matomo-debug-method-name"><?php echo $e($methodName); ?></span>
                            <span class="matomo-debug-method-size"><?php echo $e($methodSize); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
