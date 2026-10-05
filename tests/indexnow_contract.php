<?php

$_SERVER['PHP_SELF'] = 'tests/indexnow_contract.php';

$hubIndexNowFixture = array(
    'service_available' => true,
    'calls' => array(),
    'resolves' => array(),
);

function HUB_normalizeObjectType($type)
{
    return strtolower(trim((string) $type));
}

function HUB_normalizeObjectId($id)
{
    return trim((string) $id);
}

function HUB_resolveObject($type, $id, $uid = 0)
{
    global $hubIndexNowFixture;

    $hubIndexNowFixture['resolves'][] = array((string) $type, (string) $id, (int) $uid);

    $map = array(
        'staticpages:guide' => array(
            'exists' => true,
            'url' => 'https://example.test/staticpages/index.php?page=guide',
        ),
        'staticpages:second' => array(
            'exists' => true,
            'url' => 'https://example.test/staticpages/index.php?page=second',
        ),
        'article:story-1' => array(
            'exists' => true,
            'url' => 'https://example.test/article.php?story=story-1',
        ),
    );

    $key = (string) $type . ':' . (string) $id;
    if (!isset($map[$key])) {
        return array(
            'exists' => false,
            'url' => '',
        );
    }

    return array_merge(
        array(
            'type' => (string) $type,
            'id' => (string) $id,
        ),
        $map[$key]
    );
}

function HUB_serviceHasAction($plugin, $action)
{
    global $hubIndexNowFixture;

    return $plugin === 'indexnow'
        && in_array($action, array('submit_urls', 'status_read'), true)
        && !empty($hubIndexNowFixture['service_available']);
}

function PLG_invokeService($type, $action, $args, &$output, &$svc_msg)
{
    global $hubIndexNowFixture;

    $hubIndexNowFixture['calls'][] = array(
        'type' => $type,
        'action' => $action,
        'args' => $args,
    );

    if ($action === 'status_read') {
        $output = array(
            'capability' => 'indexnow.status.read',
            'transport' => array('mode' => 'immediate-batch', 'batch_size' => 100),
            'key' => array(
                'present' => true,
                'valid' => true,
                'file_exists' => true,
                'file_readable' => true,
                'file_matches' => true,
            ),
            'history_available' => true,
            'latest_submission' => array(
                'status' => 'success',
                'submitted_at' => '2026-10-05 10:00:00',
            ),
        );
    } else {
        $output = array(
            'capability' => 'indexnow.urls.submit',
            'received' => isset($args['urls']) ? count($args['urls']) : 0,
        );
    }
    $svc_msg = array();

    return 0;
}

require_once dirname(__DIR__) . '/lib-indexnow.php';

function hubIndexNowAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$contexts = array(
    array(
        'pillar_id' => 10,
        'source_type' => 'article',
        'source_id' => 'story-1',
        'reasons' => array('pillar-source'),
    ),
    array(
        'pillar_id' => 11,
        'source_type' => 'staticpages',
        'source_id' => 'guide',
        'reasons' => array('related-item'),
    ),
    array(
        'pillar_id' => 12,
        'source_type' => 'staticpages',
        'source_id' => 'guide',
        'reasons' => array('related-item'),
    ),
    array(
        'pillar_id' => 13,
        'source_type' => 'staticpages',
        'source_id' => 'missing',
        'reasons' => array('related-item'),
    ),
);

$payload = HUB_indexNowAffectedPayload(
    $contexts,
    'article',
    'story-1',
    'hub-affected-save'
);

hubIndexNowAssert(count($payload['urls']) === 2, 'Hub excludes the directly changed object and unresolved contexts');
hubIndexNowAssert($payload['urls'][0] === 'https://example.test/staticpages/index.php?page=guide', 'Hub resolves affected pillar URL anonymously');
hubIndexNowAssert($payload['urls'][1] === $payload['urls'][0], 'Hub deliberately leaves URL deduplication to IndexNow');
hubIndexNowAssert($payload['contexts'][0]['event'] === 'hub-affected-save', 'Hub preserves affected-save event metadata');
hubIndexNowAssert($payload['contexts'][0]['source'] === 'hub', 'Hub identifies itself as context source');

