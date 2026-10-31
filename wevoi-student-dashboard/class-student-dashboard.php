<?php
/**
 * Student Dashboard Class
 *
 * @package WeVoi_Student_Dashboard
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling student dashboard functionality
 */
class Wevoi_Student_Dashboard {

    /**
     * Initialize the dashboard
     */
    public function init() {
        // Add AJAX handlers
        add_action('wp_ajax_get_student_registrations', array($this, 'get_student_registrations_ajax'));
        add_action('wp_ajax_get_student_results', array($this, 'get_student_results_ajax'));
        add_action('wp_ajax_get_student_certificates', array($this, 'get_student_certificates_ajax'));
        add_action('wp_ajax_wevoi_submit_bank_account', array($this, 'submit_bank_account_ajax'));
        add_action('wp_ajax_wevoi_get_student_bank_account', array($this, 'get_student_bank_account_ajax'));
        add_action('wp_ajax_wevoi_update_visa_status', array($this, 'update_visa_status_ajax'));
        add_action('wp_ajax_wevoi_save_flight_info', array($this, 'save_flight_info_ajax'));
    }

    /**
     * Ensure newer bank account columns exist (older installs).
     */
    private function ensure_bank_accounts_columns() {
        $this->ensure_bank_column('city', "ALTER TABLE {table} ADD COLUMN `city` varchar(255) DEFAULT NULL AFTER `countryid`");
        $this->ensure_bank_column('general_info', "ALTER TABLE {table} ADD COLUMN `general_info` text DEFAULT NULL AFTER `currencyid`");
    }

    /**
     * Add a missing column on the bank accounts table.
     */
    private function ensure_bank_column($column_name, $alter_sql) {
        global $wpdb;

        $table = $wpdb->prefix . 'wevoi_bank_accounts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $column = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column_name));
        if (empty($column)) {
            $wpdb->query(str_replace('{table}', $table, $alter_sql));
        }
    }

    /**
     * Get student data
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return object|WP_Error Student data or error
     */
    public function get_student_data($student_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        // Get user data
        $user = get_userdata($student_id);

        if (!$user) {
            return new WP_Error('not_found', __('Student not found', 'wevoi-student-dashboard'));
        }

        // Get country and school IDs from user meta
        $country_id = get_user_meta($student_id, 'countryid', true);
        $school_id = get_user_meta($student_id, 'schoolid', true);
        $supervisor_email = get_user_meta($student_id, 'supervisoremail', true);

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

        // Get supervisor name if supervisor email exists
        $supervisor_name = '';
        if ($supervisor_email) {
            $supervisor_user = get_user_by('email', $supervisor_email);
            if ($supervisor_user) {
                $supervisor_first_name = get_user_meta($supervisor_user->ID, 'first_name', true);
                $supervisor_last_name = get_user_meta($supervisor_user->ID, 'last_name', true);
                $supervisor_name = trim($supervisor_first_name . ' ' . $supervisor_last_name);
                if (empty($supervisor_name)) {
                    $supervisor_name = $supervisor_user->display_name;
                }
            }
        }

        // Combine all data
        $student = (object) array_merge(
            (array) $user->data,
            array(
                'countryid' => $country_id,
                'countryname' => $country_name,
                'schoolid' => $school_id,
                'schoolname' => $school_name,
                'supervisoremail' => $supervisor_email,
                'supervisorname' => $supervisor_name
            )
        );

        return $student;
    }

