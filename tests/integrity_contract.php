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
            'relation_role' => 'equivalent',
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

function HUB_objectLanguageContext($type, $id, $uid = 0)
{
    if ((string) $type === 'events' && (string) $id === 'event-ok') {
        return array(
            'type' => 'events',
            'id' => 'event-ok',
            'site_language' => 'english',
            'object_language' => 'th',
            'object_language_known' => true,
            'source' => 'provider-item-info-language',
        );
    }

    if ((string) $type === 'staticpages' && (string) $id === 'guide') {
        return array(
            'type' => 'staticpages',
            'id' => 'guide',
            'site_language' => 'english',
            'object_language' => 'en',
            'object_language_known' => true,
            'source' => 'provider-item-info-language',
        );
    }

    return array(
        'type' => (string) $type,
        'id' => (string) $id,
        'site_language' => 'english',
        'object_language' => '',
        'object_language_known' => false,
        'source' => 'unavailable',
    );
}

function HUB_siteUrlContext($url)
{
    $url = (string) $url;
    if ($url === '') {
        return array(
            'url' => '',
            'kind' => 'unknown',
            'host' => '',
            'current_site' => false,
            'cross_site' => false,
        );
    }

    if (strpos($url, 'https://en.example.test/') === 0) {
        return array(
            'url' => $url,
            'kind' => 'absolute',
            'host' => 'en.example.test',
            'current_site' => false,
            'cross_site' => true,
        );
    }

    return array(
        'url' => $url,
        'kind' => strpos($url, 'http') === 0 ? 'absolute' : 'relative',
        'host' => 'example.test',
        'current_site' => true,
        'cross_site' => false,
    );
}

function HUB_graphIdentityKey($type, $id)
{
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);
    return $type === '' || $id === '' ? '' : $type . ':' . $id;
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

function plugin_collectSitemapItems_maps()
{
    return array();
}

function plugin_getfeednames_events()
{
    return array('events');
}

function plugin_getfeedcontent_events()
{
    return array();
}

function HUB_relationObjectTypes()
{
    return array('article', 'videos', 'documents');
}

function HUB_relationCollectionSupported($type)
{
    return in_array((string) $type, array('videos', 'documents'), true);
}

