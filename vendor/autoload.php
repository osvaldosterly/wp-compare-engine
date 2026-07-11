<?php
/**
 * Autoloader for WP Compare Engine.
 *
 * @package WPCE
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

spl_autoload_register( function ( $class ) {
    // Project namespace.
    $prefix = 'WPCE\\';
    
    // Base directory for the namespace prefix.
    $base_dir = WPCE_PLUGIN_DIR . 'includes/';

    // Does the class use the namespace prefix?
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    // Get the relative class name.
    $relative_class = substr( $class, $len );

    // Replace namespace separators with directory separators.
    $file_path = str_replace( '\\', '/', $relative_class );
    
    // Build filename: convert namespace to directory structure.
    // E.g., Core\Rewrite_Rules -> core/rewrite-rules.php
    $parts = explode( '/', $file_path );
    $filename = array_pop( $parts );
    // Convert underscores to hyphens and lowercase for filename.
    $filename = strtolower( str_replace( '_', '-', $filename ) );
    
    // Rebuild path - ensure subdir is also lowercase for case-sensitive systems.
    $subdir = implode( '/', $parts );
    if ( ! empty( $subdir ) ) {
        $file = $base_dir . strtolower( $subdir ) . '/' . $filename . '.php';
    } else {
        $file = $base_dir . $filename . '.php';
    }

    // If the file exists, require it.
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