    /**
     * Get student registrations
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return array Registration data
     */
    public function get_registrations($student_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        // Check if certificates table exists
        $has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;

        // Build query
        $query = "SELECT r.*,
               c.competition_name,
               c.competition_date,
               c.competition_type,
               c.competition_status,
               c.active_year,
               c.award_ceremony_completed,
               c.award_ceremony_zoom_link,
               c.award_ceremony_date,
               c.event_venue,
               c.event_duration,
               c.forum_payment_phase,
               ac.age_category";

        // Add certificate fields if table exists
        if ($has_certificates_table) {
            $query .= ",
               cert.id as certificate_id,
               cert.certificate_link,
               cert.issue_date as certificate_issue_date";
        }

        $query .= ",
               sup_u.user_email as supervisor_email,
               TRIM(CONCAT(COALESCE(sup_first.meta_value,''), ' ', COALESCE(sup_last.meta_value,''))) as supervisor_name
            FROM {$wpdb->prefix}wevoi_registrations r
            JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
            LEFT JOIN {$wpdb->prefix}wevoi_age_categories ac ON r.age_category_id = ac.id
            LEFT JOIN {$wpdb->prefix}users sup_u ON r.supervisor_id = sup_u.ID
            LEFT JOIN {$wpdb->prefix}usermeta sup_first ON sup_u.ID = sup_first.user_id AND sup_first.meta_key = 'first_name'
            LEFT JOIN {$wpdb->prefix}usermeta sup_last ON sup_u.ID = sup_last.user_id AND sup_last.meta_key = 'last_name'";

        // Add LEFT JOIN for certificates table if it exists
        if ($has_certificates_table) {
            $query .= "
            LEFT JOIN {$wpdb->prefix}wevoi_certificates cert ON r.user_id = cert.user_id
                AND r.competition_code = cert.competition_code
                AND cert.role = 'student'";
        }

        $query .= " WHERE r.user_id = %d
            ORDER BY c.competition_date DESC, c.competition_name";

        $registrations = $wpdb->get_results($wpdb->prepare($query, $student_id));

        return $registrations;
    }

    /**
     * Get student competition results
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return array Results data
     */
    public function get_results($student_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        // Check if certificates table exists
        $has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;

        // Get registrations with results
        $query = "SELECT r.*,
               c.competition_name,
               c.competition_date,
               c.competition_type,
               c.competition_status,
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
            JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code";

        // Add LEFT JOIN for certificates table if it exists
        if ($has_certificates_table) {
            $query .= "
            LEFT JOIN {$wpdb->prefix}wevoi_certificates cert ON r.user_id = cert.user_id
                AND r.competition_code = cert.competition_code
                AND cert.role = 'student'";
        }

        $query .= " WHERE r.user_id = %d
            AND c.competition_status = 'COMPLETED'
            AND c.award_ceremony_completed = 1
            ORDER BY c.competition_date DESC, c.competition_name";

        $results = $wpdb->get_results($wpdb->prepare($query, $student_id));

        return $results;
    }

    /**
     * Get student statistics
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return array Statistics data
     */
    public function get_statistics($student_id = 0) {
        // If no ID provided, use current user
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        $registrations = $this->get_registrations($student_id);
        $results = $this->get_results($student_id);
        $certificates = $this->get_certificates($student_id);

        // Calculate statistics
        $stats = array(
            'total_competitions' => count($registrations),
            'completed_competitions' => count($results),
            'total_certificates' => count($certificates),
            'total_medals' => 0,
            'gold_medals' => 0,
            'silver_medals' => 0,
            'bronze_medals' => 0,
            'total_points' => 0,
            'best_rank' => null,
            'videos_submitted' => 0,
            'pending_videos' => 0
        );

        // Count medals and points
        foreach ($results as $result) {
            if (!empty($result->student_medal)) {
                $stats['total_medals']++;
                $medal_lower = strtolower($result->student_medal);
                if ($medal_lower === 'gold') {
                    $stats['gold_medals']++;
                } elseif ($medal_lower === 'silver') {
                    $stats['silver_medals']++;
                } elseif ($medal_lower === 'bronze') {
                    $stats['bronze_medals']++;
                }
            }

            if (!empty($result->student_point)) {
                $stats['total_points'] += floatval($result->student_point);
            }

            if (!empty($result->student_rank)) {
                $rank = intval($result->student_rank);
                if ($stats['best_rank'] === null || $rank < $stats['best_rank']) {
                    $stats['best_rank'] = $rank;
                }
            }
        }

        // Count videos
        foreach ($registrations as $reg) {
            if (!empty($reg->youtube_link)) {
                $stats['videos_submitted']++;
            } else {
                $stats['pending_videos']++;
            }
        }

        return $stats;
    }

