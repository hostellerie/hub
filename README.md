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

## Role-aware readiness

Hub infers a plugin role from existing Geeklog capabilities before scoring interoperability: **Content**, **Presentation**, **Service**, **Infrastructure**, or **Orchestrator**. Content plugins are evaluated on Item Info, stable object types and save/delete lifecycle emission; presentation and service plugins are evaluated against their own role; infrastructure plugins are not penalized for missing content APIs.

## Markdown export

The administration audit includes **Export audit as Markdown (.md)**. The report contains environment information, the summary matrix, inferred roles, role-aware readiness, callbacks, lifecycle evidence, object types, service signatures, advanced API surface and developer recommendations. It can be attached directly to an issue or sent to a plugin maintainer.

### Final 0.1.0 audit additions

- detects `plugin_idtourl_*()` as an optional content interoperability capability
- reports whether lifecycle listener callbacks are `sub_type`-aware (Geeklog 2.2.x) or legacy/no-`sub_type` (Geeklog 2.1.x)
- lists additional Geeklog capabilities such as `plugin_getlanguageoverrides_*()`, `plugin_usercontributed_*()` and `plugin_supportsrecaptcha_*()`
- exports all of the above in the Markdown developer report
- lifecycle source detection is token-based (`token_get_all()`), avoiding strings/comments/regex false positives

## Statistics capability

The audit reports native Geeklog statistics contribution through `plugin_showstats_*()` and `plugin_statssummary_*()` as **Full**, **Partial** or **None**. Statistics are informational and do not affect Hub readiness scores. This keeps open a future path for Hub to aggregate or present plugin-provided statistics without reading plugin tables directly.

## Distribution capabilities

The audit also reports native Geeklog distribution contracts without changing Hub readiness scores:

- **Content Syndication**: `plugin_getfeednames_*()`, `plugin_getfeedcontent_*()` and optional `plugin_feedupdatecheck_*()`
- **XML Sitemap contribution**: native `plugin_collectSitemapItems_*()` when available, with the XMLSitemap plugin's `PLG_getItemInfo(type, '*', ...)` fallback reported separately

These capabilities are informational interoperability signals. Hub does not read another plugin's tables to infer them.
