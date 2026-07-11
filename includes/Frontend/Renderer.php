<?php
/**
 * Renderer for Comparison Tables
 * 
 * Generates HTML output for the comparison table using templates.
 *
 * @package WPCE\Frontend
 * @since 1.0.0
 */

namespace WPCE\Frontend;

use WPCE\Core\Template_Loader;

class Renderer {

    /**
     * Array of post objects to compare.
     *
     * @var array
     */
    private $items;

    /**
     * Comparison data structure.
     *
     * @var array
     */
    private $comparison_data = array();

    /**
     * Constructor.
     *
     * @param array $items Array of WP_Post objects.
     */
    public function __construct($items) {
        $this->items = $items;
        $this->prepare_comparison_data();
    }

    /**
     * Prepare the comparison data by extracting common ACF fields.
     */
    private function prepare_comparison_data() {
        if (empty($this->items)) {
            return;
        }

        // Get all fields from the first item as a baseline
        $first_post_id = $this->items[0]->ID;
        
        // Get ACF fields for the first post (only published/active fields)
        if (function_exists('get_field_objects')) {
            $all_fields = get_field_objects($first_post_id);
        } else {
            $all_fields = array();
        }

        // Filter fields that exist on ALL compared items (intersection)
        $common_fields = array();
        
        foreach ($all_fields as $field_key => $field) {
            $is_common = true;
            
            // Skip specific internal ACF fields or null values
            if (empty($field['name']) || strpos($field_key, 'field_') !== 0) {
                continue;
            }

            // Check if this field exists on all other items
            for ($i = 1; $i < count($this->items); $i++) {
                $other_post_id = $this->items[$i]->ID;
                
                // Check if field exists on this post
                if (function_exists('get_field_object')) {
                    $other_field = get_field_object($field_key, $other_post_id);
                    if (empty($other_field) || $other_field['key'] !== $field_key) {
                        $is_common = false;
                        break;
                    }
                } else {
                    // Fallback if ACF Pro not active: check if meta exists
                    $meta_value = get_post_meta($other_post_id, $field['name'], true);
                    if (empty($meta_value) && empty($field['value'])) {
                         // If both are empty, we might still include it, but usually skip
                         // For strict comparison, we require the field definition to exist
                         $is_common = false;
                         break;
                    }
                }
            }

            if ($is_common) {
                $common_fields[$field_key] = $field;
            }
        }

        // Build the data structure
        $this->comparison_data = array(
            'fields' => $common_fields,
            'items'  => $this->items,
        );
    }

    /**
     * Render the comparison table.
     * Uses template hierarchy to allow theme overrides.
     */
    public function render() {
        if (empty($this->items)) {
            $this->render_empty_state();
            return;
        }

        // Load template
        // Looks for: theme/wp-compare/compare.php -> plugin/templates/compare.php
        Template_Loader::get_template_part('compare', null, array(
            'items' => $this->items,
            'data'  => $this->comparison_data,
        ));
    }

    /**
     * Render empty state when no items are selected.
     */
    private function render_empty_state() {
        Template_Loader::get_template_part('components/empty', null, array());
    }

