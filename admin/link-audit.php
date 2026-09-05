<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'system/lib-admin.php';

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

$topics = HUB_linkAuditTopics();
$pages = HUB_linkAuditStaticPages();
$topicId = isset($_GET['tid']) ? COM_applyFilter($_GET['tid']) : '';
$pageId = isset($_GET['page_id']) ? COM_applyFilter($_GET['page_id']) : '';
$runAudit = ($topicId !== '' && $pageId !== '');

$content = '<style>';
$content .= '.hub-nav{margin:0 0 18px}.hub-nav a{margin-right:14px}.hub-audit-form{display:grid;grid-template-columns:minmax(220px,1fr) minmax(260px,1fr) auto;gap:12px;align-items:end;padding:16px;background:#f6f7f9;border:1px solid #d9dde5;border-radius:5px}.hub-field label{display:block;font-weight:bold;margin-bottom:5px}.hub-field select{width:100%;min-height:36px}.hub-submit{min-height:36px;padding:6px 14px}.hub-summary{margin:18px 0;padding:12px 14px;background:#f3f7f4;border-left:4px solid #6c9b74}.hub-url{overflow-wrap:anywhere}.hub-empty-result{padding:14px;background:#f3f7f4;border-radius:4px}@media(max-width:760px){.hub-audit-form{grid-template-columns:1fr}}';
$content .= '</style>';
$content .= '<nav class="hub-nav"><a href="audit.php">Plugin interoperability audit</a><strong>Article link audit</strong></nav>';
$content .= '<h2>Articles without a link to a static page</h2>';
$content .= '<p>Select a topic and the destination static page. Hub checks the stored introduction and body of every published article assigned directly to that topic.</p>';

if (empty($pages)) {
    $content .= '<p class="pluginAlert">No Static Pages table or static page was found.</p>';
} else {
    $content .= '<form class="hub-audit-form" method="get" action="link-audit.php">';
    $content .= '<div class="hub-field"><label for="tid">Topic</label><select id="tid" name="tid" required><option value="">Select a topic</option>';
    foreach ($topics as $topic) {
        $selected = ((string) $topic['tid'] === (string) $topicId) ? ' selected' : '';
        $content .= '<option value="' . HUB_linkAuditAdminEscape($topic['tid']) . '"' . $selected . '>' . HUB_linkAuditAdminEscape($topic['topic']) . ' (' . HUB_linkAuditAdminEscape($topic['tid']) . ')</option>';
    }
    $content .= '</select></div>';
    $content .= '<div class="hub-field"><label for="page_id">Static page</label><select id="page_id" name="page_id" required><option value="">Select a static page</option>';
    foreach ($pages as $page) {
        $selected = ((string) $page['sp_id'] === (string) $pageId) ? ' selected' : '';
        $content .= '<option value="' . HUB_linkAuditAdminEscape($page['sp_id']) . '"' . $selected . '>' . HUB_linkAuditAdminEscape($page['sp_title']) . ' (' . HUB_linkAuditAdminEscape($page['sp_id']) . ')</option>';
    }
    $content .= '</select></div><button class="hub-submit" type="submit">Run audit</button></form>';
}

if ($runAudit) {
    $allArticles = HUB_linkAuditArticlesByTopic($topicId);
    $missing = HUB_linkAuditMissingArticles($topicId, $pageId);
    $targetUrl = HUB_linkAuditStaticPageUrl($pageId);

    $content .= '<div class="hub-summary"><strong>' . count($missing) . '</strong> article(s) without the link out of <strong>' . count($allArticles) . '</strong> published article(s).<br><span class="hub-url">Target: <a href="' . HUB_linkAuditAdminEscape($targetUrl) . '" target="_blank" rel="noopener">' . HUB_linkAuditAdminEscape($targetUrl) . '</a></span></div>';

    if (empty($missing)) {
        $content .= '<p class="hub-empty-result">Every published article in this topic contains a link to the selected page.</p>';
    } else {
        $content .= '<div style="overflow-x:auto"><table class="admin-list" style="width:100%;border-collapse:collapse"><thead><tr><th>Article</th><th>ID</th><th>Published</th><th>Actions</th></tr></thead><tbody>';
        foreach ($missing as $article) {
            $articleUrl = function_exists('COM_buildURL')
                ? COM_buildURL($_CONF['site_url'] . '/article.php?story=' . rawurlencode($article['sid']))
                : $_CONF['site_url'] . '/article.php?story=' . rawurlencode($article['sid']);
            $editUrl = $_CONF['site_admin_url'] . '/story.php?mode=edit&sid=' . rawurlencode($article['sid']);
            $content .= '<tr><td><strong>' . HUB_linkAuditAdminEscape($article['title']) . '</strong></td><td><code>' . HUB_linkAuditAdminEscape($article['sid']) . '</code></td><td>' . HUB_linkAuditAdminEscape($article['date']) . '</td><td><a href="' . HUB_linkAuditAdminEscape($articleUrl) . '" target="_blank" rel="noopener">View</a> &middot; <a href="' . HUB_linkAuditAdminEscape($editUrl) . '">Edit</a></td></tr>';
        }
        $content .= '</tbody></table></div>';
    }
}

$display = COM_startBlock('Hub 0.1.1-dev') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
