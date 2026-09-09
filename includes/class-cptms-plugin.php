<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CPTMS_Plugin {

    private static $instance = null;
    private $engine;
    private $admin;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        if ( false === get_option( CPTMS_Settings::OPTION_KEY, false ) ) {
            add_option( CPTMS_Settings::OPTION_KEY, CPTMS_Settings::defaults(), '', false );
        }
    }

    private function __construct() {
        $this->engine = new CPTMS_Sync_Engine();
        $this->engine->register_hooks();

        if ( is_admin() ) {
            $this->admin = new CPTMS_Admin( $this->engine );
            $this->admin->register_hooks();
        }
    }
}
