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
            'equivalent' => 0,
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
                'equivalent' => 0,
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
            'editorial_role' => isset($pillar['editorial_role'])
                ? HUB_normalizeEditorialRole($pillar['editorial_role'])
                : '',
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
                'editorial_role' => isset($relation['editorial_role'])
                    ? HUB_normalizeEditorialRole($relation['editorial_role'])
                    : '',
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
 * Tokenize a title for deterministic lexical-proximity checks.
 *
 * Tokens shorter than four characters are ignored to reduce generic noise.
 * No language-specific stopword list is used so the rule stays portable and
 * explainable across multilingual sites.
 *
 * @param string $title
 * @return array
 */
function HUB_editorialTitleTokens($title)
{
    $title = html_entity_decode(strip_tags((string) $title), ENT_QUOTES, 'UTF-8');
    $title = function_exists('mb_strtolower')
        ? mb_strtolower($title, 'UTF-8')
        : strtolower($title);

    $parts = preg_split('/[^\p{L}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        return array();
    }

    $tokens = array();
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($part, 'UTF-8')
            : strlen($part);
        if ($length < 4) {
            continue;
        }

        $tokens[$part] = true;
    }

    $tokens = array_keys($tokens);
    sort($tokens, SORT_STRING);

    return $tokens;
}

/**
 * Calculate deterministic Jaccard title-token similarity.
 *
 * @param string $leftTitle
 * @param string $rightTitle
 * @return array
 */
function HUB_editorialTitleSimilarity($leftTitle, $rightTitle)
{
    $left = HUB_editorialTitleTokens($leftTitle);
    $right = HUB_editorialTitleTokens($rightTitle);

    if (empty($left) || empty($right)) {
        return array(
            'similarity' => 0.0,
            'common_tokens' => array(),
            'left_tokens' => $left,
            'right_tokens' => $right,
        );
    }

    $common = array_values(array_intersect($left, $right));
    $union = array_values(array_unique(array_merge($left, $right)));
    sort($common, SORT_STRING);
    sort($union, SORT_STRING);

    $similarity = empty($union) ? 0.0 : count($common) / count($union);

    return array(
        'similarity' => $similarity,
        'common_tokens' => $common,
        'left_tokens' => $left,
        'right_tokens' => $right,
    );
}

/**
 * Detect explainable close-content article pairs inside one Static Page pillar
 * topic context.
 *
 * Eligibility requires at least one shared specific topic, at least two common
 * title tokens and Jaccard title similarity >= 0.50. The result recommends
 * human review only and never implies automatic cannibalization.
 *
 * @param array $pillar
 * @param int $limit
 * @return array
 */
