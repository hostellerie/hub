<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Normalize a lifecycle identity to the same stable identity used by Hub.
 *
 * @param string $id
 * @param string $type
 * @param string $subType
 * @return array
 */
function HUB_normalizeLifecycleIdentity($id, $type, $subType = '')
{
    $type = (string) $type;
    $subType = (string) $subType;

    if (strpos($type, '.') !== false) {
        list($type, $legacySubType) = explode('.', $type, 2);
        if ($subType === '') {
            $subType = $legacySubType;
        }
    }

    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);

    // Geeklog stories are exposed through the common article identity in Hub.
    if ($type === 'story') {
        $type = 'article';
    }

    return array(
        'type' => $type,
        'id' => $id,
        'sub_type' => HUB_normalizeObjectType($subType),
    );
}

/**
 * Return enabled Hub pillar contexts affected by one stable content identity.
 *
 * The result is intentionally provider-neutral and contains no provider-owned
 * business data. A changed object can affect a pillar because it is the pillar
 * source itself, a related item, or both.
 *
 * @param string $type
 * @param string $id
 * @param bool $includeDisabled
 * @return array
 */
function HUB_getAffectedContexts($type, $id, $includeDisabled = false)
{
    $identity = HUB_normalizeLifecycleIdentity($id, $type);
    if ($identity['type'] === '' || $identity['id'] === '') {
        return array();
    }

    $contexts = array();

    $directPillar = HUB_findPillar($identity['type'], $identity['id']);
    if (is_array($directPillar) && ($includeDisabled || !empty($directPillar['is_enabled']))) {
        $pillarId = isset($directPillar['id']) ? (int) $directPillar['id'] : 0;
        if ($pillarId > 0) {
            $contexts[$pillarId] = array(
                'pillar_id' => $pillarId,
                'source_type' => (string) $directPillar['source_type'],
                'source_id' => (string) $directPillar['source_id'],
                'reasons' => array('pillar-source'),
            );
        }
    }

    if ($identity['type'] === 'topic' && function_exists('HUB_findPillarContextsForTopic')) {
        foreach (HUB_findPillarContextsForTopic($identity['id'], $includeDisabled) as $topicContext) {
            if (!is_array($topicContext) || empty($topicContext['pillar_id'])) {
                continue;
            }

            $pillarId = (int) $topicContext['pillar_id'];
            if (!isset($contexts[$pillarId])) {
                $contexts[$pillarId] = $topicContext;
            } elseif (!in_array('topic-assignment', $contexts[$pillarId]['reasons'], true)) {
                $contexts[$pillarId]['reasons'][] = 'topic-assignment';
            }
        }
    }

    foreach (HUB_findPillarsForItem($identity['type'], $identity['id'], $includeDisabled) as $pillar) {
        $pillarId = isset($pillar['id']) ? (int) $pillar['id'] : 0;
        if ($pillarId < 1) {
            continue;
        }

        if (!isset($contexts[$pillarId])) {
            $contexts[$pillarId] = array(
                'pillar_id' => $pillarId,
                'source_type' => isset($pillar['source_type']) ? (string) $pillar['source_type'] : '',
                'source_id' => isset($pillar['source_id']) ? (string) $pillar['source_id'] : '',
                'reasons' => array(),
            );
        }

        if (!in_array('related-item', $contexts[$pillarId]['reasons'], true)) {
            $contexts[$pillarId]['reasons'][] = 'related-item';
        }
    }

    ksort($contexts, SORT_NUMERIC);

    return array_values($contexts);
}

/**
 * Invalidate public caches for affected Hub contexts.
 *
 * @param array $contexts
 * @param string $itemType
 * @param string $itemId
 * @return void
 */
function HUB_invalidateAffectedContexts($contexts, $itemType, $itemId)
{
    if (!is_array($contexts)) {
        return;
    }

    foreach ($contexts as $context) {
        if (!is_array($context) || empty($context['pillar_id'])) {
            continue;
        }

        HUB_invalidateRelationshipCaches(
            (int) $context['pillar_id'],
            (string) $itemType,
            (string) $itemId
        );
    }
}

/**
 * Handle a saved object without rewriting Hub relationships.
 *
 * @param string $id
 * @param string $type
 * @param string $oldId
 * @param string $subType
 * @return array
 */
function HUB_handleItemSaved($id, $type, $oldId = '', $subType = '')
{
    $identity = HUB_normalizeLifecycleIdentity($id, $type, $subType);
    $contexts = HUB_getAffectedContexts($identity['type'], $identity['id']);

    if ($oldId !== '' && (string) $oldId !== (string) $identity['id']) {
        $oldIdentity = HUB_normalizeLifecycleIdentity($oldId, $type, $subType);
        $oldContexts = HUB_getAffectedContexts($oldIdentity['type'], $oldIdentity['id']);

        foreach ($oldContexts as $oldContext) {
            $found = false;
            foreach ($contexts as $context) {
                if ((int) $context['pillar_id'] === (int) $oldContext['pillar_id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $contexts[] = $oldContext;
            }
        }

        $migration = HUB_migrateObjectIdentity(
            $identity['type'],
            $oldIdentity['id'],
            $identity['id']
        );

        // If Hub references were migrated successfully, recalculate the new
        // context set so callers receive the current graph identity.
        if (!empty($migration['changed']) && empty($migration['collisions']) && empty($migration['error'])) {
            $contexts = HUB_getAffectedContexts($identity['type'], $identity['id']);
        }

        HUB_invalidateAffectedContexts($oldContexts, $oldIdentity['type'], $oldIdentity['id']);
    }

    HUB_invalidateAffectedContexts($contexts, $identity['type'], $identity['id']);

    return $contexts;
}

/**
 * Handle a deleted object while preserving its stable Hub relationship.
 *
 * @param string $id
 * @param string $type
 * @param string $subType
 * @return array
 */
function HUB_handleItemDeleted($id, $type, $subType = '')
{
    $identity = HUB_normalizeLifecycleIdentity($id, $type, $subType);
    $contexts = HUB_getAffectedContexts($identity['type'], $identity['id']);

    HUB_invalidateAffectedContexts($contexts, $identity['type'], $identity['id']);

    return $contexts;
}
