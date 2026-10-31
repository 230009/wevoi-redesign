<?php
/**
 * New file: class-bulk-form-handler.php
 */
class WeVoi_Register_Bulk_Form_Handler {
    private static $instance = null;
    private $stripe_handler;
    private $db;
    private $email_handler;
    private $logger;

    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
    global $wpdb;
    $this->db = $wpdb;
    $this->stripe_handler = WeVoi_Register_Stripe_Handler::instance();
    $this->email_handler = WeVoi_Register_Email_Handler::instance();
    $this->logger = WeVoi_Register_Logger::instance();

    add_shortcode('wevoi_bulk_register_form', array($this, 'render_form'));
    add_action('wp_ajax_wevoi_process_bulk_registration', array($this, 'process_bulk_registration'));
    add_action('wp_ajax_wevoi_update_bulk_payment_status', array($this, 'handle_update_payment_status')); // Değiştirildi
    add_action('wp_ajax_wevoi_bulk_lookup_student_by_email', array($this, 'ajax_bulk_lookup_student_by_email'));
    add_action('wp_ajax_wevoi_get_competition_topics', array($this, 'ajax_get_competition_topics'));
}

    public function render_form() {
        if (!is_user_logged_in()) {
            return '<div style="text-align: center; margin-top: 20px;">
                    <p style="font-weight: bold;">Please <a href="https://www.wevoi.org/wp-login.php"
                    style="text-decoration: none; color: blue;">log in</a> to submit videos.</p>
                    </div>';
        }

        if (!$this->user_can_use_bulk_register()) {
            return '<div style="text-align: center; margin-top: 20px;">
                    <p style="font-weight: bold;">This form is only available for supervisors and agencies.
                    Please <a href="https://www.wevoi.org/wp-login.php">log in</a> with a supervisor or agency account.</p>
                    </div>';
        }

        $competitions = $this->get_active_competitions();
        if (empty($competitions)) {
            return '<p>No active competitions available.</p>';
        }

        $eligible_students = $this->get_eligible_students();

        ob_start();
        include WEVOI_REGISTER_PLUGIN_DIR . 'public/templates/bulk-payment-form.php';
        $form_html = ob_get_clean();

        $this->enqueue_assets();

        return $form_html;
    }

    /**
     * Check if current user can use bulk registration (supervisor or agency).
     */
    private function user_can_use_bulk_register() {
        return $this->user_has_supervisor_role() || $this->user_has_agency_role();
    }

    private function user_has_agency_role() {
        global $wpdb;
        $user_id = get_current_user_id();

        $capabilities = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value
            FROM {$wpdb->usermeta}
            WHERE user_id = %d
            AND meta_key = %s",
            $user_id,
            $wpdb->prefix . 'capabilities'
        ));

        foreach ($capabilities as $capability) {
            $user_roles = maybe_unserialize($capability);
            if (is_array($user_roles) && array_key_exists('agency', $user_roles)) {
                return true;
            }
        }

        return false;
    }

    private function user_has_supervisor_role() {
    global $wpdb;
    $user_id = get_current_user_id();

    // Kullanıcının tüm rollerini veritabanından al
    $capabilities = $wpdb->get_col($wpdb->prepare(
        "SELECT meta_value
        FROM {$wpdb->usermeta}
        WHERE user_id = %d
        AND meta_key = %s",
        $user_id,
        $wpdb->prefix . 'capabilities'
    ));

    // Her bir capability değerini kontrol et
    foreach ($capabilities as $capability) {
        $user_roles = maybe_unserialize($capability);
        if (is_array($user_roles) && array_key_exists('supervisor', $user_roles)) {
            return true;
        }
    }

    return false;
}

    private function get_active_competitions() {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT competition_code, competition_name, competition_date, competition_price, competition_type, season_id
                FROM {$this->db->prefix}wevoi_competitions
                WHERE competition_status = %s AND competition_type != 'Forum'
                ORDER BY competition_date",
                'ON AIR'
            )
        );
    }

    private function get_eligible_students() {
        $supervisor_id = get_current_user_id();
        $supervisor_email = (wp_get_current_user()->user_email ?? '');
        $active_competition_code = $this->get_active_competition_code();

        // Students who: (1) have at least one registration with this supervisor/agency, OR (2) have supervisoremail usermeta = current user email
        // and are not yet registered for the active competition
        $students = $this->db->get_results($this->db->prepare(
            "SELECT
                u.ID,
                u.user_email as email,
                CONCAT(
                    COALESCE(um_first.meta_value,''), ' ',
                    COALESCE(um_last.meta_value,'')
                ) as student_name,
                um_birth.meta_value as birth_date,
                s.schoolname,
                COALESCE(co_user.countryname, c.countryname) as countryname
            FROM {$this->db->prefix}users u
            JOIN {$this->db->prefix}usermeta um_role
                ON u.ID = um_role.user_id
                AND um_role.meta_key = %s
                AND um_role.meta_value LIKE %s
            LEFT JOIN {$this->db->prefix}wevoi_registrations r_any
                ON u.ID = r_any.user_id
                AND r_any.supervisor_id = %d
            LEFT JOIN {$this->db->prefix}usermeta um_supervisor_email
                ON u.ID = um_supervisor_email.user_id
                AND um_supervisor_email.meta_key = 'supervisoremail'
                AND um_supervisor_email.meta_value = %s
            LEFT JOIN {$this->db->prefix}usermeta um_first
                ON u.ID = um_first.user_id
                AND um_first.meta_key = 'first_name'
            LEFT JOIN {$this->db->prefix}usermeta um_last
                ON u.ID = um_last.user_id
                AND um_last.meta_key = 'last_name'
            LEFT JOIN {$this->db->prefix}usermeta um_birth
                ON u.ID = um_birth.user_id
                AND um_birth.meta_key = 'birth_date'
            LEFT JOIN {$this->db->prefix}usermeta um_school
                ON u.ID = um_school.user_id
                AND um_school.meta_key = 'schoolid'
            LEFT JOIN {$this->db->prefix}wevoi_schools s
                ON um_school.meta_value = s.schoolid
            LEFT JOIN {$this->db->prefix}wevoi_countries c
                ON s.countryid = c.countryid
            LEFT JOIN {$this->db->prefix}usermeta um_country
                ON u.ID = um_country.user_id
                AND um_country.meta_key = 'countryid'
            LEFT JOIN {$this->db->prefix}wevoi_countries co_user
                ON um_country.meta_value = co_user.countryid
            LEFT JOIN {$this->db->prefix}wevoi_registrations r
                ON u.ID = r.user_id
                AND r.competition_code = %d
            WHERE r.register_id IS NULL
            AND (r_any.register_id IS NOT NULL OR um_supervisor_email.user_id IS NOT NULL)
            ORDER BY student_name",
            $this->db->prefix . 'capabilities',
            '%"student"%',
            $supervisor_id,
            $supervisor_email,
            $active_competition_code
        ));

        // Calculate age category and topic for each student based on birth_date from usermeta
        $active_competition_code = $this->get_active_competition_code();

        foreach ($students as $student) {
            // Use birth_date from query result (usermeta), not from get_user_meta
            if (!empty($student->birth_date)) {
                $age = $this->calculate_age($student->birth_date);
                $age_category_id = $this->get_age_category_id($age);
                $student->age_category_id = $age_category_id;

                if ($age_category_id) {
                    $age_category = $this->db->get_row($this->db->prepare(
                        "SELECT age_category FROM {$this->db->prefix}wevoi_age_categories WHERE id = %d",
                        $age_category_id
                    ));
                    $student->age_category = $age_category ? $age_category->age_category : null;

                    // Get topic for this student's age category and active competition
                    if ($student->age_category && $active_competition_code) {
                        $topic = $this->db->get_row($this->db->prepare(
                            "SELECT topic FROM {$this->db->prefix}wevoi_topics
                             WHERE competition_code = %d AND wevoi_age_category = %s
                             LIMIT 1",
                            $active_competition_code,
                            $student->age_category
                        ));
                        $student->topic = $topic ? $topic->topic : null;
                    } else {
                        $student->topic = null;
                    }
                } else {
                    $student->age_category = null;
                    $student->topic = null;
                }
            } else {
                $student->age_category_id = null;
                $student->age_category = null;
                $student->topic = null;
            }
        }

        return $students;
    }

    /**
     * Calculate age from birth date
     */
    private function calculate_age($birth_date) {
        if (empty($birth_date)) {
            return null;
        }

        $birth_date_obj = new DateTime($birth_date);
        $today = new DateTime();
        $age = $today->diff($birth_date_obj)->y;

        return $age;
    }

    /**
     * Get age category ID from age
     */
    private function get_age_category_id($age) {
        if ($age === null) {
            return null;
        }

        $age_category = $this->db->get_row($this->db->prepare(
            "SELECT id FROM {$this->db->prefix}wevoi_age_categories
            WHERE starting_age <= %d AND ending_age >= %d
            LIMIT 1",
            $age,
            $age
        ));

        return $age_category ? (int) $age_category->id : null;
    }

    /**
     * Get age category ID for a student based on birth date
     */
    private function get_student_age_category_id($user_id, $birth_date = null) {
        if (empty($birth_date)) {
            $birth_date = get_user_meta($user_id, 'birth_date', true);
        }

        if (empty($birth_date)) {
            return null;
        }

        $age = $this->calculate_age($birth_date);
        return $this->get_age_category_id($age);
    }

    private function get_active_competition_code() {
        return $this->db->get_var(
            $this->db->prepare(
                "SELECT competition_code
                FROM {$this->db->prefix}wevoi_competitions
                WHERE competition_status = %s
                LIMIT 1",
                'ON AIR'
            )
        );
    }

    /**
     * AJAX: Look up a student by email for bulk registration. Returns student row if eligible (student role, not yet registered for competition).
     */
    public function ajax_bulk_lookup_student_by_email() {
        check_ajax_referer('wevoi_register_nonce', 'nonce');

        if (!$this->user_can_use_bulk_register()) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $email = isset($_POST['email']) ? sanitize_email(trim($_POST['email'])) : '';
        $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Please enter a valid email address.');
            return;
        }
        if (empty($competition_code)) {
            $competition_code = $this->get_active_competition_code();
        }
        if (empty($competition_code)) {
            wp_send_json_error('No active competition.');
            return;
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error('No student account found with this email. The student must sign up on the platform first.');
            return;
        }

        $user_roles = (array) $user->roles;
        if (!in_array('student', $user_roles)) {
            wp_send_json_error('This account is not a student.');
            return;
        }

        $competition_row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}wevoi_competitions WHERE competition_code = %d",
            $competition_code
        ));
        if ($competition_row && !WeVoi_Summit_Eligibility::user_may_register_for_competition($this->db, $user->ID, $competition_row)) {
            wp_send_json_error(WeVoi_Summit_Eligibility::get_denial_message());
            return;
        }

        $already = $this->db->get_var($this->db->prepare(
            "SELECT register_id FROM {$this->db->prefix}wevoi_registrations WHERE user_id = %d AND competition_code = %d",
            $user->ID,
            $competition_code
        ));
        if (!empty($already)) {
            wp_send_json_error('This student is already registered for the selected competition.');
            return;
        }

        $birth_date = get_user_meta($user->ID, 'birth_date', true);
        $age_category_id = $this->get_student_age_category_id($user->ID, $birth_date);
        $age_category = null;
        $topic = null;
        if ($age_category_id) {
            $age_cat = $this->db->get_row($this->db->prepare(
                "SELECT age_category FROM {$this->db->prefix}wevoi_age_categories WHERE id = %d",
                $age_category_id
            ));
            $age_category = $age_cat ? $age_cat->age_category : null;
            if ($age_category && $competition_code) {
                $t = $this->db->get_row($this->db->prepare(
                    "SELECT topic FROM {$this->db->prefix}wevoi_topics WHERE competition_code = %d AND wevoi_age_category = %s LIMIT 1",
                    $competition_code,
                    $age_category
                ));
                $topic = $t ? $t->topic : null;
            }
        }

        $first = get_user_meta($user->ID, 'first_name', true);
        $last = get_user_meta($user->ID, 'last_name', true);
        $student_name = trim($first . ' ' . $last);
        if (empty($student_name)) {
            $student_name = $user->display_name ?: $email;
        }

        $schoolid = get_user_meta($user->ID, 'schoolid', true);
        $schoolname = '';
        $countryname = '';
        $country_id = get_user_meta($user->ID, 'countryid', true);
        if (!empty($country_id)) {
            $country_row = $this->db->get_row($this->db->prepare(
                "SELECT countryname FROM {$this->db->prefix}wevoi_countries WHERE countryid = %d",
                intval($country_id)
            ));
            $countryname = $country_row ? $country_row->countryname : '';
        }
        if (!empty($schoolid)) {
            $school = $this->db->get_row($this->db->prepare(
                "SELECT s.schoolname, c.countryname
                 FROM {$this->db->prefix}wevoi_schools s
                 LEFT JOIN {$this->db->prefix}wevoi_countries c ON s.countryid = c.countryid
                 WHERE s.schoolid = %s",
                $schoolid
            ));
            if ($school) {
                $schoolname = $school->schoolname ?: '';
                if ($countryname === '' && !empty($school->countryname)) {
                    $countryname = $school->countryname;
                }
            }
        }

        wp_send_json_success(array(
            'ID' => $user->ID,
            'email' => $user->user_email,
            'student_name' => $student_name,
            'birth_date' => $birth_date,
            'schoolname' => $schoolname,
            'countryname' => $countryname,
            'age_category' => $age_category,
            'age_category_id' => $age_category_id,
            'topic' => $topic
        ));
    }

    /**
     * Topics for a competition, keyed by age category name.
     */
    public function ajax_get_competition_topics() {
        check_ajax_referer('wevoi_register_nonce', 'nonce');

        if (!$this->user_can_use_bulk_register()) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;
        if ($competition_code <= 0) {
            wp_send_json_error('Invalid competition');
            return;
        }

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT wevoi_age_category, topic
             FROM {$this->db->prefix}wevoi_topics
             WHERE competition_code = %d",
            $competition_code
        ));

        $topics = array();
        if ($rows) {
            foreach ($rows as $row) {
                $topics[$row->wevoi_age_category] = $row->topic;
            }
        }

        wp_send_json_success(array(
            'topics' => $topics
        ));
    }

    public function process_bulk_registration() {
    if (ob_get_level()) {
        ob_clean();
    }

    try {
        // Set headers
        header('Content-Type: application/json');
        check_ajax_referer('wevoi_register_nonce', 'nonce');

        if (!$this->user_can_use_bulk_register()) {
            throw new Exception('Unauthorized access');
        }

        // Get and validate form data
        $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;
        $student_data = isset($_POST['students']) ? json_decode(stripslashes($_POST['students']), true) : [];
        $payer_name = isset($_POST['payer_name']) ? sanitize_text_field($_POST['payer_name']) : '';
        $payer_countryid = isset($_POST['payer_countryid']) ? intval($_POST['payer_countryid']) : 0;
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';

        if (empty($competition_code)) {
            throw new Exception('Competition code is required');
        }

        if (empty($student_data)) {
            throw new Exception('No students selected');
        }

        $supervisor_id = get_current_user_id();

        // Validate each student: must be student role and not already registered for this competition
        foreach ($student_data as $student) {
            $uid = isset($student['id']) ? intval($student['id']) : 0;
            if (!$uid) {
                throw new Exception('Invalid student data.');
            }
            $user = get_user_by('id', $uid);
            if (!$user || !in_array('student', (array) $user->roles)) {
                throw new Exception('One or more selected accounts are not students. Please only select students.');
            }
            $already = $this->db->get_var($this->db->prepare(
                "SELECT register_id FROM {$this->db->prefix}wevoi_registrations WHERE user_id = %d AND competition_code = %d",
                $uid,
                $competition_code
            ));
            if (!empty($already)) {
                throw new Exception('One or more selected students are already registered for this competition.');
            }
        }

        // Validate YouTube links
        foreach ($student_data as $student) {
            if (!isset($student['youtube_link']) || !$this->validate_youtube_link($student['youtube_link'])) {
                throw new Exception('Invalid YouTube link format for student: ' . ($student['name'] ?? 'Unknown'));
            }
        }

        $this->db->query('START TRANSACTION');

        // Get competition details
        $competition = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}wevoi_competitions
            WHERE competition_code = %d AND competition_status = 'ON AIR'",
            $competition_code
        ));

        if (!$competition) {
            throw new Exception('Invalid competition or competition is not active');
        }

        foreach ($student_data as $student) {
            $uid = isset($student['id']) ? intval($student['id']) : 0;
            if ($uid && !WeVoi_Summit_Eligibility::user_may_register_for_competition($this->db, $uid, $competition)) {
                throw new Exception(WeVoi_Summit_Eligibility::get_denial_message());
            }
        }

        // Calculate total price
        $total_price = count($student_data) * $competition->competition_price;
        $is_free = false;

        // Process coupon if exists
        if (!empty($coupon_code)) {
            $coupon = $this->db->get_row($this->db->prepare(
                "SELECT * FROM {$this->db->prefix}wevoi_coupons
                WHERE coupon_code = %s AND competition_code = %d",
                $coupon_code,
                $competition_code
            ));

            if ($coupon) {
                // Validate coupon usage limit
                if ($coupon->coupon_limit > 0 && ($coupon->coupon_used + count($student_data)) > $coupon->coupon_limit) {
                    throw new Exception('Coupon usage limit exceeded');
                }

                // Calculate discount
                // Check if coupon has discount amount (check for NULL and > 0)
                if (isset($coupon->coupon_discount_amount) && $coupon->coupon_discount_amount !== null && floatval($coupon->coupon_discount_amount) > 0) {
                    $discount_amount = floatval($coupon->coupon_discount_amount);
                    // Discount amount is applied once to the total, not per student
                    if ($discount_amount >= $total_price) {
                        $is_free = true;
                        $total_price = 0;
                    } else {
                        $total_price = $total_price - $discount_amount;
                    }
                }
                // Check if coupon has discount percentage (check for NULL and > 0)
                elseif (isset($coupon->coupon_discount_percentage) && $coupon->coupon_discount_percentage !== null && intval($coupon->coupon_discount_percentage) > 0) {
                    $discount = ($total_price * intval($coupon->coupon_discount_percentage)) / 100;
                    if ($discount >= $total_price) {
                        $is_free = true;
                        $total_price = 0;
                    } else {
                        $total_price = $total_price - $discount;
                    }
                }
            }
        }

        if ($is_free) {
            $result = $this->process_free_bulk_registration(
                $student_data,
                $competition_code,
                $coupon_code
            );
            $this->db->query('COMMIT');
            wp_send_json_success($result);
            return;
        } else {
            if (empty($payer_name) || empty($payer_countryid)) {
                throw new Exception('Missing payer information');
            }

            $result = $this->process_paid_bulk_registration(
                $student_data,
                $competition_code,
                $coupon_code,
                $total_price,
                $payer_name,
                $payer_countryid
            );
            $this->db->query('COMMIT');
            wp_send_json_success($result);
            return;
        }

    } catch (Exception $e) {
        $this->db->query('ROLLBACK');
        $this->logger->log_error('Bulk registration failed', [
            'error' => $e->getMessage(),
            'student_count' => count($student_data ?? [])
        ]);
        wp_send_json_error($e->getMessage());
    }
}

    private function process_free_bulk_registration($student_data, $competition_code, $coupon_code) {
    $registration_ids = [];
    $supervisor_id = get_current_user_id();
    $supervisor = wp_get_current_user();

    foreach ($student_data as $student) {
        // Calculate age category ID for this student
        $age_category_id = $this->get_student_age_category_id($student['id']);

        $inserted = $this->db->insert(
            $this->db->prefix . 'wevoi_registrations',
            [
                'user_id' => $student['id'],
                'supervisor_id' => $supervisor_id,
                'competition_code' => $competition_code,
                'youtube_link' => $student['youtube_link'],
                'age_category_id' => $age_category_id,
                'paid_status' => 'FREE',
                'price' => 0,
                'coupon_code' => $coupon_code,
                'registration_date' => current_time('mysql'),
                'application_status' => 'PENDING_REVIEW'
            ],
            [
                '%d', '%d', '%d', '%s', '%d', '%s', '%f', '%s', '%s', '%s'
            ]
        );

        if (!$inserted) {
            throw new Exception('Failed to insert registration for student: ' . $student['name']);
        }

        $registration_ids[] = $this->db->insert_id;
    }

    // Update coupon usage if coupon was used
    if (!empty($coupon_code)) {
        $this->update_coupon_usage($coupon_code, count($student_data));
    }

    // Log bulk registration
    $this->logger->log_bulk_payment([
        'supervisor_id' => $supervisor_id,
        'registration_ids' => $registration_ids,
        'status' => 'FREE',
        'coupon_code' => $coupon_code
    ]);

    // Send confirmation emails
    $this->email_handler->send_bulk_confirmation_emails(
        $registration_ids,
        $supervisor->user_email
    );

    return [
        'message' => 'Bulk registration successful',
        'registration_ids' => $registration_ids
    ];
}

    private function process_paid_bulk_registration($student_data, $competition_code, $coupon_code, $total_price, $payer_name, $payer_countryid) {
    $supervisor = wp_get_current_user();
    $supervisor_email = $supervisor->user_email;

    // Get country details
    $country = $this->db->get_row($this->db->prepare(
        "SELECT iso FROM {$this->db->prefix}wevoi_countries WHERE countryid = %d",
        $payer_countryid
    ));

    if (!$country) {
        throw new Exception('Invalid country selected');
    }

    // Create Stripe customer
    $customer_id = $this->stripe_handler->create_customer([
        'name' => $payer_name,
        'email' => $supervisor_email,
        'country_id' => $payer_countryid,
        'country_code' => $country->iso
    ]);

    if (is_wp_error($customer_id)) {
        throw new Exception($customer_id->get_error_message());
    }

    // Create payment intent with metadata
    $payment_intent = $this->stripe_handler->create_payment_intent($total_price, [
        'competition_code' => $competition_code,
        'customer_email' => $supervisor_email,
        'receipt_email' => $supervisor_email,
        'is_bulk' => true,
        'student_count' => count($student_data)
    ]);

    if (is_wp_error($payment_intent)) {
        throw new Exception($payment_intent->get_error_message());
    }

    // Insert registrations
    $registration_ids = [];
    $individual_price = $total_price / count($student_data);

    foreach ($student_data as $student) {
        // Calculate age category ID for this student
        $age_category_id = $this->get_student_age_category_id($student['id']);

        $inserted = $this->db->insert(
            $this->db->prefix . 'wevoi_registrations',
            [
                'user_id' => $student['id'],
                'supervisor_id' => get_current_user_id(),
                'competition_code' => $competition_code,
                'youtube_link' => $student['youtube_link'],
                'age_category_id' => $age_category_id,
                'payer_name' => $payer_name,
                'payer_countryid' => $payer_countryid,
                'paid_status' => 'PENDING',
                'price' => $individual_price,
                'coupon_code' => $coupon_code,
                'payment_intent_id' => $payment_intent->id,
                'registration_date' => current_time('mysql'),
                'application_status' => 'PENDING_REVIEW'
            ],
            [
                '%d', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s'
            ]
        );

        if (!$inserted) {
            throw new Exception('Failed to insert registration for student ID: ' . $student['id']);
        }

        $registration_ids[] = $this->db->insert_id;
    }

    $this->logger->log_payment([
        'payment_id' => $payment_intent->id,
        'amount' => $total_price,
        'status' => 'PENDING',
        'is_bulk' => true,
        'registration_ids' => $registration_ids,
        'supervisor_id' => get_current_user_id()
    ]);

    return [
        'registration_ids' => $registration_ids,
        'customer_id' => $customer_id,
        'client_secret' => $payment_intent->client_secret,
        'country_code' => $country->iso,
        'payment_intent_id' => $payment_intent->id // Bunu ekledik
    ];
}

    private function validate_youtube_link($url) {
        if (empty($url)) {
            return false;
        }

        $url = trim($url);

        $patterns = array(
            'standard' => '/^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/watch\?(?:.*&)?v=([a-zA-Z0-9_-]{11})(?:\S*)?$/',
            'shortened' => '/^(?:https?:\/\/)?(?:www\.|m\.)?youtu\.be\/([a-zA-Z0-9_-]{11})(?:\S*)?$/',
            'shorts' => '/^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})(?:\S*)?$/',
            'embed' => '/^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})(?:\S*)?$/'
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    private function update_coupon_usage($coupon_code, $usage_count) {
    // Get and lock current coupon usage
    $initial_usage = $this->db->get_var($this->db->prepare(
        "SELECT coupon_used FROM {$this->db->prefix}wevoi_coupons
        WHERE coupon_code = %s FOR UPDATE",
        $coupon_code
    ));

    if ($initial_usage === null) {
        throw new Exception('Invalid coupon code');
    }

    // Update usage count
    $updated = $this->db->update(
        $this->db->prefix . 'wevoi_coupons',
        ['coupon_used' => intval($initial_usage) + $usage_count],
        ['coupon_code' => $coupon_code],
        ['%d'],
        ['%s']
    );

    if ($updated === false) {
        throw new Exception('Failed to update coupon usage');
    }

    // Verify update
    $final_usage = $this->db->get_var($this->db->prepare(
        "SELECT coupon_used FROM {$this->db->prefix}wevoi_coupons
        WHERE coupon_code = %s",
        $coupon_code
    ));

    if ($final_usage <= $initial_usage) {
        throw new Exception('Coupon usage update failed verification');
    }

    return true;
}

    private function enqueue_assets() {
    // Ana stil dosyasi
    wp_enqueue_style(
        'wevoi-register-style',
        WEVOI_REGISTER_PLUGIN_URL . 'public/css/form-styles.css',
        array(),
        WEVOI_REGISTER_VERSION
    );

    // Stripe script
    wp_enqueue_script(
        'stripe-js',
        'https://js.stripe.com/v3/',
        array(),
        null,
        true
    );

        $current_user = wp_get_current_user();

    // Form handler script
    wp_enqueue_script(
        'wevoi-bulk-register-script',
        WEVOI_REGISTER_PLUGIN_URL . 'public/js/bulk-form-handler.js',
        array('jquery', 'stripe-js'),
        WEVOI_REGISTER_VERSION,
        true
    );

        // Template dosya yolu
    $template_path = WEVOI_REGISTER_PLUGIN_DIR . 'public/templates/bulk-payment-form.php';

        wp_localize_script(
            'wevoi-bulk-register-script',
            'wevoiBulkRegister',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wevoi_register_nonce'),
                'stripeKey' => $this->stripe_handler->get_publishable_key(),
                'userEmail' => $current_user->user_email
            )
        );
    }

    public function handle_update_payment_status() {
    check_ajax_referer('wevoi_register_nonce', 'nonce');

    try {
        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id']);
        $customer_id = sanitize_text_field($_POST['customer_id']);
        $registration_ids = isset($_POST['registration_ids']) ? array_map('intval', $_POST['registration_ids']) : [];

        if (empty($registration_ids)) {
            throw new Exception('No registrations found');
        }

        $this->db->query('START TRANSACTION');

        // Tüm kayıtların PENDING durumunda olduğunu kontrol et
        $pending_count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->db->prefix}wevoi_registrations
            WHERE register_id IN (" . implode(',', array_fill(0, count($registration_ids), '%d')) . ")
            AND paid_status = 'PENDING'",
            $registration_ids
        ));

        if ($pending_count != count($registration_ids)) {
            throw new Exception('Some registrations are not in PENDING status');
        }

        // Tüm kayıtları güncelle
        foreach ($registration_ids as $registration_id) {
            $updated = $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                [
                    'paid_status' => 'PAID',
                    'customer_code' => $customer_id
                ],
                ['register_id' => $registration_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                throw new Exception('Failed to update registration status');
            }
        }

        // Update coupon usage
        $coupon_code = $this->db->get_var($this->db->prepare(
            "SELECT coupon_code FROM {$this->db->prefix}wevoi_registrations
            WHERE register_id = %d",
            $registration_ids[0]
        ));

        if (!empty($coupon_code)) {
            $this->update_coupon_usage($coupon_code, count($registration_ids));
        }

        // Supervisor bilgilerini al
        $supervisor = wp_get_current_user();

        // Başarılı ödemeyi logla
        $this->logger->log_bulk_payment([
            'payment_id' => $payment_intent_id,
            'supervisor_id' => $supervisor->ID,
            'registration_ids' => $registration_ids,
            'status' => 'PAID'
        ]);

        // Mail gönder
        $this->email_handler->send_bulk_confirmation_emails(
            $registration_ids,
            $supervisor->user_email
        );

        $this->db->query('COMMIT');

        wp_send_json_success([
            'message' => 'Payment status updated successfully',
            'registration_ids' => $registration_ids
        ]);

    } catch (Exception $e) {
        $this->db->query('ROLLBACK');
        $this->logger->log_error('Bulk payment status update failed', [
            'error' => $e->getMessage(),
            'registration_ids' => $registration_ids ?? []
        ]);
        wp_send_json_error($e->getMessage());
    }
}


}
