<?php
/**
 * Judge Model
 *
 * @package Wevoi_Competition_Manager
 * @subpackage Judges
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Judge model class.
 */
class Wevoi_Judge {
    /**
     * Judge ID.
     *
     * @var int
     */
    public $id;
    
    /**
     * Judge user data.
     *
     * @var object
     */
    public $user_data;
    
    /**
     * Judge country ID.
     *
     * @var int
     */
    public $country_id;
    
    /**
     * Judge country name.
     *
     * @var string
     */
    public $country_name;
    
    /**
     * Judge school ID.
     *
     * @var int
     */
    public $school_id;
    
    /**
     * Judge school name.
     *
     * @var string
     */
    public $school_name;
    
    /**
     * Assignment count.
     *
     * @var int
     */
    public $assignment_count;
    
    /**
     * Evaluated count.
     *
     * @var int
     */
    public $evaluated_count;
    
    /**
     * Constructor.
     *
     * @param object $data Judge data.
     */
    public function __construct($data = null) {
        if ($data) {
            $this->set_data($data);
        }
    }
    
    /**
     * Set judge data.
     *
     * @param object $data Judge data.
     */
    public function set_data($data) {
        $this->id = isset($data->ID) ? intval($data->ID) : 0;
        $this->user_data = $data;
        $this->country_id = isset($data->countryid) ? intval($data->countryid) : 0;
        $this->country_name = isset($data->countryname) ? $data->countryname : '';
        $this->school_id = isset($data->schoolid) ? intval($data->schoolid) : 0;
        $this->school_name = isset($data->schoolname) ? $data->schoolname : '';
        $this->assignment_count = isset($data->assignment_count) ? intval($data->assignment_count) : 0;
        $this->evaluated_count = isset($data->evaluated_count) ? intval($data->evaluated_count) : 0;
    }
    
    /**
     * Get judge name.
     *
     * @return string
     */
    public function get_name() {
        $first_name = ucfirst(strtolower(get_user_meta($this->id, 'first_name', true)));
        $last_name = ucfirst(strtolower(get_user_meta($this->id, 'last_name', true)));
        
        $full_name = trim(sprintf('%s %s', $first_name, $last_name));
        
        if (empty($full_name)) {
            $full_name = ucwords(strtolower($this->user_data->display_name));
        }
        
        return $full_name;
    }
    
    /**
     * Get judge first name.
     *
     * @return string
     */
    public function get_first_name() {
        $first_name = get_user_meta($this->id, 'first_name', true);
        if (empty($first_name)) {
            // Eğer first_name boşsa display_name'i kullan
            $display_name_parts = explode(' ', $this->user_data->display_name);
            $first_name = $display_name_parts[0];
        }
        return ucfirst(strtolower($first_name));
    }
    
    /**
     * Get judge last name.
     *
     * @return string
     */
    public function get_last_name() {
        $last_name = get_user_meta($this->id, 'last_name', true);
        if (empty($last_name)) {
            // Eğer last_name boşsa ve display_name birden fazla kelimeden oluşuyorsa
            // son kısmını kullan
            $display_name_parts = explode(' ', $this->user_data->display_name);
            if (count($display_name_parts) > 1) {
                $last_name = end($display_name_parts);
            } else {
                $last_name = '';
            }
        }
        return ucfirst(strtolower($last_name));
    }
    
    /**
     * Get judge email.
     *
     * @return string
     */
    public function get_email() {
        return $this->user_data->user_email;
    }
    
    /**
     * Get judge registration date.
     *
     * @param string $format Date format.
     * @return string
     */
    public function get_registration_date($format = '') {
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }
        
        return date_i18n($format, strtotime($this->user_data->user_registered));
    }
    
    /**
     * Get evaluation status.
     *
     * @return string
     */
    public function get_evaluation_status() {
        if ($this->assignment_count == 0) {
            return '';
        } elseif ($this->evaluated_count == 0) {
            return 'not-started';
        } elseif ($this->evaluated_count < $this->assignment_count) {
            return 'in-progress';
        } else {
            return 'completed';
        }
    }
    
    /**
     * Get evaluation status text.
     *
     * @return string
     */
    public function get_evaluation_status_text() {
        $status = $this->get_evaluation_status();
        
        switch ($status) {
            case 'not-started':
                return __('Not started', 'wevoi-competition-manager');
            case 'in-progress':
                return __('Evaluating', 'wevoi-competition-manager');
            case 'completed':
                return __('Completed', 'wevoi-competition-manager');
            default:
                return '';
        }
    }
}