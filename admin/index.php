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
$content .= '<li><a href="audit.php">Plugin interoperability audit</a> — inspect shared Geeklog contracts and provider capabilities.</li>';
$content .= '<li><a href="link-audit.php">Article link audit</a> — review article-to-Static-Page contextual link opportunities.</li>';
$content .= '</ul>';

$display = COM_startBlock('Hub administration') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
