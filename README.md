# CPT Menu Sync

**CPT Menu Sync** is a small WordPress plugin that keeps posts from a post type automatically synced into a normal WordPress navigation menu.

If you found this because you were searching for something like:

- "automatically add custom post type posts to a WordPress menu"
- "sync CPT posts to a menu in WordPress"
- "keep Services posts updated in the main menu"
- "use Post Types Order for WordPress menu items"

...this plugin is meant to solve exactly that problem without making you manually update the menu every time a post is added, renamed, unpublished, or reordered.

A common example is a site with a **Services** custom post type. You may already have a menu item called **Services** and want every published Service to appear below it automatically:

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

CPT Menu Sync creates those child links as normal WordPress menu items and keeps them in sync for you.

## What It Does

You create a simple rule that says:

```text
Post Type → Menu → Parent Menu Item
```

For example:

```text
Services → Main Menu → Services
```

After that, CPT Menu Sync can automatically:

- add newly published posts to the menu
- update menu labels when post titles change
- remove managed menu items when a post is unpublished or deleted
- keep menu items in the same order as your CPT posts
- work with multiple post types and multiple menus
- avoid adding duplicate menu items

The plugin runs when relevant content changes. It does **not** rebuild your menus on every frontend page load.

---

# Quick Start

## 1. Install the Plugin

Install and activate **CPT Menu Sync** like a normal WordPress plugin.

If you downloaded a ZIP:

1. Go to **Plugins → Add Plugin** in WordPress.
2. Click **Upload Plugin**.
3. Choose the CPT Menu Sync ZIP file.
4. Install and activate it.

If you are installing the files manually, place the plugin in:

```text
/wp-content/plugins/cpt-menu-sync/
```

Then activate **CPT Menu Sync** from the WordPress Plugins screen.

## 2. Make Sure Your Parent Menu Item Already Exists

CPT Menu Sync adds posts **under an existing menu item**.

For example, if you want your Service posts to appear under **Services**, first make sure your WordPress menu already contains a Services item:

```text
Home
About
Services
Contact
```

You can create or edit classic WordPress menus from the normal WordPress menu editor used by your site.

## 3. Open CPT Menu Sync

In WordPress admin, go to:

**Tools → CPT Menu Sync**

## 4. Add a Sync Rule

Choose:

- **Post Type** — the content you want to add, such as Services
- **Menu** — the WordPress navigation menu to update
- **Parent Menu Item** — the existing item those posts should appear beneath
- **Order** — how those posts should be arranged

For a typical Services setup:

```text
Post Type: Services
Menu: Main Menu
Parent Item: Services
Order: Menu Order → Title
```

Leave the normal options enabled unless you have a reason to change them:

- **Sync Titles** — keeps menu labels matched to post titles
- **Remove Missing** — removes managed menu links if the source post is unpublished/deleted
- **Adopt Existing** — uses an existing matching menu item instead of creating a duplicate

Save the rule.

## 5. Sync

Saving your settings performs a sync automatically.

You can also click **Sync Now** at any time.

You will see a simple report such as:

```text
Matched 12 · Added 2 · Updated 10 · Removed 0
```

That's it. From then on, the plugin keeps the selected posts and menu in sync.

---

# Using Post Types Order

CPT Menu Sync works with the popular **Post Types Order** plugin and other setups that use WordPress's built-in `menu_order` value.

If you drag your Services into a custom order with Post Types Order, choose:

**Menu Order → Title**

in CPT Menu Sync.

The menu children will then follow that order.

For example, if your Services are arranged as:

```text
Waste Management
Janitorial Services
Landscaping
Grease Trap Services
```

CPT Menu Sync will use the same sequence beneath the selected parent menu item.

If two posts happen to have the same `menu_order`, their titles are used as a secondary alphabetical sort.

If you do not use Post Types Order, you can simply choose **Title A–Z** instead.

---

# Multiple Post Types

You are not limited to one CPT.

Create as many sync rules as you need:

```text
Services  → Main Menu   → Services
Locations → Main Menu   → Locations
Team      → About Menu  → Our Team
Resources → Footer Menu → Resources
```

