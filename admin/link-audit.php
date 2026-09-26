<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'system/lib-admin.php';

/*
 * This administration page can be called directly. Do not rely on Geeklog
 * having loaded the Hub functions.inc file before reaching this script.
 */
$hubPluginPath = rtrim($_CONF['path'], '/\\') . '/plugins/hub/';
if (!function_exists('HUB_linkAuditTopics')) {
    require_once $hubPluginPath . 'lib-link-audit.php';
}
if (!function_exists('HUB_topicUrl')) {
    require_once $hubPluginPath . 'lib-staticpages.php';
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

$hubSelectedPageId = isset($_GET['page_id']) ? COM_applyFilter($_GET['page_id']) : '';
$hubRunAudit = ($hubSelectedPageId !== '');

$hubStaticPageRows = HUB_linkAuditStaticPages();

$content = '<style>';
$content .= '.hub-nav{margin:0 0 18px}.hub-nav a{margin-right:14px}.hub-audit-form{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:12px;align-items:end;padding:16px;background:#f6f7f9;border:1px solid #d9dde5;border-radius:5px}.hub-field label{display:block;font-weight:bold;margin-bottom:5px}.hub-field select{width:100%;min-height:36px}.hub-submit{min-height:36px;padding:6px 14px}.hub-summary{margin:18px 0;padding:12px 14px;background:#f3f7f4;border-left:4px solid #6c9b74}.hub-url{overflow-wrap:anywhere}.hub-topic-note{margin:10px 0 0}.hub-topic-note a{overflow-wrap:anywhere}.hub-empty-result{padding:14px;background:#f3f7f4;border-radius:4px}.hub-warning{padding:10px 12px;background:#fffbea;border-left:4px solid #d7a900;margin:12px 0}@media(max-width:760px){.hub-audit-form{grid-template-columns:1fr}}';
$content .= '</style>';
$content .= '<nav class="hub-nav"><a href="audit.php">Plugin interoperability audit</a><strong>Article link audit</strong></nav>';
$content .= '<h2>Articles without a link to a static page</h2>';
$content .= '<p>Select a Static Page. Hub discovers its assigned topics automatically, gathers published articles from those topics, and lists the articles that do not currently contain a link to the selected page.</p>';

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
    $content .= '<button class="hub-submit" type="submit">Run audit</button>';
    $content .= '</form>';
}

if ($hubRunAudit && !empty($hubStaticPageRows)) {
    $hubAssignedTopics = HUB_staticPageTopics($hubSelectedPageId);
    $hubTopicIds = array();
    foreach ($hubAssignedTopics as $hubAssignedTopic) {
        if (isset($hubAssignedTopic['tid']) && $hubAssignedTopic['tid'] !== '') {
            $hubTopicIds[] = (string) $hubAssignedTopic['tid'];
        }
    }

    $hubAllArticles = HUB_linkAuditArticlesByTopics($hubTopicIds);
    $hubMissingArticles = HUB_linkAuditMissingArticlesForTopics($hubTopicIds, $hubSelectedPageId);
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

    $content .= '<div class="hub-summary"><strong>' . count($hubMissingArticles) . '</strong> possible link(s) to review out of <strong>' . count($hubAllArticles) . '</strong> published article(s) found in the Static Page\'s assigned topics.'
        . '<br><span class="hub-url">Target: <a href="' . $hubEscapedTargetUrl . '" target="_blank" rel="noopener">' . $hubEscapedTargetUrl . '</a></span>';

    if (!empty($hubTopicLinks)) {
        $content .= '<br><span>Assigned topics: ' . implode(' &middot; ', $hubTopicLinks) . '</span>';
    } else {
        $content .= '<p class="hub-warning">This Static Page has no visible assigned topics, so there are no topic-related articles to audit.</p>';
    }

    $content .= '<p class="hub-topic-note">Hub automatically adds the assigned topic links at the bottom of the Static Page. You can also edit the page and place any of these topic links manually at the most relevant position in the page content.</p></div>';

    if (empty($hubMissingArticles)) {
        if (!empty($hubTopicIds)) {
            $content .= '<p class="hub-empty-result">No missing contextual links were found among published articles in the assigned topics.</p>';
        }
    } else {
        $content .= '<p>The articles below belong to one or more topics assigned to this Static Page but do not currently link back to it. Review them to decide whether adding a contextual link is appropriate.</p>';
        $content .= '<div style="overflow-x:auto"><table class="admin-list" style="width:100%;border-collapse:collapse"><thead><tr><th>Article</th><th>ID</th><th>Published</th><th>Actions</th></tr></thead><tbody>';
        foreach ($hubMissingArticles as $hubArticleRow) {
            $hubSid = isset($hubArticleRow['sid']) ? $hubArticleRow['sid'] : '';
            $hubTitle = isset($hubArticleRow['title']) ? $hubArticleRow['title'] : $hubSid;
            $hubDate = isset($hubArticleRow['date']) ? $hubArticleRow['date'] : '';
            $hubArticleUrl = function_exists('COM_buildURL')
                ? COM_buildURL($_CONF['site_url'] . '/article.php?story=' . rawurlencode($hubSid))
                : $_CONF['site_url'] . '/article.php?story=' . rawurlencode($hubSid);
            $hubEditUrl = $_CONF['site_admin_url'] . '/story.php?mode=edit&sid=' . rawurlencode($hubSid);
            $content .= '<tr><td><strong>' . HUB_linkAuditAdminEscape($hubTitle) . '</strong></td><td><code>' . HUB_linkAuditAdminEscape($hubSid) . '</code></td><td>' . HUB_linkAuditAdminEscape($hubDate) . '</td><td><a href="' . HUB_linkAuditAdminEscape($hubArticleUrl) . '" target="_blank" rel="noopener">View</a> &middot; <a href="' . HUB_linkAuditAdminEscape($hubEditUrl) . '">Edit</a></td></tr>';
        }
        $content .= '</tbody></table></div>';
    }
}

$hubVersion = function_exists('plugin_chkVersion_hub') ? plugin_chkVersion_hub() : '0.1.1-dev';
$display = COM_startBlock('Hub ' . HUB_linkAuditAdminEscape($hubVersion)) . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
