<?php
/**
 * Supervisor Dashboard Class
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Supervisor
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling supervisor dashboard functionality
 */
class Wevoi_Supervisor_Dashboard {

    /**
     * Initialize the dashboard
     */
public function init() {
    // Add AJAX handlers
    add_action('wp_ajax_get_supervisor_students', array($this, 'get_supervisor_students'));
    add_action('wp_ajax_get_competition_results', array($this, 'get_competition_results'));
    add_action('wp_ajax_get_supervisor_certificates', array($this, 'get_supervisor_certificates'));
    add_action('wp_ajax_get_student_competitions', array($this, 'get_student_competitions'));
    add_action('wp_ajax_wevoi_update_visa_status', array($this, 'update_visa_status_ajax'));
}

/**
 * Get student competitions
 *
 * @return array Registration data grouped by competitions
 */
public function get_student_competitions() {
    // Check nonce
    check_ajax_referer('wevoi_supervisor_nonce', 'nonce');

    // Check if user is logged in and is a supervisor
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }

    $current_user = wp_get_current_user();
    if (!in_array('supervisor', $current_user->roles)) {
        wp_send_json_error('Not a supervisor');
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
 * Get supervisor data
 *
 * @param int $supervisor_id The supervisor ID (optional, uses current user if not provided)
 * @return array|WP_Error Supervisor data or error
 */
public function get_supervisor_data($supervisor_id = 0) {
    global $wpdb;

    // If no ID provided, use current user
    if (!$supervisor_id) {
        $supervisor_id = get_current_user_id();
    }

    // First, check if user exists and get basic data
    $user = get_userdata($supervisor_id);

    if (!$user) {
        return new WP_Error('not_found', __('Supervisor not found', 'wevoi-competition-manager'));
    }

    // Get country and school IDs from user meta
    $country_id = get_user_meta($supervisor_id, 'countryid', true);
    $school_id = get_user_meta($supervisor_id, 'schoolid', true);

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
    $supervisor = (object) array_merge(
        (array) $user->data,
        array(
            'countryid' => $country_id,
            'countryname' => $country_name,
            'schoolid' => $school_id,
            'schoolname' => $school_name
        )
    );

    return $supervisor;
}


    /**
     * Get students supervised by supervisor
     *
     * @param int $supervisor_id The supervisor ID (optional, uses current user if not provided)
     * @return array Students data
     */
    public function get_students($supervisor_id = 0) {
    global $wpdb;

    // If no ID provided, use current user
    if (!$supervisor_id) {
        $supervisor_id = get_current_user_id();
    }

    // Get supervisor email
    $supervisor_email = $wpdb->get_var($wpdb->prepare(
        "SELECT user_email FROM {$wpdb->users} WHERE ID = %d",
        $supervisor_id
    ));

    if (!$supervisor_email) {
        return array();
    }

    // Get students with this supervisor's email
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
         WHERE um.meta_key = 'supervisoremail'
         AND um.meta_value = %s
         AND um_role.meta_value LIKE '%\"student\"%'
         ORDER BY last_name.meta_value, first_name.meta_value",
        $supervisor_email
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
     * Get supervisor certificates
     *
     * @param int $supervisor_id The supervisor ID (optional, uses current user if not provided)
     * @return array Certificate data
     */
    public function get_certificates($supervisor_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$supervisor_id) {
            $supervisor_id = get_current_user_id();
        }

        // Check if certificates table exists
        $has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;

        if ($has_certificates_table) {
            // Get certificates from the dedicated table
            $certificates = $wpdb->get_results($wpdb->prepare(
                "SELECT cert.*, c.competition_name
                 FROM {$wpdb->prefix}wevoi_certificates cert
                 LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON cert.competition_code = c.competition_code
                 WHERE cert.user_id = %d AND cert.role = 'supervisor'
                 ORDER BY cert.issue_date DESC",
                $supervisor_id
            ));

            return $certificates;
        }

        // If no certificates table, try to get from registrations
        $supervisor_email = $wpdb->get_var($wpdb->prepare(
            "SELECT user_email FROM {$wpdb->users} WHERE ID = %d",
            $supervisor_id
        ));

        if (!$supervisor_email) {
            return array();
        }

        // Get unique competitions where student submitted videos
        $competitions = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT c.competition_code, c.competition_name, r.supervisor_certficates_folder
             FROM {$wpdb->prefix}wevoi_registrations r
             JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             JOIN {$wpdb->usermeta} um ON r.user_id = um.user_id
             WHERE um.meta_key = 'supervisoremail'
             AND um.meta_value = %s
             AND r.youtube_link IS NOT NULL
             ORDER BY c.competition_date DESC",
            $supervisor_email
        ));

