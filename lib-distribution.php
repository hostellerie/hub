<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function HUB_distributionSyndicationCapabilities($plugin)
{
    $names = 'plugin_getfeednames_' . $plugin;
    $content = 'plugin_getfeedcontent_' . $plugin;
    $update = 'plugin_feedupdatecheck_' . $plugin;

    $hasNames = function_exists($names);
    $hasContent = function_exists($content);
    $hasUpdate = function_exists($update);
    $details = array();

    if ($hasNames && $hasContent) {
        $details[] = '✓ Content Syndication: Full';
    } elseif ($hasNames || $hasContent) {
        $details[] = '◐ Content Syndication: Partial';
    } else {
        $details[] = '— Content Syndication: None';
    }

    if ($hasNames) {
        $details[] = '✓ Feed names: ' . HUB_auditFunctionSignature($names);
    }
    if ($hasContent) {
        $details[] = '✓ Feed content: ' . HUB_auditFunctionSignature($content);
    }
    if ($hasUpdate) {
        $details[] = '✓ Feed update check: ' . HUB_auditFunctionSignature($update);
    }

    return $details;
}

function HUB_distributionSitemapCapabilities($plugin, $row)
{
    $collector = 'plugin_collectSitemapItems_' . $plugin;
    $hasCollector = function_exists($collector);
    $hasItemInfo = !empty($row['caps']['item_info']);
    $details = array();

    if ($hasCollector) {
        $details[] = '✓ XML Sitemap contribution: Native collector';
        $details[] = '✓ Sitemap items: ' . HUB_auditFunctionSignature($collector);
        if ($hasItemInfo) {
            $details[] = '✓ Item Info fallback also available';
        }
    } elseif ($hasItemInfo) {
        $details[] = '✓ XML Sitemap contribution: Item Info fallback';
        $details[] = 'Uses PLG_getItemInfo(type, "*", ...) when no native sitemap collector is exposed.';
    } else {
        $details[] = '— XML Sitemap contribution: None detected';
    }

    return $details;
}

function HUB_distributionEnrichRows($rows)
{
    foreach ($rows as $key => $row) {
        $plugin = isset($row['plugin']) ? $row['plugin'] : '';
        $syndication = HUB_distributionSyndicationCapabilities($plugin);
        $sitemap = HUB_distributionSitemapCapabilities($plugin, $row);

        if (!isset($rows[$key]['additional_capabilities']) || !is_array($rows[$key]['additional_capabilities'])) {
            $rows[$key]['additional_capabilities'] = array();
        }

        $rows[$key]['additional_capabilities'] = array_merge(
            $syndication,
            $sitemap,
            $rows[$key]['additional_capabilities']
        );
        $rows[$key]['syndication'] = $syndication;
        $rows[$key]['sitemap'] = $sitemap;
    }

    return $rows;
}
