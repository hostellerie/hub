# Hub for Geeklog — Roadmap

**Current development milestone:** `0.4.0` (in development)

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
        +--> Agent for provider-neutral machine access
                  |
                  +--> Connectors / protocol adapters
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
6. Agent is the provider-neutral machine access layer. External Connectors/adapters consume Agent and must not duplicate Hub's relationship graph, provider contracts or business logic.
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
- native feed, sitemap and statistics callbacks where they already exist;
- optional `plugin_getcapabilities_*()` declarations from the shared capability contract;
- optional static `plugin.json` metadata for safe identity/compatibility discovery.

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
- cache normalized interoperability audit data through Geeklog's native cache API, with fingerprint invalidation and manual refresh
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

## 0.1.1 — Immediate article link audit

- select a destination Static Page
- discover the topics assigned to that Static Page automatically
- inspect published, non-draft core articles assigned to any of those topics
- deduplicate articles assigned to more than one matching topic
- show the matching assigned topic(s) for each suggested article so the editorial reason is explicit
- check hyperlinks in both `introtext` and `bodytext`
- recognize relative and absolute links, HTTP/HTTPS, www/non-www and reordered query parameters
- list only articles without the expected link
- provide direct View and Edit actions
- expose article view/comment counts in the audit to help administrators prioritize contextual-link reviews
- append assigned visible topic links to normal Static Page rendering without modifying stored content, reusing Geeklog's native `TOPIC_relatedTopics()` renderer and `topicrelated.thtml` markup
- expose specific Geeklog topic context as a suggestion signal while treating All/Home page only assignments strictly as placement options
- keep this first release read-only: Hub never rewrites article content automatically
- package an installable development ZIP in `dist/` through GitHub Actions

This expedited milestone provides an immediately useful subset of the later discovery and SEO-integrity work. It deliberately reads only Geeklog core article, topic and Static Pages tables.

## 0.2.0 — Generic capability discovery — completed 2026-09-27

- detect and validate existing generic `plugin_getcapabilities_*()` declarations without making them Hub-specific
- keep declared roles/capabilities visibly distinct from runtime/source evidence and inferred readiness
- consume declared provider roles for role classification while preserving technical evidence and role provenance (`declared`, `inferred`, `declared+inferred`)
- treat missing declarations as optional, while invalid declarations produce explicit admin recommendations
- detect capabilities that can be proven from existing Geeklog Plugin APIs
- allow optional capability declaration only for features that cannot be safely inferred
- keep that declaration generic and reusable by Hub, Connector and other consumers
- do **not** create `plugin_hubCapabilities_*()` if the same information can be exposed generically
- support an optional generic lifecycle declaration (`emits`, `listens`, `sub_type`) where runtime/source detection is insufficient, while keeping declaration separate from proof
- expose a reusable runtime service catalogue with dispatcher presence, supported actions and reflected signatures
- expose structured render capability where useful
- provide admin recommendations explaining which interoperability hooks are missing
- cross-check declared shared capabilities against safely detectable Memorandum implementation surfaces, without treating declarations as authorization or changing readiness automatically
- Hub itself declares the shared roles `relationship`, `orchestrator`, `service` through the same generic declaration contract
- never advertise future `hub.*` capabilities before the corresponding Hub service/read surface exists
- inspect the recommended static `plugin.json` manifest without making it a readiness requirement
- report shared Item Info collection-contract evidence (`content.collection`, `*`, `since`, `limit`, `order`, optional `hits`/`hits-desc`) without invoking arbitrary provider collections during audit

The long-term goal is a shared capability description usable by:

```text
Hub
Hello
IndexNow
Connector
Search / Recommendations
future external integrations
```

## 0.3.0 — Pillars and manual relations — completed 2026-09-27

Implemented foundation:

- persistent `hub_pillars` records
- persistent `hub_relations` records
- first pillar target: Static Pages
- Hub pillar/editorial relationships remain independent from Static Page placement assignments (All/Home page only/specific display context)
- complementary items stored by stable `item_type + item_id`
- no stored internal URL as source of truth
- dynamic title/URL resolution through `PLG_getItemInfo()` when available
- manual ordering and enable/disable state
- optional pillar title override
- direct self-relations rejected
- install and upgrade path from 0.2.0
- administrator UI for creating/removing pillars and adding/updating/removing manual relations
- shared administration navigation across Hub home, pillars/relations, interoperability audit and link audit
- relation types discovered only from active resolvable Geeklog Item Info providers
- dynamic related-object selector through the shared Item Info collection contract when available, with manual ID fallback when the provider does not expose a collection
- administration checkbox alignment hardened independently from normal text/select field sizing
- lightweight explainable administration suggestions: Static Page pillar candidates and article relation candidates based on existing specific-topic context; every suggestion requires explicit administrator approval
- integrity diagnostics distinguish invalid identity, unavailable dispatcher, unavailable provider and provider-present/object-missing cases
- unresolved relations are preserved instead of being deleted automatically
- focused relation contract tests cover normalization, resolution diagnostics, duplicate-prevention SQL constraints and deterministic ordering
- upgrade behavior validated on Geeklog 2.1.1/PHP 5.6 and Geeklog 2.2.2/PHP 8.x
- ownership of every related item remains with its source plugin

