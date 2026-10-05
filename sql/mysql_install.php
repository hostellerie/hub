<?php

$_SQL[] = "CREATE TABLE {$_TABLES['hub_pillars']} (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  source_type varchar(64) NOT NULL,
  source_id varchar(128) NOT NULL,
  editorial_role varchar(32) NOT NULL DEFAULT '',
  is_enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
  created int(11) unsigned NOT NULL DEFAULT 0,
  modified int(11) unsigned NOT NULL DEFAULT 0,
  owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
  PRIMARY KEY (id),
  UNIQUE KEY source_identity (source_type, source_id),
  KEY is_enabled (is_enabled)
) ENGINE=MyISAM";

$_SQL[] = "CREATE TABLE {$_TABLES['hub_relations']} (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  pillar_id int(11) unsigned NOT NULL,
  item_type varchar(64) NOT NULL,
  item_id varchar(128) NOT NULL,
  relation_role varchar(32) NOT NULL DEFAULT 'related',
  editorial_role varchar(32) NOT NULL DEFAULT '',
  position smallint(5) unsigned NOT NULL DEFAULT 0,
  is_enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
  created int(11) unsigned NOT NULL DEFAULT 0,
  modified int(11) unsigned NOT NULL DEFAULT 0,
  owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
  PRIMARY KEY (id),
  UNIQUE KEY pillar_item (pillar_id, item_type, item_id),
  KEY pillar_order (pillar_id, is_enabled, position, id),
  KEY item_identity (item_type, item_id)
) ENGINE=MyISAM";


$_SQL[] = "CREATE TABLE {$_TABLES['hub_suggestion_decisions']} (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  suggestion_kind varchar(32) NOT NULL,
  pillar_id int(11) unsigned NOT NULL DEFAULT 0,
  item_type varchar(64) NOT NULL,
  item_id varchar(128) NOT NULL,
  decision varchar(16) NOT NULL,
  defer_until int(11) unsigned NOT NULL DEFAULT 0,
  created int(11) unsigned NOT NULL DEFAULT 0,
  modified int(11) unsigned NOT NULL DEFAULT 0,
  owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
  PRIMARY KEY (id),
  UNIQUE KEY suggestion_identity (suggestion_kind, pillar_id, item_type, item_id),
  KEY active_decision (decision, defer_until),
  KEY item_identity (item_type, item_id)
) ENGINE=MyISAM";
