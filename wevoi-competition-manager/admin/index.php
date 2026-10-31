<?php
// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Load necessary dependencies
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Safely handle file uploads
 * 
 * @param string $file_key The file key in $_FILES array
 * @param array $allowed_mime_types List of allowed mime types
 * @param int $max_size Maximum allowed file size in bytes
 * @return array|WP_Error Array with file info on success, WP_Error on failure
 */
function wevoi_handle_file_upload($file_key, $allowed_mime_types = [], $max_size = 0) {
    // Check if file was uploaded
    if (!isset($_FILES[$file_key])) {
        return new WP_Error('no_file', __('No file was uploaded.', 'wevoi-competition-manager'));
    }
    
    // Use WordPress functions to handle upload safely
    $file = $_FILES[$file_key];
    
    // Verify file size
    if ($max_size > 0 && $file['size'] > $max_size) {
        return new WP_Error(
            'file_too_large',
            sprintf(
                __('File is too large. Maximum size is %s.', 'wevoi-competition-manager'),
                size_format($max_size)
            )
        );
    }
    
    // Verify file type
    if (!empty($allowed_mime_types)) {
        $file_type = wp_check_filetype($file['name']);
        if (!in_array($file_type['type'], $allowed_mime_types)) {
            return new WP_Error(
                'invalid_file_type',
                __('This file type is not allowed.', 'wevoi-competition-manager')
            );
        }
    }
    
    // Use WordPress media handling to process the upload
    $upload = wp_handle_upload($file, ['test_form' => false]);
    
    if (isset($upload['error'])) {
        return new WP_Error('upload_error', $upload['error']);
    }
    
    return $upload;
}

/**
 * Securely process form data
 * 
 * @param array $form_data The submitted form data
 * @param array $fields Configuration for allowed fields and their validations
 * @return array|WP_Error Sanitized data on success, WP_Error on validation failure
 */
function wevoi_process_form_data($form_data, $fields) {
    $sanitized_data = [];
    $errors = [];
    
    foreach ($fields as $field_name => $config) {
        // Skip if field is not present and not required
        if (!isset($form_data[$field_name])) {
            if (isset($config['required']) && $config['required']) {
                $errors[$field_name] = __('This field is required.', 'wevoi-competition-manager');
            }
            continue;
        }
        
        $value = $form_data[$field_name];
        
        // Apply sanitization based on type
        if (isset($config['type'])) {
            switch ($config['type']) {
                case 'text':
                    $value = sanitize_text_field($value);
                    break;
                case 'email':
                    $value = sanitize_email($value);
                    if (!is_email($value)) {
                        $errors[$field_name] = __('Please enter a valid email address.', 'wevoi-competition-manager');
                    }
                    break;
                case 'url':
                    $value = esc_url_raw($value);
                    break;
                case 'number':
                    $value = (float) $value;
                    break;
                case 'int':
                    $value = (int) $value;
                    break;
                case 'html':
                    $value = wp_kses_post($value);
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field($value);
                    break;
                case 'checkbox':
                    $value = (bool) $value;
                    break;
                case 'array':
                    $value = array_map('sanitize_text_field', (array) $value);
                    break;
                default:
                    $value = sanitize_text_field($value);
            }
        }
        
        // Apply validation if defined
        if (isset($config['validate']) && is_callable($config['validate'])) {
            $validation_result = call_user_func($config['validate'], $value);
            if ($validation_result !== true) {
                $errors[$field_name] = $validation_result;
                continue;
            }
        }
        
        $sanitized_data[$field_name] = $value;
    }
    
    if (!empty($errors)) {
        return new WP_Error('validation_failed', __('Form validation failed.', 'wevoi-competition-manager'), $errors);
    }
    
    return $sanitized_data;
}