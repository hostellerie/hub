<?php

$_SERVER['PHP_SELF'] = 'tests/services_contract.php';

define('PLG_RET_OK', 0);
define('PLG_RET_ERROR', -1);
define('PLG_RET_PERMISSION_DENIED', -2);

$hubServiceAuthorized = true;

function SEC_hasRights($right)
{
    global $hubServiceAuthorized;
    return $right === 'hub.admin' && $hubServiceAuthorized;
}

function HUB_normalizeObjectType($type)
{
    return strtolower(trim((string) $type));
}

function HUB_normalizeObjectId($id)
{
    return trim((string) $id);
}

function HUB_getAffectedContexts($type, $id, $includeDisabled = false)
{
    return array(
        array(
            'pillar_id' => 7,
            'source_type' => 'staticpages',
            'source_id' => 'guide',
            'reasons' => array('related-item'),
        ),
    );
}

require_once dirname(__DIR__) . '/services.inc.php';

function hubServiceAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$output = null;
$messages = null;
$status = service_affected_read_hub(
    array('type' => 'article', 'id' => 'story-1'),
    $output,
    $messages
);

hubServiceAssert($status === PLG_RET_OK, 'authorized affected read returns OK');
hubServiceAssert(is_array($output), 'affected read returns structured output');
hubServiceAssert($output['capability'] === 'hub.affected.read', 'service identifies its capability');
hubServiceAssert($output['object']['type'] === 'article', 'service normalizes object type');
hubServiceAssert($output['object']['id'] === 'story-1', 'service normalizes object id');
hubServiceAssert($output['count'] === 1, 'service exposes affected-context count');
hubServiceAssert($output['contexts'][0]['pillar_id'] === 7, 'service reuses Hub affected-context resolver');

$output = null;
$messages = null;
$status = service_affected_read_hub(array(), $output, $messages);
hubServiceAssert($status === PLG_RET_ERROR, 'missing identity returns service error');
hubServiceAssert(!empty($messages), 'missing identity returns an explanatory message');

$hubServiceAuthorized = false;
$output = null;
$messages = null;
$status = service_affected_read_hub(
    array('type' => 'article', 'id' => 'story-1'),
    $output,
    $messages
);
hubServiceAssert($status === PLG_RET_PERMISSION_DENIED, 'service enforces hub.admin ACL');

$functionsSource = file_get_contents(dirname(__DIR__) . '/functions.inc');
hubServiceAssert(strpos($functionsSource, 'function plugin_wsEnabled_hub()') !== false, 'Hub enables the native service dispatcher');
hubServiceAssert(strpos($functionsSource, "'hub.affected.read'") !== false, 'implemented affected service is advertised as a capability');

echo "Hub service contract tests passed." . PHP_EOL;
