<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Build a deterministic read-only summary of Hub's approved editorial graph.
 *
 * This is intentionally structural, not diagnostic. Integrity/SEO health,
 * unresolved objects, orphan discovery and inferred recommendations remain
 * separate concerns and are not silently mixed into this surface.
 *
 * @param bool $includeDisabled
 * @return array
 */
function HUB_editorialSummary($includeDisabled = false)
{
    $pillars = HUB_getPillars($includeDisabled);

    $summary = array(
        'schema' => 1,
        'pillars' => 0,
        'relations' => 0,
        'roles' => array(
            'related' => 0,
            'sub-pillar' => 0,
            'satellite' => 0,
            'support' => 0,
        ),
        'providers' => array(),
        'multi_parent_items' => 0,
        'nested_pillars' => 0,
        'pillar_items' => array(),
    );

    $parentsByItem = array();
    $pillarIdentities = array();

    foreach ($pillars as $pillar) {
        if (!is_array($pillar)
            || empty($pillar['id'])
            || empty($pillar['source_type'])
            || empty($pillar['source_id'])
        ) {
            continue;
        }

        $pillarId = (int) $pillar['id'];
        $sourceType = HUB_normalizeObjectType($pillar['source_type']);
        $sourceId = HUB_normalizeObjectId($pillar['source_id']);
        if ($pillarId < 1 || $sourceType === '' || $sourceId === '') {
            continue;
        }

        $summary['pillars']++;
        $pillarKey = HUB_graphIdentityKey($sourceType, $sourceId);
        if ($pillarKey !== '') {
            $pillarIdentities[$pillarKey] = true;
        }

        $relations = HUB_getRelations($pillarId, $includeDisabled);
        $item = array(
            'pillar_id' => $pillarId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'relations' => 0,
            'roles' => array(
                'related' => 0,
                'sub-pillar' => 0,
                'satellite' => 0,
                'support' => 0,
            ),
            'providers' => array(),
        );

        foreach ($relations as $relation) {
            if (!is_array($relation)
                || empty($relation['item_type'])
                || empty($relation['item_id'])
            ) {
                continue;
            }

            $type = HUB_normalizeObjectType($relation['item_type']);
            $id = HUB_normalizeObjectId($relation['item_id']);
            if ($type === '' || $id === '') {
                continue;
            }

            $role = isset($relation['relation_role'])
                ? HUB_normalizeRelationRole($relation['relation_role'])
                : 'related';

            $summary['relations']++;
            $summary['roles'][$role]++;
            $summary['providers'][$type] = isset($summary['providers'][$type])
                ? $summary['providers'][$type] + 1 : 1;

            $item['relations']++;
            $item['roles'][$role]++;
            $item['providers'][$type] = isset($item['providers'][$type])
                ? $item['providers'][$type] + 1 : 1;

            $key = HUB_graphIdentityKey($type, $id);
            if ($key !== '') {
                if (!isset($parentsByItem[$key])) {
                    $parentsByItem[$key] = array();
                }
                $parentsByItem[$key][$pillarId] = true;
            }
        }

        ksort($item['providers'], SORT_STRING);
        $summary['pillar_items'][] = $item;
    }

    foreach ($parentsByItem as $key => $parents) {
        if (count($parents) > 1) {
            $summary['multi_parent_items']++;
        }
        if (isset($pillarIdentities[$key])) {
            $summary['nested_pillars']++;
        }
    }

    ksort($summary['providers'], SORT_STRING);
    usort($summary['pillar_items'], function ($left, $right) {
        if ((int) $left['pillar_id'] === (int) $right['pillar_id']) {
            return 0;
        }
        return (int) $left['pillar_id'] < (int) $right['pillar_id'] ? -1 : 1;
    });

    return $summary;
}


/**
 * Build a deterministic provider-neutral inventory from approved Hub relations.
 *
 * This inventory exposes only Hub-owned structure and stable identities. It
 * deliberately avoids provider metadata resolution, link-health diagnostics
 * and inferred recommendations.
 *
 * @param bool $includeDisabled
 * @return array
 */
