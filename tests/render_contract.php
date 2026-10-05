<?php

$_SERVER['PHP_SELF'] = 'tests/render_contract.php';

$hubRenderFixture = array(
    'calls' => array(),
);

function HUB_capabilityDeclaration($plugin)
{
    if ($plugin === 'videos') {
        return array(
            'valid' => true,
            'capabilities' => array('videos.recommendations.render'),
        );
    }

    return array('valid' => true, 'capabilities' => array());
}

function HUB_serviceCatalogue($plugin)
{
    if ($plugin === 'videos') {
        return array(
            'plugin' => 'videos',
            'dispatcher' => false,
            'actions' => array(
                array(
                    'action' => 'recommendations_render',
                    'function' => 'service_recommendations_render_videos',
                    'signature' => 'service_recommendations_render_videos(...)',
                ),
            ),
        );
    }

    if ($plugin === 'multi') {
        return array(
            'plugin' => 'multi',
            'dispatcher' => false,
            'actions' => array(
                array('action' => 'card_render', 'function' => 'service_card_render_multi'),
                array('action' => 'list_render', 'function' => 'service_list_render_multi'),
            ),
        );
    }

    return array(
        'plugin' => $plugin,
        'dispatcher' => false,
        'actions' => array(),
    );
}

function PLG_invokeService($plugin, $action, $args, &$output, &$messages)
{
    global $hubRenderFixture;

    $hubRenderFixture['calls'][] = array(
        'plugin' => $plugin,
        'action' => $action,
        'args' => $args,
    );

    $output = array(
        'schema' => 1,
        'provider' => $plugin,
        'renderer' => 'recommendations',
        'title' => 'Videos',
        'html' => '<ol><li>Approved video</li></ol>',
        'empty' => false,
    );
    $messages = array();

    return 0;
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
        ? $role
        : 'related';
}

require_once dirname(__DIR__) . '/lib-render.php';

function hubRenderAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$catalogue = HUB_renderCatalogue('videos');
hubRenderAssert(
    $catalogue['declared'] === array('videos.recommendations.render'),
    'Videos render capability is discovered'
);
hubRenderAssert(
    count($catalogue['services']) === 1
        && $catalogue['services'][0]['action'] === 'recommendations_render',
    'Videos render service is discovered'
);

hubRenderAssert(
    HUB_renderSingleAction('videos') === 'recommendations_render',
    'single provider renderer is selected without guessing'
);
hubRenderAssert(
    HUB_renderSingleAction('multi') === '',
    'Hub refuses to guess when a provider exposes multiple renderers'
);

$relations = array(
    array(
        'item_type' => 'videos',
        'item_id' => 'abc123xyz01',
        'relation_role' => 'satellite',
        'position' => 10,
    ),
    array(
        'item_type' => 'documents',
        'item_id' => 'doc-1',
        'relation_role' => 'support',
        'position' => 20,
    ),
    array(
        'item_type' => 'videos',
        'item_id' => 'def456xyz02',
        'relation_role' => 'guide',
        'position' => 30,
    ),
);

$result = HUB_renderApprovedProviderRelations(
    'videos',
    $relations,
    array('type' => 'staticpages', 'id' => 'pillar')
);

hubRenderAssert(!empty($result['available']), 'provider renderer invocation is available');
hubRenderAssert(count($hubRenderFixture['calls']) === 1, 'provider renderer is invoked exactly once');
hubRenderAssert(
    $hubRenderFixture['calls'][0]['plugin'] === 'videos'
        && $hubRenderFixture['calls'][0]['action'] === 'recommendations_render',
    'Hub invokes the discovered provider-owned render action'
);
hubRenderAssert(
    count($hubRenderFixture['calls'][0]['args']['items']) === 2,
    'Hub forwards only approved relations owned by the selected provider'
);
hubRenderAssert(
    $hubRenderFixture['calls'][0]['args']['items'][0]['relation_role'] === 'satellite',
    'Hub preserves approved structural relation role'
);
hubRenderAssert(
    $hubRenderFixture['calls'][0]['args']['items'][1]['relation_role'] === 'related',
    'unknown editorial function cannot leak into structural role'
);
hubRenderAssert(
    $hubRenderFixture['calls'][0]['args']['context']['id'] === 'pillar',
    'Hub forwards stable pillar context to the provider'
);

$notRender = HUB_renderInvoke('videos', 'provider_status', array());
hubRenderAssert(empty($notRender['available']), 'Hub refuses non-render provider actions');

$relationsSource = file_get_contents(dirname(__DIR__) . '/lib-relations.php');
hubRenderAssert(
    strpos($relationsSource, 'HUB_renderApprovedProviderRelations(') !== false,
    'public pillar rendering integrates provider-owned render services'
);
hubRenderAssert(
    strpos($relationsSource, "isset(\$specializedTypes[\$provider])") !== false,
    'specialized provider output suppresses duplicate generic links'
);
hubRenderAssert(
    strpos($relationsSource, "if (empty(\$rendered['available']) || \$html === '')") !== false,
    'empty or unavailable specialized output falls back to generic rendering'
);

echo "Hub render contract tests passed." . PHP_EOL;
