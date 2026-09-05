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

$rows = HUB_statsEnrichRows(HUB_roleEnrichRows(HUB_auditActivePlugins()));

if (isset($_GET['export']) && $_GET['export'] === 'md') {
    $markdown = HUB_roleMarkdown($rows, defined('VERSION') ? VERSION : '-', PHP_VERSION, plugin_chkVersion_hub());
    $filename = 'geeklog-hub-audit-' . date('Ymd-His') . '.md';
    if (!headers_sent()) {
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
    }
    echo $markdown;
    exit;
}

function HUB_adminEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function HUB_adminYesNo($value)
{
    return $value ? '<strong>&#10003;</strong>' : '<span style="color:#777">&mdash;</span>';
}

function HUB_adminReadiness($readiness)
{
    $readiness = is_array($readiness) ? $readiness : array();
    $readiness = array_merge(array(
        'label' => 'Unknown',
        'core_total' => 0,
        'core_score' => 0,
        'optional_total' => 0,
        'optional_score' => 0,
    ), $readiness);

    $out = '<strong>' . HUB_adminEscape($readiness['label']) . '</strong>';
    $parts = array();
    if ($readiness['core_total'] > 0) {
        $parts[] = 'C ' . (int) $readiness['core_score'] . '/' . (int) $readiness['core_total'];
    }
    if ($readiness['optional_total'] > 0) {
        $parts[] = 'O ' . (int) $readiness['optional_score'] . '/' . (int) $readiness['optional_total'];
    }
    if (!empty($parts)) {
        $out .= '<br><span class="hub-readiness-score">' . implode(' &middot; ', $parts) . '</span>';
    }
    return $out;
}

function HUB_adminList($items)
{
    if (!is_array($items) || empty($items)) {
        return '<div class="hub-empty">Not detected</div>';
    }
    $out = '<ul class="hub-list">';
    foreach ($items as $item) {
        $out .= '<li><code>' . HUB_adminEscape($item) . '</code></li>';
    }
    return $out . '</ul>';
}

function HUB_adminCard($title, $items, $badge)
{
    $out = '<section class="hub-card"><div class="hub-card-title"><span>' . HUB_adminEscape($title) . '</span><span class="hub-badge">' . HUB_adminEscape($badge) . '</span></div>';
    return $out . HUB_adminList($items) . '</section>';
}

