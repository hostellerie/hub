# Hub 1.0 — Read-only service surface

Hub exposes provider-neutral read services for trusted Geeklog components through the normal Geeklog service dispatcher.

All current Hub services are administrative/read-only surfaces and require `hub.admin`.

## Common response behavior

Successful calls return the normal Geeklog success status and structured output.

Denied calls return a permission-denied status.

Hub services do not grant provider write authorization and do not bypass provider permissions.

Where relevant, responses include:

```text
site_context
```

derived from the active Geeklog site configuration.

## hub.affected.read

Purpose: return Hub contexts affected by one stable content identity.

Required arguments:

```text
type
id
```

Optional:

```text
include_disabled
```

Output includes:

- the normalized object identity;
- affected pillar contexts;
- explicit dependency reasons;
- public-page diagnostics for affected pillar pages;
- active site context.

## hub.context.read

Purpose: traverse the approved Hub relationship graph around one object.

Required:

```text
type
id
```

Optional:

```text
depth
include_disabled
```

Traversal is bidirectional, depth-bounded, cycle-safe and supports multi-parent graph participation.

## hub.related.read

Purpose: return immediate approved parents/children and edges for one stable identity.

Required:

```text
type
id
```

Optional:

```text
include_disabled
```

## hub.pillar.read

Purpose: return one Hub pillar and its approved relationship identities.

Select using either:

```text
pillar_id
```

or:

```text
type
id
```

Optional:

```text
include_disabled
```

Relationship output preserves structural and editorial roles.

## hub.editorial.summary

Purpose: return deterministic structural editorial-graph information.

Optional:

```text
include_disabled
include_inventory
```

When `include_inventory` is enabled, the inventory is explicitly scoped to the active-site graph and retains provider identity through stable `type + id`.

This service does not mix SEO/integrity verdicts into the structural summary.

## hub.suggestions.read

Purpose: return explainable editorial suggestions.

Optional:

```text
pillar_id
limit
```

Suggestions are advisory only. Hub never silently creates relationships from this service.

Evidence may include shared-topic signals, engagement, temporal markers, title-token overlap and persisted administrator decisions depending on the suggestion type.

## hub.integrity.summary

Purpose: expose normalized relationship/cluster integrity diagnostics.

The service reports, among other evidence:

- resolved/unresolved pillar and relation identities;
- renderable/non-renderable relationship targets;
- backlink integration evidence;
- reciprocal-link verification levels;
- pillar health with explainable issue codes;
- graph cycles, self-relations and multi-parent participation;
- canonical/public URL collisions;
- provider content that is `hub-unconnected`;
- sitemap/feed interoperability opportunities;
- cross-site relation context;
- provider-owned object language evidence when available;
- explicit equivalent-content site/language evidence.

Important boundaries:

- `hub-unconnected` does not mean globally SEO-orphaned;
- generic provider integration does not prove runtime backlink placement;
- Hub does not infer translations;
- Hub does not generate hreflang;
- Hub does not reconstruct a complete site-wide internal-link graph from provider HTML.

## Capability declaration

Hub advertises only implemented service/read capabilities.

Current 1.0 surface includes:

```text
hub.context.read
hub.related.read
hub.pillar.read
hub.affected.read
hub.editorial.summary
hub.integrity.summary
hub.suggestions.read
```

Consumers should discover capabilities rather than assume future names.

## Intended consumers

These services may be reused by trusted components such as:

- Monitor;
- Agent;
- administration dashboards;
- future Connectors through Agent.

Consumers must not maintain a second Hub relationship graph or reproduce Hub-specific integrity logic.
