<?php
/**
 * Plugin Name: WeVoi Signup
 * Description: WeVoi New Signup Page.
 * Version: 1.1
 * Author: Kenan
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


// Define plugin constants
define('WEVOI_SIGNUP_VERSION', '1.0');
define('WEVOI_SIGNUP_PLUGIN_FILE', __FILE__);
define('WEVOI_SIGNUP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEVOI_SIGNUP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Add hidden field to registration form for bot protection
add_filter('registration_errors', function($errors, $sanitized_user_login, $user_email) {

    $_POST['timestamp'] = isset($_POST['timestamp']) ? $_POST['timestamp'] : time();
    $time_since_last = time() - $_POST['timestamp'];

    if ($time_since_last < 3) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $form_time = isset($_POST['timestamp']) ? (time() - $_POST['timestamp']) : 'no timestamp';
        $date = date('Y-m-d H:i:s');

        $suspicious_data = array(
            'IP' => $ip,
            'Email' => $user_email,
            'Username' => $sanitized_user_login,
            'Form_Time' => $form_time . ' seconds',
            'Date' => $date,
            'First_Name' => $_POST['first_name'] ?? 'none',
            'Last_Name' => $_POST['last_name'] ?? 'none',
            'Country' => $_POST['countryid'] ?? 'none',
            'School' => $_POST['schoolid'] ?? 'none'
        );

        // Logging removed

        $htaccess_file = '/home/lms/.htaccess';
        $deny_rule = "deny from " . $ip . "\n";

        if (is_readable($htaccess_file)) {
            $current_content = file_get_contents($htaccess_file);
            if (strpos($current_content, $deny_rule) === false) {
                if (is_writable($htaccess_file)) {
                    file_put_contents($htaccess_file, $deny_rule, FILE_APPEND);
                }
            }
        }

        $errors->add('bot_error', 'Please take a moment to review your information.');
    }
    $_POST['timestamp'] = time();

    return $errors;
}, 10, 3);

// Set email content type to HTML
function set_html_mail_content_type() {
    return 'text/html';
}

// Customize password reset email
function custom_password_reset_email($message, $key, $user_login, $user_data) {
    // Site bilgilerini al
    $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $site_url = home_url();
    $logo_url = 'https://www.wevoi.org/wp-content/uploads/2018/08/WeVoi_Logo_XS-e1533120407306.png'; // Logo URL'nizi buraya ekleyin

    // Password reset URL
    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

    // IP adresi bilgisi
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // HTML e-posta şablonu
    $html_message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
                margin: 0;
                padding: 0;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                border: 1px solid #dddddd;
                border-radius: 5px;
                overflow: hidden;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .email-header {
                background-color: #ffffff;
                padding: 20px;
                text-align: center;
                border-bottom: 1px solid #eeeeee;
            }
            .email-content {
                padding: 30px;
                background-color: #ffffff;
            }
            .email-footer {
                padding: 20px;
                text-align: center;
                color: #666666;
                font-size: 12px;
                background-color: #f7f7f7;
                border-top: 1px solid #eeeeee;
            }
            h1 {
                color: #0077b5;
                margin: 0 0 20px 0;
                font-size: 24px;
                text-align: center;
            }
            .button {
                display: inline-block;
                background-color: #0077b5;
                color: white !important;
                text-decoration: none;
                padding: 12px 30px;
                border-radius: 3px;
                font-weight: bold;
                margin: 20px 0;
                text-align: center;
            }
            .button:hover {
                background-color: #005f8e;
            }
            .logo {
                max-width: 200px;
                margin-bottom: 10px;
            }
            .details {
                border-top: 1px solid #eeeeee;
                margin-top: 20px;
                padding-top: 20px;
                font-size: 12px;
                color: #666666;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <img src="' . $logo_url . '" alt="' . $site_name . '" class="logo">
            </div>
            <div class="email-content">
                <h1>Reset Your Password</h1>
                <p><h2>Hi ' . $user_login . ',</h2></p>
                <p>Someone has requested a password reset for your account.</p>
                <p>Username: <strong>' . $user_login . '</strong></p>
                <p>To set your password and access your account, simply click the button below:</p>
                <p style="text-align: center;">
                    <a href="' . $reset_url . '" class="button">Set Your Password</a>
                </p>
                <p>If you did not request this password reset, please ignore this email and nothing will happen.</p>
                <div class="details">
                    <p>This password reset request originated from the IP address: ' . $user_ip . '</p>
                </div>
            </div>
            <div class="email-footer">
                <p>Best regards,<br>The ' . $site_name . ' Team</p>
                <p><a href="' . $site_url . '">Visit Our Website</a></p>
            </div>
        </div>
    </body>
    </html>
    ';

    return $html_message;
}

// Filtreleri ekle
function apply_password_reset_email_filters() {
    add_filter('wp_mail_content_type', 'set_html_mail_content_type');
    add_filter('retrieve_password_message', 'custom_password_reset_email', 10, 4);
}

// Apply filters before sending password reset email
add_action('lostpassword_post', 'apply_password_reset_email_filters');

// Restore default mail filters after sending
function remove_html_content_type() {
    remove_filter('wp_mail_content_type', 'set_html_mail_content_type');
}

add_action('lostpassword_post', 'remove_html_content_type');

// Enqueue scripts and styles
function custom_register_enqueue_scripts() {
    if (in_array($GLOBALS['pagenow'], ['wp-login.php'])) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('custom-register-script', plugin_dir_url(__FILE__) . 'js/register.js', ['jquery'], '1.0', true);
        wp_enqueue_style('custom-register-style', plugin_dir_url(__FILE__) . 'css/register-style.css');

        wp_localize_script('custom-register-script', 'customRegisterAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('get_schools_nonce')
        ]);
    }
}
add_action('login_enqueue_scripts', 'custom_register_enqueue_scripts');

add_action('login_head', function() {
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style type="text/css">
        body:not(.login-action-register) #login {
            width: 320px !important;
        }
    </style>
    <?php
});

// Form alanlarını oluşturma
add_action('register_form', function () {
    global $wpdb;
    $countries = $wpdb->get_results("SELECT * FROM wpie_wevoi_countries ORDER BY countryname ASC");
    $selected_role = isset($_POST['role']) ? $_POST['role'] : '';
    $selected_school = isset($_POST['schoolid']) ? $_POST['schoolid'] : '';
    ?>

<p class="full-width role-selection">
    <div class="role-options">
        <label class="role-option">
            <input type="radio" name="role" value="student" <?php checked($selected_role, 'student'); ?>>
            Student
        </label>
        <label class="role-option">
            <input type="radio" name="role" value="supervisor" <?php checked($selected_role, 'supervisor'); ?>>
            Supervisor
        </label>
        <label class="role-option">
            <input type="radio" name="role" value="judge" <?php checked($selected_role, 'judge'); ?>>
            Judge
        </label>
        <label class="role-option">
            <input type="radio" name="role" value="parent" <?php checked($selected_role, 'parent'); ?>>
            Parent
        </label>
        <label class="role-option">
            <input type="radio" name="role" value="agency" <?php checked($selected_role, 'agency'); ?>>
            Agency
        </label>
    </div>
</p>

    <div class="signup-name-row">
        <p class="signup-row-half">
            <label for="first_name">First Name<br/>
                <input type="text" name="first_name" id="first_name" class="input" value="<?php echo esc_attr($_POST['first_name'] ?? ''); ?>" required /></label>
        </p>
        <p class="signup-row-half">
            <label for="last_name">Last Name<br/>
                <input type="text" name="last_name" id="last_name" class="input" value="<?php echo esc_attr($_POST['last_name'] ?? ''); ?>" required /></label>
        </p>
    </div>

    <!-- Student: Birth Date - Gender (yan yana) -->
    <div id="student-fields" class="full-width" style="<?php echo ($selected_role === 'student' ? 'display:block;' : 'display:none;'); ?>">
        <div class="signup-name-row">
            <p class="signup-row-half">
                <label for="birth_date">Birth Date<br/>
                    <input type="date" name="birth_date" id="birth_date" class="input" min="1995-01-01" max="<?php echo date('Y-m-d', strtotime('-6 years')); ?>" value="<?php echo esc_attr($_POST['birth_date'] ?? ''); ?>" required /></label>
            </p>
            <p class="signup-row-half">
                <label for="gender">Gender by Birth<br/>
                    <select name="gender" id="gender" class="input" required>
                        <option value="">Select a gender</option>
                        <option value="Male" <?php selected($_POST['gender'] ?? '', 'Male'); ?>>Male</option>
                        <option value="Female" <?php selected($_POST['gender'] ?? '', 'Female'); ?>>Female</option>
                    </select>
                </label>
            </p>
        </div>
    </div>

    <!-- Country (tek satır, tam genişlik) -->
    <p class="full-width">
        <label for="countryid">Country<br/>
            <select name="countryid" id="countryid" class="input" required>
                <option value="">Select a country</option>
                <?php foreach ($countries as $country) : ?>
                    <option value="<?php echo esc_attr($country->countryid); ?>" <?php selected($_POST['countryid'] ?? '', $country->countryid); ?>><?php echo esc_html($country->countryname); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </p>
    <!-- School Name (tek satır, tam genişlik - Student, Supervisor, Judge) -->
    <p id="school-field-container" class="full-width" style="<?php echo (in_array($selected_role, ['parent', 'agency']) ? 'display:none;' : ''); ?>">
        <label for="schoolid">School Name<br/>
            <select name="schoolid" id="schoolid" class="input" required>
                <option value="">Select a school</option>
                <?php
                    $selected_country = $_POST['countryid'] ?? '';

                    printf(
                        '<option value="other" %s class="italic-option">-- My school is not in the list --</option>',
                        selected($selected_school, 'other', false)
                    );

                    if (!empty($selected_country)) {
                        $schools = $wpdb->get_results($wpdb->prepare(
                            "SELECT schoolid, schoolname FROM wpie_wevoi_schools WHERE countryid = %d ORDER BY schoolname ASC",
                            intval($selected_country)
                        ));

                        if ($schools) {
                            foreach ($schools as $school) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($school->schoolid),
                                    selected($selected_school, $school->schoolid, false),
                                    esc_html($school->schoolname)
                                );
                            }
                        }
                    }
                ?>
            </select>
        </label>
    </p>
    <!-- Company Name (tek satır, tam genişlik - sadece Agency) -->
    <div id="agency-fields" class="full-width" style="<?php echo ($selected_role === 'agency' ? 'display:block;' : 'display:none;'); ?>">
        <p>
            <label for="company_name">Company Name<br/>
                <input type="text" name="company_name" id="company_name" class="input" value="<?php echo esc_attr($_POST['company_name'] ?? ''); ?>" /></label>
        </p>
    </div>

    <div id="new-school-container" class="full-width" style="<?php echo ($selected_school === 'other' ? 'display:block;' : 'display:none;'); ?>">
        <p>
            <label for="new_school">New School Name<br/>
                <input type="text" name="new_school" id="new_school" class="input" value="<?php echo esc_attr($_POST['new_school'] ?? ''); ?>" /></label>
        </p>
    </div>
    <?php
});

// Form validasyonu
add_filter('registration_errors', function ($errors, $sanitized_user_login, $user_email) {
    if (empty($_POST['first_name'])) {
        $errors->add('first_name_error', '<strong>ERROR</strong>: Please enter your first name.');
    }
    if (empty($_POST['last_name'])) {
        $errors->add('last_name_error', '<strong>ERROR</strong>: Please enter your last name.');
    }

    if (isset($_POST['role']) && $_POST['role'] === 'student') {
        if (empty($_POST['birth_date'])) {
            $errors->add('birth_date_error', '<strong>ERROR</strong>: Please enter your birth date.');
        } else {
            $birth_date_raw = sanitize_text_field($_POST['birth_date']);
            $birth_date_obj = DateTime::createFromFormat('Y-m-d', $birth_date_raw);
            if ($birth_date_obj === false) {
                $errors->add('birth_date_error', '<strong>ERROR</strong>: Please enter a valid birth date (e.g. 2010-05-15).');
            } else {
                try {
                    $today = new DateTime();
                    $age = $today->diff($birth_date_obj)->y;
                    $expected_category = '';

                    // Determine category based on age
                    if ($age >= 6 && $age <= 11) {
                        $expected_category = 'YoungsterVoi';
                    } elseif ($age >= 12 && $age <= 14) {
                        $expected_category = 'JuniorVoi';
                    } elseif ($age >= 15 && $age <= 18) {
                        $expected_category = 'TeenVoi';
                    } elseif ($age >= 19 && $age <= 24) {
                        $expected_category = 'SeniorVoi';
                    } 

                    if (empty($expected_category)) {
                        $errors->add('birth_date_error', '<strong>ERROR</strong>: Birth date is outside the allowed range.');
                    }
                } catch (Exception $e) {
                    $errors->add('birth_date_error', '<strong>ERROR</strong>: Please enter a valid birth date.');
                }
            }
        }

        if (empty($_POST['gender'])) {
            $errors->add('gender_error', '<strong>ERROR</strong>: Please select your gender.');
        }
    }

    if (empty($_POST['role']) || !in_array($_POST['role'], ['student', 'judge', 'supervisor', 'parent', 'agency'])) {
        $errors->add('role_error', '<strong>ERROR</strong>: Please select a valid role.');
    }

    if (empty($_POST['countryid']) || $_POST['countryid'] === '') {
        $errors->add('countryid_error', '<strong>ERROR</strong>: Please select a valid country.');
    }

    // Agency: Company Name is required
    if (isset($_POST['role']) && $_POST['role'] === 'agency') {
        if (empty(trim($_POST['company_name'] ?? ''))) {
            $errors->add('company_name_error', '<strong>ERROR</strong>: Please enter your company name.');
        }
    }

    // School is required for all roles except parent and agency
    if (isset($_POST['role']) && $_POST['role'] !== 'parent' && $_POST['role'] !== 'agency') {
        if (empty($_POST['schoolid']) || $_POST['schoolid'] === '') {
            $errors->add('school_error', '<strong>ERROR</strong>: Please select a valid school.');
        }

        if (isset($_POST['schoolid']) && $_POST['schoolid'] === 'other' && empty($_POST['new_school'])) {
            $errors->add('new_school_error', '<strong>ERROR</strong>: Please provide a new school name.');
        }
    }

    return $errors;
}, 10, 3);

// Form verilerini kaydetme
add_action('user_register', function ($user_id) {
    global $wpdb;

    $user = new WP_User($user_id);
    $user_email = $user->user_email;

    // Check if this is a parent being created during student registration (same email in parent field)
    $is_supervisor_creation = false;
    if (!$is_supervisor_creation) {
        $parent_email = '';
        if (!empty($_POST['parentemail'])) {
            $parent_email = sanitize_email($_POST['parentemail']);
        } elseif (!empty($_POST['parent_email'])) {
            $parent_email = sanitize_email($_POST['parent_email']);
        }
        if (!empty($parent_email) && $user_email === $parent_email) {
            $is_supervisor_creation = true;
        }
    }

    // Get role from POST for new registrations
    $role = '';
    if (!empty($_POST['role']) && !$is_supervisor_creation) {
        $role = sanitize_text_field($_POST['role']);
        $user->set_role($role);
    } else {
        // For parent/supervisor creation, role is set elsewhere; use existing role
        $role = isset($user->roles[0]) ? $user->roles[0] : '';
    }

    if (!empty($_POST['first_name']) && !$is_supervisor_creation) {
        update_user_meta($user_id, 'first_name', sanitize_text_field($_POST['first_name']));
    }
    if (!empty($_POST['last_name']) && !$is_supervisor_creation) {
        update_user_meta($user_id, 'last_name', sanitize_text_field($_POST['last_name']));
    }

    // Only set student-specific data (birth_date, gender). Supervisor is set per competition at registration.
    if ($role === 'student' && !$is_supervisor_creation) {
        if (!empty($_POST['birth_date'])) {
            update_user_meta($user_id, 'birth_date', sanitize_text_field($_POST['birth_date']));
        }
        if (!empty($_POST['gender'])) {
            update_user_meta($user_id, 'gender', sanitize_text_field($_POST['gender']));
        }
    }

    // Save country and school for all direct registrations
    if (!$is_supervisor_creation) {
        if (!empty($_POST['countryid'])) {
            update_user_meta($user_id, 'countryid', intval($_POST['countryid']));
        }

        // Agency: save Company Name in usermeta (metakey: company_name)
        if ($role === 'agency' && isset($_POST['company_name'])) {
            update_user_meta($user_id, 'company_name', sanitize_text_field($_POST['company_name']));
        }

        // School is not required for parent or agency
        if ($role !== 'parent' && $role !== 'agency') {
            if (!empty($_POST['new_school'])) {
                $new_school_name = sanitize_text_field($_POST['new_school']);
                $country_id = intval($_POST['countryid']);

                $wpdb->insert('wpie_wevoi_schools', [
                    'schoolname' => $new_school_name,
                    'countryid' => $country_id
                ]);

                $new_school_id = $wpdb->insert_id;
                update_user_meta($user_id, 'schoolid', $new_school_id);
            } elseif (!empty($_POST['schoolid'])) {
                update_user_meta($user_id, 'schoolid', intval($_POST['schoolid']));
            }
        }
    }
});

// AJAX handlers
function get_schools_by_country() {
    // Nonce kontrolü
    if (!check_ajax_referer('get_schools_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Invalid security token']);
        exit;
    }

    global $wpdb;

    if (!isset($_POST['countryid']) || empty($_POST['countryid'])) {
        wp_send_json_error(['message' => 'Invalid country ID']);
        exit;
    }

    $country_id = intval($_POST['countryid']);

    $schools = $wpdb->get_results($wpdb->prepare(
        "SELECT schoolid, schoolname FROM wpie_wevoi_schools WHERE countryid = %d ORDER BY schoolname ASC",
        $country_id
    ));

    wp_send_json_success($schools);
}

add_action('wp_ajax_get_schools_by_country', 'get_schools_by_country');
add_action('wp_ajax_nopriv_get_schools_by_country', 'get_schools_by_country');

// Other settings and customizations
add_action('login_footer', function () {
    echo '<style>
        #login p a {
            font-weight: bold;
            font-size: 18px;
            display: inline;
            margin: 0 5px;
        }
        #login #backtoblog,
        #login #nav {
            text-align: center;
        }
    </style>';
});

// Replace specific text in the login and signup forms
add_filter('gettext', function ($translated_text, $text, $domain) {
    switch ($text) {
        case 'Register':
            $translated_text = 'Sign Up';
            break;

        case 'Register For This Site':
            $translated_text = '<div class="wevoi-signup-container">
                <h1 class="wevoi-signup-title">Sign Up for WeVoi</h1>
            </div>';
            break;

        case 'Lost your password?':
            $translated_text = 'Forgot Password?';
            break;
    }
    return $translated_text;
}, 10, 3);

// Customize the email sent to users upon registration
add_filter('wp_new_user_notification_email', function($email, $user, $blogname) {
    $user_login = $user->user_login;
    $reset_key = get_password_reset_key($user);
    $reset_url = wp_login_url() . "?action=rp&key=$reset_key&login=" . rawurlencode($user_login);

    $email['subject'] = "Welcome to $blogname!";
    $email['message'] = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.8; color: #333; background-color: #f9f9f9; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='https://www.wevoi.org/wp-content/uploads/2018/08/WeVoi_Logo_XS-e1533120407306.png' alt='WeVoi Logo' style='max-width: 150px;'>
            </div>
            <h1 style='text-align: center; color: #0073aa;'>Welcome to $blogname!</h1>
            <p style='font-size: 18px;'>Hi <strong>{$user->display_name}</strong>,</p>
            <p>We're absolutely thrilled to have you join us! Below are your account details:</p>
            <p style='font-size: 16px;'><strong>Username:</strong> {$user_login}</p>
            <p>To set your password and access your account, simply click the button below:</p>
            <p style='text-align: center;'>
                <a href='$reset_url' style='display: inline-block; background-color: #0073aa; color: white; font-size: 18px; padding: 15px 30px; text-decoration: none; border-radius: 5px;'>Set Your Password</a>
            </p>
            <p>If you have any questions, we're here to help! Just <a href='mailto:info@wevoi.org' style='color: #0073aa;'>drop us an email</a>.</p>
            <hr style='margin: 30px 0; border: 0; border-top: 1px solid #eee;'>
            <p style='text-align: center; font-size: 14px; color: #555;'>
                Best regards,<br/>
                The <strong>$blogname</strong> Team<br/>
                <a href='https://wevoi.org' style='color: #0073aa;'>Visit Our Website</a>
            </p>
        </div>
    </body>
    </html>
    ";
    $email['headers'] = [ 'Content-Type: text/html; charset=UTF-8' ];

    return $email;
}, 10, 3);

// Admin'e yeni kullanıcı kaydı bildirimi gönder
// Send new user registration notification to admin
add_filter('wp_new_user_notification_email_admin', function($email, $user, $blogname) {
    $user_login = stripslashes($user->user_login);
    $user_email = stripslashes($user->user_email);

    // Get user meta information
    $role = isset($user->roles[0]) ? $user->roles[0] : 'N/A';
    $first_name = get_user_meta($user->ID, 'first_name', true) ?: 'N/A';
    $last_name = get_user_meta($user->ID, 'last_name', true) ?: 'N/A';
    $country_id = get_user_meta($user->ID, 'countryid', true) ?: 'N/A';
    $school_id = get_user_meta($user->ID, 'schoolid', true) ?: 'N/A';

    // Student-specific fields
    $extra_info = '';
    if ($role == 'student') {
        $birth_date = get_user_meta($user->ID, 'birth_date', true) ?: 'N/A';
        $gender = get_user_meta($user->ID, 'gender', true) ?: 'N/A';

        $extra_info = "
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;'>Birth Date:</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$birth_date}</td>
            </tr>
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Gender:</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$gender}</td>
            </tr>
        ";
    }

    // Agency: Company Name from usermeta
    $company_name = 'N/A';
    if ($role == 'agency') {
        $company_name = get_user_meta($user->ID, 'company_name', true) ?: 'N/A';
    }

    // Get country and school names from database
    global $wpdb;
    $country_name = 'N/A';
    if ($country_id && $country_id != 'N/A') {
        $country = $wpdb->get_var($wpdb->prepare(
            "SELECT countryname FROM wpie_wevoi_countries WHERE countryid = %d",
            intval($country_id)
        ));
        if ($country) {
            $country_name = $country;
        }
    }

    $school_name = 'N/A';
    if ($school_id && $school_id != 'N/A' && $school_id != 'other') {
        $school = $wpdb->get_var($wpdb->prepare(
            "SELECT schoolname FROM wpie_wevoi_schools WHERE schoolid = %d",
            intval($school_id)
        ));
        if ($school) {
            $school_name = $school;
        }
    } elseif ($school_id == 'other') {
        $new_school = get_user_meta($user->ID, 'new_school', true);
        if ($new_school) {
            $school_name = $new_school . ' (Newly Added School)';
        }
    }

    $register_date = date_i18n('F d Y, H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $email['to'] = 'admin@wevoi.org';
    $email['subject'] = "[{$blogname}] New User Registration: {$user_login}";
    $email['message'] = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; padding: 20px; margin: 0;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='https://www.wevoi.org/wp-content/uploads/2018/08/WeVoi_Logo_XS-e1533120407306.png' alt='WeVoi Logo' style='max-width: 150px;'>
            </div>
            <h1 style='text-align: center; color: #0073aa; margin-top: 0;'>New User Registration</h1>
            <p style='font-size: 16px; margin-bottom: 20px;'>A new user has registered on your site. User information is provided below:</p>

            <div style='background-color: #f5f5f5; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px;'>
                <h2 style='margin-top: 0; color: #0073aa; font-size: 18px;'>User Information</h2>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;'>Username:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$user_login}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Email:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$user_email}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Role:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$role}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>First Name:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$first_name}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Last Name:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$last_name}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Country:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$country_name}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>" . ($role == 'agency' ? 'Company Name' : 'School') . ":</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>" . ($role == 'agency' ? $company_name : $school_name) . "</td>
                    </tr>
                    {$extra_info}
                </table>
            </div>

            <div style='background-color: #f5f5f5; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px;'>
                <h2 style='margin-top: 0; color: #0073aa; font-size: 18px;'>System Information</h2>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;'>Registration Date:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$register_date}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>IP Address:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$ip_address}</td>
                    </tr>
                </table>
            </div>

            <p style='text-align: center; margin: 25px 0;'>
                <a href='" . admin_url("user-edit.php?user_id={$user->ID}") . "' style='display: inline-block; background-color: #0073aa; color: white; font-weight: bold; font-size: 16px; padding: 12px 25px; text-decoration: none; border-radius: 5px;'>View User Profile</a>
            </p>

            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #777; text-align: center;'>
                <p>This email was automatically sent by the WeVoi system.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    $email['headers'] = ['Content-Type: text/html; charset=UTF-8'];

    return $email;
}, 10, 3);
