<?php
if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Register_Form_Handler {
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

    // Existing hooks
    add_shortcode('wevoi_register_form', array($this, 'render_form'));
    add_action('wp_ajax_wevoi_validate_coupon', array($this, 'validate_coupon'));
    add_action('wp_ajax_wevoi_process_registration', array($this, 'process_registration'));
    add_action('wp_ajax_wevoi_get_competitions', array($this, 'get_competitions'));
    add_action('wp_ajax_wevoi_update_payment_status', array($this, 'update_payment_status'));
    add_action('wp_ajax_wevoi_lookup_supervisor_by_email', array($this, 'ajax_lookup_supervisor_by_email'));

    // New hooks
    add_shortcode('wevoi_my_competitions', array($this, 'render_my_competitions'));
    add_action('wp_ajax_wevoi_update_application_status', array($this, 'update_application_status'));

    // Video update hook
    add_action('wp_ajax_wevoi_update_video_link', array($this, 'update_video_link'));

    // Topics AJAX hook
    add_action('wp_ajax_wevoi_get_topics', array($this, 'get_topics'));

}

    public function render_form() {
        if (!is_user_logged_in()) {
            return '<div style="text-align: center;
                                margin-top: 20px;"><p style="font-weight: bold;">
                                Please <a href="https://www.wevoi.org/wp-login.php" style="text-decoration: none; color: blue;">log in</a> to submit a video.
                                </p></div>';
        }

        if (!$this->user_has_student_role()) {
            return '<div style="text-align: center; margin-top: 20px;"><p style="font-weight: bold;">To submit a video, please <a href="https://www.wevoi.org/wp-login.php" target="_blank">log in</a> with a student account.</p></div>';

        }

        $all_active_competitions = $this->get_active_competitions();
        $user_id = get_current_user_id();
        $db = $this->db;
        $competitions = array_values(array_filter($all_active_competitions, function ($c) use ($user_id, $db) {
            return WeVoi_Summit_Eligibility::user_may_register_for_competition($db, $user_id, $c);
        }));
        if (empty($competitions)) {
            if (!empty($all_active_competitions)
                && WeVoi_Summit_Eligibility::active_list_is_only_final_competitions($all_active_competitions)) {
                return WeVoi_Summit_Eligibility::get_unqualified_registration_notice_html();
            }
            return '<p>No active competitions available.</p>';
        }

        // Get current user's age category
        $age_category_id = $this->get_user_age_category_id($user_id);
        $age_category_name = null;
        if ($age_category_id) {
            $age_category = $this->db->get_row($this->db->prepare(
                "SELECT age_category FROM {$this->db->prefix}wevoi_age_categories WHERE id = %d",
                $age_category_id
            ));
            $age_category_name = $age_category ? $age_category->age_category : null;
        }

        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $student_name = trim($first_name . ' ' . $last_name);
        if ($student_name === '') {
            $current_user = wp_get_current_user();
            $student_name = $current_user ? trim($current_user->display_name) : '';
        }

        $student_country = '';
        $country_id = get_user_meta($user_id, 'countryid', true);
        if ($country_id) {
            $country_row = $this->db->get_row($this->db->prepare(
                "SELECT countryname FROM {$this->db->prefix}wevoi_countries WHERE countryid = %d",
                intval($country_id)
            ));
            $student_country = $country_row ? $country_row->countryname : '';
        }

        ob_start();
        include WEVOI_REGISTER_PLUGIN_DIR . 'public/templates/payment-form.php';
        $form_html = ob_get_clean();

        $this->enqueue_assets();

        return $form_html;
    }

    private function user_has_student_role() {
        $user = wp_get_current_user();
        return in_array('student', (array) $user->roles);
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
     * Get age category ID for a user based on their birth date
     */
    private function get_user_age_category_id($user_id) {
        $birth_date = get_user_meta($user_id, 'birth_date', true);
        if (empty($birth_date)) {
            return null;
        }

        $age = $this->calculate_age($birth_date);
        return $this->get_age_category_id($age);
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

public function validate_coupon() {
    // Clear output buffer first
    if (ob_get_level()) {
        ob_clean();
    }

    try {
        // Set headers
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        // Nonce check
        check_ajax_referer('wevoi_register_nonce', 'nonce');

        $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field(trim($_POST['coupon_code'])) : '';

        if (empty($competition_code) || empty($coupon_code)) {
            echo json_encode([
                'success' => false,
                'data' => 'Please fill in all fields.'
            ]);
            die();
        }

        // First check if coupon exists (without competition status check)
        $coupon_exists = $this->db->get_row($this->db->prepare(
            "SELECT c.*, comp.competition_status, comp.competition_price
             FROM {$this->db->prefix}wevoi_coupons c
             LEFT JOIN {$this->db->prefix}wevoi_competitions comp
             ON c.competition_code = comp.competition_code
             WHERE c.coupon_code = %s",
            $coupon_code
        ));

        if (!$coupon_exists) {
            echo json_encode([
                'success' => false,
                'data' => 'Coupon code not found. Please check the code and try again.'
            ]);
            die();
        }

        // Check if coupon is for the selected competition
        if ($coupon_exists->competition_code != $competition_code) {
            echo json_encode([
                'success' => false,
                'data' => 'This coupon is not valid for the selected competition.'
            ]);
            die();
        }

        // Check if competition is active
        if (!$coupon_exists->competition_status || $coupon_exists->competition_status !== 'ON AIR') {
            echo json_encode([
                'success' => false,
                'data' => 'The competition for this coupon is not currently active.'
            ]);
            die();
        }

        $coupon = $coupon_exists;

        // Check expiration date if field exists and is set
        if (property_exists($coupon, 'coupon_limit_expiration_date') && $coupon->coupon_limit_expiration_date !== null) {
            $expiration_date = new DateTime($coupon->coupon_limit_expiration_date);
            $current_date = new DateTime();

            if ($current_date > $expiration_date) {
                echo json_encode([
                    'success' => false,
                    'data' => 'This coupon has expired.'
                ]);
                die();
            }
        }

        // Check usage limit if no expiration date is set or if both are set
        if ($coupon->coupon_limit > 0) {
            if ($coupon->coupon_used >= $coupon->coupon_limit) {
                echo json_encode([
                    'success' => false,
                    'data' => 'This coupon code has reached its usage limit.'
                ]);
                die();
            }
        }

        // Calculate discount and prepare message
        $discount_amount = 0;
        $discount_percentage = 0;
        $message = '';

        // Check if coupon has discount amount (check for NULL and > 0)
        if (isset($coupon->coupon_discount_amount) && $coupon->coupon_discount_amount !== null && floatval($coupon->coupon_discount_amount) > 0) {
            // Fixed amount discount
            $discount_amount = floatval($coupon->coupon_discount_amount);
            $message = "Your coupon was successfully applied, giving you a £{$discount_amount} discount.";
        }
        // Check if coupon has discount percentage (check for NULL and > 0)
        elseif (isset($coupon->coupon_discount_percentage) && $coupon->coupon_discount_percentage !== null && intval($coupon->coupon_discount_percentage) > 0) {
            // Percentage discount
            $discount_percentage = intval($coupon->coupon_discount_percentage);
            $calculated_discount = ($coupon->competition_price * $discount_percentage) / 100;
            $message = "Your coupon was successfully applied, giving you a {$discount_percentage}% discount.";
        } else {
            // No discount found
            echo json_encode([
                'success' => false,
                'data' => 'This coupon has no discount configured.'
            ]);
            die();
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'discount_amount' => $discount_amount,
                'discount_percentage' => $discount_percentage,
                'message' => $message
            ]
        ]);
        die();

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'data' => 'An error occurred while validating the coupon: ' . $e->getMessage()
        ]);
        die();
    }
}



