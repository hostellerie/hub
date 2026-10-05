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

function HUB_normalizeRelationRole($role)
{
    $role = strtolower(trim((string) $role));
    return in_array($role, array('related', 'sub-pillar', 'satellite', 'support'), true)
        ? $role : 'related';
}

function HUB_normalizeEditorialRole($role)
{
    $role = strtolower(trim((string) $role));
    return in_array($role, array('', 'guide', 'tutorial', 'reference', 'case-study', 'download', 'video', 'discussion', 'resource', 'news', 'archive'), true)
        ? $role : '';
}

function HUB_editorialSummary($includeDisabled = false)
{
    return array(
        'schema' => 1,
        'pillars' => 2,
        'relations' => 5,
        'roles' => array(
            'related' => 0,
            'sub-pillar' => 1,
            'satellite' => 3,
            'support' => 1,
        ),
        'providers' => array('article' => 3, 'videos' => 2),
        'multi_parent_items' => 1,
        'nested_pillars' => 1,
        'pillar_items' => array(),
    );
}

function HUB_editorialInventory($includeDisabled = false)
{
    return array(
        'schema' => 1,
        'pillars' => array(
            array(
                'pillar_id' => 7,
                'source_type' => 'staticpages',
                'source_id' => 'guide',
                'is_enabled' => true,
                'items' => array(
                    array(
                        'type' => 'article',
                        'id' => 'story-1',
                        'relation_role' => 'satellite',
                        'position' => 10,
                        'is_enabled' => true,
                        'is_nested_pillar' => false,
                        'nested_pillar_id' => 0,
                        'parent_count' => 1,
                    ),
                ),
            ),
        ),
    );
}

function HUB_integritySummary($uid = 0)
{
    return array(
        'schema' => 1,
        'scope' => 'integrity-0.9',
        'pillars' => 1,
        'relations' => 2,
        'resolved_relations' => 1,
        'unresolved_relations' => 1,
        'renderable_relations' => 1,
        'non_renderable_relations' => 1,
        'unresolved_pillar_sources' => 0,
        'backlink' => array(
            'hub_managed' => 1,
            'integration_available' => 1,
            'unconfirmed' => 0,
        ),
        'providers' => array(),
        'pillar_items' => array(),
    );
}

