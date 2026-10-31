<?php
/**
 * Plugin Name: WeVoi Supervisor Dashboard
 * Plugin URI: https://wevoi.org/wp-content/plugins/wevoi-supervisor-dashboard/readme.html
 * Description: Supervisor dashboard for competition management
 * Version: 1.0.0
 * Author: Kenan
 * Author URI: https://wevoi.org
 * Text Domain: wevoi-supervisor-dashboard
 * Domain Path: /languages
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WEVOI_SUPERVISOR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WEVOI_SUPERVISOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WEVOI_SUPERVISOR_VERSION', '1.0.0');
define('WEVOI_SUPERVISOR_MODULE_PATH', plugin_dir_path(__FILE__));

// Include necessary files
require_once WEVOI_SUPERVISOR_PLUGIN_PATH . 'class-supervisor-dashboard.php';
require_once WEVOI_SUPERVISOR_PLUGIN_PATH . 'class-supervisor-shortcode.php';
require_once WEVOI_SUPERVISOR_PLUGIN_PATH . 'class-parent-dashboard.php';
require_once WEVOI_SUPERVISOR_PLUGIN_PATH . 'class-parent-shortcode.php';

/**
 * Register module assets
 */
function wevoi_supervisor_register_assets() {
    wp_register_style(
        'wevoi-supervisor-dashboard',
        WEVOI_SUPERVISOR_PLUGIN_URL . 'assets/css/supervisor-dashboard.css',
        array(),
        WEVOI_SUPERVISOR_VERSION
    );

    wp_register_script(
        'wevoi-supervisor-dashboard',
        WEVOI_SUPERVISOR_PLUGIN_URL . 'assets/js/supervisor-dashboard.js',
        array('jquery'),
        WEVOI_SUPERVISOR_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'wevoi_supervisor_register_assets');

/**
 * Initialize Supervisor Module
 */
function wevoi_init_supervisor_module() {
    // Register supervisor shortcode
    $supervisor_shortcode = new Wevoi_Supervisor_Shortcode();
    $supervisor_shortcode->register();

    // Initialize supervisor dashboard
    $supervisor_dashboard = new Wevoi_Supervisor_Dashboard();
    $supervisor_dashboard->init();

    // Register parent shortcode
    $parent_shortcode = new Wevoi_Parent_Shortcode();
    $parent_shortcode->register();

    // Initialize parent dashboard
    $parent_dashboard = new Wevoi_Parent_Dashboard();
    $parent_dashboard->init();
}
add_action('init', 'wevoi_init_supervisor_module');

/**
 * Load plugin textdomain
 */
function wevoi_supervisor_load_textdomain() {
    load_plugin_textdomain(
        'wevoi-supervisor-dashboard',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'wevoi_supervisor_load_textdomain');
