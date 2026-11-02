<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Sync{

    const CRON_HOOK = 'giw_cron_sync';

    public static function init(){

        add_filter( 'cron_schedules', array( __CLASS__, 'add_interval' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'sync_all' ) );

        // Schedule if not already scheduled.
        if( !wp_next_scheduled( self::CRON_HOOK ) ){
            $settings = Git_It_Write::general_settings();
            $interval = !empty( $settings['sync_interval'] ) ? $settings['sync_interval'] : 'hourly';
            wp_schedule_event( time(), $interval, self::CRON_HOOK );
        }

    }

    public static function add_interval( $schedules ){

        $schedules['every_30_minutes'] = array(
            'interval' => 1800,
            'display'  => 'Every 30 minutes',
        );

        $schedules['every_6_hours'] = array(
            'interval' => 21600,
            'display'  => 'Every 6 hours',
        );

        $schedules['every_12_hours'] = array(
            'interval' => 43200,
            'display'  => 'Every 12 hours',
        );

        return $schedules;

    }

    /**
     * Sync all discovered repositories.
     */
    public static function sync_all( $force = false ){

        $settings = Git_It_Write::general_settings();
        $base_dir = isset( $settings['base_directory'] ) ? $settings['base_directory'] : '';

        if( empty( $base_dir ) ){
            GIW_Utils::log( 'Sync: base_directory not configured, skipping' );
            return array();
        }

        self::maybe_switch_blog( $settings );

        // Load media functions for image uploads.
        if( !function_exists( 'media_handle_sideload' ) ){
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }

        $repos   = GIW_Discovery::discover( $base_dir );
        $results = array();

        GIW_Utils::log( '========== Starting sync of ' . count( $repos ) . ' repositories ==========' );

        foreach( $repos as $repo ){
            $results[ $repo['name'] ] = self::sync_repo( $repo, $force );
        }

        GIW_Utils::log( '========== Sync complete ==========' );

        self::maybe_restore_blog();

        return $results;

    }

    /**
     * Sync a single repository.
     *
     * @param array $repo  Repo array from GIW_Discovery (name, path, config).
     * @param bool  $force Force full sync ignoring SHA.
     * @return array  Publisher result.
     */
    public static function sync_repo( $repo, $force = false ){

        GIW_Utils::log( '---------- Syncing repo: ' . $repo['name'] . ' ----------' );

        if( $force ){
            if( !defined( 'GIW_PUBLISH_FORCE' ) ){
                define( 'GIW_PUBLISH_FORCE', true );
            }
        }

        // Load media functions for image uploads.
        if( !function_exists( 'media_handle_sideload' ) ){
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }

        $local_repo = new GIW_Local_Repository( $repo['path'], $repo['name'] );

        $config = $repo['config'];

        // Build a repo_config array compatible with GIW_Publisher.
        $repo_config = array(
            'post_type'        => !empty( $config['post_type'] ) ? $config['post_type'] : 'post',
            'branch'           => !empty( $config['branch'] ) ? $config['branch'] : 'master',
            'folder'           => isset( $config['folder'] ) ? $config['folder'] : '',
            'post_author'      => !empty( $config['post_author'] ) ? (int) $config['post_author'] : 1,
            'content_template' => !empty( $config['content_template'] ) ? $config['content_template'] : '%%content%%',
        );

        $publisher = new GIW_Publisher( $local_repo, $repo_config );

        // Set default category if specified.
        if( !empty( $config['category'] ) ){
            add_action( 'wp_insert_post', function( $post_id, $post ) use ( $config ){
                if( $post->post_type === $config['post_type'] && empty( wp_get_object_terms( $post_id, 'category' ) ) ){
                    wp_set_object_terms( $post_id, $config['category'], 'category' );
                }
            }, 10, 2 );
        }

        $result = $publisher->publish();

        // Update sync timestamp.
        $timestamps = get_option( 'giw_sync_timestamps', array() );
        $timestamps[ $repo['name'] ] = time();
        update_option( 'giw_sync_timestamps', $timestamps );

        GIW_Utils::log( '---------- Repo sync done: ' . $repo['name'] . ' ----------' );

        return $result;

    }

    /**
     * Switch to target blog in multisite.
     */
    private static function maybe_switch_blog( $settings = array() ){

        if( !is_multisite() ){
            return;
        }

        if( empty( $settings ) ){
            $settings = Git_It_Write::general_settings();
        }

        $blog_id = isset( $settings['target_blog_id'] ) ? (int) $settings['target_blog_id'] : 0;

        if( $blog_id > 0 ){
            GIW_Utils::log( 'Switching to blog ID: ' . $blog_id );
            switch_to_blog( $blog_id );
        }

    }

    private static function maybe_restore_blog(){

        if( !is_multisite() ){
            return;
        }

        restore_current_blog();

    }

    /**
     * Reschedule cron when interval changes.
     */
    public static function reschedule( $interval ){

        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if( $timestamp ){
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }

        wp_schedule_event( time(), $interval, self::CRON_HOOK );

    }

    /**
     * Unschedule cron (plugin deactivation).
     */
    public static function unschedule(){

        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if( $timestamp ){
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }

    }

}

?>
