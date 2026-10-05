# Hub for Geeklog — Roadmap

**Current development milestone:** `0.10.0` (in development)

**Implemented baseline:** `0.9.0` SEO, cluster-health and integrity scope is functionally complete within the current shared interoperability contracts.

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
10. **Hub owns generic editorial relationships and context, not provider-specific functional attachments.** A provider plugin may own narrowly scoped attachment records when those records directly control its own rendering or business behavior.
11. Provider-owned attachments must not evolve into a parallel generic relationship graph. If a relation primarily means "this content object is editorially related to that content object", belongs to a pillar, or contributes generic cross-provider context/navigation, Hub is the owner.
12. Hub may observe provider-owned attachments through public contracts for context, diagnostics or affected-item analysis, but it must not duplicate, rewrite or become the persistence owner of those records.

## Relationship ownership boundary

The relationship layer must distinguish **generic editorial/context relationships** from **provider-specific functional attachments**.

Examples owned by Hub:

- Article ↔ Document;
- Static Page ↔ Video;
- Map ↔ Document;
- pillar membership;
- generic related-content navigation;
- relationship roles, graph traversal, context, integrity and affected-item diagnostics.

Examples that remain owned by the provider plugin:

- FAQ → Article when the relation means "render this FAQ with that article";
- FAQ category → Static Page when the relation dynamically controls which FAQs are rendered;
- other provider-specific attachments whose stored state directly controls that provider's own rendering, placement, ACL, ordering or business rules.

For FAQ specifically, Hub must not replace `faq_relations` or `faq_category_relations`. FAQ remains authoritative for FAQ placement, category expansion, de-duplication and rendering. Hub may later consume a read-only/public representation of those attachments as context, but should not mirror them into the Hub graph as a second source of truth.

A useful rule is:

```text
"related to / part of / contextual to"
    -> Hub

"render or operate this provider-owned feature on that host item"
    -> provider plugin
```

This boundary prevents Hub from absorbing plugin business logic while also preventing provider plugins from becoming competing generic relationship managers.

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
- sort audit candidates by transparent signals: views, comments, matched-topic count, publication date or title
- show article age alongside the publication date
- allow direct creation of an article relation when the selected Static Page is already a Hub pillar
- mark existing Hub article relations instead of offering duplicate creation
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
- source-owned pillar titles resolved dynamically; no separate Hub title override
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

## 0.4.0 — Bidirectional navigation — implemented

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

0.4.0 validation and known provider gaps:

- public pillar/backlink rendering smoke-tested under Geeklog 2.1.1 and 2.2.2
- Core articles expose reusable template hooks and are covered with one server-rendered backlink in the article body
- Hub generic backlink rendering now covers Forum, Documents, Videos, Maps, MediaGallery and Polls through `plugin_itemdisplay_hub()`
- the owning provider must call `PLG_itemDisplay($id, $type)` on the full public item view; Forum support has been implemented in the maintained 2.1.1 and 2.2.2 Forum branches
- Hub deliberately does not inject provider-specific DOM/JavaScript or query provider-private tables just to force backlinks
- Articles and Static Pages keep their dedicated rendering paths so generic item-display callbacks cannot duplicate their Hub backlink output
- prefer adding/reusing generic Geeklog rendering hooks in those providers over Hub-specific integration
- do not duplicate specialized plugin rendering when a reusable plugin renderer or service exists
- keep output permission-aware by relying on provider resolution rather than reading provider-private tables

**0.4.0 functional scope is complete.** Additional provider-specific public placement belongs to shared provider interoperability work, not to a Hub-only fallback.

Relationship-role evolution before richer grouped rendering:

- keep the stable relationship identity as `type + id`
- optionally add editorial role metadata such as `guide`, `tutorial`, `video`, `download`, `discussion`, `case-study` or `reference`
- use roles to group public sections without changing content ownership
- never infer or overwrite an administrator-approved role silently

## 0.5.0 — Lifecycle and dependency graph — implemented

Implemented scope:

