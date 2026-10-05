<?php

$_SERVER['PHP_SELF'] = 'tests/editorial_contract.php';

$hubEditorialPillars = array(
    array('id' => 1, 'source_type' => 'staticpages', 'source_id' => 'hub-a', 'is_enabled' => 1),
    array('id' => 2, 'source_type' => 'staticpages', 'source_id' => 'pillar-b', 'is_enabled' => 1),
    array('id' => 3, 'source_type' => 'staticpages', 'source_id' => 'hub-c', 'is_enabled' => 1),
);

$hubEditorialRelations = array(
    1 => array(
        array('item_type' => 'staticpages', 'item_id' => 'pillar-b', 'relation_role' => 'sub-pillar', 'is_enabled' => 1),
        array('item_type' => 'article', 'item_id' => 'story-x', 'relation_role' => 'satellite', 'is_enabled' => 1),
    ),
    2 => array(
        array('item_type' => 'article', 'item_id' => 'story-y', 'relation_role' => 'satellite', 'is_enabled' => 1),
    ),
    3 => array(
        array('item_type' => 'staticpages', 'item_id' => 'pillar-b', 'relation_role' => 'sub-pillar', 'is_enabled' => 1),
        array('item_type' => 'article', 'item_id' => 'story-x', 'relation_role' => 'support', 'is_enabled' => 1),
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

function HUB_graphIdentityKey($type, $id)
{
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);
    return $type === '' || $id === '' ? '' : $type . ':' . $id;
}

function HUB_getPillars($includeDisabled = true)
{
    global $hubEditorialPillars;
    return $hubEditorialPillars;
}

function HUB_getRelations($pillarId, $includeDisabled = true)
{
    global $hubEditorialRelations;
    return isset($hubEditorialRelations[(int) $pillarId])
        ? $hubEditorialRelations[(int) $pillarId]
        : array();
}

function HUB_staticPageTopicContext($pageId)
{
    if ((string) $pageId !== 'hub-a' && (string) $pageId !== 'new-pillar') {
        return array('specific_topics' => array());
    }

    return array(
        'specific_topics' => array(
            array('tid' => 'seo', 'topic' => 'SEO'),
            array('tid' => 'content', 'topic' => 'Content'),
        ),
    );
}

function HUB_linkAuditArticlesByTopics(array $topicIds)
{
    return array(
        array(
            'sid' => 'story-x',
            'title' => 'Already approved story',
            'hub_topics' => array('seo' => 'SEO'),
            'hits' => 100,
            'comments' => 4,
            'date' => '2025-01-10 12:00:00',
        ),
        array(
            'sid' => 'story-new',
            'title' => 'Candidate story 2024 for version 26.8',
            'introtext' => 'Migration notes for v1.5.0.',
            'bodytext' => '',
            'hub_topics' => array('seo' => 'SEO', 'content' => 'Content'),
            'hits' => 250,
            'comments' => 9,
            'date' => '2024-06-15 09:30:00',
        ),
    );
}

function HUB_linkAuditStaticPages()
{
    return array(
        array('sp_id' => 'hub-a', 'sp_title' => 'Existing pillar'),
        array('sp_id' => 'new-pillar', 'sp_title' => 'New pillar candidate'),
    );
}

require_once dirname(__DIR__) . '/lib-editorial.php';

function hubEditorialAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$summary = HUB_editorialSummary(false);

hubEditorialAssert($summary['schema'] === 1, 'editorial summary schema is explicit');
hubEditorialAssert($summary['pillars'] === 3, 'editorial summary counts approved pillars');
hubEditorialAssert($summary['relations'] === 5, 'editorial summary counts approved relations');
hubEditorialAssert($summary['roles']['sub-pillar'] === 2, 'sub-pillar edges are counted');
hubEditorialAssert($summary['roles']['satellite'] === 2, 'satellite edges are counted');
hubEditorialAssert($summary['roles']['support'] === 1, 'support edges are counted');
hubEditorialAssert($summary['roles']['related'] === 0, 'neutral related edges are counted separately');
hubEditorialAssert($summary['providers']['staticpages'] === 2, 'Static Page provider count is normalized');
hubEditorialAssert($summary['providers']['article'] === 3, 'article provider count is normalized');
hubEditorialAssert($summary['nested_pillars'] === 1, 'related identities that are also pillars are counted as nested pillars');
hubEditorialAssert($summary['multi_parent_items'] === 2, 'multi-parent identities are counted without enforcing a tree');
hubEditorialAssert(count($summary['pillar_items']) === 3, 'per-pillar structural summaries are exposed');
hubEditorialAssert($summary['pillar_items'][0]['pillar_id'] === 1, 'pillar summaries are deterministic by id');

$inventory = HUB_editorialInventory(false);
hubEditorialAssert($inventory['schema'] === 1, 'editorial inventory schema is explicit');
hubEditorialAssert(count($inventory['pillars']) === 3, 'editorial inventory exposes every approved pillar');
hubEditorialAssert(count($inventory['pillars'][0]['items']) === 2, 'pillar inventory exposes approved relation identities');
$inventoryByIdentity = array();
foreach ($inventory['pillars'][0]['items'] as $inventoryItem) {
    $inventoryByIdentity[$inventoryItem['type'] . ':' . $inventoryItem['id']] = $inventoryItem;
}
hubEditorialAssert(isset($inventoryByIdentity['staticpages:pillar-b']), 'inventory contains the approved nested pillar identity');
hubEditorialAssert($inventoryByIdentity['staticpages:pillar-b']['relation_role'] === 'sub-pillar', 'inventory preserves structural relation roles');
hubEditorialAssert($inventoryByIdentity['staticpages:pillar-b']['is_nested_pillar'] === true, 'inventory identifies related objects that are also pillars');
hubEditorialAssert($inventoryByIdentity['staticpages:pillar-b']['nested_pillar_id'] === 2, 'inventory exposes nested pillar identity without duplicating content');
hubEditorialAssert($inventoryByIdentity['staticpages:pillar-b']['parent_count'] === 2, 'inventory exposes multi-parent participation');
hubEditorialAssert(isset($inventoryByIdentity['article:story-x']), 'inventory remains provider-neutral by stable type + id');

$suggestions = HUB_editorialSuggestions(1, 10);
hubEditorialAssert($suggestions['schema'] === 1, 'suggestion schema is explicit');
hubEditorialAssert($suggestions['generated_from'] === array('shared-topic'), 'suggestion source is explicit and explainable');
hubEditorialAssert(count($suggestions['pillars']) === 1, 'suggestions can be scoped to one pillar');
hubEditorialAssert(count($suggestions['pillars'][0]['candidates']) === 1, 'already approved identities are excluded from candidates');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['id'] === 'story-new', 'unapproved matching article is suggested');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['suggested_role'] === 'satellite', 'candidate role remains a suggestion only');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['score'] === 2, 'candidate score is deterministic from matched topics');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][0]['signal'] === 'shared-topic', 'candidate retains evidence signal');
hubEditorialAssert(count($suggestions['pillars'][0]['candidates'][0]['evidence'][0]['topics']) === 2, 'candidate retains matched topic evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['ranking']['views'] === 250, 'candidate exposes engagement ranking evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['ranking']['comments'] === 9, 'candidate exposes comment ranking evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['ranking']['published_year'] === 2024, 'candidate exposes publication year');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][1]['signal'] === 'engagement', 'candidate keeps engagement as transparent evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][2]['signal'] === 'publication-date', 'candidate keeps publication date as transparent evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][3]['signal'] === 'temporal-marker', 'candidate carries temporal-marker evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][3]['review_recommended'] === true, 'candidate temporal markers recommend human review');
hubEditorialAssert(in_array('engagement', $suggestions['ranking_signals'], true), 'suggestion payload declares engagement ranking signal');
hubEditorialAssert(in_array('publication-date', $suggestions['ranking_signals'], true), 'suggestion payload declares publication-date ranking signal');
hubEditorialAssert(in_array('older-year-marker', $suggestions['review_signals'], true), 'suggestion payload declares older year review signal');
hubEditorialAssert(in_array('version-marker', $suggestions['review_signals'], true), 'suggestion payload declares version review signal');

