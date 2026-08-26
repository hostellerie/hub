# Hub for Geeklog — Roadmap

## Vision

Hub turns a Geeklog content object (initially a Static Page) into a pillar and associates complementary items from articles and plugins without rewriting their stored content. Hub stores stable `type + id` relationships, resolves current URLs and metadata through Geeklog APIs, listens to lifecycle events, and delegates specialized work to plugin services.

## Compatibility target

- Geeklog 2.1.1 and 2.2.2
- PHP 5.6 through PHP 8.x
- No Core modification
- No direct dependency on another plugin's database schema

## 0.1.0 — Installable interoperability audit

- install/uninstall through Geeklog Plugin API
- `hub.admin` permission and Hub Admin group
- admin entry
- audit all active plugins and display installed version
- runtime detection of Item Info, Related Items, ID→URL, Blocks, Autotags, Search and detectable service/webservice entry points
- role-aware classification: Content, Presentation, Service, Infrastructure and Orchestrator
- role-specific readiness with core and optional scores
- lifecycle emitter source evidence and listener callback introspection
- lifecycle contract compatibility, including nullable `sub_type`
- object-type discovery and runtime API surface inventory
- Additional Geeklog capabilities reported separately from Hub readiness
- native statistics contribution (`plugin_showstats_*`, `plugin_statssummary_*`) reported as **Full / Partial / None** without changing Hub readiness
- Markdown audit export for developer handoff and issue reports
- do not infer lifecycle support when it cannot be proven

## 0.2.0 — Capability contract

- optional plugin capability declaration for features that cannot be safely inferred
- explicit lifecycle declaration (`PLG_itemSaved`, `PLG_itemDeleted` emission)
- service catalogue / supported actions
- structured render capability
- admin recommendations explaining which interoperability hooks are missing

## 0.3.0 — Pillars and manual relations

- create Hub pillar records
- first pillar target: Static Pages
- attach complementary items by stable `item_type + item_sub_type nullable + item_id`
- no stored internal URL as source of truth
- resolve title/URL through Geeklog APIs where available
- manual ordering and enable/disable state

## 0.4.0 — Bidirectional navigation

- render complementary items on pillar pages
- add backlink from related objects where the host calls `PLG_itemDisplay()`
- permission-aware output
- fallback adapters only when a plugin does not expose normal Geeklog APIs

## 0.5.0 — Lifecycle and dependency graph

- consume `PLG_itemSaved()` / `PLG_itemDeleted()` notifications
- refresh cached metadata
- detect all pillar pages affected by a changed item
- invalidate relevant Hub caches

## 0.6.0 — Services and IndexNow

- ask IndexNow through `PLG_invokeService()` to queue all affected URLs
- batch and deduplicate URLs in IndexNow, not Hub
- establish generic service conventions reusable by other plugins

## 0.7.0 — Specialized plugin rendering

- allow plugins to keep ownership of specialized rendering
- Videos recommendation renderer as reference implementation
- support plugin-rendered and Hub-rendered sections
- reuse existing engines behind Blocks or Autotags rather than duplicate them

## 0.8.0 — Discovery and suggestions

- use topics, keywords and `PLG_getRelatedItems()`
- suggest complementary content without automatically changing editorial relationships
- explain why an item was suggested

## 0.9.0 — SEO and integrity

- orphaned-item checks
- broken/missing object checks
- sitemap/feed integration opportunities
- affected-page diagnostics
- relationship graph diagnostics

## 1.0.0 — Stable Hub

- stable interoperability contract
- migration and upgrade path
- documentation for plugin authors
- compatibility matrix for major Geeklog plugins
- tested packaging for supported Geeklog/PHP matrix

## Future statistics integration

- aggregate or present plugin-provided statistics through native Geeklog callbacks where useful
- keep statistics outside readiness scoring
- do not read another plugin's statistics tables directly when the Plugin API can provide the information

## Design rule

Plugins should not add Hub-specific dependencies when a generic Geeklog API can expose the same capability. Hub should orchestrate relationships; each plugin remains the owner of its data, permissions, business logic and specialized rendering.
