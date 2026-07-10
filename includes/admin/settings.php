<?php
/**
 * Admin Settings Page.
 *
 * @package WPCE\Admin
 */

namespace WPCE\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add menu page.
     */
    public function add_menu_page() {
        add_options_page(
            __( 'WP Compare Engine', 'wp-compare-engine' ),
            __( 'Compare Engine', 'wp-compare-engine' ),
            'manage_options',
            'wp-compare-engine',
            array( $this, 'render_settings_page' ),
            'dashicons-table-col-after',
            30
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting( 'wpce_settings_group', 'wpce_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );

        // Section: General.
        add_settings_section(
            'wpce_general_section',
            __( 'General Settings', 'wp-compare-engine' ),
            null,
            'wp-compare-engine'
        );

        add_settings_field(
            'compare_slug',
            __( 'Compare Base Slug', 'wp-compare-engine' ),
            array( $this, 'render_slug_field' ),
            'wp-compare-engine',
            'wpce_general_section'
        );

        add_settings_field(
            'max_items',
            __( 'Maximum Items', 'wp-compare-engine' ),
            array( $this, 'render_max_items_field' ),
            'wp-compare-engine',
            'wpce_general_section'
        );

        add_settings_field(
            'min_items',
            __( 'Minimum Items', 'wp-compare-engine' ),
            array( $this, 'render_min_items_field' ),
            'wp-compare-engine',
            'wpce_general_section'
        );

        add_settings_field(
            'allowed_post_types',
            __( 'Allowed Post Types', 'wp-compare-engine' ),
            array( $this, 'render_post_types_field' ),
            'wp-compare-engine',
            'wpce_general_section'
        );

        // Section: Features.
        add_settings_section(
            'wpce_features_section',
            __( 'Features', 'wp-compare-engine' ),
            null,
            'wp-compare-engine'
        );

        add_settings_field(
            'enable_sticky_bar',
            __( 'Enable Sticky Bar', 'wp-compare-engine' ),
            array( $this, 'render_checkbox_field' ),
            'wp-compare-engine',
            'wpce_features_section',
            array( 'option_name' => 'enable_sticky_bar' )
        );

        add_settings_field(
            'highlight_differences',
            __( 'Highlight Differences', 'wp-compare-engine' ),
            array( $this, 'render_checkbox_field' ),
            'wp-compare-engine',
            'wpce_features_section',
            array( 'option_name' => 'highlight_differences' )
        );
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'wpce_settings_group' );
                do_settings_sections( 'wp-compare-engine' );
                submit_button();
                ?>
            </form>
            <hr>
            <h2><?php _e( 'Usage Instructions', 'wp-compare-engine' ); ?></h2>
            <ol>
                <li><?php _e( 'Ensure ACF Pro is installed and active.', 'wp-compare-engine' ); ?></li>
                <li><?php _e( 'Create a page with slug "compare" (or your custom slug) containing the shortcode <code>[wp_compare_table]</code>.', 'wp-compare-engine' ); ?></li>
                <li><?php _e( 'Add compare checkboxes to your archive templates (see documentation).', 'wp-compare-engine' ); ?></li>
                <li><?php _e( 'Visit Settings > Permalinks and click "Save Changes" to flush rewrite rules.', 'wp-compare-engine' ); ?></li>
            </ol>
        </div>
        <?php
    }

    /**
     * Render slug field.
     */
    public function render_slug_field() {
        $settings = get_option( 'wpce_settings', array() );
        $value = isset( $settings['compare_slug'] ) ? $settings['compare_slug'] : 'compare';
        echo '<input type="text" name="wpce_settings[compare_slug]" value="' . esc_attr( $value ) . '" class="regular-text" />';
        echo '<p class="description">' . __( 'Base slug for comparison URLs (e.g., example.com/compare/...).', 'wp-compare-engine' ) . '</p>';
    }

    /**
     * Render max items field.
     */
    public function render_max_items_field() {
        $settings = get_option( 'wpce_settings', array() );
        $value = isset( $settings['max_items'] ) ? intval( $settings['max_items'] ) : 3;
        echo '<input type="number" name="wpce_settings[max_items]" value="' . esc_attr( $value ) . '" min="2" max="5" class="small-text" />';
    }

    /**
     * Render min items field.
     */
    public function render_min_items_field() {
        $settings = get_option( 'wpce_settings', array() );
        $value = isset( $settings['min_items'] ) ? intval( $settings['min_items'] ) : 2;
        echo '<input type="number" name="wpce_settings[min_items]" value="' . esc_attr( $value ) . '" min="2" max="5" class="small-text" />';
    }

    /**
     * Render post types field.
     */
    public function render_post_types_field() {
        $settings = get_option( 'wpce_settings', array() );
        $allowed = isset( $settings['allowed_post_types'] ) ? $settings['allowed_post_types'] : array( 'post' );
        
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        
        foreach ( $post_types as $pt ) {
            if ( 'attachment' === $pt->name ) {
                continue;
            }
            $checked = in_array( $pt->name, $allowed, true ) ? 'checked' : '';
            printf(
                '<label><input type="checkbox" name="wpce_settings[allowed_post_types][]" value="%s" %s /> %s</label><br>',
                esc_attr( $pt->name ),
                esc_attr( $checked ),
                esc_html( $pt->label )
            );
        }
    }

    /**
     * Render checkbox field.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( $args ) {
        $option_name = $args['option_name'] ?? '';
        if ( empty( $option_name ) ) {
            return;
        }
        
        $settings = get_option( 'wpce_settings', array() );
        $value = isset( $settings[ $option_name ] ) ? $settings[ $option_name ] : 1;
        printf(
            '<label><input type="checkbox" name="wpce_settings[%s]" value="1" %s /> %s</label>',
            esc_attr( $option_name ),
            checked( $value, 1, false ),
            esc_html( ucfirst( str_replace( '_', ' ', $option_name ) ) )
        );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Input settings.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $output = array();

        $output['compare_slug'] = sanitize_title( $input['compare_slug'] ?? 'compare' );
        $output['max_items'] = intval( $input['max_items'] ?? 3 );
        $output['min_items'] = intval( $input['min_items'] ?? 2 );
        
        if ( isset( $input['allowed_post_types'] ) && is_array( $input['allowed_post_types'] ) ) {
            $output['allowed_post_types'] = array_map( 'sanitize_key', $input['allowed_post_types'] );
        } else {
            $output['allowed_post_types'] = array( 'post' );
        }

        $output['enable_sticky_bar'] = isset( $input['enable_sticky_bar'] ) ? 1 : 0;
        $output['highlight_differences'] = isset( $input['highlight_differences'] ) ? 1 : 0;

        return $output;
    }
}