private function validate_youtube_link($url) {
    // Trim and clean the URL
    $url = trim($url);

    // Array of valid YouTube URL patterns
    $patterns = array(
        'standard' => '/^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/watch\?(?:.*&)?v=([a-zA-Z0-9_-]{11})(?:\S*)?$/',
        'shortened' => '/^(?:https?:\/\/)?(?:www\.|m\.)?youtu\.be\/([a-zA-Z0-9_-]{11})(?:\S*)?$/',
        'shorts' => '/^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})(?:\S*)?$/',
        'embed' => '/^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})(?:\S*)?$/'
    );

    // Test URL against each pattern
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }

    return false;
}

/**
 * AJAX: Look up a Supervisor or Agency user by email. Returns id, first_name, last_name if found and role is supervisor or agency.
 */
public function ajax_lookup_supervisor_by_email() {
    check_ajax_referer('wevoi_register_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Not logged in'));
        return;
    }
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    if (empty($email)) {
        wp_send_json_error(array('message' => 'Email required'));
        return;
    }
    $user = get_user_by('email', $email);
    if (!$user) {
        wp_send_json_error(array('message' => 'No WeVoi account found with this email. Please enter a valid Supervisor/Agency email if you know one, or leave the field blank. Registration cannot continue with an email that is not in the system.'));
        return;
    }
    $roles = (array) $user->roles;
    if (!in_array('supervisor', $roles) && !in_array('agency', $roles)) {
        wp_send_json_error(array('message' => 'This account is not a Supervisor or Agency.'));
        return;
    }
    $first = get_user_meta($user->ID, 'first_name', true);
    $last = get_user_meta($user->ID, 'last_name', true);
    wp_send_json_success(array(
        'id' => $user->ID,
        'first_name' => $first ?: '',
        'last_name' => $last ?: '',
        'display_name' => trim($first . ' ' . $last) ?: $user->display_name
    ));
}

public function process_registration() {
    if (ob_get_level()) {
        ob_clean();
    }

    try {
        // Set headers
        header('Content-Type: application/json');

        // Debug logging
        error_log('WeVoi Register: process_registration started');
        error_log('WeVoi Register: POST data: ' . print_r($_POST, true));


        // Verify nonce
        error_log('WeVoi Register: Verifying nonce');
        check_ajax_referer('wevoi_register_nonce', 'nonce');
        error_log('WeVoi Register: Nonce verified successfully');

        $user_id = get_current_user_id();
        error_log('WeVoi Register: User ID: ' . $user_id);
        if (!$user_id) {
            error_log('WeVoi Register: User not logged in');
            wp_send_json_error('User not logged in');
            return;
        }

        // Get and validate form data
        $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;
        $youtube_link = isset($_POST['youtube_link']) ? esc_url_raw($_POST['youtube_link']) : '';
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
        $payer_name = isset($_POST['payer_name']) ? sanitize_text_field($_POST['payer_name']) : '';
        $payer_countryid = isset($_POST['payer_countryid']) ? intval($_POST['payer_countryid']) : 0;
        $supervisor_id = isset($_POST['supervisor_id']) && $_POST['supervisor_id'] !== '' ? intval($_POST['supervisor_id']) : null;


        // Validate YouTube link
        if (!$this->validate_youtube_link($youtube_link)) {
            wp_send_json_error('Invalid YouTube link format');
            return;
        }

        // Validate required fields
        if (empty($competition_code) || empty($youtube_link)) {

            wp_send_json_error('Missing required fields');
            return;
        }

        $this->db->query('START TRANSACTION');

        // Get competition details
        $competition = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->db->prefix}wevoi_competitions
                WHERE competition_code = %d AND competition_status = 'ON AIR'",
                $competition_code
            )
        );

        if (!$competition) {
            // Debug: Check what competition status actually is
            $debug_competition = $this->db->get_row(
                $this->db->prepare(
                    "SELECT competition_code, competition_name, competition_status FROM {$this->db->prefix}wevoi_competitions WHERE competition_code = %d",
                    $competition_code
                )
            );

            $error_msg = 'Invalid competition';
            if ($debug_competition) {
                $error_msg .= ' (Status: ' . $debug_competition->competition_status . ')';
            }
            wp_send_json_error($error_msg);
            return;
        }

        if (!WeVoi_Summit_Eligibility::user_may_register_for_competition($this->db, $user_id, $competition)) {
            $this->db->query('ROLLBACK');
            wp_send_json_error(WeVoi_Summit_Eligibility::get_denial_message());
            return;
        }

        $price = $competition->competition_price;
        $is_free = false;

        // Process coupon if exists
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
                // Check if coupon has discount amount (check for NULL and > 0)
                if (isset($coupon->coupon_discount_amount) && $coupon->coupon_discount_amount !== null && floatval($coupon->coupon_discount_amount) > 0) {
                    $discount_amount = floatval($coupon->coupon_discount_amount);
                    if ($discount_amount >= $price) {
                        $is_free = true;
                        $price = 0;
                    } else {
                        $price = $price - $discount_amount;
                    }
                }
                // Check if coupon has discount percentage (check for NULL and > 0)
                elseif (isset($coupon->coupon_discount_percentage) && $coupon->coupon_discount_percentage !== null && intval($coupon->coupon_discount_percentage) > 0) {
                    $discount = ($price * intval($coupon->coupon_discount_percentage)) / 100;
                    if ($discount >= $price) {
                        $is_free = true;
                        $price = 0;
                    } else {
                        $price = $price - $discount;
                    }
                }
            }
        }

        if ($is_free) {
            $result = $this->process_free_registration($user_id, $competition_code, $youtube_link, $coupon_code, $supervisor_id);
            $this->db->query('COMMIT');
            return;
        } else {

            if (empty($payer_name) || empty($payer_countryid)) {
                wp_send_json_error('Missing payer information');
                return;
            }

            $result = $this->process_paid_registration(
                $user_id,
                $competition_code,
                $youtube_link,
                $coupon_code,
                $price,
                $payer_name,
                $payer_countryid,
                $supervisor_id
            );
            $this->db->query('COMMIT');
            return;
        }

    } catch (Exception $e) {
        $this->db->query('ROLLBACK');
        wp_send_json_error($e->getMessage());
    }
}

