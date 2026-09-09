<?php
/**
 * CPT Menu Sync uninstall handler.
 *
 * The initial prototype intentionally leaves configuration and menu items
 * intact so uninstalling cannot unexpectedly change site navigation.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Intentionally non-destructive.
