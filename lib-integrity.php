<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Resolve public pages affected by one content identity.
 *
 * The dependency graph remains owned by HUB_getAffectedContexts(); this helper
 * only enriches those contexts with current public resolution diagnostics.
 *
 * @param string $type
 * @param string $id
 * @param bool $includeDisabled
 * @return array
 */
function HUB_integrityAffectedPages($type, $id, $includeDisabled = false)
{
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);

    $result = array(
        'object' => array('type' => $type, 'id' => $id),
        'context_count' => 0,
        'public_page_count' => 0,
        'unresolved_page_count' => 0,
        'contexts' => array(),
    );

    if ($type === '' || $id === '' || !function_exists('HUB_getAffectedContexts')) {
        return $result;
    }

    $contexts = HUB_getAffectedContexts($type, $id, $includeDisabled);
    $result['context_count'] = count($contexts);

    foreach ($contexts as $context) {
        if (!is_array($context)) {
            continue;
        }

        $sourceType = isset($context['source_type'])
            ? HUB_normalizeObjectType($context['source_type'])
            : '';
        $sourceId = isset($context['source_id'])
            ? HUB_normalizeObjectId($context['source_id'])
            : '';

        $resolved = HUB_resolveObject($sourceType, $sourceId, 0);
        $public = !empty($resolved['exists']) && !empty($resolved['url']);

        if ($public) {
            $result['public_page_count']++;
        } else {
            $result['unresolved_page_count']++;
        }

        $reasons = isset($context['reasons']) && is_array($context['reasons'])
            ? array_values(array_unique(array_map('strval', $context['reasons'])))
            : array();
        sort($reasons, SORT_STRING);

        $result['contexts'][] = array(
            'pillar_id' => isset($context['pillar_id']) ? (int) $context['pillar_id'] : 0,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reasons' => $reasons,
            'public_resolved' => $public,
            'title' => isset($resolved['title']) ? (string) $resolved['title'] : '',
            'url' => isset($resolved['url']) ? (string) $resolved['url'] : '',
            'diagnostic' => $public
                ? ''
                : (isset($resolved['diagnostic']) ? (string) $resolved['diagnostic'] : ''),
        );
    }

    usort($result['contexts'], function ($left, $right) {
        $pillarLeft = isset($left['pillar_id']) ? (int) $left['pillar_id'] : 0;
        $pillarRight = isset($right['pillar_id']) ? (int) $right['pillar_id'] : 0;
        if ($pillarLeft === $pillarRight) {
            return 0;
        }

        return $pillarLeft < $pillarRight ? -1 : 1;
    });

    return $result;
}

/**
 * Classify backlink diagnostic evidence without claiming runtime rendering that
 * Hub cannot verify.
 *
 * @param string $itemType
 * @return array
 */
function HUB_integrityBacklinkEvidence($itemType)
{
    $status = function_exists('HUB_backlinkIntegrationStatus')
        ? HUB_backlinkIntegrationStatus($itemType)
        : array();

    $supported = !empty($status['supported']);
    $mode = isset($status['mode']) ? (string) $status['mode'] : 'unconfirmed';

    $verification = 'unconfirmed';
    if ($supported && $mode === 'core-template-fallback') {
        $verification = 'hub-managed';
    } elseif ($supported && $mode === 'staticpage-template-hook') {
        // This hook guarantees Hub's Static Page pillar rendering, not a
        // reciprocal backlink when a Static Page is used as a satellite.
        $verification = 'unconfirmed';
    } elseif ($supported) {
        $verification = 'integration-available';
    }

    return array(
        'supported' => $supported,
        'mode' => $mode,
        'verification' => $verification,
        'label' => isset($status['label']) ? (string) $status['label'] : '',
        'detail' => isset($status['detail']) ? (string) $status['detail'] : '',
    );
}

/**
 * Verify reciprocal-link evidence for one approved relation.
 *
 * Core articles are the only current provider type for which Hub both builds
 * the backlink fragment and owns the full public placement path. Generic
 * providers may expose a usable fragment while runtime placement remains
 * unverified.
 *
 * @param array $pillar
 * @param array $relation
 * @param int $uid
 * @return array
 */