function HUB_editorialInventory($includeDisabled = false)
{
    $pillars = HUB_getPillars($includeDisabled);
    $pillarIdentities = array();
    $parentsByItem = array();

    foreach ($pillars as $pillar) {
        if (!is_array($pillar)
            || empty($pillar['id'])
            || empty($pillar['source_type'])
            || empty($pillar['source_id'])
        ) {
            continue;
        }

        $key = HUB_graphIdentityKey($pillar['source_type'], $pillar['source_id']);
        if ($key !== '') {
            $pillarIdentities[$key] = (int) $pillar['id'];
        }

        foreach (HUB_getRelations((int) $pillar['id'], $includeDisabled) as $relation) {
            if (!is_array($relation)
                || empty($relation['item_type'])
                || empty($relation['item_id'])
            ) {
                continue;
            }

            $itemKey = HUB_graphIdentityKey($relation['item_type'], $relation['item_id']);
            if ($itemKey === '') {
                continue;
            }

            if (!isset($parentsByItem[$itemKey])) {
                $parentsByItem[$itemKey] = array();
            }
            $parentsByItem[$itemKey][(int) $pillar['id']] = true;
        }
    }

    $inventory = array(
        'schema' => 1,
        'pillars' => array(),
    );

    foreach ($pillars as $pillar) {
        if (!is_array($pillar)
            || empty($pillar['id'])
            || empty($pillar['source_type'])
            || empty($pillar['source_id'])
        ) {
            continue;
        }

        $pillarId = (int) $pillar['id'];
        $sourceType = HUB_normalizeObjectType($pillar['source_type']);
        $sourceId = HUB_normalizeObjectId($pillar['source_id']);
        if ($pillarId < 1 || $sourceType === '' || $sourceId === '') {
            continue;
        }

        $entry = array(
            'pillar_id' => $pillarId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'is_enabled' => !empty($pillar['is_enabled']),
            'items' => array(),
        );

        foreach (HUB_getRelations($pillarId, $includeDisabled) as $relation) {
            if (!is_array($relation)
                || empty($relation['item_type'])
                || empty($relation['item_id'])
            ) {
                continue;
            }

            $type = HUB_normalizeObjectType($relation['item_type']);
            $id = HUB_normalizeObjectId($relation['item_id']);
            if ($type === '' || $id === '') {
                continue;
            }

            $key = HUB_graphIdentityKey($type, $id);
            $entry['items'][] = array(
                'type' => $type,
                'id' => $id,
                'relation_role' => isset($relation['relation_role'])
                    ? HUB_normalizeRelationRole($relation['relation_role'])
                    : 'related',
                'position' => isset($relation['position']) ? (int) $relation['position'] : 0,
                'is_enabled' => !empty($relation['is_enabled']),
                'is_nested_pillar' => isset($pillarIdentities[$key]),
                'nested_pillar_id' => isset($pillarIdentities[$key])
                    ? (int) $pillarIdentities[$key] : 0,
                'parent_count' => isset($parentsByItem[$key])
                    ? count($parentsByItem[$key]) : 0,
            );
        }

        usort($entry['items'], function ($left, $right) {
            if ((int) $left['position'] !== (int) $right['position']) {
                return (int) $left['position'] < (int) $right['position'] ? -1 : 1;
            }

            $leftKey = (string) $left['type'] . ':' . (string) $left['id'];
            $rightKey = (string) $right['type'] . ':' . (string) $right['id'];

            return strcmp($leftKey, $rightKey);
        });

        $inventory['pillars'][] = $entry;
    }

    usort($inventory['pillars'], function ($left, $right) {
        if ((int) $left['pillar_id'] === (int) $right['pillar_id']) {
            return 0;
        }
        return (int) $left['pillar_id'] < (int) $right['pillar_id'] ? -1 : 1;
    });

    return $inventory;
}


/**
 * Normalize the specific Geeklog topic context for one Static Page pillar.
 *
 * @param string $pageId
 * @return array
 */
