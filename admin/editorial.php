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

function HUB_editorialAdminTokenField()
{
    return '<input type="hidden" name="' . CSRF_TOKEN . '" value="'
        . HUB_editorialAdminEscape(SEC_createToken()) . '">';
}

$editorialMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SEC_checkToken()) {
        $editorialMessage = '<div class="uk-alert-danger">Invalid security token.</div>';
    } else {
        $action = isset($_POST['hub_action']) ? (string) $_POST['hub_action'] : '';
        if ($action === 'content_gap_decision') {
            $pillarId = isset($_POST['pillar_id']) ? (int) $_POST['pillar_id'] : 0;
            $topicId = isset($_POST['topic_id']) ? (string) $_POST['topic_id'] : '';
            $decisionAction = isset($_POST['decision_action']) ? (string) $_POST['decision_action'] : '';

            if ($decisionAction === 'restore') {
                $saved = HUB_deleteSuggestionDecision('content-gap', $pillarId, 'topic', $topicId);
                $editorialMessage = $saved
                    ? '<div class="uk-alert-success">Content-gap decision restored.</div>'
                    : '<div class="uk-alert-danger">Unable to restore content-gap decision.</div>';
            } else {
                $decision = $decisionAction === 'defer' ? 'deferred' : 'dismissed';
                $deferUntil = $decision === 'deferred' ? time() + (30 * 86400) : 0;
                $saved = HUB_saveSuggestionDecision(
                    'content-gap',
                    $pillarId,
                    'topic',
                    $topicId,
                    $decision,
                    $deferUntil
                );
                $editorialMessage = $saved
                    ? '<div class="uk-alert-success">Content gap '
                        . ($decision === 'deferred' ? 'deferred for 30 days.' : 'dismissed.')
                        . '</div>'
                    : '<div class="uk-alert-danger">Unable to save content-gap decision.</div>';
            }
        }
    }
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

$inventory = function_exists('HUB_editorialInventory')
    ? HUB_editorialInventory(false)
    : array('pillars' => array());
$inventoryPillars = isset($inventory['pillars']) && is_array($inventory['pillars'])
    ? $inventory['pillars'] : array();

$roadmap = function_exists('HUB_editorialRoadmap')
    ? HUB_editorialRoadmap(10)
    : array();

$contentGaps = isset($roadmap['content_gaps']) && is_array($roadmap['content_gaps'])
    ? $roadmap['content_gaps']
    : array();

$activeContentGapDecisions = array();
if (function_exists('HUB_getSuggestionDecisions')) {
    foreach (HUB_getSuggestionDecisions() as $decisionRow) {
        if (is_array($decisionRow)
            && !empty($decisionRow['is_active'])
            && isset($decisionRow['suggestion_kind'])
            && $decisionRow['suggestion_kind'] === 'content-gap'
        ) {
            $activeContentGapDecisions[] = $decisionRow;
        }
    }
}