foreach ($hubIndexNowFixture['resolves'] as $resolve) {
    hubIndexNowAssert($resolve[2] === 1, 'Hub resolves affected URLs using anonymous access');
}

$hubIndexNowFixture['calls'] = array();
$result = HUB_notifyIndexNowAffectedContexts(
    array(
        array(
            'pillar_id' => 11,
            'source_type' => 'staticpages',
            'source_id' => 'guide',
            'reasons' => array('related-item'),
        ),
        array(
            'pillar_id' => 12,
            'source_type' => 'staticpages',
            'source_id' => 'second',
            'reasons' => array('related-item'),
        ),
    ),
    'article',
    'story-1',
    'hub-affected-save'
);

hubIndexNowAssert(!empty($result['available']), 'Hub detects the implemented IndexNow service');
hubIndexNowAssert($result['requested'] === 2, 'Hub delegates all affected context URLs in one request');
hubIndexNowAssert(count($hubIndexNowFixture['calls']) === 1, 'Hub invokes IndexNow exactly once per lifecycle event');
hubIndexNowAssert($hubIndexNowFixture['calls'][0]['type'] === 'indexnow', 'Hub invokes the IndexNow provider');
hubIndexNowAssert($hubIndexNowFixture['calls'][0]['action'] === 'submit_urls', 'Hub uses the native submit_urls action');
hubIndexNowAssert(count($hubIndexNowFixture['calls'][0]['args']['urls']) === 2, 'Hub sends one batch of affected URLs');

$before = count($hubIndexNowFixture['calls']);
$hubIndexNowFixture['service_available'] = false;
$missing = HUB_notifyIndexNowAffectedContexts(
    array(
        array(
            'pillar_id' => 11,
            'source_type' => 'staticpages',
            'source_id' => 'guide',
            'reasons' => array('related-item'),
        ),
    ),
    'article',
    'story-1',
    'hub-affected-save'
);

hubIndexNowAssert(empty($missing['available']), 'Older or missing IndexNow is a no-op');
hubIndexNowAssert(count($hubIndexNowFixture['calls']) === $before, 'Hub does not invoke an unavailable IndexNow action');

$hubIndexNowFixture['service_available'] = true;
$hubIndexNowFixture['calls'] = array();
$statusResult = HUB_indexNowStatus();

hubIndexNowAssert(!empty($statusResult['available']), 'IndexNow status service is available');
hubIndexNowAssert($statusResult['data']['capability'] === 'indexnow.status.read', 'Hub consumes normalized IndexNow status capability');
hubIndexNowAssert($statusResult['data']['transport']['mode'] === 'immediate-batch', 'Hub receives provider-owned transport mode as information');
hubIndexNowAssert(!empty($statusResult['data']['key']['file_matches']), 'Hub receives normalized key readiness only');
hubIndexNowAssert(count($hubIndexNowFixture['calls']) === 1, 'Hub reads IndexNow status through one service invocation');
hubIndexNowAssert($hubIndexNowFixture['calls'][0]['action'] === 'status_read', 'Hub uses IndexNow status_read action');

$source = file_get_contents(dirname(__DIR__) . '/lib-indexnow.php');
hubIndexNowAssert(strpos($source, "PLG_invokeService(") !== false, 'Hub delegates through Geeklog service dispatcher');
hubIndexNowAssert(strpos($source, "send_to_indexnow(") === false, 'Hub never calls IndexNow transport internals directly');
hubIndexNowAssert(strpos($source, 'indexnow_key') === false, 'Hub never reads or stores the IndexNow key');

echo "Hub IndexNow integration contract tests passed." . PHP_EOL;
