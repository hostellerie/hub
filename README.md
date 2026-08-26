# Hub for Geeklog

Hub is an interoperability and content-relationship plugin for Geeklog.

Version **0.1.0** is intentionally minimal: after installation it adds **Hub** to the administration area and audits active plugins for detectable interoperability capabilities and their installed versions.

## Requirements

- Geeklog 2.1.1 or newer
- PHP 5.6 or newer

## Current audit columns

- Item Info
- Related Items
- Blocks
- Autotags
- Search
- Services
- readiness score
- lifecycle source evidence (`PLG_itemSaved()` / `PLG_itemDeleted()`)
- inferred object types observed in lifecycle calls
- service actions and reflected function signatures
- complete runtime `plugin_*_<plugin>()` API surface

Lifecycle is reported as **source evidence**, not as a runtime guarantee. Hub scans installed plugin PHP code (with comments removed) for lifecycle calls and keeps runtime-detected capabilities visually distinct from inferred/source-detected information.

See `ROADMAP.md` for the planned pillar/relationship implementation.

## 0.1.0 audit details

The audit includes Hub itself, exposes detected callback/function names and autotag names, and provides per-plugin recommendations in collapsed **Details / Recommendations** sections.
