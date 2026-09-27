# Hub for Geeklog

Hub is an interoperability and content-relationship plugin for Geeklog.

Version **0.4.0** builds public bidirectional navigation on top of the finalized 0.3.0 pillar and relationship model.

## Requirements

- Geeklog 2.1.1 or newer
- PHP 5.6 or newer

## Pillars and manual relations (finalized in 0.3.0)

From **Hub administration → Pillars & manual relations**, an administrator can create a Hub pillar from a Geeklog Static Page and attach complementary content by stable `type + id` identity.

Hub stores:

- the pillar source as `source_type + source_id`;
- related items as `item_type + item_id`;
- manual order;
- enabled/disabled state;
- optional pillar title override.

Hub does **not** store another plugin's canonical URL as source of truth. Titles and URLs are resolved dynamically through `PLG_getItemInfo()` when available, so ownership remains with the source plugin.

The initial 0.3.0 target is deliberately limited to Static Pages as pillars. Relations may point to any provider/object identity that Geeklog can resolve. Static Page topic assignments remain a discovery signal only and are independent from explicit Hub relationships.

The relation editor now lists only active, resolvable Geeklog Item Info provider types. When the selected provider exposes the shared `content.collection` / `plugin_getiteminfo_*('*', ...)` contract, Hub loads up to 100 selectable objects dynamically and stores only the selected stable ID. Providers without a generic collection remain usable through an explicit manual-ID fallback. Hub does not query plugin-private tables.

Saved relations display integrity diagnostics when an identity can no longer be resolved; Hub keeps the stable identity instead of deleting it automatically because a provider may simply be disabled or temporarily unavailable.

The **Find suggestions** mode adds a deliberately small 0.3.0 editorial aid. Hub can suggest Static Pages as pillar candidates when they have specific Geeklog topics with matching published articles, and can suggest article relations for an existing Static Page pillar when those articles share one or more specific topics. Each suggestion explains the topic signal and requires an explicit **Add** action. This does not replace the broader cross-plugin discovery and ranking work planned for 0.8.0.

All Hub administration pages share the same navigation between the Hub home, pillars/relations, interoperability audit and article link audit.

## Public relationship navigation (0.4.0)

On an enabled Static Page pillar, Hub now resolves enabled relations at render time and appends a crawlable **Related content / Contenus liés** section. The stored Static Page body is never rewritten. Relationship order follows the administrator-defined `position`, while missing, disabled or unresolved targets are omitted from public output.

Hub also exposes `plugin_itemdisplay_hub()`. When a core or plugin content renderer calls Geeklog's `PLG_itemDisplay($id, $type)`, Hub can return a **Part of / Dans ce dossier** backlink to the enabled pillar. This keeps backlinks generic and avoids provider-specific database access. Providers that do not render `PLG_itemDisplay()` fragments may require a later fallback adapter.

Core articles are handled without a Core patch: Geeklog already calls `PLG_templateSetVars()` for full story templates and exposes `story_id` / `story_display_type`. Hub uses that generic hook only on full article pages and appends the **Part of / Dans ce dossier** backlink to the prepared story body variables used by Geeklog themes. This avoids relying on late footer variables whose rendering differs across Geeklog 2.1.1 themes, while keeping the backlink server-rendered and crawlable. Forum, Documents, Videos and Maps currently do not expose an equivalent generic public placement hook in the reviewed source, so Hub does not inject backlinks into them through JavaScript or private-table logic.

The SEO value comes from the resulting HTML links, not from the database relationship alone: approved relationships become an explicit internal-link graph between a central pillar and complementary content. A future optional relationship-role field may classify links as guide, tutorial, video, download, discussion, case study or reference so public navigation can be grouped semantically without changing the stable `type + id` identity.

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

## Shared Memorandum alignment

Hub consumes the shared interoperability conventions maintained in `hostellerie/memorandum`; it does not define a parallel Hub-only plugin contract. In particular:

- normalized content remains owned by `plugin_getiteminfo_*()` and related Geeklog APIs;
- lifecycle remains owned by `PLG_itemSaved()` / `PLG_itemDeleted()`;
- specialized provider operations remain owned by bounded services;
- capability declarations describe existing provider surfaces and do not grant authorization;
- Hub owns relationships/context, Agent owns provider-neutral machine access, and external Connectors adapt Agent to a client/protocol.

