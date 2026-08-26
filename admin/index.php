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
    return $value
        ? '<span class="hub-cap-yes" title="Detected">&#10003;</span>'
        : '<span class="hub-cap-no" title="Not detected">&mdash;</span>';
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
        return '<div class="hub-cap-empty">Not detected</div>';
    }

    $out = '<ul class="hub-cap-list">';
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

    $out = '<tr class="hub-audit-details"><td colspan="9">';
    $out .= '<details class="hub-details">';
    $out .= '<summary>Details / Recommendations</summary>';
    $out .= '<div class="hub-details-body">';
    $out .= '<div class="hub-cap-grid">';

    foreach ($labels as $key => $label) {
        $detected = !empty($row['details'][$key]);
        $out .= '<section class="hub-cap-card' . ($detected ? ' hub-cap-card-on' : '') . '">';
        $out .= '<div class="hub-cap-title">';
        $out .= '<span>' . HUB_adminEscape($label) . '</span>';
        $out .= $detected
            ? '<span class="hub-cap-badge hub-cap-badge-on">Detected</span>'
            : '<span class="hub-cap-badge">Missing</span>';
        $out .= '</div>';
        $out .= HUB_adminCapabilityDetails($row['details'][$key]);
        $out .= '</section>';
    }

    $out .= '</div>';
    $out .= '<section class="hub-recommendations">';
    $out .= '<div class="hub-recommendations-title">Recommendations</div>';
    if (empty($row['recommendations'])) {
        $out .= '<p class="hub-recommendations-empty">No recommendation. The currently detectable interoperability surface is sufficient for this audit.</p>';
    } else {
        $out .= '<ul>';
        foreach ($row['recommendations'] as $recommendation) {
            $out .= '<li>' . HUB_adminEscape($recommendation) . '</li>';
        }
        $out .= '</ul>';
    }
    $out .= '</section>';
    $out .= '</div></details></td></tr>';

    return $out;
}

$content = '';
$content .= '<style>';
$content .= '.hub-audit-details>td{padding:0 10px 14px 10px!important;border-top:0!important}';
$content .= '.hub-details{margin:0}';
$content .= '.hub-details>summary{cursor:pointer;font-weight:bold;padding:9px 12px;list-style-position:inside;background:#f6f7f9;border-radius:4px}';
$content .= '.hub-details[open]>summary{margin-bottom:10px}';
$content .= '.hub-details-body{padding:2px 4px 4px}';
$content .= '.hub-cap-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px}';
$content .= '.hub-cap-card{min-width:0;border:1px solid #d9dde5;border-radius:5px;padding:10px 12px;background:#fafafa}';
$content .= '.hub-cap-card-on{background:#fff}';
$content .= '.hub-cap-title{display:flex;align-items:center;justify-content:space-between;gap:8px;font-weight:bold;margin-bottom:7px}';
$content .= '.hub-cap-badge{display:inline-block;font-size:11px;font-weight:normal;line-height:1.4;padding:1px 6px;border-radius:10px;background:#eceff3;color:#5f6670;white-space:nowrap}';
$content .= '.hub-cap-badge-on{background:#e8f3ea;color:#286335}';
$content .= '.hub-cap-list{margin:0;padding-left:18px}';
$content .= '.hub-cap-list li{margin:3px 0;line-height:1.35}';
$content .= '.hub-cap-list code{white-space:normal;overflow-wrap:anywhere;word-break:break-word;font-size:.92em}';
$content .= '.hub-cap-empty{color:#7a8088;font-style:italic;font-size:.92em}';
$content .= '.hub-recommendations{border-left:4px solid #d7a900;background:#fffbea;padding:10px 14px;border-radius:3px}';
$content .= '.hub-recommendations-title{font-weight:bold;margin-bottom:4px}';
$content .= '.hub-recommendations ul{margin:4px 0 0 20px;padding:0}';
$content .= '.hub-recommendations li{margin:4px 0;line-height:1.4}';
$content .= '.hub-recommendations-empty{margin:0;color:#5f6670}';
$content .= '.hub-cap-yes{font-weight:bold}.hub-cap-no{color:#777}';
$content .= '@media(max-width:1100px){.hub-cap-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}';
$content .= '@media(max-width:700px){.hub-cap-grid{grid-template-columns:1fr}.hub-details>summary{padding:8px}}';
$content .= '</style>';
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
