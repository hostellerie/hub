<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Resolve public URLs for Hub contexts affected by another changed object.
 *
 * Hub deliberately does not deduplicate URLs here. IndexNow owns
 * deduplication, batching, validation, transport and submission history.
 *
 * @param array  $contexts
 * @param string $changedType
 * @param string $changedId
 * @param string $event
 * @return array
 */
function HUB_indexNowAffectedPayload($contexts, $changedType, $changedId, $event = 'affected')
{
    $changedType = HUB_normalizeObjectType($changedType);
    $changedId = HUB_normalizeObjectId($changedId);
    $event = trim((string) $event);
    if ($event === '') {
        $event = 'affected';
    }

    $urls = array();
    $items = array();

    if (!is_array($contexts)) {
        return array('urls' => $urls, 'contexts' => $items);
    }

    foreach ($contexts as $context) {
        if (!is_array($context)) {
            continue;
        }

        $type = isset($context['source_type'])
            ? HUB_normalizeObjectType($context['source_type'])
            : '';
        $id = isset($context['source_id'])
            ? HUB_normalizeObjectId($context['source_id'])
            : '';

        if ($type === '' || $id === '') {
            continue;
        }

        // IndexNow already receives the changed object through its own Geeklog
        // lifecycle listener. Hub only announces other pages whose rendering
        // changed because of Hub relationships/context.
        if ($type === $changedType && $id === $changedId) {
            continue;
        }

        $resolved = HUB_resolveObject($type, $id, 1);
        if (empty($resolved['exists']) || empty($resolved['url'])) {
            continue;
        }

        $urls[] = (string) $resolved['url'];
        $items[] = array(
            'item_type' => $type,
            'item_id' => $id,
            'event' => $event,
            'source' => 'hub',
        );
    }

    return array(
        'urls' => $urls,
        'contexts' => $items,
    );
}

/**
 * Ask IndexNow to submit public pages affected by a Hub graph change.
 *
 * Missing/older IndexNow installations are intentionally a no-op. Hub never
 * calls IndexNow internals directly and never handles its transport state.
 *
 * @param array  $contexts
 * @param string $changedType
 * @param string $changedId
 * @param string $event
 * @return array
 */
function HUB_notifyIndexNowAffectedContexts($contexts, $changedType, $changedId, $event = 'affected')
{
    $result = array(
        'available' => false,
        'requested' => 0,
        'status' => null,
        'output' => array(),
        'messages' => array(),
    );

    if (!function_exists('PLG_invokeService')) {
        return $result;
    }

    if (function_exists('HUB_serviceHasAction')
        && !HUB_serviceHasAction('indexnow', 'submit_urls')
    ) {
        return $result;
    }

    $payload = HUB_indexNowAffectedPayload($contexts, $changedType, $changedId, $event);
    if (empty($payload['urls'])) {
        return $result;
    }

    $output = array();
    $messages = array();
    $status = PLG_invokeService(
        'indexnow',
        'submit_urls',
        array(
            'urls' => $payload['urls'],
            'contexts' => $payload['contexts'],
            'event' => $event,
        ),
        $output,
        $messages
    );

    $result['available'] = true;
    $result['requested'] = count($payload['urls']);
    $result['status'] = $status;
    $result['output'] = is_array($output) ? $output : array();
    $result['messages'] = is_array($messages) ? $messages : array();

    return $result;
}


/**
 * Read normalized IndexNow status when the provider exposes it.
 *
 * @return array
 */
function HUB_indexNowStatus()
{
    $result = array(
        'available' => false,
        'status' => null,
        'data' => array(),
        'messages' => array(),
    );

    if (!function_exists('PLG_invokeService')) {
        return $result;
    }

    if (function_exists('HUB_serviceHasAction')
        && !HUB_serviceHasAction('indexnow', 'status_read')
    ) {
        return $result;
    }

    $output = array();
    $messages = array();
    $status = PLG_invokeService(
        'indexnow',
        'status_read',
        array(),
        $output,
        $messages
    );

    $result['available'] = true;
    $result['status'] = $status;
    $result['data'] = is_array($output) ? $output : array();
    $result['messages'] = is_array($messages) ? $messages : array();

    return $result;
}
