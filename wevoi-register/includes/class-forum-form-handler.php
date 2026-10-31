<?php
if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Forum_Form_Handler {
    private static $instance = null;
    private $stripe_handler;
    private $db;
    private $email_handler;
    private $logger;

    /** @var array Localized script args for the forum form (set in render_form). */
    private $forum_script_config = array();

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

        // Register shortcode
        add_shortcode('wevoi_forum_form', array($this, 'render_form'));

        // Register AJAX actions
        add_action('wp_ajax_wevoi_process_forum_registration', array($this, 'process_forum_registration'));
        add_action('wp_ajax_nopriv_wevoi_process_forum_registration', array($this, 'process_forum_registration'));
        add_action('wp_ajax_wevoi_validate_forum_coupon', array($this, 'validate_coupon'));
        add_action('wp_ajax_nopriv_wevoi_validate_forum_coupon', array($this, 'validate_coupon'));
        add_action('wp_ajax_wevoi_update_payment_status', array($this, 'update_payment_status'));
        add_action('wp_ajax_wevoi_start_forum_payment', array($this, 'start_forum_payment'));
        add_action('wp_ajax_wevoi_get_supervisor_parent_info', array($this, 'get_supervisor_parent_info'));
        add_action('wp_ajax_nopriv_wevoi_get_supervisor_parent_info', array($this, 'get_supervisor_parent_info'));
    }

    /**
     * Render Forum registration form
     */
    public function render_form() {
        $this->forum_script_config = array(
            'formMode' => 'standard',
            'forumPaymentPhase' => 'payment_required',
            'registrationId' => 0,
            'completionAmount' => 0,
        );

        if (!is_user_logged_in()) {
            return '<div style="text-align: center; margin-top: 20px;"><p style="font-weight: bold;">Please <a href="https://www.wevoi.org/wp-login.php" style="text-decoration: none; color: blue;">log in</a> to register for Forum.</p></div>';
        }

        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;

        // Check if user has valid role (student, supervisor, parent, or agency)
        if (!in_array('student', $user_roles) && !in_array('supervisor', $user_roles) && !in_array('parent', $user_roles) && !in_array('agency', $user_roles)) {
            return '<div style="text-align: center; margin-top: 20px;"><p style="font-weight: bold;">Only students, supervisors, parents, and agencies can register for Forum.</p></div>';
        }

        // Get active Forum competitions
        $competitions = $this->get_active_forum_competitions();
        if (empty($competitions)) {
            return '<p>No active Forum competitions available.</p>';
        }

        $user_id = get_current_user_id();
        $user_info = $this->get_user_basic_info($user_id);
        $current_role = $this->get_user_role_for_pricing($user);
        $selected_competition = $competitions[0];
        // Expose for forum-registration-form.php (foreach competition === selected).
        $price = $this->get_forum_price_by_role($selected_competition->competition_code, $current_role);
        $forum_payment_phase = $this->resolve_forum_payment_phase($selected_competition);
        $existing_registration = $this->get_user_forum_registration_with_competition($user_id, $selected_competition->competition_code);

        $form_mode = 'new';
        if ($existing_registration) {
            $paid = strtoupper((string) $existing_registration->paid_status);
            if (in_array($paid, array('PAID', 'FREE'), true)) {
                $form_mode = 'blocked_complete';
            } elseif ($paid === 'PRE_REGISTERED') {
                if ($forum_payment_phase === 'payment_required' && floatval($existing_registration->price) > 0) {
                    $form_mode = 'payment_completion';
                } else {
                    $form_mode = 'blocked_pre_registration';
                }
            } elseif ($paid === 'PENDING' && !empty($existing_registration->payment_intent_id)) {
                $form_mode = 'blocked_payment_pending';
            } else {
                $form_mode = 'blocked_complete';
            }
        }

        $this->forum_script_config = array(
            'formMode' => ($form_mode === 'payment_completion') ? 'payment_completion' : 'standard',
            'forumPaymentPhase' => $forum_payment_phase,
            'registrationId' => ($form_mode === 'payment_completion') ? (int) $existing_registration->register_id : 0,
            'completionAmount' => ($form_mode === 'payment_completion') ? floatval($existing_registration->price) : 0,
        );

        $already_registered = in_array($form_mode, array('blocked_complete', 'blocked_pre_registration', 'blocked_payment_pending'), true);
        $registered_competition_code = $selected_competition->competition_code;

        if ($form_mode === 'payment_completion' && $existing_registration) {
            $price = floatval($existing_registration->price);
        }

        $supervisor_info = null;
        $parent_info = null;
        if (in_array('student', $user_roles)) {
            $parent_info = $this->get_parent_info_from_student_meta($user_id);
        }

        $this->enqueue_assets();

        ob_start();
        include WEVOI_REGISTER_PLUGIN_DIR . 'public/templates/forum-registration-form.php';
        return ob_get_clean();
    }

    /**
     * Get active Forum competitions
     */
    private function get_active_forum_competitions() {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT competition_code, competition_name, competition_date, competition_price, price_supervisor, price_parent, event_venue, event_duration, forum_payment_phase
                FROM {$this->db->prefix}wevoi_competitions
                WHERE competition_status = %s AND competition_type = %s
                ORDER BY competition_date",
                'ON AIR',
                'Forum'
            )
        );
    }

    /**
     * Normalize forum_payment_phase from competition row (defaults to pre_registration).
     */
    private function resolve_forum_payment_phase($competition_row) {
        if (!$competition_row) {
            return 'pre_registration';
        }
        $phase = isset($competition_row->forum_payment_phase) ? (string) $competition_row->forum_payment_phase : '';
        return ($phase === 'payment_required') ? 'payment_required' : 'pre_registration';
    }

    /**
     * Registration row for this user and Forum competition, with competition fields.
     *
     * @return object|null
     */
    private function get_user_forum_registration_with_competition($user_id, $competition_code) {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT r.*, c.forum_payment_phase, c.competition_name, c.competition_date, c.competition_type, c.event_venue, c.event_duration, c.competition_status
                 FROM {$this->db->prefix}wevoi_registrations r
                 INNER JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                 WHERE r.user_id = %d AND r.competition_code = %d AND c.competition_type = 'Forum'",
                $user_id,
                $competition_code
            )
        );
    }

    /**
     * Check if user is already registered for a competition
     */
    private function is_user_registered_for_competition($user_id, $competition_code) {
        $registration = $this->db->get_var(
            $this->db->prepare(
                "SELECT register_id FROM {$this->db->prefix}wevoi_registrations
                WHERE user_id = %d AND competition_code = %d",
                $user_id,
                $competition_code
            )
        );
        return !empty($registration);
    }

    /**
     * Get user basic info for read-only fields
     */
    private function get_user_basic_info($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return null;
        }

        // Get user role to determine which WhatsApp to retrieve
        $user_obj = new WP_User($user_id);
        $user_roles = (array) $user_obj->roles;

        $whatsapp = '';
        if (in_array('student', $user_roles)) {
            // For students, only get studentwhatsapp (not supervisor's WhatsApp)
            $whatsapp = get_user_meta($user_id, 'studentwhatsapp', true);
        } elseif (in_array('supervisor', $user_roles) || in_array('parent', $user_roles) || in_array('agency', $user_roles)) {
            // For supervisor/parent/agency, get supervisorwhatsapp
            $whatsapp = get_user_meta($user_id, 'supervisorwhatsapp', true) ?: get_user_meta($user_id, 'whatsapp', true);
        }

        return array(
            'email' => $user->user_email,
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'gender' => get_user_meta($user_id, 'gender', true),
            'birth_date' => get_user_meta($user_id, 'birth_date', true),
            'whatsapp' => $whatsapp
        );
    }

    /**
     * Get parent info from student's usermeta for initial prefill
     */
    private function get_parent_info_from_student_meta($student_id) {
        $parent_email = get_user_meta($student_id, 'parentemail', true);
        $parent_first_name = get_user_meta($student_id, 'parentfirstname', true);
        $parent_last_name = get_user_meta($student_id, 'parentlastname', true);
        $parent_whatsapp = get_user_meta($student_id, 'parentwhatsapp', true);

        // Also check for parentfullname (if exists)
        $parent_fullname = get_user_meta($student_id, 'parentfullname', true);
        if (empty($parent_first_name) && empty($parent_last_name) && !empty($parent_fullname)) {
            $name_parts = explode(' ', trim($parent_fullname), 2);
            $parent_first_name = !empty($name_parts[0]) ? $name_parts[0] : '';
            $parent_last_name = (count($name_parts) > 1 && !empty($name_parts[1])) ? $name_parts[1] : '';
        }

        if (empty($parent_email) && empty($parent_first_name) && empty($parent_last_name) && empty($parent_whatsapp)) {
            return null;
        }

        return array(
            'email' => $parent_email,
            'first_name' => $parent_first_name,
            'last_name' => $parent_last_name,
            'whatsapp' => $parent_whatsapp
        );
    }

    /**
     * Get user role for pricing (student, supervisor, parent, or agency)
     */
    private function get_user_role_for_pricing($user) {
        $roles = (array) $user->roles;
        if (in_array('student', $roles)) {
            return 'student';
        } elseif (in_array('supervisor', $roles)) {
            return 'supervisor';
        } elseif (in_array('parent', $roles)) {
            return 'parent';
        } elseif (in_array('agency', $roles)) {
            return 'agency';
        }
        return 'student'; // Default
    }

    /**
     * Get Forum price by role
     */
    public function get_forum_price_by_role($competition_code, $role) {
        $competition = $this->db->get_row(
            $this->db->prepare(
                "SELECT competition_price, price_supervisor, price_parent
                FROM {$this->db->prefix}wevoi_competitions
                WHERE competition_code = %d AND competition_type = 'Forum'",
                $competition_code
            )
        );

        if (!$competition) {
            return 0;
        }

        switch ($role) {
            case 'supervisor':
                return $competition->price_supervisor ? floatval($competition->price_supervisor) : floatval($competition->competition_price);
            case 'parent':
                return $competition->price_parent ? floatval($competition->price_parent) : floatval($competition->competition_price);
            case 'agency':
                return $competition->price_supervisor ? floatval($competition->price_supervisor) : floatval($competition->competition_price);
            case 'student':
            default:
                return floatval($competition->competition_price);
        }
    }

    /**
     * Process Forum registration
     */
    public function process_forum_registration() {
        if (ob_get_level()) {
            ob_clean();
        }

        try {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache');

            // Nonce check
            check_ajax_referer('wevoi_forum_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error('Please log in to register.');
                return;
            }

            $user_id = get_current_user_id();
            $user = wp_get_current_user();
            $user_roles = (array) $user->roles;

            // Check valid role
            if (!in_array('student', $user_roles) && !in_array('supervisor', $user_roles) && !in_array('parent', $user_roles) && !in_array('agency', $user_roles)) {
                wp_send_json_error('Invalid user role for Forum registration.');
                return;
            }

            $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;
            $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field(trim($_POST['coupon_code'])) : '';
            $payer_name = isset($_POST['payer_name']) ? sanitize_text_field(trim($_POST['payer_name'])) : '';
            $payer_countryid = isset($_POST['payer_countryid']) ? intval($_POST['payer_countryid']) : 0;

            // Check if user is already registered for this competition
            if ($this->is_user_registered_for_competition($user_id, $competition_code)) {
                wp_send_json_error('You have already registered for this Forum competition.');
                return;
            }

            // Get role for pricing
            $role = $this->get_user_role_for_pricing($user);
            $base_price = $this->get_forum_price_by_role($competition_code, $role);

            // Validate competition
            $competition = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->db->prefix}wevoi_competitions
                    WHERE competition_code = %d AND competition_status = 'ON AIR' AND competition_type = 'Forum'",
                    $competition_code
                )
            );

            if (!$competition) {
                wp_send_json_error('Invalid competition or competition is not active.');
                return;
            }

            // Process coupon if exists
            $price = $base_price;
            if (!empty($coupon_code)) {
                $coupon = $this->db->get_row(
                    $this->db->prepare(
                        "SELECT * FROM {$this->db->prefix}wevoi_coupons
                        WHERE coupon_code = %s AND competition_code = %d",
                        $coupon_code,
                        $competition_code
                    )
                );

                if ($coupon) {
                    // Validate role (if coupon has specific role, user must match)
                    if (!empty($coupon->role) && $coupon->role !== $role) {
                        error_log('WeVoi Forum Register: Coupon role mismatch. Coupon role: ' . $coupon->role . ', User role: ' . $role);
                        wp_send_json_error('This coupon is not valid for your role.');
                        return;
                    }

                    // Validate coupon usage limit
                    if ($coupon->coupon_limit > 0 && $coupon->coupon_used >= $coupon->coupon_limit) {
                        error_log('WeVoi Forum Register: Coupon usage limit exceeded. Limit: ' . $coupon->coupon_limit . ', Used: ' . $coupon->coupon_used);
                        wp_send_json_error('Coupon usage limit exceeded.');
                        return;
                    }

                    // Calculate discount
                    // Check if coupon has discount amount (check for NULL and > 0)
                    if (isset($coupon->coupon_discount_amount) && $coupon->coupon_discount_amount !== null && floatval($coupon->coupon_discount_amount) > 0) {
                        $discount_amount = floatval($coupon->coupon_discount_amount);
                        $price = $base_price - $discount_amount;
                        error_log('WeVoi Forum Register: Applied fixed discount. Base price: ' . $base_price . ', Discount: ' . $discount_amount . ', Final price: ' . $price);
                    }
                    // Check if coupon has discount percentage (check for NULL and > 0)
                    elseif (isset($coupon->coupon_discount_percentage) && $coupon->coupon_discount_percentage !== null && intval($coupon->coupon_discount_percentage) > 0) {
                        $discount = ($base_price * intval($coupon->coupon_discount_percentage)) / 100;
                        $price = $base_price - $discount;
                        error_log('WeVoi Forum Register: Applied percentage discount. Base price: ' . $base_price . ', Discount %: ' . $coupon->coupon_discount_percentage . ', Discount amount: ' . $discount . ', Final price: ' . $price);
                    } else {
                        error_log('WeVoi Forum Register: Coupon found but no valid discount type. Discount amount: ' . (isset($coupon->coupon_discount_amount) ? $coupon->coupon_discount_amount : 'NULL') . ', Discount percentage: ' . (isset($coupon->coupon_discount_percentage) ? $coupon->coupon_discount_percentage : 'NULL'));
                    }

                    if ($price < 0) {
                        $price = 0;
                    }
                } else {
                    error_log('WeVoi Forum Register: Coupon code not found in database. Code: ' . $coupon_code . ', Competition: ' . $competition_code);
                    // Don't fail registration if coupon is invalid, just log it and continue without discount
                }
            }

            // Get role-specific required fields
            $role_data = $this->get_role_specific_data($user_id, $role, $_POST);
            if (!$role_data['valid']) {
                error_log('WeVoi Forum Register: Role-specific validation failed for role: ' . $role . ', Error: ' . $role_data['error']);
                error_log('WeVoi Forum Register: POST data: ' . print_r($_POST, true));
                wp_send_json_error($role_data['error']);
                return;
            }

            // Get passport_number from role_data
            $passport_number = isset($role_data['data']['passport_number']) ? $role_data['data']['passport_number'] : '';

            // Start transaction
            $this->db->query('START TRANSACTION');

            $forum_phase = $this->resolve_forum_payment_phase($competition);
            $is_pre_registration_paid = ($forum_phase === 'pre_registration' && $price > 0);

            // For paid registrations (immediate Stripe), payer info is required.
            // Pre-registration with a future fee: no payer/Stripe yet.
            // Free: payer optional.
            $customer_id = null;
            $country_code = null;

            if ($price > 0 && !$is_pre_registration_paid) {
                if (empty($payer_name) || empty($payer_countryid)) {
                    $this->db->query('ROLLBACK');
                    wp_send_json_error('Missing payer information.');
                    return;
                }

                $country = $this->db->get_row(
                    $this->db->prepare(
                        "SELECT iso FROM {$this->db->prefix}wevoi_countries WHERE countryid = %d",
                        $payer_countryid
                    )
                );

                if (!$country) {
                    throw new Exception('Invalid country selected');
                }

                $country_code = $country->iso;

                $customer_id = $this->stripe_handler->create_customer([
                    'name' => $payer_name,
                    'email' => $user->user_email,
                    'country_id' => $payer_countryid,
                    'country_code' => $country_code
                ]);

                if (is_wp_error($customer_id)) {
                    throw new Exception($customer_id->get_error_message());
                }
            } else {
                if (empty($payer_name)) {
                    $payer_name = $user->display_name ?? $user->user_login;
                }
                if (empty($payer_countryid)) {
                    $payer_countryid = 0;
                }
            }

            $initial_paid_status = $is_pre_registration_paid ? 'PRE_REGISTERED' : 'PENDING';
            if (!is_string($initial_paid_status) || trim($initial_paid_status) === '') {
                $initial_paid_status = 'PENDING';
            }

            // Insert registration (youtube_link is NULL for Forum). Supervisor/Agency is optional and stored per registration.
            $supervisor_id = isset($_POST['supervisor_id']) && $_POST['supervisor_id'] !== '' ? intval($_POST['supervisor_id']) : null;
            $inserted = $this->db->insert(
                $this->db->prefix . 'wevoi_registrations',
                [
                    'user_id' => $user_id,
                    'supervisor_id' => $supervisor_id,
                    'competition_code' => $competition_code,
                    'youtube_link' => null,
                    'payer_name' => $payer_name,
                    'payer_countryid' => $payer_countryid,
                    'paid_status' => $initial_paid_status,
                    'price' => $price,
                    'coupon_code' => $coupon_code,
                    'registration_date' => current_time('mysql'),
                    'application_status' => 'APPLICATION_ACCEPTED',
                    'passport_number' => $passport_number
                ],
                [
                    '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s'
                ]
            );

            if (!$inserted) {
                throw new Exception('Failed to insert registration');
            }

            $registration_id = $this->db->insert_id;

            // Update user meta with role-specific data
            $this->update_user_meta_for_forum($user_id, $role, $_POST);

            // If price is 0, process as free registration
            if ($price == 0) {
                // Update registration status to FREE
                $this->db->update(
                    $this->db->prefix . 'wevoi_registrations',
                    ['paid_status' => 'FREE'],
                    ['register_id' => $registration_id],
                    ['%s'],
                    ['%d']
                );

                // Update coupon usage if coupon was used
                if (!empty($coupon_code)) {
                    $this->db->query($this->db->prepare(
                        "UPDATE {$this->db->prefix}wevoi_coupons
                        SET coupon_used = coupon_used + 1
                        WHERE coupon_code = %s AND competition_code = %d",
                        $coupon_code,
                        $competition_code
                    ));
                }

                // Commit transaction
                $this->db->query('COMMIT');

                // Send Forum registration notification emails
                // For students: sends to student, supervisor (if exists), and parent (if exists)
                // For supervisor/parent: sends standard confirmation email
                $this->email_handler->send_forum_registration_emails($registration_id, $user_id, $role, false);

                wp_send_json_success([
                    'registration_id' => $registration_id,
                    'is_free' => true
                ]);
                return;
            }

            if ($is_pre_registration_paid) {
                $this->db->query('COMMIT');
                $this->email_handler->send_forum_registration_emails($registration_id, $user_id, $role, true);
                wp_send_json_success([
                    'registration_id' => $registration_id,
                    'is_pre_registration' => true
                ]);
                return;
            }

            // Get competition name for Stripe description
            $competition_info = $this->db->get_row(
                $this->db->prepare(
                    "SELECT competition_name FROM {$this->db->prefix}wevoi_competitions WHERE competition_code = %d",
                    $competition_code
                )
            );

            // Create payment intent
            $payment_intent = $this->stripe_handler->create_payment_intent($price, [
                'registration_id' => $registration_id,
                'competition_code' => $competition_code,
                'competition_name' => $competition_info ? $competition_info->competition_name : 'WeVoi Forum',
                'customer_email' => $user->user_email,
                'receipt_email' => $user->user_email,
                'user_id' => $user_id,
                'is_forum' => true
            ]);

            if (is_wp_error($payment_intent)) {
                throw new Exception($payment_intent->get_error_message());
            }

            // Update registration with payment_intent_id
            $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                ['payment_intent_id' => $payment_intent->id],
                ['register_id' => $registration_id],
                ['%s'],
                ['%d']
            );

            // Commit transaction
            $this->db->query('COMMIT');

            wp_send_json_success([
                'registration_id' => $registration_id,
                'customer_id' => $customer_id,
                'client_secret' => $payment_intent->client_secret,
                'country_code' => $country_code
            ]);

        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            error_log('WeVoi Forum Register: Exception caught: ' . $e->getMessage());
            error_log('WeVoi Forum Register: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get role-specific data and validate
     */
    private function get_role_specific_data($user_id, $role, $post_data) {
        if ($role === 'student') {
            // Student requires WhatsApp Number and Passport Number
            $whatsapp = isset($post_data['whatsapp_number']) ? sanitize_text_field(trim($post_data['whatsapp_number'])) : '';
            $passport_number = isset($post_data['passport_number']) ? sanitize_text_field(trim($post_data['passport_number'])) : '';
            if (empty($whatsapp)) {
                return ['valid' => false, 'error' => 'WhatsApp Number is required for students.'];
            }
            if (empty($passport_number)) {
                return ['valid' => false, 'error' => 'Passport Number is required for students.'];
            }
            return ['valid' => true, 'data' => ['whatsapp' => $whatsapp, 'passport_number' => $passport_number]];
        } elseif ($role === 'supervisor' || $role === 'parent' || $role === 'agency') {
            // Supervisor, Parent and Agency require Gender, Date of Birth, WhatsApp Number, Passport Number
            $gender = isset($post_data['gender']) ? sanitize_text_field(trim($post_data['gender'])) : '';
            $birth_date = isset($post_data['birth_date']) ? sanitize_text_field(trim($post_data['birth_date'])) : '';
            $whatsapp = isset($post_data['whatsapp_number']) ? sanitize_text_field(trim($post_data['whatsapp_number'])) : '';
            $passport_number = isset($post_data['passport_number']) ? sanitize_text_field(trim($post_data['passport_number'])) : '';

            if (empty($gender)) {
                return ['valid' => false, 'error' => 'Gender is required.'];
            }
            if (empty($birth_date)) {
                return ['valid' => false, 'error' => 'Date of Birth is required.'];
            }
            if (empty($whatsapp)) {
                return ['valid' => false, 'error' => 'WhatsApp Number is required.'];
            }
            if (empty($passport_number)) {
                return ['valid' => false, 'error' => 'Passport Number is required.'];
            }

            return ['valid' => true, 'data' => ['gender' => $gender, 'birth_date' => $birth_date, 'whatsapp' => $whatsapp, 'passport_number' => $passport_number]];
        }

        return ['valid' => false, 'error' => 'Invalid role.'];
    }

    /**
     * Update user meta for Forum registration
     */
    private function update_user_meta_for_forum($user_id, $role, $post_data) {
        if ($role === 'student') {
            // Update Student WhatsApp Number (separate from supervisor WhatsApp)
            if (isset($post_data['whatsapp_number']) && !empty($post_data['whatsapp_number'])) {
                update_user_meta($user_id, 'studentwhatsapp', sanitize_text_field($post_data['whatsapp_number']));
            }

            // Optional Parent info (Supervisor/Agency is stored per registration in wevoi_registrations.supervisor_id)
            if (isset($post_data['parent_email']) && !empty($post_data['parent_email'])) {
                $parent_email = sanitize_email($post_data['parent_email']);
                $parent_first_name = isset($post_data['parent_first_name']) ? sanitize_text_field(trim($post_data['parent_first_name'])) : '';
                $parent_last_name = isset($post_data['parent_last_name']) ? sanitize_text_field(trim($post_data['parent_last_name'])) : '';
                $parent_whatsapp = isset($post_data['parent_whatsapp']) ? sanitize_text_field(trim($post_data['parent_whatsapp'])) : '';

                // Update student's usermeta
                update_user_meta($user_id, 'parentemail', $parent_email);
                update_user_meta($user_id, 'parentfirstname', $parent_first_name);
                update_user_meta($user_id, 'parentlastname', $parent_last_name);
                update_user_meta($user_id, 'parentwhatsapp', $parent_whatsapp);

                // Check if parent user exists (uses site $table_prefix, e.g. wpie_users).
                $parent_user = $this->db->get_row($this->db->prepare(
                    "SELECT u.ID FROM {$this->db->users} u
                     LEFT JOIN {$this->db->prefix}usermeta um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$this->db->prefix}capabilities'
                     WHERE u.user_email = %s
                     AND um_role.meta_value LIKE %s",
                    $parent_email,
                    '%"parent"%'
                ));

                if (!$parent_user) {
                    // Parent doesn't exist, create account
                    $this->create_supervisor_parent_user($parent_email, $parent_first_name, $parent_last_name, $parent_whatsapp, 'parent');
                }
            }
        } elseif ($role === 'supervisor' || $role === 'parent' || $role === 'agency') {
            // Update Gender, Birth Date, WhatsApp
            if (isset($post_data['gender']) && !empty($post_data['gender'])) {
                update_user_meta($user_id, 'gender', sanitize_text_field($post_data['gender']));
            }
            if (isset($post_data['birth_date']) && !empty($post_data['birth_date'])) {
                update_user_meta($user_id, 'birth_date', sanitize_text_field($post_data['birth_date']));
            }
            if (isset($post_data['whatsapp_number']) && !empty($post_data['whatsapp_number'])) {
                update_user_meta($user_id, 'supervisorwhatsapp', sanitize_text_field($post_data['whatsapp_number']));
            }
        }
    }

    /**
     * Create supervisor or parent user account
     */
    private function create_supervisor_parent_user($email, $first_name, $last_name, $whatsapp, $role) {
        // Generate username from email (before @)
        $email_parts = explode('@', $email);
        $username = sanitize_user($email_parts[0]);

        // Ensure username is unique
        $original_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }

        // Generate random password
        $password = wp_generate_password(12, false);

        // Create user
        $user_id = wp_create_user($username, $password, $email);

        if (!is_wp_error($user_id)) {
            // Set role
            $user_obj = new WP_User($user_id);
            $user_obj->set_role($role);

            // Set user meta
            if (!empty($first_name)) {
                update_user_meta($user_id, 'first_name', $first_name);
            }
            if (!empty($last_name)) {
                update_user_meta($user_id, 'last_name', $last_name);
            }
            if (!empty($whatsapp)) {
                update_user_meta($user_id, 'supervisorwhatsapp', $whatsapp);
            }

            // Send password reset email
            wp_new_user_notification($user_id, null, 'user');
        }
    }

    /**
     * Validate coupon for Forum
     */
    public function validate_coupon() {
        if (ob_get_level()) {
            ob_clean();
        }

        try {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache');

            check_ajax_referer('wevoi_forum_nonce', 'nonce');

            $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;
            $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field(trim($_POST['coupon_code'])) : '';

            if (empty($competition_code) || empty($coupon_code)) {
                wp_send_json_error('Please fill in all fields.');
                return;
            }

            // Get current user and role
            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                wp_send_json_error('User not logged in.');
                return;
            }

            $role = $this->get_user_role_for_pricing($user);

            $coupon = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->db->prefix}wevoi_coupons
                    WHERE coupon_code = %s AND competition_code = %d",
                    $coupon_code,
                    $competition_code
                )
            );

            if (!$coupon) {
                wp_send_json_error('Coupon code not found.');
                return;
            }

            // Validate role (if coupon has specific role, user must match)
            if (!empty($coupon->role) && $coupon->role !== $role) {
                wp_send_json_error('This coupon is not valid for your role.');
                return;
            }

            if ($coupon->coupon_limit > 0 && $coupon->coupon_used >= $coupon->coupon_limit) {
                wp_send_json_error('Coupon usage limit exceeded.');
                return;
            }

            // Determine discount type and value
            $discount_type = 'none';
            $discount_value = 0;

            if (isset($coupon->coupon_discount_amount) && $coupon->coupon_discount_amount !== null && floatval($coupon->coupon_discount_amount) > 0) {
                $discount_type = 'fixed';
                $discount_value = floatval($coupon->coupon_discount_amount);
            } elseif (isset($coupon->coupon_discount_percentage) && $coupon->coupon_discount_percentage !== null && intval($coupon->coupon_discount_percentage) > 0) {
                $discount_type = 'percentage';
                $discount_value = intval($coupon->coupon_discount_percentage);
            }

            wp_send_json_success([
                'coupon_code' => $coupon->coupon_code,
                'discount_type' => $discount_type,
                'discount_value' => $discount_value,
                'discount_value_fixed' => 0,
                'max_discount' => 0
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Create Stripe PaymentIntent for a pre-registered user when payment phase opens.
     */
    public function start_forum_payment() {
        if (ob_get_level()) {
            ob_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        check_ajax_referer('wevoi_forum_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in to continue.');
            return;
        }

        try {
            $user_id = get_current_user_id();
            $user = wp_get_current_user();
            $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
            $payer_name = isset($_POST['payer_name']) ? sanitize_text_field(trim($_POST['payer_name'])) : '';
            $payer_countryid = isset($_POST['payer_countryid']) ? intval($_POST['payer_countryid']) : 0;

            if ($registration_id <= 0 || $payer_name === '' || $payer_countryid <= 0) {
                wp_send_json_error('Missing payer information.');
                return;
            }

            $registration = $this->db->get_row(
                $this->db->prepare(
                    "SELECT r.*, c.competition_type, c.forum_payment_phase, c.competition_name, c.competition_status
                     FROM {$this->db->prefix}wevoi_registrations r
                     JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                     WHERE r.register_id = %d AND r.user_id = %d",
                    $registration_id,
                    $user_id
                )
            );

            if (!$registration || strtoupper(trim((string) $registration->competition_type)) !== 'FORUM') {
                wp_send_json_error('Registration not found.');
                return;
            }

            $paid_raw = trim((string) $registration->paid_status);
            if ($paid_raw === '' && floatval($registration->price) > 0
                && empty($registration->payment_intent_id)
            ) {
                $this->db->update(
                    $this->db->prefix . 'wevoi_registrations',
                    ['paid_status' => 'PRE_REGISTERED'],
                    [
                        'register_id' => $registration_id,
                        'user_id' => $user_id,
                    ],
                    ['%s'],
                    ['%d', '%d']
                );
                $registration->paid_status = 'PRE_REGISTERED';
            }

            if (strtoupper((string) $registration->paid_status) !== 'PRE_REGISTERED') {
                wp_send_json_error('This registration is not awaiting payment.');
                return;
            }

            if ($this->resolve_forum_payment_phase($registration) !== 'payment_required') {
                wp_send_json_error('Payment is not open for this competition yet.');
                return;
            }

            $amount = floatval($registration->price);
            if ($amount <= 0) {
                wp_send_json_error('No payment is due for this registration.');
                return;
            }

            $country = $this->db->get_row(
                $this->db->prepare(
                    "SELECT iso FROM {$this->db->prefix}wevoi_countries WHERE countryid = %d",
                    $payer_countryid
                )
            );

            if (!$country) {
                wp_send_json_error('Invalid country selected.');
                return;
            }

            $country_code = $country->iso;

            $this->db->query('START TRANSACTION');

            $customer_id = $this->stripe_handler->create_customer([
                'name' => $payer_name,
                'email' => $user->user_email,
                'country_id' => $payer_countryid,
                'country_code' => $country_code
            ]);

            if (is_wp_error($customer_id)) {
                throw new Exception($customer_id->get_error_message());
            }

            $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                [
                    'payer_name' => $payer_name,
                    'payer_countryid' => $payer_countryid,
                    'paid_status' => 'PENDING',
                ],
                ['register_id' => $registration_id],
                ['%s', '%d', '%s'],
                ['%d']
            );

            $payment_intent = $this->stripe_handler->create_payment_intent($amount, [
                'registration_id' => $registration_id,
                'competition_code' => (int) $registration->competition_code,
                'competition_name' => $registration->competition_name ? $registration->competition_name : 'WeVoi Forum',
                'customer_email' => $user->user_email,
                'receipt_email' => $user->user_email,
                'user_id' => $user_id,
                'is_forum' => true
            ]);

            if (is_wp_error($payment_intent)) {
                throw new Exception($payment_intent->get_error_message());
            }

            $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                ['payment_intent_id' => $payment_intent->id],
                ['register_id' => $registration_id],
                ['%s'],
                ['%d']
            );

            $this->db->query('COMMIT');

            wp_send_json_success([
                'registration_id' => $registration_id,
                'customer_id' => $customer_id,
                'client_secret' => $payment_intent->client_secret,
                'country_code' => $country_code
            ]);
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            error_log('WeVoi Forum: start_forum_payment error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Update payment status after successful payment
     */
    public function update_payment_status() {
        check_ajax_referer('wevoi_forum_nonce', 'nonce');

        try {
            // Start transaction
            $this->db->query('START TRANSACTION');

            $payment_intent_id = sanitize_text_field($_POST['payment_intent_id']);
            $customer_id = sanitize_text_field($_POST['customer_id']);
            $registration_id = intval($_POST['registration_id']);

            // Get registration details including coupon code and competition type
            $registration = $this->db->get_row($this->db->prepare(
                "SELECT r.*, c.competition_type
                 FROM {$this->db->prefix}wevoi_registrations r
                 JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                 WHERE r.register_id = %d
                 AND r.paid_status = 'PENDING'
                 AND r.payment_intent_id IS NOT NULL",
                $registration_id
            ));

            if (!$registration) {
                throw new Exception('Registration not found or not in pending status');
            }

            // Determine if this is a Forum registration
            $is_forum = ($registration->competition_type === 'Forum');

            // Get user role for Forum email notifications
            $user_role = null;
            if ($is_forum) {
                $user = get_user_by('id', $registration->user_id);
                if ($user) {
                    $user_roles = (array) $user->roles;
                    if (in_array('student', $user_roles)) {
                        $user_role = 'student';
                    } elseif (in_array('supervisor', $user_roles)) {
                        $user_role = 'supervisor';
                    } elseif (in_array('parent', $user_roles)) {
                        $user_role = 'parent';
                    } elseif (in_array('agency', $user_roles)) {
                        $user_role = 'agency';
                    }
                }
            }

            // Update registration status
            // Forum kayıtları için application_status direkt APPLICATION_ACCEPTED olarak ayarlanır
            $update_data = [
                'paid_status' => 'PAID',
                'customer_code' => $customer_id
            ];

            if ($is_forum) {
                $update_data['application_status'] = 'APPLICATION_ACCEPTED';
            }

            $updated = $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                $update_data,
                ['register_id' => $registration_id],
                $is_forum ? ['%s', '%s', '%s'] : ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                throw new Exception('Failed to update registration status');
            }

            // Update coupon usage if coupon was used
            if (!empty($registration->coupon_code)) {
                // Get and lock current coupon usage
                $initial_usage = $this->db->get_var($this->db->prepare(
                    "SELECT coupon_used FROM {$this->db->prefix}wevoi_coupons
                    WHERE coupon_code = %s FOR UPDATE",
                    $registration->coupon_code
                ));

                // Update the usage count
                $update_result = $this->db->query($this->db->prepare(
                    "UPDATE {$this->db->prefix}wevoi_coupons
                    SET coupon_used = %d
                    WHERE coupon_code = %s",
                    intval($initial_usage) + 1,
                    $registration->coupon_code
                ));

                if ($update_result === false) {
                    throw new Exception('Failed to update coupon usage');
                }

                // Verify the update
                $final_usage = $this->db->get_var($this->db->prepare(
                    "SELECT coupon_used FROM {$this->db->prefix}wevoi_coupons
                    WHERE coupon_code = %s",
                    $registration->coupon_code
                ));

                if ($final_usage <= $initial_usage) {
                    throw new Exception('Coupon usage update failed verification');
                }
            }

            // Send confirmation emails
            // For Forum registrations, use special email handler that sends to student, supervisor, and parent
            if ($is_forum && $user_role) {
                $this->email_handler->send_forum_registration_emails($registration_id, $registration->user_id, $user_role, false);
            } else {
                // For non-Forum registrations, use standard email handler
                $this->email_handler->send_emails($registration_id);
            }

            // Commit the transaction
            $this->db->query('COMMIT');

            wp_send_json_success('Payment status updated');

        } catch (Exception $e) {
            // Rollback on error
            $this->db->query('ROLLBACK');
            error_log('WeVoi Forum: update_payment_status error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get supervisor or parent info by email
     * Checks the WordPress users table first, then falls back to student's usermeta
     */
    public function get_supervisor_parent_info() {
        check_ajax_referer('wevoi_forum_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in.');
            return;
        }

        $email = isset($_POST['email']) ? sanitize_email(trim($_POST['email'])) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : ''; // 'supervisor' or 'parent'

        if (empty($email) || empty($type) || !in_array($type, array('supervisor', 'parent'))) {
            wp_send_json_error('Invalid parameters.');
            return;
        }

        $user_id = get_current_user_id();
        $result = array(
            'email' => $email,
            'first_name' => '',
            'last_name' => '',
            'whatsapp' => '',
            'source' => 'none'
        );

        // First, check if user exists in the WordPress users table with the specified role
        $user = $this->db->get_row($this->db->prepare(
            "SELECT u.ID, u.user_email,
                    um_first.meta_value as first_name,
                    um_last.meta_value as last_name,
                    um_whatsapp.meta_value as whatsapp
             FROM {$this->db->users} u
             LEFT JOIN {$this->db->prefix}usermeta um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
             LEFT JOIN {$this->db->prefix}usermeta um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
             LEFT JOIN {$this->db->prefix}usermeta um_whatsapp ON u.ID = um_whatsapp.user_id AND um_whatsapp.meta_key = 'supervisorwhatsapp'
             LEFT JOIN {$this->db->prefix}usermeta um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$this->db->prefix}capabilities'
             WHERE u.user_email = %s
             AND um_role.meta_value LIKE %s",
            $email,
            '%"' . $type . '"%'
        ));

        if ($user) {
            // User exists with the specified role
            $result['first_name'] = $user->first_name ?: '';
            $result['last_name'] = $user->last_name ?: '';
            $result['whatsapp'] = $user->whatsapp ?: '';
            $result['source'] = 'user_table';
        } else {
            // User doesn't exist, check student's usermeta
            if ($type === 'supervisor') {
                $supervisor_email = get_user_meta($user_id, 'supervisoremail', true);
                if ($supervisor_email === $email) {
                    $supervisor_fullname = get_user_meta($user_id, 'supervisorfullname', true);
                    $supervisor_whatsapp = get_user_meta($user_id, 'supervisorwhatsapp', true);

                    if (!empty($supervisor_fullname)) {
                        $name_parts = explode(' ', trim($supervisor_fullname), 2);
                        $result['first_name'] = !empty($name_parts[0]) ? $name_parts[0] : '';
                        $result['last_name'] = (count($name_parts) > 1 && !empty($name_parts[1])) ? $name_parts[1] : '';
                    }
                    $result['whatsapp'] = $supervisor_whatsapp ?: '';
                    $result['source'] = 'student_meta';
                }
            } elseif ($type === 'parent') {
                $parent_email = get_user_meta($user_id, 'parentemail', true);
                if ($parent_email === $email) {
                    $parent_first_name = get_user_meta($user_id, 'parentfirstname', true);
                    $parent_last_name = get_user_meta($user_id, 'parentlastname', true);
                    $parent_whatsapp = get_user_meta($user_id, 'parentwhatsapp', true);

                    $result['first_name'] = $parent_first_name ?: '';
                    $result['last_name'] = $parent_last_name ?: '';
                    $result['whatsapp'] = $parent_whatsapp ?: '';
                    $result['source'] = 'student_meta';
                }
            }
        }

        wp_send_json_success($result);
    }

    /**
     * Enqueue assets
     */
    private function enqueue_assets() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script(
            'wevoi-forum-form-handler',
            WEVOI_REGISTER_PLUGIN_URL . 'public/js/forum-form-handler.js',
            ['jquery', 'stripe-js'],
            WEVOI_REGISTER_VERSION,
            true
        );

        $publishable_key = $this->stripe_handler->get_publishable_key();
        if (empty($publishable_key)) {
            error_log('WeVoi Forum: Stripe publishable key is empty');
        }

        // Get current user role for redirect
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;
        $redirect_url = 'https://www.wevoi.org/submissions/'; // Default
        if (in_array('student', $user_roles)) {
            $redirect_url = 'https://www.wevoi.org/student-dashboard/';
        } elseif (in_array('supervisor', $user_roles)) {
            $redirect_url = 'https://www.wevoi.org/supervisor-dashboard/';
        } elseif (in_array('parent', $user_roles)) {
            $redirect_url = 'https://www.wevoi.org/parent-dashboard/';
        } elseif (in_array('agency', $user_roles)) {
            $redirect_url = 'https://www.wevoi.org/agency-dashboard/';
        }

        wp_localize_script(
            'wevoi-forum-form-handler',
            'wevoiForum',
            array_merge(
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wevoi_forum_nonce'),
                    'registerNonce' => wp_create_nonce('wevoi_register_nonce'),
                    'publishableKey' => $publishable_key,
                    'redirectUrl' => $redirect_url
                ],
                $this->forum_script_config
            )
        );

        wp_enqueue_style(
            'wevoi-forum-form-styles',
            WEVOI_REGISTER_PLUGIN_URL . 'public/css/forum-form-styles.css',
            [],
            WEVOI_REGISTER_VERSION
        );
    }
}

