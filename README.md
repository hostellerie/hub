# Hub for Geeklog

Hub is an interoperability and content-relationship plugin for Geeklog.

Version **0.2.0** keeps the read-only interoperability and article-link audits while adding generic capability and service discovery for reusable Geeklog interoperability.

## Requirements

- Geeklog 2.1.1 or newer
- PHP 5.6 or newer

## Article link audit (introduced in 0.1.1)

From **Hub administration → Article link audit**, select a destination Static Page and click **Run audit**.

Hub discovers the topics assigned to that Static Page automatically, gathers the published non-draft articles from those topics, deduplicates articles assigned to more than one matching topic, and lists only the articles whose introduction and body do not contain a hyperlink to the selected page. Each suggestion also shows the matching assigned topic(s), making the editorial reason for the suggestion explicit. Relative and absolute links, HTTP/HTTPS, www/non-www and equivalent query-string order are recognized. Each result provides **View** and **Edit** links.

When Hub is enabled, normal Static Page rendering also appends the visible topics assigned to that page using Geeklog's native `TOPIC_relatedTopics()` renderer. Static Pages therefore use the same localized `related-topics` markup as articles (for example `Classé dans :` in French). The stored `sp_content` is not modified. The audit also exposes the topics assigned to the selected Static Page. An administrator can optionally edit the page and place any of those topic links manually at a preferred position in the page content.

The audit does not modify article content. This first implementation targets Geeklog core articles and Static Pages only.

## Static Page topic semantics

Geeklog Static Pages historically use topic assignments partly as placement rules, especially for center-block display. Hub therefore distinguishes **specific Geeklog topics** from the native **All** and **Home page only** placement options.

- `All` and `Home page only` are never treated as editorial topic relationships.
- Specific topic assignments may be used by Hub as a discovery/suggestion signal.
- A shared Geeklog topic is not an approved editorial relationship.
- Future Hub pillar/relationship records remain explicit and independent from Static Page placement settings.

Hub does not require existing Static Pages to be reconfigured merely to satisfy Hub.

## Generic capability declarations

Hub now detects optional generic `plugin_getcapabilities_*()` declarations when a plugin exposes them. The expected declaration is versioned with a `schema` value and may advertise generic `roles`, `capabilities`, and an optional `lifecycle` block describing emitted/listened events and `sub_type` support.

These declarations are treated as plugin-supplied metadata only. Hub keeps them separate from runtime callback detection, source evidence, permissions and inferred role/readiness. The optional `lifecycle` block remains declarative metadata and never replaces runtime/source proof. This avoids inventing a Hub-specific capability API while allowing plugins such as Videos or Monitor to describe capabilities that cannot be inferred safely.

## Reusable service catalogue

Hub 0.2.0 exposes a reusable runtime catalogue through `HUB_serviceCatalogue()`. It reports whether the plugin service dispatcher is present and lists loaded `service_*_<plugin>()` actions with reflected signatures. The catalogue is descriptive only: Hub does not invoke the service, bypass ACL checks, or take ownership of the service implementation.

Hub itself now advertises its current orchestrator capabilities through `plugin_getcapabilities_hub()`, using the same generic declaration contract consumed from other plugins.

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

## Audit cache

The Plugin Interoperability Audit caches its normalized audit data for 10 minutes through Geeklog's native cache API. The cache is keyed by a fingerprint containing the active plugin list and versions plus the Hub, Geeklog and PHP versions. The administration page provides **Refresh audit** to bypass the cache explicitly, which is especially useful during plugin development when source code changes without a version bump.

The HTML audit and Markdown export consume the same cached dataset.

## Markdown export

The administration audit includes **Export audit as Markdown (.md)**. The export filename and report title identify the audited site using Geeklog's configured site name. The report contains environment information, the summary matrix, inferred roles, role-aware readiness, callbacks, lifecycle evidence, object types, service signatures, advanced API surface and developer recommendations. It can be attached directly to an issue or sent to a plugin maintainer.

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

The `Build installable archive` GitHub Actions workflow creates `dist/hub-0.2.0.zip`. The ZIP contains one top-level `hub/` directory and can be uploaded through Geeklog's plugin installer.