$export = isset($_GET['export']) ? strtolower(trim((string) $_GET['export'])) : '';
if ($export === 'md' && function_exists('HUB_editorialRoadmapMarkdown')) {
    if (!headers_sent()) {
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="hub-editorial-roadmap.md"');
        header('X-Content-Type-Options: nosniff');
    }
    echo HUB_editorialRoadmapMarkdown($roadmap);
    exit;
}
if ($export === 'json') {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="hub-editorial-roadmap.json"');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($roadmap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$content = HUB_adminNavigation('editorial');
$content .= '<h1>Editorial mapping</h1>';
$content .= $editorialMessage;
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

$content .= '<h2>Approved relation inventory</h2>';
if (empty($inventoryPillars)) {
    $content .= '<p>No approved relation is currently available in the structural inventory.</p>';
} else {
    foreach ($inventoryPillars as $inventoryPillar) {
        $source = HUB_editorialAdminEscape(
            (isset($inventoryPillar['source_type']) ? $inventoryPillar['source_type'] : '')
            . ':'
            . (isset($inventoryPillar['source_id']) ? $inventoryPillar['source_id'] : '')
        );
        $items = isset($inventoryPillar['items']) && is_array($inventoryPillar['items'])
            ? $inventoryPillar['items'] : array();

        $pillarEditorialRole = isset($inventoryPillar['editorial_role'])
            ? (string) $inventoryPillar['editorial_role']
            : '';
        $content .= '<details style="margin:0 0 12px;border:1px solid #d7d7d7;border-radius:4px;padding:10px">'
            . '<summary style="cursor:pointer"><strong>' . $source . '</strong>'
            . ($pillarEditorialRole !== ''
                ? ' · editorial: <code>' . HUB_editorialAdminEscape($pillarEditorialRole) . '</code>'
                : '')
            . ' — ' . count($items) . ' approved item(s)</summary>';

        if (empty($items)) {
            $content .= '<p style="margin:10px 0 0">No approved item.</p></details>';
            continue;
        }

        $content .= '<table class="uk-table uk-table-divider uk-table-small" style="margin-top:10px"><thead><tr>'
            . '<th>Structural role</th><th>Editorial role</th><th>Identity</th><th>Position</th><th>Nested pillar</th><th>Parents</th>'
            . '</tr></thead><tbody>';

        foreach ($items as $item) {
            $identity = HUB_editorialAdminEscape(
                (isset($item['type']) ? $item['type'] : '')
                . ':'
                . (isset($item['id']) ? $item['id'] : '')
            );
            $role = HUB_editorialAdminEscape(
                isset($item['relation_role']) ? $item['relation_role'] : 'related'
            );
            $editorialRole = HUB_editorialAdminEscape(
                isset($item['editorial_role']) ? $item['editorial_role'] : ''
            );
            $nested = !empty($item['is_nested_pillar'])
                ? 'yes'
                . (!empty($item['nested_pillar_id'])
                    ? ' (#' . (int) $item['nested_pillar_id'] . ')'
                    : '')
                : 'no';

            $content .= '<tr><td><code>' . $role . '</code></td>'
                . '<td>' . ($editorialRole !== '' ? '<code>' . $editorialRole . '</code>' : '—') . '</td>'
                . '<td><code>' . $identity . '</code></td>'
                . '<td>' . (isset($item['position']) ? (int) $item['position'] : 0) . '</td>'
                . '<td>' . HUB_editorialAdminEscape($nested) . '</td>'
                . '<td>' . (isset($item['parent_count']) ? (int) $item['parent_count'] : 0) . '</td></tr>';
        }

        $content .= '</tbody></table></details>';
    }
}

$content .= '<h2>Content gaps / opportunities</h2>';
if (empty($contentGaps)) {
    $content .= '<p>No topic-coverage content gap is currently detected.</p>';
} else {
    $content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
        . '<th>Pillar</th><th>Topic</th><th>Coverage</th><th>Signal</th><th>Actions</th>'
        . '</tr></thead><tbody>';

    foreach ($contentGaps as $gap) {
        $pillarId = isset($gap['pillar_id']) ? (int) $gap['pillar_id'] : 0;
        $topicId = isset($gap['topic_id']) ? (string) $gap['topic_id'] : '';
        $topicLabel = isset($gap['topic_label']) ? (string) $gap['topic_label'] : $topicId;
        $articleCount = isset($gap['article_count']) ? (int) $gap['article_count'] : 0;
        $kind = isset($gap['kind']) ? (string) $gap['kind'] : '';

        $signal = $kind === 'create-content'
            ? 'Create-content opportunity'
            : 'Thin coverage review';

        $content .= '<tr><td>#' . $pillarId . '</td>'
            . '<td><strong>' . HUB_editorialAdminEscape($topicLabel) . '</strong><br><code>'
            . HUB_editorialAdminEscape($topicId) . '</code></td>'
            . '<td>' . $articleCount . ' published article' . ($articleCount === 1 ? '' : 's') . '</td>'
            . '<td>' . HUB_editorialAdminEscape($signal) . '</td>'
            . '<td><div style="display:flex;gap:6px;flex-wrap:wrap">'
            . '<form method="post" action="editorial.php">'
            . HUB_editorialAdminTokenField()
            . '<input type="hidden" name="hub_action" value="content_gap_decision">'
            . '<input type="hidden" name="pillar_id" value="' . $pillarId . '">'
            . '<input type="hidden" name="topic_id" value="' . HUB_editorialAdminEscape($topicId) . '">'
            . '<input type="hidden" name="decision_action" value="defer">'
            . '<button type="submit" class="uk-button">Defer 30 days</button></form>'
            . '<form method="post" action="editorial.php">'
            . HUB_editorialAdminTokenField()
            . '<input type="hidden" name="hub_action" value="content_gap_decision">'
            . '<input type="hidden" name="pillar_id" value="' . $pillarId . '">'
            . '<input type="hidden" name="topic_id" value="' . HUB_editorialAdminEscape($topicId) . '">'
            . '<input type="hidden" name="decision_action" value="dismiss">'
            . '<button type="submit" class="uk-button">Dismiss</button></form>'
            . '</div></td></tr>';
    }

    $content .= '</tbody></table>';
}

if (!empty($activeContentGapDecisions)) {
    $content .= '<h3>Hidden content-gap decisions</h3>'
        . '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
        . '<th>Decision</th><th>Topic</th><th>Pillar</th><th>Until</th><th>Action</th>'
        . '</tr></thead><tbody>';

    foreach ($activeContentGapDecisions as $decisionRow) {
        $decision = isset($decisionRow['decision']) ? (string) $decisionRow['decision'] : '';
        $pillarId = isset($decisionRow['pillar_id']) ? (int) $decisionRow['pillar_id'] : 0;
        $topicId = isset($decisionRow['item_id']) ? (string) $decisionRow['item_id'] : '';
        $deferUntil = isset($decisionRow['defer_until']) ? (int) $decisionRow['defer_until'] : 0;
        $until = $decision === 'deferred' && $deferUntil > 0 ? date('Y-m-d', $deferUntil) : '—';

        $content .= '<tr><td>' . HUB_editorialAdminEscape($decision) . '</td>'
            . '<td><code>' . HUB_editorialAdminEscape($topicId) . '</code></td>'
            . '<td>#' . $pillarId . '</td>'
            . '<td>' . HUB_editorialAdminEscape($until) . '</td>'
            . '<td><form method="post" action="editorial.php">'
            . HUB_editorialAdminTokenField()
            . '<input type="hidden" name="hub_action" value="content_gap_decision">'
            . '<input type="hidden" name="pillar_id" value="' . $pillarId . '">'
            . '<input type="hidden" name="topic_id" value="' . HUB_editorialAdminEscape($topicId) . '">'
            . '<input type="hidden" name="decision_action" value="restore">'
            . '<button type="submit" class="uk-button">Restore</button></form></td></tr>';
    }

    $content .= '</tbody></table>';
}

$content .= '<h2>Editorial roadmap preview</h2>';
$content .= '<p><a class="uk-button" href="editorial.php?export=md">Download Markdown</a> '
    . '<a class="uk-button" href="editorial.php?export=json">Download JSON</a></p>';

$roadmapSummary = isset($roadmap['executive_summary']) && is_array($roadmap['executive_summary'])
    ? $roadmap['executive_summary']
    : array();
$content .= '<ul>'
    . '<li>New pillar opportunities: ' . (isset($roadmapSummary['new_pillar_opportunities']) ? (int) $roadmapSummary['new_pillar_opportunities'] : 0) . '</li>'
    . '<li>Relation candidates: ' . (isset($roadmapSummary['relation_candidates']) ? (int) $roadmapSummary['relation_candidates'] : 0) . '</li>'
    . '<li>Close-content review pairs: ' . (isset($roadmapSummary['close_content_review_pairs']) ? (int) $roadmapSummary['close_content_review_pairs'] : 0) . '</li>'
    . '<li>Temporal review candidates: ' . (isset($roadmapSummary['temporal_review_candidates']) ? (int) $roadmapSummary['temporal_review_candidates'] : 0) . '</li>'
    . '</ul>';

$nextActions = isset($roadmap['prioritized_next_actions']) && is_array($roadmap['prioritized_next_actions'])
    ? $roadmap['prioritized_next_actions']
    : array();
$content .= '<h3>Prioritized next actions</h3>';
if (empty($nextActions)) {
    $content .= '<p>No editorial action is currently suggested.</p>';
} else {
    $content .= '<ol>';
    foreach (array_slice($nextActions, 0, 10) as $action) {
        $identity = (isset($action['type']) ? $action['type'] : '')
            . ':'
            . (isset($action['id']) ? $action['id'] : '');
        $content .= '<li><strong>'
            . HUB_editorialAdminEscape(isset($action['reason']) ? $action['reason'] : '')
            . '</strong> — <code>' . HUB_editorialAdminEscape($identity) . '</code>'
            . ' (priority ' . (isset($action['priority']) ? (int) $action['priority'] : 0) . ')</li>';
    }
    $content .= '</ol>';
}

$content .= '<h2>Scope boundary</h2>';
$content .= '<p>This 0.8.0 view reports approved editorial structure only. '
    . 'Cluster health, unresolved/broken relations, reciprocal-link health, orphan checks '
    . 'and canonical integrity remain part of the 0.9.0 diagnostics layer.</p>';

$display = COM_startBlock('Hub editorial mapping') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