function HUB_editorialTopicContext($pageId)
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

        $ids[$tid] = true;
        $labels[$tid] = isset($topic['topic']) && (string) $topic['topic'] !== ''
            ? (string) $topic['topic']
            : $tid;
    }

    ksort($labels, SORT_STRING);

    return array(
        'ids' => array_keys($ids),
        'labels' => $labels,
    );
}

/**
 * Suggest unapproved article relations for one Static Page pillar.
 *
 * Candidates come only from deterministic shared-topic evidence and never
 * modify Hub relationships automatically.
 *
 * @param array $pillar
 * @param array $relations
 * @param int $limit
 * @return array
 */
/**
 * Extract objective temporal/version markers from article metadata/content.
 *
 * The result is advisory only. It never declares content stale or obsolete.
 *
 * @param array $article
 * @param int|null $referenceYear
 * @return array
 */
function HUB_editorialTemporalSignals($article, $referenceYear = null)
{
    $referenceYear = $referenceYear === null ? (int) date('Y') : (int) $referenceYear;
    if ($referenceYear < 1970) {
        $referenceYear = (int) date('Y');
    }

    $title = isset($article['title']) ? (string) $article['title'] : '';
    $intro = isset($article['introtext']) ? strip_tags((string) $article['introtext']) : '';
    $body = isset($article['bodytext']) ? strip_tags((string) $article['bodytext']) : '';
    $text = trim($title . " " . $intro . " " . $body);

    $years = array();
    if ($text !== '' && preg_match_all('/\\b(?:19[5-9]\\d|20\\d{2})\\b/', $text, $matches)) {
        foreach ($matches[0] as $year) {
            $year = (int) $year;
            if ($year > 0) {
                $years[$year] = true;
            }
        }
    }
    $years = array_keys($years);
    sort($years, SORT_NUMERIC);

    $versions = array();
    if ($text !== '' && preg_match_all('/\\bv?\\d{1,3}\\.\\d{1,3}(?:\\.\\d{1,3})?\\b/i', $text, $matches)) {
        foreach ($matches[0] as $version) {
            $normalized = strtolower((string) $version);
            $versions[$normalized] = (string) $version;
        }
    }
    $versions = array_values($versions);
    natcasesort($versions);
    $versions = array_values($versions);

    $publishedAt = isset($article['date']) ? trim((string) $article['date']) : '';
    $publishedYear = 0;
    if ($publishedAt !== '' && preg_match('/^(\\d{4})-/', $publishedAt, $matches)) {
        $publishedYear = (int) $matches[1];
    }

    $ageYears = ($publishedYear > 0 && $referenceYear >= $publishedYear)
        ? $referenceYear - $publishedYear
        : null;

    $olderYearMarkers = array();
    foreach ($years as $year) {
        if ($year < $referenceYear) {
            $olderYearMarkers[] = $year;
        }
    }

    $reviewReasons = array();
    if (!empty($olderYearMarkers)) {
        $reviewReasons[] = 'older-year-marker';
    }
    if (!empty($versions)) {
        $reviewReasons[] = 'version-marker';
    }

    return array(
        'reference_year' => $referenceYear,
        'published_at' => $publishedAt,
        'published_year' => $publishedYear,
        'publication_age_years' => $ageYears,
        'explicit_years' => $years,
        'older_year_markers' => $olderYearMarkers,
        'version_markers' => $versions,
        'review_recommended' => !empty($reviewReasons),
        'review_reasons' => $reviewReasons,
    );
}

