<?php
/**
 * Student Shortcode Class
 *
 * @package WeVoi_Student_Dashboard
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling student dashboard shortcode
 */
class Wevoi_Student_Shortcode {

    /**
     * The shortcode tag
     *
     * @var string
     */
    private $shortcode_tag = 'wevoi_student_dashboard';

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
        wp_enqueue_style('wevoi-student-dashboard');
        wp_enqueue_script('wevoi-student-dashboard');

        // Parse shortcode attributes
        $attributes = shortcode_atts(
            array(
                'show_certificates' => 'yes',
                'show_results' => 'yes',
                'show_registrations' => 'yes'
            ),
            $atts
        );

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_message();
        }

        // Check if current user is a student
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;

        if (!in_array('student', $user_roles)) {
            return $this->render_not_student_message();
        }

        // Start output buffering
        ob_start();

        // Include template
        include WEVOI_STUDENT_PLUGIN_PATH . 'dashboard.php';

        // Return buffered content
        return ob_get_clean();
    }

    /**
     * Render login message for non-logged in users
     *
     * @return string HTML message
     */
    private function render_login_message() {
        return '<div class="wevoi-student-login-required">
            <h3>' . __('Login Required', 'wevoi-student-dashboard') . '</h3>
            <p>' . __('You need to log in to access the student dashboard.', 'wevoi-student-dashboard') . '</p>
            <p><a href="' . wp_login_url(get_permalink()) . '" class="button">' . __('Log In', 'wevoi-student-dashboard') . '</a></p>
        </div>';
    }

    /**
     * Render message for non-student users
     *
     * @return string HTML message
     */
    private function render_not_student_message() {
        return '<div class="wevoi-student-access-denied">
            <h3>' . __('Access Denied', 'wevoi-student-dashboard') . '</h3>
            <p>' . __('You need to be a student to access this dashboard.', 'wevoi-student-dashboard') . '</p>
        </div>';
    }
}

