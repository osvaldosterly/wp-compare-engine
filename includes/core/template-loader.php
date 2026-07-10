<?php
/**
 * Template Loader.
 *
 * Handles loading plugin templates with theme override support.
 *
 * @package WPCE\Core
 */

namespace WPCE\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Template_Loader {

    /**
     * Constructor.
     */
    public function __construct() {
        add_filter( 'template_include', array( $this, 'load_compare_template' ) );
    }

    /**
     * Load compare template if on compare page.
     *
     * @param string $template Current template path.
     * @return string
     */
    public function load_compare_template( $template ) {
        if ( ! Query_Handler::is_compare_page() ) {
            return $template;
        }

        // Check for theme override.
        $theme_template = locate_template( array(
            'wp-compare/compare.php',
            'wp-compare.php',
        ) );

        if ( $theme_template ) {
            return $theme_template;
        }

        // Use plugin template.
        return WPCE_PLUGIN_DIR . 'templates/compare.php';
    }

    /**
     * Get template part.
     *
     * @param string $slug Template slug.
     * @param string $name Optional template name.
     */
    public static function get_template_part( $slug, $name = '' ) {
        $template = '';

        // Look in theme first.
        if ( $name ) {
            $template = locate_template( array(
                "wp-compare/{$slug}-{$name}.php",
                "wp-compare/{$slug}.php",
            ) );
        } else {
            $template = locate_template( array(
                "wp-compare/{$slug}.php",
            ) );
        }

        // Fallback to plugin.
        if ( ! $template ) {
            if ( $name && file_exists( WPCE_PLUGIN_DIR . "templates/components/{$slug}-{$name}.php" ) ) {
                $template = WPCE_PLUGIN_DIR . "templates/components/{$slug}-{$name}.php";
            } elseif ( file_exists( WPCE_PLUGIN_DIR . "templates/components/{$slug}.php" ) ) {
                $template = WPCE_PLUGIN_DIR . "templates/components/{$slug}.php";
            }
        }

        if ( $template ) {
            load_template( $template, false );
        }
    }

    /**
     * Locate template file.
     *
     * @param string $template_name Template name.
     * @return string
     */
    public static function locate_template( $template_name ) {
        $theme_template = locate_template( "wp-compare/{$template_name}" );

        if ( $theme_template ) {
            return $theme_template;
        }

        $plugin_template = WPCE_PLUGIN_DIR . "templates/{$template_name}";

        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }

        return '';
    }
}
