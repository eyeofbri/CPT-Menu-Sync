<?php
/**
 * Plugin Name:       CPT Menu Sync
 * Plugin URI:         https://github.com/eyeofbri/CPT-Menu-Sync
 * Description:       Prototype tool for synchronizing one WordPress post type beneath a selected classic menu parent.
 * Version:           0.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Brian McLendon
 * Author URI:        https://github.com/eyeofbri
 * License:           MIT
 * License URI:       https://opensource.org/license/mit/
 * Text Domain:       cpt-menu-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CPTMS_VERSION', '0.0.1' );
define( 'CPTMS_FILE', __FILE__ );
define( 'CPTMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CPTMS_URL', plugin_dir_url( __FILE__ ) );

require_once CPTMS_PATH . 'includes/class-cptms-settings.php';
require_once CPTMS_PATH . 'includes/class-cptms-sync-engine.php';
require_once CPTMS_PATH . 'includes/class-cptms-admin.php';
require_once CPTMS_PATH . 'includes/class-cptms-plugin.php';

register_activation_hook( __FILE__, array( 'CPTMS_Plugin', 'activate' ) );

CPTMS_Plugin::instance();