$temporal = HUB_editorialTemporalSignals(array(
    'title' => 'LibreOffice 2024 and 26.8',
    'introtext' => 'Upgrade notes for v1.5.0',
    'bodytext' => '',
    'date' => '2023-05-01 10:00:00',
), 2026);
hubEditorialAssert($temporal['publication_age_years'] === 3, 'temporal signal exposes publication age');
hubEditorialAssert($temporal['explicit_years'] === array(2024), 'temporal signal extracts explicit year markers');
hubEditorialAssert(in_array('26.8', $temporal['version_markers'], true), 'temporal signal extracts two-part version markers');
hubEditorialAssert(in_array('v1.5.0', $temporal['version_markers'], true), 'temporal signal extracts prefixed semantic versions');
hubEditorialAssert($temporal['review_recommended'] === true, 'older year/version markers recommend editorial review');
hubEditorialAssert(in_array('older-year-marker', $temporal['review_reasons'], true), 'older year reason is explicit');
hubEditorialAssert(in_array('version-marker', $temporal['review_reasons'], true), 'version reason is explicit');

$temporalNeutral = HUB_editorialTemporalSignals(array(
    'title' => 'Evergreen guide',
    'introtext' => 'General principles without dated markers',
    'bodytext' => '',
    'date' => '2025-01-01 00:00:00',
), 2026);
hubEditorialAssert($temporalNeutral['review_recommended'] === false, 'publication age alone does not mark evergreen content for review');
hubEditorialAssert(empty($temporalNeutral['review_reasons']), 'neutral content has no temporal review reason');

