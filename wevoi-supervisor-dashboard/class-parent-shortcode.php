<?php
/**
 * Parent Shortcode Class
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Parent
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Class for handling parent dashboard shortcode
 */
class Wevoi_Parent_Shortcode {

    /**
     * The shortcode tag
     *
     * @var string
     */
    private $shortcode_tag = 'parent_dashboard';

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
        wp_enqueue_style('wevoi-supervisor-dashboard');
        wp_enqueue_script('wevoi-supervisor-dashboard');

        // Parse shortcode attributes
        $attributes = shortcode_atts(
            array(
                'show_certificates' => 'yes',
                'show_students' => 'yes',
                'show_results' => 'yes'
            ),
            $atts
        );

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_message();
        }

        // Check if current user is a parent
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;

        if (!in_array('parent', $user_roles)) {
            return $this->render_not_parent_message();
        }

        // Start output buffering
        ob_start();

        // Include template
        include WEVOI_SUPERVISOR_PLUGIN_PATH . 'parent-dashboard.php';

        // Return buffered content
        return ob_get_clean();
    }

    /**
     * Render login message for non-logged in users
     *
     * @return string HTML message
     */
    private function render_login_message() {
        return '<div class="wevoi-supervisor-login-required">
            <h3>' . __('Login Required', 'wevoi-competition-manager') . '</h3>
            <p>' . __('You need to log in to access your parent profile.', 'wevoi-competition-manager') . '</p>
            <p><a href="' . wp_login_url(get_permalink()) . '" class="button">' . __('Log In', 'wevoi-competition-manager') . '</a></p>
        </div>';
    }

    /**
     * Render message for non-parent users
     *
     * @return string HTML message
     */
    private function render_not_parent_message() {
        return '<div class="wevoi-supervisor-access-denied">
            <h3>' . __('Access Denied', 'wevoi-competition-manager') . '</h3>
            <p>' . __('You need to be a parent to access this dashboard.', 'wevoi-competition-manager') . '</p>
        </div>';
    }
}
