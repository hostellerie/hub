# Hub 1.0 — Plugin author integration guide

Hub consumes shared Geeklog interoperability contracts. Content plugins must not implement Hub-specific APIs when an existing Geeklog contract can expose the same information.

## Minimum content-provider baseline

A provider that should participate cleanly in Hub should expose, where appropriate:

- `plugin_getiteminfo_PLUGIN()` for stable metadata such as `id`, `title` and `url`;
- Item Info collection support with `id = '*'` when multiple addressable objects should be discoverable;
- lifecycle notifications through `PLG_itemSaved()` and `PLG_itemDeleted()`;
- `plugin_idtourl_PLUGIN()` where supported by the Geeklog version/provider;
- a public `PLG_itemDisplay($id, $type)` placement when third-party contextual fragments are appropriate;
- optional `plugin_getcapabilities_PLUGIN()` metadata using the shared Memorandum contract.

Hub stores only stable provider identity and Hub-owned relationship metadata. It does not read provider-private tables to resolve normal content metadata.

## Stable identity

Provider-owned identities must remain stable and URL-independent.

Preferred contract:

```text
provider type + provider item id
```

Examples:

```text
videos:42
documents:category:12
forum:845
maps:marker:27
```

Do not make Hub persist a provider URL as source of truth. The provider remains responsible for resolving its current URL.

## Item Info

For one object, Hub primarily needs:

```text
id
title
url
```

For provider collections, Hub uses the shared collection convention:

```php
PLG_getItemInfo(
    'PLUGIN',
    '*',
    'id,title,url',
    0,
    array(
        'limit' => 100,
        'order' => 'modified-desc'
    )
);
```

Unknown requested fields should degrade safely.

## Lifecycle

After a successful create/update, providers should emit:

```php
PLG_itemSaved($id, 'PLUGIN');
```

After deletion:

```php
PLG_itemDeleted($id, 'PLUGIN');
```

Hub listens to those events and recalculates affected relationship context, invalidates relevant caches and may delegate affected URLs to IndexNow when that integration is available.

Hub preserves a deleted object's stored stable relationship so integrity diagnostics can expose the unresolved editorial intent instead of silently deleting it.

## Public contextual rendering

Providers with a full public item page should call Geeklog's generic extension point where practical:

```php
$extensions = PLG_itemDisplay($id, 'PLUGIN');
```

The provider owns where returned fragments are rendered. Hub can then return reciprocal pillar-navigation HTML without provider-specific coupling.

Integration availability is not treated as proof that a provider actually rendered the fragment; Hub integrity diagnostics distinguish those cases.

## Structural and editorial roles

Hub owns structural relationship roles:

- `related`
- `sub-pillar`
- `satellite`
- `support`
- `equivalent`

Optional editorial function is separate:

- `guide`
- `tutorial`
- `reference`
- `case-study`
- `download`
- `video`
- `discussion`
- `resource`
- `news`
- `archive`

Providers should not duplicate these generic relationships in a second provider-owned graph.

## Multisite

Providers must consume the site already selected by Geeklog. Do not add Hub-specific host routing.

Hub derives current site context from Geeklog configuration such as `site_url`, and never asks a provider to know about sibling site databases.

Cross-site relationships are allowed when provider-owned identities resolve through shared contracts. Hub records the relationship, not a second multisite registry.

## Language metadata

If the provider explicitly supports generic Item Info language metadata, Hub accepts a non-empty:

```text
language
```

value as provider-owned object-language evidence.

When that value is absent, Hub keeps object language unknown. It does not infer language from hostname, URL, slug, object ID or the active site language.

## Equivalent content

An administrator may explicitly mark a relationship as:

```text
relation_role = equivalent
```

Hub may then report site/language evidence around that approved equivalence. Hub does not infer translations and does not generate hreflang.

## Sitemap and feeds

Providers remain responsible for their own distribution surfaces.

Hub recognizes:

- `plugin_collectSitemapItems_PLUGIN()`;
- Item Info collections as the documented XML Sitemap fallback;
- `plugin_getfeednames_PLUGIN()` + `plugin_getfeedcontent_PLUGIN()`;
- the shared `content.syndication` capability declaration.

Hub reports interoperability opportunities but does not generate provider sitemap/feed data itself.

## Ownership rule

A useful boundary is:

```text
generic editorial/context relationship
    -> Hub

provider-specific functional attachment controlling provider behavior
    -> provider plugin
```

Examples such as FAQ placement remain provider-owned when they directly control FAQ rendering/business behavior.

## Compatibility target

Hub 1.0 targets:

- Geeklog 2.1.1 and 2.2.2;
- PHP 5.6 through current PHP 8.x compatibility tested by the repository CI matrix;
- no Core modification;
- no provider-private SQL dependency for normal interoperability.
