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

$topicId = isset($_GET['tid']) ? COM_applyFilter($_GET['tid']) : '';
$pageId = isset($_GET['page_id']) ? COM_applyFilter($_GET['page_id']) : '';
$runAudit = ($topicId !== '' && $pageId !== '');

$topics = HUB_linkAuditTopics();
$pages = HUB_linkAuditStaticPages();

$content = '<style>';
$content .= '.hub-nav{margin:0 0 18px}.hub-nav a{margin-right:14px}.hub-audit-form{display:grid;grid-template-columns:minmax(220px,1fr) minmax(260px,1fr) auto;gap:12px;align-items:end;padding:16px;background:#f6f7f9;border:1px solid #d9dde5;border-radius:5px}.hub-field label{display:block;font-weight:bold;margin-bottom:5px}.hub-field select{width:100%;min-height:36px}.hub-submit{min-height:36px;padding:6px 14px}.hub-summary{margin:18px 0;padding:12px 14px;background:#f3f7f4;border-left:4px solid #6c9b74}.hub-url{overflow-wrap:anywhere}.hub-empty-result{padding:14px;background:#f3f7f4;border-radius:4px}.hub-warning{padding:10px 12px;background:#fffbea;border-left:4px solid #d7a900;margin:12px 0}@media(max-width:760px){.hub-audit-form{grid-template-columns:1fr}}';
$content .= '</style>';
$content .= '<nav class="hub-nav"><a href="audit.php">Plugin interoperability audit</a><strong>Article link audit</strong></nav>';
$content .= '<h2>Articles without a link to a static page</h2>';
$content .= '<p>Select a topic and a destination static page. Hub lists the published articles assigned to that topic whose introduction and body do not contain a link to the selected page.</p>';

if (empty($topics)) {
    $content .= '<p class="hub-warning">No Geeklog topic could be loaded.</p>';
}

if (empty($pages)) {
    $content .= '<p class="hub-warning">No static page could be loaded. Check that the Static Pages plugin is installed and enabled.</p>';
}

if (!empty($topics) && !empty($pages)) {
    $content .= '<form class="hub-audit-form" method="get" action="link-audit.php">';
    $content .= '<div class="hub-field"><label for="tid">Topic</label><select id="tid" name="tid" required><option value="">Select a topic</option>';
    foreach ($topics as $topic) {
        $tid = isset($topic['tid']) ? $topic['tid'] : '';
        $topicName = isset($topic['topic']) ? $topic['topic'] : $tid;
        $selected = ((string) $tid === (string) $topicId) ? ' selected' : '';
        $content .= '<option value="' . HUB_linkAuditAdminEscape($tid) . '"' . $selected . '>' . HUB_linkAuditAdminEscape($topicName) . ' (' . HUB_linkAuditAdminEscape($tid) . ')</option>';
    }
    $content .= '</select></div>';

    $content .= '<div class="hub-field"><label for="page_id">Static page</label><select id="page_id" name="page_id" required><option value="">Select a static page</option>';
    foreach ($pages as $page) {
        $spId = isset($page['sp_id']) ? $page['sp_id'] : '';
        $spTitle = isset($page['sp_title']) ? $page['sp_title'] : $spId;
        $selected = ((string) $spId === (string) $pageId) ? ' selected' : '';
        $content .= '<option value="' . HUB_linkAuditAdminEscape($spId) . '"' . $selected . '>' . HUB_linkAuditAdminEscape($spTitle) . ' (' . HUB_linkAuditAdminEscape($spId) . ')</option>';
    }
    $content .= '</select></div>';
    $content .= '<button class="hub-submit" type="submit">Run audit</button>';
    $content .= '</form>';
}

if ($runAudit && !empty($topics) && !empty($pages)) {
    $allArticles = HUB_linkAuditArticlesByTopic($topicId);
    $missing = HUB_linkAuditMissingArticles($topicId, $pageId);
    $targetUrl = HUB_linkAuditStaticPageUrl($pageId);

    $content .= '<div class="hub-summary"><strong>' . count($missing) . '</strong> article(s) without the selected link out of <strong>' . count($allArticles) . '</strong> published article(s).<br><span class="hub-url">Target: <a href="' . HUB_linkAuditAdminEscape($targetUrl) . '" target="_blank" rel="noopener">' . HUB_linkAuditAdminEscape($targetUrl) . '</a></span></div>';

    if (empty($missing)) {
        $content .= '<p class="hub-empty-result">Every published article in this topic contains a link to the selected page.</p>';
    } else {
        $content .= '<div style="overflow-x:auto"><table class="admin-list" style="width:100%;border-collapse:collapse"><thead><tr><th>Article</th><th>ID</th><th>Published</th><th>Actions</th></tr></thead><tbody>';
        foreach ($missing as $article) {
            $sid = isset($article['sid']) ? $article['sid'] : '';
            $title = isset($article['title']) ? $article['title'] : $sid;
            $date = isset($article['date']) ? $article['date'] : '';
            $articleUrl = function_exists('COM_buildURL')
                ? COM_buildURL($_CONF['site_url'] . '/article.php?story=' . rawurlencode($sid))
                : $_CONF['site_url'] . '/article.php?story=' . rawurlencode($sid);
            $editUrl = $_CONF['site_admin_url'] . '/story.php?mode=edit&sid=' . rawurlencode($sid);
            $content .= '<tr><td><strong>' . HUB_linkAuditAdminEscape($title) . '</strong></td><td><code>' . HUB_linkAuditAdminEscape($sid) . '</code></td><td>' . HUB_linkAuditAdminEscape($date) . '</td><td><a href="' . HUB_linkAuditAdminEscape($articleUrl) . '" target="_blank" rel="noopener">View</a> &middot; <a href="' . HUB_linkAuditAdminEscape($editUrl) . '">Edit</a></td></tr>';
        }
        $content .= '</tbody></table></div>';
    }
}

$hubVersion = function_exists('plugin_chkVersion_hub') ? plugin_chkVersion_hub() : '0.1.1-dev';
$display = COM_startBlock('Hub ' . HUB_linkAuditAdminEscape($hubVersion)) . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
