<?php
/**
 * Judge Service
 *
 * @package Wevoi_Competition_Manager
 * @subpackage Judges
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Judge service class.
 */
class Wevoi_Judge_Service {
    /**
     * Judge repository instance.
     *
     * @var Wevoi_Judge_Repository
     */
    private $repository;
    
    /**
     * Constructor.
     *
     * @param Wevoi_Judge_Repository $repository Judge repository.
     */
    public function __construct($repository) {
        $this->repository = $repository;
    }
    
    /**
     * Get judges for list table.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_judges_for_list_table($args = array()) {
        return $this->repository->get_judges($args);
    }
    
    /**
     * Get judge by ID.
     *
     * @param int $judge_id Judge ID.
     * @return Wevoi_Judge|false
     */
    public function get_judge($judge_id) {
        return $this->repository->get_judge($judge_id);
    }
    
    /**
     * Get judge details data.
     *
     * @param int $judge_id Judge ID.
     * @return array
     */
    public function get_judge_details_data($judge_id) {
        $judge = $this->repository->get_judge($judge_id);
        
        if (!$judge) {
            return false;
        }
        
        $assignments = $this->repository->get_judge_assignments($judge_id);
        $certificates = $this->repository->get_judge_certificates($judge_id);
        
        return array(
            'judge' => $judge,
            'assignments' => $assignments,
            'certificates' => $certificates
        );
    }
    
    /**
     * Update judge.
     *
     * @param int $judge_id Judge ID.
     * @param array $data Judge data.
     * @return bool
     */
    public function update_judge($judge_id, $data) {
        return $this->repository->update_judge($judge_id, $data);
    }
    
    /**
     * Delete judge.
     *
     * @param int $judge_id Judge ID.
     * @return bool
     */
    public function delete_judge($judge_id) {
        return $this->repository->delete_judge($judge_id);
    }
    
    /**
     * AJAX handler for getting judge details.
     */
    public function ajax_get_judge_details() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_judge_details')) {
            wp_send_json_error(__('Security check failed', 'wevoi-competition-manager'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wevoi-competition-manager'));
            return;
        }
        
        // Check judge ID
        $judge_id = isset($_POST['judge_id']) ? intval($_POST['judge_id']) : 0;
        if (!$judge_id) {
            wp_send_json_error(__('Invalid judge ID', 'wevoi-competition-manager'));
            return;
        }
        
        // Get judge details data
        $data = $this->get_judge_details_data($judge_id);
        
        if (!$data) {
            wp_send_json_error(__('Judge not found', 'wevoi-competition-manager'));
            return;
        }
        
        // Start output buffer
        ob_start();
        
        // Include details view
        require_once plugin_dir_path(dirname(__FILE__)) . 'views/details.php';
        
        // Get output
        $html = ob_get_clean();
        
        // Send response
        wp_send_json_success($html);
    }
    
   /**
    * AJAX handler for updating judge.
    */
   public function ajax_update_judge() {
       // Check nonce
       if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update_judge_data')) {
           wp_send_json_error(__('Security check failed', 'wevoi-competition-manager'));
           return;
       }
       
       // Check permissions
       if (!current_user_can('manage_options')) {
           wp_send_json_error(__('Permission denied', 'wevoi-competition-manager'));
           return;
       }
       
       // Check judge ID
       $judge_id = isset($_POST['judge_id']) ? intval($_POST['judge_id']) : 0;
       if (!$judge_id) {
           wp_send_json_error(__('Invalid judge ID', 'wevoi-competition-manager'));
           return;
       }
       
       // Prepare data
       $data = array(
           'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
           'first_name' => isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '',
           'last_name' => isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : ''
       );
       
       // Update judge
       $result = $this->update_judge($judge_id, $data);
       
       if (!$result) {
           wp_send_json_error(__('Failed to update judge', 'wevoi-competition-manager'));
           return;
       }
       
