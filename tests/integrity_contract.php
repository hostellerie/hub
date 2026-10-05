<?php

$_SERVER['PHP_SELF'] = 'tests/integrity_contract.php';

$hubIntegrityPillars = array(
    array(
        'id' => 1,
        'source_type' => 'staticpages',
        'source_id' => 'guide',
        'editorial_role' => 'guide',
        'is_enabled' => 1,
    ),
);

$hubIntegrityRelations = array(
    1 => array(
        array(
            'item_type' => 'article',
            'item_id' => 'story-ok',
            'relation_role' => 'satellite',
            'editorial_role' => 'tutorial',
            'is_enabled' => 1,
        ),
        array(
            'item_type' => 'videos',
            'item_id' => 'video-ok',
            'relation_role' => 'support',
            'editorial_role' => 'video',
            'is_enabled' => 1,
        ),
        array(
            'item_type' => 'maps',
            'item_id' => 'missing-map',
            'relation_role' => 'support',
            'editorial_role' => 'resource',
            'is_enabled' => 1,
        ),
        array(
            'item_type' => 'events',
            'item_id' => 'event-ok',
            'relation_role' => 'related',
            'editorial_role' => '',
            'is_enabled' => 1,
        ),
    ),
);

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

function HUB_getPillars($includeDisabled = true)
{
    global $hubIntegrityPillars;
    return $hubIntegrityPillars;
}

function HUB_getRelations($pillarId, $includeDisabled = true)
{
    global $hubIntegrityRelations;
    return isset($hubIntegrityRelations[(int) $pillarId])
        ? $hubIntegrityRelations[(int) $pillarId]
        : array();
}

function HUB_resolveObject($type, $id, $uid = 0)
{
    $key = (string) $type . ':' . (string) $id;

    $fixture = array(
        'staticpages:guide' => array('title' => 'Guide', 'url' => '/guide', 'exists' => true),
        'article:story-ok' => array('title' => 'Story', 'url' => '/story', 'exists' => true),
        'videos:video-ok' => array('title' => 'Video', 'url' => '/video', 'exists' => true),
        'events:event-ok' => array('title' => 'Event', 'url' => '/event', 'exists' => true),
    );

    if (isset($fixture[$key])) {
        return array(
            'type' => $type,
            'id' => $id,
            'title' => $fixture[$key]['title'],
            'url' => $fixture[$key]['url'],
            'exists' => true,
            'diagnostic' => '',
        );
    }

    return array(
        'type' => $type,
        'id' => $id,
        'title' => $id,
        'url' => '',
        'exists' => false,
        'diagnostic' => 'Unresolved fixture.',
    );
}

function HUB_renderItemPillarBacklinks($itemType, $itemId)
{
    if ((string) $itemType === 'article' && (string) $itemId === 'story-ok') {
        return '<aside><a href="/guide">Guide</a></aside>';
    }

    if ((string) $itemType === 'videos' && (string) $itemId === 'video-ok') {
        return '<aside><a href="/guide">Guide</a></aside>';
    }

    return '';
}

function HUB_backlinkIntegrationStatus($itemType)
{
    $itemType = HUB_normalizeObjectType($itemType);

    if ($itemType === 'article') {
        return array(
            'supported' => true,
            'mode' => 'core-template-fallback',
            'label' => 'Core article fallback',
            'detail' => 'Hub owns this rendering path.',
        );
    }

    if (in_array($itemType, array('videos', 'maps'), true)) {
        return array(
            'supported' => true,
            'mode' => 'generic-itemdisplay-provider',
            'label' => 'Generic item display hook',
            'detail' => 'Provider must call the shared hook.',
        );
    }

    return array(
        'supported' => false,
        'mode' => 'provider-hook-unconfirmed',
        'label' => 'Unconfirmed',
        'detail' => 'Runtime backlink not verifiable.',
    );
}

require_once dirname(__DIR__) . '/lib-integrity.php';

function hubIntegrityAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$articleEvidence = HUB_integrityBacklinkEvidence('article');
hubIntegrityAssert($articleEvidence['verification'] === 'hub-managed', 'article backlink evidence is Hub-managed');

$videoEvidence = HUB_integrityBacklinkEvidence('videos');
hubIntegrityAssert($videoEvidence['verification'] === 'integration-available', 'generic provider backlink is integration-available only');

$eventEvidence = HUB_integrityBacklinkEvidence('events');
hubIntegrityAssert($eventEvidence['verification'] === 'unconfirmed', 'unsupported provider backlink remains unconfirmed');

$articleReciprocal = HUB_integrityReciprocalEvidence(
    $hubIntegrityPillars[0],
    $hubIntegrityRelations[1][0],
    0
);
hubIntegrityAssert($articleReciprocal['status'] === 'hub-rendered', 'article reciprocal evidence is verified through Hub-owned placement');
hubIntegrityAssert($articleReciprocal['runtime_verified'] === true, 'article reciprocal backlink is runtime-verifiable by Hub');