function HUB_integrityReciprocalEvidence($pillar, $relation, $uid = 0)
{
    $result = array(
        'status' => 'unconfirmed',
        'fragment_available' => false,
        'runtime_verified' => false,
        'pillar_url' => '',
        'detail' => '',
    );

    if (!is_array($pillar) || !is_array($relation)
        || empty($relation['item_type']) || empty($relation['item_id'])
    ) {
        $result['detail'] = 'Missing pillar or relation identity.';
        return $result;
    }

    $pillarResolved = HUB_resolveObject(
        isset($pillar['source_type']) ? $pillar['source_type'] : '',
        isset($pillar['source_id']) ? $pillar['source_id'] : '',
        $uid
    );
    $pillarUrl = isset($pillarResolved['url']) ? (string) $pillarResolved['url'] : '';
    $result['pillar_url'] = $pillarUrl;

    if (empty($pillarResolved['exists']) || $pillarUrl === '') {
        $result['status'] = 'pillar-unresolved';
        $result['detail'] = 'The pillar target cannot be resolved to a public URL.';
        return $result;
    }

    $type = HUB_normalizeObjectType($relation['item_type']);
    $id = HUB_normalizeObjectId($relation['item_id']);
    $target = HUB_resolveObject($type, $id, $uid);
    if (empty($target['exists'])) {
        $result['status'] = 'target-unresolved';
        $result['detail'] = 'The related object cannot be resolved.';
        return $result;
    }

    $fragment = function_exists('HUB_renderItemPillarBacklinks')
        ? HUB_renderItemPillarBacklinks($type, $id)
        : '';
    $escapedPillarUrl = htmlspecialchars($pillarUrl, ENT_QUOTES, 'UTF-8');
    $fragmentHasExpectedLink = $fragment !== ''
        && (strpos($fragment, $escapedPillarUrl) !== false
            || strpos($fragment, $pillarUrl) !== false);

    $result['fragment_available'] = $fragmentHasExpectedLink;

    $integration = HUB_integrityBacklinkEvidence($type);
    if ($type === 'article'
        && isset($integration['verification'])
        && $integration['verification'] === 'hub-managed'
        && $fragmentHasExpectedLink
    ) {
        $result['status'] = 'hub-rendered';
        $result['runtime_verified'] = true;
        $result['detail'] = 'Hub generates the expected pillar backlink and owns the Core article placement path.';
        return $result;
    }

    if ($fragmentHasExpectedLink && !empty($integration['supported'])) {
        $result['status'] = 'fragment-available-runtime-unverified';
        $result['detail'] = 'Hub can generate the expected backlink fragment, but provider runtime placement is not verified.';
        return $result;
    }

    if (!empty($integration['supported'])) {
        $result['status'] = 'integration-available-unverified';
        $result['detail'] = 'A backlink integration path is declared, but the expected rendered backlink is not verifiable here.';
        return $result;
    }

    $result['detail'] = 'No confirmed generic reciprocal-link placement path is available.';
    return $result;
}

/**
 * Describe one administrator-approved equivalent-content relation.
 *
 * Equivalence itself is never inferred here. This helper only describes
 * site/language evidence around a relation already marked "equivalent".
 *
 * @param array $sourceLanguage
 * @param array $targetLanguage
 * @param array $targetSite
 * @return array
 */
function HUB_integrityEquivalenceContext($sourceLanguage, $targetLanguage, $targetSite)
{
    $sourceLanguage = is_array($sourceLanguage) ? $sourceLanguage : array();
    $targetLanguage = is_array($targetLanguage) ? $targetLanguage : array();
    $targetSite = is_array($targetSite) ? $targetSite : array();

    $sourceKnown = !empty($sourceLanguage['object_language_known'])
        && !empty($sourceLanguage['object_language']);
    $targetKnown = !empty($targetLanguage['object_language_known'])
        && !empty($targetLanguage['object_language']);

    if ($sourceKnown && $targetKnown) {
        $sourceValue = strtolower(trim((string) $sourceLanguage['object_language']));
        $targetValue = strtolower(trim((string) $targetLanguage['object_language']));
        $languageStatus = $sourceValue === $targetValue
            ? 'same-language-review'
            : 'cross-language';
    } else {
        $languageStatus = 'language-unverified';
    }

    if (!empty($targetSite['cross_site'])) {
        $siteStatus = 'cross-site';
    } elseif (!empty($targetSite['current_site'])) {
        $siteStatus = 'current-site';
    } else {
        $siteStatus = 'unknown';
    }

    return array(
        'approved_equivalence' => true,
        'language_status' => $languageStatus,
        'site_status' => $siteStatus,
        'source_language' => $sourceKnown ? (string) $sourceLanguage['object_language'] : '',
        'target_language' => $targetKnown ? (string) $targetLanguage['object_language'] : '',
    );
}

/**
 * Build deterministic integrity diagnostics for one approved Hub pillar.
 *
 * This verifies stable identities and resolvability through Geeklog contracts.
 * Backlink evidence reports integration capability only unless Hub owns the
 * rendering path itself.
 *
 * @param array $pillar
 * @param int $uid
 * @return array
 */
