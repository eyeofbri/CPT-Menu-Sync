<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Storage for the original single synchronization rule.
 */
final class CPTMS_Settings {

    const OPTION_KEY = 'cptms_rule';

    public static function defaults() {
        return array(
            'post_type'           => '',
            'menu_id'             => 0,
            'parent_menu_item_id' => 0,
        );
    }

    public static function get_rule() {
        $rule = get_option( self::OPTION_KEY, array() );
        $rule = is_array( $rule ) ? $rule : array();

        return wp_parse_args( $rule, self::defaults() );
    }

    public static function sanitize_rule( $raw ) {
        $raw = is_array( $raw ) ? $raw : array();

        $post_type = isset( $raw['post_type'] ) ? sanitize_key( $raw['post_type'] ) : '';
        $menu_id   = isset( $raw['menu_id'] ) ? absint( $raw['menu_id'] ) : 0;
        $parent_id = isset( $raw['parent_menu_item_id'] ) ? absint( $raw['parent_menu_item_id'] ) : 0;

        $valid_post_types = get_post_types( array( 'show_ui' => true ), 'names' );
        if ( ! in_array( $post_type, $valid_post_types, true ) ) {
            $post_type = '';
        }

        if ( $menu_id && ! wp_get_nav_menu_object( $menu_id ) ) {
            $menu_id = 0;
        }

        if ( $parent_id && ( ! $menu_id || ! self::menu_contains_item( $menu_id, $parent_id ) ) ) {
            $parent_id = 0;
        }

        return array(
            'post_type'           => $post_type,
            'menu_id'             => $menu_id,
            'parent_menu_item_id' => $parent_id,
        );
    }

    public static function save_rule( array $rule ) {
        return update_option( self::OPTION_KEY, $rule, false );
    }

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
}
