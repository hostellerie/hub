# Hub for Geeklog — Roadmap

## Vision

Hub turns a Geeklog content object, initially a Static Page, into a pillar and associates complementary items from articles and plugins without rewriting their stored content.

Hub is the **internal content relationship and context orchestrator** of Geeklog. It stores stable `type + id` relationships, resolves current URLs and metadata through Geeklog APIs, listens to lifecycle events, detects affected content, and delegates specialized work to the plugins that own it.

Hub is **not** the external API gateway, the newsletter sender, the search-engine submission transport, or the owner of other plugins' business logic.

The intended architecture is:

```text
Content plugins / Core
        |
        | common Geeklog contracts
        v
       Hub
 relationship graph / context / diagnostics
        |
        +--> Hello for communication workflows
        +--> IndexNow for URL submission
        +--> Connector for external access to Hub capabilities
```

## Compatibility target

- Geeklog 2.1.1 and 2.2.2
- PHP 5.6 through PHP 8.x
- No Core modification required for the current roadmap
- No direct dependency on another plugin's database schema
- Multisite-safe behavior

## Architectural rules

1. **Hub consumes common Geeklog contracts; it does not own them.**
2. Plugins must not implement Hub-specific integration when an existing or generic Geeklog API can expose the same capability.
3. Hub must not duplicate another plugin's business logic, rendering, transport, queue, analytics, or persistence model.
4. Each plugin remains authoritative for its own data, permissions, URLs, actions and specialized rendering.
5. Hub may orchestrate a service, but the service-owning plugin remains responsible for execution.
6. The future ChatGPT/agent Connector must consume Hub capabilities where useful and must not duplicate Hub's relationship graph or context logic.
7. Hello remains the communication layer for registered users and email campaigns; Hub may provide context or candidate content but must not become a newsletter engine.
8. IndexNow remains responsible for queueing, deduplication and submission to search engines.
9. A future common Data/API layer and common Events layer should remain shared Geeklog architecture, not Hub-specific infrastructure.

## Shared interoperability baseline

Hub should preferentially reuse the conventions documented in the Memorandum, including:

- `plugin_getiteminfo_*()` for structured metadata;
- collection retrieval through `'*'` where supported;
- `PLG_itemSaved()` and `PLG_itemDeleted()` lifecycle notifications;
- `plugin_idtourl_*()` or Item Info URL fallback;
- `plugin_getrelateditems_*()` where available;
- `plugin_dopluginsearch_*()` where relevant;
- `PLG_invokeService()` for specialized plugin actions;
- native feed, sitemap and statistics callbacks where they already exist.

Hub should never introduce a second parallel contract merely because it is a consumer.

## 0.1.0 — Installable interoperability audit

- install/uninstall through Geeklog Plugin API
- `hub.admin` permission and Hub Admin group
- admin entry
- audit all active plugins and display installed version
- runtime detection of:
  - `plugin_getiteminfo_*`
  - `plugin_getrelateditems_*`
  - `plugin_getBlocks_*`
  - `plugin_autotags_*`
  - `plugin_dopluginsearch_*`
  - detectable service/webservice entry points
- readiness score
- include Hub itself in the audit
- show detected callback/service function names and declared autotag names
- collapsed per-plugin Details / Recommendations guidance
- role-aware classification: content, presentation, service, infrastructure, communication and orchestrator
- role-specific readiness with core and optional scores
- Markdown audit export for developer handoff and issue reports
- do not infer lifecycle support when it cannot be proven at runtime
- audit native statistics contribution (`plugin_showstats_*`, `plugin_statssummary_*`) as Full / Partial / None without changing Hub readiness
- report Content Syndication support (`plugin_getfeednames_*`, `plugin_getfeedcontent_*`, optional `plugin_feedupdatecheck_*`)
- report XMLSitemap contribution through `plugin_collectSitemapItems_*` or the supported `PLG_getItemInfo(type, '*', ...)` fallback

### Role classification clarification

Examples:

```text
Maps / Documents / Videos / Store / Forum / Static Pages = content owners
Hello                                                = communication
IndexNow                                             = indexing transport
Hub                                                  = orchestrator / context
Connector                                            = external gateway
```

The audit should not reduce a communication or infrastructure plugin's score simply because it does not expose normal content-collection capabilities.

## 0.2.0 — Generic capability discovery

- detect capabilities that can be proven from existing Geeklog Plugin APIs
- allow optional capability declaration only for features that cannot be safely inferred
- keep that declaration generic and reusable by Hub, Connector and other consumers
- do **not** create `plugin_hubCapabilities_*()` if the same information can be exposed generically
- expose explicit lifecycle declaration where runtime detection is insufficient
- expose service catalogue / supported actions where useful
- expose structured render capability where useful
- provide admin recommendations explaining which interoperability hooks are missing

The long-term goal is a shared capability description usable by:

```text
Hub
Hello
IndexNow
Connector
Search / Recommendations
future external integrations
```