Final 0.3.0 hardening completed:

- administration UI smoke-tested with article and plugin-owned relations
- collection-backed selectors and manual-ID fallback validated
- destructive actions grouped and confirmed
- relation editor layout simplified to Order / Relation / Enabled / Actions
- broader cross-plugin discovery/ranking remains scheduled for 0.8.0

## 0.4.0 — Bidirectional navigation

Implemented foundation:

- render enabled complementary items as normal crawlable HTML links on enabled Static Page pillars
- preserve administrator-defined relation order in public rendering
- resolve every title and URL dynamically through Geeklog Item Info at render time
- skip unresolved or URL-less related objects rather than emitting broken public links
- add generic backlink fragments through `plugin_itemdisplay_hub()` when the host renderer calls `PLG_itemDisplay()`
- add a Core article fallback through `PLG_templateSetVars()`: on full article pages Hub appends the backlink to the prepared story body variables, avoiding theme-dependent late footer placement
- localize the initial public labels for English, French, German, Italian and Spanish
- keep public presentation theme-neutral through Hub CSS
- no stored-content rewriting

Remaining 0.4.0 work:

- smoke-test public pillar rendering under Geeklog 2.1.1 and 2.2.2
- verify which major content providers actually place `PLG_itemDisplay()` fragments and document gaps
- current source review: Core articles expose a reusable template hook; Forum, Documents, Videos and Maps do not currently expose a generic `PLG_itemDisplay()` placement or equivalent Hub-usable public render hook
- prefer adding/reusing generic Geeklog rendering hooks in those providers over Hub-specific integration
- add fallback adapters only when a provider cannot expose normal Geeklog rendering hooks
- do not duplicate specialized plugin rendering when a reusable plugin renderer or service exists
- keep output permission-aware by relying on provider resolution rather than reading provider-private tables

Relationship-role evolution to prepare before richer grouped rendering:

- keep the stable relationship identity as `type + id`
- optionally add editorial role metadata such as `guide`, `tutorial`, `video`, `download`, `discussion`, `case-study` or `reference`
- use roles to group public sections without changing content ownership
- never infer or overwrite an administrator-approved role silently

## 0.5.0 — Lifecycle and dependency graph

- consume `PLG_itemSaved()` / `PLG_itemDeleted()` notifications
- refresh cached metadata
- detect all pillar pages affected by a changed item
- detect Static Pages whose public rendering changes when topic assignments are added or removed
- detect Static Pages affected when an assigned topic is renamed, removed or otherwise changes its public label/URL
- invalidate relevant Hub caches
- maintain a dependency graph based on stable content identity
- expose affected-page/context information through a reusable Hub service for administration and future external consumers

Shared Hub capability targets from the Memorandum:

```text
hub.context.read
hub.related.read
hub.pillar.read
hub.affected.read
hub.integrity.summary
hub.suggestions.read
hub.interoperability.summary
dashboard.summary
```

These capabilities must be advertised only when the corresponding Hub-owned read/service surface is implemented.

## 0.6.0 — Services and IndexNow

- ask IndexNow through `PLG_invokeService()` to queue all affected URLs
- include Static Page URLs whose rendered topic links changed because of assignment changes or topic metadata changes
- batch and deduplicate URLs in IndexNow, not Hub
- establish generic service conventions reusable by other plugins
- expose IndexNow status in Hub only as information when the IndexNow plugin provides it
- never duplicate IndexNow transport or queue logic inside Hub

Preferred flows:

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

```text
Topic assignment / topic metadata changed
    ↓
Hub identifies affected Static Pages
    ↓
Hub resolves their public URLs
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

- extend the 0.1.1 topic audit beyond core articles
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

- extend the 0.1.1 link audit to saved Hub relationships and plugin-owned content
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

## Integration with Agent and external Connectors

Agent is the provider-neutral Geeklog machine access layer. It may consume implemented Hub capabilities such as `hub.context.read`, `hub.related.read`, `hub.affected.read`, `hub.integrity.summary` and `hub.suggestions.read` when a machine request needs relationship/context information.

External Connectors adapt Agent to ChatGPT, MCP, REST/OpenAPI or another client/protocol.

However:

- Agent and Connectors must not maintain a second relationship graph;
- they must not calculate Hub-specific orphan or dependency logic independently;
- they must not add consumer-specific callbacks to content plugins when shared Geeklog contracts already provide the information;
- protocol-specific names belong to the Connector/adapter, not to Hub's shared capability model;
- Hub must remain fully usable without Agent, ChatGPT or any external AI provider.

## Future statistics integration

- aggregate or present plugin-provided statistics through native Geeklog callbacks where useful
- do not read another plugin's statistics tables directly when the Plugin API can provide the information
- keep SEO-provider, Analytics and campaign statistics owned by their respective plugins

## Design rule

> **Hub connects and interprets Geeklog content relationships. It consumes shared Geeklog contracts, delegates specialized actions to the owning plugin, and exposes reusable context to other consumers. It does not become the universal API, newsletter system, indexing transport, analytics engine or AI layer.**
