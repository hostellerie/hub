<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'system/lib-admin.php';
require_once $_CONF['path'] . 'plugins/hub/lib-admin-ui.php';

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

function HUB_relAdminTopicContext($pageId)
{
    $context = function_exists('HUB_staticPageTopicContext')
        ? HUB_staticPageTopicContext($pageId)
        : array('specific_topics' => array());

    $topics = isset($context['specific_topics']) && is_array($context['specific_topics'])
        ? $context['specific_topics']
        : array();

    $ids = array();
    $labels = array();
    foreach ($topics as $topic) {
        $tid = isset($topic['tid']) ? (string) $topic['tid'] : '';
        if ($tid === '') {
            continue;
        }

        $ids[] = $tid;
        $labels[$tid] = isset($topic['topic']) && (string) $topic['topic'] !== ''
            ? (string) $topic['topic']
            : $tid;
    }

    return array(
        'ids' => array_values(array_unique($ids)),
        'labels' => $labels,
    );
}

function HUB_relAdminSuggestedPillars($staticPages, $pillars, $limit = 8)
{
    $existing = array();
    foreach ($pillars as $pillar) {
        if (isset($pillar['source_type'], $pillar['source_id'])
            && (string) $pillar['source_type'] === 'staticpages'
        ) {
            $existing[(string) $pillar['source_id']] = true;
        }
    }

    $suggestions = array();
    foreach ($staticPages as $page) {
        $pageId = isset($page['sp_id']) ? (string) $page['sp_id'] : '';
        if ($pageId === '' || isset($existing[$pageId])) {
            continue;
        }

        $topicContext = HUB_relAdminTopicContext($pageId);
        if (empty($topicContext['ids'])) {
            continue;
        }

        $articles = function_exists('HUB_linkAuditArticlesByTopics')
            ? HUB_linkAuditArticlesByTopics($topicContext['ids'])
            : array();

        if (empty($articles)) {
            continue;
        }

        $suggestions[] = array(
            'id' => $pageId,
            'title' => isset($page['sp_title']) && (string) $page['sp_title'] !== ''
                ? (string) $page['sp_title']
                : $pageId,
            'topics' => array_values($topicContext['labels']),
            'article_count' => count($articles),
        );
    }

    usort($suggestions, function ($left, $right) {
        if ($left['article_count'] === $right['article_count']) {
            return strcasecmp($left['title'], $right['title']);
        }
        return $left['article_count'] > $right['article_count'] ? -1 : 1;
    });

    return array_slice($suggestions, 0, max(1, (int) $limit));
}

function HUB_relAdminSuggestedArticles($pillar, $relations, $limit = 10)
{
    if (!isset($pillar['source_type'], $pillar['source_id'])
        || (string) $pillar['source_type'] !== 'staticpages'
    ) {
        return array();
    }

    $topicContext = HUB_relAdminTopicContext($pillar['source_id']);
    if (empty($topicContext['ids']) || !function_exists('HUB_linkAuditArticlesByTopics')) {
        return array();
    }

    $existing = array();
    foreach ($relations as $relation) {
        if (isset($relation['item_type'], $relation['item_id'])
            && (string) $relation['item_type'] === 'article'
        ) {
            $existing[(string) $relation['item_id']] = true;
        }
    }

    $suggestions = array();
    foreach (HUB_linkAuditArticlesByTopics($topicContext['ids']) as $article) {
        $sid = isset($article['sid']) ? (string) $article['sid'] : '';
        if ($sid === '' || isset($existing[$sid])) {
            continue;
        }

        $suggestions[] = $article;
        if (count($suggestions) >= max(1, (int) $limit)) {
            break;
        }
    }

    return $suggestions;
}