function HUB_adminDetails($row)
{
    $details = isset($row['details']) && is_array($row['details']) ? $row['details'] : array();
    $details = array_merge(array(
        'item_info' => array(),
        'related_items' => array(),
        'id_to_url' => array(),
        'services' => array(),
        'blocks' => array(),
        'autotags' => array(),
        'search' => array(),
    ), $details);

    $role = isset($row['role']) && is_array($row['role']) ? $row['role'] : array();
    $role = array_merge(array('label' => 'Unknown', 'evidence' => array()), $role);

    $lifecycleEmitter = isset($row['lifecycle_emitter']) && is_array($row['lifecycle_emitter']) ? $row['lifecycle_emitter'] : array();
    $lifecycleListener = isset($row['lifecycle_listener']) && is_array($row['lifecycle_listener']) ? $row['lifecycle_listener'] : array();
    $lifecycleContract = isset($row['lifecycle_contract']) && is_array($row['lifecycle_contract']) ? $row['lifecycle_contract'] : array();
    $objectTypes = isset($row['object_types']) && is_array($row['object_types']) ? $row['object_types'] : array();
    $additionalCapabilities = isset($row['additional_capabilities']) && is_array($row['additional_capabilities']) ? $row['additional_capabilities'] : array();
    $apiSurface = isset($row['api_surface']) && is_array($row['api_surface']) ? $row['api_surface'] : array();
    $recommendations = isset($row['role_recommendations']) && is_array($row['role_recommendations']) ? $row['role_recommendations'] : array();

    $out = '<tr class="hub-details-row"><td colspan="11"><details class="hub-details"><summary>Details / Recommendations</summary><div class="hub-details-body">';
    $out .= '<div class="hub-role"><strong>Inferred role:</strong> ' . HUB_adminEscape($role['label']);
    if (!empty($role['evidence'])) {
        $out .= ' &mdash; ' . HUB_adminEscape(implode('; ', $role['evidence']));
    }
    $out .= '</div>';

    $out .= '<h4>Hub-relevant capabilities</h4><div class="hub-grid">';
    $out .= HUB_adminCard('Item Info', $details['item_info'], empty($details['item_info']) ? 'Missing' : 'Runtime');
    $out .= HUB_adminCard('Related Items', $details['related_items'], empty($details['related_items']) ? 'Missing' : 'Runtime');
    $out .= HUB_adminCard('ID to URL', $details['id_to_url'], empty($details['id_to_url']) ? 'Missing' : 'Runtime');
    $out .= HUB_adminCard('Services', $details['services'], empty($details['services']) ? 'Missing' : 'Runtime');
    $out .= HUB_adminCard('Lifecycle emitter', $lifecycleEmitter, 'Source evidence');
    $out .= HUB_adminCard('Lifecycle listener', $lifecycleListener, 'Runtime');
    $out .= HUB_adminCard('Lifecycle contract', $lifecycleContract, 'Compatibility');
    $out .= HUB_adminCard('Object types', $objectTypes, 'Discovered');
    $out .= '</div>';

    if (!empty($row['search_types_function'])) {
        $out .= '<div class="hub-note"><strong>Object type discovery:</strong> <code>' . HUB_adminEscape($row['search_types_function']) . '</code> was called when safe to do so.</div>';
    }

    $out .= '<h4>Embedding / discovery</h4><div class="hub-grid">';
    $out .= HUB_adminCard('Blocks', $details['blocks'], empty($details['blocks']) ? 'Missing' : 'Runtime');
    $out .= HUB_adminCard('Autotags', $details['autotags'], empty($details['autotags']) ? 'Missing' : 'Runtime');
    $out .= HUB_adminCard('Search', $details['search'], empty($details['search']) ? 'Missing' : 'Runtime');
    $out .= '</div>';

    $out .= '<h4>Additional Geeklog capabilities</h4>';
    $out .= HUB_adminList($additionalCapabilities);

    $out .= '<details class="hub-advanced"><summary>Advanced API surface (' . count($apiSurface) . ' callbacks)</summary><div class="hub-advanced-body">' . HUB_adminList($apiSurface) . '</div></details>';
    $out .= '<div class="hub-legend"><strong>Evidence:</strong> ✓ Runtime = loaded/callable; ◐ Source = call found in PHP source; ? = cannot be concluded automatically.</div>';
    $out .= '<section class="hub-recommend"><strong>Recommendations</strong>';
    if (empty($recommendations)) {
        $out .= '<p>None.</p>';
    } else {
        $out .= '<ul>';
        foreach ($recommendations as $recommendation) {
            $out .= '<li>' . HUB_adminEscape($recommendation) . '</li>';
        }
        $out .= '</ul>';
    }
    return $out . '</section></div></details></td></tr>';
}