function HUB_integrityPillar($pillar, $uid = 0)
{
    if (!is_array($pillar) || empty($pillar['id'])) {
        return array();
    }

    $pillarId = (int) $pillar['id'];
    $sourceType = isset($pillar['source_type']) ? HUB_normalizeObjectType($pillar['source_type']) : '';
    $sourceId = isset($pillar['source_id']) ? HUB_normalizeObjectId($pillar['source_id']) : '';

    $source = HUB_resolveObject($sourceType, $sourceId, $uid);
    $relations = HUB_getRelations($pillarId, false);

    $result = array(
        'pillar_id' => $pillarId,
        'source' => array(
            'type' => $sourceType,
            'id' => $sourceId,
            'editorial_role' => isset($pillar['editorial_role'])
                ? HUB_normalizeEditorialRole($pillar['editorial_role'])
                : '',
            'resolved' => !empty($source['exists']),
            'renderable' => !empty($source['exists']) && !empty($source['url']),
            'title' => isset($source['title']) ? (string) $source['title'] : '',
            'url' => isset($source['url']) ? (string) $source['url'] : '',
            'diagnostic' => isset($source['diagnostic']) ? (string) $source['diagnostic'] : '',
            'site_context' => function_exists('HUB_siteUrlContext')
                ? HUB_siteUrlContext(isset($source['url']) ? (string) $source['url'] : '')
                : array(),
            'language_context' => function_exists('HUB_objectLanguageContext')
                ? HUB_objectLanguageContext($sourceType, $sourceId, $uid)
                : array(),
        ),
        'relation_count' => count($relations),
        'resolved_relations' => 0,
        'unresolved_relations' => 0,
        'renderable_relations' => 0,
        'non_renderable_relations' => 0,
        'cross_site_relations' => 0,
        'current_site_relations' => 0,
        'unknown_site_relations' => 0,
        'equivalents' => array(
            'total' => 0,
            'cross_language' => 0,
            'same_language_review' => 0,
            'language_unverified' => 0,
            'cross_site' => 0,
        ),
        'backlink' => array(
            'hub_managed' => 0,
            'integration_available' => 0,
            'unconfirmed' => 0,
        ),
        'reciprocal' => array(
            'hub_rendered' => 0,
            'fragment_available_runtime_unverified' => 0,
            'integration_available_unverified' => 0,
            'unconfirmed' => 0,
        ),
        'providers' => array(),
        'relations' => array(),
    );

    foreach ($relations as $relation) {
        if (!is_array($relation)) {
            continue;
        }

        $type = isset($relation['item_type']) ? HUB_normalizeObjectType($relation['item_type']) : '';
        $id = isset($relation['item_id']) ? HUB_normalizeObjectId($relation['item_id']) : '';
        $resolved = HUB_resolveObject($type, $id, $uid);
        $exists = !empty($resolved['exists']);
        $renderable = $exists && !empty($resolved['url']);
        $backlink = HUB_integrityBacklinkEvidence($type);
        $reciprocal = HUB_integrityReciprocalEvidence($pillar, $relation, $uid);
        $targetUrl = isset($resolved['url']) ? (string) $resolved['url'] : '';
        $siteUrlContext = function_exists('HUB_siteUrlContext')
            ? HUB_siteUrlContext($targetUrl)
            : array();
        $languageContext = function_exists('HUB_objectLanguageContext')
            ? HUB_objectLanguageContext($type, $id, $uid)
            : array();

        if (!empty($siteUrlContext['cross_site'])) {
            $result['cross_site_relations']++;
        } elseif (!empty($siteUrlContext['current_site'])) {
            $result['current_site_relations']++;
        } else {
            $result['unknown_site_relations']++;
        }

        $relationRole = isset($relation['relation_role'])
            ? HUB_normalizeRelationRole($relation['relation_role'])
            : 'related';
        $equivalenceContext = array();

        if ($relationRole === 'equivalent') {
            $equivalenceContext = HUB_integrityEquivalenceContext(
                isset($result['source']['language_context']) ? $result['source']['language_context'] : array(),
                $languageContext,
                $siteUrlContext
            );
            $result['equivalents']['total']++;

            if ($equivalenceContext['language_status'] === 'cross-language') {
                $result['equivalents']['cross_language']++;
            } elseif ($equivalenceContext['language_status'] === 'same-language-review') {
                $result['equivalents']['same_language_review']++;
            } else {
                $result['equivalents']['language_unverified']++;
            }

            if ($equivalenceContext['site_status'] === 'cross-site') {
                $result['equivalents']['cross_site']++;
            }
        }

        if ($exists) {
            $result['resolved_relations']++;
        } else {
            $result['unresolved_relations']++;
        }

        if ($renderable) {
            $result['renderable_relations']++;
        } else {
            $result['non_renderable_relations']++;
        }

        if (!isset($result['providers'][$type])) {
            $result['providers'][$type] = array(
                'relations' => 0,
                'resolved' => 0,
                'unresolved' => 0,
            );
        }
        $result['providers'][$type]['relations']++;
        if ($exists) {
            $result['providers'][$type]['resolved']++;
        } else {
            $result['providers'][$type]['unresolved']++;
        }

        $verification = isset($backlink['verification'])
            ? (string) $backlink['verification']
            : 'unconfirmed';
        if ($verification === 'hub-managed') {
            $result['backlink']['hub_managed']++;
        } elseif ($verification === 'integration-available') {
            $result['backlink']['integration_available']++;
        } else {
            $result['backlink']['unconfirmed']++;
        }

        $reciprocalStatus = isset($reciprocal['status']) ? (string) $reciprocal['status'] : 'unconfirmed';
        if ($reciprocalStatus === 'hub-rendered') {
            $result['reciprocal']['hub_rendered']++;
        } elseif ($reciprocalStatus === 'fragment-available-runtime-unverified') {
            $result['reciprocal']['fragment_available_runtime_unverified']++;
        } elseif ($reciprocalStatus === 'integration-available-unverified') {
            $result['reciprocal']['integration_available_unverified']++;
        } else {
            $result['reciprocal']['unconfirmed']++;
        }

        $result['relations'][] = array(
            'type' => $type,
            'id' => $id,
            'relation_role' => $relationRole,
            'editorial_role' => isset($relation['editorial_role'])
                ? HUB_normalizeEditorialRole($relation['editorial_role'])
                : '',
            'resolved' => $exists,
            'renderable_from_pillar' => $renderable,
            'title' => isset($resolved['title']) ? (string) $resolved['title'] : '',
            'url' => $targetUrl,
            'site_context' => $siteUrlContext,
            'language_context' => $languageContext,
            'equivalence_context' => $equivalenceContext,
            'diagnostic' => $renderable ? '' : (isset($resolved['diagnostic']) ? (string) $resolved['diagnostic'] : ''),
            'backlink_evidence' => $backlink,
            'reciprocal_evidence' => $reciprocal,
        );
    }

    ksort($result['providers'], SORT_STRING);

    $issues = array();
    $status = 'healthy';

    if (empty($result['source']['resolved'])) {
        $issues[] = array(
            'code' => 'pillar-source-unresolved',
            'severity' => 'broken',
            'count' => 1,
        );
        $status = 'broken';
    }

    if ($result['unresolved_relations'] > 0) {
        $issues[] = array(
            'code' => 'relation-target-unresolved',
            'severity' => 'broken',
            'count' => (int) $result['unresolved_relations'],
        );
        $status = 'broken';
    }

    if ($result['non_renderable_relations'] > $result['unresolved_relations']) {
        $issues[] = array(
            'code' => 'relation-target-non-renderable',
            'severity' => 'attention',
            'count' => (int) ($result['non_renderable_relations'] - $result['unresolved_relations']),
        );
        if ($status === 'healthy') {
            $status = 'attention';
        }
    }

    $runtimeUnverified = (int) $result['reciprocal']['fragment_available_runtime_unverified']
        + (int) $result['reciprocal']['integration_available_unverified']
        + (int) $result['reciprocal']['unconfirmed'];

    if ($runtimeUnverified > 0) {
        $issues[] = array(
            'code' => 'reciprocal-link-runtime-unverified',
            'severity' => 'attention',
            'count' => $runtimeUnverified,
        );
        if ($status === 'healthy') {
            $status = 'attention';
        }
    }

    if ($result['relation_count'] === 0) {
        $issues[] = array(
            'code' => 'pillar-without-relations',
            'severity' => 'attention',
            'count' => 1,
        );
        if ($status === 'healthy') {
            $status = 'attention';
        }
    }

    $equivalenceReviewCount = (int) $result['equivalents']['same_language_review']
        + (int) $result['equivalents']['language_unverified'];

    if ($equivalenceReviewCount > 0) {
        $issues[] = array(
            'code' => 'equivalence-review',
            'severity' => 'attention',
            'count' => $equivalenceReviewCount,
        );
        if ($status === 'healthy') {
            $status = 'attention';
        }
    }

    $result['health'] = array(
        'status' => $status,
        'source_resolved' => !empty($result['source']['resolved']),
        'all_relations_resolved' => $result['unresolved_relations'] === 0,
        'all_relations_renderable' => $result['non_renderable_relations'] === 0,
        'backlink_integration_unconfirmed' => $result['backlink']['unconfirmed'],
        'issues' => $issues,
    );

    return $result;
}