       wp_send_json_success(__('Judge updated successfully', 'wevoi-competition-manager'));
   }
   
   /**
    * Handle judge deletion.
    */
   public function handle_judge_deletion() {
       // Bulk deletion
       if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['judges'])) {
           check_admin_referer('bulk-judges');
           
           if (!current_user_can('manage_options')) {
               wp_die(__('You do not have permission to delete judges.', 'wevoi-competition-manager'));
           }

           $deleted = 0;
           $judge_ids = array_map('intval', $_POST['judges']);
           
           foreach ($judge_ids as $judge_id) {
               if ($this->delete_judge($judge_id)) {
                   $deleted++;
               }
           }

           wp_redirect(add_query_arg(
               [
                   'page' => 'wevoi-competitions',
                   'tab' => 'judges',
                   'deleted' => $deleted
               ],
               admin_url('admin.php')
           ));
           exit;
       }

       // Single deletion
       if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['judge'])) {
           check_admin_referer('delete_judge_' . $_GET['judge']);
           
           if (!current_user_can('manage_options')) {
               wp_die(__('You do not have permission to delete judges.', 'wevoi-competition-manager'));
           }

           $judge_id = intval($_GET['judge']);
           $deleted = $this->delete_judge($judge_id) ? 1 : 0;

           wp_redirect(add_query_arg(
               [
                   'page' => 'wevoi-competitions',
                   'tab' => 'judges',
                   'deleted' => $deleted
               ],
               admin_url('admin.php')
           ));
           exit;
       }
   }
   
   /**
    * Export judges to CSV
    * 
    * Exports all judges to a CSV file based on the current filters
    */
   public function export_judges_to_csv() {
       // Script execution time ve memory limit arttıralım
       set_time_limit(300); // 5 dakika
       ini_set('memory_limit', '256M'); // 256MB'a çıkaralım
       
       if (!current_user_can('manage_options')) {
           wp_die(__('You do not have permission to export judges.', 'wevoi-competition-manager'));
       }
       
       error_log('Starting CSV export process');
       
       // Get filters from URL
       $country = isset($_GET['country']) && !empty($_GET['country']) ? intval($_GET['country']) : 0;
       $school = isset($_GET['school']) && !empty($_GET['school']) ? intval($_GET['school']) : 0;
       $evaluation_status = isset($_GET['evaluation_status']) && !empty($_GET['evaluation_status']) ? sanitize_text_field($_GET['evaluation_status']) : '';
       $search = isset($_GET['s']) && !empty($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
       $orderby = isset($_GET['orderby']) && !empty($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user_registered';
       $order = isset($_GET['order']) && !empty($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
       
       error_log('Export filters - Country: ' . $country . ', School: ' . $school . ', Status: ' . $evaluation_status);
       
       // Get all judges (no pagination for export)
       try {
           error_log('Calling repository get_judges with per_page=-1');
           $result = $this->repository->get_judges([
               'per_page' => -1, // Get all
               'page' => 1,
               'search' => $search,
               'country' => $country,
               'school' => $school,
               'evaluation_status' => $evaluation_status,
               'orderby' => $orderby,
               'order' => $order
           ]);
           
           $judges = $result['items'];
           error_log('Retrieved ' . count($judges) . ' judges for export');
           
           // WordPress'in tüm çıktı işleme mekanizmalarını temizleyelim
           if (ob_get_level()) {
               while (ob_get_level() > 0) {
                   ob_end_clean();
               }
           }
           
           // Geçici dosya oluştur
           $temp_file = tempnam(sys_get_temp_dir(), 'csv_');
           $file = fopen($temp_file, 'w');
           
           // UTF-8 BOM ekle
           fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
           
           // CSV başlıklarını yaz
           $headers = [
               __('Registration Date', 'wevoi-competition-manager'),
               __('ID', 'wevoi-competition-manager'),
               __('First Name', 'wevoi-competition-manager'),
               __('Last Name', 'wevoi-competition-manager'),
               __('Email', 'wevoi-competition-manager'),
               __('Country', 'wevoi-competition-manager'),
               __('School', 'wevoi-competition-manager'),
               __('Evaluations Completed', 'wevoi-competition-manager'),
               __('Total Assignments', 'wevoi-competition-manager'),
               __('Status', 'wevoi-competition-manager')
           ];
           
           fputcsv($file, $headers);
           
           // Verileri yaz
           if (!empty($judges)) {
               foreach ($judges as $judge) {
                   fputcsv($file, [
                       $judge->get_registration_date(),
                       $judge->id,
                       $judge->get_first_name(),
                       $judge->get_last_name(),
                       $judge->get_email(),
                       $judge->country_name,
                       $judge->school_name,
                       $judge->evaluated_count,
                       $judge->assignment_count,
                       $judge->get_evaluation_status_text()
                   ]);
               }
           }
           
           fclose($file);
           
           // Dosyanın içeriğini oku
           $file_content = file_get_contents($temp_file);
           
           // Geçici dosyayı sil
           @unlink($temp_file);
           
           // CSV header'larını ayarla
           header('Content-Type: text/csv; charset=utf-8');
           header('Content-Disposition: attachment; filename=wevoi-judges-export-' . date('Y-m-d') . '.csv');
           header('Content-Length: ' . strlen($file_content));
           header('Pragma: no-cache');
           header('Expires: 0');
           header('Cache-Control: must-revalidate');
           
           // Dosya içeriğini çıktıla
           echo $file_content;
           exit;
           
       } catch (Exception $e) {
           error_log('Error in export_judges_to_csv: ' . $e->getMessage());
           wp_die('Error exporting judges: ' . $e->getMessage());
       }
   }
}