function HUB_editorialCloseContentCandidates($pillar, $limit = 20)
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

    $articles = HUB_linkAuditArticlesByTopics($topicContext['ids']);
    if (count($articles) < 2) {
        return array();
    }

    $pairs = array();
    $pillarId = isset($pillar['id']) ? (int) $pillar['id'] : 0;
    $count = count($articles);

    for ($i = 0; $i < $count - 1; $i++) {
        $left = $articles[$i];
        $leftId = isset($left['sid']) ? HUB_normalizeObjectId($left['sid']) : '';
        if ($leftId === '') {
            continue;
        }

        for ($j = $i + 1; $j < $count; $j++) {
            $right = $articles[$j];
            $rightId = isset($right['sid']) ? HUB_normalizeObjectId($right['sid']) : '';
            if ($rightId === '' || $leftId === $rightId) {
                continue;
            }

            $leftTopics = !empty($left['hub_topics']) && is_array($left['hub_topics'])
                ? array_keys($left['hub_topics'])
                : array();
            $rightTopics = !empty($right['hub_topics']) && is_array($right['hub_topics'])
                ? array_keys($right['hub_topics'])
                : array();
            $sharedTopicIds = array_values(array_intersect($leftTopics, $rightTopics));
            if (empty($sharedTopicIds)) {
                continue;
            }
            sort($sharedTopicIds, SORT_STRING);

            $similarity = HUB_editorialTitleSimilarity(
                isset($left['title']) ? $left['title'] : '',
                isset($right['title']) ? $right['title'] : ''
            );

            if (count($similarity['common_tokens']) < 2 || $similarity['similarity'] < 0.50) {
                continue;
            }

            $pairId = function_exists('HUB_suggestionPairId')
                ? HUB_suggestionPairId('article', $leftId, 'article', $rightId)
                : sha1(min($leftId, $rightId) . "\n" . max($leftId, $rightId));

            if ($pairId === '') {
                continue;
            }

            if (function_exists('HUB_isSuggestionHidden')
                && HUB_isSuggestionHidden('close-content', $pillarId, 'article-pair', $pairId)
            ) {
                continue;
            }

            $sharedTopics = array();
            foreach ($sharedTopicIds as $tid) {
                $label = isset($left['hub_topics'][$tid])
                    ? (string) $left['hub_topics'][$tid]
                    : (isset($right['hub_topics'][$tid]) ? (string) $right['hub_topics'][$tid] : $tid);
                $sharedTopics[] = array('id' => (string) $tid, 'label' => $label);
            }

            $score = (int) round($similarity['similarity'] * 100);

            $pairs[] = array(
                'pair_id' => $pairId,
                'pillar_id' => $pillarId,
                'left' => array(
                    'type' => 'article',
                    'id' => $leftId,
                    'title' => isset($left['title']) ? (string) $left['title'] : $leftId,
                ),
                'right' => array(
                    'type' => 'article',
                    'id' => $rightId,
                    'title' => isset($right['title']) ? (string) $right['title'] : $rightId,
                ),
                'score' => $score,
                'review_recommended' => true,
                'evidence' => array(
                    array(
                        'signal' => 'shared-topic',
                        'topics' => $sharedTopics,
                    ),
                    array(
                        'signal' => 'title-token-overlap',
                        'similarity' => round($similarity['similarity'], 4),
                        'common_tokens' => $similarity['common_tokens'],
                        'left_token_count' => count($similarity['left_tokens']),
                        'right_token_count' => count($similarity['right_tokens']),
                    ),
                ),
            );
        }
    }

    usort($pairs, function ($left, $right) {
        if ((int) $left['score'] !== (int) $right['score']) {
            return (int) $left['score'] > (int) $right['score'] ? -1 : 1;
        }

        return strcmp((string) $left['pair_id'], (string) $right['pair_id']);
    });

    return array_slice($pairs, 0, max(1, (int) $limit));
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

        $pillarId = isset($pillar['id']) ? (int) $pillar['id'] : 0;
        if (function_exists('HUB_isSuggestionHidden')
            && HUB_isSuggestionHidden('relation', $pillarId, 'article', $sid)
        ) {
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
        $closeContent = HUB_editorialCloseContentCandidates($pillar, $limitPerPillar);
        $contentGaps = HUB_editorialContentGaps($pillar, $limitPerPillar);

        if (empty($candidates) && empty($closeContent) && empty($contentGaps)) {
            continue;
        }

        $rows[] = array(
            'pillar_id' => $currentPillarId,
            'source_type' => (string) $pillar['source_type'],
            'source_id' => (string) $pillar['source_id'],
            'candidates' => $candidates,
            'close_content' => $closeContent,
            'content_gaps' => $contentGaps,
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
        'review_signals' => array('older-year-marker', 'version-marker', 'title-token-overlap', 'topic-coverage'),
        'pillar_candidates' => $pillarId > 0
            ? array()
            : HUB_editorialPillarCandidates($limitPerPillar),
        'pillars' => $rows,
    );
}


/**
 * Detect deterministic topic-coverage gaps for Static Page pillars.
 *
 * Zero published article for a specific topic is a create-content opportunity.
 * One published article is a thin-coverage review signal. Existing article
 * candidates remain relation suggestions and are not duplicated here.
 *
 * @param array $pillar
 * @param int $limit
 * @return array
 */