- consume `PLG_itemSaved()` / `PLG_itemDeleted()` notifications through callbacks compatible with Geeklog 2.1.1 and 2.2.x;
- normalize lifecycle identities, including legacy dotted subtype notation and Core `story` → `article` identity;
- migrate Hub-owned stable references when providers report `old_id → new_id`, with collision preflight and no provider-owned writes;
- preserve unresolved Hub relations when provider content is deleted so integrity diagnostics can expose stale identities;
- detect all enabled pillar contexts affected by a changed pillar source or related item;
- detect Static Page pillar contexts affected by normal Static Page topic-assignment saves and by later topic metadata changes;
- invalidate only relevant article/Static Page relationship caches;
- maintain a provider-neutral dependency graph based on stable `type + id` identities;
- traverse the graph bidirectionally with bounded depth, cycle protection and support for multiple approved parents;
- support multi-level editorial structures such as `hub → pillar → sub-pillar → satellite` and `pillar → map → marker` without changing provider ownership;
- store explicit Hub-owned structural edge roles through `relation_role`: `related`, `sub-pillar`, `satellite`, `support`;
- keep structural edge roles separate from future editorial-function metadata such as `guide`, `tutorial`, `reference`, `case-study` or `download`;
- expose read-only Hub services protected by `hub.admin`:
  - `hub.affected.read`;
  - `hub.context.read`;
  - `hub.related.read`;
  - `hub.pillar.read`;
- advertise only Hub capabilities whose corresponding service surface is implemented;
- provide contract tests for lifecycle handling, identity migration, topic impact, graph traversal, cycle/multi-parent behavior, service ACLs, schema upgrade and structural relation roles;
- upgrade existing 0.4.x development installations to 0.5.0 by adding `relation_role` with neutral `related` defaults and removing the obsolete `title_override` persistence column.

No separate Hub metadata cache is introduced in 0.5.0; provider metadata remains resolved dynamically through Geeklog contracts. Therefore there is no additional metadata-cache refresh layer to maintain in this milestone.

Shared Hub capability targets from the Memorandum:

```text
hub.context.read
hub.related.read
hub.pillar.read
hub.affected.read
hub.integrity.summary
hub.suggestions.read
hub.interoperability.summary
hub.editorial.summary
dashboard.summary
```

Implemented and advertised in 0.5.0:

```text
hub.context.read
hub.related.read
hub.pillar.read
hub.affected.read
```

Future targets remain unadvertised until their corresponding Hub-owned surfaces are implemented.

## 0.6.0 — Services and IndexNow — implemented

Implemented first integration slice:

- IndexNow 1.3.0 `develop-1.3.0` now exposes the native Geeklog capability `indexnow.urls.submit` through action `submit_urls`;
- Hub resolves anonymously readable URLs for pillar/context pages affected by relationship, lifecycle or topic changes;
- Hub delegates those URLs through `PLG_invokeService('indexnow', 'submit_urls', ...)`;
- Hub deliberately excludes the directly changed object because IndexNow already receives that object through its own `PLG_itemSaved()` / `PLG_itemDeleted()` listener;
- missing or older IndexNow installations remain an optional no-op and never break Hub lifecycle handling;
- Hub never calls `send_to_indexnow()`, reads IndexNow configuration, manages its key, stores submission state or duplicates transport logic;
- URL deduplication, batch sizing, validation, submission and history remain owned by IndexNow;
- IndexNow's current 1.3.0 architecture uses immediate/batch submission rather than introducing a general asynchronous queue.

Current flow:

```text
Content saved/deleted
    ↓
PLG_itemSaved() / PLG_itemDeleted()
    ├── IndexNow submits the changed object itself
    ↓
Hub determines other affected context pages
    ↓
Hub resolves their anonymous public URLs
    ↓
PLG_invokeService('indexnow', 'submit_urls', ...)
    ↓
IndexNow deduplicates / batches / validates / submits / records history
```

Topic-driven flow:

```text
Topic assignment / topic metadata changed
    ↓
Hub identifies affected Static Page pillars
    ↓
Hub resolves their anonymous public URLs
    ↓
IndexNow native submit_urls service
```

Completed 0.6.0 validation:

- CI/contract coverage verifies submission delegation, missing-service degradation and normalized status reads;
- IndexNow availability/status is consumed through `indexnow.status.read` and shown as informational state in Hub administration;
- README documents the optional dependency and graceful-degradation behavior;
- no extra generic abstraction was added without a demonstrated second consumer.

## 0.7.0 — Specialized plugin rendering — implemented

Implemented first rendering slice:

- providers keep ownership of specialized rendering; Hub discovers only declared `*.render` capabilities and matching `*_render` services;
- Videos 0.21.0 is the reference implementation through `videos.recommendations.render` / `recommendations_render`;
- the Videos renderer reuses its existing local cache, moderation and card/block presentation helpers instead of duplicating recommendation logic in Hub;
- Hub forwards only approved relation identities belonging to that provider; a provider renderer cannot silently broaden a pillar into unrelated recommendations;
- specialized provider output replaces the duplicate generic link list for that provider only when rendering succeeds and returns non-empty HTML;
- unavailable, ambiguous, failing or empty specialized renderers automatically fall back to Hub's generic relation links;
- Hub refuses to guess when one provider exposes several render actions;
- provider-owned presentation dependencies remain provider-owned (Videos activates its existing `block.css` flag when it emits HTML);
- service execution goes only through `PLG_invokeService()`; Hub never calls provider-private renderer functions directly.

Reference flow:

```text
Hub pillar
    ↓
approved relations grouped by provider
    ↓
render capability/service discovery
    ↓
exactly one provider renderer?
    ├── yes → PLG_invokeService(provider, *_render, approved items)
    │          ↓
    │        provider-owned HTML / CSS dependency
    │
    └── no / empty / error → Hub generic relation links
```

Completed 0.7.0 validation:

- Hub and Videos CI are green on the shared render contract;
- README/roadmaps document the provider-render and approval-preserving boundary;
- contract tests verify specialized output, ambiguity handling and generic fallback behavior;
- Blocks/Autotags remain provider implementation details and Hub contains no copy of the Videos recommendation engine.

## 0.8.0 — Discovery, editorial inventory and suggestions — implemented

### Marketing/editorial mapping

Hub's marketing/editorial mapping is a **read model over the existing approved relationship graph**, not a second graph, parallel persistence layer or separate marketing module.

Implemented first 0.8.0 slice:

- expose `hub.editorial.summary` as a deterministic read-only structural summary for Monitor, Agent, Eclipse and other capability-aware consumers;
- count approved pillars, relations and structural roles (`related`, `sub-pillar`, `satellite`, `support`);
- summarize relation participation by provider;
- expose nested-pillar and multi-parent counts without enforcing a tree;
- expose deterministic per-pillar structural summaries;
- keep provider metadata resolution, integrity/SEO health and inferred suggestions outside this first summary so consumers do not conflate approved structure with diagnostics;
- keep all relationship ownership inside Hub and require consumers such as Monitor to use Hub services rather than query Hub tables or recompute the graph.

Boundary with 0.9.0:

- **0.8.0** owns discovery, editorial inventory, topic/coverage opportunities and explainable suggestions;
- **0.9.0** owns cluster health, unresolved/broken relationships, reciprocal-link health, orphan/integrity diagnostics and canonical consistency.

The normalized editorial summary is therefore the first reusable "marketing mapping" surface, while richer inventory and opportunity detection will build on the same graph rather than introduce new persistence.


