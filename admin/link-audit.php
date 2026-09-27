<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'system/lib-admin.php';
require_once $_CONF['path'] . 'plugins/hub/lib-admin-ui.php';

/*
 * This administration page can be called directly. Do not rely on Geeklog
 * having loaded the Hub functions.inc file before reaching this script.
 */
$hubPluginPath = rtrim($_CONF['path'], '/\\') . '/plugins/hub/';
if (!function_exists('HUB_linkAuditStaticPages')) {
    require_once $hubPluginPath . 'lib-link-audit.php';
}
if (!function_exists('HUB_topicUrl')) {
    require_once $hubPluginPath . 'lib-staticpages.php';
}
if (!function_exists('HUB_findPillar')) {
    require_once $hubPluginPath . 'lib-relations.php';
}

if (!SEC_hasRights('hub.admin')) {
    COM_accessLog('User ' . (int) $_USER['uid'] . ' attempted to access the Hub link audit without permission.');
    $display = COM_startBlock('Access denied') . 'You do not have sufficient rights to access this page.' . COM_endBlock();
    COM_output(COM_createHTMLDocument($display));
    exit;
}

function HUB_linkAuditAdminEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function HUB_linkAuditTokenField()
{
    return '<input type="hidden" name="' . CSRF_TOKEN . '" value="'
        . HUB_linkAuditAdminEscape(SEC_createToken()) . '">';
}

function HUB_linkAuditNextRelationPosition($pillarId)
{
    $max = 0;
    foreach (HUB_getRelations($pillarId, true) as $relation) {
        $position = isset($relation['position']) ? (int) $relation['position'] : 0;
        if ($position > $max) {
            $max = $position;
        }
    }

    return $max + 10;
}

$hubSelectedPageId = isset($_GET['page_id']) ? COM_applyFilter($_GET['page_id']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['page_id'])) {
    $hubSelectedPageId = COM_applyFilter($_POST['page_id']);
}
$hubRunAudit = ($hubSelectedPageId !== '');

$hubSort = isset($_GET['sort']) ? COM_applyFilter($_GET['sort']) : 'views';
$hubDirection = isset($_GET['dir']) ? strtolower(COM_applyFilter($_GET['dir'])) : 'desc';
if (!in_array($hubSort, array('topics', 'views', 'comments', 'date', 'title'), true)) {
    $hubSort = 'views';
}
if (!in_array($hubDirection, array('asc', 'desc'), true)) {
    $hubDirection = 'desc';
}

$hubMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SEC_checkToken()) {
        $hubMessage = '<p class="hub-warning">Invalid security token.</p>';
    } else {
        $hubAction = isset($_POST['hub_action']) ? (string) $_POST['hub_action'] : '';
        if ($hubAction === 'add_article_relation') {
            $hubSid = isset($_POST['sid']) ? HUB_normalizeObjectId($_POST['sid']) : '';
            $hubPillar = HUB_findPillar('staticpages', $hubSelectedPageId);

            if (!$hubPillar) {
                $hubMessage = '<p class="hub-warning">This Static Page is not registered as a Hub pillar yet. Add it as a pillar before creating relations from the link audit.</p>';
            } elseif ($hubSid === '') {
                $hubMessage = '<p class="hub-warning">Invalid article ID.</p>';
            } else {
                $hubPosition = HUB_linkAuditNextRelationPosition((int) $hubPillar['id']);
                $hubSaved = HUB_saveRelation(
                    0,
                    (int) $hubPillar['id'],
                    'article',
                    $hubSid,
                    $hubPosition,
                    1
                );

                $hubMessage = $hubSaved
                    ? '<p class="hub-summary">Article relation added to this pillar.</p>'
                    : '<p class="hub-warning">Unable to add the relation. It may already exist.</p>';
            }
        }
    }
}

$hubStaticPageRows = HUB_linkAuditStaticPages();

