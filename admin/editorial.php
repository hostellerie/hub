<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'plugins/hub/lib-admin-ui.php';

if (!SEC_hasRights('hub.admin')) {
    COM_accessLog('User ' . (int) $_USER['uid'] . ' attempted to access Hub editorial mapping without permission.');
    $display = COM_startBlock('Access denied')
        . 'You do not have sufficient rights to access this page.'
        . COM_endBlock();
    COM_output(COM_createHTMLDocument($display));
    exit;
}

function HUB_editorialAdminEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$summary = function_exists('HUB_editorialSummary')
    ? HUB_editorialSummary(false)
    : array();

$roles = isset($summary['roles']) && is_array($summary['roles'])
    ? $summary['roles'] : array();
$providers = isset($summary['providers']) && is_array($summary['providers'])
    ? $summary['providers'] : array();
$pillars = isset($summary['pillar_items']) && is_array($summary['pillar_items'])
    ? $summary['pillar_items'] : array();

$content = HUB_adminNavigation('editorial');
$content .= '<h1>Editorial mapping</h1>';
$content .= '<p>This is a read-only structural view of Hub&#039;s approved editorial graph. '
    . 'It does not create a second graph and does not infer or modify relationships.</p>';

$content .= '<div class="hub-editorial-summary" style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">';
$cards = array(
    'Pillars' => isset($summary['pillars']) ? (int) $summary['pillars'] : 0,
    'Relations' => isset($summary['relations']) ? (int) $summary['relations'] : 0,
    'Nested pillars' => isset($summary['nested_pillars']) ? (int) $summary['nested_pillars'] : 0,
    'Multi-parent items' => isset($summary['multi_parent_items']) ? (int) $summary['multi_parent_items'] : 0,
);
foreach ($cards as $label => $value) {
    $content .= '<div style="min-width:140px;padding:12px;border:1px solid #d7d7d7;border-radius:4px">'
        . '<div style="font-size:1.6em;font-weight:700">' . (int) $value . '</div>'
        . '<div>' . HUB_editorialAdminEscape($label) . '</div>'
        . '</div>';
}
$content .= '</div>';

$content .= '<h2>Structural roles</h2>';
$content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
    . '<th>Role</th><th>Relations</th></tr></thead><tbody>';
foreach (array('related', 'sub-pillar', 'satellite', 'support') as $role) {
    $content .= '<tr><td><code>' . HUB_editorialAdminEscape($role) . '</code></td><td>'
        . (isset($roles[$role]) ? (int) $roles[$role] : 0) . '</td></tr>';
}
$content .= '</tbody></table>';

$content .= '<h2>Provider participation</h2>';
if (empty($providers)) {
    $content .= '<p>No approved relation provider is currently represented.</p>';
} else {
    $content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
        . '<th>Provider</th><th>Approved relations</th></tr></thead><tbody>';
    foreach ($providers as $provider => $count) {
        $content .= '<tr><td><code>' . HUB_editorialAdminEscape($provider) . '</code></td><td>'
            . (int) $count . '</td></tr>';
    }
    $content .= '</tbody></table>';
}

$content .= '<h2>Pillar inventory</h2>';
if (empty($pillars)) {
    $content .= '<p>No enabled Hub pillar is currently defined.</p>';
} else {
    $content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
        . '<th>ID</th><th>Source</th><th>Relations</th><th>Sub-pillars</th>'
        . '<th>Satellites</th><th>Support</th><th>Providers</th></tr></thead><tbody>';

    foreach ($pillars as $pillar) {
        $pillarRoles = isset($pillar['roles']) && is_array($pillar['roles'])
            ? $pillar['roles'] : array();
        $pillarProviders = isset($pillar['providers']) && is_array($pillar['providers'])
            ? $pillar['providers'] : array();

        $providerParts = array();
        foreach ($pillarProviders as $provider => $count) {
            $providerParts[] = HUB_editorialAdminEscape($provider) . ' (' . (int) $count . ')';
        }

        $content .= '<tr>'
            . '<td>' . (isset($pillar['pillar_id']) ? (int) $pillar['pillar_id'] : 0) . '</td>'
            . '<td><code>'
            . HUB_editorialAdminEscape(isset($pillar['source_type']) ? $pillar['source_type'] : '')
            . ':'
            . HUB_editorialAdminEscape(isset($pillar['source_id']) ? $pillar['source_id'] : '')
            . '</code></td>'
            . '<td>' . (isset($pillar['relations']) ? (int) $pillar['relations'] : 0) . '</td>'
            . '<td>' . (isset($pillarRoles['sub-pillar']) ? (int) $pillarRoles['sub-pillar'] : 0) . '</td>'
            . '<td>' . (isset($pillarRoles['satellite']) ? (int) $pillarRoles['satellite'] : 0) . '</td>'
            . '<td>' . (isset($pillarRoles['support']) ? (int) $pillarRoles['support'] : 0) . '</td>'
            . '<td>' . implode(', ', $providerParts) . '</td>'
            . '</tr>';
    }

    $content .= '</tbody></table>';
}

$content .= '<h2>Scope boundary</h2>';
$content .= '<p>This 0.8.0 view reports approved editorial structure only. '
    . 'Cluster health, unresolved/broken relations, reciprocal-link health, orphan checks '
    . 'and canonical integrity remain part of the 0.9.0 diagnostics layer.</p>';

$display = COM_startBlock('Hub editorial mapping') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
