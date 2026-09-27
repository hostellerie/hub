<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'system/lib-admin.php';

if (!SEC_hasRights('hub.admin')) {
    COM_accessLog('User ' . (int) $_USER['uid'] . ' attempted to access Hub relationships without permission.');
    $display = COM_startBlock('Access denied') . 'You do not have sufficient rights to access this page.' . COM_endBlock();
    COM_output(COM_createHTMLDocument($display));
    exit;
}

function HUB_relAdminEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function HUB_relAdminTokenField()
{
    return '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . HUB_relAdminEscape(SEC_createToken()) . '">';
}

function HUB_relAdminStaticPages()
{
    global $_TABLES;

    $rows = array();
    if (empty($_TABLES['staticpage'])) {
        return $rows;
    }

    $result = DB_query(
        "SELECT sp_id, sp_title FROM {$_TABLES['staticpage']} ORDER BY sp_title, sp_id",
        1
    );
    if ($result === false) {
        return $rows;
    }

    while ($row = DB_fetchArray($result)) {
        if (is_array($row) && !empty($row['sp_id'])) {
            $rows[] = $row;
        }
    }

    return $rows;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SEC_checkToken()) {
        $message = '<div class="hub-rel-message hub-rel-error">Invalid security token.</div>';
    } else {
        $action = isset($_POST['hub_action']) ? (string) $_POST['hub_action'] : '';

        if ($action === 'save_pillar') {
            $pillarId = isset($_POST['pillar_id']) ? (int) $_POST['pillar_id'] : 0;
            $sourceId = isset($_POST['source_id']) ? (string) $_POST['source_id'] : '';
            $titleOverride = isset($_POST['title_override']) ? (string) $_POST['title_override'] : '';
            $enabled = !empty($_POST['is_enabled']) ? 1 : 0;

            $saved = HUB_savePillar($pillarId, 'staticpages', $sourceId, $titleOverride, $enabled);
            $message = $saved
                ? '<div class="hub-rel-message">Pillar saved.</div>'
                : '<div class="hub-rel-message hub-rel-error">Unable to save pillar. The Static Page may already be registered.</div>';
        } elseif ($action === 'delete_pillar') {
            $pillarId = isset($_POST['pillar_id']) ? (int) $_POST['pillar_id'] : 0;
            $message = HUB_deletePillar($pillarId)
                ? '<div class="hub-rel-message">Pillar and its relations deleted.</div>'
                : '<div class="hub-rel-message hub-rel-error">Unable to delete pillar.</div>';
        } elseif ($action === 'save_relation') {
            $relationId = isset($_POST['relation_id']) ? (int) $_POST['relation_id'] : 0;
            $pillarId = isset($_POST['pillar_id']) ? (int) $_POST['pillar_id'] : 0;
            $itemType = isset($_POST['item_type']) ? (string) $_POST['item_type'] : '';
            $itemId = isset($_POST['item_id']) ? (string) $_POST['item_id'] : '';
            $position = isset($_POST['position']) ? (int) $_POST['position'] : 0;
            $enabled = !empty($_POST['is_enabled']) ? 1 : 0;

            $saved = HUB_saveRelation($relationId, $pillarId, $itemType, $itemId, $position, $enabled);
            $message = $saved
                ? '<div class="hub-rel-message">Relation saved.</div>'
                : '<div class="hub-rel-message hub-rel-error">Unable to save relation. Check the pillar and unique type + id.</div>';
        } elseif ($action === 'delete_relation') {
            $relationId = isset($_POST['relation_id']) ? (int) $_POST['relation_id'] : 0;
            $message = HUB_deleteRelation($relationId)
                ? '<div class="hub-rel-message">Relation deleted.</div>'
                : '<div class="hub-rel-message hub-rel-error">Unable to delete relation.</div>';
        }
    }
}

$staticPages = HUB_relAdminStaticPages();
$pillars = HUB_getPillars(true);

$content = '<style>'
    . '.hub-rel-nav{margin:0 0 1rem}.hub-rel-nav a{margin-right:1rem}'
    . '.hub-rel-message{padding:.7rem 1rem;margin:0 0 1rem;background:#edf7ed;border-left:4px solid #2e7d32}'
    . '.hub-rel-error{background:#fff1f0;border-left-color:#b00020}'
    . '.hub-rel-card{border:1px solid #d5d8dc;padding:1rem;margin:0 0 1rem;border-radius:4px}'
    . '.hub-rel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;align-items:end}'
    . '.hub-rel-grid label{display:block;font-weight:600}.hub-rel-grid input,.hub-rel-grid select{box-sizing:border-box;width:100%;padding:.45rem}'
    . '.hub-rel-table{width:100%;border-collapse:collapse;margin-top:1rem}.hub-rel-table th,.hub-rel-table td{padding:.45rem;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}'
    . '.hub-rel-actions form{display:inline}.hub-rel-muted{opacity:.7;font-size:.92em}'
    . '</style>';

$content .= '<div class="hub-rel-nav">'
    . '<a href="audit.php">Interoperability audit</a>'
    . '<a href="link-audit.php">Article link audit</a>'
    . '<strong>Pillars &amp; relations</strong>'
    . '</div>';

$content .= '<h1>Hub pillars &amp; manual relations</h1>';
$content .= '<p>Hub 0.3.0 stores only stable <code>type + id</code> identities. Titles and URLs are resolved dynamically from the owning Geeklog provider.</p>';
$content .= $message;

