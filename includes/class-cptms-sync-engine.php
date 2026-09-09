<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core synchronization engine.
 *
 * This class intentionally contains no admin-page rendering logic.
 */
final class CPTMS_Sync_Engine {

    const META_RULE_ID = '_cptms_rule_id';

    /** @var array<string,bool> */
    private $queue = array();

    /** @var bool */
    private $syncing = false;

    /**
     * Register event-driven synchronization hooks.
     */
    public function register_hooks() {
        add_action( 'save_post', array( $this, 'queue_for_post_save' ), 20, 3 );
        add_action( 'transition_post_status', array( $this, 'queue_for_status_change' ), 20, 3 );
        add_action( 'wp_update_nav_menu', array( $this, 'queue_for_menu_update' ), 20, 2 );
        add_action( 'shutdown', array( $this, 'run_queue' ), 20 );
    }

    /**
     * Queue enabled rules that match a saved post type.
     */
    public function queue_for_post_save( $post_id, $post, $update ) {
        if ( $this->syncing || ! $post instanceof WP_Post ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $this->queue_rules_for_post_type( $post->post_type );
    }

    /**
     * Queue enabled rules when status changes.
     */
    public function queue_for_status_change( $new_status, $old_status, $post ) {
        if ( $this->syncing || ! $post instanceof WP_Post || $new_status === $old_status ) {
            return;
        }

        $this->queue_rules_for_post_type( $post->post_type );
    }

    /**
     * Queue rules that target a menu after the menu itself is edited.
     */
    public function queue_for_menu_update( $menu_id, $menu_data = array() ) {
        if ( $this->syncing ) {
            return;
        }

        foreach ( CPTMS_Settings::get_rules() as $rule ) {
            if ( ! empty( $rule['enabled'] ) && (int) $rule['menu_id'] === (int) $menu_id ) {
                $this->queue[ $rule['id'] ] = true;
            }
        }
    }

    /**
     * Synchronize queued rules once at the end of the current request.
     */
    public function run_queue() {
        if ( empty( $this->queue ) || $this->syncing ) {
            return;
        }

        $queued = array_keys( $this->queue );
        $this->queue = array();

        foreach ( $queued as $rule_id ) {
            $rule = CPTMS_Settings::get_rule( $rule_id );
            if ( $rule && ! empty( $rule['enabled'] ) ) {
                $this->sync_rule( $rule );
            }
        }
    }

    /**
     * Release menu items belonging to rules that were removed or retargeted.
     *
     * Released items are left in the navigation menu as normal manual items;
     * only the plugin ownership metadata is removed. This avoids destructive
     * menu changes when an administrator edits the plugin configuration.
     *
     * @param array $old_rules Previously saved rules.
     * @param array $new_rules Newly saved rules.
     */
    public function release_changed_rules( array $old_rules, array $new_rules ) {
        $new_by_id = array();

        foreach ( $new_rules as $rule ) {
            if ( ! empty( $rule['id'] ) ) {
                $new_by_id[ $rule['id'] ] = $rule;
            }
        }

        foreach ( $old_rules as $old_rule ) {
            if ( empty( $old_rule['id'] ) ) {
                continue;
            }

            $new_rule = isset( $new_by_id[ $old_rule['id'] ] ) ? $new_by_id[ $old_rule['id'] ] : null;
            $removed  = ! $new_rule;
            $retargeted = $new_rule && (
                $old_rule['post_type'] !== $new_rule['post_type'] ||
                (int) $old_rule['menu_id'] !== (int) $new_rule['menu_id'] ||
                (int) $old_rule['parent_menu_item_id'] !== (int) $new_rule['parent_menu_item_id']
            );

            if ( $removed || $retargeted ) {
                $this->release_rule_items( $old_rule );
            }
        }
    }

    /**
     * Remove this plugin's ownership metadata from a rule's current menu items.
     */
    private function release_rule_items( array $rule ) {
        if ( empty( $rule['id'] ) || empty( $rule['menu_id'] ) ) {
            return;
        }

        $items = wp_get_nav_menu_items( (int) $rule['menu_id'] );
        if ( ! is_array( $items ) ) {
            return;
        }

        foreach ( $items as $item ) {
            if ( get_post_meta( $item->ID, self::META_RULE_ID, true ) === $rule['id'] ) {
                delete_post_meta( $item->ID, self::META_RULE_ID );
            }
        }
    }

    /**
     * Synchronize all enabled rules.
     *
     * @return array Aggregate report.
     */
    public function sync_all() {
        $report = array();

        foreach ( CPTMS_Settings::get_rules() as $rule ) {
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }

            $report[] = $this->sync_rule( $rule );
        }

        return $report;
    }