Each rule works independently.

# One Post Type in Multiple Menus

You can also use the same post type in more than one menu:

```text
Services → Main Menu   → Services
Services → Mobile Menu → Services
Services → Footer Menu → Our Services
```

Just create a separate rule for each menu.

---

# Rule Options Explained

## Enabled

Turns a rule on or off without deleting it.

## Post Type

The WordPress post type whose posts should be added to the menu.

This can be a custom post type such as:

- Services
- Locations
- Team
- Resources
- Products

or another manageable post type available on the site.

## Menu

The classic WordPress navigation menu that CPT Menu Sync should update.

## Parent Menu Item

The existing menu item that should contain the synchronized posts.

The plugin stores the actual WordPress menu-item ID, so renaming the parent later does not normally break the rule.

## Order

Available sorting options include:

- **Menu Order → Title**
- **Title A–Z**
- **Title Z–A**
- **Newest first**
- **Oldest first**

Use **Menu Order → Title** if your CPT supports manual ordering or you use Post Types Order.

## Sync Titles

When enabled, the menu label follows the post title.

If you rename:

```text
Commercial Cleaning
```

to:

```text
Commercial & Industrial Cleaning
```

the managed menu item will also update.

Disable this option if you intentionally use custom menu labels.

## Remove Missing

When enabled, a managed menu item is removed if its source post no longer belongs in the rule—for example if the post is drafted, trashed, deleted, or unpublished.

CPT Menu Sync only removes menu items it knows it manages. It does not blindly delete unrelated menu links.

## Adopt Existing

If the post is already in the selected menu, CPT Menu Sync can adopt that menu item instead of creating another copy.

This is useful when setting up the plugin on a site that already has some CPT links added manually.

---

# What Happens When I Edit a Post?

CPT Menu Sync listens for changes to the post types used by your rules.

It can resync when a relevant post is:

- created
- edited
- published
- drafted
- trashed
- restored
- deleted/unpublished

It also reacts when a configured classic WordPress menu is edited.

The plugin queues the work so the same rule is not repeatedly run by several WordPress save hooks during one request.

It does **not** add extra synchronization work to normal visitor page loads.

---

# What Happens If I Delete a Rule?

Deleting or changing a rule does not automatically wipe its old links out of your navigation.

Instead, CPT Menu Sync releases those old items from plugin management and leaves them in the menu as normal WordPress menu items.

This is intentional. Changing a plugin setting should not unexpectedly destroy a site's navigation.

---

# Troubleshooting

## My posts are not appearing

Check these first:

1. Make sure the posts are **Published**.
2. Make sure the rule is **Enabled**.
3. Confirm that the correct **Post Type** is selected.
4. Confirm that the correct **Menu** is selected.
5. Confirm that the selected **Parent Menu Item** still exists.
6. Click **Sync Now** and review the sync report.

## The order is wrong

If you use Post Types Order or manually arrange your CPT posts, choose:

**Menu Order → Title**

If you just want alphabetical ordering, choose:

**Title A–Z**

Then click **Sync Now**.

## I already added some of these posts to the menu manually

Enable **Adopt Existing**.

CPT Menu Sync will try to use matching post-type menu items rather than adding duplicates.

## I renamed the Services menu item

That should be fine after the rule has been saved. CPT Menu Sync stores the selected parent by its menu-item ID rather than relying only on the visible title.

## I don't see my menu in the dropdown

This version works with **classic WordPress navigation menus**.

WordPress block-theme Navigation blocks use a different storage system and are not managed by this version.

## I don't see an update immediately after a GitHub release

GitHub release information is cached for up to one hour.

Go to:

**Tools → CPT Menu Sync**

and click:

**Check for Updates**

That forces a fresh GitHub check and refreshes WordPress's plugin update data.

---

# Requirements

- WordPress 6.0+
- PHP 7.4+
- A site using classic WordPress navigation menus
- Permission to manage WordPress menus

> **Block themes:** WordPress Navigation blocks use a different menu system. CPT Menu Sync currently manages classic WordPress navigation menus only.

---

# GitHub Updates

