<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Return visible topics assigned to a Static Page.
 *
 * @param string $pageId
 * @return array
 */
function HUB_staticPageTopics($pageId)
{
    global $_TABLES;

    $topics = array();
    if (empty($_TABLES['topic_assignments']) || empty($_TABLES['topics'])) {
        return $topics;
    }

    $pageId = function_exists('DB_escapeString')
        ? DB_escapeString((string) $pageId)
        : addslashes((string) $pageId);

    $sql = "SELECT DISTINCT t.tid, t.topic "
         . "FROM {$_TABLES['topic_assignments']} AS ta "
         . "INNER JOIN {$_TABLES['topics']} AS t ON t.tid = ta.tid "
         . "WHERE ta.type = 'staticpages' AND ta.id = '" . $pageId . "'";

    if (function_exists('COM_getTopicSQL')) {
        $topicSql = COM_getTopicSQL('AND', 0, 'ta');
        if (!empty($topicSql)) {
            $sql .= ' ' . $topicSql;
        }
    }

    $sql .= ' ORDER BY t.topic';

    $result = DB_query($sql, 1);
    if ($result === false) {
        return $topics;
    }

    while ($row = DB_fetchArray($result)) {
        $tid = isset($row['tid']) ? (string) $row['tid'] : '';
        if ($tid === '') {
            continue;
        }
        if (defined('TOPIC_ALL_OPTION') && $tid === (string) TOPIC_ALL_OPTION) {
            continue;
        }
        if (defined('TOPIC_HOMEONLY_OPTION') && $tid === (string) TOPIC_HOMEONLY_OPTION) {
            continue;
        }
        $topics[] = $row;
    }

    return $topics;
}

/**
 * Build a public topic URL through Geeklog's URL builder.
 *
 * @param string $topicId
 * @return string
 */
function HUB_topicUrl($topicId)
{
    global $_CONF;

    $url = rtrim($_CONF['site_url'], '/') . '/index.php?topic=' . rawurlencode((string) $topicId);

    return function_exists('COM_buildURL') ? COM_buildURL($url) : $url;
}

/**
 * Render the visible topics assigned to a Static Page.
 *
 * @param string $pageId
 * @return string
 */
function HUB_renderStaticPageTopics($pageId)
{
    $rows = HUB_staticPageTopics($pageId);
    if (empty($rows)) {
        return '';
    }

    $links = array();
    foreach ($rows as $row) {
        $tid = isset($row['tid']) ? (string) $row['tid'] : '';
        $label = isset($row['topic']) && $row['topic'] !== '' ? (string) $row['topic'] : $tid;
        if ($tid === '') {
            continue;
        }

        $links[] = '<a href="'
            . htmlspecialchars(HUB_topicUrl($tid), ENT_QUOTES, 'UTF-8')
            . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</a>';
    }

    if (empty($links)) {
        return '';
    }

    return '<p class="hub-staticpage-topics"><strong>Topics:</strong> '
        . implode(' &middot; ', $links)
        . '</p>';
}
