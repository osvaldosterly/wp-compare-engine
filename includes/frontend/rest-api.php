<?php
/**
 * REST API Handler.
 *
 * Provides endpoints for AJAX search and comparison actions.
 *
 * @package WPCE\Frontend
 */

namespace WPCE\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class REST_API {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        register_rest_route( 'wp-compare/v1', '/search', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_search' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'q' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'post_type' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => 'any',
                ),
            ),
        ) );

        register_rest_route( 'wp-compare/v1', '/validate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_validate' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'slugs' => array(
                    'required'          => true,
                    'sanitize_callback' => array( $this, 'sanitize_slugs' ),
                ),
            ),
        ) );
    }

    /**
     * Handle search request.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function handle_search( $request ) {
        $query = $request->get_param( 'q' );
        $post_type = $request->get_param( 'post_type' );

        if ( empty( $query ) ) {
            return rest_ensure_response( array( 'results' => array() ) );
        }

        $settings = get_option( 'wpce_settings', array() );
        $allowed_post_types = isset( $settings['allowed_post_types'] ) ? $settings['allowed_post_types'] : array( 'post', 'product' );

        // If specific post type requested and allowed.
        if ( 'any' !== $post_type && in_array( $post_type, $allowed_post_types, true ) ) {
            $allowed_post_types = array( $post_type );
        }

        $args = array(
            's'              => $query,
            'post_type'      => $allowed_post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
        );

        $query_results = new \WP_Query( $args );
        $results = array();

        foreach ( $query_results->posts as $post_id ) {
            $post = get_post( $post_id );
            $thumb_id = get_post_thumbnail_id( $post_id );
            $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';

            $results[] = array(
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'slug'       => $post->post_name,
                'post_type'  => $post->post_type,
                'permalink'  => get_permalink( $post_id ),
                'thumbnail'  => $thumb_url,
                'excerpt'    => wp_trim_words( $post->post_excerpt, 15 ),
            );
        }

        return rest_ensure_response( array( 'results' => $results ) );
    }

    /**
     * Handle validation request.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function handle_validate( $request ) {
        $slugs = $request->get_param( 'slugs' );

        if ( ! is_array( $slugs ) ) {
            return rest_ensure_response( array( 'valid' => false, 'error' => 'Invalid slugs format' ) );
        }

        $settings = get_option( 'wpce_settings', array() );
        $min_items = isset( $settings['min_items'] ) ? intval( $settings['min_items'] ) : 2;
        $max_items = isset( $settings['max_items'] ) ? intval( $settings['max_items'] ) : 3;
        $allowed_post_types = isset( $settings['allowed_post_types'] ) ? $settings['allowed_post_types'] : array( 'post', 'product' );

        // Check count.
        if ( count( $slugs ) < $min_items ) {
            return rest_ensure_response( array(
                'valid' => false,
                'error' => sprintf( __( 'Minimum %d items required.', 'wp-compare-engine' ), $min_items ),
            ) );
        }

        // Check duplicates.
        if ( count( $slugs ) !== count( array_unique( $slugs ) ) ) {
            return rest_ensure_response( array(
                'valid' => false,
                'error' => __( 'Duplicate items detected.', 'wp-compare-engine' ),
            ) );
        }

        // Verify slugs exist.
        $valid_slugs = array();
        foreach ( $slugs as $slug ) {
            $post = get_page_by_path( sanitize_title( $slug ), OBJECT, $allowed_post_types );
            if ( $post && 'publish' === $post->post_status ) {
                $valid_slugs[] = $slug;
            }
        }

        if ( count( $valid_slugs ) !== count( $slugs ) ) {
            return rest_ensure_response( array(
                'valid' => false,
                'error' => __( 'One or more items not found.', 'wp-compare-engine' ),
            ) );
        }

        // Generate URL.
        $base_slug = isset( $settings['compare_slug'] ) ? $settings['compare_slug'] : 'compare';
        $compare_url = home_url( '/' . $base_slug . '/' . implode( '-vs-', $valid_slugs ) );

        return rest_ensure_response( array(
            'valid' => true,
            'url'   => $compare_url,
        ) );
    }

    /**
     * Sanitize slugs array.
     *
     * @param mixed $slugs Slugs input.
     * @return array
     */
    public function sanitize_slugs( $slugs ) {
        if ( ! is_array( $slugs ) ) {
            return array();
        }
        return array_map( 'sanitize_title', $slugs );
    }
}