$content = '<style>';
$content .= '.hub-nav{margin:0 0 18px}.hub-nav a{margin-right:14px}.hub-audit-form{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:12px;align-items:end;padding:16px;background:#f6f7f9;border:1px solid #d9dde5;border-radius:5px}.hub-field label{display:block;font-weight:bold;margin-bottom:5px}.hub-field select{width:100%;min-height:36px}.hub-submit{min-height:36px;padding:6px 14px}.hub-summary{margin:18px 0;padding:12px 14px;background:#f3f7f4;border-left:4px solid #6c9b74}.hub-url{overflow-wrap:anywhere}.hub-topic-note{margin:10px 0 0}.hub-topic-note a{overflow-wrap:anywhere}.hub-empty-result{padding:14px;background:#f3f7f4;border-radius:4px}.hub-sortbar{display:flex;gap:.65rem;align-items:end;flex-wrap:wrap;margin:14px 0}.hub-sortbar label{font-weight:bold}.hub-sortbar select{min-height:34px}.hub-metric{white-space:nowrap;text-align:right}.hub-count{font-weight:bold}.hub-warning{padding:10px 12px;background:#fffbea;border-left:4px solid #d7a900;margin:12px 0}@media(max-width:760px){.hub-audit-form{grid-template-columns:1fr}}';
$content .= '</style>';
$content .= HUB_adminNavigation('link-audit');
$content .= $hubMessage;
$content .= '<h2>Articles without a link to a static page</h2>';
$content .= '<p>Select a Static Page. Hub uses only specific Geeklog topics as editorial context. The native <strong>All</strong> and <strong>Home page only</strong> assignments are treated as placement options and are never interpreted as editorial topics.</p>';

if (empty($hubStaticPageRows)) {
    $content .= '<p class="hub-warning">No static page could be loaded. Check that the Static Pages plugin is installed and enabled.</p>';
}

if (!empty($hubStaticPageRows)) {
    $content .= '<form class="hub-audit-form" method="get" action="link-audit.php">';
    $content .= '<div class="hub-field"><label for="page_id">Static page</label><select id="page_id" name="page_id" required><option value="">Select a static page</option>';
    foreach ($hubStaticPageRows as $hubStaticPageRow) {
        $hubSpId = isset($hubStaticPageRow['sp_id']) ? $hubStaticPageRow['sp_id'] : '';
        $hubSpTitle = isset($hubStaticPageRow['sp_title']) ? $hubStaticPageRow['sp_title'] : $hubSpId;
        $hubSelected = ((string) $hubSpId === (string) $hubSelectedPageId) ? ' selected' : '';
        $content .= '<option value="' . HUB_linkAuditAdminEscape($hubSpId) . '"' . $hubSelected . '>' . HUB_linkAuditAdminEscape($hubSpTitle) . ' (' . HUB_linkAuditAdminEscape($hubSpId) . ')</option>';
    }
    $content .= '</select></div>';
    $content .= '<input type="hidden" name="sort" value="' . HUB_linkAuditAdminEscape($hubSort) . '">';
    $content .= '<input type="hidden" name="dir" value="' . HUB_linkAuditAdminEscape($hubDirection) . '">';
    $content .= '<button class="hub-submit" type="submit">Run audit</button>';
    $content .= '</form>';
}

