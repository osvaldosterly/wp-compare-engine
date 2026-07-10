<?php
/**
 * Shortcodes Handler.
 *
 * Registers [wp_compare_table] and [wp_compare_selector].
 *
 * @package WPCE\Frontend
 */

namespace WPCE\Frontend;

use WPCE\Core\Query_Handler;
use WPCE\Core\Template_Loader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shortcodes {

    /**
     * Constructor.
     */
    public function __construct() {
        add_shortcode( 'wp_compare_table', array( $this, 'render_compare_table' ) );
        add_shortcode( 'wp_compare_selector', array( $this, 'render_compare_selector' ) );
    }

    /**
     * Render comparison table.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     * @return string
     */
    public function render_compare_table( $atts, $content = '' ) {
        // Only render if we are on a valid compare page with posts.
        $slugs = \WPCE\Core\Rewrite_Rules::parse_slugs();
        
        if ( empty( $slugs ) ) {
            return '<p class="wpce-notice">' . __( 'No items selected for comparison.', 'wp-compare-engine' ) . '</p>';
        }

        $settings = get_option( 'wpce_settings', array() );
        $allowed_post_types = isset( $settings['allowed_post_types'] ) ? $settings['allowed_post_types'] : array( 'post', 'product' );

        $posts = array();
        foreach ( $slugs as $slug ) {
            $post = get_page_by_path( $slug, OBJECT, $allowed_post_types );
            if ( $post ) {
                $posts[] = $post;
            }
        }

        if ( count( $posts ) < 2 ) {
            return '<p class="wpce-notice">' . __( 'Select at least 2 items to compare.', 'wp-compare-engine' ) . '</p>';
        }

        // Start output buffering.
        ob_start();

        // Load template.
        Template_Loader::get_template_part( 'table', 'main' );

        return ob_get_clean();
    }

    /**
     * Render floating compare selector bar.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     * @return string
     */
    public function render_compare_selector( $atts, $content = '' ) {
        // This shortcode is usually called via AJAX or embedded in theme.
        // For the floating bar, we typically enqueue JS which renders it dynamically.
        // But we can provide a static fallback.
        
        ob_start();
        Template_Loader::get_template_part( 'selector', 'bar' );
        return ob_get_clean();
    }
}
