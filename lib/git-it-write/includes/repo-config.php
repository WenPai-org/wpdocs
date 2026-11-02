<?php

if( ! defined( 'ABSPATH' ) ) exit;

use Symfony\Component\Yaml\Yaml;

class GIW_Repo_Config{

    private static $defaults = array(
        'folder'           => '',
        'post_type'        => 'post',
        'category'         => '',
        'post_author'      => 1,
        'content_template' => '%%content%%',
        'branch'           => 'master',
    );

    /**
     * Load .giw.yml from a repository root and merge with global defaults.
     *
     * @param string $repo_path  Absolute path to the repo directory.
     * @return array  Merged configuration.
     */
    public static function load( $repo_path ){

        $global_settings = Git_It_Write::general_settings();

        $defaults = self::$defaults;

        // Override defaults with global settings where available.
        if( !empty( $global_settings['default_post_type'] ) ){
            $defaults['post_type'] = $global_settings['default_post_type'];
        }
        if( !empty( $global_settings['default_post_author'] ) ){
            $defaults['post_author'] = (int) $global_settings['default_post_author'];
        }

        $config_file = rtrim( $repo_path, '/' ) . '/.giw.yml';

        if( !is_readable( $config_file ) ){
            return $defaults;
        }

        $yaml_content = file_get_contents( $config_file );

        if( empty( $yaml_content ) ){
            return $defaults;
        }

        $parsed = Yaml::parse( $yaml_content );

        if( !is_array( $parsed ) ){
            return $defaults;
        }

        return wp_parse_args( $parsed, $defaults );

    }

}

?>