$content = '<style>';
$content .= '.hub-details-row>td{padding:0 10px 14px!important;border-top:0!important}.hub-details>summary{cursor:pointer;font-weight:bold;padding:9px 12px;background:#f6f7f9;border-radius:4px}.hub-details-body{padding:4px}.hub-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px}.hub-card{min-width:0;border:1px solid #d9dde5;border-radius:5px;padding:10px 12px;background:#fff}.hub-card-title{display:flex;justify-content:space-between;gap:8px;font-weight:bold;margin-bottom:7px}.hub-badge{font-size:11px;font-weight:normal;padding:1px 6px;border-radius:10px;background:#eceff3;color:#5f6670;white-space:nowrap}.hub-list{margin:0;padding-left:18px}.hub-list li{margin:3px 0}.hub-list code{white-space:normal;overflow-wrap:anywhere;word-break:break-word}.hub-empty{color:#777;font-style:italic}.hub-role{margin:10px 0;padding:8px 10px;background:#f3f7f4;border-left:4px solid #6c9b74;border-radius:3px}.hub-note,.hub-legend{margin:8px 0 12px;padding:8px 10px;background:#f6f7f9;border-radius:4px}.hub-advanced{margin:4px 0 12px;border:1px solid #d9dde5;border-radius:5px}.hub-advanced>summary{cursor:pointer;font-weight:bold;padding:9px 11px}.hub-advanced-body{padding:0 12px 10px}.hub-recommend{display:block;border-left:4px solid #d7a900;background:#fffbea;padding:10px 14px;border-radius:3px}.hub-readiness-score{font-size:.88em;color:#666;font-weight:normal}.hub-export{display:inline-block;margin:8px 0 14px;padding:7px 12px;border:1px solid #b9c0c8;border-radius:4px;background:#f6f7f9;text-decoration:none;font-weight:bold}@media(max-width:1100px){.hub-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.hub-grid{grid-template-columns:1fr}}</style>';
$content .= '<nav style="margin:0 0 18px"><strong>Plugin interoperability audit</strong> &nbsp; <a href="link-audit.php">Article link audit</a></nav>';
$content .= '<h2>Plugin interoperability audit</h2>';
$content .= '<p>Hub infers each plugin role before evaluating readiness, so content, presentation, service and infrastructure plugins are not judged against the same contract.</p>';
$content .= '<a class="hub-export" href="?export=md">Export audit as Markdown (.md)</a>';
$content .= '<p><strong>Geeklog:</strong> ' . HUB_adminEscape(defined('VERSION') ? VERSION : '-') . ' &nbsp; <strong>PHP:</strong> ' . HUB_adminEscape(PHP_VERSION) . ' &nbsp; <strong>Hub:</strong> ' . HUB_adminEscape(plugin_chkVersion_hub()) . '</p>';
$content .= '<div style="overflow-x:auto"><table class="admin-list" style="width:100%;border-collapse:collapse"><thead><tr><th>Plugin</th><th>Version</th><th>Role</th><th>Item Info</th><th>Related Items</th><th>ID&rarr;URL</th><th>Blocks</th><th>Autotags</th><th>Search</th><th>Services</th><th>Readiness</th></tr></thead><tbody>';

if (empty($rows)) {
    $content .= '<tr><td colspan="11">No active plugins were detected.</td></tr>';
} else {
    foreach ($rows as $row) {
        $c = isset($row['caps']) && is_array($row['caps']) ? $row['caps'] : array();
        $c = array_merge(array(
            'item_info' => false,
            'related_items' => false,
            'id_to_url' => false,
            'blocks' => false,
            'autotags' => false,
            'search' => false,
            'services' => false,
        ), $c);
        $role = isset($row['role']) && is_array($row['role']) ? $row['role'] : array('label' => 'Unknown');
        $roleLabel = isset($role['label']) ? $role['label'] : 'Unknown';
        $content .= '<tr><td><strong>' . HUB_adminEscape(isset($row['plugin']) ? $row['plugin'] : '') . '</strong></td><td>' . HUB_adminEscape(isset($row['version']) ? $row['version'] : '') . '</td><td>' . HUB_adminEscape($roleLabel) . '</td>';
        $content .= '<td style="text-align:center">' . HUB_adminYesNo($c['item_info']) . '</td><td style="text-align:center">' . HUB_adminYesNo($c['related_items']) . '</td><td style="text-align:center">' . HUB_adminYesNo($c['id_to_url']) . '</td><td style="text-align:center">' . HUB_adminYesNo($c['blocks']) . '</td><td style="text-align:center">' . HUB_adminYesNo($c['autotags']) . '</td><td style="text-align:center">' . HUB_adminYesNo($c['search']) . '</td><td style="text-align:center">' . HUB_adminYesNo($c['services']) . '</td><td>' . HUB_adminReadiness(isset($row['role_readiness']) ? $row['role_readiness'] : array()) . '</td></tr>';
        $content .= HUB_adminDetails($row);
    }
}
$content .= '</tbody></table></div>';
$content .= '<h3>Audit meaning</h3><p><strong>Role</strong>: inferred from existing Geeklog capabilities. <strong>C</strong>: core score for that role. <strong>O</strong>: optional interoperability score, including ID→URL where available. Statistics are reported separately and do not affect readiness. Runtime, source evidence and inference remain distinct.</p>';
$display = COM_startBlock('Hub ' . plugin_chkVersion_hub()) . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
