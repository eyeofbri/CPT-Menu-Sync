<?php
/**
 * GitHub release updater for CPT Menu Sync.
 *
 * Author: Brian McLendon
 * GitHub: https://github.com/eyeofbri
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CPTMS_Updater {

    const CACHE_KEY     = 'cptms_github_latest_release';
    const STATUS_OPTION = 'cptms_github_update_status';
    const CACHE_TTL     = 3600; // 1 hour.

    /** @var string */
    private static $plugin_basename = '';

    /**
     * Register WordPress updater hooks.
     */
    public static function init() {
        self::$plugin_basename = plugin_basename( CPTMS_FILE );

        if ( ! self::has_repository() ) {
            if ( is_admin() ) {
                add_action( 'admin_notices', array( __CLASS__, 'repository_not_configured_notice' ) );
            }
            return;
        }

        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_github_source_directory' ), 10, 4 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 10, 2 );
        add_action( 'delete_site_transient_update_plugins', array( __CLASS__, 'clear_release_cache' ) );
    }

    /**
     * Warn administrators when a repository was not configured in the build.
     */
    public static function repository_not_configured_notice() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__( 'CPT Menu Sync GitHub updates are not configured. Set CPTMS_GITHUB_REPOSITORY in cpt-menu-sync.php before publishing this build.', 'cpt-menu-sync' );
        echo '</p></div>';
    }

    /**
     * Add a newer normal GitHub Release to WordPress's plugin update data.
     */
    public static function check_for_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $release = self::get_latest_release();

        if ( ! $release ) {
            return $transient;
        }

        $remote_version = self::normalize_version( isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '' );
        $package_url    = self::find_release_package( $release );

        if ( '' === $remote_version || ! version_compare( CPTMS_VERSION, $remote_version, '<' ) || '' === $package_url ) {
            return $transient;
        }

        $update = (object) array(
            'id'           => self::repository_url(),
            'slug'         => 'cpt-menu-sync',
            'plugin'       => self::$plugin_basename,
            'new_version'  => $remote_version,
            'url'          => self::repository_url(),
            'package'      => $package_url,
            'requires'     => '6.0',
            'requires_php' => '7.4',
            'tested'       => '',
        );

        $transient->response[ self::$plugin_basename ] = $update;

        return $transient;
    }

    /**
     * Supply the WordPress plugin-information modal with GitHub release data.
     */
    public static function plugin_information( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || 'cpt-menu-sync' !== $args->slug ) {
            return $result;
        }

        $release = self::get_latest_release();

        if ( ! $release ) {
            return $result;
        }

        $remote_version = self::normalize_version( isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '' );
        $release_notes  = isset( $release['body'] ) && is_string( $release['body'] ) ? $release['body'] : '';

        return (object) array(
            'name'          => 'CPT Menu Sync',
            'slug'          => 'cpt-menu-sync',
            'version'       => $remote_version ? $remote_version : CPTMS_VERSION,
            'author'        => '<a href="https://github.com/eyeofbri" target="_blank" rel="noopener noreferrer">Brian McLendon</a>',
            'homepage'      => self::repository_url(),
            'requires'      => '6.0',
            'requires_php'  => '7.4',
            'download_link' => self::find_release_package( $release ),
            'sections'      => array(
                'description' => 'Automatically synchronize post-type content beneath selected parent items in classic WordPress navigation menus.',
                'changelog'   => '' !== trim( $release_notes )
                    ? wpautop( esc_html( $release_notes ) )
                    : '<p>See changelog.md in the CPT Menu Sync repository for release history.</p>',
            ),
        );
    }

    /**
     * Force a fresh GitHub request and rebuild WordPress plugin update data.
     *
     * @return array
     */
    public static function force_check() {
        // WordPress's deletion hook also clears our release cache.
        delete_site_transient( 'update_plugins' );
        self::clear_release_cache();

        $release = self::get_latest_release( true );

        if ( is_array( $release ) && function_exists( 'wp_update_plugins' ) ) {
            wp_update_plugins();
        }

        return self::get_diagnostics( false );
    }

    /**
     * Return updater status for the Tools admin page.
     *
     * @param bool $refresh_if_empty Query GitHub when no check has been stored yet.
     * @return array
     */
    public static function get_diagnostics( $refresh_if_empty = true ) {
        $status = get_site_option( self::STATUS_OPTION, array() );
        $status = is_array( $status ) ? $status : array();

        if ( $refresh_if_empty && empty( $status['last_checked'] ) ) {
            self::get_latest_release();
            $status = get_site_option( self::STATUS_OPTION, array() );
            $status = is_array( $status ) ? $status : array();
        }

        $latest_version = isset( $status['latest_version'] ) ? (string) $status['latest_version'] : '';

        if ( '' === $latest_version ) {
            $cached = get_site_transient( self::CACHE_KEY );
            if ( is_array( $cached ) ) {
                $latest_version = self::normalize_version( isset( $cached['tag_name'] ) ? (string) $cached['tag_name'] : '' );
            }
        }

        $connection = isset( $status['connection'] ) ? sanitize_key( (string) $status['connection'] ) : 'not_checked';

        if ( ! self::has_repository() ) {
            $connection = 'not_configured';
        }

        return array(
            'installed_version' => CPTMS_VERSION,
            'latest_version'    => $latest_version,
            'last_checked'      => isset( $status['last_checked'] ) ? absint( $status['last_checked'] ) : 0,
            'connection'        => $connection,
            'http_code'         => isset( $status['http_code'] ) ? absint( $status['http_code'] ) : 0,
            'message'           => isset( $status['message'] ) ? sanitize_text_field( (string) $status['message'] ) : '',
            'update_available'  => '' !== $latest_version && version_compare( CPTMS_VERSION, $latest_version, '<' ),
            'cache_ttl'         => self::CACHE_TTL,
        );
    }

    /**
     * Public GitHub Releases page.
     */
    public static function releases_url() {
        return trailingslashit( self::repository_url() ) . 'releases';
    }

    /**
     * Public repository URL.
     */
    public static function repository_page_url() {
        return self::repository_url();
    }

    /**
     * Clear the cached GitHub release response.
     */
    public static function clear_release_cache() {
        delete_site_transient( self::CACHE_KEY );
    }

    /**
     * Clear cached release data after this plugin updates.
     */
    public static function clear_cache_after_upgrade( $upgrader, $options ) {
        unset( $upgrader );

        if ( ! is_array( $options ) || 'update' !== ( isset( $options['action'] ) ? $options['action'] : '' ) || 'plugin' !== ( isset( $options['type'] ) ? $options['type'] : '' ) ) {
            return;
        }

        $plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] ) ? $options['plugins'] : array();

        if ( isset( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
            $plugins[] = $options['plugin'];
        }

        if ( in_array( self::$plugin_basename, $plugins, true ) ) {
            self::clear_release_cache();
        }
    }

    /**
     * Request the latest normal GitHub Release.
     *
     * @param bool $force Ignore the one-hour cache.
     * @return array|null
     */
    private static function get_latest_release( $force = false ) {
        if ( ! self::has_repository() ) {
            return null;
        }

        if ( ! $force ) {
            $cached = get_site_transient( self::CACHE_KEY );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $endpoint = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            rawurlencode( self::repository_owner() ),
            rawurlencode( self::repository_name() )
        );

        $response = wp_remote_get(
            $endpoint,
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'CPT-Menu-Sync/' . CPTMS_VERSION,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            self::record_check_status( 'error', 0, '', $response->get_error_message() );
            return null;
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );

        if ( 200 !== $http_code ) {
            $message = 404 === $http_code
                ? __( 'No published GitHub Release was found yet.', 'cpt-menu-sync' )
                : sprintf(
                    /* translators: %d: HTTP status code returned by GitHub. */
                    __( 'GitHub returned HTTP %d.', 'cpt-menu-sync' ),
                    $http_code
                );

            self::record_check_status( 'error', $http_code, '', $message );
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $release ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
            self::record_check_status( 'error', $http_code, '', __( 'GitHub did not return a valid normal release.', 'cpt-menu-sync' ) );
            return null;
        }

        $latest_version = self::normalize_version( isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '' );

        if ( '' === $latest_version ) {
            self::record_check_status( 'error', $http_code, '', __( 'The latest GitHub release has an invalid version tag.', 'cpt-menu-sync' ) );
            return null;
        }

        set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
        self::record_check_status( 'connected', $http_code, $latest_version, __( 'Connected to GitHub Releases.', 'cpt-menu-sync' ) );

        return $release;
    }

    /**
     * Store diagnostics for the admin page.
     */
    private static function record_check_status( $connection, $http_code, $latest_version, $message ) {
        update_site_option(
            self::STATUS_OPTION,
            array(
                'connection'     => sanitize_key( $connection ),
                'http_code'      => max( 0, (int) $http_code ),
                'latest_version' => sanitize_text_field( $latest_version ),
                'last_checked'   => time(),
                'message'        => sanitize_text_field( $message ),
            )
        );
    }

    /**
     * Use GitHub's automatically generated source ZIP for the Release.
     */
    private static function find_release_package( array $release ) {
        if ( empty( $release['zipball_url'] ) || ! is_string( $release['zipball_url'] ) ) {
            return '';
        }

        return esc_url_raw( $release['zipball_url'] );
    }

    /**
     * GitHub source archives extract to an owner/repository/tag-specific folder.
     * Normalize that folder to cpt-menu-sync so WordPress replaces the existing
     * plugin directory instead of installing a second copy beside it.
     *
     * @param string|WP_Error $source        Extracted source directory.
     * @param string          $remote_source Working directory used by the upgrader.
     * @param WP_Upgrader     $upgrader      Current upgrader instance.
     * @param array           $hook_extra    Upgrade context.
     * @return string|WP_Error
     */
    public static function normalize_github_source_directory( $source, $remote_source, $upgrader, $hook_extra ) {
        unset( $upgrader );

        if ( is_wp_error( $source ) || ! is_array( $hook_extra ) || ! self::is_our_upgrade( $hook_extra ) ) {
            return $source;
        }

        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            return $source;
        }

        $source = trailingslashit( (string) $source );

        if ( ! $wp_filesystem->exists( $source . 'cpt-menu-sync.php' ) ) {
            return new WP_Error(
                'cptms_invalid_update_package',
                __( 'The CPT Menu Sync GitHub archive does not contain cpt-menu-sync.php at its repository root.', 'cpt-menu-sync' )
            );
        }

        $normalized_source = trailingslashit( $remote_source ) . 'cpt-menu-sync/';

        if ( untrailingslashit( $source ) === untrailingslashit( $normalized_source ) ) {
            return $source;
        }

        if ( $wp_filesystem->exists( $normalized_source ) ) {
            $wp_filesystem->delete( $normalized_source, true );
        }

        if ( ! $wp_filesystem->move( $source, $normalized_source, true ) ) {
            return new WP_Error(
                'cptms_update_directory_normalization_failed',
                __( 'CPT Menu Sync could not normalize the GitHub update directory.', 'cpt-menu-sync' )
            );
        }

        return $normalized_source;
    }

    /**
     * Determine whether an upgrader operation belongs to CPT Menu Sync.
     */
    private static function is_our_upgrade( array $hook_extra ) {
        if ( isset( $hook_extra['plugin'] ) && self::$plugin_basename === $hook_extra['plugin'] ) {
            return true;
        }

        if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) && in_array( self::$plugin_basename, $hook_extra['plugins'], true ) ) {
            return true;
        }

        return isset( $hook_extra['slug'] ) && 'cpt-menu-sync' === $hook_extra['slug'];
    }

    /**
     * Convert tags such as v0.1.1 to 0.1.1.
     */
    private static function normalize_version( $tag ) {
        $version = ltrim( trim( (string) $tag ), "vV \t\n\r\0\x0B" );

        return preg_match( '/^[0-9]+(?:\.[0-9A-Za-z-]+)+$/', $version ) ? $version : '';
    }

    /**
     * Whether the build has a real owner/repository configured.
     */
    private static function has_repository() {
        return defined( 'CPTMS_GITHUB_REPOSITORY' )
            && is_string( CPTMS_GITHUB_REPOSITORY )
            && '' !== trim( CPTMS_GITHUB_REPOSITORY )
            && ! in_array( CPTMS_GITHUB_REPOSITORY, array( 'OWNER/cpt-menu-sync', 'OWNER/CPT-Menu-Sync' ), true );
    }

    private static function repository_url() {
        return 'https://github.com/' . self::repository_owner() . '/' . self::repository_name() . '/';
    }

    private static function repository_owner() {
        $parts = explode( '/', trim( CPTMS_GITHUB_REPOSITORY, '/' ), 2 );
        return isset( $parts[0] ) ? $parts[0] : '';
    }

    private static function repository_name() {
        $parts = explode( '/', trim( CPTMS_GITHUB_REPOSITORY, '/' ), 2 );
        return isset( $parts[1] ) ? $parts[1] : '';
    }
}
