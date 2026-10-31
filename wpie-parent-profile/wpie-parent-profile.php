<?php
/**
 * Plugin Name: WPIE Parent Profile
 * Plugin URI: https://wevoi.org
 * Description: Personal information page for parents showing their profile, children, results, and certificates
 * Version: 1.0.0
 * Author: Kenan
 * Author URI: https://wevoi.org
 * Text Domain: wpie-parent-profile
 * Domain Path: /languages
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPIE_PARENT_PROFILE_VERSION', '1.0.0');
define('WPIE_PARENT_PROFILE_PATH', plugin_dir_path(__FILE__));
define('WPIE_PARENT_PROFILE_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once WPIE_PARENT_PROFILE_PATH . 'includes/class-parent-profile.php';
require_once WPIE_PARENT_PROFILE_PATH . 'includes/class-parent-profile-shortcode.php';

/**
 * Register plugin assets
 */
function wpie_parent_profile_register_assets() {
    wp_register_style(
        'wpie-parent-profile',
        WPIE_PARENT_PROFILE_URL . 'assets/css/parent-profile.css',
        array(),
        WPIE_PARENT_PROFILE_VERSION
    );
}
add_action('wp_enqueue_scripts', 'wpie_parent_profile_register_assets');

/**
 * Initialize Parent Profile Module
 */
function wpie_init_parent_profile_module() {
    // Check if classes exist
    if (!class_exists('WPIE_Parent_Profile_Shortcode')) {
        error_log('WPIE Parent Profile: WPIE_Parent_Profile_Shortcode class not found');
        return;
    }

    if (!class_exists('WPIE_Parent_Profile')) {
        error_log('WPIE Parent Profile: WPIE_Parent_Profile class not found');
        return;
    }

    // Register shortcode
    $shortcode = new WPIE_Parent_Profile_Shortcode();
    $shortcode->register();

    error_log('WPIE Parent Profile: Shortcode registered: wpie_parent_profile');

    // Initialize profile class
    $profile = new WPIE_Parent_Profile();
    $profile->init();
}
add_action('init', 'wpie_init_parent_profile_module');

/**
 * Load plugin textdomain
 */
function wpie_parent_profile_load_textdomain() {
    load_plugin_textdomain(
        'wpie-parent-profile',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'wpie_parent_profile_load_textdomain');
