<?php
/**
 * Plugin Name: WP Compare Engine
 * Plugin URI: https://example.com/wp-compare-engine
 * Description: A production-ready comparison engine for WordPress Custom Post Types using ACF. SEO-friendly URLs, GenerateBlocks compatible, no custom tables.
 * Version: 1.0.0
 * Author: Senior WP Developer
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-compare-engine
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires At Least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'WPCE_VERSION', '1.0.0' );
define( 'WPCE_PLUGIN_FILE', __FILE__ );
define( 'WPCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once WPCE_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main Plugin Class.
 */
final class WP_Compare_Engine {

    /**
     * Single instance of the class.
     *
     * @var WP_Compare_Engine
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return WP_Compare_Engine
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook( WPCE_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( WPCE_PLUGIN_FILE, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'init_components' ), 1 );
    }

    /**
     * Activation hook.
     */
    public function activate() {
        // Flush rewrite rules.
        $this->flush_rewrite_rules();
        
        // Create default compare page if not exists.
        $this->create_compare_page();
    }

    /**
     * Deactivation hook.
     */
    public function deactivate() {
        $this->flush_rewrite_rules();
    }

    /**
     * Flush rewrite rules safely.
     */
    private function flush_rewrite_rules() {
        // Temporarily add rules to ensure they are flushed.
        $rewriter = new \WPCE\Core\Rewrite_Rules();
        $rewriter->register_rules();
        flush_rewrite_rules( false );
    }

    /**
     * Create default compare page.
     */
    private function create_compare_page() {
        $settings = get_option( 'wpce_settings', array() );
        $slug = isset( $settings['compare_slug'] ) ? $settings['compare_slug'] : 'compare';

        $page_exists = get_page_by_path( $slug );

        if ( ! $page_exists ) {
            wp_insert_post( array(
                'post_title'   => __( 'Compare Items', 'wp-compare-engine' ),
                'post_content' => '[wp_compare_table]',
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'meta_input'   => array(
                    '_wpce_is_compare_page' => true,
                ),
            ) );
        }
    }

    /**
     * Load text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'wp-compare-engine', false, dirname( WPCE_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Initialize components.
     */
    public function init_components() {
        // Core.
        new \WPCE\Core\Rewrite_Rules();
        new \WPCE\Core\Query_Handler();
        new \WPCE\Core\Template_Loader();
        
        // Frontend.
        new \WPCE\Frontend\Assets();
        new \WPCE\Frontend\Shortcodes();
        new \WPCE\Frontend\REST_API();
        new \WPCE\Frontend\Comparison_Logic();
        
        // Admin.
        if ( is_admin() ) {
            new \WPCE\Admin\Settings();
        }
    }
}

// Initialize plugin.
function wpce_init() {
    return WP_Compare_Engine::get_instance();
}
wpce_init();