$videoReciprocal = HUB_integrityReciprocalEvidence(
    $hubIntegrityPillars[0],
    $hubIntegrityRelations[1][1],
    0
);
hubIntegrityAssert($videoReciprocal['status'] === 'fragment-available-runtime-unverified', 'generic provider fragment does not imply runtime placement');
hubIntegrityAssert($videoReciprocal['runtime_verified'] === false, 'generic provider reciprocal placement stays unverified');

$pillar = HUB_integrityPillar($hubIntegrityPillars[0], 0);
hubIntegrityAssert($pillar['relation_count'] === 4, 'pillar integrity counts approved relations');
hubIntegrityAssert($pillar['resolved_relations'] === 3, 'pillar integrity counts resolved relations');
hubIntegrityAssert($pillar['unresolved_relations'] === 1, 'pillar integrity counts unresolved relations');
hubIntegrityAssert($pillar['renderable_relations'] === 3, 'pillar integrity counts renderable outgoing targets');
hubIntegrityAssert($pillar['non_renderable_relations'] === 1, 'pillar integrity counts non-renderable outgoing targets');
hubIntegrityAssert($pillar['backlink']['hub_managed'] === 1, 'pillar counts Hub-managed backlink paths');
hubIntegrityAssert($pillar['backlink']['integration_available'] === 2, 'pillar counts generic provider integration availability');
hubIntegrityAssert($pillar['backlink']['unconfirmed'] === 1, 'pillar counts unconfirmed backlink integrations');
hubIntegrityAssert($pillar['reciprocal']['hub_rendered'] === 1, 'pillar counts verified Hub-rendered reciprocal links');
hubIntegrityAssert($pillar['reciprocal']['fragment_available_runtime_unverified'] === 1, 'pillar counts generic fragments separately from runtime proof');
hubIntegrityAssert($pillar['relations'][0]['reciprocal_evidence']['status'] === 'hub-rendered', 'article relation exposes verified reciprocal evidence');
hubIntegrityAssert($pillar['relations'][0]['editorial_role'] === 'tutorial', 'integrity keeps optional editorial role');
hubIntegrityAssert($pillar['relations'][1]['backlink_evidence']['verification'] === 'integration-available', 'generic provider is not reported as runtime backlink');
hubIntegrityAssert($pillar['health']['status'] === 'broken', 'pillar health is broken when an approved target is unresolved');
hubIntegrityAssert(!empty($pillar['health']['issues']), 'pillar health keeps explicit issue reasons');
$issueCodes = array();
foreach ($pillar['health']['issues'] as $issue) {
    $issueCodes[] = $issue['code'];
}
hubIntegrityAssert(in_array('relation-target-unresolved', $issueCodes, true), 'broken pillar explains unresolved target issue');
hubIntegrityAssert(in_array('reciprocal-link-runtime-unverified', $issueCodes, true), 'pillar explains runtime-unverified reciprocal links');

$summary = HUB_integritySummary(0);
hubIntegrityAssert($summary['schema'] === 1, 'integrity summary schema is explicit');
hubIntegrityAssert($summary['scope'] === 'integrity-0.9', 'integrity summary scope is explicit');
hubIntegrityAssert($summary['pillars'] === 1, 'integrity summary counts pillars');
hubIntegrityAssert($summary['relations'] === 4, 'integrity summary counts relations');
hubIntegrityAssert($summary['unresolved_relations'] === 1, 'integrity summary aggregates unresolved relations');
hubIntegrityAssert($summary['providers']['maps']['unresolved'] === 1, 'integrity summary aggregates provider unresolved state');
hubIntegrityAssert($summary['providers']['videos']['resolved'] === 1, 'integrity summary aggregates provider resolved state');
hubIntegrityAssert($summary['backlink']['integration_available'] === 2, 'integrity summary aggregates integration evidence');
hubIntegrityAssert($summary['health']['broken'] === 1, 'integrity summary aggregates broken pillar status');
hubIntegrityAssert($summary['reciprocal']['hub_rendered'] === 1, 'integrity summary aggregates verified reciprocal links');
hubIntegrityAssert($summary['reciprocal']['fragment_available_runtime_unverified'] === 1, 'integrity summary preserves unverified generic fragment distinction');

$source = file_get_contents(dirname(__DIR__) . '/lib-integrity.php');
hubIntegrityAssert(strpos($source, "verification' => 'integration-available'") === false, 'integrity code does not hardcode a runtime backlink claim in output');
hubIntegrityAssert(strpos($source, 'function HUB_integritySummary(') !== false, 'integrity summary helper exists');

echo "Hub integrity contract tests passed." . PHP_EOL;
