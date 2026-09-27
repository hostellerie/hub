<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function HUB_updateSchema_0_3_0()
{
    global $_TABLES;

    $queries = array(
        "CREATE TABLE IF NOT EXISTS {$_TABLES['hub_pillars']} (
          id int(11) unsigned NOT NULL AUTO_INCREMENT,
          source_type varchar(64) NOT NULL,
          source_id varchar(128) NOT NULL,
          title_override varchar(255) NOT NULL DEFAULT '',
          is_enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
          created int(11) unsigned NOT NULL DEFAULT 0,
          modified int(11) unsigned NOT NULL DEFAULT 0,
          owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
          PRIMARY KEY (id),
          UNIQUE KEY source_identity (source_type, source_id),
          KEY is_enabled (is_enabled)
        ) ENGINE=MyISAM",
        "CREATE TABLE IF NOT EXISTS {$_TABLES['hub_relations']} (
          id int(11) unsigned NOT NULL AUTO_INCREMENT,
          pillar_id int(11) unsigned NOT NULL,
          item_type varchar(64) NOT NULL,
          item_id varchar(128) NOT NULL,
          position smallint(5) unsigned NOT NULL DEFAULT 0,
          is_enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
          created int(11) unsigned NOT NULL DEFAULT 0,
          modified int(11) unsigned NOT NULL DEFAULT 0,
          owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
          PRIMARY KEY (id),
          UNIQUE KEY pillar_item (pillar_id, item_type, item_id),
          KEY pillar_order (pillar_id, is_enabled, position, id),
          KEY item_identity (item_type, item_id)
        ) ENGINE=MyISAM",
    );

    foreach ($queries as $sql) {
        DB_query($sql, 1);
        if (DB_error()) {
            if (function_exists('COM_errorLog')) {
                COM_errorLog('Hub 0.3.0 schema upgrade failed.');
            }
            return false;
        }
    }

    return true;
}
