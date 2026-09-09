<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings storage and sanitization.
 */
final class CPTMS_Settings {

    const OPTION_KEY = 'cptms_rules';


    /**
     * Migrate the original v0.0.1 single-rule option into the rule collection.
     *
     * This runs only when no v0.0.2 rules have been stored yet.
     */
    public static function maybe_migrate_legacy_rule() {
        $existing = get_option( self::OPTION_KEY, false );

        if ( is_array( $existing ) && ! empty( $existing ) ) {
            return;
        }

        $legacy = get_option( 'cptms_rule', false );

        if ( ! is_array( $legacy ) ) {
            return;
        }

        $post_type = isset( $legacy['post_type'] ) ? sanitize_key( $legacy['post_type'] ) : '';
        $menu_id   = isset( $legacy['menu_id'] ) ? absint( $legacy['menu_id'] ) : 0;
        $parent_id = isset( $legacy['parent_menu_item_id'] ) ? absint( $legacy['parent_menu_item_id'] ) : 0;

        if (
            ! $post_type ||
            ! post_type_exists( $post_type ) ||
            ! $menu_id ||
            ! $parent_id ||
            ! self::menu_contains_item( $menu_id, $parent_id )
        ) {
            return;
        }

        update_option(
            self::OPTION_KEY,
            array(
                array(
                    'id'                  => wp_generate_uuid4(),
                    'enabled'             => true,
                    'post_type'           => $post_type,
                    'menu_id'             => $menu_id,
                    'parent_menu_item_id' => $parent_id,
                    'sort_mode'           => 'title_asc',
                    'sync_title'          => true,
                    'remove_missing'      => true,
                    'adopt_existing'      => true,
                ),
            ),
            false
        );
    }

    /**
     * Return all configured rules.
     *
     * @return array
     */
    public static function get_rules() {
        $rules = get_option( self::OPTION_KEY, array() );
        return is_array( $rules ) ? $rules : array();
    }

    /**
     * Return one configured rule.
     *
     * @param string $rule_id Rule UUID.
     * @return array|null
     */
    public static function get_rule( $rule_id ) {
        foreach ( self::get_rules() as $rule ) {
            if ( isset( $rule['id'] ) && $rule['id'] === $rule_id ) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Sanitize rules posted by the admin screen.
     *
     * @param mixed $raw_rules Raw submitted rules.
     * @return array
     */
    public static function sanitize_rules( $raw_rules ) {
        if ( ! is_array( $raw_rules ) ) {
            return array();
        }

        $valid_post_types = get_post_types( array( 'show_ui' => true ), 'names' );
        $valid_menus      = wp_get_nav_menus();
        $valid_menu_ids   = array_map( 'intval', wp_list_pluck( $valid_menus, 'term_id' ) );
        $sort_modes       = array( 'menu_order', 'title_asc', 'title_desc', 'date_desc', 'date_asc' );
        $clean            = array();

        foreach ( $raw_rules as $raw_rule ) {
            if ( ! is_array( $raw_rule ) ) {
                continue;
            }

            $post_type     = isset( $raw_rule['post_type'] ) ? sanitize_key( $raw_rule['post_type'] ) : '';
            $menu_id       = isset( $raw_rule['menu_id'] ) ? absint( $raw_rule['menu_id'] ) : 0;
            $parent_id     = isset( $raw_rule['parent_menu_item_id'] ) ? absint( $raw_rule['parent_menu_item_id'] ) : 0;
            $sort_mode     = isset( $raw_rule['sort_mode'] ) ? sanitize_key( $raw_rule['sort_mode'] ) : 'menu_order';
            $existing_id   = isset( $raw_rule['id'] ) ? sanitize_text_field( $raw_rule['id'] ) : '';

            if ( ! $post_type || ! in_array( $post_type, $valid_post_types, true ) ) {
                continue;
            }

            if ( ! $menu_id || ! in_array( $menu_id, $valid_menu_ids, true ) ) {
                continue;
            }

            if ( ! $parent_id || ! self::menu_contains_item( $menu_id, $parent_id ) ) {
                continue;
            }

            if ( ! in_array( $sort_mode, $sort_modes, true ) ) {
                $sort_mode = 'menu_order';
            }

            $clean[] = array(
                'id'                  => self::is_uuid( $existing_id ) ? $existing_id : wp_generate_uuid4(),
                'enabled'             => ! empty( $raw_rule['enabled'] ),
                'post_type'           => $post_type,
                'menu_id'             => $menu_id,
                'parent_menu_item_id' => $parent_id,
                'sort_mode'           => $sort_mode,
                'sync_title'          => ! empty( $raw_rule['sync_title'] ),
                'remove_missing'      => ! empty( $raw_rule['remove_missing'] ),
                'adopt_existing'      => ! empty( $raw_rule['adopt_existing'] ),
            );
        }

        return $clean;
    }

    /**
     * Save sanitized rules.
     *
     * @param array $rules Sanitized rules.
     * @return bool
     */
    public static function save_rules( array $rules ) {
        return update_option( self::OPTION_KEY, array_values( $rules ), false );
    }

    /**
     * Verify that a menu item belongs to a menu.
     */
    private static function menu_contains_item( $menu_id, $item_id ) {
        $items = wp_get_nav_menu_items( $menu_id );

        if ( ! is_array( $items ) ) {
            return false;
        }

        foreach ( $items as $item ) {
            if ( (int) $item->ID === (int) $item_id ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Basic UUID v4-ish format validation for stored rule IDs.
     */
    private static function is_uuid( $value ) {
        return is_string( $value ) && 1 === preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