if (isset($_GET['hub_ajax']) && $_GET['hub_ajax'] === 'items') {
    $type = isset($_GET['type']) ? HUB_normalizeObjectType($_GET['type']) : '';
    $payload = HUB_relationObjectOptions($type, 100);

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode($payload);
    exit;
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
            $enabled = !empty($_POST['is_enabled']) ? 1 : 0;

            $saved = HUB_savePillar($pillarId, 'staticpages', $sourceId, $enabled);
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
            if ($itemType === '__custom__') {
                $itemType = isset($_POST['item_type_custom']) ? (string) $_POST['item_type_custom'] : '';
            }
            $itemIdChoice = isset($_POST['item_id_choice']) ? (string) $_POST['item_id_choice'] : '';
            $itemIdManual = isset($_POST['item_id_manual']) ? (string) $_POST['item_id_manual'] : '';
            $itemIdDirect = isset($_POST['item_id']) ? (string) $_POST['item_id'] : '';
            if ($itemIdChoice !== '' && $itemIdChoice !== '__manual__') {
                $itemId = $itemIdChoice;
            } elseif ($itemIdManual !== '') {
                $itemId = $itemIdManual;
            } else {
                $itemId = $itemIdDirect;
            }
            $position = isset($_POST['position']) ? (int) $_POST['position'] : 0;
            $enabled = !empty($_POST['is_enabled']) ? 1 : 0;
            $relationRole = isset($_POST['relation_role']) ? (string) $_POST['relation_role'] : 'related';

            $saved = HUB_saveRelation($relationId, $pillarId, $itemType, $itemId, $position, $enabled, 0, $relationRole);
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
$objectTypes = HUB_relationObjectTypes();
$relationRoles = HUB_relationRoles();
$pillars = HUB_getPillars(true);
$showSuggestions = isset($_GET['suggest']) && $_GET['suggest'] === '1';
$pillarSuggestions = $showSuggestions
    ? HUB_relAdminSuggestedPillars($staticPages, $pillars, 8)
    : array();

$content = '<style>'
    . '.hub-rel-nav{margin:0 0 1rem}.hub-rel-nav a{margin-right:1rem}'
    . '.hub-rel-message{padding:.7rem 1rem;margin:0 0 1rem;background:#edf7ed;border-left:4px solid #2e7d32}'
    . '.hub-rel-error{background:#fff1f0;border-left-color:#b00020}'
    . '.hub-rel-card{border:1px solid #d5d8dc;padding:1rem;margin:0 0 1rem;border-radius:4px}'
    . '.hub-rel-pillar{padding:0;overflow:hidden}.hub-rel-pillar>summary{cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;background:#f8f9fb}'
    . '.hub-rel-pillar>summary::-webkit-details-marker{display:none}.hub-rel-pillar>summary:before{content:"▸";font-size:1.1rem;flex:0 0 auto}.hub-rel-pillar[open]>summary:before{content:"▾"}'
    . '.hub-rel-pillar-summary-main{display:flex;align-items:center;gap:.65rem;min-width:0;flex:1}.hub-rel-pillar-summary-title{font-weight:700;font-size:1.05rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
    . '.hub-rel-pillar-summary-meta{display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;font-size:.9em;opacity:.78}.hub-rel-pillar-body{padding:1rem;border-top:1px solid #e3e6eb}'

    . '.hub-rel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;align-items:end}'
    . '.hub-rel-grid label{display:block;font-weight:600}.hub-rel-grid input:not([type=checkbox]),.hub-rel-grid select{box-sizing:border-box;width:100%;padding:.45rem}'
    . '.hub-rel-grid input[type=checkbox]{width:auto;margin:0}'
    . '.hub-rel-check{display:flex!important;align-items:center;gap:.45rem;min-height:2.45rem;font-weight:600!important}'
    . '.hub-rel-check input[type=checkbox]{flex:0 0 auto}.hub-rel-check span{display:inline}'
    . '.hub-rel-form-note{margin:.45rem 0 0;font-size:.9em;opacity:.72}'
    . '.hub-rel-suggest{background:#f7f9fc}.hub-rel-suggest-row{display:grid;grid-template-columns:minmax(220px,2fr) minmax(220px,3fr) auto;gap:.75rem;align-items:center;padding:.65rem 0;border-bottom:1px solid #e3e6eb}'
    . '.hub-rel-suggest-row:last-child{border-bottom:0}.hub-rel-reason{font-size:.9em;opacity:.75}'
    . '@media(max-width:760px){.hub-rel-suggest-row{grid-template-columns:1fr}.hub-rel-row-edit{grid-template-columns:1fr}.hub-rel-table{table-layout:auto}.hub-rel-col-order,.hub-rel-col-enabled,.hub-rel-col-actions{width:auto}}'
    . '.hub-rel-table{width:100%;border-collapse:collapse;margin-top:1rem;table-layout:fixed}.hub-rel-table th,.hub-rel-table td{padding:.5rem;border-bottom:1px solid #ddd;text-align:left;vertical-align:middle}'
    . '.hub-rel-col-order{width:120px}.hub-rel-col-enabled{width:150px}.hub-rel-col-actions{width:240px}'
    . '.hub-rel-relation-main{line-height:1.45}.hub-rel-relation-main code{display:inline-block;margin-bottom:.15rem}'

    . '.hub-rel-row-edit{grid-template-columns:120px minmax(260px,1fr) 150px 240px}'
    . '.hub-rel-actions{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}.hub-rel-actions form{display:inline}.hub-rel-muted{opacity:.7;font-size:.92em}'

    . '.hub-rel-integrity{margin:.55rem 0;padding:.55rem .7rem;border-left:4px solid #d7a900;background:#fffbea}.hub-rel-public-rendering{margin:1.25rem 0;padding:.8rem 1rem}'
    . '.hub-rel-ok{display:inline-block;padding:.12rem .45rem;border-radius:10px;background:#edf7ed;font-size:.86em}'
    . '.hub-rel-unresolved{display:inline-block;padding:.12rem .45rem;border-radius:10px;background:#fff1f0;font-size:.86em}'
    . '</style>';

$content .= HUB_adminNavigation('relations');

$content .= '<h1>Pillars &amp; manual relations</h1>';
$content .= '<p>Hub 0.5.0 stores stable <code>type + id</code> identities plus a Hub-owned structural role for each approved relation. Titles and URLs are resolved dynamically from the owning Geeklog provider.</p>';
$content .= '<p class="hub-rel-muted">Known relation types are discovered from active Geeklog Item Info providers. When a provider exposes the shared collection contract, Hub can also list its selectable objects without querying plugin-private tables. Choose <em>Custom / other…</em> only when a provider type is not listed.</p>';
$content .= $message;

if ($showSuggestions) {
    $content .= '<p><a class="uk-button" href="relations.php">Hide suggestions</a></p>';
} else {
    $content .= '<p><a class="uk-button" href="relations.php?suggest=1">Find suggestions</a> '
        . '<span class="hub-rel-muted">Uses specific Geeklog topics as an explainable editorial signal; nothing is added automatically.</span></p>';
}

if ($showSuggestions) {
    $content .= '<div class="hub-rel-card hub-rel-suggest"><h2>Suggested pillars</h2>';
    if (empty($pillarSuggestions)) {
        $content .= '<p class="hub-rel-muted">No additional Static Page currently has both a specific topic context and matching published articles.</p>';
    } else {
        foreach ($pillarSuggestions as $suggestion) {
            $reason = count($suggestion['topics']) . ' specific topic(s) · '
                . (int) $suggestion['article_count'] . ' matching published article(s)';
            $content .= '<div class="hub-rel-suggest-row"><div><strong>'
                . HUB_relAdminEscape($suggestion['title']) . '</strong><br><code>'
                . HUB_relAdminEscape($suggestion['id']) . '</code></div><div class="hub-rel-reason">'
                . HUB_relAdminEscape(implode(', ', $suggestion['topics'])) . '<br>'
                . HUB_relAdminEscape($reason) . '</div><form method="post" action="relations.php">'
                . HUB_relAdminTokenField()
                . '<input type="hidden" name="hub_action" value="save_pillar">'
                . '<input type="hidden" name="pillar_id" value="0">'
                . '<input type="hidden" name="source_id" value="' . HUB_relAdminEscape($suggestion['id']) . '">'

                . '<input type="hidden" name="is_enabled" value="1">'
                . '<button type="submit" class="uk-button">Add as pillar</button></form></div>';
        }
    }
    $content .= '</div>';
}

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
$content .= '<label class="hub-rel-check"><input type="checkbox" name="is_enabled" value="1" checked><span>Enabled</span></label>';
$content .= '<div><button type="submit" class="uk-button uk-button-primary">Add pillar</button></div></div></form></div>';

if (empty($pillars)) {
    $content .= '<p>No pillar has been created yet.</p>';
} else {
    foreach ($pillars as $pillar) {
        $resolvedPillar = HUB_resolveObject($pillar['source_type'], $pillar['source_id']);
        $pillarTitle = (string) $resolvedPillar['title'];
        if ($pillarTitle === '') {
            $pillarTitle = (string) $pillar['source_id'];
        }
        $relations = HUB_getRelations($pillar['id'], true);

        $content .= '<details class="hub-rel-card hub-rel-pillar">';
        $content .= '<summary>'
            . '<span class="hub-rel-pillar-summary-main"><span class="hub-rel-pillar-summary-title">'
            . HUB_relAdminEscape($pillarTitle)
            . '</span></span>'
            . '<span class="hub-rel-pillar-summary-meta"><code>'
            . HUB_relAdminEscape($pillar['source_type'] . ':' . $pillar['source_id'])
            . '</code><span>'
            . (!empty($pillar['is_enabled']) ? 'enabled' : 'disabled')
            . '</span><span>'
            . count($relations) . ' relation' . (count($relations) === 1 ? '' : 's')
            . '</span></span></summary>';
        $content .= '<div class="hub-rel-pillar-body">';
        if (!empty($resolvedPillar['url'])) {
            $content .= '<p class="hub-rel-muted"><a href="' . HUB_relAdminEscape($resolvedPillar['url']) . '">View source</a></p>';
        }
        if (empty($resolvedPillar['exists'])) {
            $content .= '<div class="hub-rel-integrity"><strong>Pillar source unresolved.</strong> '
                . HUB_relAdminEscape($resolvedPillar['diagnostic']) . '</div>';
        }



        $content .= '<form method="post" action="relations.php">' . HUB_relAdminTokenField();
        $content .= '<input type="hidden" name="hub_action" value="save_pillar">';
        $content .= '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">';
        $content .= '<input type="hidden" name="source_id" value="' . HUB_relAdminEscape($pillar['source_id']) . '">';
        $content .= '<div class="hub-rel-grid">';
        $content .= '<label class="hub-rel-check"><input type="checkbox" name="is_enabled" value="1"' . (!empty($pillar['is_enabled']) ? ' checked' : '') . '><span>Enabled</span></label>';
        $content .= '<div><button type="submit" class="uk-button">Update pillar</button></div></div></form>';

        $content .= '<h3>Relations</h3>';
        if (empty($relations)) {
            $content .= '<p class="hub-rel-muted">No manual relation yet.</p>';
        } else {
            $content .= '<table class="hub-rel-table"><thead><tr><th class="hub-rel-col-order">Order</th><th>Relation</th><th class="hub-rel-col-enabled">Enabled</th><th class="hub-rel-col-actions">Actions</th></tr></thead><tbody>';
            foreach ($relations as $relation) {
                $resolved = HUB_resolveObject($relation['item_type'], $relation['item_id']);
                $relationIdentity = HUB_relAdminEscape($relation['item_type'] . ':' . $relation['item_id']);
                $content .= '<tr><td colspan="4"><form method="post" action="relations.php" class="hub-rel-grid hub-rel-row-edit">'
                    . HUB_relAdminTokenField()
                    . '<input type="hidden" name="relation_id" value="' . (int) $relation['id'] . '">'
                    . '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">'
                    . '<input type="hidden" name="item_type" value="' . HUB_relAdminEscape($relation['item_type']) . '">'
                    . '<input type="hidden" name="item_id" value="' . HUB_relAdminEscape($relation['item_id']) . '">'
                    . '<label>Order<input type="number" name="position" min="0" max="65535" value="' . (int) $relation['position'] . '"></label>'
                    . '<label>Role<select name="relation_role">';
                foreach ($relationRoles as $roleValue => $roleLabel) {
                    $selected = HUB_normalizeRelationRole(isset($relation['relation_role']) ? $relation['relation_role'] : 'related') === $roleValue
                        ? ' selected' : '';
                    $content .= '<option value="' . HUB_relAdminEscape($roleValue) . '"' . $selected . '>'
                        . HUB_relAdminEscape($roleLabel) . '</option>';
                }
                $content .= '</select></label>'
                    . '<div class="hub-rel-relation-main"><strong><code>' . $relationIdentity . '</code></strong><br>'
                    . HUB_relAdminEscape($resolved['title'])
                    . (!empty($resolved['url']) ? ' · <a href="' . HUB_relAdminEscape($resolved['url']) . '">View</a>' : '')
                    . '<br>'
                    . (!empty($resolved['exists'])
                        ? '<span class="hub-rel-ok">Resolved</span>'
                        : '<span class="hub-rel-unresolved">Unresolved</span> <span class="hub-rel-muted">' . HUB_relAdminEscape($resolved['diagnostic']) . '</span>')
                    . '<br><span class="hub-rel-muted">Backlink: '
                    . HUB_relAdminEscape(HUB_backlinkIntegrationStatus($relation['item_type'])['label'])
                    . '</span>'
                    . '</div>'
                    . '<label class="hub-rel-check"><input type="checkbox" name="is_enabled" value="1"' . (!empty($relation['is_enabled']) ? ' checked' : '') . '><span>Enabled</span></label>'
                    . '<div class="hub-rel-actions">'
                    . '<button type="submit" name="hub_action" value="save_relation" class="uk-button">Update</button>'
                    . '<button type="submit" name="hub_action" value="delete_relation" class="uk-button" formnovalidate '
                    . 'onclick="return confirm(\'Delete relation ' . $relationIdentity . '?\');">Delete</button>'
                    . '</div></form></td></tr>';
            }
            $content .= '</tbody></table>';
        }

        $publicDiagCurrent = HUB_pillarRenderDiagnostics($pillar['source_type'], $pillar['source_id'], 0);
        $publicDiagAnon = HUB_pillarRenderDiagnostics($pillar['source_type'], $pillar['source_id'], 1);
        $content .= '<div class="hub-rel-integrity hub-rel-public-rendering"><strong>Public rendering:</strong> current user '
            . (int) $publicDiagCurrent['renderable_count'] . ' / ' . (int) $publicDiagCurrent['relation_count']
            . ' · anonymous/SEO '
            . (int) $publicDiagAnon['renderable_count'] . ' / ' . (int) $publicDiagAnon['relation_count']
            . ' enabled relation(s) renderable.';
        if (!empty($publicDiagCurrent['relations'])) {
            $content .= '<ul style="margin:.4rem 0 0 1.2rem">';
            foreach ($publicDiagCurrent['relations'] as $index => $diagRelation) {
                $anonRelation = isset($publicDiagAnon['relations'][$index]) ? $publicDiagAnon['relations'][$index] : array();
                $content .= '<li><code>'
                    . HUB_relAdminEscape($diagRelation['type'] . ':' . $diagRelation['id'])
                    . '</code> — current: '
                    . (!empty($diagRelation['renderable'])
                        ? 'renderable'
                        : 'skipped: ' . HUB_relAdminEscape($diagRelation['diagnostic']))
                    . ' · anonymous/SEO: '
                    . (!empty($anonRelation['renderable'])
                        ? 'renderable'
                        : 'skipped: ' . HUB_relAdminEscape(isset($anonRelation['diagnostic']) ? $anonRelation['diagnostic'] : 'not resolved'));
                if (!empty($diagRelation['url'])) {
                    $content .= ' · <a href="' . HUB_relAdminEscape($diagRelation['url']) . '">View</a>';
                }
                $content .= '</li>';
            }
            $content .= '</ul>';
        }
        $content .= '</div>';

        if ($showSuggestions) {
            $articleSuggestions = HUB_relAdminSuggestedArticles($pillar, $relations, 10);
            $content .= '<div class="hub-rel-suggest"><h3>Suggested relations</h3>';
            if (empty($articleSuggestions)) {
                $content .= '<p class="hub-rel-muted">No new article relation is suggested from this pillar\'s specific topics.</p>';
            } else {
                $suggestionPosition = count($relations) * 10 + 10;
                foreach ($articleSuggestions as $articleSuggestion) {
                    $sid = isset($articleSuggestion['sid']) ? (string) $articleSuggestion['sid'] : '';
                    $title = isset($articleSuggestion['title']) && (string) $articleSuggestion['title'] !== ''
                        ? (string) $articleSuggestion['title']
                        : $sid;
                    $topics = isset($articleSuggestion['hub_topics']) && is_array($articleSuggestion['hub_topics'])
                        ? array_values($articleSuggestion['hub_topics'])
                        : array();

                    $content .= '<div class="hub-rel-suggest-row"><div><strong>'
                        . HUB_relAdminEscape($title) . '</strong><br><code>article:'
                        . HUB_relAdminEscape($sid) . '</code></div><div class="hub-rel-reason">Shared specific topic'
                        . (count($topics) === 1 ? ': ' : 's: ')
                        . HUB_relAdminEscape(implode(', ', $topics))
                        . '</div><form method="post" action="relations.php">'
                        . HUB_relAdminTokenField()
                        . '<input type="hidden" name="hub_action" value="save_relation">'
                        . '<input type="hidden" name="relation_id" value="0">'
                        . '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">'
                        . '<input type="hidden" name="item_type" value="article">'
                        . '<input type="hidden" name="item_id_manual" value="' . HUB_relAdminEscape($sid) . '">'
                        . '<input type="hidden" name="position" value="' . (int) $suggestionPosition . '">'
                        . '<input type="hidden" name="relation_role" value="satellite">'
                        . '<input type="hidden" name="is_enabled" value="1">'
                        . '<button type="submit" class="uk-button">Add relation</button></form></div>';
                    $suggestionPosition += 10;
                }
            }
            $content .= '</div>';
        }

        $content .= '<h3>Add relation</h3>';
        $content .= '<form method="post" action="relations.php">' . HUB_relAdminTokenField();
        $content .= '<input type="hidden" name="hub_action" value="save_relation">';
        $content .= '<input type="hidden" name="relation_id" value="0">';
        $content .= '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">';
        $content .= '<div class="hub-rel-grid">';
        $hubTypeSelectId = 'hub-item-type-' . (int) $pillar['id'];
        $hubTypeCustomId = 'hub-item-type-custom-' . (int) $pillar['id'];
        $hubItemSelectId = 'hub-item-id-' . (int) $pillar['id'];
        $hubItemManualId = 'hub-item-id-manual-' . (int) $pillar['id'];
        $hubItemNoteId = 'hub-item-note-' . (int) $pillar['id'];
        $content .= '<label>Item type<select class="hub-rel-type-select" id="' . $hubTypeSelectId . '" name="item_type"'
            . ' data-custom-id="' . $hubTypeCustomId . '" data-item-select-id="' . $hubItemSelectId . '"'
            . ' data-item-manual-id="' . $hubItemManualId . '" data-note-id="' . $hubItemNoteId . '" required>';
        $content .= '<option value="">Select a type</option>';
        foreach ($objectTypes as $objectType) {
            $content .= '<option value="' . HUB_relAdminEscape($objectType) . '">' . HUB_relAdminEscape($objectType) . '</option>';
        }
        $content .= '<option value="__custom__">Custom / other…</option></select></label>';
        $content .= '<label id="' . $hubTypeCustomId . '-wrap" style="display:none">Custom type<input type="text" id="' . $hubTypeCustomId . '" name="item_type_custom" maxlength="64" autocomplete="off"></label>';
        $content .= '<label id="' . $hubItemSelectId . '-wrap">Item<select class="hub-rel-item-select" id="' . $hubItemSelectId . '" name="item_id_choice" disabled><option value="">Select a type first</option></select></label>';
        $content .= '<label id="' . $hubItemManualId . '-wrap" style="display:none">Item id<input type="text" id="' . $hubItemManualId . '" name="item_id_manual" maxlength="128" autocomplete="off"></label>';
        $content .= '<label>Order<input type="number" name="position" min="0" max="65535" value="' . (count($relations) * 10 + 10) . '"></label>';
        $content .= '<label>Role<select name="relation_role">';
        foreach ($relationRoles as $roleValue => $roleLabel) {
            $selected = $roleValue === 'related' ? ' selected' : '';
            $content .= '<option value="' . HUB_relAdminEscape($roleValue) . '"' . $selected . '>'
                . HUB_relAdminEscape($roleLabel) . '</option>';
        }
        $content .= '</select></label>';
        $content .= '<label class="hub-rel-check"><input type="checkbox" name="is_enabled" value="1" checked><span>Enabled</span></label>';
        $content .= '<div><button type="submit" class="uk-button uk-button-primary">Add relation</button></div>';
        $content .= '</div><div class="hub-rel-form-note" id="' . $hubItemNoteId . '"></div></form>';

        $content .= '<hr><form method="post" action="relations.php" onsubmit="return confirm(\'Delete this pillar and all its relations?\');">'
            . HUB_relAdminTokenField()
            . '<input type="hidden" name="hub_action" value="delete_pillar">'
            . '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">'
            . '<button type="submit" class="uk-button">Delete pillar</button></form>';

        $content .= '</div></details>';
    }
}

$content .= '<script>(function(){'
    . 'var pillars=document.querySelectorAll("details.hub-rel-pillar");for(var p=0;p<pillars.length;p++){pillars[p].open=false;pillars[p].removeAttribute("open");}'
    . 'function byId(id){return document.getElementById(id);}'
    . 'function setManual(input,wrap,on){if(wrap){wrap.style.display=on?"block":"none";}if(input){input.required=on;if(!on){input.value="";}}}'
    . 'function resetItems(select,note,text){select.innerHTML="";var option=document.createElement("option");option.value="";option.textContent=text||"Select an item";select.appendChild(option);select.disabled=true;if(note){note.textContent="";}}'
    . 'var types=document.querySelectorAll(".hub-rel-type-select");'
    . 'for(var i=0;i<types.length;i++){(function(typeSelect){'
    . 'var customId=typeSelect.getAttribute("data-custom-id"),customInput=byId(customId),customWrap=byId(customId+"-wrap");'
    . 'var itemId=typeSelect.getAttribute("data-item-select-id"),itemSelect=byId(itemId),itemWrap=byId(itemId+"-wrap");'
    . 'var manualId=typeSelect.getAttribute("data-item-manual-id"),manualInput=byId(manualId),manualWrap=byId(manualId+"-wrap");'
    . 'var note=byId(typeSelect.getAttribute("data-note-id"));'
    . 'function showManual(message){if(itemWrap){itemWrap.style.display="none";}setManual(manualInput,manualWrap,true);if(note){note.textContent=message||"";}}'
    . 'function loadItems(){var type=typeSelect.value,custom=type==="__custom__";'
    . 'if(customWrap){customWrap.style.display=custom?"block":"none";}if(customInput){customInput.required=custom;if(!custom){customInput.value="";}}'
    . 'resetItems(itemSelect,note,"Loading…");setManual(manualInput,manualWrap,false);if(itemWrap){itemWrap.style.display=custom?"none":"block";}'
    . 'if(!type){resetItems(itemSelect,note,"Select a type first");return;}'
    . 'if(custom){showManual("Enter the provider type and item ID manually.");return;}'
    . 'fetch("relations.php?hub_ajax=items&type="+encodeURIComponent(type),{credentials:"same-origin"})'
    . '.then(function(response){if(!response.ok){throw new Error("HTTP "+response.status);}return response.json();})'
    . '.then(function(data){itemSelect.innerHTML="";'
    . 'if(!data||!data.supported){showManual(data&&data.message?data.message:"Collection unavailable; enter the item ID manually.");return;}'
    . 'if(itemWrap){itemWrap.style.display="block";}var first=document.createElement("option");first.value="";first.textContent=data.items&&data.items.length?"Select an item":"No selectable item";itemSelect.appendChild(first);'
    . 'if(data.items){for(var j=0;j<data.items.length;j++){var row=data.items[j],opt=document.createElement("option");opt.value=row.id;opt.textContent=(row.title||row.id)+" ["+row.id+"]";itemSelect.appendChild(opt);}}'
    . 'var manual=document.createElement("option");manual.value="__manual__";manual.textContent="Enter ID manually…";itemSelect.appendChild(manual);itemSelect.disabled=false;'
    . 'if(note){note.textContent=data.message||((data.items&&data.items.length)?data.items.length+" selectable item(s).":"");}'
    . '})'
    . '.catch(function(){showManual("Unable to load the provider collection; enter the item ID manually.");});'
    . '}'
    . 'itemSelect.addEventListener("change",function(){var manual=itemSelect.value==="__manual__";setManual(manualInput,manualWrap,manual);if(note&&manual){note.textContent="Manual fallback for providers without a collection or for an ID not listed above.";}if(manualInput&&manual){manualInput.focus();}});'
    . 'typeSelect.addEventListener("change",loadItems);loadItems();'
    . '})(types[i]);}'
    . '})();</script>';

$display = COM_startBlock('Hub 0.4.0') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
