<?php
/**
 * Query Handler for Comparison Requests
 * 
 * Parses URL slugs, validates items, and prepares data for rendering.
 *
 * @package WPCE\Frontend
 * @since 1.0.0
 */

namespace WPCE\Frontend;

use WP_Error;
use WP_Query;

class Query_Handler {

    /**
     * Array of post objects to compare.
     *
     * @var array
     */
    private $items = array();

    /**
     * Error message if validation fails.
     *
     * @var string|WP_Error
     */
    private $error = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->parse_request();
    }

    /**
     * Parse the request to extract slugs from the URL.
     * Supports both Rewrite Rules (/compare/a-vs-b) and Fallback (?items=a,b).
     */
    private function parse_request() {
        global $wp_query;

        // 1. Try to get slugs from Rewrite Rule variable
        $slugs_string = get_query_var('wpce_compare_items', '');

        // 2. Fallback: Check for query var 'items' (e.g., ?items=slug-a-vs-slug-b)
        if (empty($slugs_string) && isset($_GET['items'])) {
            $slugs_string = sanitize_text_field($_GET['items']);
        }

        // 3. Fallback: Check raw URL path if rewrite var is empty but we are on compare page
        if (empty($slugs_string)) {
            $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            $base_slug = get_option('wpce_compare_slug', 'compare');
            
            // Check if URL starts with base slug
            if (strpos($path, $base_slug) === 0) {
                $remainder = substr($path, strlen($base_slug));
                $remainder = trim($remainder, '/');
                if (!empty($remainder)) {
                    $slugs_string = $remainder;
                }
            }
        }

        if (empty($slugs_string)) {
            $this->error = new WP_Error('no_items', __('No items specified for comparison.', 'wp-compare-engine'));
            return;
        }

        // Normalize separator: allow both '-vs-' and ','
        // Priority to '-vs-' as per SEO requirement
        if (strpos($slugs_string, '-vs-') !== false) {
            $slugs = explode('-vs-', $slugs_string);
        } else {
            $slugs = explode(',', $slugs_string);
        }

        // Sanitize and filter empty slugs
        $slugs = array_filter(array_map('sanitize_title', $slugs));
        $slugs = array_unique($slugs); // Remove duplicates

        if (empty($slugs)) {
            $this->error = new WP_Error('invalid_slugs', __('Invalid slugs provided.', 'wp-compare-engine'));
            return;
        }

        $this->fetch_posts($slugs);
    }

    /**
     * Fetch posts based on slugs.
     *
     * @param array $slugs Array of post slugs.
     */
    private function fetch_posts($slugs) {
        $allowed_post_types = get_option('wpce_allowed_post_types', array('post'));
        
        $args = array(
            'post_type'      => $allowed_post_types,
            'post_status'    => 'publish',
            'post_name__in'  => $slugs,
            'posts_per_page' => count($slugs),
            'orderby'        => 'post_name__in',
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            // Preserve the order of slugs from the URL
            $posts_map = array();
            foreach ($query->posts as $post) {
                $posts_map[$post->post_name] = $post;
            }

            foreach ($slugs as $slug) {
                if (isset($posts_map[$slug])) {
                    $this->items[] = $posts_map[$slug];
                }
            }
        }

        wp_reset_postdata();

        // Validation Checks
        $min_items = apply_filters('wpce_min_compare_items', get_option('wpce_min_items', 2));
        $max_items = apply_filters('wpce_max_compare_items', get_option('wpce_max_items', 3));

        if (count($this->items) < $min_items) {
            /* translators: 1: count, 2: minimum required */
            $this->error = new WP_Error('too_few_items', sprintf(__('Only %1$d item(s) found. Minimum %2$d required.', 'wp-compare-engine'), count($this->items), $min_items));
            return;
        }

        if (count($this->items) > $max_items) {
            // Slice to max allowed instead of erroring out completely for better UX
            $this->items = array_slice($this->items, 0, $max_items);
        }
    }

    /**
     * Get the validated items.
     *
     * @return array|WP_Error Array of WP_Post objects or WP_Error.
     */
    public function get_items() {
        if ($this->error) {
            return $this->error;
        }
        return $this->items;
    }

    /**
     * Check if there is an error.
     *
     * @return bool
     */
    public function has_error() {
        return !empty($this->error);
    }

    /**
     * Get the error message.
     *
     * @return string|WP_Error
     */
    public function get_error() {
        return $this->error;
    }
}
// Debug version: Sat Jul 11 11:07:52 UTC 2026
