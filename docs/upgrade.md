# Hub 1.0 — Installation and upgrade path

Hub 1.0 keeps pre-release development installs upgradeable without requiring a database reset.

## Fresh install

A fresh 1.0 install creates the current Hub tables directly:

- `hub_pillars`;
- `hub_relations`;
- `hub_suggestion_decisions`.

The fresh schema includes structural relation roles and optional editorial roles.

## Upgrade sequence

`plugin_upgrade_hub()` keeps migrations ordered chronologically:

1. pre-0.3 installs receive the pillar/relation foundation;
2. pre-0.5 installs receive structural relation roles and obsolete `title_override` cleanup;
3. every upgrade runs the idempotent 0.8 schema reconciliation for suggestion decisions and editorial-role columns;
4. only after successful migration does Hub update the installed plugin version.

No 0.9 or 0.10 schema migration is required because those milestones added read models, diagnostics, service surfaces and contextual metadata without new persisted columns.

## Idempotence

The 0.8 reconciliation intentionally runs even when an installation already reports a newer Hub version.

It uses:

- `CREATE TABLE IF NOT EXISTS`;
- `SHOW COLUMNS` checks before additive `ALTER TABLE` statements.

This makes shared-file/staggered multisite upgrades safer: loading newer plugin files does not require every site to mutate its persisted state at the same instant.

## Data ownership

Migrations change only Hub-owned persistence.

They do not:

- rewrite provider content;
- migrate provider tables;
- store provider canonical URLs;
- create a second multisite registry.

## Pre-release upgrade coverage

The 1.0 repository contract verifies that the chronological migration chain remains present and that fresh-install SQL contains the final schema elements expected from the upgrade path.

Before publishing 1.0, perform at least one real database smoke test using:

- a fresh Geeklog 2.2.2 installation;
- an older Hub persisted schema upgraded in place;
- a Geeklog 2.1.1 compatibility installation when available.

The automated contract test complements, but does not replace, those real installation tests.
