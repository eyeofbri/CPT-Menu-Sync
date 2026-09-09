<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Initial single-rule synchronization engine.
 */
final class CPTMS_Sync_Engine {

    const META_MANAGED = '_cptms_managed';

    private $queued  = false;
    private $syncing = false;

    public function register_hooks() {
        add_action( 'save_post', array( $this, 'queue_for_post_save' ), 20, 3 );
        add_action( 'transition_post_status', array( $this, 'queue_for_status_change' ), 20, 3 );
        add_action( 'shutdown', array( $this, 'run_queue' ), 20 );
    }

    public function queue_for_post_save( $post_id, $post, $update ) {
        unset( $update );

        if ( $this->syncing || ! $post instanceof WP_Post ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $rule = CPTMS_Settings::get_rule();

        if ( ! empty( $rule['post_type'] ) && $post->post_type === $rule['post_type'] ) {
            $this->queued = true;
        }
    }

    public function queue_for_status_change( $new_status, $old_status, $post ) {
        if ( $this->syncing || $new_status === $old_status || ! $post instanceof WP_Post ) {
            return;
        }

        $rule = CPTMS_Settings::get_rule();

        if ( ! empty( $rule['post_type'] ) && $post->post_type === $rule['post_type'] ) {
            $this->queued = true;
        }
    }

    public function run_queue() {
        if ( ! $this->queued || $this->syncing ) {
            return;
        }

        $this->queued = false;
        $this->sync();
    }

    /**
     * Synchronize the configured post type alphabetically beneath one parent.
     */
    public function sync() {
        $rule = CPTMS_Settings::get_rule();

        $result = array(
            'matched' => 0,
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
            'errors'  => array(),
        );

        if ( ! $this->is_rule_usable( $rule ) ) {
            $result['errors'][] = __( 'Select a valid post type, menu, and parent menu item first.', 'cpt-menu-sync' );
            return $result;
        }

        $this->syncing = true;

        try {
            $posts = get_posts(
                array(
                    'post_type'      => $rule['post_type'],
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                )
            );

            $result['matched'] = count( $posts );
            $desired_post_ids = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );

            $menu_items = wp_get_nav_menu_items( $rule['menu_id'] );
            $menu_items = is_array( $menu_items ) ? $menu_items : array();

            $existing_by_post = array();

            foreach ( $menu_items as $item ) {
                if (
                    'post_type' === $item->type &&
                    $rule['post_type'] === $item->object &&
                    (int) $item->menu_item_parent === (int) $rule['parent_menu_item_id']
                ) {
                    $existing_by_post[ (int) $item->object_id ] = $item;
                }
            }

            foreach ( $posts as $post ) {
                $existing = isset( $existing_by_post[ $post->ID ] )
                    ? $existing_by_post[ $post->ID ]
                    : null;

                $data = array(
                    'menu-item-object-id' => (int) $post->ID,
                    'menu-item-object'    => $rule['post_type'],
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                    'menu-item-parent-id' => (int) $rule['parent_menu_item_id'],
                    'menu-item-title'     => wp_slash( get_the_title( $post ) ),
                );

                $menu_item_id = wp_update_nav_menu_item(
                    (int) $rule['menu_id'],
                    $existing ? (int) $existing->ID : 0,
                    $data
                );

                if ( is_wp_error( $menu_item_id ) ) {
                    $result['errors'][] = $menu_item_id->get_error_message();
                    continue;
                }

                update_post_meta( $menu_item_id, self::META_MANAGED, 1 );

                if ( $existing ) {
                    $result['updated']++;
                } else {
                    $result['created']++;
                }
            }

            foreach ( $menu_items as $item ) {
                if (
                    'post_type' !== $item->type ||
                    $rule['post_type'] !== $item->object ||
                    (int) $item->menu_item_parent !== (int) $rule['parent_menu_item_id'] ||
                    ! get_post_meta( $item->ID, self::META_MANAGED, true )
                ) {
                    continue;
                }

                if ( ! in_array( (int) $item->object_id, $desired_post_ids, true ) ) {
                    wp_delete_post( $item->ID, true );
                    $result['removed']++;
                }
            }

            $this->apply_alphabetical_order( $rule, $desired_post_ids );
        } finally {
            $this->syncing = false;
        }

        return $result;
    }

    private function is_rule_usable( array $rule ) {
        if (
            empty( $rule['post_type'] ) ||
            empty( $rule['menu_id'] ) ||
            empty( $rule['parent_menu_item_id'] ) ||
            ! post_type_exists( $rule['post_type'] ) ||
            ! wp_get_nav_menu_object( (int) $rule['menu_id'] )
        ) {
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
     * Reuse the managed items' existing menu_order slots while rearranging
     * those managed items into the alphabetical post order.
     */
    private function apply_alphabetical_order( array $rule, array $desired_post_ids ) {
        $items = wp_get_nav_menu_items( (int) $rule['menu_id'] );
        if ( ! is_array( $items ) ) {
            return;
        }

        $managed_by_post = array();
        $positions       = array();

        foreach ( $items as $item ) {
            if (
                (int) $item->menu_item_parent === (int) $rule['parent_menu_item_id'] &&
                'post_type' === $item->type &&
                $rule['post_type'] === $item->object &&
                get_post_meta( $item->ID, self::META_MANAGED, true )
            ) {
                $managed_by_post[ (int) $item->object_id ] = $item;
                $positions[] = (int) $item->menu_order;
            }
        }

        sort( $positions, SORT_NUMERIC );

        $index = 0;
        foreach ( $desired_post_ids as $post_id ) {
            if ( ! isset( $managed_by_post[ $post_id ], $positions[ $index ] ) ) {
                continue;
            }

            wp_update_post(
                array(
                    'ID'         => (int) $managed_by_post[ $post_id ]->ID,
                    'menu_order' => (int) $positions[ $index ],
                )
            );

            $index++;
        }
    }
}
