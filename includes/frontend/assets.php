<?php
/**
 * Assets Handler.
 *
 * Enqueues CSS and JS files.
 *
 * @package WPCE\Frontend
 */

namespace WPCE\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_assets() {
        // Only enqueue on pages where compare functionality is needed.
        // We enqueue everywhere for the floating bar logic, but could be optimized.
        
        wp_enqueue_style(
            'wpce-style',
            WPCE_PLUGIN_URL . 'assets/css/wp-compare.css',
            array(),
            WPCE_VERSION
        );

        wp_enqueue_script(
            'wpce-script',
            WPCE_PLUGIN_URL . 'assets/js/wp-compare.js',
            array(),
            WPCE_VERSION,
            true
        );

        // Localize script with config.
        $settings = get_option( 'wpce_settings', array() );
        $config = array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'restUrl'       => rest_url( 'wp-compare/v1/' ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'compareSlug'   => isset( $settings['compare_slug'] ) ? $settings['compare_slug'] : 'compare',
            'maxItems'      => isset( $settings['max_items'] ) ? intval( $settings['max_items'] ) : 3,
            'minItems'      => isset( $settings['min_items'] ) ? intval( $settings['min_items'] ) : 2,
            'storageKey'    => 'wpce_compare_list',
            'strings'       => array(
                'compare'      => __( 'Compare', 'wp-compare-engine' ),
                'compared'     => __( 'Compared', 'wp-compare-engine' ),
                'clearAll'     => __( 'Clear All', 'wp-compare-engine' ),
                'searchTitle'  => __( 'Search Items to Compare', 'wp-compare-engine' ),
                'searchPlaceholder' => __( 'Type to search...', 'wp-compare-engine' ),
                'noResults'    => __( 'No items found.', 'wp-compare-engine' ),
                'maxReached'   => sprintf( __( 'Maximum %d items allowed.', 'wp-compare-engine' ), isset( $settings['max_items'] ) ? intval( $settings['max_items'] ) : 3 ),
            ),
        );

        wp_localize_script( 'wpce-script', 'wpceConfig', $config );
    }
}
