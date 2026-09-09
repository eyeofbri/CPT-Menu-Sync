# Changelog

## 0.0.2

Rule-system / reusable plugin milestone.

- Expanded the original prototype from one configuration to multiple sync rules.
- Added support for multiple post types and menus.
- Added dependent menu-parent selection.
- Added Menu Order, alphabetical, and date sort modes.
- Added compatibility with the native `menu_order` field used by Post Types Order.
- Added actual managed-menu reordering to match the selected source order.
- Added Sync Titles, Remove Missing, and Adopt Existing options.
- Added per-rule UUID ownership metadata.
- Added safer release behavior when rules are deleted or retargeted.
- Added one-time migration of the v0.0.1 single-rule configuration.
- Added synchronization when configured menus change.
- Improved Tools → CPT Menu Sync UI and sync reporting.

## 0.0.1

Initial functional prototype.

- Added one configurable post-type-to-menu-parent synchronization.
- Added automatic synchronization on matching post changes.
- Added manual Sync Now.
- Added alphabetical post ordering.
- Added basic managed-item cleanup.
- Added the initial Tools → CPT Menu Sync screen.
