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
    $file = $base_dir . strtolower( str_replace( '\\', '/', $relative_class ) ) . '.php';

    // If the file exists, require it.
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
