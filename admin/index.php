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

$content = '';
$content .= '<h2>Plugin interoperability audit</h2>';
$content .= '<p>This first Hub milestone audits active plugins using capabilities that can be detected safely at runtime. Lifecycle notifications (PLG_itemSaved / PLG_itemDeleted) are intentionally not guessed; they will be reported when plugins expose an explicit capability declaration.</p>';
$content .= '<p><strong>Geeklog:</strong> ' . htmlspecialchars(defined('VERSION') ? VERSION : '-', ENT_QUOTES, 'UTF-8') . ' &nbsp; <strong>PHP:</strong> ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . ' &nbsp; <strong>Hub:</strong> 0.1.0</p>';
$content .= '<div style="overflow-x:auto"><table class="admin-list" style="width:100%;border-collapse:collapse">';
$content .= '<thead><tr>';
$content .= '<th>Plugin</th><th>Version</th><th>Item Info</th><th>Related Items</th><th>Blocks</th><th>Autotags</th><th>Search</th><th>Services</th><th>Readiness</th>';
$content .= '</tr></thead><tbody>';

if (empty($rows)) {
    $content .= '<tr><td colspan="9">No other active plugins were detected.</td></tr>';
} else {
    foreach ($rows as $row) {
        $caps = $row['caps'];
        $content .= '<tr>';
        $content .= '<td><strong>' . htmlspecialchars($row['plugin'], ENT_QUOTES, 'UTF-8') . '</strong></td>';
        $content .= '<td>' . htmlspecialchars($row['version'], ENT_QUOTES, 'UTF-8') . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['item_info']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['related_items']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['blocks']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['autotags']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['search']) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($caps['services']) . '</td>';
        $content .= '<td>' . HUB_adminReadiness($row['readiness']) . ' (' . (int) $row['score'] . '/6)</td>';
        $content .= '</tr>';
    }
}

$content .= '</tbody></table></div>';
$content .= '<h3>Audit meaning</h3>';
$content .= '<p><strong>Item Info</strong>: plugin_getiteminfo_*; <strong>Related Items</strong>: plugin_getrelateditems_*; <strong>Blocks</strong>: plugin_getBlocks_*; <strong>Autotags</strong>: plugin_autotags_*; <strong>Search</strong>: plugin_dopluginsearch_*; <strong>Services</strong>: detectable Geeklog service/webservice entry points.</p>';

$display = COM_startBlock('Hub 0.1.0') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
