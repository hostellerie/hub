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

$hubSelectedTopicId = isset($_GET['tid']) ? COM_applyFilter($_GET['tid']) : '';
$hubSelectedPageId = isset($_GET['page_id']) ? COM_applyFilter($_GET['page_id']) : '';
$hubRunAudit = ($hubSelectedTopicId !== '' && $hubSelectedPageId !== '');

$hubTopicRows = HUB_linkAuditTopics();
$hubStaticPageRows = HUB_linkAuditStaticPages();

$content = '<style>';
$content .= '.hub-nav{margin:0 0 18px}.hub-nav a{margin-right:14px}.hub-audit-form{display:grid;grid-template-columns:minmax(220px,1fr) minmax(260px,1fr) auto;gap:12px;align-items:end;padding:16px;background:#f6f7f9;border:1px solid #d9dde5;border-radius:5px}.hub-field label{display:block;font-weight:bold;margin-bottom:5px}.hub-field select{width:100%;min-height:36px}.hub-submit{min-height:36px;padding:6px 14px}.hub-summary{margin:18px 0;padding:12px 14px;background:#f3f7f4;border-left:4px solid #6c9b74}.hub-url{overflow-wrap:anywhere}.hub-empty-result{padding:14px;background:#f3f7f4;border-radius:4px}.hub-warning{padding:10px 12px;background:#fffbea;border-left:4px solid #d7a900;margin:12px 0}@media(max-width:760px){.hub-audit-form{grid-template-columns:1fr}}';
$content .= '</style>';
$content .= '<nav class="hub-nav"><a href="audit.php">Plugin interoperability audit</a><strong>Article link audit</strong></nav>';
$content .= '<h2>Articles without a link to a static page</h2>';
$content .= '<p>Select a topic and a destination static page. Hub lists the published articles assigned to that topic whose introduction and body do not contain a link to the selected page.</p>';

if (empty($hubTopicRows)) {
    $content .= '<p class="hub-warning">No Geeklog topic could be loaded.</p>';
}

if (empty($hubStaticPageRows)) {
    $content .= '<p class="hub-warning">No static page could be loaded. Check that the Static Pages plugin is installed and enabled.</p>';
}

if (!empty($hubTopicRows) && !empty($hubStaticPageRows)) {
    $content .= '<form class="hub-audit-form" method="get" action="link-audit.php">';
    $content .= '<div class="hub-field"><label for="tid">Topic</label><select id="tid" name="tid" required><option value="">Select a topic</option>';
    foreach ($hubTopicRows as $hubTopicRow) {
        $hubTid = isset($hubTopicRow['tid']) ? $hubTopicRow['tid'] : '';
        $hubTopicName = isset($hubTopicRow['topic']) ? $hubTopicRow['topic'] : $hubTid;
        $hubSelected = ((string) $hubTid === (string) $hubSelectedTopicId) ? ' selected' : '';
        $content .= '<option value="' . HUB_linkAuditAdminEscape($hubTid) . '"' . $hubSelected . '>' . HUB_linkAuditAdminEscape($hubTopicName) . ' (' . HUB_linkAuditAdminEscape($hubTid) . ')</option>';
    }
    $content .= '</select></div>';

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

if ($hubRunAudit && !empty($hubTopicRows) && !empty($hubStaticPageRows)) {
    $hubAllArticles = HUB_linkAuditArticlesByTopic($hubSelectedTopicId);
    $hubMissingArticles = HUB_linkAuditMissingArticles($hubSelectedTopicId, $hubSelectedPageId);
    $hubTargetUrl = HUB_linkAuditStaticPageUrl($hubSelectedPageId);

    $content .= '<div class="hub-summary"><strong>' . count($hubMissingArticles) . '</strong> article(s) without the selected link out of <strong>' . count($hubAllArticles) . '</strong> published article(s).<br><span class="hub-url">Target: <a href="' . HUB_linkAuditAdminEscape($hubTargetUrl) . '" target="_blank" rel="noopener">' . HUB_linkAuditAdminEscape($hubTargetUrl) . '</a></span></div>';

    if (empty($hubMissingArticles)) {
        $content .= '<p class="hub-empty-result">Every published article in this topic contains a link to the selected page.</p>';
    } else {
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