- extend the 0.1.1 topic audit beyond core articles
- use topics, keywords and `PLG_getRelatedItems()` — shared specific topics are implemented as the first deterministic evidence source; engagement and publication-date signals now transparently prioritize eligible article candidates. Keywords remain deferred until a shared provider contract exposes them, and Hub must not parse provider HTML from `PLG_getRelatedItems()` to manufacture stable identities
- build a continuously refreshed editorial inventory from the real Hub relationship graph — implemented as deterministic `HUB_editorialInventory()` over approved Hub identities
- keep that inventory provider-agnostic: satellites are stable `type + id` objects, not only Core articles or Static Pages — implemented
- treat articles, Static Pages, Maps objects (including maps and markers when exposed as resolvable content), Documents items, Videos items and Forum topics as first-class pillar satellites when their providers expose the shared Geeklog contracts
- allow future content providers to participate without adding hard-coded Hub-only satellite types
- expose a pillar → approved items structural inventory in administration and optionally through `hub.editorial.summary?include_inventory`; missing links and candidate content remain subsequent 0.8/0.9 layers
- reciprocal-link health and pillar/satellite outgoing-link coverage are intentionally handled in 0.9.0, not duplicated in the 0.8 editorial-suggestion layer
- detect thematically close content that is not yet connected to the relevant pillar or cluster — first deterministic article-pair review implemented within Static Page pillar topic contexts using shared topics plus title-token Jaccard similarity
- distinguish explicit Hub relations from inferred thematic proximity — implemented for shared-topic candidates by excluding already approved `type + id` identities
- show why a candidate was detected: shared topics, keywords, related-items provider, existing links, engagement, or other transparent evidence — implemented first for `shared-topic`, with matched topic IDs/labels retained in candidate evidence
- use provider-exposed engagement signals such as views or comments only as prioritization evidence, never as automatic editorial approval — implemented for article candidates after shared-topic eligibility
- surface temporal signals such as publication/update age and obvious year/version markers so stale or strongly time-bound content can be reviewed — implemented as publication age plus explicit year/version-marker evidence. Hub only recommends human review; it does not classify content as stale or obsolete
- allow explainable semantic-proximity / potential-cannibalization warnings as suggestions only; Hub must not auto-merge, redirect or rewrite content — implemented first as explainable lexical proximity (`shared-topic` + `title-token-overlap`, threshold ≥ 50% with at least two common title tokens). The result is explicitly a human-review signal, not a cannibalization verdict
- suggest complementary content without automatically changing editorial relationships — implemented for Static Page pillar → article candidates through `HUB_editorialSuggestions()` and read-only `hub.suggestions.read`
- allow administrators to approve, dismiss or defer suggestions so repeated audits remain useful — implemented. Dismissed suggestions remain hidden until restored; deferred suggestions are persisted with an expiry and automatically return after 30 days; active decisions can be restored from Relations administration
- generate an editorial roadmap directly from the current inventory and relationship graph
- display generated roadmaps in Hub administration with clear sections for pillars, satellites, missing links, candidate content and editorial gaps — editorial gaps/content opportunities are now displayed and can be dismissed, deferred for 30 days or restored
- provide a one-click Markdown download of the generated roadmap
- optionally provide a structured JSON export of the same roadmap/inventory for external consumers
- keep roadmap generation deterministic and explainable by default; every recommendation must retain its evidence
- allow administrators to regenerate a roadmap after relationship, topic or content changes without manually maintaining a separate document
- keep GitHub synchronization outside Hub itself: Agent/Connector or another external integration may publish/update an exported roadmap in a repository
- allow future semantic or AI-assisted ranking only as an optional layer
- AI suggestions must not silently create editorial relationships
- keep structural graph position in `relation_role` (`related`, `sub-pillar`, `satellite`, `support`) and store optional editorial function separately in `editorial_role` — implemented for both pillars and relations with `guide`, `tutorial`, `reference`, `case-study`, `download`, `video`, `discussion`, `resource`, `news` and `archive`
- keep editorial roles optional, administrator-approved and independent from the provider's own content type — implemented with an empty default and explicit administration selectors
- never infer or overwrite an approved editorial role silently — implemented; unknown values normalize to empty and suggestions never assign editorial roles automatically

Suggested inventory model:

```text
Pillar
├── approved satellites (article / staticpage / map / marker / document / video / forum topic / future providers)
│   ├── backlink to pillar: yes/no
│   └── pillar links back: yes/no
├── strong satellite candidates not yet related
├── thematically close content
├── missing / broken relationships
└── editorial gaps / content opportunities
```

Suggested roadmap output:

```text
Editorial roadmap
├── Executive summary
├── Existing pillars
│   ├── sub-pillars
│   ├── approved satellites
│   ├── missing reciprocal links
│   ├── orphan / weakly connected content
│   └── strongest new candidates
├── New pillar opportunities
├── Potential cannibalization / close-content review
├── Stale or strongly dated content
├── Content gaps to create or refresh
├── Internal-link actions
├── Broken/unresolved relationships
└── Prioritized next actions
```

