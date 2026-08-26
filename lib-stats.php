<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function HUB_statsPluginCapabilities($plugin)
{
    $show = 'plugin_showstats_' . $plugin;
    $summary = 'plugin_statssummary_' . $plugin;
    $hasShow = function_exists($show);
    $hasSummary = function_exists($summary);
    $details = array();

    if ($hasShow && $hasSummary) {
        $details[] = '✓ Statistics: Full';
    } elseif ($hasShow || $hasSummary) {
        $details[] = '◐ Statistics: Partial';
    } else {
        $details[] = '— Statistics: None';
    }

    if ($hasShow) {
        $details[] = '✓ Statistics page contribution: ' . HUB_auditFunctionSignature($show);
    }
    if ($hasSummary) {
        $details[] = '✓ Statistics summary: ' . HUB_auditFunctionSignature($summary);
    }

    return $details;
}

function HUB_statsEnrichRows($rows)
{
    foreach ($rows as $key => $row) {
        $plugin = isset($row['plugin']) ? $row['plugin'] : '';
        $statistics = HUB_statsPluginCapabilities($plugin);
        if (!isset($rows[$key]['additional_capabilities']) || !is_array($rows[$key]['additional_capabilities'])) {
            $rows[$key]['additional_capabilities'] = array();
        }
        $rows[$key]['additional_capabilities'] = array_merge($statistics, $rows[$key]['additional_capabilities']);
        $rows[$key]['statistics'] = $statistics;
    }
    if (function_exists('HUB_distributionEnrichRows')) {
        return HUB_distributionEnrichRows($rows);
    }

    return $rows;
}
