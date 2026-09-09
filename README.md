# CPT Menu Sync

**CPT Menu Sync** is a small, reusable WordPress plugin that automatically keeps posts from any manageable post type synchronized beneath a selected parent item in a classic WordPress navigation menu.

It is designed for sites where a section such as **Services**, **Locations**, **Team**, **Resources**, or another custom post type should automatically populate a menu without requiring editors to manually add or remove menu items every time content changes.

## Author

**Brian McLendon**  
GitHub: https://github.com/eyeofbri

Repository: https://github.com/eyeofbri/CPT-Menu-Sync

## License

MIT License. See [`LICENSE`](LICENSE).

## Current Version

`0.1.0`

## Features

- Admin interface under **Tools → CPT Menu Sync**
- Multiple independent sync rules
- Multiple custom post types
- The same post type can be synced into multiple menus by creating multiple rules
- Select an existing WordPress navigation menu
- Select any existing menu item as the parent
- Automatically add newly published posts
- Automatically update existing managed menu items
- Optionally keep menu labels synchronized to post titles
- Optionally remove menu items when posts are unpublished, trashed, deleted, or otherwise no longer eligible
- Optionally adopt matching menu items that were manually added before the plugin was installed
- Avoid duplicate generated items
- Manual **Sync Now** action
- Sync report showing matched, added, updated, and removed items
- Event-driven synchronization rather than rebuilding menus on every frontend request
- Compatible with WordPress `menu_order`
- Compatible with drag-and-drop ordering plugins such as **Post Types Order** when the **Menu Order** sort mode is selected
- Managed menu items are tagged with private post meta so unrelated menu items are not removed
- GitHub Releases integration for normal WordPress plugin updates
- Built-in update diagnostics and **Check for Updates** action

## Requirements

- WordPress 6.0+
- PHP 7.4+
- A site using classic WordPress navigation menus
- Permission to manage WordPress menus

> Block-theme Navigation blocks use a different storage system and are not managed by this version.

## Installation

1. Download or clone the `cpt-menu-sync` folder.
2. Place it in:

```text
/wp-content/plugins/cpt-menu-sync/
```

3. Activate **CPT Menu Sync** from **Plugins** in WordPress.
4. Go to **Tools → CPT Menu Sync**.

## Creating a Sync Rule

Each rule connects:

```text
Post Type → WordPress Menu → Parent Menu Item
```

For example:

```text
Services → 2024 Main Menu → Services
```

If the `services` post type contains:

```text
Commercial Cleaning
Grease Trap Services
Landscaping
Waste Management
```

CPT Menu Sync can maintain:

```text
Home
About
Services
    Commercial Cleaning
    Grease Trap Services
    Landscaping
    Waste Management
Contact
```

The generated children are normal WordPress post-type menu items, not custom URLs.

## Rule Options

### Enabled

Controls whether the rule is currently active.

Disabled rules remain saved but are ignored by automatic and manual synchronization.

### Post Type

Select the WordPress post type whose published posts should be synchronized.

The plugin supports custom post types as well as other manageable WordPress post types exposed in the admin UI.

### Menu

Select the classic WordPress navigation menu that should receive the synchronized items.

### Parent Menu Item

Select the existing menu item that should contain the synchronized posts.

The parent is stored by its WordPress navigation menu item ID, so changing its displayed label later does not break the rule.

### Order

Available ordering modes include:

- **Menu Order → Title**
- **Title A–Z**
- **Title Z–A**
- **Newest first**
- **Oldest first**

#### Post Types Order Compatibility

The **Menu Order → Title** option uses WordPress's native `menu_order` field.

This is compatible with plugins such as **Post Types Order**, which store their drag-and-drop order in that field.

If multiple posts have the same `menu_order`, their titles are used as a secondary alphabetical sort.

The plugin applies the resulting order to the actual managed navigation menu items, so it is not limited to merely retrieving the posts in the correct order.

### Sync Titles

When enabled, changing a post title also changes the corresponding managed menu label.

