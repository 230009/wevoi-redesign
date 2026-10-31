<?php
/**
 * Plugin Name: WeVoi Student Dashboard
 * Plugin URI: https://wevoi.org
 * Description: Student dashboard for competition management
 * Version: 1.0.4
 * Author: Kenan
 * Author URI: https://wevoi.org
 * Text Domain: wevoi-student-dashboard
 * Domain Path: /languages
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WEVOI_STUDENT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WEVOI_STUDENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WEVOI_STUDENT_VERSION', '1.0.4');

// Include necessary files
require_once WEVOI_STUDENT_PLUGIN_PATH . 'class-student-dashboard.php';
require_once WEVOI_STUDENT_PLUGIN_PATH . 'class-student-shortcode.php';

/**
 * Register module assets
 */
function wevoi_student_register_assets() {
    wp_register_style(
        'wevoi-student-dashboard',
        WEVOI_STUDENT_PLUGIN_URL . 'assets/css/student-dashboard.css',
        array(),
        WEVOI_STUDENT_VERSION
    );

    wp_register_script(
        'wevoi-student-dashboard',
        WEVOI_STUDENT_PLUGIN_URL . 'assets/js/student-dashboard.js',
        array('jquery'),
        WEVOI_STUDENT_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'wevoi_student_register_assets');

/**
 * Initialize Student Module
 */
function wevoi_init_student_module() {
    // Register shortcode
    $shortcode = new Wevoi_Student_Shortcode();
    $shortcode->register();

    // Initialize dashboard
    $dashboard = new Wevoi_Student_Dashboard();
    $dashboard->init();
}
add_action('init', 'wevoi_init_student_module');

/**
 * Load plugin textdomain
 */
function wevoi_student_load_textdomain() {
    load_plugin_textdomain(
        'wevoi-student-dashboard',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'wevoi_student_load_textdomain');

