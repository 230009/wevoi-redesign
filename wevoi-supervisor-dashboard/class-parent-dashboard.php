<?php
/**
 * Parent Dashboard Class
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Parent
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling parent dashboard functionality
 */
class Wevoi_Parent_Dashboard {

    /**
     * Initialize the dashboard
     */
    public function init() {
        // Add AJAX handlers
        add_action('wp_ajax_get_parent_students', array($this, 'get_parent_students'));
        add_action('wp_ajax_get_parent_competition_results', array($this, 'get_competition_results'));
        add_action('wp_ajax_get_parent_certificates', array($this, 'get_parent_certificates'));
        add_action('wp_ajax_get_parent_student_competitions', array($this, 'get_student_competitions'));
        add_action('wp_ajax_wevoi_update_visa_status', array($this, 'update_visa_status_ajax'));
    }

    /**
     * Get student competitions
     *
     * @return array Registration data grouped by competitions
     */
    public function get_student_competitions() {
        // Check nonce
        check_ajax_referer('wevoi_parent_nonce', 'nonce');

        // Check if user is logged in and is a parent
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            wp_send_json_error('Not a parent');
            return;
        }

        // Get student IDs
        $student_ids = isset($_POST['student_ids']) ? $_POST['student_ids'] : array();

        // Sanitize student IDs
        if (is_array($student_ids)) {
            $student_ids = array_map('intval', $student_ids);
        } else {
            // If it's a comma-separated string, convert to array of integers
            $student_ids = array_map('intval', explode(',', $student_ids));
        }

        // Filter out any zero values
        $student_ids = array_filter($student_ids);

        if (empty($student_ids)) {
            wp_send_json_error('No student IDs provided');
            return;
        }

        // Get registrations
        $registrations = $this->get_student_registrations($student_ids);