function HUB_editorialContentGaps($pillar, $limit = 20)
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

    $pillarId = isset($pillar['id']) ? (int) $pillar['id'] : 0;
    $gaps = array();

    foreach ($topicContext['ids'] as $topicId) {
        $topicId = (string) $topicId;
        if ($topicId === '') {
            continue;
        }

        $articles = HUB_linkAuditArticlesByTopics(array($topicId));
        $articleCount = count($articles);

        if ($articleCount > 1) {
            continue;
        }

        if (function_exists('HUB_isSuggestionHidden')
            && HUB_isSuggestionHidden('content-gap', $pillarId, 'topic', $topicId)
        ) {
            continue;
        }

        $label = isset($topicContext['labels'][$topicId])
            ? (string) $topicContext['labels'][$topicId]
            : $topicId;

        $gapKind = $articleCount === 0
            ? 'create-content'
            : 'review-thin-coverage';

        $gaps[] = array(
            'pillar_id' => $pillarId,
            'topic_id' => $topicId,
            'topic_label' => $label,
            'kind' => $gapKind,
            'article_count' => $articleCount,
            'priority' => $articleCount === 0 ? 100 : 50,
            'evidence' => array(
                array(
                    'signal' => 'topic-coverage',
                    'topic' => array(
                        'id' => $topicId,
                        'label' => $label,
                    ),
                    'published_article_count' => $articleCount,
                ),
            ),
        );
    }

    usort($gaps, function ($left, $right) {
        if ((int) $left['priority'] !== (int) $right['priority']) {
            return (int) $left['priority'] > (int) $right['priority'] ? -1 : 1;
        }

        return strcmp((string) $left['topic_id'], (string) $right['topic_id']);
    });

    return array_slice($gaps, 0, max(1, (int) $limit));
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

        if (function_exists('HUB_isSuggestionHidden')
            && HUB_isSuggestionHidden('pillar', 0, 'staticpages', $pageId)
        ) {
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


/**
 * Build a deterministic editorial roadmap from Hub-owned structure and
 * explainable 0.8 suggestion signals.
 *
 * SEO/link-health diagnostics deliberately remain outside this model.
 *
 * @param int $candidateLimit
 * @return array
 */
function HUB_editorialRoadmap($candidateLimit = 10)
{
    $candidateLimit = max(1, min(100, (int) $candidateLimit));
    $summary = HUB_editorialSummary(false);
    $inventory = HUB_editorialInventory(false);
    $suggestions = HUB_editorialSuggestions(0, $candidateLimit);

    $suggestionsByPillar = array();
    if (!empty($suggestions['pillars']) && is_array($suggestions['pillars'])) {
        foreach ($suggestions['pillars'] as $row) {
            if (is_array($row) && !empty($row['pillar_id'])) {
                $suggestionsByPillar[(int) $row['pillar_id']] = $row;
            }
        }
    }

    $existingPillars = array();
    $temporalReview = array();
    $closeContentReview = array();
    $contentGaps = array();
    $actions = array();

    $inventoryPillars = isset($inventory['pillars']) && is_array($inventory['pillars'])
        ? $inventory['pillars']
        : array();

    foreach ($inventoryPillars as $pillar) {
        if (!is_array($pillar) || empty($pillar['pillar_id'])) {
            continue;
        }

        $pillarId = (int) $pillar['pillar_id'];
        $candidateRow = isset($suggestionsByPillar[$pillarId])
            ? $suggestionsByPillar[$pillarId]
            : array('candidates' => array());
        $candidates = isset($candidateRow['candidates']) && is_array($candidateRow['candidates'])
            ? $candidateRow['candidates']
            : array();
        $closeContent = isset($candidateRow['close_content']) && is_array($candidateRow['close_content'])
            ? $candidateRow['close_content']
            : array();
        $pillarContentGaps = isset($candidateRow['content_gaps']) && is_array($candidateRow['content_gaps'])
            ? $candidateRow['content_gaps']
            : array();

        $existingPillars[] = array(
            'pillar_id' => $pillarId,
            'source_type' => isset($pillar['source_type']) ? (string) $pillar['source_type'] : '',
            'source_id' => isset($pillar['source_id']) ? (string) $pillar['source_id'] : '',
            'approved_items' => isset($pillar['items']) && is_array($pillar['items'])
                ? count($pillar['items'])
                : 0,
            'strongest_candidates' => array_slice($candidates, 0, 5),
            'close_content_review' => array_slice($closeContent, 0, 5),
            'content_gaps' => array_slice($pillarContentGaps, 0, 5),
        );

        foreach ($pillarContentGaps as $gap) {
            if (!is_array($gap)) {
                continue;
            }

            $contentGaps[] = $gap;
            $actions[] = array(
                'kind' => 'review-content-gap',
                'pillar_id' => $pillarId,
                'type' => 'topic',
                'id' => isset($gap['topic_id']) ? (string) $gap['topic_id'] : '',
                'title' => isset($gap['topic_label']) ? (string) $gap['topic_label'] : '',
                'priority' => isset($gap['priority']) ? (int) $gap['priority'] : 0,
                'reason' => isset($gap['kind']) && $gap['kind'] === 'create-content'
                    ? 'Content gap: no published article for pillar topic'
                    : 'Thin topic coverage: only one published article',
            );
        }

        foreach ($closeContent as $pair) {
            if (!is_array($pair)) {
                continue;
            }

            $closeContentReview[] = $pair;
            $actions[] = array(
                'kind' => 'review-close-content',
                'pillar_id' => $pillarId,
                'type' => 'article-pair',
                'id' => isset($pair['pair_id']) ? (string) $pair['pair_id'] : '',
                'title' => (
                    isset($pair['left']['title']) ? (string) $pair['left']['title'] : ''
                ) . ' ↔ ' . (
                    isset($pair['right']['title']) ? (string) $pair['right']['title'] : ''
                ),
                'priority' => isset($pair['score']) ? (int) $pair['score'] : 0,
                'reason' => 'Close-content review: shared topic + title-token overlap',
            );
        }

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $reviewEvidence = array();
            if (!empty($candidate['evidence']) && is_array($candidate['evidence'])) {
                foreach ($candidate['evidence'] as $evidence) {
                    if (is_array($evidence)
                        && isset($evidence['signal'])
                        && $evidence['signal'] === 'temporal-marker'
                        && !empty($evidence['review_recommended'])
                    ) {
                        $reviewEvidence = $evidence;
                        break;
                    }
                }
            }

            if (!empty($reviewEvidence)) {
                $temporalReview[] = array(
                    'pillar_id' => $pillarId,
                    'type' => isset($candidate['type']) ? (string) $candidate['type'] : '',
                    'id' => isset($candidate['id']) ? (string) $candidate['id'] : '',
                    'title' => isset($candidate['title']) ? (string) $candidate['title'] : '',
                    'review_reasons' => isset($reviewEvidence['review_reasons'])
                        ? $reviewEvidence['review_reasons']
                        : array(),
                    'older_year_markers' => isset($reviewEvidence['older_year_markers'])
                        ? $reviewEvidence['older_year_markers']
                        : array(),
                    'version_markers' => isset($reviewEvidence['version_markers'])
                        ? $reviewEvidence['version_markers']
                        : array(),
                );
            }

            $actions[] = array(
                'kind' => 'review-relation-candidate',
                'pillar_id' => $pillarId,
                'type' => isset($candidate['type']) ? (string) $candidate['type'] : '',
                'id' => isset($candidate['id']) ? (string) $candidate['id'] : '',
                'title' => isset($candidate['title']) ? (string) $candidate['title'] : '',
                'priority' => isset($candidate['score']) ? (int) $candidate['score'] : 0,
                'reason' => 'Shared-topic editorial candidate',
            );
        }
    }

    $pillarCandidates = isset($suggestions['pillar_candidates']) && is_array($suggestions['pillar_candidates'])
        ? $suggestions['pillar_candidates']
        : array();

    foreach ($pillarCandidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $actions[] = array(
            'kind' => 'review-pillar-candidate',
            'pillar_id' => 0,
            'type' => isset($candidate['type']) ? (string) $candidate['type'] : '',
            'id' => isset($candidate['id']) ? (string) $candidate['id'] : '',
            'title' => isset($candidate['title']) ? (string) $candidate['title'] : '',
            'priority' => isset($candidate['score']) ? (int) $candidate['score'] : 0,
            'reason' => 'Static Page with shared-topic article coverage',
        );
    }

    usort($actions, function ($left, $right) {
        if ((int) $left['priority'] !== (int) $right['priority']) {
            return (int) $left['priority'] > (int) $right['priority'] ? -1 : 1;
        }

        $kindCompare = strcmp((string) $left['kind'], (string) $right['kind']);
        if ($kindCompare !== 0) {
            return $kindCompare;
        }

        return strcmp(
            (string) $left['type'] . ':' . (string) $left['id'],
            (string) $right['type'] . ':' . (string) $right['id']
        );
    });

    return array(
        'schema' => 1,
        'scope' => 'editorial-0.8',
        'executive_summary' => array(
            'pillars' => isset($summary['pillars']) ? (int) $summary['pillars'] : 0,
            'approved_relations' => isset($summary['relations']) ? (int) $summary['relations'] : 0,
            'new_pillar_opportunities' => count($pillarCandidates),
            'relation_candidates' => count($actions) - count($pillarCandidates),
            'temporal_review_candidates' => count($temporalReview),
            'close_content_review_pairs' => count($closeContentReview),
            'content_gaps' => count($contentGaps),
        ),
        'existing_pillars' => $existingPillars,
        'new_pillar_opportunities' => $pillarCandidates,
        'content_gaps' => $contentGaps,
        'close_content_review' => $closeContentReview,
        'temporal_review_candidates' => $temporalReview,
        'prioritized_next_actions' => $actions,
        'deferred_diagnostics' => array(
            'missing-reciprocal-links',
            'orphan-content',
            'broken-or-unresolved-relations',
            'canonical-consistency',
            'cluster-health',
        ),
    );
}