/**
 * Normalize one resolved public URL for duplicate/canonical consistency checks.
 *
 * @param string $url
 * @return string
 */
function HUB_integrityCanonicalUrlKey($url)
{
    $url = trim(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return strtolower(rtrim($url, '/'));
    }

    $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
    $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }

    $path = isset($parts['path']) ? preg_replace('#/+#', '/', (string) $parts['path']) : '';
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    $query = array();
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
        ksort($query);
    }

    // Treat http/https as the same public destination for consistency review.
    $base = $host !== '' ? $host . $path : $path;
    if ($host === '' && $scheme !== '') {
        $base = $scheme . ':' . $base;
    }

    return $base . (empty($query) ? '' : '?' . http_build_query($query, '', '&'));
}

/**
 * Inspect Hub-owned graph structure independently from provider content.
 *
 * Multi-parent participation is informational and valid. Self-relations and
 * cycles are review signals because they can produce confusing editorial
 * navigation even though traversal remains cycle-safe.
 *
 * @return array
 */
function HUB_integrityGraphDiagnostics()
{
    $pillars = HUB_getPillars(false);
    $pillarByIdentity = array();
    $pillarById = array();

    foreach ($pillars as $pillar) {
        if (!is_array($pillar) || empty($pillar['id'])) {
            continue;
        }

        $key = HUB_graphIdentityKey(
            isset($pillar['source_type']) ? $pillar['source_type'] : '',
            isset($pillar['source_id']) ? $pillar['source_id'] : ''
        );
        if ($key === '') {
            continue;
        }

        $pillarByIdentity[$key] = (int) $pillar['id'];
        $pillarById[(int) $pillar['id']] = $pillar;
    }

    $edges = array();
    $parentCounts = array();
    $selfRelations = array();

    foreach ($pillarById as $pillarId => $pillar) {
        $sourceKey = HUB_graphIdentityKey($pillar['source_type'], $pillar['source_id']);
        foreach (HUB_getRelations($pillarId, false) as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $targetKey = HUB_graphIdentityKey(
                isset($relation['item_type']) ? $relation['item_type'] : '',
                isset($relation['item_id']) ? $relation['item_id'] : ''
            );
            if ($targetKey === '') {
                continue;
            }

            if (!isset($parentCounts[$targetKey])) {
                $parentCounts[$targetKey] = array();
            }
            $parentCounts[$targetKey][$pillarId] = true;

            if ($targetKey === $sourceKey) {
                $selfRelations[] = array(
                    'pillar_id' => $pillarId,
                    'identity' => $sourceKey,
                    'relation_id' => isset($relation['id']) ? (int) $relation['id'] : 0,
                );

                // Self-relations have their own diagnostic category and must
                // not also be counted as one-node cycles.
                continue;
            }

            if (isset($pillarByIdentity[$targetKey])) {
                if (!isset($edges[$sourceKey])) {
                    $edges[$sourceKey] = array();
                }
                $edges[$sourceKey][$targetKey] = true;
            }
        }
    }

    $multiParent = array();
    foreach ($parentCounts as $identity => $parents) {
        if (count($parents) > 1) {
            $multiParent[] = array(
                'identity' => $identity,
                'parent_pillar_ids' => array_map('intval', array_keys($parents)),
            );
        }
    }

    $cycles = array();
    $visiting = array();
    $visited = array();
    $stack = array();

    $walk = function ($node) use (&$walk, &$edges, &$visiting, &$visited, &$stack, &$cycles) {
        if (isset($visited[$node])) {
            return;
        }

        $visiting[$node] = true;
        $stack[] = $node;

        $targets = isset($edges[$node]) ? array_keys($edges[$node]) : array();
        sort($targets, SORT_STRING);

        foreach ($targets as $target) {
            if (isset($visiting[$target])) {
                $start = array_search($target, $stack, true);
                if ($start !== false) {
                    $cycle = array_slice($stack, $start);
                    $cycle[] = $target;

                    $members = array_slice($cycle, 0, -1);
                    sort($members, SORT_STRING);
                    $cycleKey = implode('|', $members);
                    $cycles[$cycleKey] = $cycle;
                }
                continue;
            }

            if (!isset($visited[$target])) {
                $walk($target);
            }
        }

        array_pop($stack);
        unset($visiting[$node]);
        $visited[$node] = true;
    };

    $nodes = array_keys($pillarByIdentity);
    sort($nodes, SORT_STRING);
    foreach ($nodes as $node) {
        $walk($node);
    }

    ksort($cycles, SORT_STRING);

    return array(
        'self_relations' => $selfRelations,
        'cycles' => array_values($cycles),
        'multi_parent_items' => $multiParent,
        'counts' => array(
            'self_relations' => count($selfRelations),
            'cycles' => count($cycles),
            'multi_parent_items' => count($multiParent),
        ),
    );
}

