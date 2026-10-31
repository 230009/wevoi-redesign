<?php
/**
 * Parent Profile Class
 *
 * @package WPIE_Parent_Profile
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main class for handling parent profile data
 */
class WPIE_Parent_Profile {

    /**
     * Initialize the class
     */
    public function init() {
        // Add AJAX handlers
        add_action('wp_ajax_wevoi_update_visa_status_parent', array($this, 'update_visa_status_ajax'));
        add_action('wp_ajax_wevoi_save_flight_info_parent', array($this, 'save_flight_info_ajax'));
    }

    /**
     * Update visa status via AJAX
     */
    public function update_visa_status_ajax() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wevoi_parent_nonce')) {
            error_log('WeVoi Parent Profile: Nonce verification failed');
            wp_send_json_error('Security check failed');
            return;
        }

        if (!is_user_logged_in()) {
            error_log('WeVoi Parent Profile: User not logged in');
            wp_send_json_error('User not logged in');
            return;
        }

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
        $visa_status = isset($_POST['visa_status']) ? sanitize_text_field($_POST['visa_status']) : '';

        error_log('WeVoi Parent Profile: Update visa status - Registration ID: ' . $registration_id . ', Visa Status: ' . $visa_status);

        if (!$registration_id) {
            error_log('WeVoi Parent Profile: Invalid registration ID');
            wp_send_json_error('Invalid registration ID');
            return;
        }

        if (empty($visa_status)) {
            error_log('WeVoi Parent Profile: Visa status is empty');
            wp_send_json_error('Visa status is required');
            return;
        }

        global $wpdb;
        $user_id = get_current_user_id();

        // Verify that the registration belongs to the current user
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wevoi_registrations WHERE register_id = %d AND user_id = %d",
            $registration_id,
            $user_id
        ));

        if (!$registration) {
            error_log('WeVoi Parent Profile: Registration not found or access denied. Registration ID: ' . $registration_id . ', User ID: ' . $user_id);
            wp_send_json_error('Registration not found or access denied');
            return;
        }

        $comp_row = $wpdb->get_row($wpdb->prepare(
            "SELECT competition_type FROM {$wpdb->prefix}wevoi_competitions WHERE competition_code = %d",
            $registration->competition_code
        ));
        if ($comp_row && strtoupper(trim((string) $comp_row->competition_type)) === 'FORUM') {
            $ps = strtoupper((string) ($registration->paid_status ?? ''));
            if (!in_array($ps, array('PAID', 'FREE'), true)) {
                wp_send_json_error('Travel details are available after Forum payment is completed.');
                return;
            }
        }

        // Update visa status
        $updated = $wpdb->update(
            $wpdb->prefix . 'wevoi_registrations',
            array('visa_status' => $visa_status),
            array('register_id' => $registration_id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            error_log('WeVoi Parent Profile: Database update failed. Last error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to update visa status');
            return;
        }

        error_log('WeVoi Parent Profile: Visa status updated successfully. Rows affected: ' . $updated);
        wp_send_json_success('Visa status updated successfully');
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
            return new WP_Error('not_found', __('Parent not found', 'wpie-parent-profile'));
        }

        // Get country and school IDs from user meta
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
     * Get children (students) linked to parent
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
                    last_name.meta_value as last_name,
                    age_category_tbl.age_category as age_category,
                    gender.meta_value as gender
             FROM {$wpdb->usermeta} um
             JOIN {$wpdb->users} u ON um.user_id = u.ID
             JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'
             LEFT JOIN {$wpdb->usermeta} first_name ON u.ID = first_name.user_id AND first_name.meta_key = 'first_name'
             LEFT JOIN {$wpdb->usermeta} last_name ON u.ID = last_name.user_id AND last_name.meta_key = 'last_name'
             LEFT JOIN {$wpdb->usermeta} birth_date_meta ON u.ID = birth_date_meta.user_id AND birth_date_meta.meta_key = 'birth_date'
             LEFT JOIN {$wpdb->prefix}wevoi_age_categories age_category_tbl
                ON TIMESTAMPDIFF(YEAR, birth_date_meta.meta_value, CURDATE()) BETWEEN age_category_tbl.starting_age AND age_category_tbl.ending_age
             LEFT JOIN {$wpdb->usermeta} gender ON u.ID = gender.user_id AND gender.meta_key = 'gender'
             WHERE um.meta_key = 'parentemail'
             AND um.meta_value = %s
             AND um_role.meta_value LIKE '%\"student\"%'
             ORDER BY last_name.meta_value, first_name.meta_value",
            $parent_email
        ));

        return $students;
    }

    /**
     * Get students competition registrations with certificates
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
               c.competition_date,
               c.competition_type,
               c.award_ceremony_completed,
               r.claimed_summit_coupon_code,
               r.claimed_prize_amount,
               r.claimed_prize_paid_status";

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
            ORDER BY c.competition_name ASC, ac.age_category ASC, (r.student_rank IS NULL), CAST(r.student_rank AS UNSIGNED) ASC";

        $registrations = $wpdb->get_results($query);

        // Process registrations to create full student names
        foreach ($registrations as $reg) {
            $first_name = !empty($reg->first_name) ? mb_convert_case($reg->first_name, MB_CASE_TITLE, 'UTF-8') : '';
            $last_name = !empty($reg->last_name) ? mb_convert_case($reg->last_name, MB_CASE_TITLE, 'UTF-8') : '';

            $full_name = trim(sprintf('%s %s', $first_name, $last_name));

            if (empty($full_name)) {
                $full_name = mb_convert_case($reg->display_name, MB_CASE_TITLE, 'UTF-8');
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
                "SELECT cert.*, c.competition_name, c.competition_date
                 FROM {$wpdb->prefix}wevoi_certificates cert
                 LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON cert.competition_code = c.competition_code
                 WHERE cert.user_id = %d AND cert.role = 'parent'
                 ORDER BY cert.issue_date DESC",
                $parent_id
            ));

            return $certificates;
        }

        return array();
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
               c.event_duration,
               c.forum_payment_phase
         FROM {$wpdb->prefix}wevoi_registrations r
         JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
         WHERE r.user_id = %d
         AND UPPER(TRIM(c.competition_type)) = 'FORUM'
         ORDER BY c.competition_date DESC, c.competition_name";

        $registrations = $wpdb->get_results($wpdb->prepare($query, $parent_id), OBJECT);

        return $registrations;
    }

    /**
     * Get comprehensive statistics for parent
     *
     * @param int $parent_id The parent ID (optional, uses current user if not provided)
     * @return array Statistics data
     */
    public function get_statistics($parent_id = 0) {
        global $wpdb;

        if (!$parent_id) {
            $parent_id = get_current_user_id();
        }

        // Get parent email
        $parent_email = $wpdb->get_var($wpdb->prepare(
            "SELECT user_email FROM {$wpdb->users} WHERE ID = %d",
            $parent_id
        ));

        if (!$parent_email) {
            return $this->get_empty_statistics();
        }

        // Get student IDs
        $student_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT um.user_id
             FROM {$wpdb->usermeta} um
             JOIN {$wpdb->usermeta} um_role ON um.user_id = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'
             WHERE um.meta_key = 'parentemail'
             AND um.meta_value = %s
             AND um_role.meta_value LIKE '%\"student\"%'",
            $parent_email
        ));

        if (empty($student_ids)) {
            return $this->get_empty_statistics();
        }

        $ids_str = implode(',', array_map('intval', $student_ids));

        // Get all registrations
        $registrations = $wpdb->get_results(
            "SELECT r.*, c.competition_name, c.competition_type, c.competition_status, c.award_ceremony_completed, c.competition_date
             FROM {$wpdb->prefix}wevoi_registrations r
             JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             WHERE r.user_id IN ({$ids_str})"
        );

        // Get parent certificates count
        $has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;
        $parent_cert_count = 0;
        if ($has_certificates_table) {
            $parent_cert_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wevoi_certificates WHERE user_id = %d AND role = 'parent'",
                $parent_id
            ));
        }

        // Get student certificates count
        $student_cert_count = 0;
        if ($has_certificates_table) {
            $student_cert_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wevoi_certificates WHERE user_id IN ({$ids_str}) AND role = 'student'"
            );
        }

        // Calculate statistics
        $stats = array(
            'total_students' => count($student_ids),
            'total_registrations' => count($registrations),
            'total_parent_certificates' => (int) $parent_cert_count,
            'total_student_certificates' => (int) $student_cert_count,
            'total_certificates' => (int) $parent_cert_count + (int) $student_cert_count,
            'active_registrations' => 0,
            'video_submissions' => 0,
            'average_point' => 0,
            'highest_point' => 0,
            'success_rate' => 0,
            'gold_medals' => 0,
            'silver_medals' => 0,
            'bronze_medals' => 0,
            'success_certificates' => 0,
            'competition_type_stats' => array(
                'qualification' => 0,
                'final' => 0,
                'forum' => 0
            ),
            'application_status_stats' => array(
                'application_accepted' => 0,
                'pending_review' => 0,
                'video_unlisted' => 0,
                'video_inaccessible' => 0,
                'irrelevant_content' => 0,
                'application_rejected' => 0
            )
        );

        if (!empty($registrations)) {
            $points = array();
            $successful_registrations = 0;

            foreach ($registrations as $reg) {
                // Video submissions
                if (!empty($reg->youtube_link)) {
                    $stats['video_submissions']++;
                }

                // Application status
                $app_status = strtolower($reg->application_status);
                if (isset($stats['application_status_stats'][$app_status])) {
                    $stats['application_status_stats'][$app_status]++;
                }

                // Competition type
                $comp_type = strtolower($reg->competition_type);
                if ($comp_type === 'qualification') {
                    $stats['competition_type_stats']['qualification']++;
                } elseif ($comp_type === 'final') {
                    $stats['competition_type_stats']['final']++;
                } elseif ($comp_type === 'forum') {
                    $stats['competition_type_stats']['forum']++;
                }

                // Points and medals (only for completed competitions with results)
                if ($reg->competition_status === 'COMPLETED' && $reg->award_ceremony_completed == 1) {
                    $stats['active_registrations']++;

                    if (!empty($reg->student_point)) {
                        $points[] = (float) $reg->student_point;

                        if ($reg->student_point > $stats['highest_point']) {
                            $stats['highest_point'] = (float) $reg->student_point;
                        }
                    }

                    // Medals
                    if (!empty($reg->student_medal)) {
                        $medal = strtoupper($reg->student_medal);
                        if ($medal === 'GOLD') {
                            $stats['gold_medals']++;
                            $successful_registrations++;
                        } elseif ($medal === 'SILVER') {
                            $stats['silver_medals']++;
                            $successful_registrations++;
                        } elseif ($medal === 'BRONZE') {
                            $stats['bronze_medals']++;
                            $successful_registrations++;
                        } elseif ($medal === 'SUCCESS') {
                            $stats['success_certificates']++;
                            $successful_registrations++;
                        }
                    }
                }
            }

            // Calculate average point
            if (!empty($points)) {
                $stats['average_point'] = round(array_sum($points) / count($points), 2);
            }

            // Calculate success rate
            if ($stats['active_registrations'] > 0) {
                $stats['success_rate'] = round(($successful_registrations / $stats['active_registrations']) * 100, 2);
            }
        }

        return $stats;
    }

    /**
     * Get empty statistics structure
     *
     * @return array Empty statistics
     */
    private function get_empty_statistics() {
        return array(
            'total_students' => 0,
            'total_registrations' => 0,
            'total_parent_certificates' => 0,
            'total_student_certificates' => 0,
            'total_certificates' => 0,
            'active_registrations' => 0,
            'video_submissions' => 0,
            'average_point' => 0,
            'highest_point' => 0,
            'success_rate' => 0,
            'gold_medals' => 0,
            'silver_medals' => 0,
            'bronze_medals' => 0,
            'success_certificates' => 0,
            'competition_type_stats' => array(
                'qualification' => 0,
                'final' => 0,
                'forum' => 0
            ),
            'application_status_stats' => array(
                'application_accepted' => 0,
                'pending_review' => 0,
                'video_unlisted' => 0,
                'video_inaccessible' => 0,
                'irrelevant_content' => 0,
                'application_rejected' => 0
            )
        );
    }

    /**
     * Check if parent is registered for active Forum competition
     *
     * @param int $parent_id The parent ID (optional, uses current user if not provided)
     * @return bool True if registered, false otherwise
     */
    public function is_registered_for_active_forum($parent_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$parent_id) {
            $parent_id = get_current_user_id();
        }

        // Check if parent has registration for active Forum competition
        $registration_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}wevoi_registrations r
             JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             WHERE r.user_id = %d
             AND UPPER(c.competition_type) = 'FORUM'
             AND c.active_year = 1",
            $parent_id
        ));

        return $registration_count > 0;
    }

    /**
     * Get active Forum competition details
     *
     * @return object|null Active Forum competition data or null if not found
     */
    public function get_active_forum_competition() {
        global $wpdb;

        // Get active Forum competition
        $forum_competition = $wpdb->get_row(
            "SELECT competition_code, competition_name, competition_date, event_venue, event_duration
             FROM {$wpdb->prefix}wevoi_competitions
             WHERE UPPER(competition_type) = 'FORUM'
             AND competition_status = 'ON AIR'
             AND active_year = 1
             ORDER BY competition_date ASC
             LIMIT 1"
        );

        return $forum_competition;
    }

    /**
     * Get flight information for a registration
     *
     * @param int $registration_id The registration ID
     * @return object|null Flight information or null if not found
     */
    public function get_flight_info($registration_id) {
        global $wpdb;

        $flight_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wevoi_flight_info WHERE registration_id = %d",
            $registration_id
        ));

        return $flight_info;
    }

    /**
     * Save flight information via AJAX
     */
    public function save_flight_info_ajax() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wevoi_parent_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;

        if (!$registration_id) {
            wp_send_json_error('Invalid registration ID');
            return;
        }

        // Verify that the registration belongs to the current user
        global $wpdb;
        $user_id = get_current_user_id();
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wevoi_registrations WHERE register_id = %d AND user_id = %d",
            $registration_id,
            $user_id
        ));

        if (!$registration) {
            wp_send_json_error('Registration not found or access denied');
            return;
        }

        $comp_row = $wpdb->get_row($wpdb->prepare(
            "SELECT competition_type FROM {$wpdb->prefix}wevoi_competitions WHERE competition_code = %d",
            $registration->competition_code
        ));
        if ($comp_row && strtoupper(trim((string) $comp_row->competition_type)) === 'FORUM') {
            $ps = strtoupper((string) ($registration->paid_status ?? ''));
            if (!in_array($ps, array('PAID', 'FREE'), true)) {
                wp_send_json_error('Travel details are available after Forum payment is completed.');
                return;
            }
        }

        // Sanitize and prepare flight data
        $flight_data = array(
            'registration_id' => $registration_id,
            'arrival_flight_number' => isset($_POST['arrival_flight_number']) ? sanitize_text_field($_POST['arrival_flight_number']) : null,
            'arrival_airline' => isset($_POST['arrival_airline']) ? sanitize_text_field($_POST['arrival_airline']) : null,
            'arrival_airport_from' => isset($_POST['arrival_airport_from']) ? sanitize_text_field($_POST['arrival_airport_from']) : null,
            'arrival_airport_to' => isset($_POST['arrival_airport_to']) ? sanitize_text_field($_POST['arrival_airport_to']) : null,
            'arrival_date' => isset($_POST['arrival_date']) && !empty($_POST['arrival_date']) ? sanitize_text_field($_POST['arrival_date']) : null,
            'arrival_time' => isset($_POST['arrival_time']) && !empty($_POST['arrival_time']) ? sanitize_text_field($_POST['arrival_time']) : null,
            'return_flight_number' => isset($_POST['return_flight_number']) ? sanitize_text_field($_POST['return_flight_number']) : null,
            'return_airline' => isset($_POST['return_airline']) ? sanitize_text_field($_POST['return_airline']) : null,
            'return_airport_from' => isset($_POST['return_airport_from']) ? sanitize_text_field($_POST['return_airport_from']) : null,
            'return_airport_to' => isset($_POST['return_airport_to']) ? sanitize_text_field($_POST['return_airport_to']) : null,
            'return_departure_date' => isset($_POST['return_departure_date']) && !empty($_POST['return_departure_date']) ? sanitize_text_field($_POST['return_departure_date']) : null,
            'return_departure_time' => isset($_POST['return_departure_time']) && !empty($_POST['return_departure_time']) ? sanitize_text_field($_POST['return_departure_time']) : null,
        );

        // Check if flight info already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wevoi_flight_info WHERE registration_id = %d",
            $registration_id
        ));

        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $wpdb->prefix . 'wevoi_flight_info',
                $flight_data,
                array('registration_id' => $registration_id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $wpdb->prefix . 'wevoi_flight_info',
                $flight_data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }

        if ($result === false) {
            wp_send_json_error('Failed to save flight information');
            return;
        }

        wp_send_json_success('Flight information saved successfully');
    }

    /**
     * Get airports for a competition
     *
     * @param int $competition_code The competition code
     * @return array Airport data
     */
    public function get_competition_airports($competition_code) {
        global $wpdb;

        $airports = $wpdb->get_results($wpdb->prepare(
            "SELECT fa.*, c.countryname
             FROM {$wpdb->prefix}wevoi_flight_airports fa
             LEFT JOIN {$wpdb->prefix}wevoi_countries c ON fa.country_id = c.countryid
             WHERE fa.competition_code = %d
             AND fa.is_active = 1
             ORDER BY fa.airport_name ASC",
            $competition_code
        ));

        return $airports;
    }
}
