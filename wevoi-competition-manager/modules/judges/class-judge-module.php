<?php
/**
 * Judge Module
 *
 * @package Wevoi_Competition_Manager
 * @subpackage Judges
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Judge Module main class.
 */
class Wevoi_Judge_Module {
    /**
     * Instance of this class.
     *
     * @var Wevoi_Judge_Module
     */
    private static $instance = null;

    /**
     * Judge Repository instance.
     *
     * @var Wevoi_Judge_Repository
     */
    private $repository;

    /**
     * Judge Service instance.
     *
     * @var Wevoi_Judge_Service
     */
    private $service;

    /**
     * Module directory path.
     *
     * @var string
     */
    private $dir_path;

    /**
     * Module directory URL.
     *
     * @var string
     */
    private $dir_url;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->dir_path = plugin_dir_path(__FILE__);
        $this->dir_url = plugin_dir_url(__FILE__);
        
        $this->load_dependencies();
        $this->init_components();
        $this->define_hooks();
    }

    /**
     * Get instance of this class.
     *
     * @return Wevoi_Judge_Module
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load required dependencies.
     */
    private function load_dependencies() {
        // Model
        require_once $this->dir_path . 'models/class-judge.php';
        
        // Repository
        require_once $this->dir_path . 'repositories/class-judge-repository.php';
        
        // Services
        require_once $this->dir_path . 'services/class-judge-service.php';
    }

    /**
     * Initialize components.
     */
    private function init_components() {
        $this->repository = new Wevoi_Judge_Repository();
        $this->service = new Wevoi_Judge_Service($this->repository);
    }

    /**
     * Define WordPress hooks for this module.
     */
    private function define_hooks() {
        // Handle export CSV - daha erken bir kancada tanımlayalım
        add_action('init', array($this, 'handle_export_csv'), 5);
        
        // Admin menu hook
        add_action('wevoi_register_admin_tabs', array($this, 'register_admin_tab'));
        
        // Tab content hook
        add_action('wevoi_competition_tab_judges', array($this, 'render_judges_page'));
        
        // AJAX handlers
        add_action('wp_ajax_get_judge_details', array($this->service, 'ajax_get_judge_details'));
        add_action('wp_ajax_update_judge', array($this->service, 'ajax_update_judge'));
        
        // Asset loading
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Handle judge deletion
        add_action('admin_init', array($this->service, 'handle_judge_deletion'));
    }

    /**
     * Register admin tab.
     *
     * @param array $tabs Current admin tabs.
     * @return array Modified admin tabs.
     */
    public function register_admin_tab($tabs) {
        $tabs['judges'] = __('Judges', 'wevoi-competition-manager');
        return $tabs;
    }

    /**
     * Render judges page content.
     */
    public function render_judges_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch($action) {
            case 'details':
                $this->render_view('details');
                break;
            case 'list':
            default:
                $this->render_view('list');
                break;
        }
    }

    /**
     * Render a view.
     *
     * @param string $view View name.
     * @param array $args Arguments to pass to the view.
     */
    public function render_view($view, $args = array()) {
        // Extract args to variables
        if (!empty($args)) {
            extract($args);
        }
        
        // Include view file
        $view_path = $this->dir_path . 'views/' . $view . '.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            printf(__('View file not found: %s', 'wevoi-competition-manager'), $view_path);
        }
    }

    /**
     * Enqueue assets.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'wevoi-competitions') === false) {
            return;
        }
        
        // Only load when on judges tab
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'judges') {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'wevoi-judges',
            $this->dir_url . 'assets/css/judge-admin.css',
            array(),
            WEVOI_CM_VERSION
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'wevoi-judges',
            $this->dir_url . 'assets/js/judge-admin.js',
            array('jquery'),
            WEVOI_CM_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wevoi-judges', 'wevoiJudges', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wevoi_judge_nonce'),
            'messages' => array(
                'confirmDelete' => __('Are you sure you want to delete this judge?', 'wevoi-competition-manager'),
                'deleteError' => __('Error deleting judge.', 'wevoi-competition-manager'),
                'updateSuccess' => __('Judge updated successfully.', 'wevoi-competition-manager'),
                'updateError' => __('Error updating judge.', 'wevoi-competition-manager')
            )
        ));
    }

    /**
     * Get repository instance.
     *
     * @return Wevoi_Judge_Repository
     */
    public function get_repository() {
        return $this->repository;
    }

    /**
     * Get service instance.
     *
     * @return Wevoi_Judge_Service
     */
    public function get_service() {
        return $this->service;
    }

    /**
     * Handle export to CSV action
     */
    public function handle_export_csv() {
        // Basit kontrol - tab ve action parametreleri doğru mu?
        if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && 
            isset($_GET['tab']) && $_GET['tab'] === 'judges') {
            
            // Verify permissions
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to export judges.', 'wevoi-competition-manager'));
            }
            
            // Database connection
            global $wpdb;
            
            // Filtreleri al
            $where_clauses = [];
            $join_clauses = [];
            
            // Ana sorgu
            $sql = "SELECT u.ID, u.user_registered, u.user_email, u.display_name, 
                   umfn.meta_value as first_name, 
                   umln.meta_value as last_name,
                   umc.meta_value as country_id, 
                   c.countryname as country_name,
                   ums.meta_value as school_id, 
                   s.schoolname as school_name,
                   COUNT(DISTINCT ja.assignment_id) as assignment_count,
                   COUNT(DISTINCT je.evaluation_id) as evaluation_count
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities'
            LEFT JOIN {$wpdb->usermeta} umfn ON u.ID = umfn.user_id AND umfn.meta_key = 'first_name'
            LEFT JOIN {$wpdb->usermeta} umln ON u.ID = umln.user_id AND umln.meta_key = 'last_name'
            LEFT JOIN {$wpdb->usermeta} umc ON u.ID = umc.user_id AND umc.meta_key = 'countryid'
            LEFT JOIN {$wpdb->usermeta} ums ON u.ID = ums.user_id AND ums.meta_key = 'schoolid'
            LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = umc.meta_value
            LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = ums.meta_value
            LEFT JOIN {$wpdb->prefix}judge_assignments ja ON u.ID = ja.judge_id
            LEFT JOIN {$wpdb->prefix}judge_evaluations je ON ja.assignment_id = je.assignment_id
            WHERE um.meta_value LIKE '%\"judge\"%'";
            
            // Ülke filtresi
            if (isset($_GET['country']) && !empty($_GET['country'])) {
                $country_id = intval($_GET['country']);
                $sql .= $wpdb->prepare(" AND umc.meta_value = %d", $country_id);
            }
            
            // Okul filtresi
            if (isset($_GET['school']) && !empty($_GET['school'])) {
                $school_id = intval($_GET['school']);
                $sql .= $wpdb->prepare(" AND ums.meta_value = %d", $school_id);
            }
            
            // Arama filtresi
            if (isset($_GET['s']) && !empty($_GET['s'])) {
                $search_term = '%' . $wpdb->esc_like($_GET['s']) . '%';
                $sql .= $wpdb->prepare(" AND (u.display_name LIKE %s OR u.user_email LIKE %s)", 
                                      $search_term, $search_term);
            }
            
            // Group by
            $sql .= " GROUP BY u.ID";
            
            // Değerlendirme durumu filtresi
            if (isset($_GET['evaluation_status']) && !empty($_GET['evaluation_status'])) {
                $status = sanitize_text_field($_GET['evaluation_status']);
                switch ($status) {
                    case 'not_started':
                        $sql .= " HAVING assignment_count > 0 AND evaluation_count = 0";
                        break;
                    case 'evaluating':
                        $sql .= " HAVING evaluation_count > 0 AND evaluation_count < assignment_count";
                        break;
                    case 'completed':
                        $sql .= " HAVING assignment_count > 0 AND evaluation_count = assignment_count";
                        break;
                }
            }
            
            // Sırala
            $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user_registered';
            $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
            
            $allowed_orderby = ['user_registered', 'display_name', 'assignment_count', 'evaluation_count'];
            if (!in_array($orderby, $allowed_orderby)) {
                $orderby = 'user_registered';
            }
            
            $sql .= " ORDER BY {$orderby} {$order}";
            
            // Sorguyu çalıştır
            $judges = $wpdb->get_results($sql);
            
            if (empty($judges)) {
                wp_die(__('No judges found to export.', 'wevoi-competition-manager'));
            }
            
            // Tüm çıktı tamponlamalarını temizle
            if (ob_get_level()) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }
            
            // CSV başlıkları
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=judges-export-' . date('Y-m-d') . '.csv');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Çıktı akışını aç
            $output = fopen('php://output', 'w');
            
            // UTF-8 BOM ekle
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // CSV başlık satırı
            fputcsv($output, [
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
            ]);
            
            // Hata ayıklama
            // error_log('Exporting ' . count($judges) . ' judges');
            
            // Veri satırları
            foreach ($judges as $judge) {
                // Değerlendirme durumunu belirle
                $status_text = '';
                if ($judge->assignment_count > 0) {
                    if ($judge->evaluation_count == 0) {
                        $status_text = __('Not started', 'wevoi-competition-manager');
                    } else if ($judge->evaluation_count < $judge->assignment_count) {
                        $status_text = __('Evaluating', 'wevoi-competition-manager');
                    } else {
                        $status_text = __('Completed', 'wevoi-competition-manager');
                    }
                }
                
                // İsim değerlerini al
                $first_name = !empty($judge->first_name) ? ucfirst(strtolower($judge->first_name)) : '';
                $last_name = !empty($judge->last_name) ? ucfirst(strtolower($judge->last_name)) : '';
                
                // İsim alanları boşsa display_name'i parçala
                if (empty($first_name) || empty($last_name)) {
                    $name_parts = explode(' ', $judge->display_name);
                    if (empty($first_name)) {
                        $first_name = ucfirst(strtolower($name_parts[0]));
                    }
                    if (empty($last_name) && count($name_parts) > 1) {
                        $last_name = ucfirst(strtolower(end($name_parts)));
                    }
                }
                
                fputcsv($output, [
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($judge->user_registered)),
                    $judge->ID,
                    $first_name,
                    $last_name,
                    $judge->user_email,
                    !empty($judge->country_name) ? $judge->country_name : '',
                    !empty($judge->school_name) ? $judge->school_name : '',
                    $judge->evaluation_count,
                    $judge->assignment_count,
                    $status_text
                ]);
            }
            
            fclose($output);
            exit;
        }
    }
}