# CPT Menu Sync

**Version 0.0.1**

Initial functional prototype of CPT Menu Sync, a lightweight WordPress plugin by **Brian McLendon**.

Repository: https://github.com/eyeofbri/CPT-Menu-Sync

License: MIT

## Purpose

CPT Menu Sync keeps the published posts from one selected WordPress post type beneath one selected parent item in a classic WordPress navigation menu.

The prototype was created to remove the need to manually add newly created custom-post-type entries to a site's navigation.

## Features

- Tools → CPT Menu Sync settings page.
- Select one post type.
- Select one classic WordPress navigation menu.
- Select the existing parent menu item.
- Automatically create normal WordPress post-type menu items beneath that parent.
- Keep menu labels synchronized to post titles.
- Remove plugin-managed entries when their source post is no longer published.
- Automatically sync after matching post saves/status changes.
- Manual **Sync Now** button.
- Alphabetical Title A–Z ordering.
- Temporary admin logo and `dashicons-share-alt` page branding.

## Limitations in 0.0.1

This is intentionally a small first prototype.

- One synchronization rule only.
- Alphabetical ordering only.
- No Post Types Order / `menu_order` option yet.
- No per-rule configuration toggles.
- No GitHub release updater yet.

These limitations are expanded in later versions.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Classic WordPress navigation menus

## Author

**Brian McLendon**

GitHub: https://github.com/eyeofbri

## License

CPT Menu Sync is released under the MIT License. See `LICENSE`.
