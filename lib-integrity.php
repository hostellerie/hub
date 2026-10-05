<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
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
        ),
        'relation_count' => count($relations),
        'resolved_relations' => 0,
        'unresolved_relations' => 0,
        'renderable_relations' => 0,
        'non_renderable_relations' => 0,
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
            'relation_role' => isset($relation['relation_role'])
                ? HUB_normalizeRelationRole($relation['relation_role'])
                : 'related',
            'editorial_role' => isset($relation['editorial_role'])
                ? HUB_normalizeEditorialRole($relation['editorial_role'])
                : '',
            'resolved' => $exists,
            'renderable_from_pillar' => $renderable,
            'title' => isset($resolved['title']) ? (string) $resolved['title'] : '',
            'url' => isset($resolved['url']) ? (string) $resolved['url'] : '',
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

    ksort($summary['providers'], SORT_STRING);
    usort($summary['pillar_items'], function ($left, $right) {
        return (int) $left['pillar_id'] < (int) $right['pillar_id'] ? -1
            : ((int) $left['pillar_id'] > (int) $right['pillar_id'] ? 1 : 0);
    });

    return $summary;
}