        return $competitions;
    }

    /**
     * AJAX handler for getting supervisor students
     */
    public function get_supervisor_students() {
        // Check nonce
        check_ajax_referer('wevoi_supervisor_nonce', 'nonce');

        // Check if user is logged in and is a supervisor
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('supervisor', $current_user->roles)) {
            wp_send_json_error('Not a supervisor');
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
    check_ajax_referer('wevoi_supervisor_nonce', 'nonce');

    // Check if user is logged in and is a supervisor
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }

    $current_user = wp_get_current_user();
    if (!in_array('supervisor', $current_user->roles)) {
        wp_send_json_error('Not a supervisor');
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

    // Log for debugging
    error_log('Student IDs: ' . print_r($student_ids, true));

    // If still no student IDs, get all students for this supervisor
    if (empty($student_ids)) {
        $students = $this->get_students();
        if (!empty($students)) {
            $student_ids = wp_list_pluck($students, 'student_id');
            // Log for debugging
            error_log('Retrieved student IDs: ' . print_r($student_ids, true));
        }
    }

    // Get registrations
    $registrations = $this->get_student_registrations($student_ids);

    // Log for debugging
    error_log('Registration count: ' . count($registrations));
    if (!empty($registrations)) {
        // Check first item for claimed_summit_coupon_code
        error_log('First registration claimed_summit_coupon_code: ' .
                 (isset($registrations[0]->claimed_summit_coupon_code) ?
                 $registrations[0]->claimed_summit_coupon_code : 'Not set'));
    }

    wp_send_json_success($registrations);
}

    /**
     * AJAX handler for getting supervisor certificates
     */
    public function get_supervisor_certificates() {
        // Check nonce
        check_ajax_referer('wevoi_supervisor_nonce', 'nonce');

        // Check if user is logged in and is a supervisor
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('supervisor', $current_user->roles)) {
            wp_send_json_error('Not a supervisor');
            return;
        }

        // Get certificates
        $certificates = $this->get_certificates();

        wp_send_json_success($certificates);
    }

    /**
     * Get supervisor's own Forum registrations
     *
     * @param int $supervisor_id The supervisor ID (optional, uses current user if not provided)
     * @return array Registration data
     */
    public function get_own_forum_registrations($supervisor_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$supervisor_id) {
            $supervisor_id = get_current_user_id();
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

        $prepared_query = $wpdb->prepare($query, $supervisor_id);
        
        // Debug logging
        error_log('WeVoi Supervisor Dashboard: get_own_forum_registrations - Supervisor ID: ' . $supervisor_id);
        error_log('WeVoi Supervisor Dashboard: Prepared query: ' . $prepared_query);
        
        $registrations = $wpdb->get_results($prepared_query, OBJECT);
        
        // Debug logging
        error_log('WeVoi Supervisor Dashboard: Found ' . count($registrations) . ' forum registrations');
        if (!empty($registrations)) {
            error_log('WeVoi Supervisor Dashboard: First registration register_id: ' . $registrations[0]->register_id);
            error_log('WeVoi Supervisor Dashboard: First registration competition_name: ' . $registrations[0]->competition_name);
            error_log('WeVoi Supervisor Dashboard: First registration competition_type: ' . $registrations[0]->competition_type);
            error_log('WeVoi Supervisor Dashboard: First registration event_venue: ' . ($registrations[0]->event_venue ?? 'NULL'));
            error_log('WeVoi Supervisor Dashboard: First registration event_duration: ' . ($registrations[0]->event_duration ?? 'NULL'));
        } else {
            // Try alternative query to see if there are any registrations at all
            $alt_query = "SELECT r.*, c.competition_type, c.competition_name 
                         FROM {$wpdb->prefix}wevoi_registrations r
                         JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                         WHERE r.user_id = %d";
            $alt_results = $wpdb->get_results($wpdb->prepare($alt_query, $supervisor_id));
            error_log('WeVoi Supervisor Dashboard: Total registrations for user: ' . count($alt_results));
            if (!empty($alt_results)) {
                foreach ($alt_results as $alt_reg) {
                    error_log('WeVoi Supervisor Dashboard: Registration - register_id: ' . $alt_reg->register_id . ', competition_code: ' . $alt_reg->competition_code . ', competition_type: ' . ($alt_reg->competition_type ?? 'NULL') . ', competition_name: ' . ($alt_reg->competition_name ?? 'NULL'));
                }
            }
            
            // Try direct query without prepare to see if that works
            $direct_query = "SELECT r.*, c.competition_type, c.competition_name, c.event_venue, c.event_duration
                            FROM {$wpdb->prefix}wevoi_registrations r
                            JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                            WHERE r.user_id = {$supervisor_id}
                            AND c.competition_type = 'Forum'";
            $direct_results = $wpdb->get_results($direct_query, OBJECT);
            error_log('WeVoi Supervisor Dashboard: Direct query results count: ' . count($direct_results));
        }

        return $registrations;
    }

    /**
     * Update visa status via AJAX
     */
    public function update_visa_status_ajax() {
        check_ajax_referer('wevoi_supervisor_nonce', 'nonce');

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