    /**
     * Get upcoming competitions
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return array Upcoming competitions
     */
    public function get_upcoming_competitions($student_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        // Get student's registered competition codes
        $registered_codes = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT competition_code
             FROM {$wpdb->prefix}wevoi_registrations
             WHERE user_id = %d",
            $student_id
        ));

        // Get upcoming/active competitions
        $upcoming = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*,
                    CASE WHEN r.user_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
             FROM {$wpdb->prefix}wevoi_competitions c
             LEFT JOIN {$wpdb->prefix}wevoi_registrations r
                ON c.competition_code = r.competition_code
                AND r.user_id = %d
             WHERE c.competition_status IN ('ACTIVE', 'UPCOMING', 'REGISTRATION_OPEN')
             ORDER BY c.competition_date ASC
             LIMIT 10",
            $student_id
        ));

        return $upcoming;
    }

    /**
     * Get student certificates
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return array Certificate data
     */
    public function get_certificates($student_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        // Check if certificates table exists
        $has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;

        if ($has_certificates_table) {
            // Only expose certificates for competitions that are fully completed (same rule as student-facing access).
            $certificates = $wpdb->get_results($wpdb->prepare(
                "SELECT cert.*, c.competition_name
                 FROM {$wpdb->prefix}wevoi_certificates cert
                 INNER JOIN {$wpdb->prefix}wevoi_competitions c ON cert.competition_code = c.competition_code
                 WHERE cert.user_id = %d AND cert.role = 'student'
                 AND c.competition_status = 'COMPLETED'
                 ORDER BY cert.issue_date DESC",
                $student_id
            ));

            return $certificates;
        }

        // If no certificates table, get from registrations
        $certificates = $wpdb->get_results($wpdb->prepare(
            "SELECT r.competition_code,
                    c.competition_name,
                    r.certificate_link,
                    c.competition_date
             FROM {$wpdb->prefix}wevoi_registrations r
             JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             WHERE r.user_id = %d
             AND r.certificate_link IS NOT NULL
             AND r.certificate_link != ''
             AND c.competition_status = 'COMPLETED'
             ORDER BY c.competition_date DESC",
            $student_id
        ));

        return $certificates;
    }

    /**
     * AJAX handler for getting student registrations
     */
    public function get_student_registrations_ajax() {
        // Check nonce
        check_ajax_referer('wevoi_student_nonce', 'nonce');

        // Check if user is logged in and is a student
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('student', $current_user->roles)) {
            wp_send_json_error('Not a student');
            return;
        }

        // Get registrations
        $registrations = $this->get_registrations();

        wp_send_json_success($registrations);
    }

    /**
     * AJAX handler for getting student results
     */
    public function get_student_results_ajax() {
        // Check nonce
        check_ajax_referer('wevoi_student_nonce', 'nonce');

        // Check if user is logged in and is a student
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('student', $current_user->roles)) {
            wp_send_json_error('Not a student');
            return;
        }

        // Get results
        $results = $this->get_results();

        wp_send_json_success($results);
    }

    /**
     * AJAX handler for getting student certificates
     */
    public function get_student_certificates_ajax() {
        // Check nonce
        check_ajax_referer('wevoi_student_nonce', 'nonce');

        // Check if user is logged in and is a student
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('student', $current_user->roles)) {
            wp_send_json_error('Not a student');
            return;
        }

        // Get certificates
        $certificates = $this->get_certificates();

        wp_send_json_success($certificates);
    }

    /**
     * Summit banner: show only in the active season, after Spark/Reload qualification,
     * until the student has submitted a WeVoi Summit video for that season.
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return array Summit qualification data
     */
    public function check_summit_qualification($student_id = 0) {
        global $wpdb;

        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        $qualification_data = array(
            'qualified' => false,
            'has_free_coupon' => false,
            'qualification_competitions' => array(),
            'free_coupon_competitions' => array()
        );

        $season_id = $this->get_active_season_id();
        if ($season_id <= 0) {
            return $qualification_data;
        }

        // Banner is only for students still preparing Summit, not after they join it.
        if ($this->has_summit_participation_in_season($student_id, $season_id)) {
            return $qualification_data;
        }

        $spark_like = '%' . $wpdb->esc_like('spark') . '%';
        $reload_like = '%' . $wpdb->esc_like('reload') . '%';

        $qualification_registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*,
               c.competition_name,
               c.competition_type,
               c.competition_date,
               c.season_id,
               ac.age_category
             FROM {$wpdb->prefix}wevoi_registrations r
             JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             LEFT JOIN {$wpdb->prefix}wevoi_age_categories ac ON r.age_category_id = ac.id
             WHERE r.user_id = %d
             AND r.application_status = 'APPLICATION_ACCEPTED'
             AND c.season_id = %d
             AND c.competition_type = 'Qualification'
             AND (LOWER(c.competition_name) LIKE %s OR LOWER(c.competition_name) LIKE %s)
             ORDER BY c.competition_date DESC",
            $student_id,
            $season_id,
            $spark_like,
            $reload_like
        ));

        if (empty($qualification_registrations)) {
            return $qualification_data;
        }

        $qualification_data['qualified'] = true;

        foreach ($qualification_registrations as $reg) {
            $comp_data = array(
                'competition_name' => $reg->competition_name,
                'competition_code' => $reg->competition_code,
                'age_category' => $reg->age_category,
                'rank' => $reg->student_rank ?? null,
                'coupon_code' => $reg->claimed_summit_coupon_code ?? null,
                'competition_date' => $reg->competition_date
            );

            $qualification_data['qualification_competitions'][] = $comp_data;

            if (!empty($reg->student_rank) && intval($reg->student_rank) <= 5 && !empty($reg->claimed_summit_coupon_code)) {
                $qualification_data['has_free_coupon'] = true;
                $qualification_data['free_coupon_competitions'][] = $comp_data;
            }
        }

        return $qualification_data;
    }

    /**
     * Active season id from wevoi_seasons (is_active = 1).
     *
     * @return int
     */
    private function get_active_season_id() {
        global $wpdb;

        $table = $wpdb->prefix . 'wevoi_seasons';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT season_id FROM {$table} WHERE is_active = 1 LIMIT 1");
    }

    /**
     * True when the student already registered for WeVoi Summit in this season (video submitted).
     *
     * @param int $student_id
     * @param int $season_id
     * @return bool
     */
    private function has_summit_participation_in_season($student_id, $season_id) {
        global $wpdb;

        $summit_like = '%' . $wpdb->esc_like('summit') . '%';

        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT 1
             FROM {$wpdb->prefix}wevoi_registrations r
             JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             WHERE r.user_id = %d
             AND c.season_id = %d
             AND (c.competition_type = 'Final' OR LOWER(c.competition_name) LIKE %s)
             AND r.youtube_link IS NOT NULL
             AND r.youtube_link != ''
             LIMIT 1",
            $student_id,
            $season_id,
            $summit_like
        ));

        return !empty($found);
    }

    /**
     * Open video competitions in the active season the student has not submitted a video for.
     *
     * Forum events are excluded. WeVoi Summit (Final) is included only when the student
     * is eligible to register for it.
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return array List of competition rows
     */
    public function get_open_competitions_awaiting_video($student_id = 0) {
        global $wpdb;

        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        $season_id = $this->get_active_season_id();
        if ($season_id <= 0) {
            return array();
        }

        $competitions = $wpdb->get_results($wpdb->prepare(
            "SELECT c.competition_code, c.competition_name, c.competition_date, c.competition_type, c.season_id
             FROM {$wpdb->prefix}wevoi_competitions c
             WHERE c.season_id = %d
             AND c.competition_status = 'ON AIR'
             AND UPPER(c.competition_type) <> 'FORUM'
             AND NOT EXISTS (
                 SELECT 1
                 FROM {$wpdb->prefix}wevoi_registrations r
                 WHERE r.user_id = %d
                 AND r.competition_code = c.competition_code
                 AND r.youtube_link IS NOT NULL
                 AND TRIM(r.youtube_link) <> ''
             )
             ORDER BY c.competition_date ASC",
            $season_id,
            $student_id
        ));

        if (empty($competitions)) {
            return array();
        }

        if (!class_exists('WeVoi_Summit_Eligibility') && defined('WEVOI_REGISTER_PLUGIN_DIR')) {
            $eligibility_file = WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-wevoi-summit-eligibility.php';
            if (file_exists($eligibility_file)) {
                require_once $eligibility_file;
            }
        }

        $awaiting = array();
        foreach ($competitions as $competition) {
            if (class_exists('WeVoi_Summit_Eligibility')
                && !WeVoi_Summit_Eligibility::user_may_register_for_competition($wpdb, $student_id, $competition)
            ) {
                continue;
            }
            $awaiting[] = $competition;
        }

        return $awaiting;
    }

    /**
     * Get the current student's bank account details for a registration.
     */
    public function get_student_bank_account_ajax() {
        check_ajax_referer('wevoi_student_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('User not logged in', 'wevoi-student-dashboard'));
            return;
        }

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
        if (!$registration_id) {
            wp_send_json_error(__('Invalid registration ID', 'wevoi-student-dashboard'));
            return;
        }

        $this->ensure_bank_accounts_columns();

        global $wpdb;
        $account = $wpdb->get_row($wpdb->prepare(
            "SELECT ba.account_holder_name, ba.bank_name, ba.account_number, ba.sort_code,
                    ba.iban, ba.swift_code, ba.countryid, ba.city, ba.currencyid, ba.general_info
             FROM {$wpdb->prefix}wevoi_bank_accounts ba
             INNER JOIN {$wpdb->prefix}wevoi_registrations r ON ba.registration_id = r.register_id
             WHERE ba.registration_id = %d AND r.user_id = %d",
            $registration_id,
            get_current_user_id()
        ));

        if (!$account) {
            wp_send_json_error(__('Bank account details not found', 'wevoi-student-dashboard'));
            return;
        }

        wp_send_json_success($account);
    }

    /**
     * Submit or update bank account details for a prize-winning registration.
     */
    public function submit_bank_account_ajax() {
        check_ajax_referer('wevoi_student_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('User not logged in', 'wevoi-student-dashboard'));
            return;
        }

        $user_id = get_current_user_id();
        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;

        if (!$registration_id) {
            wp_send_json_error(__('Invalid registration ID', 'wevoi-student-dashboard'));
            return;
        }

        global $wpdb;

        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT r.register_id, r.user_id, r.competition_code, r.claimed_prize_amount, r.claimed_prize_paid_status
             FROM {$wpdb->prefix}wevoi_registrations r
             WHERE r.register_id = %d AND r.user_id = %d",
            $registration_id,
            $user_id
        ));

        if (!$registration) {
            wp_send_json_error(__('Registration not found or access denied', 'wevoi-student-dashboard'));
            return;
        }

        if (empty($registration->claimed_prize_amount) || floatval($registration->claimed_prize_amount) <= 0) {
            wp_send_json_error(__('No cash prize found for this registration', 'wevoi-student-dashboard'));
            return;
        }

        if (!empty($registration->claimed_prize_paid_status) && strtoupper($registration->claimed_prize_paid_status) === 'PAID') {
            wp_send_json_error(__('Prize has already been paid. Bank details cannot be changed.', 'wevoi-student-dashboard'));
            return;
        }

        $this->ensure_bank_accounts_columns();

        $required_fields = array('account_holder_name', 'bank_name', 'account_number', 'countryid', 'currencyid', 'city');
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
                wp_send_json_error(__('Please fill in all required fields', 'wevoi-student-dashboard'));
                return;
            }
        }

        $account_data = array(
            'user_id' => $user_id,
            'competition_code' => $registration->competition_code,
            'registration_id' => $registration_id,
            'account_holder_name' => sanitize_text_field($_POST['account_holder_name']),
            'bank_name' => sanitize_text_field($_POST['bank_name']),
            'account_number' => sanitize_text_field($_POST['account_number']),
            'sort_code' => sanitize_text_field(isset($_POST['sort_code']) ? $_POST['sort_code'] : ''),
            'iban' => sanitize_text_field(isset($_POST['iban']) ? $_POST['iban'] : ''),
            'swift_code' => sanitize_text_field(isset($_POST['swift_code']) ? $_POST['swift_code'] : ''),
            'countryid' => intval($_POST['countryid']),
            'city' => sanitize_text_field($_POST['city']),
            'currencyid' => intval($_POST['currencyid']),
            'general_info' => sanitize_textarea_field(isset($_POST['general_info']) ? $_POST['general_info'] : ''),
            'status' => 'PENDING'
        );

        $formats = array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s');

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wevoi_bank_accounts WHERE registration_id = %d",
            $registration_id
        ));

        if ($existing_id) {
            $result = $wpdb->update(
                $wpdb->prefix . 'wevoi_bank_accounts',
                $account_data,
                array('registration_id' => $registration_id),
                $formats,
                array('%d')
            );
            $message = __('Bank account details updated successfully!', 'wevoi-student-dashboard');
        } else {
            $result = $wpdb->insert(
                $wpdb->prefix . 'wevoi_bank_accounts',
                $account_data,
                $formats
            );
            $message = __('Bank account details submitted successfully!', 'wevoi-student-dashboard');
        }

        if ($result === false) {
            wp_send_json_error(__('Error saving bank account details. Please try again.', 'wevoi-student-dashboard'));
            return;
        }

        wp_send_json_success(array('message' => $message));
    }

    /**
     * Update visa status via AJAX
     */
    public function update_visa_status_ajax() {
        check_ajax_referer('wevoi_student_nonce', 'nonce');

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

    /**
     * Get Forum coupon information for the student
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return array|null Forum coupon data or null if not found
     */
    public function get_forum_coupon_info($student_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        // Get claimed forum coupon code from registrations table
        $forum_coupon_code = $wpdb->get_var($wpdb->prepare(
            "SELECT claimed_forum_coupon_code
             FROM {$wpdb->prefix}wevoi_registrations
             WHERE user_id = %d
             AND claimed_forum_coupon_code IS NOT NULL
             AND claimed_forum_coupon_code != ''
             LIMIT 1",
            $student_id
        ));

        // If no coupon code found, return null
        if (empty($forum_coupon_code)) {
            return null;
        }

        // Get coupon discount information from coupons table
        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT coupon_discount_amount, coupon_discount_percentage
             FROM {$wpdb->prefix}wevoi_coupons
             WHERE coupon_code = %s
             LIMIT 1",
            $forum_coupon_code
        ));

        if (!$coupon) {
            // Coupon code exists in registrations but not in coupons table
            return array(
                'coupon_code' => $forum_coupon_code,
                'discount_type' => null,
                'discount_value' => null
            );
        }

        // Determine discount type and value
        $discount_type = null;
        $discount_value = null;

        if (!empty($coupon->coupon_discount_percentage) && $coupon->coupon_discount_percentage !== null) {
            // Percentage discount
            $discount_type = 'percentage';
            $discount_value = intval($coupon->coupon_discount_percentage);
        } elseif (!empty($coupon->coupon_discount_amount) && $coupon->coupon_discount_amount !== null) {
            // Fixed amount discount
            $discount_type = 'amount';
            $discount_value = floatval($coupon->coupon_discount_amount);
        }

        return array(
            'coupon_code' => $forum_coupon_code,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value
        );
    }

    /**
     * Check if student is registered for active Forum competition
     *
     * @param int $student_id The student ID (optional, uses current user if not provided)
     * @return bool True if registered, false otherwise
     */
    public function is_registered_for_active_forum($student_id = 0) {
        global $wpdb;

        // If no ID provided, use current user
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        // Check if student has registration for active Forum competition
        $registration_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}wevoi_registrations r
             JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             WHERE r.user_id = %d
             AND UPPER(c.competition_type) = 'FORUM'
             AND c.active_year = 1",
            $student_id
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
        check_ajax_referer('wevoi_student_nonce', 'nonce');

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
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wevoi_registrations WHERE register_id = %d AND user_id = %d",
            $registration_id,
            get_current_user_id()
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

    /**
     * Registrations eligible for medal stickers (accepted application, completed competition, medal awarded).
     *
     * @param int $student_id User ID.
     * @return array<int, object> List of row objects.
     */
    public function get_medal_sticker_registrations($student_id = 0) {
        global $wpdb;

        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        $sql = "SELECT r.register_id,
                       r.competition_code,
                       r.student_medal,
                       c.competition_name,
                       c.competition_date,
                       ac.age_category
                FROM {$wpdb->prefix}wevoi_registrations r
                INNER JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                LEFT JOIN {$wpdb->prefix}wevoi_age_categories ac ON r.age_category_id = ac.id
                WHERE r.user_id = %d
                  AND r.application_status = 'APPLICATION_ACCEPTED'
                  AND c.competition_status = 'COMPLETED'
                  AND r.student_medal IS NOT NULL
                  AND r.student_medal != ''
                ORDER BY c.competition_date DESC, c.competition_name ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $student_id));
    }

    /**
     * Competition code for sticker filenames (digits only). Season 25–26 may use 3 digits (e.g. 261); 24–25 uses 4 (e.g. 2559).
     *
     * @param object $row Row from get_medal_sticker_registrations().
     * @return string Empty if invalid.
     */
    public function get_medal_sticker_competition_code_string($row) {
        if (!isset($row->competition_code)) {
            return '';
        }
        $digits = preg_replace('/\D/', '', (string) $row->competition_code);
        if ($digits === '' || !ctype_digit($digits) || (int) $digits < 1) {
            return '';
        }

        return apply_filters('wevoi_medal_sticker_competition_code_string', $digits, $row);
    }

    /**
     * Build medal sticker asset basename: {competition_code}-{medal}-{age}.png (all lowercase medal and age slug).
     *
     * @param object $row Row from get_medal_sticker_registrations().
     * @return string Empty if invalid.
     */
    public function get_medal_sticker_basename($row) {
        $code = $this->get_medal_sticker_competition_code_string($row);
        if ($code === '') {
            return '';
        }

        $medal = strtolower(trim((string) ($row->student_medal ?? '')));
        if (!in_array($medal, array('gold', 'silver', 'bronze', 'success'), true)) {
            return '';
        }

        $age_raw = isset($row->age_category) ? (string) $row->age_category : '';
        $age     = strtolower(preg_replace('/\s+/', '', $age_raw));
        if ($age === '') {
            return '';
        }

        $basename = $code . '-' . $medal . '-' . $age . '.png';

        return apply_filters('wevoi_medal_sticker_basename', $basename, $row);
    }

    /**
     * Find a readable sticker PNG on disk (case-insensitive basename; optional single-file fallback when age slug is missing).
     *
     * @param object $row Row from get_medal_sticker_registrations().
     * @return string Full path or empty string.
     */
    public function resolve_medal_sticker_file_path($row) {
        $dir = trailingslashit(WEVOI_STUDENT_PLUGIN_PATH . 'assets/images');
        if (!is_dir($dir)) {
            return '';
        }

        $basename = $this->get_medal_sticker_basename($row);
        if ($basename !== '') {
            $basename = basename($basename);
            if ($basename === '' || stripos($basename, '.png') === false || preg_match('#[/\\\\]#', $basename) || strpos($basename, '..') !== false) {
                return '';
            }

            $primary = $dir . $basename;
            if (is_readable($primary)) {
                return $primary;
            }

            $want = strtolower($basename);
            foreach (scandir($dir) ?: array() as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                if (!preg_match('/\.png$/i', $name)) {
                    continue;
                }
                if (strtolower($name) !== $want) {
                    continue;
                }
                $full = $dir . $name;
                if (is_readable($full)) {
                    return $full;
                }
            }
        }

        // Age category missing from join: if exactly one PNG matches {code}-{medal}-*.png, use it.
        $code  = $this->get_medal_sticker_competition_code_string($row);
        $medal = strtolower(trim((string) ($row->student_medal ?? '')));
        if ($code === '' || !in_array($medal, array('gold', 'silver', 'bronze', 'success'), true)) {
            return '';
        }

        $age_raw = isset($row->age_category) ? (string) $row->age_category : '';
        $age     = strtolower(preg_replace('/\s+/', '', $age_raw));
        if ($age !== '') {
            return '';
        }

        $prefix = $code . '-' . $medal . '-';
        $hits   = array();
        foreach (scandir($dir) ?: array() as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (!preg_match('/\.png$/i', $name)) {
                continue;
            }
            if (stripos($name, $prefix) !== 0) {
                continue;
            }
            $full = $dir . $name;
            if (is_readable($full)) {
                $hits[] = $full;
            }
        }

        if (count($hits) === 1) {
            return $hits[0];
        }

        return '';
    }

    /**
     * Absolute filesystem path to a medal sticker PNG under assets/images/.
     *
     * @param string $basename File name only, e.g. 261-bronze-juniorvoi.png or 2559-bronze-juniorvoi.png (3- or 4-digit code).
     * @return string
     */
    public function get_medal_sticker_asset_path($basename) {
        $basename = basename((string) $basename);
        if ($basename === '' || !preg_match('/\.png$/i', $basename) || preg_match('#[/\\\\]#', $basename) || strpos($basename, '..') !== false) {
            return '';
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $basename)) {
            return '';
        }

        return WEVOI_STUDENT_PLUGIN_PATH . 'assets/images/' . $basename;
    }

    /**
     * Public URL for a medal sticker asset (with cache-busting when file exists).
     *
     * @param string $basename File name only.
     * @return string
     */
    public function get_medal_sticker_asset_url($basename) {
        $basename = basename((string) $basename);
        if ($basename === '' || !preg_match('/\.png$/i', $basename) || preg_match('#[/\\\\]#', $basename) || strpos($basename, '..') !== false) {
            return '';
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $basename)) {
            return '';
        }

        $url  = WEVOI_STUDENT_PLUGIN_URL . 'assets/images/' . $basename;
        $path = WEVOI_STUDENT_PLUGIN_PATH . 'assets/images/' . basename($basename);
        if (is_readable($path)) {
            $url = add_query_arg('ver', (string) filemtime($path), $url);
        }

        return apply_filters('wevoi_medal_sticker_asset_url', $url, $basename);
    }

    /**
     * Sticker rows that have a matching PNG under assets/images/ (flat layout).
     *
     * @param int $student_id User ID.
     * @return array<int, object> Each object: register_id, competition_name, sticker_basename, sticker_url, download_url, download_filename.
     */
    public function get_medal_sticker_display_items($student_id = 0) {
        if (!$student_id) {
            $student_id = get_current_user_id();
        }

        $rows  = $this->get_medal_sticker_registrations($student_id);
        $items = array();

        foreach ($rows as $row) {
            $path = $this->resolve_medal_sticker_file_path($row);
            if ($path === '' || !is_readable($path)) {
                continue;
            }

            $basename = basename($path);
            $url      = $this->get_medal_sticker_asset_url($basename);
            if ($url === '') {
                continue;
            }

            $register_id = absint($row->register_id);

            $items[] = (object) array(
                'register_id'        => $register_id,
                'competition_name'   => (string) $row->competition_name,
                'student_medal'      => (string) $row->student_medal,
                'sticker_basename'   => $basename,
                'preview_url'        => $url,
                'download_url'       => $url,
                'download_filename'  => $basename,
            );
        }

        return $items;
    }
}

