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
 * Describe the native Geeklog topic assignment context of a Static Page.
 *
 * "all" and "homeonly" are placement/display options inherited from Geeklog's
 * Static Pages model. They are not treated by Hub as editorial topic
 * relationships. Only real topic ids are returned as specific topics.
 *
 * @param string $pageId
 * @return array
 */
function HUB_staticPageTopicContext($pageId)
{
    global $_TABLES;

    $context = array(
        'all' => false,
        'homeonly' => false,
        'specific_topics' => HUB_staticPageTopics($pageId),
        'assignment_ids' => array(),
        'placement_only' => false,
    );

    if (empty($_TABLES['topic_assignments'])) {
        return $context;
    }

    $escapedPageId = function_exists('DB_escapeString')
        ? DB_escapeString((string) $pageId)
        : addslashes((string) $pageId);

    $sql = "SELECT DISTINCT tid FROM {$_TABLES['topic_assignments']} "
         . "WHERE type = 'staticpages' AND id = '" . $escapedPageId . "'";

    $result = DB_query($sql, 1);
    if ($result === false) {
        return $context;
    }

    $allId = defined('TOPIC_ALL_OPTION') ? (string) TOPIC_ALL_OPTION : 'all';
    $homeOnlyId = defined('TOPIC_HOMEONLY_OPTION') ? (string) TOPIC_HOMEONLY_OPTION : 'homeonly';

    while ($row = DB_fetchArray($result)) {
        $tid = isset($row['tid']) ? (string) $row['tid'] : '';
        if ($tid === '') {
            continue;
        }

        $context['assignment_ids'][] = $tid;

        if ($tid === $allId) {
            $context['all'] = true;
        } elseif ($tid === $homeOnlyId) {
            $context['homeonly'] = true;
        }
    }

    $context['assignment_ids'] = array_values(array_unique($context['assignment_ids']));
    $context['placement_only'] = empty($context['specific_topics'])
        && ($context['all'] || $context['homeonly']);

    return $context;
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
 * Prefer Geeklog's native related-topics renderer so Static Pages use the
 * same localized label, permissions and topicrelated.thtml markup as articles.
 * Keep a small fallback for supported installations where the helper is not
 * available.
 *
 * @param string $pageId
 * @return string
 */
function HUB_renderStaticPageTopics($pageId)
{
    if (function_exists('TOPIC_relatedTopics')) {
        $html = TOPIC_relatedTopics('staticpages', (string) $pageId, 0);
        if ($html === '') {
            return '';
        }

        return '<div class="hub-staticpage-topics">' . $html . '</div>';
    }

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

    return '<div class="hub-staticpage-topics"><div class="related-topics">Topics: ' . implode(' ', $links) . '</div></div>';
}


/**
 * Return enabled Hub Static Page pillar contexts assigned to a Geeklog topic.
 *
 * @param string $topicId
 * @param bool $includeDisabled
 * @return array
 */
function HUB_findPillarContextsForTopic($topicId, $includeDisabled = false)
{
    global $_TABLES;

    $topicId = trim((string) $topicId);
    if ($topicId === '' || empty($_TABLES['topic_assignments']) || empty($_TABLES['hub_pillars'])) {
        return array();
    }

    $topicSql = DB_escapeString($topicId);
    $sql = "SELECT DISTINCT p.id, p.source_type, p.source_id, p.is_enabled "
         . "FROM {$_TABLES['hub_pillars']} AS p "
         . "INNER JOIN {$_TABLES['topic_assignments']} AS ta "
         . "ON ta.type = 'staticpages' AND ta.id = p.source_id "
         . "WHERE p.source_type = 'staticpages' "
         . "AND ta.tid = '" . $topicSql . "'";

    if (!$includeDisabled) {
        $sql .= " AND p.is_enabled = 1";
    }

    $sql .= " ORDER BY p.id ASC";

    $rows = array();
    $result = DB_query($sql, 1);
    if ($result === false) {
        return $rows;
    }

    while ($row = DB_fetchArray($result)) {
        if (!is_array($row) || empty($row['id'])) {
            continue;
        }

        $rows[] = array(
            'pillar_id' => (int) $row['id'],
            'source_type' => (string) $row['source_type'],
            'source_id' => (string) $row['source_id'],
            'reasons' => array('topic-assignment'),
        );
    }

    return $rows;
}
