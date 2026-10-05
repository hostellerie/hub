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
        $verification = 'hub-managed';
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
        );
    }

    ksort($result['providers'], SORT_STRING);

    $result['health'] = array(
        'source_resolved' => !empty($result['source']['resolved']),
        'all_relations_resolved' => $result['unresolved_relations'] === 0,
        'all_relations_renderable' => $result['non_renderable_relations'] === 0,
        'backlink_integration_unconfirmed' => $result['backlink']['unconfirmed'],
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
        'backlink' => array(
            'hub_managed' => 0,
            'integration_available' => 0,
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

        foreach ($summary['backlink'] as $key => $value) {
            if (isset($diagnostic['backlink'][$key])) {
                $summary['backlink'][$key] += (int) $diagnostic['backlink'][$key];
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