/**
 * Render the 0.8 editorial roadmap as portable Markdown.
 *
 * @param array $roadmap
 * @return string
 */
function HUB_editorialRoadmapMarkdown($roadmap)
{
    $roadmap = is_array($roadmap) ? $roadmap : array();
    $summary = isset($roadmap['executive_summary']) && is_array($roadmap['executive_summary'])
        ? $roadmap['executive_summary']
        : array();

    $lines = array(
        '# Editorial roadmap',
        '',
        '## Executive summary',
        '',
        '- Pillars: ' . (isset($summary['pillars']) ? (int) $summary['pillars'] : 0),
        '- Approved relations: ' . (isset($summary['approved_relations']) ? (int) $summary['approved_relations'] : 0),
        '- New pillar opportunities: ' . (isset($summary['new_pillar_opportunities']) ? (int) $summary['new_pillar_opportunities'] : 0),
        '- Relation candidates: ' . (isset($summary['relation_candidates']) ? (int) $summary['relation_candidates'] : 0),
        '- Temporal review candidates: ' . (isset($summary['temporal_review_candidates']) ? (int) $summary['temporal_review_candidates'] : 0),
        '- Close-content review pairs: ' . (isset($summary['close_content_review_pairs']) ? (int) $summary['close_content_review_pairs'] : 0),
        '- Content gaps: ' . (isset($summary['content_gaps']) ? (int) $summary['content_gaps'] : 0),
        '',
        '## Existing pillars',
        '',
    );

    $pillars = isset($roadmap['existing_pillars']) && is_array($roadmap['existing_pillars'])
        ? $roadmap['existing_pillars']
        : array();

    if (empty($pillars)) {
        $lines[] = '_No enabled pillar._';
    } else {
        foreach ($pillars as $pillar) {
            $identity = (isset($pillar['source_type']) ? $pillar['source_type'] : '')
                . ':' . (isset($pillar['source_id']) ? $pillar['source_id'] : '');
            $lines[] = '### ' . $identity;
            $lines[] = '';
            $lines[] = '- Approved items: ' . (isset($pillar['approved_items']) ? (int) $pillar['approved_items'] : 0);

            $candidates = isset($pillar['strongest_candidates']) && is_array($pillar['strongest_candidates'])
                ? $pillar['strongest_candidates']
                : array();
            if (!empty($candidates)) {
                $lines[] = '- Strongest candidates:';
                foreach ($candidates as $candidate) {
                    $candidateIdentity = (isset($candidate['type']) ? $candidate['type'] : '')
                        . ':' . (isset($candidate['id']) ? $candidate['id'] : '');
                    $title = isset($candidate['title']) && (string) $candidate['title'] !== ''
                        ? ' — ' . $candidate['title']
                        : '';
                    $lines[] = '  - ' . $candidateIdentity . $title
                        . ' (score ' . (isset($candidate['score']) ? (int) $candidate['score'] : 0) . ')';
                }
            }
            $lines[] = '';
        }
    }

    $lines[] = '## New pillar opportunities';
    $lines[] = '';
    $pillarCandidates = isset($roadmap['new_pillar_opportunities']) && is_array($roadmap['new_pillar_opportunities'])
        ? $roadmap['new_pillar_opportunities']
        : array();
    if (empty($pillarCandidates)) {
        $lines[] = '_None detected._';
    } else {
        foreach ($pillarCandidates as $candidate) {
            $identity = (isset($candidate['type']) ? $candidate['type'] : '')
                . ':' . (isset($candidate['id']) ? $candidate['id'] : '');
            $lines[] = '- ' . $identity . ' — '
                . (isset($candidate['title']) ? $candidate['title'] : '')
                . ' (score ' . (isset($candidate['score']) ? (int) $candidate['score'] : 0) . ')';
        }
    }

    $lines[] = '';
    $lines[] = '## Content gaps to create or refresh';
    $lines[] = '';
    $gaps = isset($roadmap['content_gaps']) && is_array($roadmap['content_gaps'])
        ? $roadmap['content_gaps']
        : array();
    if (empty($gaps)) {
        $lines[] = '_None detected._';
    } else {
        foreach ($gaps as $gap) {
            $kind = isset($gap['kind']) ? (string) $gap['kind'] : '';
            $topicLabel = isset($gap['topic_label']) ? (string) $gap['topic_label'] : '';
            $articleCount = isset($gap['article_count']) ? (int) $gap['article_count'] : 0;
            $lines[] = '- ' . $topicLabel . ' — ' . $kind
                . ' (' . $articleCount . ' published article'
                . ($articleCount === 1 ? '' : 's') . ')';
        }
    }

    $lines[] = '';
    $lines[] = '## Potential cannibalization / close-content review';
    $lines[] = '';
    $closePairs = isset($roadmap['close_content_review']) && is_array($roadmap['close_content_review'])
        ? $roadmap['close_content_review']
        : array();
    if (empty($closePairs)) {
        $lines[] = '_None detected._';
    } else {
        foreach ($closePairs as $pair) {
            $left = isset($pair['left']) && is_array($pair['left']) ? $pair['left'] : array();
            $right = isset($pair['right']) && is_array($pair['right']) ? $pair['right'] : array();
            $leftIdentity = (isset($left['type']) ? $left['type'] : '')
                . ':' . (isset($left['id']) ? $left['id'] : '');
            $rightIdentity = (isset($right['type']) ? $right['type'] : '')
                . ':' . (isset($right['id']) ? $right['id'] : '');
            $lines[] = '- ' . $leftIdentity . ' ↔ ' . $rightIdentity
                . ' (title similarity ' . (isset($pair['score']) ? (int) $pair['score'] : 0) . '%)';
        }
    }

    $lines[] = '';
    $lines[] = '## Temporal review candidates';
    $lines[] = '';
    $temporal = isset($roadmap['temporal_review_candidates']) && is_array($roadmap['temporal_review_candidates'])
        ? $roadmap['temporal_review_candidates']
        : array();
    if (empty($temporal)) {
        $lines[] = '_None detected._';
    } else {
        foreach ($temporal as $item) {
            $identity = (isset($item['type']) ? $item['type'] : '')
                . ':' . (isset($item['id']) ? $item['id'] : '');
            $reasons = isset($item['review_reasons']) && is_array($item['review_reasons'])
                ? implode(', ', $item['review_reasons'])
                : '';
            $lines[] = '- ' . $identity . ' — '
                . (isset($item['title']) ? $item['title'] : '')
                . ($reasons !== '' ? ' (' . $reasons . ')' : '');
        }
    }

    $lines[] = '';
    $lines[] = '## Prioritized next actions';
    $lines[] = '';
    $actions = isset($roadmap['prioritized_next_actions']) && is_array($roadmap['prioritized_next_actions'])
        ? $roadmap['prioritized_next_actions']
        : array();
    if (empty($actions)) {
        $lines[] = '_No editorial action suggested._';
    } else {
        foreach ($actions as $action) {
            $identity = (isset($action['type']) ? $action['type'] : '')
                . ':' . (isset($action['id']) ? $action['id'] : '');
            $lines[] = '- [' . (isset($action['priority']) ? (int) $action['priority'] : 0) . '] '
                . (isset($action['reason']) ? $action['reason'] : '')
                . ' — ' . $identity;
        }
    }

    $lines[] = '';
    $lines[] = '## Deferred diagnostics';
    $lines[] = '';
    $lines[] = 'The following belong to Hub 0.9.0 and are not inferred by this 0.8 roadmap:';
    $deferred = isset($roadmap['deferred_diagnostics']) && is_array($roadmap['deferred_diagnostics'])
        ? $roadmap['deferred_diagnostics']
        : array();
    foreach ($deferred as $item) {
        $lines[] = '- ' . $item;
    }

    return implode("\\n", $lines) . "\\n";
}