$allSuggestions = HUB_editorialSuggestions(0, 10);
hubEditorialAssert(isset($allSuggestions['pillar_candidates']), 'global suggestions expose pillar candidates');
hubEditorialAssert(count($allSuggestions['pillar_candidates']) === 1, 'existing Static Page pillars are excluded from pillar candidates');
hubEditorialAssert($allSuggestions['pillar_candidates'][0]['id'] === 'new-pillar', 'unapproved Static Page is suggested as a pillar candidate');
hubEditorialAssert($allSuggestions['pillar_candidates'][0]['score'] === 2, 'pillar candidate score is deterministic from matching published articles');
hubEditorialAssert($allSuggestions['pillar_candidates'][0]['evidence'][0]['signal'] === 'shared-topic', 'pillar candidate retains shared-topic evidence');
hubEditorialAssert($allSuggestions['pillar_candidates'][0]['evidence'][0]['matching_article_count'] === 2, 'pillar candidate exposes matching article count');

$source = file_get_contents(dirname(__DIR__) . '/lib-editorial.php');
$summaryStart = strpos($source, 'function HUB_editorialSummary(');
$summaryEnd = strpos($source, 'function HUB_editorialInventory(', $summaryStart);
$summarySource = ($summaryStart !== false && $summaryEnd !== false)
    ? substr($source, $summaryStart, $summaryEnd - $summaryStart)
    : '';

hubEditorialAssert(strpos($summarySource, 'HUB_resolveObject(') === false, 'structural summary does not mix provider metadata resolution');
hubEditorialAssert(strpos($summarySource, 'HUB_linkAudit') === false, 'structural summary does not mix SEO/link diagnostics');
hubEditorialAssert(strpos($source, 'HUB_saveRelation(') === false, 'suggestion model never auto-approves relationships');

$adminSource = file_get_contents(dirname(__DIR__) . '/admin/editorial.php');
hubEditorialAssert(strpos($adminSource, 'Approved relation inventory') !== false, 'editorial admin exposes approved structural inventory');
hubEditorialAssert(strpos($adminSource, 'HUB_editorialInventory(false)') !== false, 'editorial admin reads the shared inventory model');
hubEditorialAssert(strpos($adminSource, 'HUB_saveRelation(') === false, 'editorial mapping view remains read-only');
hubEditorialAssert(strpos($adminSource, 'Review signal:') !== false, 'temporal review signals are visible in admin');
hubEditorialAssert(strpos($adminSource, 'dated marker:') !== false, 'admin explains dated marker evidence');
hubEditorialAssert(strpos($adminSource, 'version marker:') !== false, 'admin explains version marker evidence');

echo "Hub editorial summary contract tests passed." . PHP_EOL;
