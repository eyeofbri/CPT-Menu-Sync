<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tools -> CPT Menu Sync admin interface.
 */
final class CPTMS_Admin {

    const PAGE_SLUG = 'cpt-menu-sync';

    /** @var CPTMS_Sync_Engine */
    private $engine;

    public function __construct( CPTMS_Sync_Engine $engine ) {
        $this->engine = $engine;
    }

    /**
     * Register admin hooks.
     */
    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Non-JavaScript fallbacks.
        add_action( 'admin_post_cptms_save_rules', array( $this, 'handle_save' ) );
        add_action( 'admin_post_cptms_sync_now', array( $this, 'handle_sync_now' ) );
        add_action( 'admin_post_cptms_check_updates', array( $this, 'handle_check_updates' ) );

        // AJAX-powered admin actions.
        add_action( 'wp_ajax_cptms_save_rules', array( $this, 'ajax_save_rules' ) );
        add_action( 'wp_ajax_cptms_sync_now', array( $this, 'ajax_sync_now' ) );
        add_action( 'wp_ajax_cptms_check_updates', array( $this, 'ajax_check_updates' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( CPTMS_FILE ), array( $this, 'plugin_action_links' ) );
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 4 );
    }

    /**
     * Add page beneath WordPress Tools.
     *
     * WordPress does not provide a custom icon argument for Tools submenu
     * pages, so dashicons-share-alt is used in the submenu label.
     */
    public function register_page() {
        add_management_page(
            __( 'CPT Menu Sync', 'cpt-menu-sync' ),
            '<span class="dashicons dashicons-share-alt" aria-hidden="true"></span> ' . __( 'CPT Menu Sync', 'cpt-menu-sync' ),
            'edit_theme_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Load CSS/JS only on this plugin's admin page.
     */
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
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'menus'   => $this->get_menu_item_data(),
                'strings' => array(
                    'selectParent' => __( 'Select a parent item', 'cpt-menu-sync' ),
                    'noItems'      => __( 'This menu has no items.', 'cpt-menu-sync' ),
                    'removeRule'   => __( 'Remove rule', 'cpt-menu-sync' ),
                    'requestError' => __( 'Something went wrong. Please try again.', 'cpt-menu-sync' ),
                ),
            )
        );
    }

    /**
     * Add Settings action link on Plugins screen.
     */
    public function plugin_action_links( $links ) {
        $url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
        array_unshift(
            $links,
            '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cpt-menu-sync' ) . '</a>'
        );
        return $links;
    }

    /**
     * Open the plugin author's "By" link and "Visit plugin site" link in
     * new tabs without changing other plugins' row metadata.
     */
    public function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
        unset( $status );

        if ( plugin_basename( CPTMS_FILE ) !== $plugin_file || ! is_array( $plugin_meta ) ) {
            return $plugin_meta;
        }

        $author_uri = ! empty( $plugin_data['AuthorURI'] ) ? (string) $plugin_data['AuthorURI'] : '';
        $plugin_uri = ! empty( $plugin_data['PluginURI'] ) ? (string) $plugin_data['PluginURI'] : '';

        foreach ( $plugin_meta as $index => $meta ) {
            if ( ! is_string( $meta ) || false === stripos( $meta, '<a ' ) ) {
                continue;
            }

            $matches_author = $author_uri && false !== strpos( $meta, $author_uri );
            $matches_plugin = $plugin_uri && false !== strpos( $meta, $plugin_uri );

            if ( ! $matches_author && ! $matches_plugin ) {
                continue;
            }

            if ( false === stripos( $meta, ' target=' ) ) {
                $meta = preg_replace(
                    '/<a\s+/i',
                    '<a target="_blank" rel="noopener noreferrer" ',
                    $meta,
                    1
                );
            }

            $plugin_meta[ $index ] = $meta;
        }

        return $plugin_meta;
    }

    /**
     * Render the admin page.
     */
    public function render_page() {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage navigation menus.', 'cpt-menu-sync' ) );
        }

        $rules      = CPTMS_Settings::get_rules();
        $post_types = $this->get_post_types();
        $menus      = wp_get_nav_menus();
        $notice     = $this->consume_notice();
        $updates    = class_exists( 'CPTMS_Updater' ) ? CPTMS_Updater::get_diagnostics() : array();
        ?>
        <div class="wrap cptms-wrap">
            <div class="cptms-header">
                <div class="cptms-logo">
                    <img src="<?php echo esc_url( CPTMS_URL . 'assets/images/logo.svg' ); ?>" alt="">
                </div>
                <div>
                    <h1><?php esc_html_e( 'CPT Menu Sync', 'cpt-menu-sync' ); ?></h1>
                    <p><?php esc_html_e( 'Keep posts from any post type synchronized beneath selected parent items in classic WordPress menus.', 'cpt-menu-sync' ); ?></p>
                </div>
            </div>

            <div id="cptms-notice-area" aria-live="polite">
                <?php $this->render_notice( $notice ); ?>
            </div>

            <?php if ( empty( $menus ) ) : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e( 'No classic WordPress navigation menus were found. Create a menu first, then return here to add a sync rule.', 'cpt-menu-sync' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="cptms-layout">
                <main class="cptms-main">
                    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="cptms-rules-form">
                        <input type="hidden" name="action" value="cptms_save_rules">
                        <?php wp_nonce_field( 'cptms_save_rules' ); ?>

                        <div class="cptms-section-heading">
                            <div>
                                <h2><?php esc_html_e( 'Sync Rules', 'cpt-menu-sync' ); ?></h2>
                                <p><?php esc_html_e( 'Each rule connects one post type to one menu parent. Add another rule to sync the same post type to another menu.', 'cpt-menu-sync' ); ?></p>
                            </div>
                            <button type="button" class="button" id="cptms-add-rule">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                <?php esc_html_e( 'Add Rule', 'cpt-menu-sync' ); ?>
                            </button>
                        </div>

                        <div id="cptms-rules" data-next-index="<?php echo esc_attr( count( $rules ) ); ?>">
                            <?php foreach ( $rules as $index => $rule ) : ?>
                                <?php $this->render_rule_card( $index, $rule, $post_types, $menus ); ?>
                            <?php endforeach; ?>
                        </div>

                        <div id="cptms-empty" class="cptms-empty <?php echo empty( $rules ) ? '' : 'is-hidden'; ?>">
                            <span class="dashicons dashicons-share-alt" aria-hidden="true"></span>
                            <h3><?php esc_html_e( 'No sync rules yet', 'cpt-menu-sync' ); ?></h3>
                            <p><?php esc_html_e( 'Add a rule to connect a post type to a navigation menu.', 'cpt-menu-sync' ); ?></p>
                        </div>

                        <div class="cptms-form-actions">
                            <button type="submit" class="button button-primary" id="cptms-save-rules">
                                <?php esc_html_e( 'Save Rules', 'cpt-menu-sync' ); ?>
                            </button>
                        </div>
                    </form>

                    <details class="cptms-howto">
                        <summary>
                            <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
                            <?php esc_html_e( 'How to use CPT Menu Sync', 'cpt-menu-sync' ); ?>
                        </summary>
                        <div class="cptms-howto-content">
                            <ol>
                                <li><?php esc_html_e( 'Make sure the parent item you want to use already exists in a classic WordPress menu.', 'cpt-menu-sync' ); ?></li>
                                <li><?php esc_html_e( 'Click Add Rule and choose the Post Type, Menu, and Parent Menu Item.', 'cpt-menu-sync' ); ?></li>
                                <li><?php esc_html_e( 'Choose an order. Use Menu Order → Title if you use Post Types Order or another menu_order-based sorter.', 'cpt-menu-sync' ); ?></li>
                                <li><?php esc_html_e( 'Leave Sync titles, Remove missing, and Adopt existing enabled for the usual automatic setup.', 'cpt-menu-sync' ); ?></li>
                                <li><?php esc_html_e( 'Click Save Rules. The plugin saves the rule and immediately synchronizes the matching posts.', 'cpt-menu-sync' ); ?></li>
                            </ol>
                            <p><?php esc_html_e( 'Need to force a refresh later? Use Sync Now in the sidebar.', 'cpt-menu-sync' ); ?></p>
                        </div>
                    </details>
                </main>

                <aside class="cptms-sidebar">
                    <div class="cptms-panel">
                        <h2><?php esc_html_e( 'Manual Sync', 'cpt-menu-sync' ); ?></h2>
                        <p><?php esc_html_e( 'Run all enabled rules immediately. Automatic syncing also occurs when matching posts or configured menus change.', 'cpt-menu-sync' ); ?></p>

                        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="cptms-sync-form">
                            <input type="hidden" name="action" value="cptms_sync_now">
                            <?php wp_nonce_field( 'cptms_sync_now' ); ?>
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                <?php esc_html_e( 'Sync Now', 'cpt-menu-sync' ); ?>
                            </button>
                        </form>
                    </div>

                    <div class="cptms-panel">
                        <h2><?php esc_html_e( 'Ordering', 'cpt-menu-sync' ); ?></h2>
                        <p><?php esc_html_e( '“Menu Order” uses WordPress’s native menu_order value, making it compatible with drag-and-drop post ordering plugins such as Post Types Order.', 'cpt-menu-sync' ); ?></p>
                    </div>

                    <div class="cptms-panel cptms-updates" id="cptms-update-panel">
                        <h2><?php esc_html_e( 'GitHub Updates', 'cpt-menu-sync' ); ?></h2>
                        <div id="cptms-update-content">
                            <?php $this->render_update_content( $updates ); ?>
                        </div>
                    </div>

                    <div class="cptms-panel cptms-about">
                        <h2><?php esc_html_e( 'CPT Menu Sync', 'cpt-menu-sync' ); ?></h2>
                        <p><?php echo esc_html( 'v' . CPTMS_VERSION ); ?></p>
                        <p>
                            <?php esc_html_e( 'Author:', 'cpt-menu-sync' ); ?>
                            <a href="https://github.com/eyeofbri" target="_blank" rel="noopener noreferrer">Brian McLendon</a>
                        </p>
                        <p><?php esc_html_e( 'License: MIT', 'cpt-menu-sync' ); ?></p>
                    </div>
                </aside>
            </div>
        </div>

        <script type="text/template" id="cptms-rule-template">
            <?php
            $this->render_rule_card(
                '__INDEX__',
                array(
                    'id'                  => '',
                    'enabled'             => true,
                    'post_type'           => '',
                    'menu_id'             => 0,
                    'parent_menu_item_id' => 0,
                    'sort_mode'           => 'menu_order',
                    'sync_title'          => true,
                    'remove_missing'      => true,
                    'adopt_existing'      => true,
                ),
                $post_types,
                $menus
            );
            ?>
        </script>
        <?php
    }

    /**
     * Save rules and sync immediately so the saved configuration is live.
     * Non-JavaScript fallback.
     */
    public function handle_save() {
        $this->require_menu_capability();
        check_admin_referer( 'cptms_save_rules' );

        $result = $this->save_rules_from_request();
        $this->store_notice( $result['notice'] );
        $this->redirect_to_page();
    }

    /**
     * Run all enabled rules immediately. Non-JavaScript fallback.
     */
    public function handle_sync_now() {
        $this->require_menu_capability();
        check_admin_referer( 'cptms_sync_now' );

        $notice = $this->sync_now();
        $this->store_notice( $notice );
        $this->redirect_to_page();
    }

    /**
     * Force a fresh GitHub release check. Non-JavaScript fallback.
     */
    public function handle_check_updates() {
        $this->require_update_capability();
        check_admin_referer( 'cptms_check_updates' );

        $result = $this->check_updates();
        $this->store_notice( $result['notice'] );
        $this->redirect_to_page();
    }

    /**
     * AJAX: save rules and synchronize them without reloading the Tools page.
     */
    public function ajax_save_rules() {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to manage navigation menus.', 'cpt-menu-sync' ) ), 403 );
        }

        check_ajax_referer( 'cptms_save_rules' );
        $result = $this->save_rules_from_request();

        wp_send_json_success(
            array(
                'notice_html' => $this->get_notice_html( $result['notice'] ),
                'rules'       => $result['rules'],
            )
        );
    }

    /**
     * AJAX: synchronize all enabled rules without reloading the Tools page.
     */
    public function ajax_sync_now() {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to manage navigation menus.', 'cpt-menu-sync' ) ), 403 );
        }

        check_ajax_referer( 'cptms_sync_now' );
        $notice = $this->sync_now();

        wp_send_json_success(
            array(
                'notice_html' => $this->get_notice_html( $notice ),
            )
        );
    }

    /**
     * AJAX: force a fresh GitHub release check and refresh diagnostics.
     */
    public function ajax_check_updates() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to update plugins.', 'cpt-menu-sync' ) ), 403 );
        }

        check_ajax_referer( 'cptms_check_updates' );
        $result = $this->check_updates();

        wp_send_json_success(
            array(
                'notice_html' => $this->get_notice_html( $result['notice'] ),
                'panel_html'  => $this->get_update_content_html( $result['diagnostics'] ),
            )
        );
    }

    /**
     * Save/sanitize rule configuration and perform an immediate sync.
     */
    private function save_rules_from_request() {
        $old_rules = CPTMS_Settings::get_rules();
        $raw_rules = isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : array();
        $rules     = CPTMS_Settings::sanitize_rules( $raw_rules );

        $this->engine->release_changed_rules( $old_rules, $rules );
        CPTMS_Settings::save_rules( $rules );
        $report = $this->engine->sync_all();

        return array(
            'rules'  => $rules,
            'notice' => array(
                'type'    => 'success',
                'message' => __( 'Rules saved and synchronized.', 'cpt-menu-sync' ),
                'report'  => $report,
            ),
        );
    }

    /**
     * Run all configured enabled rules and return a notice payload.
     */
    private function sync_now() {
        return array(
            'type'    => 'success',
            'message' => __( 'Synchronization complete.', 'cpt-menu-sync' ),
            'report'  => $this->engine->sync_all(),
        );
    }

    /**
     * Force the updater to refresh and return diagnostics + notice payloads.
     */
    private function check_updates() {
        $diagnostics = CPTMS_Updater::force_check();

        if ( ! empty( $diagnostics['update_available'] ) ) {
            $message = sprintf(
                /* translators: %s: latest available plugin version. */
                __( 'CPT Menu Sync %s is available through the WordPress plugin updater.', 'cpt-menu-sync' ),
                $diagnostics['latest_version']
            );
            $type = 'success';
        } elseif ( 'connected' === ( isset( $diagnostics['connection'] ) ? $diagnostics['connection'] : '' ) ) {
            $message = __( 'CPT Menu Sync is up to date.', 'cpt-menu-sync' );
            $type = 'success';
        } else {
            $message = ! empty( $diagnostics['message'] )
                ? $diagnostics['message']
                : __( 'CPT Menu Sync could not check GitHub Releases.', 'cpt-menu-sync' );
            $type = 'error';
        }

        return array(
            'diagnostics' => $diagnostics,
            'notice'      => array(
                'type'    => $type,
                'message' => $message,
            ),
        );
    }

    /**
     * Render one configurable rule card.
     */
    private function render_rule_card( $index, array $rule, array $post_types, array $menus ) {
        $name_prefix = 'rules[' . $index . ']';
        ?>
        <section class="cptms-rule" data-rule-index="<?php echo esc_attr( $index ); ?>">
            <input type="hidden" class="cptms-rule-id" name="<?php echo esc_attr( $name_prefix ); ?>[id]" value="<?php echo esc_attr( $rule['id'] ); ?>">

            <div class="cptms-rule-header">
                <div class="cptms-rule-title">
                    <span class="dashicons dashicons-share-alt" aria-hidden="true"></span>
                    <strong><?php esc_html_e( 'Sync Rule', 'cpt-menu-sync' ); ?></strong>
                </div>
                <div class="cptms-rule-header-actions">
                    <label class="cptms-toggle-label">
                        <input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>>
                        <?php esc_html_e( 'Enabled', 'cpt-menu-sync' ); ?>
                    </label>
                    <button type="button" class="button-link-delete cptms-remove-rule"><?php esc_html_e( 'Remove', 'cpt-menu-sync' ); ?></button>
                </div>
            </div>

            <div class="cptms-grid">
                <div class="cptms-field">
                    <label><?php esc_html_e( 'Post Type', 'cpt-menu-sync' ); ?></label>
                    <select name="<?php echo esc_attr( $name_prefix ); ?>[post_type]" required>
                        <option value=""><?php esc_html_e( 'Select a post type', 'cpt-menu-sync' ); ?></option>
                        <?php foreach ( $post_types as $post_type ) : ?>
                            <option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $rule['post_type'], $post_type->name ); ?>>
                                <?php echo esc_html( $post_type->labels->singular_name . ' (' . $post_type->name . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cptms-field">
                    <label><?php esc_html_e( 'Menu', 'cpt-menu-sync' ); ?></label>
                    <select class="cptms-menu-select" name="<?php echo esc_attr( $name_prefix ); ?>[menu_id]" required>
                        <option value=""><?php esc_html_e( 'Select a menu', 'cpt-menu-sync' ); ?></option>
                        <?php foreach ( $menus as $menu ) : ?>
                            <option value="<?php echo esc_attr( $menu->term_id ); ?>" <?php selected( (int) $rule['menu_id'], (int) $menu->term_id ); ?>>
                                <?php echo esc_html( $menu->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cptms-field">
                    <label><?php esc_html_e( 'Parent Menu Item', 'cpt-menu-sync' ); ?></label>
                    <select class="cptms-parent-select" name="<?php echo esc_attr( $name_prefix ); ?>[parent_menu_item_id]" data-selected="<?php echo esc_attr( $rule['parent_menu_item_id'] ); ?>" required>
                        <option value=""><?php esc_html_e( 'Select a parent item', 'cpt-menu-sync' ); ?></option>
                    </select>
                </div>

                <div class="cptms-field">
                    <label><?php esc_html_e( 'Order', 'cpt-menu-sync' ); ?></label>
                    <select name="<?php echo esc_attr( $name_prefix ); ?>[sort_mode]">
                        <option value="menu_order" <?php selected( $rule['sort_mode'], 'menu_order' ); ?>><?php esc_html_e( 'Menu Order → Title (Post Types Order compatible)', 'cpt-menu-sync' ); ?></option>
                        <option value="title_asc" <?php selected( $rule['sort_mode'], 'title_asc' ); ?>><?php esc_html_e( 'Title A–Z', 'cpt-menu-sync' ); ?></option>
                        <option value="title_desc" <?php selected( $rule['sort_mode'], 'title_desc' ); ?>><?php esc_html_e( 'Title Z–A', 'cpt-menu-sync' ); ?></option>
                        <option value="date_desc" <?php selected( $rule['sort_mode'], 'date_desc' ); ?>><?php esc_html_e( 'Newest first', 'cpt-menu-sync' ); ?></option>
                        <option value="date_asc" <?php selected( $rule['sort_mode'], 'date_asc' ); ?>><?php esc_html_e( 'Oldest first', 'cpt-menu-sync' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="cptms-options">
                <label>
                    <input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[sync_title]" value="1" <?php checked( ! empty( $rule['sync_title'] ) ); ?>>
                    <span><strong><?php esc_html_e( 'Sync titles', 'cpt-menu-sync' ); ?></strong> <?php esc_html_e( 'Keep menu labels matched to post titles.', 'cpt-menu-sync' ); ?></span>
                </label>

                <label>
                    <input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[remove_missing]" value="1" <?php checked( ! empty( $rule['remove_missing'] ) ); ?>>
                    <span><strong><?php esc_html_e( 'Remove missing', 'cpt-menu-sync' ); ?></strong> <?php esc_html_e( 'Remove managed items when posts are unpublished, trashed, or deleted.', 'cpt-menu-sync' ); ?></span>
                </label>

                <label>
                    <input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[adopt_existing]" value="1" <?php checked( ! empty( $rule['adopt_existing'] ) ); ?>>
                    <span><strong><?php esc_html_e( 'Adopt existing', 'cpt-menu-sync' ); ?></strong> <?php esc_html_e( 'Use matching manually-added post items instead of creating duplicates.', 'cpt-menu-sync' ); ?></span>
                </label>
            </div>
        </section>
        <?php
    }

    /**
     * Render current GitHub update diagnostics and controls.
     */
    private function render_update_content( $updates ) {
        $updates = is_array( $updates ) ? $updates : array();

        if ( ! empty( $updates ) ) :
            ?>
            <dl class="cptms-update-status">
                <div>
                    <dt><?php esc_html_e( 'Installed', 'cpt-menu-sync' ); ?></dt>
                    <dd><?php echo esc_html( isset( $updates['installed_version'] ) ? $updates['installed_version'] : CPTMS_VERSION ); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e( 'Latest', 'cpt-menu-sync' ); ?></dt>
                    <dd><?php echo esc_html( ! empty( $updates['latest_version'] ) ? $updates['latest_version'] : '—' ); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e( 'Status', 'cpt-menu-sync' ); ?></dt>
                    <dd>
                        <?php
                        $connection = isset( $updates['connection'] ) ? $updates['connection'] : 'not_checked';
                        if ( ! empty( $updates['update_available'] ) ) {
                            esc_html_e( 'Update available', 'cpt-menu-sync' );
                        } elseif ( 'connected' === $connection ) {
                            esc_html_e( 'Up to date', 'cpt-menu-sync' );
                        } elseif ( 'not_configured' === $connection ) {
                            esc_html_e( 'Not configured', 'cpt-menu-sync' );
                        } elseif ( 'error' === $connection ) {
                            esc_html_e( 'Connection error', 'cpt-menu-sync' );
                        } else {
                            esc_html_e( 'Not checked', 'cpt-menu-sync' );
                        }
                        ?>
                    </dd>
                </div>
                <div>
                    <dt><?php esc_html_e( 'Last check', 'cpt-menu-sync' ); ?></dt>
                    <dd>
                        <?php
                        if ( ! empty( $updates['last_checked'] ) ) {
                            echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $updates['last_checked'] ) );
                        } else {
                            echo '—';
                        }
                        ?>
                    </dd>
                </div>
            </dl>

            <?php if ( ! empty( $updates['message'] ) ) : ?>
                <p class="description"><?php echo esc_html( $updates['message'] ); ?></p>
            <?php endif; ?>
            <?php
        endif;

        if ( current_user_can( 'update_plugins' ) ) :
            ?>
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="cptms-update-form">
                <input type="hidden" name="action" value="cptms_check_updates">
                <?php wp_nonce_field( 'cptms_check_updates' ); ?>
                <button type="submit" class="button button-secondary">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <?php esc_html_e( 'Check for Updates', 'cpt-menu-sync' ); ?>
                </button>
            </form>
            <?php
        endif;

        if ( class_exists( 'CPTMS_Updater' ) ) :
            ?>
            <p class="cptms-repo-link"><a href="<?php echo esc_url( CPTMS_Updater::releases_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View GitHub Releases', 'cpt-menu-sync' ); ?></a></p>
            <?php
        endif;
    }

    /**
     * Capture the update panel body for an AJAX response.
     */
    private function get_update_content_html( $updates ) {
        ob_start();
        $this->render_update_content( $updates );
        return (string) ob_get_clean();
    }

    /**
     * Get manageable post type objects.
     */
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

    /**
     * Provide menu items to JavaScript for dependent parent dropdowns.
     */
    private function get_menu_item_data() {
        $data = array();

        foreach ( wp_get_nav_menus() as $menu ) {
            $items = wp_get_nav_menu_items( $menu->term_id );
            $items = is_array( $items ) ? $items : array();
            $data[ (string) $menu->term_id ] = $this->flatten_menu_items( $items );
        }

        return $data;
    }

    /**
     * Convert menu items into a label/value list with visual nesting.
     */
    private function flatten_menu_items( array $items ) {
        $children = array();

        foreach ( $items as $item ) {
            $parent = (int) $item->menu_item_parent;
            if ( ! isset( $children[ $parent ] ) ) {
                $children[ $parent ] = array();
            }
            $children[ $parent ][] = $item;
        }

        $flat = array();
        $walk = function( $parent_id, $depth ) use ( &$walk, &$children, &$flat ) {
            if ( empty( $children[ $parent_id ] ) ) {
                return;
            }

            foreach ( $children[ $parent_id ] as $item ) {
                $flat[] = array(
                    'id'    => (int) $item->ID,
                    'label' => str_repeat( '— ', $depth ) . wp_strip_all_tags( $item->title ),
                );
                $walk( (int) $item->ID, $depth + 1 );
            }
        };

        $walk( 0, 0 );

        return $flat;
    }

    /**
     * Capability helpers for non-AJAX fallback handlers.
     */
    private function require_menu_capability() {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage navigation menus.', 'cpt-menu-sync' ) );
        }
    }

    private function require_update_capability() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( esc_html__( 'You do not have permission to update plugins.', 'cpt-menu-sync' ) );
        }
    }

    /**
     * Return to the Tools page after a non-JavaScript action.
     */
    private function redirect_to_page() {
        wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) );
        exit;
    }

    /**
     * Store a one-use admin notice for the current user.
     */
    private function store_notice( array $notice ) {
        set_transient( 'cptms_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
    }

    /**
     * Consume the current user's notice.
     */
    private function consume_notice() {
        $key    = 'cptms_notice_' . get_current_user_id();
        $notice = get_transient( $key );
        delete_transient( $key );
        return is_array( $notice ) ? $notice : null;
    }

    /**
     * Capture a notice for an AJAX response.
     */
    private function get_notice_html( $notice ) {
        ob_start();
        $this->render_notice( $notice );
        return (string) ob_get_clean();
    }

    /**
     * Render save/sync status and optional per-rule report.
     */
    private function render_notice( $notice ) {
        if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
            return;
        }

        $class = 'error' === ( isset( $notice['type'] ) ? $notice['type'] : '' ) ? 'notice-error' : 'notice-success';
        ?>
        <div class="notice <?php echo esc_attr( $class ); ?> is-dismissible cptms-notice">
            <p><strong><?php echo esc_html( $notice['message'] ); ?></strong></p>

            <?php if ( ! empty( $notice['report'] ) && is_array( $notice['report'] ) ) : ?>
                <div class="cptms-report">
                    <?php foreach ( $notice['report'] as $row ) : ?>
                        <?php
                        $post_type = get_post_type_object( $row['post_type'] );
                        $menu      = wp_get_nav_menu_object( $row['menu_id'] );
                        $label     = $post_type ? $post_type->labels->name : $row['post_type'];
                        ?>
                        <div>
                            <strong><?php echo esc_html( $label ); ?></strong>
                            <?php if ( $menu ) : ?>
                                <?php echo esc_html( ' → ' . $menu->name ); ?>
                            <?php endif; ?>
                            <span>
                                <?php
                                echo esc_html(
                                    sprintf(
                                        'Matched %1$d · Added %2$d · Updated %3$d · Removed %4$d',
                                        (int) $row['matched'],
                                        (int) $row['created'],
                                        (int) $row['updated'],
                                        (int) $row['removed']
                                    )
                                );
                                ?>
                            </span>
                            <?php if ( ! empty( $row['errors'] ) ) : ?>
                                <ul>
                                    <?php foreach ( $row['errors'] as $error ) : ?>
                                        <li><?php echo esc_html( $error ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
