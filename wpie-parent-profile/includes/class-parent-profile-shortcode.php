<?php
/**
 * Parent Profile Shortcode Class
 *
 * @package WPIE_Parent_Profile
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling parent profile shortcode
 */
class WPIE_Parent_Profile_Shortcode {

    /**
     * The shortcode tag
     *
     * @var string
     */
    private $shortcode_tag = 'wpie_parent_profile';

    /**
     * Register the shortcode
     */
    public function register() {
        add_shortcode($this->shortcode_tag, array($this, 'render_shortcode'));
        error_log('WPIE Parent Profile: Shortcode registration called for: ' . $this->shortcode_tag);
    }

    /**
     * Render shortcode output
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_shortcode($atts) {
        error_log('WPIE Parent Profile: render_shortcode called');

        // Enqueue required assets
        wp_enqueue_style('wpie-parent-profile');
        wp_enqueue_script('jquery');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            error_log('WPIE Parent Profile: User not logged in');
            return $this->render_login_message();
        }

        // Check if current user is a parent
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;

        error_log('WPIE Parent Profile: Current user roles: ' . implode(', ', $user_roles));

        if (!in_array('parent', $user_roles)) {
            error_log('WPIE Parent Profile: User is not a parent');
            return $this->render_not_parent_message();
        }

        // Check if template file exists
        $template_path = WPIE_PARENT_PROFILE_PATH . 'templates/parent-profile.php';
        if (!file_exists($template_path)) {
            error_log('WPIE Parent Profile: Template file not found: ' . $template_path);
            return '<div class="wpie-parent-profile-error"><p>Template file not found. Please contact administrator.</p></div>';
        }

        // Start output buffering
        ob_start();

        // Include template
        include $template_path;

        // Return buffered content
        $output = ob_get_clean();
        error_log('WPIE Parent Profile: Shortcode output length: ' . strlen($output));
        return $output;
    }

    /**
     * Render login message for non-logged in users
     *
     * @return string HTML message
     */
    private function render_login_message() {
        return '<div class="wpie-parent-profile-login-required">
            <h3>' . __('Login Required', 'wpie-parent-profile') . '</h3>
            <p>' . __('You need to log in to access your parent profile.', 'wpie-parent-profile') . '</p>
            <p><a href="' . wp_login_url(get_permalink()) . '" class="button">' . __('Log In', 'wpie-parent-profile') . '</a></p>
        </div>';
    }

    /**
     * Render message for non-parent users
     *
     * @return string HTML message
     */
    private function render_not_parent_message() {
        return '<div class="wpie-parent-profile-access-denied">
            <h3>' . __('Access Denied', 'wpie-parent-profile') . '</h3>
            <p>' . __('You need to be a parent to access this page.', 'wpie-parent-profile') . '</p>
        </div>';
    }
}