Hub's own `plugin.json` follows the same recommended static metadata convention.

## Reusable service catalogue

Hub 0.2.0 exposes a reusable runtime catalogue through `HUB_serviceCatalogue()`. It reports whether the plugin service dispatcher is present and lists loaded `service_*_<plugin>()` actions with reflected signatures. The catalogue is descriptive only: Hub does not invoke the service, bypass ACL checks, or take ownership of the service implementation.

Hub itself uses the same shared declaration contract through `plugin_getcapabilities_hub()`. Its declared roles are `relationship`, `orchestrator` and `service`. Hub does not advertise future `hub.*` capabilities until the corresponding provider-owned service/read surface actually exists.

## Current interoperability audit

The permanent Plugin Interoperability Audit follows the shared Memorandum model and separates three layers instead of treating every callback as one Hub-specific checklist.

### Content interoperability baseline

For content-owning providers Hub reports Item Info, stable object types, lifecycle save/delete evidence, URL resolution and services. It also reports **shared content contract evidence** for the recommended Item Info collection conventions:

- `content.collection` declarations;
- source evidence for `$id = '*'`;
- source evidence for collection options `since`, `limit` and `order`;
- source evidence for optional `hits` / `hits-desc` popularity support.

Hub deliberately does **not** execute arbitrary `plugin_getiteminfo_*('*', ...)` provider queries merely to prove these conventions. Declaration/source evidence remains distinct from runtime proof and does not currently change readiness scoring.

### Extended Geeklog integration

Hub reports native optional/distribution surfaces separately, including Related Items, Blocks, Autotags, Search, services, Content Syndication, XML Sitemap, statistics, language overrides and other detectable Geeklog APIs. Optional integration does not reduce a provider's core content-readiness score merely because a feature is irrelevant to that provider.

### Shared capabilities and modernization metadata

Hub detects the provider-neutral `plugin_getcapabilities_*()` convention, reusable service actions, plugin-owned render entry points and the optional static `plugin.json` manifest. The manifest is validated as modernization metadata but is not a Geeklog Core requirement and does not affect readiness scoring.

### Capability implementation evidence

When a provider declares a shared capability, Hub cross-checks the normal Memorandum implementation surface when it can do so safely. Examples include `content.read` → Item Info, `content.lifecycle` → save/delete lifecycle evidence, `content.url.resolve` → ID-to-URL or Item Info, and `dashboard.summary` → the bounded `dashboard_summary` service.

This reconciliation is diagnostic only. A missing or ambiguous implementation surface produces a review recommendation but does not make the declaration invalid, grant authorization, or change the role/readiness score automatically. Specialized provider capabilities remain accepted when Hub cannot infer their implementation surface.

Runtime-detected callbacks are kept distinct from source evidence, provider declarations and inference. Hub never presents source scanning as proof that every mutation path emits a lifecycle notification. The full runtime `plugin_*_<plugin>()` surface remains available under the collapsed **Advanced API surface** section.

See `ROADMAP.md` for the current 0.4.0 bidirectional-navigation milestone and later lifecycle/discovery work.

## 0.1.0 audit details

The audit includes Hub itself, exposes detected callback/function names and autotag names, and provides per-plugin recommendations in collapsed **Details / Recommendations** sections. The most Hub-relevant information is shown first; embedding/discovery helpers are separated and the full Plugin API surface is hidden under **Advanced API surface** by default.

## Role-aware readiness

Hub combines provider-declared roles from the shared capability contract with technical evidence from existing Geeklog APIs. Addressable-content evidence remains authoritative for identifying a content owner; otherwise an explicit provider role can identify **Orchestrator**, **Relationship**, **Diagnostic**, **Navigation**, **Communication**, **Presentation**, **Service** or **Infrastructure** providers.

The audit records whether the primary role is `declared`, `inferred` or `declared+inferred`, and preserves all declared secondary roles. Content providers are evaluated on Item Info, stable object types and save/delete lifecycle emission. Service and presentation providers retain role-specific checks. Diagnostic, relationship, navigation, communication and infrastructure roles are not penalized for missing content-owner APIs.

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

The `Build installable archive` GitHub Actions workflow creates the archive matching the current plugin version (for example `dist/hub-0.4.0.zip`) and preserves previously generated version archives. The ZIP contains one top-level `hub/` directory and can be uploaded through Geeklog's plugin installer.
