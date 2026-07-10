<?php
/**
 * Query Handler.
 *
 * Handles fetching and validating posts for comparison.
 *
 * @package WPCE\Core
 */

namespace WPCE\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Query_Handler {

    /**
     * Array of compared posts.
     *
     * @var array
     */
    private $compared_posts = array();

    /**
     * Error message if any.
     *
     * @var string
     */
    private $error = '';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'template_redirect', array( $this, 'handle_compare_query' ), 5 );
    }

    /**
     * Handle compare query on template redirect.
     */
    public function handle_compare_query() {
        $slugs = Rewrite_Rules::parse_slugs();

        if ( empty( $slugs ) ) {
            return;
        }

        $settings = get_option( 'wpce_settings', array() );
        $min_items = isset( $settings['min_items'] ) ? intval( $settings['min_items'] ) : 2;
        $max_items = isset( $settings['max_items'] ) ? intval( $settings['max_items'] ) : 3;
        $allowed_post_types = isset( $settings['allowed_post_types'] ) ? $settings['allowed_post_types'] : array( 'post', 'product' );

        // Validate count.
        if ( count( $slugs ) < $min_items ) {
            wp_die( 
                sprintf( __( 'Minimum %d items required for comparison.', 'wp-compare-engine' ), $min_items ), 
                '', 
                array( 'response' => 400 ) 
            );
        }

        if ( count( $slugs ) > $max_items ) {
            wp_die( 
                sprintf( __( 'Maximum %d items allowed for comparison.', 'wp-compare-engine' ), $max_items ), 
                '', 
                array( 'response' => 400 ) 
            );
        }

        // Check for duplicates.
        if ( count( $slugs ) !== count( array_unique( $slugs ) ) ) {
            wp_die( 
                __( 'Duplicate items detected in comparison.', 'wp-compare-engine' ), 
                '', 
                array( 'response' => 400 ) 
            );
        }

        // Fetch posts by slug.
        $posts = array();
        foreach ( $slugs as $slug ) {
            $post = get_page_by_path( $slug, OBJECT, $allowed_post_types );
            if ( $post && 'publish' === $post->post_status ) {
                $posts[] = $post;
            }
        }

        if ( count( $posts ) !== count( $slugs ) ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            include get_query_template( '404' );
            exit;
        }

        $this->compared_posts = $posts;

        // Modify the main query to use these posts.
        add_filter( 'the_title', array( $this, 'modify_page_title' ), 10, 2 );
        add_filter( 'document_title_parts', array( $this, 'modify_document_title' ) );
        
        // Store globally for other classes to access.
        $GLOBALS['wpce_compared_posts'] = $posts;
    }

    /**
     * Modify page title for SEO.
     *
     * @param string $title Post title.
     * @param int    $id    Post ID.
     * @return string
     */
    public function modify_page_title( $title, $id ) {
        if ( in_the_loop() && is_main_query() && get_queried_object_id() === $id ) {
            $titles = array();
            foreach ( $this->compared_posts as $post ) {
                $titles[] = $post->post_title;
            }
            return implode( ' vs ', $titles );
        }
        return $title;
    }

    /**
     * Modify document title for SEO.
     *
     * @param array $parts Title parts.
     * @return array
     */
    public function modify_document_title( $parts ) {
        $titles = array();
        foreach ( $this->compared_posts as $post ) {
            $titles[] = $post->post_title;
        }
        $parts['title'] = implode( ' vs ', $titles );
        return $parts;
    }

    /**
     * Get compared posts.
     *
     * @return array
     */
    public function get_compared_posts() {
        return $this->compared_posts;
    }

    /**
     * Check if we are on a compare page.
     *
     * @return bool
     */
    public static function is_compare_page() {
        return ! empty( Rewrite_Rules::parse_slugs() );
    }
}