/**
 * Detect multiple Hub identities resolving to the same public URL.
 *
 * @param int $uid
 * @return array
 */
function HUB_integrityCanonicalCollisions($uid = 0)
{
    $byUrl = array();

    foreach (HUB_getPillars(false) as $pillar) {
        if (!is_array($pillar) || empty($pillar['id'])) {
            continue;
        }

        $objects = array(array(
            'type' => isset($pillar['source_type']) ? $pillar['source_type'] : '',
            'id' => isset($pillar['source_id']) ? $pillar['source_id'] : '',
        ));

        foreach (HUB_getRelations((int) $pillar['id'], false) as $relation) {
            if (is_array($relation)) {
                $objects[] = array(
                    'type' => isset($relation['item_type']) ? $relation['item_type'] : '',
                    'id' => isset($relation['item_id']) ? $relation['item_id'] : '',
                );
            }
        }

        foreach ($objects as $object) {
            $type = HUB_normalizeObjectType($object['type']);
            $id = HUB_normalizeObjectId($object['id']);
            $resolved = HUB_resolveObject($type, $id, $uid);
            if (empty($resolved['exists']) || empty($resolved['url'])) {
                continue;
            }

            $urlKey = HUB_integrityCanonicalUrlKey($resolved['url']);
            if ($urlKey === '') {
                continue;
            }

            $identity = $type . ':' . $id;
            if (!isset($byUrl[$urlKey])) {
                $byUrl[$urlKey] = array();
            }
            $byUrl[$urlKey][$identity] = array(
                'type' => $type,
                'id' => $id,
                'url' => (string) $resolved['url'],
            );
        }
    }

    $collisions = array();
    foreach ($byUrl as $urlKey => $identities) {
        if (count($identities) < 2) {
            continue;
        }

        ksort($identities, SORT_STRING);
        $collisions[] = array(
            'url_key' => $urlKey,
            'identities' => array_values($identities),
        );
    }

    usort($collisions, function ($left, $right) {
        return strcmp((string) $left['url_key'], (string) $right['url_key']);
    });

    return $collisions;
}

