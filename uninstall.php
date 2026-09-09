<?php
/**
 * CPT Menu Sync uninstall handler.
 *
 * v0.0.2 intentionally leaves rule settings and navigation menu items intact.
 * Removing the plugin should not unexpectedly alter a site's navigation.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Intentionally non-destructive.
