<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'system/lib-admin.php';

if (!SEC_hasRights('hub.admin')) {
    COM_accessLog('User ' . (int) $_USER['uid'] . ' attempted to access Hub administration without permission.');
    $display = COM_startBlock('Access denied') . 'You do not have sufficient rights to access this page.' . COM_endBlock();
    COM_output(COM_createHTMLDocument($display));
    exit;
}

$rows = HUB_auditActivePlugins();

function HUB_adminEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function HUB_adminYesNo($value)
{
    return $value ? '<span title="Detected">&#10003;</span>' : '<span title="Not detected">&mdash;</span>';
}

function HUB_adminReadiness($value)
{
    if ($value === 'ready') {
        return '<strong>Ready</strong>';
    }
    if ($value === 'partial') {
        return '<strong>Partial</strong>';
    }
    return 'Basic';
}

function HUB_adminCapabilityDetails($details)
{
    if (empty($details)) {
        return '<span>&mdash;</span>';
    }

    $out = '<ul style="margin:4px 0 8px 18px">';
    foreach ($details as $detail) {
        $out .= '<li><code>' . HUB_adminEscape($detail) . '</code></li>';
    }
    $out .= '</ul>';

    return $out;
}

function HUB_adminDetailsRow($row)
{
    $labels = array(
        'item_info'     => 'Item Info',
        'related_items' => 'Related Items',
        'blocks'        => 'Blocks',
        'autotags'      => 'Autotags',
        'search'        => 'Search',
        'services'      => 'Services',
    );

    $out = '<tr class="hub-audit-details"><td colspan="9" style="padding:0 14px 14px 14px">';
    $out .= '<details style="margin-top:4px">';
    $out .= '<summary style="cursor:pointer;font-weight:bold">Details / Recommendations</summary>';
    $out .= '<div style="padding:10px 8px 0 8px">';
    $out .= '<div style="display:flex;flex-wrap:wrap;gap:18px">';

    foreach ($labels as $key => $label) {
        $out .= '<div style="min-width:180px;max-width:320px">';
        $out .= '<strong>' . HUB_adminEscape($label) . '</strong>';
        $out .= HUB_adminCapabilityDetails($row['details'][$key]);
        $out .= '</div>';
    }

    $out .= '</div>';
    $out .= '<strong>Recommendations</strong>';
    if (empty($row['recommendations'])) {
        $out .= '<p>No recommendation.</p>';
    } else {
        $out .= '<ul style="margin-top:4px">';
        foreach ($row['recommendations'] as $recommendation) {
            $out .= '<li>' . HUB_adminEscape($recommendation) . '</li>';
        }
        $out .= '</ul>';
    }
    $out .= '</div></details></td></tr>';

    return $out;
}

$content = '';
$content .= '<h2>Plugin interoperability audit</h2>';
$content .= '<p>This first Hub milestone audits active plugins using capabilities that can be detected safely at runtime. Expand <strong>Details / Recommendations</strong> under a plugin to see detected function names, autotag names and suggested interoperability improvements. Lifecycle notifications (PLG_itemSaved / PLG_itemDeleted) are intentionally not guessed.</p>';
$content .= '<p><strong>Geeklog:</strong> ' . HUB_adminEscape(defined('VERSION') ? VERSION : '-') . ' &nbsp; <strong>PHP:</strong> ' . HUB_adminEscape(PHP_VERSION) . ' &nbsp; <strong>Hub:</strong> 0.1.0</p>';
$content .= '<div style="overflow-x:auto"><table class="admin-list" style="width:100%;border-collapse:collapse">';
$content .= '<thead><tr>';
$content .= '<th>Plugin</th><th>Version</th><th>Item Info</th><th>Related Items</th><th>Blocks</th><th>Autotags</th><th>Search</th><th>Services</th><th>Readiness</th>';
$content .= '</tr></thead><tbody>';

if (empty($rows)) {
    $content .= '<tr><td colspan="9">No active plugins were detected.</td></tr>';
} else {
    foreach ($rows as $row) {
        $caps = $row['caps'];
        $content .= '<tr>';
        $content .= '<td><strong>' . HUB_adminEscape($row['plugin']) . '</strong></td>';
        $content .= '<td>' . HUB_adminEscape($row['version']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['item_info']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['related_items']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['blocks']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['autotags']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['search']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['services']) . '</td>';
        $content .= '<td>' . HUB_adminReadiness($row['readiness']) . ' (' . (int) $row['score'] . '/6)</td>';
        $content .= '</tr>';
        $content .= HUB_adminDetailsRow($row);
    }
}

$content .= '</tbody></table></div>';
$content .= '<h3>Audit meaning</h3>';
$content .= '<p><strong>Item Info</strong>: plugin_getiteminfo_*; <strong>Related Items</strong>: plugin_getrelateditems_*; <strong>Blocks</strong>: plugin_getBlocks_*; <strong>Autotags</strong>: plugin_autotags_*; <strong>Search</strong>: plugin_dopluginsearch_*; <strong>Services</strong>: detectable Geeklog service/webservice entry points.</p>';

$display = COM_startBlock('Hub 0.1.0') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