When disabled, an existing customized menu label is preserved.

### Remove Missing

When enabled, CPT Menu Sync removes a menu item it manages when the source post is no longer included, including when a post is:

- unpublished
- changed to draft
- trashed
- deleted

Only menu items tagged as managed by the applicable sync rule are removed.

### Adopt Existing

When enabled, the plugin will use a matching manually-added post-type menu item rather than creating a duplicate.

After adoption, the menu item is tagged as belonging to that sync rule.

## Multiple CPTs

Create as many rules as needed.

Example:

```text
Services  → Main Menu   → Services
Locations → Main Menu   → Locations
Team      → About Menu  → Our Team
Resources → Footer Menu → Resources
```

The synchronization engine is not tied to any specific custom post type name.

## One CPT in Multiple Menus

Create multiple rules using the same post type.

Example:

```text
Services → Main Menu   → Services
Services → Mobile Menu → Services
Services → Footer Menu → Our Services
```

Each rule is independent and receives its own permanent rule ID.

## Automatic Synchronization

The plugin queues an appropriate rule when a matching post is saved or its publication status changes.

It also reacts when a configured classic navigation menu is updated.

Queued work runs once near the end of the current WordPress request, preventing multiple save/status hooks from needlessly running the same rule repeatedly.

The plugin does **not** rebuild menus on ordinary frontend page loads.

## Manual Synchronization

Go to:

**Tools → CPT Menu Sync**

and click:

**Sync Now**

A report will show the result of each enabled rule, including:

```text
Matched 12 · Added 2 · Updated 10 · Removed 0
```

Saving the rule configuration also performs an immediate synchronization.

If a rule is removed or retargeted to another post type, menu, or parent, its old menu items are **released** rather than deleted: CPT Menu Sync removes its ownership metadata and leaves those entries in place as normal manual menu items. This avoids destructive navigation changes when configuration is edited.

## Managed Item Metadata

Generated or adopted menu items are tagged with:

```text
_cptms_rule_id
```

The value is the UUID of the rule that manages the item.

This allows the synchronization engine to distinguish its own items from unrelated menu entries.

## GitHub Updates

CPT Menu Sync can update directly from normal GitHub Releases in:

```text
eyeofbri/CPT-Menu-Sync
```

The updater integrates with WordPress's standard plugin updater, so a newer GitHub Release can appear on the normal **Plugins** and **Updates** screens.

The **Tools → CPT Menu Sync** page also shows:

- installed version
- latest normal GitHub Release
- GitHub connection status
- last GitHub check
- whether an update is available
- a **Check for Updates** button

Automatic GitHub release metadata is cached for up to one hour. **Check for Updates** clears that cache and WordPress's plugin-update transient before requesting the latest release again.

### Release Workflow

1. Update the plugin version in `cpt-menu-sync.php`.
2. Update `CPTMS_VERSION` to the same version.
3. Update `changelog.md` and the README changelog.
4. Commit and push the version to GitHub.
5. Create a GitHub Release with a matching tag, for example:

```text
v0.1.1
```

6. Publish it as a normal release — not a draft and not a prerelease.

No manually uploaded release ZIP is required. The updater uses GitHub's automatically generated source ZIP for the release. During the WordPress upgrade process, CPT Menu Sync normalizes GitHub's generated repository folder back to:

```text
cpt-menu-sync/
```

This prevents WordPress from installing a second copy of the plugin beside the existing one.

### Repository Layout Requirement

The GitHub repository root should be the plugin root itself. In other words, `cpt-menu-sync.php` should live directly at the repository root:

```text
CPT-Menu-Sync/
├── cpt-menu-sync.php
├── README.md
├── changelog.md
├── LICENSE
├── uninstall.php
├── assets/
└── includes/
```

Do **not** put another `cpt-menu-sync/` wrapper folder inside the repository. GitHub creates its own temporary wrapper directory in source archives, and the updater handles that automatically.

### Repository Configuration

