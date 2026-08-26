# Hub for Geeklog

Hub is an interoperability and content-relationship plugin for Geeklog.

Version **0.1.0** is intentionally minimal: after installation it adds **Hub** to the administration area and audits active plugins for detectable interoperability capabilities and their installed versions.

## Requirements

- Geeklog 2.1.1 or newer
- PHP 5.6 or newer

## Current audit columns

- Item Info
- Related Items
- Blocks
- Autotags
- Search
- Services
- readiness score

Lifecycle support is not guessed in 0.1.0 because a plugin's calls to `PLG_itemSaved()` / `PLG_itemDeleted()` cannot be reliably proven from runtime function existence alone.

See `ROADMAP.md` for the planned pillar/relationship implementation.

## 0.1.0 audit details

The audit includes Hub itself, exposes detected callback/function names and autotag names, and provides per-plugin recommendations in collapsed **Details / Recommendations** sections.
