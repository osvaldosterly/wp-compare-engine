<?php
/**
 * Comparison Logic & ACF Renderer.
 *
 * Core logic for comparing posts and rendering ACF fields.
 *
 * @package WPCE\Frontend
 */

namespace WPCE\Frontend;

use WPCE\Core\Query_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Comparison_Logic {

    /**
     * Array of compared posts.
     *
     * @var array
     */
    private $posts = array();

    /**
     * Array of common field keys.
     *
     * @var array
     */
    private $common_fields = array();

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_loaded', array( $this, 'init_comparison' ) );
    }

    /**
     * Initialize comparison data.
     */
    public function init_comparison() {
        if ( ! Query_Handler::is_compare_page() ) {
            return;
        }

        // Try to get posts from global first (set by Query_Handler).
        if ( ! empty( $GLOBALS['wpce_compared_posts'] ) && is_array( $GLOBALS['wpce_compared_posts'] ) ) {
            $this->posts = $GLOBALS['wpce_compared_posts'];
        } else {
            // Fallback: re-fetch based on slugs.
            $slugs = \WPCE\Core\Rewrite_Rules::parse_slugs();
            $settings = get_option( 'wpce_settings', array() );
            $allowed_post_types = isset( $settings['allowed_post_types'] ) ? $settings['allowed_post_types'] : array( 'post', 'product' );

            $this->posts = array();
            foreach ( $slugs as $slug ) {
                $post = get_page_by_path( $slug, OBJECT, $allowed_post_types );
                if ( $post ) {
                    $this->posts[] = $post;
                }
            }
        }

        $this->find_common_fields();
    }

    /**
     * Find common ACF fields across all compared posts.
     */
    private function find_common_fields() {
        if ( empty( $this->posts ) ) {
            return;
        }

        $field_sets = array();

        foreach ( $this->posts as $post ) {
            // Get all ACF field keys for this post.
            // We use get_field_objects to get full field data including labels.
            $fields = get_field_objects( $post->ID );

            if ( ! is_array( $fields ) ) {
                continue;
            }

            $keys = array_keys( $fields );
            $field_sets[] = $keys;
        }

        if ( empty( $field_sets ) ) {
            return;
        }

        // Find intersection of all field keys.
        $this->common_fields = call_user_func_array( 'array_intersect', $field_sets );

        // Sort fields by group order if possible, otherwise alphabetically.
        sort( $this->common_fields );
    }

    /**
     * Get compared posts.
     *
     * @return array
     */
    public function get_posts() {
        return $this->posts;
    }

    /**
     * Get common fields.
     *
     * @return array
     */
    public function get_common_fields() {
        return $this->common_fields;
    }

    /**
     * Render a specific field value for a post.
     *
     * @param int    $post_id   Post ID.
     * @param string $field_key Field key/name.
     * @return string HTML output.
     */
    public function render_field( $post_id, $field_key ) {
        $value = get_field( $field_key, $post_id );

        // Allow filtering before rendering.
        $value = apply_filters( 'wpce_field_value', $value, $field_key, $post_id );

        if ( empty( $value ) && ! is_numeric( $value ) && ! is_bool( $value ) ) {
            return '<span class="compare-empty">—</span>';
        }

        $field_object = get_field_object( $field_key, $post_id );
        $type = isset( $field_object['type'] ) ? $field_object['type'] : 'text';

        // Render based on type.
        $output = $this->render_by_type( $value, $type, $field_object, $post_id );

        return apply_filters( 'wpce_rendered_field', $output, $value, $type, $field_key, $post_id );
    }

    /**
     * Render value based on ACF type.
     *
     * @param mixed  $value        Field value.
     * @param string $type         Field type.
     * @param array  $field_object Field object settings.
     * @param int    $post_id      Post ID.
     * @return string
     */
    private function render_by_type( $value, $type, $field_object, $post_id ) {
        switch ( $type ) {
            case 'image':
                return $this->render_image( $value );
            case 'gallery':
                return $this->render_gallery( $value );
            case 'true_false':
                return $value ? '<span class="compare-yes">✔ Yes</span>' : '<span class="compare-no">✖ No</span>';
            case 'relationship':
            case 'post_object':
                return $this->render_relationship( $value );
            case 'taxonomy':
                return $this->render_taxonomy( $value, $field_object );
            case 'repeater':
                return $this->render_repeater( $value, $field_object, $post_id );
            case 'group':
                return $this->render_group( $value, $field_object, $post_id );
            case 'wysiwyg':
                return wp_kses_post( $value );
            case 'link':
                return $this->render_link( $value );
            case 'google_map':
                return $this->render_google_map( $value );
            default:
                if ( is_array( $value ) ) {
                    return esc_html( implode( ', ', $value ) );
                }
                return esc_html( $value );
        }
    }

    /**
     * Render image field.
     */
    private function render_image( $value ) {
        if ( ! is_array( $value ) || empty( $value['url'] ) ) {
            return '—';
        }
        $thumbnail = wp_get_attachment_image_src( $value['id'], 'thumbnail' );
        $full = $value['url'];
        
        if ( $thumbnail ) {
            return sprintf(
                '<a href="%s" class="compare-image-link" data-full="%s"><img src="%s" alt="%s" loading="lazy" /></a>',
                esc_url( $full ),
                esc_url( $full ),
                esc_url( $thumbnail[0] ),
                esc_attr( $value['alt'] ?? '' )
            );
        }
        
        return sprintf(
            '<img src="%s" alt="%s" loading="lazy" />',
            esc_url( $value['url'] ),
            esc_attr( $value['alt'] ?? '' )
        );
    }

    /**
     * Render gallery field.
     */
    private function render_gallery( $value ) {
        if ( ! is_array( $value ) ) {
            return '—';
        }
        $output = '<div class="compare-gallery">';
        foreach ( $value as $image ) {
            $thumb = wp_get_attachment_image_src( $image['id'], 'thumbnail' );
            $src = $thumb ? $thumb[0] : $image['url'];
            $output .= sprintf(
                '<a href="%s" class="compare-gallery-item"><img src="%s" alt="%s" loading="lazy" /></a>',
                esc_url( $image['url'] ),
                esc_url( $src ),
                esc_attr( $image['alt'] ?? '' )
            );
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Render relationship/post_object field.
     */
    private function render_relationship( $value ) {
        if ( ! is_array( $value ) ) {
            $value = array( $value );
        }
        $links = array();
        foreach ( $value as $post ) {
            if ( is_object( $post ) && isset( $post->ID ) ) {
                $links[] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url( get_permalink( $post->ID ) ),
                    esc_html( get_the_title( $post->ID ) )
                );
            }
        }
        return implode( ', ', $links );
    }

    /**
     * Render taxonomy field.
     */
    private function render_taxonomy( $value, $field_object ) {
        if ( ! is_array( $value ) ) {
            return '—';
        }
        $names = wp_list_pluck( $value, 'name' );
        return esc_html( implode( ', ', $names ) );
    }

    /**
     * Render repeater field.
     */
    private function render_repeater( $value, $field_object, $post_id ) {
        if ( ! is_array( $value ) ) {
            return '—';
        }
        
        $output = '<ul class="compare-repeater">';
        foreach ( $value as $row ) {
            $output .= '<li>';
            $row_values = array();
            foreach ( $row as $sub_key => $sub_value ) {
                if ( ! empty( $sub_value ) ) {
                    $row_values[] = esc_html( $sub_value );
                }
            }
            $output .= implode( ' - ', $row_values );
            $output .= '</li>';
        }
        $output .= '</ul>';
        return $output;
    }

    /**
     * Render group field.
     */
    private function render_group( $value, $field_object, $post_id ) {
        if ( ! is_array( $value ) ) {
            return '—';
        }
        $output = '<div class="compare-group">';
        foreach ( $value as $sub_key => $sub_value ) {
            if ( ! empty( $sub_value ) ) {
                $output .= sprintf(
                    '<div class="compare-group-row"><strong>%s:</strong> %s</div>',
                    esc_html( ucfirst( str_replace( '_', ' ', $sub_key ) ) ),
                    esc_html( is_array( $sub_value ) ? implode( ', ', $sub_value ) : $sub_value )
                );
            }
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Render link field.
     */
    private function render_link( $value ) {
        if ( ! is_array( $value ) || empty( $value['url'] ) ) {
            return '—';
        }
        return sprintf(
            '<a href="%s" target="%s" rel="%s">%s</a>',
            esc_url( $value['url'] ),
            esc_attr( $value['target'] ?? '_self' ),
            esc_attr( isset( $value['target'] ) && '_blank' === $value['target'] ? 'noopener noreferrer' : '' ),
            esc_html( $value['title'] ?? $value['url'] )
        );
    }

    /**
     * Render Google Map field.
     */
    private function render_google_map( $value ) {
        if ( ! is_array( $value ) || empty( $value['address'] ) ) {
            return '—';
        }
        return esc_html( $value['address'] );
    }

    /**
     * Check if values are different across posts for a field.
     *
     * @param string $field_key Field key.
     * @return bool
     */
    public function has_differences( $field_key ) {
        $values = array();
        foreach ( $this->posts as $post ) {
            $val = get_field( $field_key, $post->ID );
            $values[] = maybe_serialize( $val );
        }
        return count( array_unique( $values ) ) > 1;
    }

    /**
     * Get field label.
     *
     * @param string $field_key Field key.
     * @param int    $post_id   Post ID (any post, assuming same structure).
     * @return string
     */
    public function get_field_label( $field_key, $post_id ) {
        $field_object = get_field_object( $field_key, $post_id );
        if ( $field_object && ! empty( $field_object['label'] ) ) {
            return $field_object['label'];
        }
        // Fallback to humanized key.
        return ucwords( str_replace( '_', ' ', $field_key ) );
    }
}