CPT Menu Sync can update through normal WordPress plugin updates using releases from:

```text
https://github.com/eyeofbri/CPT-Menu-Sync
```

When a newer normal GitHub Release is available, WordPress can show it on the standard **Plugins** and **Updates** screens.

You can also go to:

**Tools → CPT Menu Sync**

for update information including:

- installed version
- latest release version
- GitHub connection status
- last update check
- whether an update is available
- **Check for Updates**

The updater uses GitHub's automatically generated source ZIP, so release ZIP files do not need to be manually attached to every GitHub Release.

---

# For Developers / Repository Setup

Most WordPress users do not need anything below this point.

## Release Workflow

1. Update the plugin version in `cpt-menu-sync.php`.
2. Update `CPTMS_VERSION` to the same version.
3. Update `changelog.md` and this README.
4. Commit and push the version to GitHub.
5. Create a GitHub Release with a matching tag, for example:

```text
v0.1.1
```

6. Publish it as a normal release, not a draft or prerelease.

No manually uploaded release ZIP is required. The updater uses GitHub's generated source ZIP.

During a WordPress update, the plugin normalizes GitHub's generated source directory back to:

```text
cpt-menu-sync/
```

This prevents WordPress from installing the update as a second copy of the plugin.

## Repository Layout

The repository root should also be the plugin root:

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

Do not place another `cpt-menu-sync/` wrapper directory inside the repository.

## Repository Configuration

The repository used for updates is defined in `cpt-menu-sync.php`:

```php
define( 'CPTMS_GITHUB_REPOSITORY', 'eyeofbri/CPT-Menu-Sync' );
```

If the repository is renamed, update this constant before publishing the next release.

---

# Plugin Structure

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

The code is intentionally separated by responsibility:

- `cpt-menu-sync.php` — plugin bootstrap and constants
- `CPTMS_Plugin` — plugin initialization and dependency wiring
- `CPTMS_Settings` — rule storage and validation
- `CPTMS_Sync_Engine` — menu synchronization behavior
- `CPTMS_Admin` — Tools page, rule editor, reports, and admin actions
- `CPTMS_Updater` — GitHub Releases / WordPress updater integration
- `admin.js` — repeatable rules and parent-item dropdown behavior

---

# Managed Menu Item Data

CPT Menu Sync marks generated or adopted menu items with private post meta:

```text
_cptms_rule_id
```

The value is the unique ID of the rule managing that menu item.

This is how the plugin can tell its own managed items apart from unrelated menu links.

---

# Admin Branding

The plugin uses the WordPress Dashicon:

```text
dashicons-share-alt
```

The included `assets/images/logo.svg` is a temporary project logo and can be replaced later without changing the synchronization engine.

---

# Changelog

## 0.1.0

First public-ready release.

- Added modular CPT-to-menu sync engine
- Added **Tools → CPT Menu Sync** admin page
- Added repeatable sync rules
- Added support for multiple CPTs and menus
- Added menu and parent-item selectors
- Added `menu_order` / Post Types Order compatible sorting
- Added alphabetical and date sorting modes
- Added automatic event-driven synchronization
- Added manual **Sync Now** and sync reports
- Added optional title synchronization
- Added stale-item removal
- Added adoption of existing menu items
- Added managed-item ownership metadata
- Added temporary admin branding and `dashicons-share-alt`
- Added GitHub Releases updater for normal WordPress plugin updates
- Added GitHub source-folder normalization during updates
- Added update diagnostics and **Check for Updates**

## 0.0.2

Expanded the prototype into a reusable rule-based plugin.

- Added multiple synchronization rules
- Added support for multiple CPTs and menus
- Added Post Types Order compatible sorting
- Added safer ownership and adoption behavior
- Added expanded admin controls

## 0.0.1

Initial working prototype.

- Added basic CPT-to-menu synchronization
- Added parent menu selection
- Added alphabetical ordering
- Added manual sync controls

---

# Author

**Brian McLendon**  
GitHub: https://github.com/eyeofbri

Repository: https://github.com/eyeofbri/CPT-Menu-Sync

# License

MIT License. See [`LICENSE`](LICENSE).