/**
 * List provider-owned content that is visible through the shared collection
 * contract but not currently present in the enabled Hub graph.
 *
 * This is deliberately named "hub-unconnected", not "orphan": Hub does not
 * know every hyperlink or navigation path on the site and therefore cannot
 * infer global SEO orphan status from its relationship graph alone.
 *
 * Core article SQL helpers are intentionally not used here. Providers only
 * participate when the shared public collection contract is available.
 *
 * @param int $limitPerProvider
 * @return array
 */
function HUB_integrityUnconnectedContent($limitPerProvider = 100)
{
    $limitPerProvider = max(1, min(200, (int) $limitPerProvider));

    $connected = array();
    foreach (HUB_getPillars(false) as $pillar) {
        if (!is_array($pillar) || empty($pillar['id'])) {
            continue;
        }

        $sourceKey = HUB_graphIdentityKey(
            isset($pillar['source_type']) ? $pillar['source_type'] : '',
            isset($pillar['source_id']) ? $pillar['source_id'] : ''
        );
        if ($sourceKey !== '') {
            $connected[$sourceKey] = true;
        }

        foreach (HUB_getRelations((int) $pillar['id'], false) as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $key = HUB_graphIdentityKey(
                isset($relation['item_type']) ? $relation['item_type'] : '',
                isset($relation['item_id']) ? $relation['item_id'] : ''
            );
            if ($key !== '') {
                $connected[$key] = true;
            }
        }
    }

    $providers = array();
    $total = 0;

    $types = function_exists('HUB_relationObjectTypes')
        ? HUB_relationObjectTypes()
        : array();

    foreach ($types as $type) {
        $type = HUB_normalizeObjectType($type);
        if ($type === '') {
            continue;
        }

        // Do not use Core article SQL discovery for this cross-provider
        // diagnostic. Only the shared collection contract qualifies.
        if (!function_exists('HUB_relationCollectionSupported')
            || !HUB_relationCollectionSupported($type)
        ) {
            continue;
        }

        $collection = HUB_relationObjectOptions($type, $limitPerProvider);
        if (empty($collection['supported'])) {
            continue;
        }

        $items = isset($collection['items']) && is_array($collection['items'])
            ? $collection['items']
            : array();

        $unconnected = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? HUB_normalizeObjectId($item['id']) : '';
            if ($id === '') {
                continue;
            }

            $key = HUB_graphIdentityKey($type, $id);
            if ($key === '' || isset($connected[$key])) {
                continue;
            }

            $unconnected[] = array(
                'type' => $type,
                'id' => $id,
                'title' => isset($item['title']) ? (string) $item['title'] : $id,
                'url' => isset($item['url']) ? (string) $item['url'] : '',
                'status' => 'hub-unconnected',
                'evidence' => array(
                    'signal' => 'content.collection',
                    'graph_membership' => 'absent',
                ),
            );
        }

        if (empty($unconnected)) {
            continue;
        }

        usort($unconnected, function ($left, $right) {
            $titleCmp = strcasecmp(
                isset($left['title']) ? (string) $left['title'] : '',
                isset($right['title']) ? (string) $right['title'] : ''
            );
            if ($titleCmp !== 0) {
                return $titleCmp;
            }

            return strcmp(
                isset($left['id']) ? (string) $left['id'] : '',
                isset($right['id']) ? (string) $right['id'] : ''
            );
        });

        $providers[$type] = array(
            'status' => 'collection-reviewed',
            'collection_limit' => $limitPerProvider,
            'returned_items' => count($items),
            'hub_unconnected_count' => count($unconnected),
            'possibly_truncated' => count($items) >= $limitPerProvider,
            'items' => $unconnected,
        );
        $total += count($unconnected);
    }

    ksort($providers, SORT_STRING);

    return array(
        'status' => 'hub-unconnected',
        'scope' => 'shared-content-collections-only',
        'limit_per_provider' => $limitPerProvider,
        'total' => $total,
        'providers' => $providers,
        'note' => 'Hub-unconnected means absent from the enabled Hub graph; it does not mean SEO orphan.',
    );
}