        wp_send_json_success($registrations);
    }

    /**
     * Get parent data
     *
     * @param int $parent_id The parent ID (optional, uses current user if not provided)
     * @return array|WP_Error Parent data or error
     */
    public function get_parent_data($parent_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$parent_id) {
            $parent_id = get_current_user_id();
        }

        // First, check if user exists and get basic data
        $user = get_userdata($parent_id);

        if (!$user) {
            return new WP_Error('not_found', __('Parent not found', 'wevoi-competition-manager'));
        }

        // Get country and school IDs from user meta (if applicable)
        $country_id = get_user_meta($parent_id, 'countryid', true);
        $school_id = get_user_meta($parent_id, 'schoolid', true);

        // Get country name
        $country_name = '';
        if ($country_id) {
            $country_name = $wpdb->get_var($wpdb->prepare(
                "SELECT countryname FROM {$wpdb->prefix}wevoi_countries WHERE countryid = %s",
                $country_id
            ));
        }

        // Get school name
        $school_name = '';
        if ($school_id) {
            $school_name = $wpdb->get_var($wpdb->prepare(
                "SELECT schoolname FROM {$wpdb->prefix}wevoi_schools WHERE schoolid = %s",
                $school_id
            ));
        }

        // Combine all data
        $parent = (object) array_merge(
            (array) $user->data,
            array(
                'countryid' => $country_id,
                'countryname' => $country_name,
                'schoolid' => $school_id,
                'schoolname' => $school_name
            )
        );

        return $parent;
    }

    /**
     * Get students linked to parent
     *
     * @param int $parent_id The parent ID (optional, uses current user if not provided)
     * @return array Students data
     */
    public function get_students($parent_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$parent_id) {
            $parent_id = get_current_user_id();
        }

        // Get parent email
        $parent_email = $wpdb->get_var($wpdb->prepare(
            "SELECT user_email FROM {$wpdb->users} WHERE ID = %d",
            $parent_id
        ));

        if (!$parent_email) {
            return array();
        }

        // Get students with this parent's email
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT u.*,
                    um.user_id as student_id,
                    first_name.meta_value as first_name,
                    last_name.meta_value as last_name
             FROM {$wpdb->usermeta} um
             JOIN {$wpdb->users} u ON um.user_id = u.ID
             JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'
             LEFT JOIN {$wpdb->usermeta} first_name ON u.ID = first_name.user_id AND first_name.meta_key = 'first_name'
             LEFT JOIN {$wpdb->usermeta} last_name ON u.ID = last_name.user_id AND last_name.meta_key = 'last_name'
             WHERE um.meta_key = 'parentemail'
             AND um.meta_value = %s
             AND um_role.meta_value LIKE '%\"student\"%'
             ORDER BY last_name.meta_value, first_name.meta_value",
            $parent_email
        ));

        return $students;
    }

    /**
     * Get students competition registrations
     *
     * @param array $student_ids List of student IDs
     * @return array Registration data
     */
    public function get_student_registrations($student_ids) {
        global $wpdb;

        if (empty($student_ids)) {
            return array();
        }

        // Convert array to comma-separated string for IN clause
        $ids_str = implode(',', array_map('intval', $student_ids));

        // Check if certificates table exists
        $has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;

        // Get registrations
        $query = "SELECT r.*,
               u.user_email as student_email,
               u.display_name,
               um_first.meta_value as first_name,
               um_last.meta_value as last_name,
               ac.age_category,
               c.competition_name,
               c.award_ceremony_completed,
               r.claimed_summit_coupon_code";

        // Add certificate fields if table exists
        if ($has_certificates_table) {
            $query .= ",
               cert.id as certificate_id,
               cert.certificate_link,
               cert.issue_date as certificate_issue_date";
        }

        $query .= " FROM {$wpdb->prefix}wevoi_registrations r
        JOIN {$wpdb->users} u ON r.user_id = u.ID
        LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
        LEFT JOIN {$wpdb->prefix}wevoi_age_categories ac ON r.age_category_id = ac.id
        JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code";

        // Add LEFT JOIN for certificates table if it exists
        if ($has_certificates_table) {
            $query .= "
        LEFT JOIN {$wpdb->prefix}wevoi_certificates cert ON r.user_id = cert.user_id
            AND r.competition_code = cert.competition_code
            AND cert.role = 'student'";
        }

        $query .= " WHERE r.user_id IN ({$ids_str})
        AND c.competition_status = 'COMPLETED'
        AND c.award_ceremony_completed = 1
        ORDER BY c.competition_name, um_last.meta_value, um_first.meta_value";

        $registrations = $wpdb->get_results($query);

        // Process registrations to create full student names
        foreach ($registrations as $reg) {
            $first_name = !empty($reg->first_name) ? ucfirst(strtolower($reg->first_name)) : '';
            $last_name = !empty($reg->last_name) ? ucfirst(strtolower($reg->last_name)) : '';

            $full_name = trim(sprintf('%s %s', $first_name, $last_name));

            if (empty($full_name)) {
                $full_name = ucwords(strtolower($reg->display_name));
            }

            $reg->student_name = $full_name;
        }

        return $registrations;
    }

    /**
     * Get parent certificates
     *
     * @param int $parent_id The parent ID (optional, uses current user if not provided)
     * @return array Certificate data
     */
    public function get_certificates($parent_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$parent_id) {
            $parent_id = get_current_user_id();
        }

        // Check if certificates table exists
        $has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;

        if ($has_certificates_table) {
            // Get certificates from the dedicated table
            $certificates = $wpdb->get_results($wpdb->prepare(
                "SELECT cert.*, c.competition_name
                 FROM {$wpdb->prefix}wevoi_certificates cert
                 LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON cert.competition_code = c.competition_code
                 WHERE cert.user_id = %d AND cert.role = 'parent'
                 ORDER BY cert.issue_date DESC",
                $parent_id
            ));

            return $certificates;
        }

        // If no certificates table, return empty array for parents
        return array();
    }

    /**
     * AJAX handler for getting parent students
     */
    public function get_parent_students() {
        // Check nonce
        check_ajax_referer('wevoi_parent_nonce', 'nonce');

        // Check if user is logged in and is a parent
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            wp_send_json_error('Not a parent');
            return;
        }

        // Get students
        $students = $this->get_students();

        wp_send_json_success($students);
    }

    /**
     * AJAX handler for getting competition results
     */
    public function get_competition_results() {
        // Check nonce
        check_ajax_referer('wevoi_parent_nonce', 'nonce');

        // Check if user is logged in and is a parent
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            wp_send_json_error('Not a parent');
            return;
        }

        // Get student IDs
        $student_ids = isset($_POST['student_ids']) ? $_POST['student_ids'] : array();

        // Sanitize student IDs
        if (is_array($student_ids)) {
            $student_ids = array_map('intval', $student_ids);
        } else {
            // If it's a comma-separated string, convert to array of integers
            $student_ids = array_map('intval', explode(',', $student_ids));
        }

        // Filter out any zero values
        $student_ids = array_filter($student_ids);

        // If still no student IDs, get all students for this parent
        if (empty($student_ids)) {
            $students = $this->get_students();
            if (!empty($students)) {
                $student_ids = wp_list_pluck($students, 'student_id');
            }
        }

        // Get registrations
        $registrations = $this->get_student_registrations($student_ids);

        wp_send_json_success($registrations);
    }

    /**
     * AJAX handler for getting parent certificates
     */
    public function get_parent_certificates() {
        // Check nonce
        check_ajax_referer('wevoi_parent_nonce', 'nonce');

        // Check if user is logged in and is a parent
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            wp_send_json_error('Not a parent');
            return;
        }

        // Get certificates
        $certificates = $this->get_certificates();

        wp_send_json_success($certificates);
    }

    /**
     * Get parent's own Forum registrations
     *
     * @param int $parent_id The parent ID (optional, uses current user if not provided)
     * @return array Registration data
     */
    public function get_own_forum_registrations($parent_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$parent_id) {
            $parent_id = get_current_user_id();
        }

        $query = "SELECT r.*,
               c.competition_name,
               c.competition_date,
               c.competition_type,
               c.competition_status,
               c.event_venue,
               c.event_duration
         FROM {$wpdb->prefix}wevoi_registrations r
         JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
         WHERE r.user_id = %d
         AND UPPER(TRIM(c.competition_type)) = 'FORUM'
         ORDER BY c.competition_date DESC, c.competition_name";

        $registrations = $wpdb->get_results($wpdb->prepare($query, $parent_id));

        return $registrations;
    }

    /**
     * Update visa status via AJAX
     */
    public function update_visa_status_ajax() {
        check_ajax_referer('wevoi_parent_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
        $visa_status = isset($_POST['visa_status']) ? sanitize_text_field($_POST['visa_status']) : '';

        if (!$registration_id) {
            wp_send_json_error('Invalid registration ID');
            return;
        }

        // Verify that the registration belongs to the current user
        global $wpdb;
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wevoi_registrations WHERE register_id = %d AND user_id = %d",
            $registration_id,
            get_current_user_id()
        ));

        if (!$registration) {
            wp_send_json_error('Registration not found or access denied');
            return;
        }

        // Update visa status
        $result = $wpdb->update(
            $wpdb->prefix . 'wevoi_registrations',
            ['visa_status' => $visa_status],
            ['register_id' => $registration_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error('Failed to update visa status');
            return;
        }

        wp_send_json_success('Visa status updated successfully');
    }
}
