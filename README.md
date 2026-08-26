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
- ID to URL (`plugin_idtourl_*`) when available
- Blocks
- Autotags
- Search
- Services and reflected service signatures/actions
- role-aware readiness (Content, Presentation, Service, Infrastructure, Orchestrator)
- Lifecycle **emitter** source evidence (`PLG_itemSaved()` / `PLG_itemDeleted()` calls)
- Lifecycle **listener** callbacks (`plugin_itemsaved_*()` / `plugin_itemdeleted_*()`)
- lifecycle contract compatibility, including `sub_type`-aware callbacks on newer Geeklog versions
- object types discovered from `plugin_searchtypes_*()` when it can be called safely
- object types observed as literal lifecycle types
- primary type inference from `plugin_getiteminfo_*()`
- additional Geeklog capabilities such as language overrides, user-contributed content and reCAPTCHA support
- native statistics contribution detection (`plugin_showstats_*`, `plugin_statssummary_*`) reported as **Full / Partial / None** without affecting readiness scores
- complete runtime `plugin_*_<plugin>()` API surface under a collapsed Advanced section
- Markdown export suitable for sharing with plugin developers

Runtime-detected callbacks are kept distinct from source evidence and inference. Hub never presents source scanning as proof that every mutation path emits a lifecycle notification.

Object-type evidence from several sources is merged into one entry instead of displaying duplicates. This lets Hub learn as much as possible from existing Geeklog conventions before requiring any Hub-specific capability contract.

Statistics are treated as a transversal Geeklog capability, not as a Hub readiness requirement. Hub may later aggregate or present plugin-provided statistics through native Geeklog callbacks without reading plugin tables directly.

See `ROADMAP.md` for the planned pillar/relationship implementation.