function HUB_editorialSuggestions($pillarId = 0, $limit = 20)
{
    return array(
        'schema' => 1,
        'generated_from' => array('shared-topic'),
        'pillar_candidates' => $pillarId > 0 ? array() : array(
            array(
                'type' => 'staticpages',
                'id' => 'new-pillar',
                'title' => 'New pillar candidate',
                'score' => 2,
                'evidence' => array(
                    array(
                        'signal' => 'shared-topic',
                        'topics' => array(
                            array('id' => 'seo', 'label' => 'SEO'),
                        ),
                        'matching_article_count' => 2,
                    ),
                ),
            ),
        ),
        'pillars' => array(
            array(
                'pillar_id' => 7,
                'source_type' => 'staticpages',
                'source_id' => 'guide',
                'candidates' => array(
                    array(
                        'type' => 'article',
                        'id' => 'story-2',
                        'title' => 'Candidate story',
                        'suggested_role' => 'satellite',
                        'score' => 2,
                        'evidence' => array(
                            array(
                                'signal' => 'shared-topic',
                                'topics' => array(
                                    array('id' => 'seo', 'label' => 'SEO'),
                                    array('id' => 'content', 'label' => 'Content'),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        ),
    );
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

function HUB_graphContext($type, $id, $depth = 4, $includeDisabled = false)
{
    return array(
        'start' => array('type' => $type, 'id' => $id),
        'max_depth' => (int) $depth,
        'nodes' => array(
            array('type' => $type, 'id' => $id, 'depth' => 0),
            array('type' => 'staticpages', 'id' => 'guide', 'depth' => 1),
        ),
        'edges' => array(
            array(
                'from' => array('type' => 'staticpages', 'id' => 'guide'),
                'to' => array('type' => $type, 'id' => $id),
                'kind' => 'related',
            ),
        ),
    );
}

function HUB_graphNeighbors($type, $id, $includeDisabled = false)
{
    return array(
        'parents' => array(array('type' => 'staticpages', 'id' => 'guide')),
        'children' => array(array('type' => 'article', 'id' => 'child-1')),
        'edges' => array(),
    );
}

function HUB_getPillar($pillarId)
{
    return (int) $pillarId === 7
        ? array('id' => 7, 'source_type' => 'staticpages', 'source_id' => 'guide', 'is_enabled' => 1)
        : false;
}

function HUB_findPillar($type, $id)
{
    return ((string) $type === 'staticpages' && (string) $id === 'guide')
        ? HUB_getPillar(7)
        : false;
}

function HUB_getRelations($pillarId, $includeDisabled = true)
{
    return (int) $pillarId === 7
        ? array(array('item_type' => 'article', 'item_id' => 'story-1', 'relation_role' => 'satellite', 'editorial_role' => 'tutorial', 'position' => 10, 'is_enabled' => 1))
        : array();
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

$hubServiceAuthorized = true;
$output = null;
$messages = null;
$status = service_context_read_hub(
    array('type' => 'article', 'id' => 'story-1', 'depth' => 3),
    $output,
    $messages
);
hubServiceAssert($status === PLG_RET_OK, 'context read returns OK');
hubServiceAssert($output['capability'] === 'hub.context.read', 'context service identifies its capability');
hubServiceAssert($output['context']['max_depth'] === 3, 'context service forwards requested traversal depth');
hubServiceAssert(count($output['context']['nodes']) === 2, 'context service returns graph nodes');

$output = null;
$messages = null;
$status = service_related_read_hub(array('type' => 'article', 'id' => 'story-1'), $output, $messages);
hubServiceAssert($status === PLG_RET_OK, 'related read returns OK');
hubServiceAssert($output['capability'] === 'hub.related.read', 'related service identifies its capability');
hubServiceAssert(count($output['parents']) === 1, 'related service returns immediate parents');

$output = null;
$messages = null;
$status = service_pillar_read_hub(array('pillar_id' => 7), $output, $messages);
hubServiceAssert($status === PLG_RET_OK, 'pillar read returns OK');
hubServiceAssert($output['capability'] === 'hub.pillar.read', 'pillar service identifies its capability');
hubServiceAssert($output['pillar']['source_id'] === 'guide', 'pillar service returns stable source identity');
hubServiceAssert(count($output['relations']) === 1, 'pillar service returns relation identities');
hubServiceAssert($output['relations'][0]['relation_role'] === 'satellite', 'pillar service returns structural role');

$output = null;
$messages = null;
$status = service_editorial_summary_hub(array(), $output, $messages);
hubServiceAssert($status === PLG_RET_OK, 'editorial summary returns OK');
hubServiceAssert($output['capability'] === 'hub.editorial.summary', 'editorial summary identifies its capability');
hubServiceAssert($output['summary']['pillars'] === 2, 'editorial summary service returns structural graph counts');
hubServiceAssert($output['summary']['multi_parent_items'] === 1, 'editorial summary preserves graph-shaped context');
hubServiceAssert(!isset($output['inventory']), 'editorial summary stays compact by default');

$output = null;
$messages = null;
$status = service_editorial_summary_hub(array('include_inventory' => true), $output, $messages);
hubServiceAssert($status === PLG_RET_OK, 'editorial summary with inventory returns OK');
hubServiceAssert(isset($output['inventory']), 'editorial summary optionally returns inventory');
hubServiceAssert($output['inventory']['pillars'][0]['items'][0]['relation_role'] === 'satellite', 'inventory keeps structural roles');
hubServiceAssert($output['inventory']['pillars'][0]['items'][0]['parent_count'] === 1, 'inventory keeps parent participation');

$output = null;
$messages = null;
$status = service_suggestions_read_hub(array('pillar_id' => 7, 'limit' => 5), $output, $messages);
hubServiceAssert($status === PLG_RET_OK, 'suggestions read returns OK');
hubServiceAssert($output['capability'] === 'hub.suggestions.read', 'suggestions service identifies its capability');
hubServiceAssert($output['suggestions']['generated_from'] === array('shared-topic'), 'suggestions service keeps evidence source explicit');
hubServiceAssert($output['suggestions']['pillars'][0]['candidates'][0]['id'] === 'story-2', 'suggestions service returns stable candidate identity');
hubServiceAssert(empty($output['suggestions']['pillar_candidates']), 'pillar-scoped suggestions omit global pillar candidates');

$output = null;
$messages = null;
$status = service_suggestions_read_hub(array('limit' => 5), $output, $messages);
hubServiceAssert($status === PLG_RET_OK, 'global suggestions read returns OK');
hubServiceAssert(count($output['suggestions']['pillar_candidates']) === 1, 'global suggestions expose pillar candidates');
hubServiceAssert($output['suggestions']['pillar_candidates'][0]['id'] === 'new-pillar', 'pillar candidate keeps stable Static Page identity');

$output = null;
$messages = null;
$status = service_integrity_summary_hub(array(), $output, $messages);
hubServiceAssert($status === PLG_RET_OK, 'integrity summary returns OK');
hubServiceAssert($output['capability'] === 'hub.integrity.summary', 'integrity service identifies its capability');
hubServiceAssert($output['summary']['scope'] === 'integrity-0.9', 'integrity service returns 0.9 scope');
hubServiceAssert($output['summary']['unresolved_relations'] === 1, 'integrity service returns normalized unresolved count');

$functionsSource = file_get_contents(dirname(__DIR__) . '/functions.inc');
hubServiceAssert(strpos($functionsSource, 'function plugin_wsEnabled_hub()') !== false, 'Hub enables the native service dispatcher');
hubServiceAssert(strpos($functionsSource, "'hub.affected.read'") !== false, 'implemented affected service is advertised as a capability');
hubServiceAssert(strpos($functionsSource, "'hub.context.read'") !== false, 'implemented context service is advertised as a capability');
hubServiceAssert(strpos($functionsSource, "'hub.related.read'") !== false, 'implemented related service is advertised as a capability');
hubServiceAssert(strpos($functionsSource, "'hub.pillar.read'") !== false, 'implemented pillar service is advertised as a capability');
hubServiceAssert(strpos($functionsSource, "'hub.editorial.summary'") !== false, 'implemented editorial summary is advertised as a capability');
hubServiceAssert(strpos($functionsSource, "'hub.suggestions.read'") !== false, 'implemented suggestions service is advertised as a capability');
hubServiceAssert(strpos($functionsSource, "'hub.integrity.summary'") !== false, 'implemented integrity service is advertised as a capability');

echo "Hub service contract tests passed." . PHP_EOL;
