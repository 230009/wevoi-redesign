<?php
/**
 * Plugin Name: WeVoi Summit Videos Top Three
 * Plugin URI: https://wevoi.org/wp-content/plugins/wevoi-summit-videos-top-three/
 * Description: Display top-three videos from the most recent completed WeVoi Summit in a sliding carousel on the homepage
 * Version: 1.0.0
 * Author: Kenan
 * Author URI: https://wevoi.org
 * Text Domain: wevoi-summit-videos-top-three
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WEVOI_SUMMIT_VIDEOS_VERSION', '1.0.0');
define('WEVOI_SUMMIT_VIDEOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEVOI_SUMMIT_VIDEOS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WEVOI_SUMMIT_VIDEOS_PLUGIN_DIR . 'includes/class-wevoi-summit-videos.php';

// Initialize the plugin
function wevoi_summit_videos_init() {
    return WeVoi_Summit_Videos::instance();
}

// Start the plugin
add_action('plugins_loaded', 'wevoi_summit_videos_init');

// Register activation hook
register_activation_hook(__FILE__, 'wevoi_summit_videos_activate');

function wevoi_summit_videos_activate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'wevoi_summit_videos_deactivate');

function wevoi_summit_videos_deactivate() {
    // Clean up if needed
    flush_rewrite_rules();
}