/**
 * Report sitemap/feed interoperability paths for providers participating in
 * the enabled Hub graph.
 *
 * Hub does not generate sitemap or feed output here. It only reports whether
 * the owning provider exposes the shared Geeklog distribution contracts
 * documented by the Memorandum.
 *
 * @return array
 */
function HUB_integrityDistributionOpportunities()
{
    $types = array();

    foreach (HUB_getPillars(false) as $pillar) {
        if (!is_array($pillar) || empty($pillar['id'])) {
            continue;
        }

        $sourceType = isset($pillar['source_type'])
            ? HUB_normalizeObjectType($pillar['source_type'])
            : '';
        if ($sourceType !== '') {
            $types[$sourceType] = true;
        }

        foreach (HUB_getRelations((int) $pillar['id'], false) as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $type = isset($relation['item_type'])
                ? HUB_normalizeObjectType($relation['item_type'])
                : '';
            if ($type !== '') {
                $types[$type] = true;
            }
        }
    }

    ksort($types, SORT_STRING);
    $providers = array();

    foreach (array_keys($types) as $type) {
        if ($type === 'article') {
            $providers[$type] = array(
                'sitemap' => array(
                    'status' => 'core-owned',
                    'native_collector' => false,
                    'collection_fallback' => false,
                ),
                'syndication' => array(
                    'status' => 'core-owned',
                    'declared' => false,
                    'callbacks' => false,
                ),
                'opportunities' => array(),
            );
            continue;
        }

        $collector = 'plugin_collectSitemapItems_' . $type;
        $nativeSitemap = function_exists($collector);
        $collectionFallback = function_exists('HUB_relationCollectionSupported')
            && HUB_relationCollectionSupported($type);

        $feedNames = 'plugin_getfeednames_' . $type;
        $feedContent = 'plugin_getfeedcontent_' . $type;
        $feedCallbacks = function_exists($feedNames) && function_exists($feedContent);

        $declaredSyndication = false;
        if (function_exists('HUB_capabilityDeclaration')) {
            $declaration = HUB_capabilityDeclaration($type);
            if (!empty($declaration['valid'])
                && !empty($declaration['capabilities'])
                && is_array($declaration['capabilities'])
            ) {
                $declaredSyndication = in_array(
                    'content.syndication',
                    $declaration['capabilities'],
                    true
                );
            }
        }

        if ($nativeSitemap) {
            $sitemapStatus = 'native-collector';
        } elseif ($collectionFallback) {
            $sitemapStatus = 'collection-fallback';
        } else {
            $sitemapStatus = 'not-detected';
        }

        if ($feedCallbacks) {
            $syndicationStatus = 'native-callbacks';
        } elseif ($declaredSyndication) {
            $syndicationStatus = 'declared-unverified';
        } else {
            $syndicationStatus = 'not-declared';
        }

        $opportunities = array();
        if ($sitemapStatus === 'not-detected') {
            $opportunities[] = 'review-sitemap-participation';
        }
        if ($syndicationStatus === 'not-declared') {
            $opportunities[] = 'review-syndication-if-content-is-feed-worthy';
        }

        $providers[$type] = array(
            'sitemap' => array(
                'status' => $sitemapStatus,
                'native_collector' => $nativeSitemap,
                'collection_fallback' => $collectionFallback,
            ),
            'syndication' => array(
                'status' => $syndicationStatus,
                'declared' => $declaredSyndication,
                'callbacks' => $feedCallbacks,
            ),
            'opportunities' => $opportunities,
        );
    }

    return array(
        'scope' => 'provider-distribution-contracts',
        'providers' => $providers,
        'note' => 'Opportunities are interoperability reviews, not SEO failures. Hub does not own sitemap or feed generation.',
    );
}

/**
 * Build normalized integrity summary for all enabled Hub pillars.
 *
 * @param int $uid
 * @return array
 */