private function process_free_registration($user_id, $competition_code, $youtube_link, $coupon_code, $supervisor_id = null) {
    try {
        if (ob_get_level()) {
            ob_clean();
        }


        // Start transaction
        $this->db->query('START TRANSACTION');

        // Calculate age category ID
        $age_category_id = $this->get_user_age_category_id($user_id);

        $inserted = $this->db->insert(
            $this->db->prefix . 'wevoi_registrations',
            [
                'user_id' => $user_id,
                'supervisor_id' => $supervisor_id,
                'competition_code' => $competition_code,
                'youtube_link' => $youtube_link,
                'age_category_id' => $age_category_id,
                'paid_status' => 'FREE',
                'price' => 0,
                'coupon_code' => $coupon_code,
                'registration_date' => current_time('mysql')
            ],
            [
                '%d', '%d', '%d', '%s', '%d', '%s', '%f', '%s', '%s'
            ]
        );

        if (!$inserted) {
            throw new Exception('Failed to insert registration');
        }

        $registration_id = $this->db->insert_id;

        // Coupon usage update
        if (!empty($coupon_code)) {


            // İlk kullanım değerini al ve kilitle
            $initial_usage = $this->db->get_var($this->db->prepare(
                "SELECT coupon_used FROM {$this->db->prefix}wevoi_coupons WHERE coupon_code = %s FOR UPDATE",
                $coupon_code
            ));


            // Güncelleme yap
            $update_result = $this->db->query($this->db->prepare(
                "UPDATE {$this->db->prefix}wevoi_coupons
                SET coupon_used = %d
                WHERE coupon_code = %s",
                intval($initial_usage) + 1,
                $coupon_code
            ));

            if ($update_result === false) {
                throw new Exception('Failed to update coupon usage');
            }

            // Son değeri kontrol et
            $final_usage = $this->db->get_var($this->db->prepare(
                "SELECT coupon_used FROM {$this->db->prefix}wevoi_coupons WHERE coupon_code = %s",
                $coupon_code
            ));


            if ($final_usage <= $initial_usage) {
                throw new Exception('Coupon usage update failed verification');
            }

        }

        // Send emails
        $this->email_handler->send_emails($registration_id);

        // Commit transaction
        $this->db->query('COMMIT');

        wp_send_json_success(['message' => 'Registration successful']);
        return true;

    } catch (Exception $e) {
        // Rollback on error
        $this->db->query('ROLLBACK');

        wp_send_json_error($e->getMessage());
        return false;
    }
}




