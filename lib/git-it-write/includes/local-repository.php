<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Local_Repository{

    public $structure = array();

    public $repo_path;

    public $repo_name;

    private $skip_dirs = array( '.git', 'node_modules', '.github', '.vscode', '__pycache__' );

    public function __construct( $repo_path, $repo_name ){

        $this->repo_path = rtrim( $repo_path, '/' );
        $this->repo_name = $repo_name;
        $this->build_repo_structure();

    }

    public function build_repo_structure(){

        GIW_Utils::log( 'Building local repo structure for [' . $this->repo_name . '] at [' . $this->repo_path . ']' );

        if( !is_dir( $this->repo_path ) ){
            GIW_Utils::log( 'Repository path does not exist: ' . $this->repo_path );
            return;
        }

        $iterator = new RecursiveDirectoryIterator(
            $this->repo_path,
            RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
        );

        $filter = new GIW_Dir_Filter( $iterator, $this->skip_dirs );
        $files  = new RecursiveIteratorIterator( $filter, RecursiveIteratorIterator::SELF_FIRST );

        foreach( $files as $file ){

            if( $file->isDir() ){
                continue;
            }

            $absolute_path = $file->getPathname();
            $relative_path = substr( $absolute_path, strlen( $this->repo_path ) + 1 );
            $path_parts    = explode( '/', $relative_path );

            $this->structure = $this->add_to_structure( $this->structure, $path_parts, $absolute_path, $relative_path );
        }

        GIW_Utils::log( 'Local repo structure built successfully' );

    }

    private function add_to_structure( $structure, $path_split, $absolute_path, $relative_path ){

        if( count( $path_split ) == 1 ){

            $full_file_name = $path_split[0];
            $file_info      = pathinfo( $full_file_name );
            $file_slug      = $file_info['filename'];
            $extension      = isset( $file_info['extension'] ) ? $file_info['extension'] : '';

            $content = file_get_contents( $absolute_path );
            $sha     = sha1( "blob " . strlen( $content ) . "\0" . $content );

            $structure[ $file_slug ] = array(
                'type'       => 'file',
                'sha'        => $sha,
                'file_type'  => strtolower( $extension ),
                'local_path' => $absolute_path,
                'rel_url'    => $relative_path,
                'raw_url'    => '', // Not used in local mode, kept for image upload compat
                'github_url' => '',
            );

            return $structure;

        }else{

            $first_dir = array_shift( $path_split );

            if( !array_key_exists( $first_dir, $structure ) ){
                $structure[ $first_dir ] = array(
                    'items' => array(),
                    'type'  => 'directory',
                );
            }

            $structure[ $first_dir ]['items'] = $this->add_to_structure( $structure[ $first_dir ]['items'], $path_split, $absolute_path, $relative_path );
            return $structure;

        }

    }

    public function get_item_content( $item_props ){

        if( !empty( $item_props['local_path'] ) && is_readable( $item_props['local_path'] ) ){
            return file_get_contents( $item_props['local_path'] );
        }

        GIW_Utils::log( 'Cannot read local file: ' . ( isset( $item_props['local_path'] ) ? $item_props['local_path'] : 'unknown' ) );
        return false;

    }

}

/**
 * RecursiveFilterIterator to skip unwanted directories.
 */
class GIW_Dir_Filter extends RecursiveFilterIterator{

    private $skip_dirs;

    public function __construct( RecursiveDirectoryIterator $iterator, $skip_dirs ){
        parent::__construct( $iterator );
        $this->skip_dirs = $skip_dirs;
    }

    public function accept(): bool{
        $filename = $this->current()->getFilename();
        if( $this->current()->isDir() && in_array( $filename, $this->skip_dirs, true ) ){
            return false;
        }
        return true;
    }

    public function getChildren(): ?RecursiveFilterIterator{
        return new self( $this->getInnerIterator()->getChildren(), $this->skip_dirs );
    }

}

?>
