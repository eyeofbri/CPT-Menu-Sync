<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Initial Tools -> CPT Menu Sync admin screen.
 */
final class CPTMS_Admin {

    const PAGE_SLUG = 'cpt-menu-sync';

    private $engine;

    public function __construct( CPTMS_Sync_Engine $engine ) {
        $this->engine = $engine;
    }

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_cptms_save_rule', array( $this, 'handle_save' ) );
        add_action( 'admin_post_cptms_sync_now', array( $this, 'handle_sync_now' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( CPTMS_FILE ), array( $this, 'plugin_action_links' ) );
    }

    public function register_page() {
        add_management_page(
            __( 'CPT Menu Sync', 'cpt-menu-sync' ),
            '<span class="dashicons dashicons-share-alt" aria-hidden="true"></span> ' . __( 'CPT Menu Sync', 'cpt-menu-sync' ),
            'edit_theme_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style(
            'cptms-admin',
            CPTMS_URL . 'assets/css/admin.css',
            array( 'dashicons' ),
            CPTMS_VERSION
        );

        wp_enqueue_script(
            'cptms-admin',
            CPTMS_URL . 'assets/js/admin.js',
            array(),
            CPTMS_VERSION,
            true
        );

        wp_localize_script(
            'cptms-admin',
            'CPTMS_DATA',
            array(
                'menus'   => $this->get_menu_item_data(),
                'strings' => array(
                    'selectParent' => __( 'Select a parent item', 'cpt-menu-sync' ),
                    'noItems'      => __( 'This menu has no items.', 'cpt-menu-sync' ),
                ),
            )
        );
    }