$content .= '<div class="hub-rel-card"><h2>Add Static Page pillar</h2>';
$content .= '<form method="post" action="relations.php">' . HUB_relAdminTokenField();
$content .= '<input type="hidden" name="hub_action" value="save_pillar">';
$content .= '<input type="hidden" name="pillar_id" value="0">';
$content .= '<div class="hub-rel-grid"><label>Static Page<select name="source_id" required>';
$content .= '<option value="">Select a Static Page</option>';
foreach ($staticPages as $page) {
    $content .= '<option value="' . HUB_relAdminEscape($page['sp_id']) . '">'
        . HUB_relAdminEscape($page['sp_title'] . ' [' . $page['sp_id'] . ']') . '</option>';
}
$content .= '</select></label>';
$content .= '<label>Optional title override<input type="text" name="title_override" maxlength="255"></label>';
$content .= '<label><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label>';
$content .= '<div><button type="submit" class="uk-button uk-button-primary">Add pillar</button></div></div></form></div>';

if (empty($pillars)) {
    $content .= '<p>No pillar has been created yet.</p>';
} else {
    foreach ($pillars as $pillar) {
        $resolvedPillar = HUB_resolveObject($pillar['source_type'], $pillar['source_id']);
        $pillarTitle = trim((string) $pillar['title_override']) !== ''
            ? $pillar['title_override']
            : $resolvedPillar['title'];
        $relations = HUB_getRelations($pillar['id'], true);

        $content .= '<div class="hub-rel-card">';
        $content .= '<h2>' . HUB_relAdminEscape($pillarTitle) . '</h2>';
        $content .= '<p class="hub-rel-muted"><code>' . HUB_relAdminEscape($pillar['source_type'])
            . ':' . HUB_relAdminEscape($pillar['source_id']) . '</code>'
            . (!empty($pillar['is_enabled']) ? ' · enabled' : ' · disabled');
        if (!empty($resolvedPillar['url'])) {
            $content .= ' · <a href="' . HUB_relAdminEscape($resolvedPillar['url']) . '">View source</a>';
        }
        $content .= '</p>';

        $content .= '<form method="post" action="relations.php">' . HUB_relAdminTokenField();
        $content .= '<input type="hidden" name="hub_action" value="save_pillar">';
        $content .= '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">';
        $content .= '<input type="hidden" name="source_id" value="' . HUB_relAdminEscape($pillar['source_id']) . '">';
        $content .= '<div class="hub-rel-grid">';
        $content .= '<label>Title override<input type="text" name="title_override" maxlength="255" value="' . HUB_relAdminEscape($pillar['title_override']) . '"></label>';
        $content .= '<label><input type="checkbox" name="is_enabled" value="1"' . (!empty($pillar['is_enabled']) ? ' checked' : '') . '> Enabled</label>';
        $content .= '<div><button type="submit" class="uk-button">Update pillar</button></div></div></form>';

        $content .= '<h3>Relations</h3>';
        if (empty($relations)) {
            $content .= '<p class="hub-rel-muted">No manual relation yet.</p>';
        } else {
            $content .= '<table class="hub-rel-table"><thead><tr><th>Order</th><th>Type + id</th><th>Resolved item</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            foreach ($relations as $relation) {
                $resolved = HUB_resolveObject($relation['item_type'], $relation['item_id']);
                $content .= '<tr><td>' . (int) $relation['position'] . '</td>';
                $content .= '<td><code>' . HUB_relAdminEscape($relation['item_type']) . ':' . HUB_relAdminEscape($relation['item_id']) . '</code></td>';
                $content .= '<td>' . HUB_relAdminEscape($resolved['title']);
                if (!empty($resolved['url'])) {
                    $content .= ' · <a href="' . HUB_relAdminEscape($resolved['url']) . '">View</a>';
                }
                if (empty($resolved['exists'])) {
                    $content .= ' <span class="hub-rel-muted">(not resolved)</span>';
                }
                $content .= '</td><td>' . (!empty($relation['is_enabled']) ? 'Enabled' : 'Disabled') . '</td>';
                $content .= '<td class="hub-rel-actions"><form method="post" action="relations.php">'
                    . HUB_relAdminTokenField()
                    . '<input type="hidden" name="hub_action" value="delete_relation">'
                    . '<input type="hidden" name="relation_id" value="' . (int) $relation['id'] . '">'
                    . '<button type="submit" class="uk-button">Delete</button></form></td></tr>';
            }
            $content .= '</tbody></table>';
        }

        $content .= '<h3>Add relation</h3>';
        $content .= '<form method="post" action="relations.php">' . HUB_relAdminTokenField();
        $content .= '<input type="hidden" name="hub_action" value="save_relation">';
        $content .= '<input type="hidden" name="relation_id" value="0">';
        $content .= '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">';
        $content .= '<div class="hub-rel-grid">';
        $content .= '<label>Item type<input type="text" name="item_type" maxlength="64" placeholder="article, maps, videos..." required></label>';
        $content .= '<label>Item id<input type="text" name="item_id" maxlength="128" required></label>';
        $content .= '<label>Order<input type="number" name="position" min="0" max="65535" value="' . (count($relations) * 10 + 10) . '"></label>';
        $content .= '<label><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label>';
        $content .= '<div><button type="submit" class="uk-button uk-button-primary">Add relation</button></div>';
        $content .= '</div></form>';

        $content .= '<hr><form method="post" action="relations.php" onsubmit="return confirm(\'Delete this pillar and all its relations?\');">'
            . HUB_relAdminTokenField()
            . '<input type="hidden" name="hub_action" value="delete_pillar">'
            . '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">'
            . '<button type="submit" class="uk-button">Delete pillar</button></form>';

        $content .= '</div>';
    }
}

$display = COM_startBlock('Hub 0.3.0') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