function HUB_editorialArticleCandidates($pillar, $relations, $limit = 20)
{
    if (!is_array($pillar)
        || !isset($pillar['source_type'], $pillar['source_id'])
        || (string) $pillar['source_type'] !== 'staticpages'
        || !function_exists('HUB_linkAuditArticlesByTopics')
    ) {
        return array();
    }

    $topicContext = HUB_editorialTopicContext($pillar['source_id']);
    if (empty($topicContext['ids'])) {
        return array();
    }

    $existing = array();
    foreach ($relations as $relation) {
        if (is_array($relation)
            && isset($relation['item_type'], $relation['item_id'])
        ) {
            $key = HUB_graphIdentityKey($relation['item_type'], $relation['item_id']);
            if ($key !== '') {
                $existing[$key] = true;
            }
        }
    }

    $candidates = array();

    foreach (HUB_linkAuditArticlesByTopics($topicContext['ids']) as $article) {
        $sid = isset($article['sid']) ? HUB_normalizeObjectId($article['sid']) : '';
        if ($sid === '') {
            continue;
        }

        $key = HUB_graphIdentityKey('article', $sid);
        if ($key === '' || isset($existing[$key])) {
            continue;
        }

        $matchedTopics = array();
        if (!empty($article['hub_topics']) && is_array($article['hub_topics'])) {
            foreach ($article['hub_topics'] as $tid => $label) {
                $matchedTopics[] = array(
                    'id' => (string) $tid,
                    'label' => (string) $label,
                );
            }
        }

        usort($matchedTopics, function ($left, $right) {
            return strcmp((string) $left['id'], (string) $right['id']);
        });

        $hits = isset($article['hits']) ? max(0, (int) $article['hits']) : 0;
        $comments = isset($article['comments']) ? max(0, (int) $article['comments']) : 0;
        $temporal = HUB_editorialTemporalSignals($article);
        $publishedAt = (string) $temporal['published_at'];
        $publishedYear = (int) $temporal['published_year'];

        $evidence = array(
            array(
                'signal' => 'shared-topic',
                'topics' => $matchedTopics,
            ),
            array(
                'signal' => 'engagement',
                'views' => $hits,
                'comments' => $comments,
            ),
        );

        if ($publishedAt !== '') {
            $evidence[] = array(
                'signal' => 'publication-date',
                'published_at' => $publishedAt,
                'published_year' => $publishedYear,
                'publication_age_years' => $temporal['publication_age_years'],
            );
        }

        if (!empty($temporal['explicit_years']) || !empty($temporal['version_markers'])) {
            $evidence[] = array(
                'signal' => 'temporal-marker',
                'explicit_years' => $temporal['explicit_years'],
                'older_year_markers' => $temporal['older_year_markers'],
                'version_markers' => $temporal['version_markers'],
                'review_recommended' => $temporal['review_recommended'],
                'review_reasons' => $temporal['review_reasons'],
            );
        }

        $candidates[] = array(
            'type' => 'article',
            'id' => $sid,
            'title' => isset($article['title']) && (string) $article['title'] !== ''
                ? (string) $article['title']
                : $sid,
            'suggested_role' => 'satellite',
            'score' => count($matchedTopics),
            'ranking' => array(
                'shared_topics' => count($matchedTopics),
                'views' => $hits,
                'comments' => $comments,
                'published_at' => $publishedAt,
                'published_year' => $publishedYear,
                'publication_age_years' => $temporal['publication_age_years'],
                'review_recommended' => $temporal['review_recommended'],
            ),
            'evidence' => $evidence,
        );
    }

    usort($candidates, function ($left, $right) {
        if ((int) $left['score'] !== (int) $right['score']) {
            return (int) $left['score'] > (int) $right['score'] ? -1 : 1;
        }

        $leftViews = isset($left['ranking']['views']) ? (int) $left['ranking']['views'] : 0;
        $rightViews = isset($right['ranking']['views']) ? (int) $right['ranking']['views'] : 0;
        if ($leftViews !== $rightViews) {
            return $leftViews > $rightViews ? -1 : 1;
        }

        $leftComments = isset($left['ranking']['comments']) ? (int) $left['ranking']['comments'] : 0;
        $rightComments = isset($right['ranking']['comments']) ? (int) $right['ranking']['comments'] : 0;
        if ($leftComments !== $rightComments) {
            return $leftComments > $rightComments ? -1 : 1;
        }

        $titleCompare = strcasecmp((string) $left['title'], (string) $right['title']);
        if ($titleCompare !== 0) {
            return $titleCompare;
        }

        return strcmp((string) $left['id'], (string) $right['id']);
    });

    return array_slice($candidates, 0, max(1, (int) $limit));
}

/**
 * Return deterministic relation suggestions for approved Hub pillars.
 *
 * @param int $pillarId 0 for all enabled pillars
 * @param int $limitPerPillar
 * @return array
 */
