# Hub for Geeklog

Hub is an interoperability and content-relationship plugin for Geeklog.

Version **0.1.1-dev** keeps the interoperability audit and adds a read-only article link audit for immediate editorial cleanup.

## Requirements

- Geeklog 2.1.1 or newer
- PHP 5.6 or newer

## Article link audit (0.1.1-dev)

From **Hub administration → Article link audit**, select:

1. a Geeklog topic;
2. a destination Static Page;
3. **Run audit**.

Hub lists published, non-draft articles assigned directly to that topic whose introduction and body do not contain a hyperlink to the selected page. Relative and absolute links, HTTP/HTTPS, www/non-www and equivalent query-string order are recognized. Each result provides **View** and **Edit** links.

The audit does not modify article content. This first implementation targets Geeklog core articles and Static Pages only.

## Current interoperability audit

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

## Development archive

The `Build installable archive` GitHub Actions workflow creates `dist/hub-0.1.1-dev.zip`. The ZIP contains one top-level `hub/` directory and can be uploaded through Geeklog's plugin installer.