The repository used for updates is defined in `cpt-menu-sync.php`:

```php
define( 'CPTMS_GITHUB_REPOSITORY', 'eyeofbri/CPT-Menu-Sync' );
```

If the repository is renamed, update this constant before publishing the next version.

## File Structure

```text
cpt-menu-sync/
├── cpt-menu-sync.php
├── README.md
├── changelog.md
├── LICENSE
├── uninstall.php
├── .gitignore
├── assets/
│   ├── css/
│   │   └── admin.css
│   ├── js/
│   │   └── admin.js
│   └── images/
│       └── logo.svg
└── includes/
    ├── class-cptms-admin.php
    ├── class-cptms-plugin.php
    ├── class-cptms-settings.php
    ├── class-cptms-sync-engine.php
    └── class-cptms-updater.php
```

## Architecture

The plugin is deliberately split into small responsibilities.

### `cpt-menu-sync.php`

Plugin bootstrap, constants, file loading, and activation registration.

### `CPTMS_Plugin`

Dependency wiring and plugin initialization.

### `CPTMS_Settings`

Rule storage, validation, and sanitization.

### `CPTMS_Sync_Engine`

All menu synchronization behavior. It has no admin-page rendering responsibilities.

### `CPTMS_Admin`

Tools page, rule editor, notices, asset loading, settings submission, manual synchronization, and update-status controls.

### `CPTMS_Updater`

GitHub Releases integration, WordPress update-transient integration, plugin information, release caching/diagnostics, forced update checks, and GitHub source-directory normalization.

### `admin.js`

Handles repeatable rule cards and dynamically populates parent-menu-item choices after a menu is selected.

This separation is intentional so future additions can be implemented without turning the main plugin file into a large all-purpose script.

## Admin Icon

The plugin uses the WordPress Dashicon:

```text
dashicons-share-alt
```

WordPress does not provide a dedicated icon argument for individual submenu pages under **Tools**, so the Tools entry includes the requested Dashicon in its menu label and the page heading uses the same `dashicons-share-alt` icon.

The included `assets/images/logo.svg` is a temporary project logo displayed on the admin page and can be replaced later without changing the synchronization engine.

## Notes About Menu Ordering

Classic WordPress menu ordering is global across all items in a menu, even though the UI displays parent/child nesting.

CPT Menu Sync reorders the menu slots occupied by items managed by a rule while preserving the relative ordering of unrelated menu items. This lets managed CPT children follow the selected content order without unnecessarily taking ownership of the rest of the navigation menu.

## Uninstall Behavior

Version `0.1.0` intentionally does not delete menu items or rule data automatically when the plugin is deactivated.

This prevents an accidental plugin deactivation from destructively altering site navigation.

Future versions may add an explicit cleanup/uninstall option if needed.

## Development Principles

CPT Menu Sync is intended to remain narrowly focused:

> Keep post-type content synchronized with classic WordPress navigation menus.

Features that fit this scope can be added without turning the plugin into a general-purpose menu builder.

Potential future additions could include:

- taxonomy filters
- post include/exclude controls
- ordering by a selected custom field
- per-rule manual menu-label templates
- duplicate-rule helpers
- export/import of rules
- WP-CLI synchronization command

## Changelog

### 0.1.0

Initial plugin version.

- Added modular sync engine
- Added Tools admin page
- Added repeatable sync rules
- Added multiple-CPT support
- Added menu and parent-item selectors
- Added WordPress `menu_order` / Post Types Order compatible sorting
- Added alphabetical and date sorting
- Added event-driven synchronization
- Added manual Sync Now action and report
- Added title synchronization
- Added stale-item removal
- Added adoption of existing menu items
- Added managed-item metadata tagging
- Added temporary admin branding and `dashicons-share-alt`
- Added GitHub Releases updater compatible with WordPress plugin updates
- Added automatic GitHub source-ZIP folder normalization
- Added GitHub update diagnostics and Check for Updates control
- Added GitHub-ready repository metadata and release workflow

## License

MIT License.