if ($hubRunAudit && !empty($hubStaticPageRows)) {
    $hubTopicContext = HUB_staticPageTopicContext($hubSelectedPageId);
    $hubAssignedTopics = isset($hubTopicContext['specific_topics']) && is_array($hubTopicContext['specific_topics'])
        ? $hubTopicContext['specific_topics']
        : array();
    $hubTopicIds = array();
    foreach ($hubAssignedTopics as $hubAssignedTopic) {
        if (isset($hubAssignedTopic['tid']) && $hubAssignedTopic['tid'] !== '') {
            $hubTopicIds[] = (string) $hubAssignedTopic['tid'];
        }
    }

    $hubAllArticles = HUB_linkAuditArticlesByTopics($hubTopicIds);
    $hubMissingArticles = HUB_linkAuditMissingArticlesForTopics($hubTopicIds, $hubSelectedPageId);
    $hubMissingArticles = HUB_linkAuditSortArticles($hubMissingArticles, $hubSort, $hubDirection);
    $hubPillar = HUB_findPillar('staticpages', $hubSelectedPageId);
    $hubExistingArticleRelations = array();
    if ($hubPillar) {
        foreach (HUB_getRelations((int) $hubPillar['id'], true) as $hubExistingRelation) {
            if (isset($hubExistingRelation['item_type'], $hubExistingRelation['item_id'])
                && (string) $hubExistingRelation['item_type'] === 'article'
            ) {
                $hubExistingArticleRelations[(string) $hubExistingRelation['item_id']] = true;
            }
        }
    }
    $hubTargetUrl = HUB_linkAuditStaticPageUrl($hubSelectedPageId);
    $hubEscapedTargetUrl = HUB_linkAuditAdminEscape($hubTargetUrl);

    $hubTopicLinks = array();
    foreach ($hubAssignedTopics as $hubAssignedTopic) {
        $hubTid = isset($hubAssignedTopic['tid']) ? (string) $hubAssignedTopic['tid'] : '';
        $hubTopicName = isset($hubAssignedTopic['topic']) && $hubAssignedTopic['topic'] !== ''
            ? (string) $hubAssignedTopic['topic']
            : $hubTid;
        if ($hubTid === '') {
            continue;
        }
        $hubTopicUrl = HUB_topicUrl($hubTid);
        $hubTopicLinks[] = '<a href="' . HUB_linkAuditAdminEscape($hubTopicUrl) . '" target="_blank" rel="noopener">'
            . HUB_linkAuditAdminEscape($hubTopicName) . '</a>';
    }

    $content .= '<div class="hub-summary"><strong>' . count($hubMissingArticles) . '</strong> possible link(s) to review out of <strong>' . count($hubAllArticles) . '</strong> published article(s) found in the Static Page\'s specific Geeklog topics.'
        . '<br><span class="hub-url">Target: <a href="' . $hubEscapedTargetUrl . '" target="_blank" rel="noopener">' . $hubEscapedTargetUrl . '</a></span>';

    if (!empty($hubTopicLinks)) {
        $content .= '<br><span>Specific Geeklog topics: ' . implode(' &middot; ', $hubTopicLinks) . '</span>';
    } else {
        if (!empty($hubTopicContext['placement_only'])) {
            $hubPlacementLabels = array();
            if (!empty($hubTopicContext['all'])) {
                $hubPlacementLabels[] = 'All';
            }
            if (!empty($hubTopicContext['homeonly'])) {
                $hubPlacementLabels[] = 'Home page only';
            }
            $content .= '<p class="hub-warning">This Static Page only uses Geeklog placement assignment(s): <strong>'
                . HUB_linkAuditAdminEscape(implode(', ', $hubPlacementLabels))
                . '</strong>. Hub does not interpret these placement options as editorial topics, so there are no topic-based article suggestions.</p>';
        } else {
            $content .= '<p class="hub-warning">This Static Page has no visible specific Geeklog topic. Hub therefore has no topic-based article suggestions for it.</p>';
        }
    }

    if (!empty($hubTopicLinks)) {
        $content .= '<p class="hub-topic-note">Hub displays links only for the specific Geeklog topics assigned to this Static Page. The native All/Home page only placement options are never rendered as editorial topic links.</p>';
    }
    $content .= '</div>';

    if (empty($hubMissingArticles)) {
        if (!empty($hubTopicIds)) {
            $content .= '<p class="hub-empty-result">No missing contextual links were found among published articles in the assigned topics.</p>';
        }
    } else {
        $content .= '<p>The articles below share one or more <strong>specific Geeklog topics</strong> with this Static Page but do not currently link back to it. This is a suggestion signal only; review each article before deciding whether a contextual link is editorially appropriate.</p>';

        if (!$hubPillar) {
            $content .= '<p class="hub-warning">This Static Page is not yet a Hub pillar. You can review candidates here, but <strong>Add relation</strong> becomes available only after the page is registered as a pillar.</p>';
        }

        $content .= '<form class="hub-sortbar" method="get" action="link-audit.php">'
            . '<input type="hidden" name="page_id" value="' . HUB_linkAuditAdminEscape($hubSelectedPageId) . '">'
            . '<label>Sort by<br><select name="sort">'
            . '<option value="views"' . ($hubSort === 'views' ? ' selected' : '') . '>Views</option>'
            . '<option value="comments"' . ($hubSort === 'comments' ? ' selected' : '') . '>Comments</option>'
            . '<option value="topics"' . ($hubSort === 'topics' ? ' selected' : '') . '>Matched topics</option>'
            . '<option value="date"' . ($hubSort === 'date' ? ' selected' : '') . '>Published date</option>'
            . '<option value="title"' . ($hubSort === 'title' ? ' selected' : '') . '>Title</option>'
            . '</select></label>'
            . '<label>Direction<br><select name="dir">'
            . '<option value="desc"' . ($hubDirection === 'desc' ? ' selected' : '') . '>Descending</option>'
            . '<option value="asc"' . ($hubDirection === 'asc' ? ' selected' : '') . '>Ascending</option>'
            . '</select></label>'
            . '<button class="hub-submit" type="submit">Apply sort</button></form>';

        $content .= '<div style="overflow-x:auto"><table class="admin-list" style="width:100%;border-collapse:collapse"><thead><tr><th>Article</th><th>Matched topics</th><th>Views</th><th>Comments</th><th>Age</th><th>Published</th><th>ID</th><th>Actions</th></tr></thead><tbody>';
        foreach ($hubMissingArticles as $hubArticleRow) {
            $hubSid = isset($hubArticleRow['sid']) ? $hubArticleRow['sid'] : '';
            $hubTitle = isset($hubArticleRow['title']) ? $hubArticleRow['title'] : $hubSid;
            $hubTitle = html_entity_decode((string) $hubTitle, ENT_QUOTES, 'UTF-8');
            $hubDate = isset($hubArticleRow['date']) ? $hubArticleRow['date'] : '';
            $hubHits = isset($hubArticleRow['hits']) ? (int) $hubArticleRow['hits'] : 0;
            $hubComments = isset($hubArticleRow['comments']) ? (int) $hubArticleRow['comments'] : 0;
            $hubTopicCount = !empty($hubArticleRow['hub_topics']) && is_array($hubArticleRow['hub_topics'])
                ? count($hubArticleRow['hub_topics'])
                : 0;
            $hubAge = HUB_linkAuditArticleAge($hubDate);
            $hubArticleUrl = function_exists('COM_buildURL')
                ? COM_buildURL($_CONF['site_url'] . '/article.php?story=' . rawurlencode($hubSid))
                : $_CONF['site_url'] . '/article.php?story=' . rawurlencode($hubSid);
            $hubEditUrl = $_CONF['site_admin_url'] . '/story.php?mode=edit&sid=' . rawurlencode($hubSid);

            $hubMatchedTopicLinks = array();
            if (!empty($hubArticleRow['hub_topics']) && is_array($hubArticleRow['hub_topics'])) {
                foreach ($hubArticleRow['hub_topics'] as $hubMatchedTid => $hubMatchedTopicName) {
                    $hubMatchedTopicUrl = HUB_topicUrl($hubMatchedTid);
                    $hubMatchedTopicLinks[] = '<a href="' . HUB_linkAuditAdminEscape($hubMatchedTopicUrl) . '" target="_blank" rel="noopener">'
                        . HUB_linkAuditAdminEscape($hubMatchedTopicName) . '</a>';
                }
            }
            $hubMatchedTopicsHtml = empty($hubMatchedTopicLinks)
                ? '&mdash;'
                : implode('<br>', $hubMatchedTopicLinks);

            $hubActions = '<a href="' . HUB_linkAuditAdminEscape($hubArticleUrl) . '" target="_blank" rel="noopener">View</a> &middot; <a href="' . HUB_linkAuditAdminEscape($hubEditUrl) . '">Edit</a>';
            if (isset($hubExistingArticleRelations[$hubSid])) {
                $hubActions .= ' &middot; <strong>Already related</strong>';
            } elseif ($hubPillar) {
                $hubActions .= '<form method="post" action="link-audit.php?page_id='
                    . rawurlencode($hubSelectedPageId) . '&amp;sort=' . rawurlencode($hubSort)
                    . '&amp;dir=' . rawurlencode($hubDirection)
                    . '" style="display:inline;margin-left:.5rem">'
                    . HUB_linkAuditTokenField()
                    . '<input type="hidden" name="hub_action" value="add_article_relation">'
                    . '<input type="hidden" name="page_id" value="' . HUB_linkAuditAdminEscape($hubSelectedPageId) . '">'
                    . '<input type="hidden" name="sid" value="' . HUB_linkAuditAdminEscape($hubSid) . '">'
                    . '<button type="submit" class="uk-button">Add relation</button></form>';
            }

            $content .= '<tr><td><strong>' . HUB_linkAuditAdminEscape($hubTitle) . '</strong></td>'
                . '<td><span class="hub-count">' . $hubTopicCount . '</span><br>' . $hubMatchedTopicsHtml . '</td>'
                . '<td class="hub-metric">' . $hubHits . '</td>'
                . '<td class="hub-metric">' . $hubComments . '</td>'
                . '<td>' . HUB_linkAuditAdminEscape($hubAge) . '</td>'
                . '<td>' . HUB_linkAuditAdminEscape($hubDate) . '</td>'
                . '<td><code>' . HUB_linkAuditAdminEscape($hubSid) . '</code></td>'
                . '<td>' . $hubActions . '</td></tr>';
        }
        $content .= '</tbody></table></div>';
    }
}

$hubVersion = function_exists('plugin_chkVersion_hub') ? plugin_chkVersion_hub() : '0.2.0';
$display = COM_startBlock('Hub ' . HUB_linkAuditAdminEscape($hubVersion)) . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