function HUB_integritySummary($uid = 0)
{
    $summary = array(
        'schema' => 1,
        'scope' => 'integrity-0.9',
        'pillars' => 0,
        'relations' => 0,
        'resolved_relations' => 0,
        'unresolved_relations' => 0,
        'renderable_relations' => 0,
        'non_renderable_relations' => 0,
        'cross_site_relations' => 0,
        'current_site_relations' => 0,
        'unknown_site_relations' => 0,
        'equivalents' => array(
            'total' => 0,
            'cross_language' => 0,
            'same_language_review' => 0,
            'language_unverified' => 0,
            'cross_site' => 0,
        ),
        'unresolved_pillar_sources' => 0,
        'health' => array(
            'healthy' => 0,
            'attention' => 0,
            'broken' => 0,
        ),
        'backlink' => array(
            'hub_managed' => 0,
            'integration_available' => 0,
            'unconfirmed' => 0,
        ),
        'reciprocal' => array(
            'hub_rendered' => 0,
            'fragment_available_runtime_unverified' => 0,
            'integration_available_unverified' => 0,
            'unconfirmed' => 0,
        ),
        'providers' => array(),
        'graph' => array(),
        'canonical_collisions' => array(),
        'unconnected_content' => array(),
        'distribution' => array(),
        'pillar_items' => array(),
    );

    foreach (HUB_getPillars(false) as $pillar) {
        $diagnostic = HUB_integrityPillar($pillar, $uid);
        if (empty($diagnostic)) {
            continue;
        }

        $summary['pillars']++;
        $summary['relations'] += (int) $diagnostic['relation_count'];
        $summary['resolved_relations'] += (int) $diagnostic['resolved_relations'];
        $summary['unresolved_relations'] += (int) $diagnostic['unresolved_relations'];
        $summary['renderable_relations'] += (int) $diagnostic['renderable_relations'];
        $summary['non_renderable_relations'] += (int) $diagnostic['non_renderable_relations'];
        $summary['cross_site_relations'] += isset($diagnostic['cross_site_relations'])
            ? (int) $diagnostic['cross_site_relations'] : 0;
        $summary['current_site_relations'] += isset($diagnostic['current_site_relations'])
            ? (int) $diagnostic['current_site_relations'] : 0;
        $summary['unknown_site_relations'] += isset($diagnostic['unknown_site_relations'])
            ? (int) $diagnostic['unknown_site_relations'] : 0;

        if (!empty($diagnostic['equivalents']) && is_array($diagnostic['equivalents'])) {
            foreach ($summary['equivalents'] as $key => $value) {
                if (isset($diagnostic['equivalents'][$key])) {
                    $summary['equivalents'][$key] += (int) $diagnostic['equivalents'][$key];
                }
            }
        }

        if (empty($diagnostic['source']['resolved'])) {
            $summary['unresolved_pillar_sources']++;
        }

        $healthStatus = isset($diagnostic['health']['status'])
            ? (string) $diagnostic['health']['status']
            : 'attention';
        if (isset($summary['health'][$healthStatus])) {
            $summary['health'][$healthStatus]++;
        }

        foreach ($summary['backlink'] as $key => $value) {
            if (isset($diagnostic['backlink'][$key])) {
                $summary['backlink'][$key] += (int) $diagnostic['backlink'][$key];
            }
        }
        foreach ($summary['reciprocal'] as $key => $value) {
            if (isset($diagnostic['reciprocal'][$key])) {
                $summary['reciprocal'][$key] += (int) $diagnostic['reciprocal'][$key];
            }
        }

        foreach ($diagnostic['providers'] as $provider => $providerStats) {
            if (!isset($summary['providers'][$provider])) {
                $summary['providers'][$provider] = array(
                    'relations' => 0,
                    'resolved' => 0,
                    'unresolved' => 0,
                );
            }

            foreach (array('relations', 'resolved', 'unresolved') as $field) {
                $summary['providers'][$provider][$field] += isset($providerStats[$field])
                    ? (int) $providerStats[$field]
                    : 0;
            }
        }

        $summary['pillar_items'][] = $diagnostic;
    }

    $summary['graph'] = HUB_integrityGraphDiagnostics();
    $summary['canonical_collisions'] = HUB_integrityCanonicalCollisions($uid);
    $summary['unconnected_content'] = HUB_integrityUnconnectedContent(100);
    $summary['distribution'] = HUB_integrityDistributionOpportunities();

    ksort($summary['providers'], SORT_STRING);
    usort($summary['pillar_items'], function ($left, $right) {
        return (int) $left['pillar_id'] < (int) $right['pillar_id'] ? -1
            : ((int) $left['pillar_id'] > (int) $right['pillar_id'] ? 1 : 0);
    });

    return $summary;
}
