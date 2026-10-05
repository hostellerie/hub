<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'plugins/hub/lib-admin-ui.php';

if (!SEC_hasRights('hub.admin')) {
    COM_accessLog('User ' . (int) $_USER['uid'] . ' attempted to access Hub administration without permission.');
    $display = COM_startBlock('Access denied') . 'You do not have sufficient rights to access this page.' . COM_endBlock();
    COM_output(COM_createHTMLDocument($display));
    exit;
}

$version = function_exists('plugin_chkVersion_hub') ? plugin_chkVersion_hub() : '0.3.0';

$content = HUB_adminNavigation('home');
$content .= '<h1>Hub ' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '</h1>';
$content .= '<p>Hub manages Geeklog interoperability, editorial pillars and stable content relationships.</p>';
$content .= '<ul>';
$content .= '<li><a href="relations.php"><strong>Pillars &amp; manual relations</strong></a> — create Static Page pillars and attach related items by stable type + id.</li>';
$content .= '<li><a href="editorial.php"><strong>Editorial mapping</strong></a> — inspect the approved pillar/cluster structure, role counts and provider participation.</li>';
$content .= '<li><a href="audit.php">Plugin interoperability audit</a> — inspect shared Geeklog contracts and provider capabilities.</li>';
$content .= '<li><a href="link-audit.php">Article link audit</a> — review article-to-Static-Page contextual link opportunities.</li>';
$content .= '</ul>';

$indexNow = function_exists('HUB_indexNowStatus') ? HUB_indexNowStatus() : array('available' => false);
$content .= '<h2>IndexNow integration</h2>';

if (empty($indexNow['available'])) {
    $content .= '<p>IndexNow service integration is not available. Hub continues normally without indexing delegation.</p>';
} else {
    $data = isset($indexNow['data']) && is_array($indexNow['data']) ? $indexNow['data'] : array();
    $key = isset($data['key']) && is_array($data['key']) ? $data['key'] : array();
    $transport = isset($data['transport']) && is_array($data['transport']) ? $data['transport'] : array();
    $latest = isset($data['latest_submission']) && is_array($data['latest_submission'])
        ? $data['latest_submission'] : array();

    $ready = !empty($key['present']) && !empty($key['valid'])
        && !empty($key['file_exists']) && !empty($key['file_readable']) && !empty($key['file_matches']);

    $content .= '<p><strong>Status:</strong> ' . ($ready ? 'Ready' : 'Needs attention') . '</p>';
    $content .= '<ul>';
    $content .= '<li>Transport: ' . htmlspecialchars(
        isset($transport['mode']) ? (string) $transport['mode'] : 'unknown',
        ENT_QUOTES,
        'UTF-8'
    ) . '</li>';
    $content .= '<li>Key configured: ' . (!empty($key['present']) ? 'yes' : 'no') . '</li>';
    $content .= '<li>Key valid: ' . (!empty($key['valid']) ? 'yes' : 'no') . '</li>';
    $content .= '<li>Key file ready: ' . (
        !empty($key['file_exists']) && !empty($key['file_readable']) && !empty($key['file_matches'])
            ? 'yes' : 'no'
    ) . '</li>';
    $content .= '<li>Submission history available: ' . (!empty($data['history_available']) ? 'yes' : 'no') . '</li>';
    $content .= '</ul>';

    if (!empty($latest)) {
        $latestStatus = isset($latest['status']) ? (string) $latest['status'] : 'unknown';
        $latestAt = isset($latest['submitted_at']) ? (string) $latest['submitted_at'] : '';
        $content .= '<p>Latest submission: <strong>'
            . htmlspecialchars($latestStatus, ENT_QUOTES, 'UTF-8') . '</strong>'
            . ($latestAt !== '' ? ' — ' . htmlspecialchars($latestAt, ENT_QUOTES, 'UTF-8') : '')
            . '</p>';
    }
}

$display = COM_startBlock('Hub administration') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
