# Hub for Geeklog

Hub is an interoperability and content-relationship plugin for Geeklog.

Version **0.1.0** is intentionally minimal: after installation it adds **Hub** to the administration area and audits active plugins for detectable interoperability capabilities and their installed versions.

## Requirements

- Geeklog 2.1.1 or newer
- PHP 5.6 or newer

## Current audit

The permanent Plugin Interoperability Audit reports:

- Item Info
- Related Items
- Blocks
- Autotags
- Search
- Services and reflected service signatures/actions
- readiness score
- Lifecycle **emitter** source evidence (`PLG_itemSaved()` / `PLG_itemDeleted()` calls)
- Lifecycle **listener** callbacks (`plugin_itemsaved_*()` / `plugin_itemdeleted_*()`)
- object types discovered from `plugin_searchtypes_*()` when it can be called safely
- object types observed as literal lifecycle types
- primary type inference from `plugin_getiteminfo_*()`
- complete runtime `plugin_*_<plugin>()` API surface under a collapsed Advanced section

Runtime-detected callbacks are kept distinct from source evidence and inference. Hub never presents source scanning as proof that every mutation path emits a lifecycle notification.

Object-type evidence from several sources is merged into one entry instead of displaying duplicates. This lets Hub learn as much as possible from existing Geeklog conventions before requiring any Hub-specific capability contract.

See `ROADMAP.md` for the planned pillar/relationship implementation.

## 0.1.0 audit details

The audit includes Hub itself, exposes detected callback/function names and autotag names, and provides per-plugin recommendations in collapsed **Details / Recommendations** sections. The most Hub-relevant information is shown first; embedding/discovery helpers are separated and the full Plugin API surface is hidden under **Advanced API surface** by default.
