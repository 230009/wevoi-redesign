<?php
/**
 * Email handler class
 */
class Wevoi_Email_Handler {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Add email modal to admin footer
        add_action('admin_footer', array($this, 'add_email_modal'));
        
        // AJAX handler
        add_action('wp_ajax_send_user_email', array($this, 'handle_email_sending'));
    }

    /**
     * Enqueue necessary assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'wevoi-competitions') === false) {
            return;
        }

        wp_enqueue_script(
            'wevoi-email-handler',
            WEVOI_CM_PLUGIN_URL . 'admin/js/email-handler.js',
            array('jquery'),
            WEVOI_CM_VERSION,
            true
        );

        wp_localize_script('wevoi-email-handler', 'wevoiEmailData', array(
            'signature' => "\n\nBest regards,\nWeVoi Team",
            'nonce' => wp_create_nonce('send_email_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }

    /**
     * Add email modal to admin footer
     */
    public function add_email_modal() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'wevoi-competitions') === false) {
            return;
        }
        
        require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/email-modal.php';
    }

/**
 * Replace email template variables with actual values
 */
private function parse_email_variables($content, $to_email) {
    // Get user data by email
    $user = get_user_by('email', $to_email);
    if (!$user) {
        return $content;
    }
    
    // Get user metadata
    $first_name = get_user_meta($user->ID, 'first_name', true);
    $last_name = get_user_meta($user->ID, 'last_name', true);
    $display_name = $user->display_name;
    
    // Define available variables
    $variables = array(
        '{first_name}' => $first_name,
        '{last_name}' => $last_name,
        '{full_name}' => trim("$first_name $last_name"),
        '{display_name}' => $display_name,
        '{email}' => $to_email,
        '{user_id}' => $user->ID,
    );
    
    // Get user role and add role-specific variables
    $user_roles = $user->roles;
    if (in_array('student', $user_roles)) {
        // Additional student variables if needed
        $variables['{student_name}'] = $variables['{full_name}'];
    } elseif (in_array('supervisor', $user_roles)) {
        // Additional supervisor variables if needed
        $variables['{supervisor_name}'] = $variables['{full_name}'];
    } elseif (in_array('judge', $user_roles)) {
        // Additional judge variables if needed
        $variables['{judge_name}'] = $variables['{full_name}'];
    }
    
    // Replace variables in content
    foreach ($variables as $variable => $value) {
        $content = str_replace($variable, $value, $content);
    }
    
    return $content;
}


    /**
 * Handle email sending via AJAX
 */
public function handle_email_sending() {
    // Nonce check
    check_ajax_referer('send_email_nonce', 'nonce');
    
    // Permission check
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $to = sanitize_email($_POST['to']);
    $subject = sanitize_text_field($_POST['subject']);
    $message = wpautop(wp_kses_post($_POST['message']));
    
    // Parse variables in subject and message
    $subject = $this->parse_email_variables($subject, $to);
    $message = $this->parse_email_variables($message, $to);
    
    // Get sender details
    $from_email = get_option('admin_email');
    $from_name = get_bloginfo('name');
    
    // Email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>'
    );
    
    // Send email
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if ($sent) {
        error_log(sprintf(
            'Email sent to %s with subject: %s',
            $to,
            $subject
        ));
        wp_send_json_success('Email sent successfully');
    } else {
        wp_send_json_error('Failed to send email');
    }
}
}