Administration target:

```text
Hub → Editorial roadmap
        ↓
Generate / Refresh
        ↓
HTML preview
        ↓
Download .md
        ↓
optional JSON export / external publication
```

Possible future flow:

```text
Hub builds the site relationship graph
        ↓
deterministic signals
(topics / links / related items / metadata / engagement)
        ↓
editorial inventory + explainable gaps
        ↓
optional external/AI analysis
        ↓
suggestion + explanation
        ↓
human/editorial decision
        ↓
Hub stores approved relationship
```

## 0.9.0 — SEO, cluster health and integrity — functionally implemented within current shared contracts

### Implemented scope

- extend the former article-only link audit into saved Hub relationship diagnostics across provider-owned content identities without querying provider-private tables;
- report provider-neutral pillar health as `healthy`, `attention` or `broken`, with explicit issue codes rather than an opaque score;
- report pillar source resolution, relation resolution/renderability, provider participation and backlink-integration evidence in **Integrity & cluster health**;
- distinguish reciprocal-link evidence precisely:
  - Core article backlinks may be reported as `hub-rendered` because Hub generates the expected fragment and owns the public placement path;
  - generic provider fragments/integration remain explicitly runtime-unverified until provider placement can be proven;
- detect approved relations that cannot produce a public outgoing pillar link because their target is unresolved or non-renderable;
- expose `hub-unconnected` provider content returned by shared `content.collection` but absent from the enabled Hub graph, without mislabelling it as a global SEO orphan;
- report unresolved pillar sources and unresolved approved relation identities through `HUB_integritySummary()`;
- report sitemap/feed interoperability opportunities using provider-owned shared contracts:
  - native `plugin_collectSitemapItems_*()` when available;
  - `content.collection` as the documented XML Sitemap fallback;
  - `content.syndication` or native feed callbacks for feed-capable providers;
- enrich `hub.affected.read` with public-page resolution, URL, dependency reasons and resolved/unresolved affected-page counts while retaining `HUB_getAffectedContexts()` as the single dependency source;
- diagnose self-relations, pillar cycles and informational multi-parent participation;
- detect distinct stable identities resolving to the same normalized public URL as a canonical/public-destination review signal;
- expose normalized read-only diagnostics through `hub.integrity.summary` so Monitor, Agent, Connector or administration tools do not reimplement Hub logic.

### Deliberate boundary

Hub 0.9.0 does **not** claim a complete site-wide internal-link graph.

A true verdict such as:

- "this page is globally orphaned";
- "this cluster has only N inbound links";
- "this provider page contains no contextual link to another arbitrary page";
- "this page is weakly linked across the whole site";

would require a shared, permission-aware provider/Core contract that exposes actual outbound/inbound page links or a normalized link graph. The current Memorandum does not define such a contract, and Hub must not obtain it by parsing provider HTML or querying provider-private tables.

Until such a shared contract exists:

- `hub-unconnected` means only "absent from the Hub relationship graph";
- generic reciprocal-link placement remains "runtime-unverified" when Hub does not own rendering;
- Hub reports structural/integrity evidence rather than inventing site-wide SEO certainty.

## 0.10.0 — Multisite and multilingual context

This phase remains secondary to the single-site relationship graph and should reuse shared provider metadata rather than introduce Hub-specific translation or SEO contracts.

- keep site/domain identity available as relationship context in multisite deployments without merging provider-owned databases or identities
- allow approved cross-site relationships when the source and destination objects are resolvable through shared Geeklog contracts
- accept optional generic language metadata when exposed by the owning provider
- allow an optional equivalent-content relation between resolvable objects in different languages or sites
- expose cross-site / cross-language diagnostics without treating a missing translation as an error
- allow diagnostics for suspicious cross-domain or cross-language links when the relevant site/language metadata is available
- keep hreflang generation, translation workflow and language-specific SEO ownership outside Hub; Hub may expose context to the responsible plugin or external consumer
- keep network-level inventories provider-agnostic and explainable, with each object retaining its owning site/plugin identity

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