function HUB_editorialSuggestions($pillarId = 0, $limitPerPillar = 20)
{
    $pillarId = (int) $pillarId;
    $rows = array();

    foreach (HUB_getPillars(false) as $pillar) {
        if (!is_array($pillar) || empty($pillar['id'])) {
            continue;
        }

        $currentPillarId = (int) $pillar['id'];
        if ($pillarId > 0 && $currentPillarId !== $pillarId) {
            continue;
        }

        $relations = HUB_getRelations($currentPillarId, false);
        $candidates = HUB_editorialArticleCandidates($pillar, $relations, $limitPerPillar);

        if (empty($candidates)) {
            continue;
        }

        $rows[] = array(
            'pillar_id' => $currentPillarId,
            'source_type' => (string) $pillar['source_type'],
            'source_id' => (string) $pillar['source_id'],
            'candidates' => $candidates,
        );
    }

    usort($rows, function ($left, $right) {
        if ((int) $left['pillar_id'] === (int) $right['pillar_id']) {
            return 0;
        }

        return (int) $left['pillar_id'] < (int) $right['pillar_id'] ? -1 : 1;
    });

    return array(
        'schema' => 1,
        'generated_from' => array('shared-topic'),
        'ranking_signals' => array('shared-topic-count', 'engagement', 'publication-date'),
        'review_signals' => array('older-year-marker', 'version-marker'),
        'pillar_candidates' => $pillarId > 0
            ? array()
            : HUB_editorialPillarCandidates($limitPerPillar),
        'pillars' => $rows,
    );
}


/**
 * Suggest Static Pages that may deserve promotion to Hub pillars.
 *
 * Evidence is deterministic: the page has one or more specific Geeklog topics
 * and those topics currently contain published articles visible to the caller.
 *
 * @param int $limit
 * @return array
 */
function HUB_editorialPillarCandidates($limit = 10)
{
    if (!function_exists('HUB_linkAuditStaticPages')
        || !function_exists('HUB_linkAuditArticlesByTopics')
    ) {
        return array();
    }

    $existing = array();
    foreach (HUB_getPillars(true) as $pillar) {
        if (is_array($pillar)
            && isset($pillar['source_type'], $pillar['source_id'])
            && (string) $pillar['source_type'] === 'staticpages'
        ) {
            $existing[(string) $pillar['source_id']] = true;
        }
    }

    $candidates = array();

    foreach (HUB_linkAuditStaticPages() as $page) {
        $pageId = isset($page['sp_id']) ? HUB_normalizeObjectId($page['sp_id']) : '';
        if ($pageId === '' || isset($existing[$pageId])) {
            continue;
        }

        $topicContext = HUB_editorialTopicContext($pageId);
        if (empty($topicContext['ids'])) {
            continue;
        }

        $articles = HUB_linkAuditArticlesByTopics($topicContext['ids']);
        if (empty($articles)) {
            continue;
        }

        $topics = array();
        foreach ($topicContext['labels'] as $tid => $label) {
            $topics[] = array(
                'id' => (string) $tid,
                'label' => (string) $label,
            );
        }

        usort($topics, function ($left, $right) {
            return strcmp((string) $left['id'], (string) $right['id']);
        });

        $candidates[] = array(
            'type' => 'staticpages',
            'id' => $pageId,
            'title' => isset($page['sp_title']) && (string) $page['sp_title'] !== ''
                ? (string) $page['sp_title']
                : $pageId,
            'score' => count($articles),
            'evidence' => array(
                array(
                    'signal' => 'shared-topic',
                    'topics' => $topics,
                    'matching_article_count' => count($articles),
                ),
            ),
        );
    }

    usort($candidates, function ($left, $right) {
        if ((int) $left['score'] !== (int) $right['score']) {
            return (int) $left['score'] > (int) $right['score'] ? -1 : 1;
        }

        $titleCompare = strcasecmp((string) $left['title'], (string) $right['title']);
        if ($titleCompare !== 0) {
            return $titleCompare;
        }

        return strcmp((string) $left['id'], (string) $right['id']);
    });

    return array_slice($candidates, 0, max(1, (int) $limit));
}