## 0.3.0 — Pillars and manual relations

- create Hub pillar records
- first pillar target: Static Pages
- attach complementary items by stable `item_type + item_id`
- no stored internal URL as source of truth
- resolve title/URL through `PLG_getItemInfo()` where available
- manual ordering and enable/disable state
- preserve ownership of every related item in its source plugin

## 0.4.0 — Bidirectional navigation

- render complementary items on pillar pages
- add backlink from related objects where the host calls `PLG_itemDisplay()`
- permission-aware output
- fallback adapters only when a plugin does not expose normal Geeklog APIs
- do not duplicate specialized plugin rendering when a reusable plugin renderer or service exists

## 0.5.0 — Lifecycle and dependency graph

- consume `PLG_itemSaved()` / `PLG_itemDeleted()` notifications
- refresh cached metadata
- detect all pillar pages affected by a changed item
- invalidate relevant Hub caches
- maintain a dependency graph based on stable content identity
- expose affected-page/context information through a reusable Hub service for administration and future external consumers

Possible Hub service capabilities:

```text
hub.get_context
hub.get_related_items
hub.get_affected_items
hub.get_integrity_report
hub.get_suggestions
```

These are conceptual service names, not frozen API names.

## 0.6.0 — Services and IndexNow

- ask IndexNow through `PLG_invokeService()` to queue all affected URLs
- batch and deduplicate URLs in IndexNow, not Hub
- establish generic service conventions reusable by other plugins
- expose IndexNow status in Hub only as information when the IndexNow plugin provides it
- never duplicate IndexNow transport or queue logic inside Hub

Preferred flow:

```text
Content saved
    ↓
PLG_itemSaved()
    ↓
Hub determines affected pages
    ↓
IndexNow service
    ↓
queue / deduplicate / submit
```

## 0.7.0 — Specialized plugin rendering

- allow plugins to keep ownership of specialized rendering
- Videos recommendation renderer as reference implementation
- support plugin-rendered and Hub-rendered sections
- reuse existing engines behind Blocks or Autotags rather than duplicate them

## 0.8.0 — Discovery and suggestions

- use topics, keywords and `PLG_getRelatedItems()`
- suggest complementary content without automatically changing editorial relationships
- explain why an item was suggested
- allow future semantic or AI-assisted ranking only as an optional layer
- AI suggestions must not silently create editorial relationships

Possible future flow:

```text
Hub finds candidate items
        ↓
optional external/AI analysis
        ↓
ranked suggestion + explanation
        ↓
human/editorial decision
        ↓
Hub stores approved relationship
```

## 0.9.0 — SEO and integrity

- orphaned-item checks
- broken/missing object checks
- sitemap/feed integration opportunities
- affected-page diagnostics
- relationship graph diagnostics
- canonical URL consistency checks
- expose normalized diagnostics so Connector or administration tools can report them without reimplementing Hub logic

## 1.0.0 — Stable Hub

- stable relationship and context model
- stable use of shared Geeklog interoperability contracts
- migration and upgrade path
- documentation for plugin authors
- compatibility matrix for major Geeklog plugins
- tested packaging for supported Geeklog/PHP matrix
- documented service surface for other trusted Geeklog components

## Integration with Hello

Hello is the registered-user communication and newsletter plugin.

Hub may provide:

- pillar context;
- related content;
- recently changed items;
- editorially approved relationships;
- candidate items for a digest;
- context explaining why content belongs together.

Hello remains responsible for:

- recipient selection according to its rules;
- subscriber preferences;
- campaign creation;
- queueing;
- throttling;
- delivery;
- unsubscribe handling;
- open/click tracking;
- campaign statistics.

Hub must not access Hello's campaign or subscriber tables directly.

Example future workflow:

```text
Hub: content relevant to a pillar
        ↓
Hello: build a campaign/digest draft
        ↓
admin test / validation
        ↓
Hello queue and delivery
```

## Integration with the future Connector

The Connector is the secure external gateway for ChatGPT and other authorized clients.

The Connector may expose Hub capabilities such as:

```text
get_hub_context
get_related_items
get_affected_items
get_integrity_report
get_suggestions
```

However:

- Connector must not maintain a second relationship graph;
- Connector must not calculate Hub-specific orphan or dependency logic independently;
- Connector must not add Connector-specific callbacks to content plugins when shared Geeklog contracts already provide the information;
- Hub must remain fully usable without ChatGPT or any external AI provider.

## Future statistics integration

- aggregate or present plugin-provided statistics through native Geeklog callbacks where useful
- do not read another plugin's statistics tables directly when the Plugin API can provide the information
- keep SEO-provider, Analytics and campaign statistics owned by their respective plugins

## Design rule

> **Hub connects and interprets Geeklog content relationships. It consumes shared Geeklog contracts, delegates specialized actions to the owning plugin, and exposes reusable context to other consumers. It does not become the universal API, newsletter system, indexing transport, analytics engine or AI layer.**