function HUB_relationObjectOptions($type, $limit = 100)
{
    if ($type === 'videos') {
        return array(
            'supported' => true,
            'items' => array(
                array('id' => 'video-ok', 'title' => 'Connected video', 'url' => '/video'),
                array('id' => 'video-free', 'title' => 'Unconnected video', 'url' => '/video-free'),
            ),
            'message' => '',
        );
    }

    if ($type === 'documents') {
        return array(
            'supported' => true,
            'items' => array(
                array('id' => 'doc-free', 'title' => 'Unconnected document', 'url' => '/doc-free'),
            ),
            'message' => '',
        );
    }

    return array(
        'supported' => false,
        'items' => array(),
        'message' => '',
    );
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
        'events:event-ok' => array('title' => 'Event', 'url' => 'https://en.example.test/event', 'exists' => true),
        'staticpages:cycle-a' => array('title' => 'Cycle A', 'url' => '/cycle-a', 'exists' => true),
        'staticpages:cycle-b' => array('title' => 'Cycle B', 'url' => '/cycle-b', 'exists' => true),
        'article:canonical-a' => array('title' => 'Canonical A', 'url' => 'https://example.com/same/', 'exists' => true),
        'article:canonical-b' => array('title' => 'Canonical B', 'url' => 'http://www.example.com/same', 'exists' => true),
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

$sameLanguageEquivalence = HUB_integrityEquivalenceContext(
    array('object_language_known' => true, 'object_language' => 'en'),
    array('object_language_known' => true, 'object_language' => 'en'),
    array('current_site' => true, 'cross_site' => false)
);
hubIntegrityAssert($sameLanguageEquivalence['language_status'] === 'same-language-review', 'same-language equivalence requires review');
hubIntegrityAssert($sameLanguageEquivalence['site_status'] === 'current-site', 'same-site equivalent context is preserved');

$unknownLanguageEquivalence = HUB_integrityEquivalenceContext(
    array('object_language_known' => true, 'object_language' => 'en'),
    array('object_language_known' => false, 'object_language' => ''),
    array('current_site' => false, 'cross_site' => true)
);
hubIntegrityAssert($unknownLanguageEquivalence['language_status'] === 'language-unverified', 'missing provider language keeps equivalent relation unverified');
hubIntegrityAssert($unknownLanguageEquivalence['site_status'] === 'cross-site', 'cross-site evidence is independent from language evidence');

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
hubIntegrityAssert($pillar['cross_site_relations'] === 1, 'pillar integrity counts cross-site relation targets');
hubIntegrityAssert($pillar['current_site_relations'] === 2, 'pillar integrity counts current-site relation targets');
hubIntegrityAssert($pillar['unknown_site_relations'] === 1, 'pillar integrity counts unresolved/unknown site targets');
hubIntegrityAssert($pillar['relations'][3]['site_context']['cross_site'] === true, 'relation exposes cross-site URL context');
hubIntegrityAssert($pillar['relations'][3]['language_context']['object_language'] === 'th', 'relation preserves explicit provider object language');
hubIntegrityAssert($pillar['relations'][3]['relation_role'] === 'equivalent', 'explicit equivalent-content role is preserved');
hubIntegrityAssert($pillar['relations'][3]['equivalence_context']['language_status'] === 'cross-language', 'equivalent relation is classified cross-language from provider metadata');
hubIntegrityAssert($pillar['relations'][3]['equivalence_context']['site_status'] === 'cross-site', 'equivalent relation is classified cross-site from resolved URL');
hubIntegrityAssert($pillar['equivalents']['total'] === 1, 'pillar counts approved equivalent relation');
hubIntegrityAssert($pillar['equivalents']['cross_language'] === 1, 'pillar counts cross-language equivalence');
hubIntegrityAssert($pillar['equivalents']['cross_site'] === 1, 'pillar counts cross-site equivalence');
hubIntegrityAssert($pillar['relations'][0]['language_context']['object_language_known'] === false, 'relation does not infer missing object language');
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
hubIntegrityAssert(strpos($source = file_get_contents(dirname(__DIR__) . '/lib-integrity.php'), "'code' => 'equivalence-review'") !== false, 'equivalence review issue code is available');

$unconnected = HUB_integrityUnconnectedContent(100);
hubIntegrityAssert($unconnected['status'] === 'hub-unconnected', 'unconnected diagnostic uses explicit non-orphan status');
hubIntegrityAssert($unconnected['scope'] === 'shared-content-collections-only', 'unconnected diagnostic scope is explicit');
hubIntegrityAssert($unconnected['total'] === 2, 'hub-unconnected detects collection items outside graph');
hubIntegrityAssert(isset($unconnected['providers']['videos']), 'collection-capable videos provider participates');
hubIntegrityAssert(isset($unconnected['providers']['documents']), 'collection-capable documents provider participates');
hubIntegrityAssert($unconnected['providers']['videos']['hub_unconnected_count'] === 1, 'connected collection item is excluded');
hubIntegrityAssert($unconnected['providers']['videos']['items'][0]['id'] === 'video-free', 'unconnected video identity is retained');
hubIntegrityAssert($unconnected['providers']['videos']['items'][0]['evidence']['signal'] === 'content.collection', 'unconnected evidence names shared collection contract');
hubIntegrityAssert(!isset($unconnected['providers']['article']), 'core article SQL discovery is excluded from provider collection diagnostic');
hubIntegrityAssert(strpos($unconnected['note'], 'does not mean SEO orphan') !== false, 'unconnected diagnostic rejects orphan overclaim');

$distribution = HUB_integrityDistributionOpportunities();
hubIntegrityAssert($distribution['scope'] === 'provider-distribution-contracts', 'distribution diagnostic scope is explicit');
hubIntegrityAssert($distribution['providers']['article']['sitemap']['status'] === 'core-owned', 'distribution diagnostics keep article ownership in Core');
hubIntegrityAssert($distribution['providers']['article']['syndication']['status'] === 'core-owned', 'article syndication is not reassigned to Hub');
hubIntegrityAssert($distribution['providers']['videos']['sitemap']['status'] === 'collection-fallback', 'collection-capable provider exposes sitemap fallback opportunity');
hubIntegrityAssert($distribution['providers']['maps']['sitemap']['status'] === 'native-collector', 'native sitemap collector is preferred');
hubIntegrityAssert($distribution['providers']['events']['syndication']['status'] === 'native-callbacks', 'native feed callbacks are detected');
hubIntegrityAssert(in_array('review-syndication-if-content-is-feed-worthy', $distribution['providers']['videos']['opportunities'], true), 'feed review remains optional and content-dependent');
hubIntegrityAssert(strpos($distribution['note'], 'not SEO failures') !== false, 'distribution opportunities are not reported as SEO failures');

$summary = HUB_integritySummary(0);
hubIntegrityAssert($summary['schema'] === 1, 'integrity summary schema is explicit');
hubIntegrityAssert($summary['scope'] === 'integrity-0.9', 'integrity summary scope is explicit');
hubIntegrityAssert($summary['pillars'] === 1, 'integrity summary counts pillars');
hubIntegrityAssert($summary['relations'] === 4, 'integrity summary counts relations');
hubIntegrityAssert($summary['unresolved_relations'] === 1, 'integrity summary aggregates unresolved relations');
hubIntegrityAssert($summary['cross_site_relations'] === 1, 'integrity summary aggregates cross-site relation count');
hubIntegrityAssert($summary['current_site_relations'] === 2, 'integrity summary aggregates current-site relation count');
hubIntegrityAssert($summary['equivalents']['total'] === 1, 'integrity summary aggregates equivalent relations');
hubIntegrityAssert($summary['equivalents']['cross_language'] === 1, 'integrity summary aggregates cross-language equivalents');
hubIntegrityAssert($summary['providers']['maps']['unresolved'] === 1, 'integrity summary aggregates provider unresolved state');
hubIntegrityAssert($summary['providers']['videos']['resolved'] === 1, 'integrity summary aggregates provider resolved state');
hubIntegrityAssert($summary['backlink']['integration_available'] === 2, 'integrity summary aggregates integration evidence');
hubIntegrityAssert($summary['health']['broken'] === 1, 'integrity summary aggregates broken pillar status');
hubIntegrityAssert($summary['reciprocal']['hub_rendered'] === 1, 'integrity summary aggregates verified reciprocal links');
hubIntegrityAssert($summary['reciprocal']['fragment_available_runtime_unverified'] === 1, 'integrity summary preserves unverified generic fragment distinction');
hubIntegrityAssert(isset($summary['graph']['counts']), 'integrity summary exposes graph diagnostics');
hubIntegrityAssert(isset($summary['canonical_collisions']), 'integrity summary exposes canonical collision diagnostics');
hubIntegrityAssert(isset($summary['unconnected_content']), 'integrity summary exposes provider content unconnected from Hub graph');
hubIntegrityAssert($summary['unconnected_content']['status'] === 'hub-unconnected', 'integrity summary preserves non-orphan terminology');
hubIntegrityAssert(isset($summary['distribution']['providers']), 'integrity summary exposes provider distribution diagnostics');

$hubIntegrityPillars = array(
    array('id' => 10, 'source_type' => 'staticpages', 'source_id' => 'cycle-a', 'editorial_role' => '', 'is_enabled' => 1),
    array('id' => 11, 'source_type' => 'staticpages', 'source_id' => 'cycle-b', 'editorial_role' => '', 'is_enabled' => 1),
);
$hubIntegrityRelations = array(
    10 => array(
        array('id' => 101, 'item_type' => 'staticpages', 'item_id' => 'cycle-b', 'relation_role' => 'sub-pillar', 'editorial_role' => '', 'is_enabled' => 1),
        array('id' => 102, 'item_type' => 'article', 'item_id' => 'canonical-a', 'relation_role' => 'satellite', 'editorial_role' => '', 'is_enabled' => 1),
    ),
    11 => array(
        array('id' => 103, 'item_type' => 'staticpages', 'item_id' => 'cycle-a', 'relation_role' => 'sub-pillar', 'editorial_role' => '', 'is_enabled' => 1),
        array('id' => 104, 'item_type' => 'article', 'item_id' => 'canonical-b', 'relation_role' => 'satellite', 'editorial_role' => '', 'is_enabled' => 1),
        array('id' => 105, 'item_type' => 'staticpages', 'item_id' => 'cycle-b', 'relation_role' => 'related', 'editorial_role' => '', 'is_enabled' => 1),
    ),
);

$graph = HUB_integrityGraphDiagnostics();
hubIntegrityAssert($graph['counts']['cycles'] === 1, 'graph diagnostics detect pillar cycle');
hubIntegrityAssert($graph['counts']['self_relations'] === 1, 'graph diagnostics detect self-relation');
hubIntegrityAssert($graph['counts']['multi_parent_items'] === 1, 'graph diagnostics report multi-parent participation without treating it as an error');

$collisions = HUB_integrityCanonicalCollisions(0);
hubIntegrityAssert(count($collisions) === 1, 'canonical diagnostics detect identities resolving to the same URL');
hubIntegrityAssert(count($collisions[0]['identities']) === 2, 'canonical collision retains both stable identities');
hubIntegrityAssert(HUB_integrityCanonicalUrlKey('https://www.example.com/same/') === HUB_integrityCanonicalUrlKey('http://example.com/same'), 'canonical key ignores scheme www and trailing slash');

$source = file_get_contents(dirname(__DIR__) . '/lib-integrity.php');
hubIntegrityAssert(strpos($source, "verification' => 'integration-available'") === false, 'integrity code does not hardcode a runtime backlink claim in output');
hubIntegrityAssert(strpos($source, 'function HUB_integritySummary(') !== false, 'integrity summary helper exists');

echo "Hub integrity contract tests passed." . PHP_EOL;
