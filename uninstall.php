<?php
/**
 * CPT Menu Sync uninstall handler.
 *
 * Intentionally non-destructive for navigation/configuration data.
 * Rule settings and menu items are retained so deleting the plugin cannot
 * unexpectedly alter a site's navigation. Only disposable updater state is
 * removed.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_site_transient( 'cptms_github_latest_release' );
delete_site_option( 'cptms_github_update_status' );