    /**
     * Synchronize a single rule.
     *
     * @param array $rule Rule data.
     * @return array Report data.
     */
    public function sync_rule( array $rule ) {
        $result = array(
            'rule_id'   => isset( $rule['id'] ) ? $rule['id'] : '',
            'post_type' => isset( $rule['post_type'] ) ? $rule['post_type'] : '',
            'menu_id'   => isset( $rule['menu_id'] ) ? (int) $rule['menu_id'] : 0,
            'matched'   => 0,
            'created'   => 0,
            'updated'   => 0,
            'removed'   => 0,
            'errors'    => array(),
        );

        if ( ! $this->is_rule_usable( $rule ) ) {
            $result['errors'][] = __( 'The rule is incomplete or references a missing post type, menu, or parent item.', 'cpt-menu-sync' );
            return $result;
        }

        $this->syncing = true;

        try {
            $posts = get_posts( $this->build_query_args( $rule ) );
            $result['matched'] = count( $posts );

            $desired_post_ids = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );
            $menu_items       = wp_get_nav_menu_items( $rule['menu_id'] );
            $menu_items       = is_array( $menu_items ) ? $menu_items : array();

            $existing_by_post = $this->find_existing_items( $menu_items, $rule );
            $managed_item_ids = array();

            foreach ( $posts as $post ) {
                $post_id       = (int) $post->ID;
                $existing_item = isset( $existing_by_post[ $post_id ] ) ? $existing_by_post[ $post_id ] : null;
                $item_data     = $this->build_menu_item_data( $post, $rule, $existing_item );

                $menu_item_id = wp_update_nav_menu_item(
                    (int) $rule['menu_id'],
                    $existing_item ? (int) $existing_item->ID : 0,
                    $item_data
                );

                if ( is_wp_error( $menu_item_id ) ) {
                    $result['errors'][] = sprintf(
                        /* translators: 1: post title, 2: error message */
                        __( '%1$s: %2$s', 'cpt-menu-sync' ),
                        get_the_title( $post ),
                        $menu_item_id->get_error_message()
                    );
                    continue;
                }

                update_post_meta( $menu_item_id, self::META_RULE_ID, $rule['id'] );
                $managed_item_ids[ $post_id ] = (int) $menu_item_id;

                if ( $existing_item ) {
                    $result['updated']++;
                } else {
                    $result['created']++;
                }
            }

            if ( ! empty( $rule['remove_missing'] ) ) {
                foreach ( $menu_items as $item ) {
                    if ( get_post_meta( $item->ID, self::META_RULE_ID, true ) !== $rule['id'] ) {
                        continue;
                    }

                    if ( ! in_array( (int) $item->object_id, $desired_post_ids, true ) ) {
                        wp_delete_post( $item->ID, true );
                        $result['removed']++;
                    }
                }
            }

            // Refresh after creates/deletes, then mirror the selected post ordering
            // in the actual WordPress menu item order.
            $this->apply_managed_item_order( $rule, $desired_post_ids );

        } finally {
            $this->syncing = false;
        }