private function process_paid_registration($user_id, $competition_code, $youtube_link, $coupon_code, $price, $payer_name, $payer_countryid, $supervisor_id = null) {
    if (ob_get_level()) {
        ob_clean();
    }

    try {
        // Start transaction
        $this->db->query('START TRANSACTION');


        $user = get_user_by('id', $user_id);
        $user_email = $user->user_email;

        // Get country code
        $country = $this->db->get_row(
            $this->db->prepare(
                "SELECT iso FROM {$this->db->prefix}wevoi_countries WHERE countryid = %d",
                $payer_countryid
            )
        );

        if (!$country) {
            throw new Exception('Invalid country selected');
        }

        // Create Stripe customer
        $customer_id = $this->stripe_handler->create_customer([
            'name' => $payer_name,
            'email' => $user_email,
            'country_id' => $payer_countryid,
            'country_code' => $country->iso
        ]);

        if (is_wp_error($customer_id)) {
            throw new Exception($customer_id->get_error_message());
        }


        // Calculate age category ID
        $age_category_id = $this->get_user_age_category_id($user_id);

        // First insert registration to get the ID
        $inserted = $this->db->insert(
            $this->db->prefix . 'wevoi_registrations',
            [
                'user_id' => $user_id,
                'supervisor_id' => $supervisor_id,
                'competition_code' => $competition_code,
                'youtube_link' => $youtube_link,
                'age_category_id' => $age_category_id,
                'payer_name' => $payer_name,
                'payer_countryid' => $payer_countryid,
                'paid_status' => 'PENDING',
                'price' => $price,
                'coupon_code' => $coupon_code,
                'registration_date' => current_time('mysql')
            ],
            [
                '%d', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%f', '%s', '%s'
            ]
        );

        if (!$inserted) {
            throw new Exception('Failed to insert registration');
        }

        $registration_id = $this->db->insert_id;

        // Get competition name for Stripe description
        $competition_info = $this->db->get_row(
            $this->db->prepare(
                "SELECT competition_name FROM {$this->db->prefix}wevoi_competitions WHERE competition_code = %d",
                $competition_code
            )
        );

        // Create payment intent with registration ID in metadata
        error_log('WeVoi Register: Creating payment intent for amount: ' . $price);
        $payment_intent = $this->stripe_handler->create_payment_intent($price, [
            'registration_id' => $registration_id,
            'competition_code' => $competition_code,
            'competition_name' => $competition_info ? $competition_info->competition_name : 'Unknown Competition',
            'customer_email' => $user_email,
            'receipt_email' => $user_email,
            'user_id' => $user_id
        ]);

        if (is_wp_error($payment_intent)) {
            error_log('WeVoi Register: Payment intent creation failed: ' . $payment_intent->get_error_message());
            throw new Exception($payment_intent->get_error_message());
        }
        error_log('WeVoi Register: Payment intent created successfully: ' . $payment_intent->id);

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
            'country_code' => $country->iso
        ]);

    } catch (Exception $e) {
        error_log('WeVoi Register: Exception caught: ' . $e->getMessage());
        error_log('WeVoi Register: Exception trace: ' . $e->getTraceAsString());
        $this->db->query('ROLLBACK');

        wp_send_json_error($e->getMessage());
    }
}








public function update_payment_status() {
    check_ajax_referer('wevoi_register_nonce', 'nonce');

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
        $this->email_handler->send_emails($registration_id);

        // Commit the transaction
        $this->db->query('COMMIT');

        wp_send_json_success('Payment status updated');

    } catch (Exception $e) {
        // Rollback on error
        $this->db->query('ROLLBACK');
        wp_send_json_error($e->getMessage());
    }
}






