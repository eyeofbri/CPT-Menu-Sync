<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin bootstrap / dependency wiring.
 */
final class CPTMS_Plugin {

    /** @var CPTMS_Plugin|null */
    private static $instance = null;

    /** @var CPTMS_Sync_Engine */
    private $engine;

    /** @var CPTMS_Admin|null */
    private $admin = null;

    /**
     * Singleton accessor.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Activation setup.
     */
    public static function activate() {
        if ( false === get_option( CPTMS_Settings::OPTION_KEY, false ) ) {
            add_option( CPTMS_Settings::OPTION_KEY, array(), '', false );
        }
    }

    private function __construct() {
        CPTMS_Settings::maybe_migrate_legacy_rule();

        $this->engine = new CPTMS_Sync_Engine();
        $this->engine->register_hooks();


        if ( is_admin() ) {
            $this->admin = new CPTMS_Admin( $this->engine );
            $this->admin->register_hooks();
        }
    }

    /**
     * Expose the engine for advanced integrations.
     */
    public function engine() {
        return $this->engine;
    }
}
