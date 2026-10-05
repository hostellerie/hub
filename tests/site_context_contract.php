<?php

$_SERVER['PHP_SELF'] = 'tests/site_context_contract.php';
$_SERVER['HTTP_HOST'] = 'wrong-host.example.test';

$_CONF = array(
    'site_url' => 'https://fr.example.test',
    'site_admin_url' => 'https://fr.example.test/admin',
    'site_name' => 'Example FR',
    'language' => 'french_utf-8',
);

function PLG_getItemInfo($type, $id, $what, $uid = 0, $options = array())
{
    if ($what !== 'language') {
        return '';
    }

    if ($type === 'documents' && $id === 'doc-fr') {
        return 'fr';
    }

    return '';
}

require_once dirname(__DIR__) . '/lib-site-context.php';

function hubSiteAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$context = HUB_siteContext();
hubSiteAssert($context['schema'] === 1, 'site context schema is explicit');
hubSiteAssert($context['site_url'] === 'https://fr.example.test', 'site context uses active Geeklog site URL');
hubSiteAssert($context['host'] === 'fr.example.test', 'site context derives host from Geeklog configuration');
hubSiteAssert($context['language'] === 'french_utf-8', 'site context exposes active Geeklog language');
hubSiteAssert($context['source'] === 'active-geeklog-config', 'site context names its authoritative source');
hubSiteAssert($context['host'] !== $_SERVER['HTTP_HOST'], 'site context does not re-run HTTP host selection');

$relative = HUB_siteUrlContext('/documents/item/1');
hubSiteAssert($relative['kind'] === 'relative', 'relative URL is identified');
hubSiteAssert($relative['current_site'] === true, 'relative URL belongs to active site');
hubSiteAssert($relative['cross_site'] === false, 'relative URL is not cross-site');

$current = HUB_siteUrlContext('https://fr.example.test/maps/1');
hubSiteAssert($current['current_site'] === true, 'same configured host is current site');
hubSiteAssert($current['cross_site'] === false, 'same configured host is not cross-site');

$external = HUB_siteUrlContext('https://en.example.test/maps/1');
hubSiteAssert($external['current_site'] === false, 'different host is not current site');
hubSiteAssert($external['cross_site'] === true, 'different host is classified cross-site');

$knownLanguage = HUB_objectLanguageContext('documents', 'doc-fr', 0);
hubSiteAssert($knownLanguage['object_language_known'] === true, 'provider explicit object language is accepted');
hubSiteAssert($knownLanguage['object_language'] === 'fr', 'provider language value is preserved');
hubSiteAssert($knownLanguage['source'] === 'provider-item-info-language', 'provider language provenance is explicit');
hubSiteAssert($knownLanguage['site_language'] === 'french_utf-8', 'site language remains separate from object language');

$unknownLanguage = HUB_objectLanguageContext('documents', 'doc-unknown', 0);
hubSiteAssert($unknownLanguage['object_language_known'] === false, 'missing provider language remains unknown');
hubSiteAssert($unknownLanguage['object_language'] === '', 'Hub does not infer object language from site language');
hubSiteAssert($unknownLanguage['site_language'] === 'french_utf-8', 'unknown object still exposes active site language context');

$source = file_get_contents(dirname(__DIR__) . '/lib-site-context.php');
hubSiteAssert(strpos($source, 'HTTP_HOST') !== false, 'site context documents HTTP_HOST boundary');
hubSiteAssert(strpos($source, '\$_SERVER[\'HTTP_HOST\']') === false, 'site context never reads HTTP_HOST');

echo "Hub site context contract tests passed." . PHP_EOL;
