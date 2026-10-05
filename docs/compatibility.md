# Hub 1.0 — Compatibility matrix

This document defines the Hub 1.0 compatibility target and what is actually exercised by repository tests.

## Core runtime target

| Component | Target | Status |
| --- | --- | --- |
| Geeklog | 2.1.1 | Supported compatibility baseline |
| Geeklog | 2.2.2 | Supported current baseline |
| PHP | 5.6 | CI syntax + contract test target |
| PHP | 8.1 | CI syntax + contract test target |
| PHP | 8.3 | CI syntax + contract test target |
| Database | MySQL-compatible through Geeklog DB API | Hub uses Geeklog table mapping and DB helpers |
| Theme | Theme-independent core behavior | Public rendering uses Geeklog extension/template hooks |
| Multisite | Active-site scoped | Hub consumes the site already selected by Geeklog |

The CI matrix validates Hub's standalone PHP/contract compatibility. It does not replace full browser/runtime smoke tests inside complete Geeklog installations.

## Geeklog 2.1.1 / 2.2.2 compatibility approach

Hub uses feature-compatible callback signatures and shared APIs rather than branching business logic by version where avoidable.

Examples:

- lifecycle listeners accept optional subtype arguments so the same callbacks work with the older and newer notification forms;
- object URL resolution prefers shared Item Info and may consume newer provider URL helpers when available;
- relationship rendering relies on existing Geeklog extension/template hooks and does not require a Core patch;
- provider discovery degrades gracefully when modern capability declarations are absent.

## Major content-provider interoperability

The matrix below describes the generic contract Hub expects. It does not claim that every historical version of each plugin implements every optional feature.

| Provider family | Stable Item Info identity | Collection useful for Hub | Generic backlink placement | Hub relationship ownership |
| --- | --- | --- | --- | --- |
| Core Articles | Yes | Hub has Core selector support | Hub-owned article template path | Generic editorial relations |
| Static Pages | Yes | Used as initial pillar source | Hub-owned Static Page template path | Generic editorial relations |
| Documents | Expected in modernized release | Recommended | Via provider `PLG_itemDisplay()` when implemented | Generic editorial relations |
| Videos | Expected in modernized release | Recommended | Via provider `PLG_itemDisplay()` when implemented | Generic editorial relations |
| Maps | Expected in modernized release | Recommended | Via provider `PLG_itemDisplay()` when implemented | Generic editorial relations |
| Forum | Item Info available | Collection support depends on release | Via provider `PLG_itemDisplay()` when implemented | Generic editorial relations |
| MediaGallery | Expected in modernized release | Recommended | Via provider `PLG_itemDisplay()` when implemented | Generic editorial relations |
| FAQ | Provider-owned functional associations | Provider-specific | FAQ owns its own placement/rendering | Hub must not replace FAQ attachment tables |

Hub does not read these providers' private tables for normal relationship metadata.

## Capability degradation

Hub must remain usable when a provider lacks optional contracts.

Examples:

- no collection contract -> administrator can use stable manual item ID where supported;
- no runtime backlink placement -> relationship stays valid, integrity reports placement as unverified;
- no object language metadata -> object language remains unknown;
- no sitemap/feed contract -> Hub reports an interoperability review opportunity only;
- provider disabled or object missing -> stored relationship remains and integrity reports unresolved identity.

## Multisite

Hub does not implement its own host router.

It consumes the active Geeklog site configuration and scopes normal inventory/state to that site.

Cross-site target URLs may be represented as context on an approved relationship, but Hub does not open sibling databases or maintain a second network registry.

## Release verification checklist

Before publishing Hub 1.0:

- [ ] PHP 5.6 CI contract suite passes;
- [ ] PHP 8.1 CI contract suite passes;
- [ ] PHP 8.3 CI contract suite passes;
- [ ] installable archive is built only after compatibility jobs pass;
- [ ] fresh install tested on Geeklog 2.2.2;
- [ ] upgrade from an earlier Hub persisted schema tested;
- [ ] Geeklog 2.1.1 compatibility smoke test completed;
- [ ] Geeklog 2.2.2 compatibility smoke test completed;
- [ ] public article/Static Page relationship rendering inspected;
- [ ] administration pages inspected;
- [ ] service ACL behavior verified;
- [ ] no provider-private table dependency introduced.
