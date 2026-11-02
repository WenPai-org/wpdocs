<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Discovery{

    /**
     * Scan base_directory for subdirectories that contain a .git/ folder.
     *
     * @param string $base_dir  Absolute path to the base directory.
     * @return array  Array of discovered repos: [ ['name'=>..., 'path'=>..., 'config'=>[...]], ... ]
     */
    public static function discover( $base_dir ){

        $base_dir = rtrim( $base_dir, '/' );
        $repos    = array();

        if( !is_dir( $base_dir ) ){
            GIW_Utils::log( 'Discovery: base_directory does not exist — ' . $base_dir );
            return $repos;
        }

        $entries = scandir( $base_dir );

        if( $entries === false ){
            GIW_Utils::log( 'Discovery: cannot read base_directory — ' . $base_dir );
            return $repos;
        }

        foreach( $entries as $entry ){

            if( $entry === '.' || $entry === '..' ){
                continue;
            }

            $entry_path = $base_dir . '/' . $entry;

            if( !is_dir( $entry_path ) ){
                continue;
            }

            // Must contain .git/ to be a repo.
            if( !is_dir( $entry_path . '/.git' ) ){
                continue;
            }

            $config = GIW_Repo_Config::load( $entry_path );

            $repos[] = array(
                'name'   => $entry,
                'path'   => $entry_path,
                'config' => $config,
            );

        }

        GIW_Utils::log( 'Discovery: found ' . count( $repos ) . ' repositories in ' . $base_dir );

        return $repos;

    }

    /**
     * Find a single repo by directory name within base_dir.
     *
     * @param string $base_dir  Absolute path.
     * @param string $repo_name Directory name.
     * @return array|false  Repo array or false.
     */
    public static function find( $base_dir, $repo_name ){

        $base_dir  = rtrim( $base_dir, '/' );
        $repo_path = $base_dir . '/' . $repo_name;

        if( !is_dir( $repo_path ) || !is_dir( $repo_path . '/.git' ) ){
            return false;
        }

        return array(
            'name'   => $repo_name,
            'path'   => $repo_path,
            'config' => GIW_Repo_Config::load( $repo_path ),
        );

    }

}

?>
