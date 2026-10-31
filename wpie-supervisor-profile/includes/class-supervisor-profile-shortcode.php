<?php
/**
 * Supervisor Profile Shortcode Class
 *
 * @package WPIE_Supervisor_Profile
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling supervisor profile shortcode
 */
class WPIE_Supervisor_Profile_Shortcode {

    /**
     * The shortcode tag
     *
     * @var string
     */
    private $shortcode_tag = 'wpie_supervisor_profile';

    /**
     * Register the shortcode
     */
    public function register() {
        add_shortcode($this->shortcode_tag, array($this, 'render_shortcode'));
    }

    /**
     * Render shortcode output
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_shortcode($atts) {
        // Enqueue required assets
        wp_enqueue_style('wpie-supervisor-profile');
        wp_enqueue_script('jquery');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_message();
        }

        // Check if current user is a supervisor
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;

        if (!in_array('supervisor', $user_roles)) {
            return $this->render_not_supervisor_message();
        }

        // Start output buffering
        ob_start();

        // Include template
        include WPIE_SUPERVISOR_PROFILE_PATH . 'templates/supervisor-profile.php';

        // Return buffered content
        return ob_get_clean();
    }

    /**
     * Render login message for non-logged in users
     *
     * @return string HTML message
     */
    private function render_login_message() {
        return '<div class="wpie-supervisor-profile-login-required">
            <h3>' . __('Login Required', 'wpie-supervisor-profile') . '</h3>
            <p>' . __('You need to log in to access your supervisor profile.', 'wpie-supervisor-profile') . '</p>
            <p><a href="' . wp_login_url(get_permalink()) . '" class="button">' . __('Log In', 'wpie-supervisor-profile') . '</a></p>
        </div>';
    }

    /**
     * Render message for non-supervisor users
     *
     * @return string HTML message
     */
    private function render_not_supervisor_message() {
        return '<div class="wpie-supervisor-profile-access-denied">
            <h3>' . __('Access Denied', 'wpie-supervisor-profile') . '</h3>
            <p>' . __('You need to be a supervisor to access this page.', 'wpie-supervisor-profile') . '</p>
        </div>';
    }
}
