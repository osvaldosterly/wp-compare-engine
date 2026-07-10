<?php
/**
 * Rewrite Rules Handler.
 *
 * Registers custom rewrite rules for SEO-friendly comparison URLs.
 * Example: /compare/iphone-16-vs-galaxy-s25
 *
 * @package WPCE\Core
 */

namespace WPCE\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rewrite_Rules {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
    }

    /**
     * Register custom rewrite rules.
     */
    public function register_rules() {
        $settings = get_option( 'wpce_settings', array() );
        $base_slug = isset( $settings['compare_slug'] ) ? $settings['compare_slug'] : 'compare';

        // Rule: compare/slug1-vs-slug2-vs-slug3
        add_rewrite_rule(
            '^' . $base_slug . '/([a-z0-9-]+(?:-vs-[a-z0-9-]+)*)/?$',
            'index.php?wpce_compare=$matches[1]',
            'top'
        );
    }

    /**
     * Add custom query vars.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'wpce_compare';
        return $vars;
    }

    /**
     * Parse the compare slugs from the query var.
     *
     * @return array Array of post slugs.
     */
    public static function parse_slugs() {
        $compare_var = get_query_var( 'wpce_compare' );

        if ( empty( $compare_var ) ) {
            return array();
        }

        // Split by -vs-.
        $slugs = explode( '-vs-', $compare_var );

        // Sanitize slugs.
        $slugs = array_map( 'sanitize_title', $slugs );

        // Remove empty values.
        $slugs = array_filter( $slugs );

        return array_values( $slugs );
    }
}
