<?php

$root = dirname(__DIR__);
$upgrade = file_get_contents($root . '/install_updates.php');
$functions = file_get_contents($root . '/functions.inc');
$sql = file_get_contents($root . '/sql/mysql_install.php');
$config = file_get_contents($root . '/config.php');

function hubUpgradeAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

hubUpgradeAssert(strpos($config, "'pi_version'    => '1.0.0'") !== false, '1.0 code version is explicit');

hubUpgradeAssert(
    strpos($functions, "version_compare((string) $installedVersion, '0.3.0', '<')") !== false,
    'upgrade path retains pre-0.3 migration gate'
);
hubUpgradeAssert(
    strpos($functions, "version_compare((string) $installedVersion, '0.5.0', '<')") !== false,
    'upgrade path retains pre-0.5 migration gate'
);
hubUpgradeAssert(
    strpos($functions, 'HUB_updateSchema_0_8_0()') !== false,
    'upgrade path always runs the idempotent 0.8 schema reconciliation'
);

$pos03 = strpos($functions, 'HUB_updateSchema_0_3_0()');
$pos05 = strpos($functions, 'HUB_updateSchema_0_5_0()');
$pos08 = strpos($functions, 'HUB_updateSchema_0_8_0()');
hubUpgradeAssert($pos03 !== false && $pos05 !== false && $pos08 !== false && $pos03 < $pos05 && $pos05 < $pos08, 'schema migrations run in chronological order');

hubUpgradeAssert(strpos($upgrade, 'CREATE TABLE IF NOT EXISTS') !== false, 'upgrade creates missing tables idempotently');
hubUpgradeAssert(strpos($upgrade, "SHOW COLUMNS FROM") !== false, 'upgrade feature-detects existing columns before ALTER');
hubUpgradeAssert(strpos($upgrade, "DROP COLUMN title_override") !== false, 'upgrade removes obsolete title override persistence');

foreach (array(
    "relation_role varchar(32) NOT NULL DEFAULT 'related'",
    "editorial_role varchar(32) NOT NULL DEFAULT ''",
    "hub_suggestion_decisions",
) as $needle) {
    hubUpgradeAssert(strpos($sql, $needle) !== false, 'fresh install contains current schema element: ' . $needle);
    hubUpgradeAssert(strpos($upgrade, $needle) !== false || $needle === "editorial_role varchar(32) NOT NULL DEFAULT ''", 'upgrade path contains current schema element: ' . $needle);
}

hubUpgradeAssert(
    substr_count($sql, "editorial_role varchar(32) NOT NULL DEFAULT ''") >= 2,
    'fresh install contains editorial roles for pillars and relations'
);
hubUpgradeAssert(
    strpos($upgrade, "ADD editorial_role varchar(32) NOT NULL DEFAULT '' AFTER source_id") !== false,
    'upgrade adds pillar editorial role'
);
hubUpgradeAssert(
    strpos($upgrade, "ADD editorial_role varchar(32) NOT NULL DEFAULT '' AFTER relation_role") !== false,
    'upgrade adds relation editorial role'
);

hubUpgradeAssert(
    strpos($functions, "UPDATE {$_TABLES['plugins']} SET pi_version") !== false,
    'successful upgrade records current plugin version'
);
hubUpgradeAssert(
    strpos($functions, "pi_gl_version = '2.1.1'") !== false,
    'upgrade preserves Geeklog 2.1.1 compatibility baseline'
);

echo "Hub upgrade contract tests passed." . PHP_EOL;
