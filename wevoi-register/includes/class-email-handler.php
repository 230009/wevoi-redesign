<?php
/**
 * Email handling for WeVoi Register
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Register_Email_Handler {
    private static $instance = null;
    private $db;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        // Set email content type to HTML
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }

    /**
     * Set email content type to HTML
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Send confirmation emails
     */
    public function send_emails($registration_id) {
        // Get registration details
        $registration = $this->get_registration_details($registration_id);
        if (!$registration) {
            return false;
        }

        // Send user confirmation
        $this->send_user_confirmation($registration);

        // Send admin notification
        $this->send_admin_notification($registration);

        // Notify supervisor/agency if assigned (no approval, informational only)
        if (!empty($registration->supervisor_id)) {
            $this->send_supervisor_assignment_notification($registration);
        }

        return true;
    }

    /**
     * Get registration details
     */
    private function get_registration_details($registration_id) {
        return $this->db->get_row($this->db->prepare("
            SELECT
                r.*,
                u.user_email,
                u.display_name,
                c.competition_name,
                c.competition_date,
                co.countryname
            FROM {$this->db->prefix}wevoi_registrations r
            JOIN {$this->db->prefix}users u ON r.user_id = u.ID
            JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
            LEFT JOIN {$this->db->prefix}wevoi_countries co ON r.payer_countryid = co.countryid
            WHERE r.register_id = %d
        ", $registration_id));
    }

    /**
     * Send user confirmation email
     */
    private function send_user_confirmation($registration) {
        $subject = sprintf('Competition Registration Confirmation - %s', $registration->competition_name);
        $message = $this->get_user_email_template($registration);

        wp_mail($registration->user_email, $subject, $message);
    }

    /**
     * Send admin notification email
     */
    private function send_admin_notification($registration) {
        $subject = sprintf('New Competition Registration - %s', $registration->competition_name);
        $message = $this->get_admin_email_template($registration);

        wp_mail('admin@wevoi.org', $subject, $message);
    }

    /**
     * Notify supervisor/agency that they were assigned to this registration (informational, no approval)
     */
    private function send_supervisor_assignment_notification($registration) {
        $sup = get_userdata($registration->supervisor_id);
        if (!$sup || !$sup->user_email) {
            return;
        }
        $sup_name = trim(get_user_meta($registration->supervisor_id, 'first_name', true) . ' ' . get_user_meta($registration->supervisor_id, 'last_name', true));
        if (empty($sup_name)) {
            $sup_name = $sup->display_name ?: $sup->user_email;
        }
        $student_name = $registration->display_name ?: $registration->user_email;
        $subject = sprintf('WeVoi: You were assigned as Supervisor/Agency - %s', $registration->competition_name);
        $message = sprintf(
            '<p>Dear %s,</p><p>A student (%s) has registered for the competition <strong>%s</strong> and selected you as their Supervisor/Agency.</p><p>This is an informational notification only; no action is required.</p><p>Best regards,<br>WeVoi Team</p>',
            esc_html($sup_name),
            esc_html($student_name),
            esc_html($registration->competition_name)
        );
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($sup->user_email, $subject, $message, $headers);
    }

    /**
     * Get user email template
     */
    private function get_user_email_template($registration) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Registration Confirmation</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0066cc;">Registration Confirmation</h2>

                <p>Dear <?php echo esc_html($registration->display_name); ?>,</p>

                <p>Thank you for registering for the competition. Here are your registration details:</p>

                <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p><strong>Competition:</strong> <?php echo esc_html($registration->competition_name); ?></p>
                    <p><strong>Date:</strong> <?php echo esc_html(date('d/m/Y', strtotime($registration->competition_date))); ?></p>
                    <p><strong>YouTube Link:</strong> <?php echo esc_url($registration->youtube_link); ?></p>
                    <p><strong>Registration Status:</strong> <?php echo esc_html($registration->paid_status); ?></p>
                    <?php if ($registration->paid_status !== 'FREE'): ?>
                        <p><strong>Amount Paid:</strong> £<?php echo number_format($registration->price, 2); ?></p>
                    <?php endif; ?>
                </div>

                <p>Please keep this email for your records. If you have any questions, feel free to contact us.</p>

                <p>Best regards,<br>WeVoi Team</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get admin email template
     */
    private function get_admin_email_template($registration) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>New Registration Notification</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0066cc;">New Competition Registration</h2>

                <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p><strong>Competition:</strong> <?php echo esc_html($registration->competition_name); ?></p>
                    <p><strong>Date:</strong> <?php echo esc_html(date('d/m/Y', strtotime($registration->competition_date))); ?></p>
                    <p><strong>Participant:</strong> <?php echo esc_html($registration->display_name); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html($registration->user_email); ?></p>
                    <p><strong>YouTube Link:</strong> <?php echo esc_url($registration->youtube_link); ?></p>

                    <?php if ($registration->paid_status !== 'FREE'): ?>
                        <p><strong>Payer Name:</strong> <?php echo esc_html($registration->payer_name); ?></p>
                        <p><strong>Payer Country:</strong> <?php echo esc_html($registration->countryname); ?></p>
                        <p><strong>Amount Paid:</strong> £<?php echo number_format($registration->price, 2); ?></p>
                        <p><strong>Customer Code:</strong> <?php echo esc_html($registration->customer_code); ?></p>
                    <?php endif; ?>

                    <p><strong>Registration Status:</strong> <?php echo esc_html($registration->paid_status); ?></p>
                    <?php if ($registration->coupon_code): ?>
                        <p><strong>Coupon Used:</strong> <?php echo esc_html($registration->coupon_code); ?></p>
                    <?php endif; ?>
                </div>

                <p>This is an automated notification. Please check the admin panel for more details.</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

public function send_bulk_confirmation_emails($registration_ids, $supervisor_email) {
    // Send supervisor confirmation
    $subject = 'Bulk Registration Confirmation';
    $supervisor_message = $this->get_supervisor_bulk_email_template($registration_ids);
    wp_mail($supervisor_email, $subject, $supervisor_message);

    // Send individual confirmations to students
    foreach ($registration_ids as $registration_id) {
        $this->send_emails($registration_id);
    }
}

private function get_supervisor_bulk_email_template($registration_ids) {
    $registrations = $this->db->get_results($this->db->prepare(
        "SELECT r.*, u.user_email, u.display_name, c.competition_name
         FROM {$this->db->prefix}wevoi_registrations r
         JOIN {$this->db->prefix}users u ON r.user_id = u.ID
         JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
         WHERE r.register_id IN (" . implode(',', array_fill(0, count($registration_ids), '%d')) . ")",
        $registration_ids
    ));

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Bulk Registration Confirmation</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2>Bulk Registration Confirmation</h2>
            <p>Your bulk registration has been processed successfully. Here are the details:</p>

            <div style="background: #f8f9fa; padding: 15px; margin: 20px 0;">
                <h3>Registered Students:</h3>
                <ul>
                <?php foreach ($registrations as $reg): ?>
                    <li>
                        <?php echo esc_html($reg->display_name); ?> -
                        <a href="<?php echo esc_url($reg->youtube_link); ?>">View Video</a>
                    </li>
                <?php endforeach; ?>
                </ul>
                <p><strong>Competition:</strong> <?php echo esc_html($registrations[0]->competition_name); ?></p>
            </div>

            <p>Individual confirmation emails have been sent to each student.</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

    /**
     * Send Forum registration notification emails
     * For students: sends to student, supervisor (if exists), and parent (if exists)
     * For supervisors: sends to supervisor
     * For parents: sends to parent
     */
    public function send_forum_registration_emails($registration_id, $user_id, $role, $is_pre_registration = false) {
        // Get registration details
        $registration = $this->get_forum_registration_details($registration_id);
        if (!$registration) {
            return false;
        }

        // Get user information
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $user_first_name = get_user_meta($user_id, 'first_name', true);
        $user_last_name = get_user_meta($user_id, 'last_name', true);
        $user_name = trim($user_first_name . ' ' . $user_last_name);
        if (empty($user_name)) {
            $user_name = $user->display_name;
        }

        if ($role === 'student') {
            $this->send_forum_student_email($registration, $user_name, $user->user_email, $is_pre_registration);

            if (!empty($registration->supervisor_id)) {
                $sup = get_userdata($registration->supervisor_id);
                if ($sup && $sup->user_email) {
                    $supervisor_name = trim(get_user_meta($registration->supervisor_id, 'first_name', true) . ' ' . get_user_meta($registration->supervisor_id, 'last_name', true));
                    if (empty($supervisor_name)) {
                        $supervisor_name = $sup->display_name ?: $sup->user_email;
                    }
                    $this->send_forum_supervisor_email($registration, $user_name, $user->user_email, $supervisor_name, $sup->user_email, $is_pre_registration);
                }
            }

            $parent_email = get_user_meta($user_id, 'parentemail', true);
            if (!empty($parent_email) && is_email($parent_email)) {
                $parent_first_name = get_user_meta($user_id, 'parentfirstname', true);
                $parent_last_name = get_user_meta($user_id, 'parentlastname', true);
                $parent_name = trim($parent_first_name . ' ' . $parent_last_name);
                if (empty($parent_name)) {
                    $parent_user = get_user_by('email', $parent_email);
                    if ($parent_user) {
                        $parent_name = $parent_user->display_name;
                    } else {
                        $parent_name = $parent_email;
                    }
                }
                $this->send_forum_parent_email($registration, $user_name, $user->user_email, $parent_name, $parent_email, $is_pre_registration);
            }
        } elseif ($role === 'supervisor') {
            $this->send_forum_supervisor_self_email($registration, $user_name, $user->user_email, $is_pre_registration);
        } elseif ($role === 'parent') {
            $this->send_forum_parent_self_email($registration, $user_name, $user->user_email, $is_pre_registration);
        } elseif ($role === 'agency') {
            $this->send_forum_supervisor_self_email($registration, $user_name, $user->user_email, $is_pre_registration);
        }

        return true;
    }

    /**
     * Get Forum registration details
     */
    private function get_forum_registration_details($registration_id) {
        return $this->db->get_row($this->db->prepare("
            SELECT
                r.*,
                u.user_email,
                u.display_name,
                c.competition_name,
                c.competition_date,
                c.competition_type
            FROM {$this->db->prefix}wevoi_registrations r
            JOIN {$this->db->prefix}users u ON r.user_id = u.ID
            JOIN {$this->db->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
            WHERE r.register_id = %d
        ", $registration_id));
    }

    /**
     * Send Forum registration email to student
     */
    private function send_forum_student_email($registration, $student_name, $student_email, $is_pre_registration = false) {
        $subject = $is_pre_registration ? 'WeVoi Forum Pre-Registration Received' : 'WeVoi Forum Registration Confirmation';
        $message = $this->get_forum_student_email_template($registration, $student_name, $is_pre_registration);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: WeVoi Team <noreply@wevoi.org>'
        );

        wp_mail($student_email, $subject, $message, $headers);
    }

    /**
     * Send Forum registration notification email to supervisor
     */
    private function send_forum_supervisor_email($registration, $student_name, $student_email, $supervisor_name, $supervisor_email, $is_pre_registration = false) {
        $subject = $is_pre_registration ? 'Your Student\'s Forum Pre-Registration' : 'Your Student\'s Forum Registration';
        $message = $this->get_forum_supervisor_email_template($registration, $student_name, $student_email, $supervisor_name, $is_pre_registration);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: WeVoi Team <noreply@wevoi.org>'
        );

        wp_mail($supervisor_email, $subject, $message, $headers);
    }

    /**
     * Send Forum registration notification email to parent
     */
    private function send_forum_parent_email($registration, $student_name, $student_email, $parent_name, $parent_email, $is_pre_registration = false) {
        $subject = $is_pre_registration ? 'Your Child\'s Forum Pre-Registration' : 'Your Child\'s Forum Registration';
        $message = $this->get_forum_parent_email_template($registration, $student_name, $student_email, $parent_name, $is_pre_registration);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: WeVoi Team <noreply@wevoi.org>'
        );

        wp_mail($parent_email, $subject, $message, $headers);
    }

    /**
     * Get Forum student email template
     */
    private function get_forum_student_email_template($registration, $student_name, $is_pre_registration = false) {
        $forum_date = !empty($registration->competition_date) ? date('F d, Y', strtotime($registration->competition_date)) : 'TBA';
        $registration_status = 'Application Accepted';
        $payment_status = $is_pre_registration ? 'Pre-registered (payment not yet due)' : ucfirst(strtolower($registration->paid_status));
        $price_section = '';

        if ($registration->paid_status !== 'FREE' && floatval($registration->price) > 0) {
            if ($is_pre_registration) {
                $price_section = '<p><strong>Registration fee (due when payment opens):</strong> £' . number_format($registration->price, 2) . '</p>';
            } else {
                $price_section = '<p><strong>Registration Fee:</strong> £' . number_format($registration->price, 2) . '</p>';
            }
        }

        $heading = $is_pre_registration ? 'WeVoi Forum Pre-Registration Received' : 'WeVoi Forum Registration Confirmation';
        $intro = $is_pre_registration
            ? '<p>Thank you for completing your <strong>pre-registration</strong> for WeVoi Forum.</p><p>No payment has been taken. When payment opens, you will be able to complete checkout from your WeVoi account dashboard or by returning to the Forum registration page.</p>'
            : '<p>Thank you for successfully registering for WeVoi Forum!</p>';
        $footer = $is_pre_registration
            ? '<p>We will email you if any further information is required. Please log in to your dashboard to complete payment when it becomes available.</p>'
            : '<p>Your registration has been completed. All updates regarding the Forum will be sent to you via email.</p>';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($heading); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0066cc;"><?php echo esc_html($heading); ?></h2>

                <p>Dear <?php echo esc_html($student_name); ?>,</p>

                <?php echo $intro; ?>

                <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p><strong>Your Registration Details:</strong></p>
                    <p><strong>Forum Name:</strong> <?php echo esc_html($registration->competition_name); ?></p>
                    <p><strong>Forum Date:</strong> <?php echo esc_html($forum_date); ?></p>
                    <p><strong>Registration Status:</strong> <?php echo esc_html($registration_status); ?></p>
                    <p><strong>Payment Status:</strong> <?php echo esc_html($payment_status); ?></p>
                    <?php echo $price_section; ?>
                </div>

                <?php echo $footer; ?>

                <p>If you have any questions, please feel free to contact us.</p>

                <p>Best regards,<br>WeVoi Team</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get Forum supervisor email template
     */
    private function get_forum_supervisor_email_template($registration, $student_name, $student_email, $supervisor_name, $is_pre_registration = false) {
        $forum_date = !empty($registration->competition_date) ? date('F d, Y', strtotime($registration->competition_date)) : 'TBA';
        $registration_status = 'Application Accepted';
        $payment_status = $is_pre_registration ? 'Pre-registered (payment not yet due)' : ucfirst(strtolower($registration->paid_status));
        $heading = $is_pre_registration ? 'Your Student\'s Forum Pre-Registration' : 'Your Student\'s Forum Registration';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($heading); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0066cc;"><?php echo esc_html($heading); ?></h2>

                <p>Dear <?php echo esc_html($supervisor_name); ?>,</p>

                <?php if ($is_pre_registration) : ?>
                <p>Your student <?php echo esc_html($student_name); ?> has completed <strong>pre-registration</strong> for WeVoi Forum. No payment has been taken yet.</p>
                <?php else : ?>
                <p>Your student <?php echo esc_html($student_name); ?> has successfully registered for WeVoi Forum.</p>
                <?php endif; ?>

                <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p><strong>Student Information:</strong></p>
                    <p><strong>Student Name:</strong> <?php echo esc_html($student_name); ?></p>
                    <p><strong>Student Email:</strong> <?php echo esc_html($student_email); ?></p>
                    <p><strong>Forum Name:</strong> <?php echo esc_html($registration->competition_name); ?></p>
                    <p><strong>Forum Date:</strong> <?php echo esc_html($forum_date); ?></p>
                    <p><strong>Registration Status:</strong> <?php echo esc_html($registration_status); ?></p>
                    <p><strong>Payment Status:</strong> <?php echo esc_html($payment_status); ?></p>
                </div>

                <?php if ($is_pre_registration) : ?>
                <p>They will complete payment from their dashboard when payment opens. We will keep you informed by email where appropriate.</p>
                <?php else : ?>
                <p>Your student's registration has been completed. All updates regarding the Forum will be sent to both you and the student via email.</p>
                <?php endif; ?>

                <p>If you have any questions, please feel free to contact us.</p>

                <p>Best regards,<br>WeVoi Team</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get Forum parent email template
     */
    private function get_forum_parent_email_template($registration, $student_name, $student_email, $parent_name, $is_pre_registration = false) {
        $forum_date = !empty($registration->competition_date) ? date('F d, Y', strtotime($registration->competition_date)) : 'TBA';
        $registration_status = 'Application Accepted';
        $payment_status = $is_pre_registration ? 'Pre-registered (payment not yet due)' : ucfirst(strtolower($registration->paid_status));
        $heading = $is_pre_registration ? 'Your Child\'s Forum Pre-Registration' : 'Your Child\'s Forum Registration';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($heading); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0066cc;"><?php echo esc_html($heading); ?></h2>

                <p>Dear <?php echo esc_html($parent_name); ?>,</p>

                <?php if ($is_pre_registration) : ?>
                <p>Your child <?php echo esc_html($student_name); ?> has completed <strong>pre-registration</strong> for WeVoi Forum. No payment has been taken yet.</p>
                <?php else : ?>
                <p>Your child <?php echo esc_html($student_name); ?> has successfully registered for WeVoi Forum.</p>
                <?php endif; ?>

                <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p><strong>Student Information:</strong></p>
                    <p><strong>Student Name:</strong> <?php echo esc_html($student_name); ?></p>
                    <p><strong>Student Email:</strong> <?php echo esc_html($student_email); ?></p>
                    <p><strong>Forum Name:</strong> <?php echo esc_html($registration->competition_name); ?></p>
                    <p><strong>Forum Date:</strong> <?php echo esc_html($forum_date); ?></p>
                    <p><strong>Registration Status:</strong> <?php echo esc_html($registration_status); ?></p>
                    <p><strong>Payment Status:</strong> <?php echo esc_html($payment_status); ?></p>
                </div>

                <?php if ($is_pre_registration) : ?>
                <p>They will complete payment from their dashboard when payment opens. We will keep you informed by email where appropriate.</p>
                <?php else : ?>
                <p>Your child's registration has been completed. All updates regarding the Forum will be sent to both you and the student via email.</p>
                <?php endif; ?>

                <p>If you have any questions, please feel free to contact us.</p>

                <p>Best regards,<br>WeVoi Team</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send Forum registration email to supervisor (when supervisor registers themselves)
     */
    private function send_forum_supervisor_self_email($registration, $supervisor_name, $supervisor_email, $is_pre_registration = false) {
        $subject = $is_pre_registration ? 'WeVoi Forum Pre-Registration Received' : 'WeVoi Forum Registration Confirmation';
        $message = $this->get_forum_supervisor_self_email_template($registration, $supervisor_name, $is_pre_registration);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: WeVoi Team <noreply@wevoi.org>'
        );

        wp_mail($supervisor_email, $subject, $message, $headers);
    }

    /**
     * Send Forum registration email to parent (when parent registers themselves)
     */
    private function send_forum_parent_self_email($registration, $parent_name, $parent_email, $is_pre_registration = false) {
        $subject = $is_pre_registration ? 'WeVoi Forum Pre-Registration Received' : 'WeVoi Forum Registration Confirmation';
        $message = $this->get_forum_parent_self_email_template($registration, $parent_name, $is_pre_registration);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: WeVoi Team <noreply@wevoi.org>'
        );

        wp_mail($parent_email, $subject, $message, $headers);
    }

    /**
     * Get Forum supervisor self-registration email template
     */
    private function get_forum_supervisor_self_email_template($registration, $supervisor_name, $is_pre_registration = false) {
        $forum_date = !empty($registration->competition_date) ? date('F d, Y', strtotime($registration->competition_date)) : 'TBA';
        $registration_status = 'Application Accepted';
        $payment_status = $is_pre_registration ? 'Pre-registered (payment not yet due)' : ucfirst(strtolower($registration->paid_status));
        $price_section = '';

        if ($registration->paid_status !== 'FREE' && floatval($registration->price) > 0) {
            $price_section = $is_pre_registration
                ? '<p><strong>Registration fee (due when payment opens):</strong> £' . number_format($registration->price, 2) . '</p>'
                : '<p><strong>Registration Fee:</strong> £' . number_format($registration->price, 2) . '</p>';
        }

        $heading = $is_pre_registration ? 'WeVoi Forum Pre-Registration Received' : 'WeVoi Forum Registration Confirmation';
        $intro = $is_pre_registration
            ? '<p>Thank you for completing your <strong>pre-registration</strong> for WeVoi Forum.</p><p>No payment has been taken. When payment opens, complete checkout from your WeVoi account dashboard or the Forum registration page.</p>'
            : '<p>Thank you for successfully registering for WeVoi Forum!</p>';
        $footer = $is_pre_registration
            ? '<p>Please log in to your dashboard to complete payment when it becomes available.</p>'
            : '<p>Your registration has been completed. All updates regarding the Forum will be sent to you via email.</p>';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($heading); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0066cc;"><?php echo esc_html($heading); ?></h2>

                <p>Dear <?php echo esc_html($supervisor_name); ?>,</p>

                <?php echo $intro; ?>

                <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p><strong>Your Registration Details:</strong></p>
                    <p><strong>Forum Name:</strong> <?php echo esc_html($registration->competition_name); ?></p>
                    <p><strong>Forum Date:</strong> <?php echo esc_html($forum_date); ?></p>
                    <p><strong>Registration Status:</strong> <?php echo esc_html($registration_status); ?></p>
                    <p><strong>Payment Status:</strong> <?php echo esc_html($payment_status); ?></p>
                    <?php echo $price_section; ?>
                </div>

                <?php echo $footer; ?>

                <p>If you have any questions, please feel free to contact us.</p>

                <p>Best regards,<br>WeVoi Team</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get Forum parent self-registration email template
     */
    private function get_forum_parent_self_email_template($registration, $parent_name, $is_pre_registration = false) {
        $forum_date = !empty($registration->competition_date) ? date('F d, Y', strtotime($registration->competition_date)) : 'TBA';
        $registration_status = 'Application Accepted';
        $payment_status = $is_pre_registration ? 'Pre-registered (payment not yet due)' : ucfirst(strtolower($registration->paid_status));
        $price_section = '';

        if ($registration->paid_status !== 'FREE' && floatval($registration->price) > 0) {
            $price_section = $is_pre_registration
                ? '<p><strong>Registration fee (due when payment opens):</strong> £' . number_format($registration->price, 2) . '</p>'
                : '<p><strong>Registration Fee:</strong> £' . number_format($registration->price, 2) . '</p>';
        }

        $heading = $is_pre_registration ? 'WeVoi Forum Pre-Registration Received' : 'WeVoi Forum Registration Confirmation';
        $intro = $is_pre_registration
            ? '<p>Thank you for completing your <strong>pre-registration</strong> for WeVoi Forum.</p><p>No payment has been taken. When payment opens, complete checkout from your WeVoi account dashboard or the Forum registration page.</p>'
            : '<p>Thank you for successfully registering for WeVoi Forum!</p>';
        $footer = $is_pre_registration
            ? '<p>Please log in to your dashboard to complete payment when it becomes available.</p>'
            : '<p>Your registration has been completed. All updates regarding the Forum will be sent to you via email.</p>';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($heading); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0066cc;"><?php echo esc_html($heading); ?></h2>

                <p>Dear <?php echo esc_html($parent_name); ?>,</p>

                <?php echo $intro; ?>

                <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p><strong>Your Registration Details:</strong></p>
                    <p><strong>Forum Name:</strong> <?php echo esc_html($registration->competition_name); ?></p>
                    <p><strong>Forum Date:</strong> <?php echo esc_html($forum_date); ?></p>
                    <p><strong>Registration Status:</strong> <?php echo esc_html($registration_status); ?></p>
                    <p><strong>Payment Status:</strong> <?php echo esc_html($payment_status); ?></p>
                    <?php echo $price_section; ?>
                </div>

                <?php echo $footer; ?>

                <p>If you have any questions, please feel free to contact us.</p>

                <p>Best regards,<br>WeVoi Team</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

}