    public function plugin_action_links( $links ) {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'cpt-menu-sync' ) . '</a>'
        );

        return $links;
    }

    public function render_page() {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage navigation menus.', 'cpt-menu-sync' ) );
        }

        $rule       = CPTMS_Settings::get_rule();
        $post_types = $this->get_post_types();
        $menus      = wp_get_nav_menus();
        $notice     = $this->consume_notice();
        ?>
        <div class="wrap cptms-wrap">
            <div class="cptms-header">
                <div class="cptms-logo">
                    <img src="<?php echo esc_url( CPTMS_URL . 'assets/images/logo.svg' ); ?>" alt="">
                </div>
                <div>
                    <h1><span class="dashicons dashicons-share-alt cptms-title-icon" aria-hidden="true"></span><?php esc_html_e( 'CPT Menu Sync', 'cpt-menu-sync' ); ?></h1>
                    <p><?php esc_html_e( 'Synchronize one post type beneath a selected parent in a classic WordPress navigation menu.', 'cpt-menu-sync' ); ?></p>
                </div>
            </div>

            <?php $this->render_notice( $notice ); ?>

            <div class="cptms-layout">
                <main class="cptms-main">
                    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                        <input type="hidden" name="action" value="cptms_save_rule">
                        <?php wp_nonce_field( 'cptms_save_rule' ); ?>

                        <section class="cptms-rule">
                            <div class="cptms-rule-header">
                                <div class="cptms-rule-title">
                                    <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                                    <strong><?php esc_html_e( 'Sync Configuration', 'cpt-menu-sync' ); ?></strong>
                                </div>
                            </div>

                            <div class="cptms-grid">
                                <div class="cptms-field">
                                    <label for="cptms-post-type"><?php esc_html_e( 'Post Type', 'cpt-menu-sync' ); ?></label>
                                    <select id="cptms-post-type" name="rule[post_type]" required>
                                        <option value=""><?php esc_html_e( 'Select a post type', 'cpt-menu-sync' ); ?></option>
                                        <?php foreach ( $post_types as $post_type ) : ?>
                                            <option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $rule['post_type'], $post_type->name ); ?>>
                                                <?php echo esc_html( $post_type->labels->singular_name . ' (' . $post_type->name . ')' ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="cptms-field">
                                    <label for="cptms-menu"><?php esc_html_e( 'Menu', 'cpt-menu-sync' ); ?></label>
                                    <select id="cptms-menu" class="cptms-menu-select" name="rule[menu_id]" required>
                                        <option value="0"><?php esc_html_e( 'Select a menu', 'cpt-menu-sync' ); ?></option>
                                        <?php foreach ( $menus as $menu ) : ?>
                                            <option value="<?php echo esc_attr( $menu->term_id ); ?>" <?php selected( (int) $rule['menu_id'], (int) $menu->term_id ); ?>>
                                                <?php echo esc_html( $menu->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="cptms-field">
                                    <label for="cptms-parent"><?php esc_html_e( 'Parent Menu Item', 'cpt-menu-sync' ); ?></label>
                                    <select id="cptms-parent" class="cptms-parent-select" name="rule[parent_menu_item_id]" data-selected="<?php echo esc_attr( $rule['parent_menu_item_id'] ); ?>" required>
                                        <option value="0"><?php esc_html_e( 'Select a parent item', 'cpt-menu-sync' ); ?></option>
                                    </select>
                                </div>

                                <div class="cptms-field">
                                    <label><?php esc_html_e( 'Order', 'cpt-menu-sync' ); ?></label>
                                    <input type="text" class="regular-text" value="<?php esc_attr_e( 'Title A–Z', 'cpt-menu-sync' ); ?>" disabled>
                                </div>
                            </div>
                        </section>

                        <div class="cptms-form-actions">
                            <?php submit_button( __( 'Save & Sync', 'cpt-menu-sync' ), 'primary', 'submit', false ); ?>
                        </div>
                    </form>
                </main>

                <aside class="cptms-sidebar">
                    <div class="cptms-panel">
                        <h2><?php esc_html_e( 'Manual Sync', 'cpt-menu-sync' ); ?></h2>
                        <p><?php esc_html_e( 'Run the configured synchronization immediately.', 'cpt-menu-sync' ); ?></p>
                        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                            <input type="hidden" name="action" value="cptms_sync_now">
                            <?php wp_nonce_field( 'cptms_sync_now' ); ?>
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                <?php esc_html_e( 'Sync Now', 'cpt-menu-sync' ); ?>
                            </button>
                        </form>
                    </div>

                    <div class="cptms-panel">
                        <h2><?php esc_html_e( 'Prototype', 'cpt-menu-sync' ); ?></h2>
                        <p><?php esc_html_e( 'v0.0.1 supports one post type, one menu parent, and alphabetical ordering.', 'cpt-menu-sync' ); ?></p>
                    </div>

                    <div class="cptms-panel cptms-about">
                        <h2><?php esc_html_e( 'CPT Menu Sync', 'cpt-menu-sync' ); ?></h2>
                        <p><?php echo esc_html( 'v' . CPTMS_VERSION ); ?></p>
                        <p><?php esc_html_e( 'Author:', 'cpt-menu-sync' ); ?> <a href="https://github.com/eyeofbri" target="_blank" rel="noopener noreferrer">Brian McLendon</a></p>
                        <p><?php esc_html_e( 'License: MIT', 'cpt-menu-sync' ); ?></p>
                    </div>
                </aside>
            </div>
        </div>
        <?php
    }

    public function handle_save() {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage navigation menus.', 'cpt-menu-sync' ) );
        }

        check_admin_referer( 'cptms_save_rule' );

        $raw_rule = isset( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();
        $rule     = CPTMS_Settings::sanitize_rule( $raw_rule );

        CPTMS_Settings::save_rule( $rule );
        $report = $this->engine->sync();

        $this->store_notice(
            array(
                'type'    => empty( $report['errors'] ) ? 'success' : 'error',
                'message' => empty( $report['errors'] )
                    ? __( 'Configuration saved and synchronized.', 'cpt-menu-sync' )
                    : __( 'Configuration saved, but synchronization needs attention.', 'cpt-menu-sync' ),
                'report'  => $report,
            )
        );

        wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) );
        exit;
    }

    public function handle_sync_now() {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage navigation menus.', 'cpt-menu-sync' ) );
        }

        check_admin_referer( 'cptms_sync_now' );

        $report = $this->engine->sync();

        $this->store_notice(
            array(
                'type'    => empty( $report['errors'] ) ? 'success' : 'error',
                'message' => empty( $report['errors'] )
                    ? __( 'Synchronization complete.', 'cpt-menu-sync' )
                    : __( 'Synchronization could not complete.', 'cpt-menu-sync' ),
                'report'  => $report,
            )
        );

        wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) );
        exit;
    }

    private function get_post_types() {
        $objects = get_post_types( array( 'show_ui' => true ), 'objects' );
        unset( $objects['attachment'] );

        uasort(
            $objects,
            static function( $a, $b ) {
                return strcasecmp( $a->labels->singular_name, $b->labels->singular_name );
            }
        );

        return $objects;
    }

    private function get_menu_item_data() {
        $data = array();

        foreach ( wp_get_nav_menus() as $menu ) {
            $items = wp_get_nav_menu_items( $menu->term_id );
            $items = is_array( $items ) ? $items : array();

            $data[ (string) $menu->term_id ] = array();

            foreach ( $items as $item ) {
                $data[ (string) $menu->term_id ][] = array(
                    'id'    => (int) $item->ID,
                    'label' => wp_strip_all_tags( $item->title ),
                );
            }
        }

        return $data;
    }

    private function store_notice( array $notice ) {
        set_transient( 'cptms_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
    }

    private function consume_notice() {
        $key    = 'cptms_notice_' . get_current_user_id();
        $notice = get_transient( $key );
        delete_transient( $key );

        return is_array( $notice ) ? $notice : null;
    }

    private function render_notice( $notice ) {
        if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
            return;
        }

        $class  = 'error' === ( isset( $notice['type'] ) ? $notice['type'] : '' ) ? 'notice-error' : 'notice-success';
        $report = isset( $notice['report'] ) && is_array( $notice['report'] ) ? $notice['report'] : array();
        ?>
        <div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
            <p><strong><?php echo esc_html( $notice['message'] ); ?></strong></p>
            <?php if ( ! empty( $report ) ) : ?>
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            'Matched %1$d · Added %2$d · Updated %3$d · Removed %4$d',
                            isset( $report['matched'] ) ? (int) $report['matched'] : 0,
                            isset( $report['created'] ) ? (int) $report['created'] : 0,
                            isset( $report['updated'] ) ? (int) $report['updated'] : 0,
                            isset( $report['removed'] ) ? (int) $report['removed'] : 0
                        )
                    );
                    ?>
                </p>
                <?php if ( ! empty( $report['errors'] ) ) : ?>
                    <ul>
                        <?php foreach ( $report['errors'] as $error ) : ?>
                            <li><?php echo esc_html( $error ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