    /**
     * Get the value of a specific field for a specific post.
     * Handles formatting for different ACF field types.
     *
     * @param int    $post_id Post ID.
     * @param array  $field   ACF Field array.
     * @return string Formatted HTML value.
     */
    public static function get_field_value($post_id, $field) {
        if (!function_exists('get_field')) {
            return esc_html(get_post_meta($post_id, $field['name'], true));
        }

        $value = get_field($field['name'], $post_id);

        // Handle empty values
        if (empty($value) && $value !== '0' && $value !== false) {
            return '<span class="wpce-empty">—</span>';
        }

        // Format based on field type
        $type = $field['type'] ?? 'text';
        $formatted_value = '';

        switch ($type) {
            case 'true_false':
                $formatted_value = $value ? '<span class="wpce-yes">✔ Yes</span>' : '<span class="wpce-no">✖ No</span>';
                break;

            case 'image':
                if (is_array($value) && !empty($value['url'])) {
                    $formatted_value = sprintf(
                        '<a href="%s" class="wpce-image-link" target="_blank"><img src="%s" alt="%s" class="wpce-compare-image"></a>',
                        esc_url($value['url']),
                        esc_url($value['sizes']['thumbnail'] ?? $value['url']),
                        esc_attr($value['alt'] ?? '')
                    );
                }
                break;

            case 'gallery':
                if (is_array($value)) {
                    $images = array();
                    foreach ($value as $img) {
                        $thumb = $img['sizes']['thumbnail'] ?? $img['url'];
                        $full = $img['url'];
                        $images[] = sprintf(
                            '<a href="%s" class="wpce-gallery-item" target="_blank"><img src="%s" alt="%s"></a>',
                            esc_url($full),
                            esc_url($thumb),
                            esc_attr($img['alt'] ?? '')
                        );
                    }
                    $formatted_value = '<div class="wpce-gallery">' . implode('', $images) . '</div>';
                }
                break;

            case 'textarea':
                $formatted_value = nl2br(esc_html($value));
                break;

            case 'url':
                $formatted_value = sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($value), esc_html($value));
                break;

            case 'select':
            case 'radio':
                // ACF usually returns the label if configured, otherwise value
                if (is_array($field['choices']) && isset($field['choices'][$value])) {
                    $formatted_value = esc_html($field['choices'][$value]);
                } else {
                    $formatted_value = esc_html($value);
                }
                break;

            case 'checkbox':
                if (is_array($value)) {
                    $labels = array();
                    if (is_array($field['choices'])) {
                        foreach ($value as $val) {
                            $labels[] = isset($field['choices'][$val]) ? $field['choices'][$val] : $val;
                        }
                    } else {
                        $labels = $value;
                    }
                    $formatted_value = esc_html(implode(', ', $labels));
                }
                break;

            case 'relationship':
            case 'post_object':
                if (is_array($value)) {
                    $links = array();
                    foreach ($value as $post_obj) {
                        if (is_object($post_obj)) {
                            $links[] = sprintf('<a href="%s">%s</a>', esc_url(get_permalink($post_obj)), esc_html(get_the_title($post_obj)));
                        }
                    }
                    $formatted_value = implode(', ', $links);
                } elseif (is_object($value)) {
                    $formatted_value = sprintf('<a href="%s">%s</a>', esc_url(get_permalink($value)), esc_html(get_the_title($value)));
                }
                break;

            case 'taxonomy':
                if (is_array($value)) {
                    $terms = wp_list_pluck($value, 'name');
                    $formatted_value = esc_html(implode(', ', $terms));
                } elseif (is_object($value)) {
                    $formatted_value = esc_html($value->name);
                }
                break;

            case 'repeater':
                if (is_array($value)) {
                    $rows = array();
                    foreach ($value as $row) {
                        $row_items = array();
                        foreach ($row as $sub_key => $sub_val) {
                            // Simple formatting for repeater subfields
                            $row_items[] = is_array($sub_val) ? json_encode($sub_val) : $sub_val;
                        }
                        $rows[] = implode(' - ', $row_items);
                    }
                    $formatted_value = '<ul class="wpce-repeater-list"><li>' . implode('</li><li>', $rows) . '</li></ul>';
                }
                break;

            case 'group':
                if (is_array($value)) {
                    $html = '<div class="wpce-group">';
                    foreach ($value as $sub_key => $sub_val) {
                        $html .= '<div class="wpce-group-row"><strong>' . esc_html(ucwords(str_replace('_', ' ', $sub_key))) . ':</strong> ' . esc_html($sub_val) . '</div>';
                    }
                    $html .= '</div>';
                    $formatted_value = $html;
                }
                break;

            case 'color_picker':
                $formatted_value = sprintf('<span class="wpce-color-swatch" style="background-color:%s;"></span> %s', esc_attr($value), esc_html($value));
                break;

            default:
                $formatted_value = esc_html($value);
                break;
        }

        // Allow filtering
        return apply_filters('wpce_render_field_value', $formatted_value, $field, $value, $post_id);
    }

    /**
     * Check if a row has different values across all items.
     * Used for highlighting differences.
     *
     * @param array  $field ACF Field array.
     * @return bool
     */
    public function has_differences($field) {
        $values = array();
        foreach ($this->items as $item) {
            $val = get_field($field['name'], $item->ID);
            // Serialize arrays for comparison
            if (is_array($val)) {
                $val = serialize($val);
            }
            $values[] = $val;
        }

        // If unique count > 1, there are differences
        return count(array_unique($values)) > 1;
    }
}
