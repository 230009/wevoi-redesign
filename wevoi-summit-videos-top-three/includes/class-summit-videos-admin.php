<?php
/**
 * Admin functionality for WeVoi Summit Videos
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Summit_Videos_Admin {
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('WeVoi Summit Videos Settings', 'wevoi-summit-videos-top-three'),
            __('WeVoi Summit Videos', 'wevoi-summit-videos-top-three'),
            'manage_options',
            'wevoi-summit-videos-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wevoi_summit_videos_settings', 'wevoi_summit_videos_options');

        add_settings_section(
            'wevoi_summit_videos_general',
            __('General Settings', 'wevoi-summit-videos-top-three'),
            array($this, 'general_section_callback'),
            'wevoi_summit_videos_settings'
        );

        add_settings_field(
            'default_competition_code',
            __('Default Competition Code', 'wevoi-summit-videos-top-three'),
            array($this, 'competition_code_field_callback'),
            'wevoi_summit_videos_settings',
            'wevoi_summit_videos_general'
        );

        add_settings_field(
            'default_autoplay',
            __('Enable Autoplay', 'wevoi-summit-videos-top-three'),
            array($this, 'autoplay_field_callback'),
            'wevoi_summit_videos_settings',
            'wevoi_summit_videos_general'
        );

        add_settings_field(
            'default_speed',
            __('Autoplay Speed (ms)', 'wevoi-summit-videos-top-three'),
            array($this, 'speed_field_callback'),
            'wevoi_summit_videos_settings',
            'wevoi_summit_videos_general'
        );

        add_settings_field(
            'default_slides_per_view',
            __('Slides Per View', 'wevoi-summit-videos-top-three'),
            array($this, 'slides_per_view_field_callback'),
            'wevoi_summit_videos_settings',
            'wevoi_summit_videos_general'
        );

        add_settings_field(
            'default_space_between',
            __('Space Between Slides (px)', 'wevoi-summit-videos-top-three'),
            array($this, 'space_between_field_callback'),
            'wevoi_summit_videos_settings',
            'wevoi_summit_videos_general'
        );
    }

    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure the default settings for the WeVoi Summit Videos carousel.', 'wevoi-summit-videos-top-three') . '</p>';
    }

    /**
     * Competition code field callback
     */
    public function competition_code_field_callback() {
        $options = get_option('wevoi_summit_videos_options');
        $value = isset($options['default_competition_code']) ? $options['default_competition_code'] : '';
        echo '<input type="number" name="wevoi_summit_videos_options[default_competition_code]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Leave empty to use the most recent completed summit automatically.', 'wevoi-summit-videos-top-three') . '</p>';
    }

    /**
     * Autoplay field callback
     */
    public function autoplay_field_callback() {
        $options = get_option('wevoi_summit_videos_options');
        $value = isset($options['default_autoplay']) ? $options['default_autoplay'] : 'true';
        echo '<select name="wevoi_summit_videos_options[default_autoplay]">';
        echo '<option value="true" ' . selected($value, 'true', false) . '>' . __('Yes', 'wevoi-summit-videos-top-three') . '</option>';
        echo '<option value="false" ' . selected($value, 'false', false) . '>' . __('No', 'wevoi-summit-videos-top-three') . '</option>';
        echo '</select>';
    }

    /**
     * Speed field callback
     */
    public function speed_field_callback() {
        $options = get_option('wevoi_summit_videos_options');
        $value = isset($options['default_speed']) ? $options['default_speed'] : '3000';
        echo '<input type="number" name="wevoi_summit_videos_options[default_speed]" value="' . esc_attr($value) . '" min="1000" max="10000" step="500" />';
        echo '<p class="description">' . __('Time in milliseconds between automatic slides.', 'wevoi-summit-videos-top-three') . '</p>';
    }

    /**
     * Slides per view field callback
     */
    public function slides_per_view_field_callback() {
        $options = get_option('wevoi_summit_videos_options');
        $value = isset($options['default_slides_per_view']) ? $options['default_slides_per_view'] : '3';
        echo '<select name="wevoi_summit_videos_options[default_slides_per_view]">';
        echo '<option value="1" ' . selected($value, '1', false) . '>1</option>';
        echo '<option value="2" ' . selected($value, '2', false) . '>2</option>';
        echo '<option value="3" ' . selected($value, '3', false) . '>3</option>';
        echo '<option value="4" ' . selected($value, '4', false) . '>4</option>';
        echo '<option value="5" ' . selected($value, '5', false) . '>5</option>';
        echo '</select>';
    }

    /**
     * Space between field callback
     */
    public function space_between_field_callback() {
        $options = get_option('wevoi_summit_videos_options');
        $value = isset($options['default_space_between']) ? $options['default_space_between'] : '30';
        echo '<input type="number" name="wevoi_summit_videos_options[default_space_between]" value="' . esc_attr($value) . '" min="0" max="100" step="5" />';
        echo '<p class="description">' . __('Space between slides in pixels.', 'wevoi-summit-videos-top-three') . '</p>';
    }

    /**
     * Admin enqueue scripts
     */
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_wevoi-summit-videos-settings' === $hook) {
            wp_enqueue_style(
                'wevoi-summit-videos-admin-style',
                WEVOI_SUMMIT_VIDEOS_PLUGIN_URL . 'assets/css/admin-style.css',
                array(),
                WEVOI_SUMMIT_VIDEOS_VERSION
            );
        }
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('wevoi_summit_videos_messages', 'wevoi_summit_videos_message', __('Settings Saved', 'wevoi-summit-videos-top-three'), 'updated');
        }

        settings_errors('wevoi_summit_videos_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wevoi-admin-header">
                <h2><?php _e('WeVoi Summit Videos Top Three', 'wevoi-summit-videos-top-three'); ?></h2>
                <p><?php _e('This plugin displays the top-three videos from the most recent completed WeVoi Summit in a sliding carousel format.', 'wevoi-summit-videos-top-three'); ?></p>
            </div>

            <div class="wevoi-admin-content">
                <div class="wevoi-admin-main">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('wevoi_summit_videos_settings');
                        do_settings_sections('wevoi_summit_videos_settings');
                        submit_button(__('Save Settings', 'wevoi-summit-videos-top-three'));
                        ?>
                    </form>
                </div>

                <div class="wevoi-admin-sidebar">
                    <div class="wevoi-admin-box">
                        <h3><?php _e('Shortcode Usage', 'wevoi-summit-videos-top-three'); ?></h3>
                        <p><?php _e('Use this shortcode to display the summit videos carousel:', 'wevoi-summit-videos-top-three'); ?></p>
                        <code>[wevoi_summit_videos]</code>

                        <h4><?php _e('Parameters', 'wevoi-summit-videos-top-three'); ?></h4>
                        <ul>
                            <li><strong>competition_code</strong>: <?php _e('Specific competition code (optional)', 'wevoi-summit-videos-top-three'); ?></li>
                            <li><strong>show_title</strong>: <?php _e('Show competition title (true/false)', 'wevoi-summit-videos-top-three'); ?></li>
                            <li><strong>show_date</strong>: <?php _e('Show competition date (true/false)', 'wevoi-summit-videos-top-three'); ?></li>
                            <li><strong>autoplay</strong>: <?php _e('Enable autoplay (true/false)', 'wevoi-summit-videos-top-three'); ?></li>
                            <li><strong>speed</strong>: <?php _e('Autoplay speed in milliseconds', 'wevoi-summit-videos-top-three'); ?></li>
                            <li><strong>slides_per_view</strong>: <?php _e('Number of slides visible at once', 'wevoi-summit-videos-top-three'); ?></li>
                            <li><strong>space_between</strong>: <?php _e('Space between slides in pixels', 'wevoi-summit-videos-top-three'); ?></li>
                        </ul>

                        <h4><?php _e('Example', 'wevoi-summit-videos-top-three'); ?></h4>
                        <code>[wevoi_summit_videos autoplay="true" speed="4000" slides_per_view="3"]</code>
                    </div>

                    <div class="wevoi-admin-box">
                        <h3><?php _e('Plugin Information', 'wevoi-summit-videos-top-three'); ?></h3>
                        <p><strong><?php _e('Version:', 'wevoi-summit-videos-top-three'); ?></strong> <?php echo WEVOI_SUMMIT_VIDEOS_VERSION; ?></p>
                        <p><strong><?php _e('Author:', 'wevoi-summit-videos-top-three'); ?></strong> Kenan</p>
                        <p><strong><?php _e('Website:', 'wevoi-summit-videos-top-three'); ?></strong> <a href="https://wevoi.org" target="_blank">wevoi.org</a></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