        return $result;
    }

    /**
     * Queue rules for one post type.
     */
    private function queue_rules_for_post_type( $post_type ) {
        foreach ( CPTMS_Settings::get_rules() as $rule ) {
            if ( ! empty( $rule['enabled'] ) && $rule['post_type'] === $post_type ) {
                $this->queue[ $rule['id'] ] = true;
            }
        }
    }

    /**
     * Validate that referenced objects still exist.
     */
    private function is_rule_usable( array $rule ) {
        if (
            empty( $rule['id'] ) ||
            empty( $rule['post_type'] ) ||
            empty( $rule['menu_id'] ) ||
            empty( $rule['parent_menu_item_id'] ) ||
            ! post_type_exists( $rule['post_type'] )
        ) {
            return false;
        }

        if ( ! wp_get_nav_menu_object( (int) $rule['menu_id'] ) ) {
            return false;
        }

        $items = wp_get_nav_menu_items( (int) $rule['menu_id'] );
        if ( ! is_array( $items ) ) {
            return false;
        }

        foreach ( $items as $item ) {
            if ( (int) $item->ID === (int) $rule['parent_menu_item_id'] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build post query arguments for a sort mode.
     *
     * menu_order reads WordPress's native menu_order field, which is also
     * what drag-and-drop ordering plugins such as Post Types Order use.
     */
    private function build_query_args( array $rule ) {
        $args = array(
            'post_type'              => $rule['post_type'],
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        switch ( $rule['sort_mode'] ) {
            case 'title_asc':
                $args['orderby'] = 'title';
                $args['order']   = 'ASC';
                break;

            case 'title_desc':
                $args['orderby'] = 'title';
                $args['order']   = 'DESC';
                break;

            case 'date_asc':
                $args['orderby'] = 'date';
                $args['order']   = 'ASC';
                break;

            case 'date_desc':
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
                break;

            case 'menu_order':
            default:
                $args['orderby'] = array(
                    'menu_order' => 'ASC',
                    'title'      => 'ASC',
                );
                $args['order'] = 'ASC';
                break;
        }

        return $args;
    }

    /**
     * Find existing matching post-type menu items.
     */
    private function find_existing_items( array $menu_items, array $rule ) {
        $existing = array();

        foreach ( $menu_items as $item ) {
            if ( 'post_type' !== $item->type || $rule['post_type'] !== $item->object ) {
                continue;
            }

            $managed_rule = get_post_meta( $item->ID, self::META_RULE_ID, true );

            if ( $managed_rule === $rule['id'] ) {
                $existing[ (int) $item->object_id ] = $item;
                continue;
            }

            if ( ! empty( $rule['adopt_existing'] ) && empty( $managed_rule ) ) {
                $existing[ (int) $item->object_id ] = $item;
            }
        }

        return $existing;
    }

    /**
     * Build wp_update_nav_menu_item() data.
     */
    private function build_menu_item_data( WP_Post $post, array $rule, $existing_item = null ) {
        $title = get_the_title( $post );

        if ( $existing_item && empty( $rule['sync_title'] ) ) {
            $title = $existing_item->title;
        }

        $data = array(
            'menu-item-object-id' => (int) $post->ID,
            'menu-item-object'    => $rule['post_type'],
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => (int) $rule['parent_menu_item_id'],
            'menu-item-title'     => wp_slash( $title ),
        );

        if ( $existing_item ) {
            $data['menu-item-description'] = wp_slash( (string) $existing_item->description );
            $data['menu-item-attr-title']  = wp_slash( (string) $existing_item->attr_title );
            $data['menu-item-target']      = (string) $existing_item->target;
            $data['menu-item-xfn']         = (string) $existing_item->xfn;
            $data['menu-item-classes']     = implode( ' ', array_filter( (array) $existing_item->classes ) );
        }

        return $data;
    }

    /**
     * Reorder only the slots occupied by items managed by this rule.
     *
     * This preserves the relative order of unrelated/manual menu items while
     * ensuring the managed CPT items appear in the same order as the query.
     */
    private function apply_managed_item_order( array $rule, array $desired_post_ids ) {
        $items = wp_get_nav_menu_items(
            (int) $rule['menu_id'],
            array( 'orderby' => 'menu_order', 'order' => 'ASC' )
        );

        if ( ! is_array( $items ) || empty( $items ) ) {
            return;
        }

        $managed_by_post = array();
        $managed_slots   = array();

        foreach ( $items as $index => $item ) {
            if ( get_post_meta( $item->ID, self::META_RULE_ID, true ) !== $rule['id'] ) {
                continue;
            }

            if ( (int) $item->menu_item_parent !== (int) $rule['parent_menu_item_id'] ) {
                continue;
            }

            $managed_by_post[ (int) $item->object_id ] = (int) $item->ID;
            $managed_slots[] = $index;
        }

        if ( empty( $managed_slots ) ) {
            return;
        }

        $ordered_ids = array();
        foreach ( $desired_post_ids as $post_id ) {
            if ( isset( $managed_by_post[ $post_id ] ) ) {
                $ordered_ids[] = $managed_by_post[ $post_id ];
            }
        }

        if ( count( $ordered_ids ) !== count( $managed_slots ) ) {
            return;
        }

        $final_ids = array_map(
            static function( $item ) {
                return (int) $item->ID;
            },
            $items
        );

        foreach ( $managed_slots as $slot_index => $item_slot ) {
            $final_ids[ $item_slot ] = $ordered_ids[ $slot_index ];
        }

        foreach ( $final_ids as $position => $item_id ) {
            wp_update_post(
                array(
                    'ID'         => $item_id,
                    'menu_order' => $position + 1,
                )
            );
        }
    }
}
