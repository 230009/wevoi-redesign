<?php
/**
 * Main WeVoi Summit Videos class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Summit_Videos {
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once WEVOI_SUMMIT_VIDEOS_PLUGIN_DIR . 'includes/class-summit-videos-database.php';
        require_once WEVOI_SUMMIT_VIDEOS_PLUGIN_DIR . 'includes/class-summit-videos-shortcode.php';
        require_once WEVOI_SUMMIT_VIDEOS_PLUGIN_DIR . 'includes/class-summit-videos-admin.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));

        // Initialize shortcode and admin
        add_action('init', array($this, 'init_components'));

        // Add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    /**
     * Register scripts and styles
     */
    public function register_scripts() {
        // Register CSS
        wp_register_style(
            'wevoi-summit-videos-style',
            WEVOI_SUMMIT_VIDEOS_PLUGIN_URL . 'assets/css/wevoi-summit-videos-style.css',
            array(),
            WEVOI_SUMMIT_VIDEOS_VERSION
        );

        // Register JS
        wp_register_script(
            'wevoi-summit-videos-script',
            WEVOI_SUMMIT_VIDEOS_PLUGIN_URL . 'assets/js/wevoi-summit-videos-script.js',
            array('jquery'),
            WEVOI_SUMMIT_VIDEOS_VERSION,
            true
        );

        // Add YouTube API for video embedding
        wp_register_script(
            'youtube-api',
            'https://www.youtube.com/iframe_api',
            array(),
            null,
            true
        );

        // Add Swiper.js for carousel functionality
        wp_register_style(
            'swiper-css',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
            array(),
            '11.0.0'
        );

        wp_register_script(
            'swiper-js',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
            array(),
            '11.0.0',
            true
        );
    }

    /**
     * Initialize components
     */
    public function init_components() {
        WeVoi_Summit_Videos_Shortcode::instance();
        WeVoi_Summit_Videos_Admin::instance();
    }

    /**
     * Add settings link to plugin page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wevoi-summit-videos-settings') . '">' . __('Settings', 'wevoi-summit-videos-top-three') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
