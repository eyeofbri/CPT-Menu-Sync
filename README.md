# CPT Menu Sync

**Version 0.0.2**

CPT Menu Sync is a lightweight WordPress plugin by **Brian McLendon** for keeping posts from one or more post types synchronized beneath selected parent items in classic WordPress navigation menus.

Repository: https://github.com/eyeofbri/CPT-Menu-Sync

License: MIT

## What v0.0.2 does

This release expands the original single-rule prototype into a reusable rule-based menu synchronization tool.

- Create multiple synchronization rules.
- Use posts, pages, or custom post types.
- Target different classic WordPress menus and parent menu items.
- Sync the same post type into more than one menu by creating multiple rules.
- Automatically synchronize when matching posts or configured menus change.
- Manually run every enabled rule with **Sync Now**.
- Keep menu labels synchronized with post titles.
- Remove managed items when source posts are unpublished, trashed, deleted, or otherwise excluded.
- Adopt matching menu items that were already added manually.
- Tag managed menu items with rule ownership metadata.
- Release ownership safely when a rule is removed or retargeted.
- Migrate the v0.0.1 single-rule configuration into the new rule system.
- Preserve unrelated navigation items.

## Ordering

Each rule can use one of these ordering modes:

- Menu Order → Title
- Title A–Z
- Title Z–A
- Newest first
- Oldest first

**Menu Order → Title** reads WordPress's native `menu_order` value. This makes it compatible with ordering tools such as **Post Types Order**.

The sync engine also applies the selected source order to the managed WordPress menu items rather than only querying the posts in that order.

## Admin screen

Open:

**Tools → CPT Menu Sync**

The page uses the `dashicons-share-alt` Dashicon and includes a temporary plugin logo.

Each rule contains:

- Enabled
- Post Type
- Menu
- Parent Menu Item
- Order
- Sync Titles
- Remove Missing
- Adopt Existing

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Classic WordPress navigation menus

This version does not yet include automatic GitHub Release updates.

## Author

**Brian McLendon**

GitHub: https://github.com/eyeofbri

## License

CPT Menu Sync is released under the MIT License. See `LICENSE`.