private function update_coupon_usage($coupon_code) {
    try {

        // SQL sorgusunu hazırla
        $sql = $this->db->prepare(
            "UPDATE {$this->db->prefix}wevoi_coupons
            SET coupon_used = IFNULL(coupon_used, 0) + 1
            WHERE coupon_code = %s",
            $coupon_code
        );


        // Güncellemeyi yap
        $result = $this->db->query($sql);


        if ($result === false) {
            throw new Exception('Failed to update coupon usage. MySQL Error: ' . $this->db->last_error);
        }

        // Güncellenmiş değeri kontrol et
        $updated_value = $this->db->get_var($this->db->prepare(
            "SELECT coupon_used FROM {$this->db->prefix}wevoi_coupons WHERE coupon_code = %s",
            $coupon_code
        ));

        return true;
    } catch (Exception $e) {
        throw $e;
    }
}






   public function update_video_link() {
    try {
        check_ajax_referer('wevoi_register_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
        $new_video_link = isset($_POST['video_link']) ? esc_url_raw($_POST['video_link']) : '';

        // Önce mevcut video linkini alalım
        $current_video_link = $this->db->get_var($this->db->prepare(
            "SELECT youtube_link FROM {$this->db->prefix}wevoi_registrations WHERE register_id = %d",
            $registration_id
        ));

        // Eğer yeni link mevcut linkle aynıysa, güncelleme yapmayalım
        if ($current_video_link === $new_video_link) {
            wp_send_json_error('Video link is the same as current one');
            return;
        }

        // Check if competition is ON AIR - only allow video updates for ON AIR competitions
        $competition_status = $this->db->get_var($this->db->prepare(
            "SELECT c.competition_status
             FROM {$this->db->prefix}wevoi_registrations r
             JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             WHERE r.register_id = %d",
            $registration_id
        ));

        if ($competition_status !== 'ON AIR') {
            wp_send_json_error('Video updates are only allowed for competitions that are currently ON AIR');
            return;
        }

        if (!$this->validate_youtube_link($new_video_link)) {
            wp_send_json_error('Invalid YouTube link format');
            return;
        }

        // Get user information for email notification
        $user_info = $this->db->get_row($this->db->prepare(
            "SELECT u.user_email,
                    CONCAT(um_first.meta_value, ' ', um_last.meta_value) as student_name,
                    c.competition_name
             FROM {$this->db->prefix}wevoi_registrations r
             JOIN {$this->db->prefix}users u ON r.user_id = u.ID
             JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             LEFT JOIN {$this->db->prefix}usermeta um_first ON r.user_id = um_first.user_id AND um_first.meta_key = 'first_name'
             LEFT JOIN {$this->db->prefix}usermeta um_last ON r.user_id = um_last.user_id AND um_last.meta_key = 'last_name'
             WHERE r.register_id = %d",
            $registration_id
        ));

        // Update video link
        $updated = $this->db->update(
            $this->db->prefix . 'wevoi_registrations',
            ['youtube_link' => $new_video_link],
            ['register_id' => $registration_id],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error('Failed to update video link');
            return;
        }

        // Send notification email to admin with HTML template
        $this->send_admin_video_update_notification($user_info, $new_video_link);

        wp_send_json_success('Video link updated successfully');

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}










public function update_application_status() {
    try {
        if (!current_user_can('administrator')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        check_ajax_referer('wevoi_register_nonce', 'nonce');

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        $allowed_statuses = array(
            'PENDING_REVIEW',
            'APPLICATION_ACCEPTED',
            'VIDEO_INACCESSIBLE',
            'IRRELEVANT_CONTENT',
            'APPLICATION_REJECTED',
        );
        if (!in_array($new_status, $allowed_statuses, true)) {
            wp_send_json_error('Invalid status');
            return;
        }

        // Get user and supervisor information (supervisor from registration, not usermeta)
        $user_info = $this->db->get_row($this->db->prepare(
            "SELECT u.user_email,
                    sup_u.user_email as supervisor_email,
                    TRIM(CONCAT(COALESCE(sup_first.meta_value,''), ' ', COALESCE(sup_last.meta_value,''))) as supervisor_name,
                    c.competition_name,
                    CONCAT(um_first.meta_value, ' ', um_last.meta_value) as student_name
             FROM {$this->db->prefix}wevoi_registrations r
             JOIN {$this->db->prefix}users u ON r.user_id = u.ID
             JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             LEFT JOIN {$this->db->prefix}usermeta um_first ON r.user_id = um_first.user_id AND um_first.meta_key = 'first_name'
             LEFT JOIN {$this->db->prefix}usermeta um_last ON r.user_id = um_last.user_id AND um_last.meta_key = 'last_name'
             LEFT JOIN {$this->db->prefix}users sup_u ON r.supervisor_id = sup_u.ID
             LEFT JOIN {$this->db->prefix}usermeta sup_first ON sup_u.ID = sup_first.user_id AND sup_first.meta_key = 'first_name'
             LEFT JOIN {$this->db->prefix}usermeta sup_last ON sup_u.ID = sup_last.user_id AND sup_last.meta_key = 'last_name'
             WHERE r.register_id = %d",
            $registration_id
        ));

        $updated = $this->db->update(
            $this->db->prefix . 'wevoi_registrations',
            ['application_status' => $new_status],
            ['register_id' => $registration_id],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error('Update failed');
            return;
        }

        $this->send_status_update_notifications($new_status, $user_info);

        wp_send_json_success([
            'message' => 'Status updated successfully',
            'status'  => $new_status,
        ]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

private function send_status_update_notifications($status, $user_info) {
    $subject = "WeVoi Competition - Application Status Update";

    // Get status-specific message and color
    $status_info = $this->get_status_message_and_color($status);

    // Email template for student
    $student_html = $this->get_email_template([
        'recipient_name' => $user_info->student_name,
        'competition_name' => $user_info->competition_name,
        'status' => $status,
        'message' => $status_info['message'],
        'color' => $status_info['color']
    ]);

    // Send to student with HTML format
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($user_info->user_email, $subject, $student_html, $headers);

    // Send to supervisor if exists
    if (!empty($user_info->supervisor_email)) {
        $supervisor_html = $this->get_email_template([
            'recipient_name' => $user_info->supervisor_name,
            'student_name' => $user_info->student_name,
            'competition_name' => $user_info->competition_name,
            'status' => $status,
            'message' => $status_info['message'],
            'color' => $status_info['color'],
            'is_supervisor' => true
        ]);

        wp_mail($user_info->supervisor_email, $subject, $supervisor_html, $headers);
    }
}


private function get_status_message_and_color($status) {
    switch ($status) {
        case 'VIDEO_INACCESSIBLE':
            return [
                'message' => "Your video link is broken or inaccessible. Please update your video link.",
                'color' => '#dc3545' // red
            ];
        case 'IRRELEVANT_CONTENT':
            return [
                'message' => "Your video content does not match with the competition topic. Please submit a relevant video.",
                'color' => '#ffc107' // yellow
            ];
        case 'APPLICATION_REJECTED':
            return [
                'message' => "Your application has been rejected. Please check the competition rules and update your video.",
                'color' => '#dc3545' // red
            ];
        case 'PENDING_REVIEW':
            return [
                'message' => "Your application is under review and will be finalized as soon as possible.",
                'color' => '#fd7e14' // orange
            ];
        case 'APPLICATION_ACCEPTED':
            return [
                'message' => "Congratulations, your application has been accepted. After the deadline, your video will be evaluated by our esteemed judges, and the results will be announced.",
                'color' => '#28a745' // green
            ];
        default:
            return [
                'message' => "Your application status has been updated.",
                'color' => '#6c757d' // gray
            ];
    }
}



private function get_email_template($data) {
    $wevoi_logo_url = 'https://www.wevoi.org/wp-content/uploads/2018/08/WeVoi_Logo_XS-e1533120407306.png';
    $status_badge = $this->get_status_badge($data['status'], $data['color']);

    // Build the message based on whether it's for supervisor or student
    $message_intro = $data['is_supervisor'] ?? false
        ? "The application status of your student {$data['student_name']}"
        : "Your application status";

    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
            .email-container { max-width: 600px; margin: 0 auto; }
             .header { background-color: #EFE8E8; color: white; padding: 20px; text-align: center; }
            .header img { max-width: 200px; height: auto; }
            .content { padding: 30px; background-color: #ffffff; }
            .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .status-badge {
                display: inline-block;
                padding: 8px 15px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: bold;
                color: white;
                margin: 15px 0;
            }
            .message-box {
                background-color: #f8f9fa;
                border-left: 4px solid ' . $data['color'] . ';
                padding: 15px;
                margin: 20px 0;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #EFE8E8;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 20px;
            }
            @media only screen and (max-width: 600px) {
                .content { padding: 15px; }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <img src="' . $wevoi_logo_url . '" alt="WeVoi Logo">
            </div>
            <div class="content">
                <h2>Dear ' . $data['recipient_name'] . ',</h2>
                <p>' . $message_intro . ' for the competition "' . $data['competition_name'] . '" has been updated.</p>

                ' . $status_badge . '

                <div class="message-box">
                    ' . $data['message'] . '
                </div>
                <a href="https://www.wevoi.org/submissions/" class="button" style="color: black !important; text-decoration: none;">View My Submissions</a>
            </div>
            <div class="footer">
                <p>This is an automated message from WeVoi Competition System. Please do not reply to this email.</p>
                <p>&copy; ' . date('Y') . ' WeVoi. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
}

private function get_status_badge($status, $color) {
    $status_display = str_replace('_', ' ', $status);
    return '<div class="status-badge" style="background-color: ' . $color . ';">' .
           ucwords(strtolower($status_display)) .
           '</div>';
}


// Admin video update notification'ı için yeni HTML template
public function send_admin_video_update_notification($user_info, $video_link) {
    $subject = "WeVoi Competition - Video Link Updated";
    $headers = array('Content-Type: text/html; charset=UTF-8');

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
            .email-container { max-width: 600px; margin: 0 auto; }
            .header { background-color: #EFE8E8; color: white; padding: 20px; text-align: center; }
            .header img { max-width: 200px; height: auto; }
            .content { padding: 30px; background-color: #ffffff; }
            .info-box {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
            }
            .info-row {
                margin-bottom: 10px;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .info-label {
                font-weight: bold;
                color: #495057;
            }
            .video-link {
                color: ##EFE8E8;
                text-decoration: none;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: ##EFE8E8;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 20px;
            }
            .footer {
                background-color: #f8f9fa;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <img src="https://www.wevoi.org/wp-content/uploads/2018/08/WeVoi_Logo_XS-e1533120407306.png" alt="WeVoi Logo">
            </div>
            <div class="content">
                <h2>Video Link Update Notification</h2>
                <p>A student has updated their video submission.</p>

                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Student:</span>
                        <span>' . $user_info->student_name . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Competition:</span>
                        <span>' . $user_info->competition_name . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">New Video Link:</span>
                        <a href="' . $video_link . '" class="video-link" target="_blank">' . $video_link . '</a>
                    </div>
                </div>

                <p>Please review the updated video submission at your earliest convenience.</p>

                <a href="https://www.wevoi.org/submissions" class="button">Review Submissions</a>
            </div>
            <div class="footer">
                <p>This is an automated message from WeVoi Competition System.</p>
                <p>&copy; ' . date('Y') . ' WeVoi. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';

    wp_mail('admin@wevoi.org', $subject, $html, $headers);
}


    private function enqueue_assets() {
        // Main style file
        wp_enqueue_style(
            'wevoi-register-style',
            WEVOI_REGISTER_PLUGIN_URL . 'public/css/form-styles.css',
            array(),
            WEVOI_REGISTER_VERSION
        );

        // Stripe required script
        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            array(),
            null,
            true
        );

        // Get current user email
        $current_user = wp_get_current_user();
        $userEmail = $current_user->user_email;

        // Form handler script
        wp_enqueue_script(
            'wevoi-register-script',
            WEVOI_REGISTER_PLUGIN_URL . 'public/js/form-handler.js',
            array('jquery', 'stripe-js'),
            WEVOI_REGISTER_VERSION,
            true
        );

        // Localize script with PHP variables
        wp_localize_script(
            'wevoi-register-script',
            'wevoiRegister',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wevoi_register_nonce'),
                'stripeKey' => $this->stripe_handler->get_publishable_key(),
                'userEmail' => $userEmail // Pass user email to JavaScript
            )
        );
    }

private function enqueue_my_competitions_assets() {
    if (!wp_style_is('wevoi-my-competitions-style', 'enqueued')) {
        // Style file
        wp_enqueue_style(
            'wevoi-my-competitions-style',
            WEVOI_REGISTER_PLUGIN_URL . 'public/css/my-competitions.css',
            array(),
            WEVOI_REGISTER_VERSION
        );

        // JavaScript'i tüm kullanıcılar için yükle
        wp_enqueue_script(
            'wevoi-my-competitions',  // admin kelimesini kaldırdık
            WEVOI_REGISTER_PLUGIN_URL . 'public/js/my-competitions-admin.js',
            array('jquery'),
            WEVOI_REGISTER_VERSION,
            true
        );

        // JavaScript'e gerekli değişkenleri aktar
        wp_localize_script(
            'wevoi-my-competitions',  // burayı da güncelledik
            'wevoiRegister',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wevoi_register_nonce')
            )
        );
    }
}

    public function render_my_competitions() {
    if (!is_user_logged_in()) {
        return '<div style="text-align: center; margin-top: 20px;">
                <p style="font-weight: bold;">Please <a href="https://www.wevoi.org/wp-login.php"
                style="text-decoration: none; color: blue;">log in</a> to view your submissions.</p>
                </div>';
    }

    // Stilleri yükle
    $this->enqueue_my_competitions_assets();

    // Admin kontrolü
    $user = wp_get_current_user();
    $is_admin = in_array('administrator', (array) $user->roles);

    if ($is_admin) {
        $registrations = $this->get_all_registrations();

        // Admin için ON AIR, CLOSED ve EVALUATING durumundaki yarışmaları göster
        $filtered_registrations = [];
        foreach ($registrations as $registration) {
            if (in_array($registration->competition_status, array('ON AIR', 'CLOSED', 'EVALUATING'))) {
                $filtered_registrations[] = $registration;
            }
        }
        $registrations = $filtered_registrations;
    } else {
        $registrations = $this->get_user_registrations(get_current_user_id());
    }

    if (empty($registrations)) {
        // Admin için özel mesaj
        if ($is_admin) {
            // Admin için ON AIR, CLOSED veya EVALUATING durumlarını kontrol et
            $competition_statuses = $this->db->get_results(
                "SELECT competition_status FROM {$this->db->prefix}wevoi_competitions WHERE competition_status IN ('ON AIR', 'CLOSED', 'EVALUATING')"
            );

            if (empty($competition_statuses)) {
                return '<div class="wevoi-no-competitions">
                    <p>No video submissions found for competitions with ON AIR, CLOSED, or EVALUATING status.</p>
                    </div>';
            } else {
                return '<div class="wevoi-no-competitions">
                    <p>No video submissions found for the current competitions.</p>
                    </div>';
            }
        }

        // Normal kullanıcılar için mevcut mesajlar
        // Get competition statuses from database
        $competition_statuses = $this->db->get_results(
            "SELECT competition_status FROM {$this->db->prefix}wevoi_competitions"
        );

        $has_onair = false;
        $has_closed = false;
        $has_completed = false;
        $all_not_yet = true;

        foreach ($competition_statuses as $status) {
            if ($status->competition_status === 'ON AIR') {
                $has_onair = true;
                $all_not_yet = false;
            } elseif ($status->competition_status === 'CLOSED') {
                $has_closed = true;
                $all_not_yet = false;
            } elseif ($status->competition_status === 'COMPLETED') {
                $has_completed = true;
                $all_not_yet = false;
            } elseif ($status->competition_status !== 'NOT YET') {
                $all_not_yet = false;
            }
        }

        // Öncelik sırasına göre mesajları göster
        if ($has_onair) {
            // Rol bazlı link: student → register, supervisor/parent/agency → bulk-register
            $user_roles = (array) $user->roles;
            $upload_url = (in_array('supervisor', $user_roles) || in_array('parent', $user_roles) || in_array('agency', $user_roles))
                ? 'https://www.wevoi.org/bulk-register/'
                : 'https://www.wevoi.org/register/';
            return '<div class="wevoi-no-competitions">
                <p>No video submission found. Be part of WeVoi by <a href="' . esc_url($upload_url) . '"><strong> uploading your video</strong></a> now!</p>
                </div>';
        } elseif ($has_closed) {
            // Eğer aktif yarışma yoksa ama kapalı yarışma varsa
            return '<div class="wevoi-no-competitions">
                <p>The competition registration period has ended. Our valued judges are currently evaluating the submitted videos. Video submissions are no longer possible. If you have any questions, please contact <a href="mailto:info@wevoi.org">info@wevoi.org</a>.</p>
                </div>';
        } elseif ($has_completed && !$has_onair && !$has_closed) {
            // Eğer tüm yarışmalar tamamlanmışsa
            return '<div class="wevoi-no-competitions">
                <p>The competitions for this season have been completed. Please check back later for new competitions.</p>
                </div>';
        } elseif ($all_not_yet) {
            // Eğer tüm yarışmalar henüz başlamamışsa
            return '<div class="wevoi-no-competitions">
                <p>Competitions will start soon. Please check back later to submit your videos.</p>
                </div>';
        } else {
            // Diğer durumlar için genel bir mesaj
            return '<div class="wevoi-no-competitions">
                <p>There are currently no active competitions available for video submission. Please check back later.</p>
                </div>';
        }
    }

    ob_start();
    include WEVOI_REGISTER_PLUGIN_DIR . 'public/templates/my-competitions.php';
    return ob_get_clean();
}

private function get_all_registrations() {
    return $this->db->get_results(
        "SELECT r.*,
                c.competition_name,
                c.competition_date,
                c.competition_status, /* Bu satırı ekledik */
                COALESCE(r.refund_amount, 0) as refund_amount,
                COALESCE(r.refund_date, '') as refund_date,
                u.display_name as user_display_name,
                u.user_email as user_email,
                um_first.meta_value as first_name,
                um_last.meta_value as last_name,
                ac.age_category,
                um_country.meta_value as user_countryid,
                co.countryname,
                t.topic,
                sup_u.user_email as supervisor_email,
                TRIM(CONCAT(COALESCE(sup_first.meta_value,''), ' ', COALESCE(sup_last.meta_value,''))) as supervisor_name
         FROM {$this->db->prefix}wevoi_registrations r
         JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
         JOIN {$this->db->prefix}users u ON r.user_id = u.ID
         LEFT JOIN {$this->db->prefix}usermeta um_first ON r.user_id = um_first.user_id AND um_first.meta_key = 'first_name'
         LEFT JOIN {$this->db->prefix}usermeta um_last ON r.user_id = um_last.user_id AND um_last.meta_key = 'last_name'
         LEFT JOIN {$this->db->prefix}wevoi_age_categories ac ON r.age_category_id = ac.id
         LEFT JOIN {$this->db->prefix}usermeta um_country ON r.user_id = um_country.user_id AND um_country.meta_key = 'countryid'
         LEFT JOIN {$this->db->prefix}wevoi_countries co ON um_country.meta_value = co.countryid
         LEFT JOIN {$this->db->prefix}wevoi_topics t ON c.competition_code = t.competition_code AND ac.age_category = t.wevoi_age_category
         LEFT JOIN {$this->db->prefix}users sup_u ON r.supervisor_id = sup_u.ID
         LEFT JOIN {$this->db->prefix}usermeta sup_first ON sup_u.ID = sup_first.user_id AND sup_first.meta_key = 'first_name'
         LEFT JOIN {$this->db->prefix}usermeta sup_last ON sup_u.ID = sup_last.user_id AND sup_last.meta_key = 'last_name'
         WHERE r.paid_status IN ('PAID', 'FREE')
         AND c.competition_type != 'Forum'
         ORDER BY r.registration_date DESC"
    );
    }

// Get registrations for current user (student: own; supervisor/agency: registrations where they are assigned)
private function get_user_registrations($user_id) {
    $user = wp_get_current_user();
    $is_supervisor_or_agency = in_array('supervisor', (array) $user->roles) || in_array('agency', (array) $user->roles);

    if ($is_supervisor_or_agency) {
        // Supervisor/Agency: get registrations where they are assigned (r.supervisor_id)
        $supervisor_id = get_current_user_id();
        return $this->db->get_results($this->db->prepare(
            "SELECT r.*,
                    c.competition_name,
                    c.competition_date,
                    c.competition_status,
                    c.award_ceremony_completed,
                    c.award_ceremony_zoom_link,
                    c.award_ceremony_date,
                    COALESCE(r.refund_amount, 0) as refund_amount,
                    COALESCE(r.refund_date, '') as refund_date,
                    COALESCE(r.claimed_prize_amount, 0) as claimed_prize_amount,
                    r.claimed_prize_paid_status,
                    um_first.meta_value as first_name,
                    um_last.meta_value as last_name,
                    ac.age_category,
                    um_country.meta_value as user_countryid,
                    co.countryname,
                    t.topic,
                    u.user_email,
                    u.display_name,
                    sup_u.user_email as supervisor_email,
                    TRIM(CONCAT(COALESCE(sup_first.meta_value,''), ' ', COALESCE(sup_last.meta_value,''))) as supervisor_name
             FROM {$this->db->prefix}wevoi_registrations r
             JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             JOIN {$this->db->prefix}users u ON r.user_id = u.ID
             LEFT JOIN {$this->db->prefix}usermeta um_first
                ON r.user_id = um_first.user_id
                AND um_first.meta_key = 'first_name'
             LEFT JOIN {$this->db->prefix}usermeta um_last
                ON r.user_id = um_last.user_id
                AND um_last.meta_key = 'last_name'
             LEFT JOIN {$this->db->prefix}wevoi_age_categories ac
                ON r.age_category_id = ac.id
             LEFT JOIN {$this->db->prefix}usermeta um_country
                ON r.user_id = um_country.user_id
                AND um_country.meta_key = 'countryid'
             LEFT JOIN {$this->db->prefix}wevoi_countries co
                ON um_country.meta_value = co.countryid
             LEFT JOIN {$this->db->prefix}wevoi_topics t
                ON c.competition_code = t.competition_code
                AND ac.age_category = t.wevoi_age_category
             LEFT JOIN {$this->db->prefix}users sup_u ON r.supervisor_id = sup_u.ID
             LEFT JOIN {$this->db->prefix}usermeta sup_first ON sup_u.ID = sup_first.user_id AND sup_first.meta_key = 'first_name'
             LEFT JOIN {$this->db->prefix}usermeta sup_last ON sup_u.ID = sup_last.user_id AND sup_last.meta_key = 'last_name'
             WHERE r.supervisor_id = %d
             AND r.paid_status IN ('PAID', 'FREE')
             AND c.competition_type != 'Forum'
             AND (c.award_ceremony_completed = 1 OR c.competition_status != 'COMPLETED')
             ORDER BY r.registration_date DESC",
            $supervisor_id
        ));
    } else {
        // Student: own registrations only
        return $this->db->get_results($this->db->prepare(
            "SELECT r.*,
                    c.competition_name,
                    c.competition_date,
                    c.competition_status,
                    c.award_ceremony_completed,
                    c.award_ceremony_zoom_link,
                    c.award_ceremony_date,
                    COALESCE(r.refund_amount, 0) as refund_amount,
                    COALESCE(r.refund_date, '') as refund_date,
                    COALESCE(r.claimed_prize_amount, 0) as claimed_prize_amount,
                    r.claimed_prize_paid_status,
                    um_first.meta_value as first_name,
                    um_last.meta_value as last_name,
                    ac.age_category,
                    um_country.meta_value as user_countryid,
                    co.countryname,
                    t.topic,
                    sup_u.user_email as supervisor_email,
                    TRIM(CONCAT(COALESCE(sup_first.meta_value,''), ' ', COALESCE(sup_last.meta_value,''))) as supervisor_name
             FROM {$this->db->prefix}wevoi_registrations r
             JOIN {$this->db->prefix}wevoi_competitions c
                ON r.competition_code = c.competition_code
             LEFT JOIN {$this->db->prefix}usermeta um_first
                ON r.user_id = um_first.user_id
                AND um_first.meta_key = 'first_name'
             LEFT JOIN {$this->db->prefix}usermeta um_last
                ON r.user_id = um_last.user_id
                AND um_last.meta_key = 'last_name'
             LEFT JOIN {$this->db->prefix}wevoi_age_categories ac
                ON r.age_category_id = ac.id
             LEFT JOIN {$this->db->prefix}usermeta um_country
                ON r.user_id = um_country.user_id
                AND um_country.meta_key = 'countryid'
             LEFT JOIN {$this->db->prefix}wevoi_countries co
                ON um_country.meta_value = co.countryid
             LEFT JOIN {$this->db->prefix}wevoi_topics t
                ON c.competition_code = t.competition_code
                AND ac.age_category = t.wevoi_age_category
             LEFT JOIN {$this->db->prefix}users sup_u ON r.supervisor_id = sup_u.ID
             LEFT JOIN {$this->db->prefix}usermeta sup_first ON sup_u.ID = sup_first.user_id AND sup_first.meta_key = 'first_name'
             LEFT JOIN {$this->db->prefix}usermeta sup_last ON sup_u.ID = sup_last.user_id AND sup_last.meta_key = 'last_name'
             WHERE r.user_id = %d
             AND r.paid_status IN ('PAID', 'FREE')
             AND c.competition_type != 'Forum'
             AND (c.award_ceremony_completed = 1 OR c.competition_status != 'COMPLETED')
             ORDER BY r.registration_date DESC",
            $user_id
        ));
    }
}

    /**
     * Get topics for a specific competition
     */
    public function get_topics() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }

        $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;

        if (!$competition_code) {
            wp_send_json_error('Invalid competition code');
            return;
        }

        // Get user's age category ID from current age calculation
        $user_id = get_current_user_id();
        $age_category_id = $this->get_user_age_category_id($user_id);

        if (!$age_category_id) {
            wp_send_json_error('User age category could not be determined. Please ensure your birth date is set in your profile.');
            return;
        }

        // Get age category name
        $age_category_obj = $this->db->get_row($this->db->prepare(
            "SELECT age_category FROM {$this->db->prefix}wevoi_age_categories WHERE id = %d",
            $age_category_id
        ));

        if (!$age_category_obj) {
            wp_send_json_error('Age category not found');
            return;
        }

        $age_category = $age_category_obj->age_category;

        // Get topics for this competition and age category
        $topics = $this->db->get_results($this->db->prepare(
            "SELECT topic, wevoi_age_category FROM {$this->db->prefix}wevoi_topics
             WHERE competition_code = %d AND wevoi_age_category = %s",
            $competition_code,
            $age_category
        ));

        wp_send_json_success([
            'topics' => $topics,
            'age_category' => $age_category
        ]);
    }
}
?>
