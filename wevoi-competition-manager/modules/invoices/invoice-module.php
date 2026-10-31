<?php
/**
 * Invoice Module for WeVoi Competition Manager
 * 
 * @package WeVoi_Competition_Manager
 * @subpackage Invoice
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Sabitleri kullan
define('WEVOI_INVOICE_VERSION', '1.0.0');

class Wevoi_Invoice_Module {
    private static $instance;
    private $ajax_handler;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
        $this->init_ajax_handlers();
        $this->check_dependencies();
    }

    private function includes() {
        require_once WEVOI_INVOICE_MODULE_PATH . 'includes/class-invoice.php';
        require_once WEVOI_INVOICE_MODULE_PATH . 'includes/class-invoice-list-table.php';
        require_once WEVOI_INVOICE_MODULE_PATH . 'includes/class-invoice-pdf-generator.php';
        require_once WEVOI_INVOICE_MODULE_PATH . 'includes/class-invoice-status-handler.php';
        require_once WEVOI_INVOICE_MODULE_PATH . 'includes/class-invoice-ajax.php';
    }

    private function init_hooks() {
        // Hook into the admin menu
        add_action('wevoi_competition_tab_invoices', array($this, 'display_invoice_tab'));
        
        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    private function init_ajax_handlers() {
        try {
            // Check if required classes exist
            if (!class_exists('Wevoi_Invoice_PDF_Generator')) {
                require_once WEVOI_INVOICE_MODULE_PATH . 'includes/class-invoice-pdf-generator.php';
            }
            
            if (!class_exists('Wevoi_Invoice_Status_Handler')) {
                require_once WEVOI_INVOICE_MODULE_PATH . 'includes/class-invoice-status-handler.php';
            }
            
            if (!class_exists('Wevoi_Invoice_Ajax')) {
                require_once WEVOI_INVOICE_MODULE_PATH . 'includes/class-invoice-ajax.php';
            }
            
            // Initialize AJAX handler
            $this->ajax_handler = new Wevoi_Invoice_Ajax();
        } catch (Exception $e) {
            // Log error and continue gracefully
            error_log('WeVoi Invoice Ajax Handler Initialization Error: ' . $e->getMessage());
        }
    }

    /**
     * Check for required dependencies and show admin notices if any are missing
     */
    private function check_dependencies() {
        // Check if TCPDF is available - try multiple paths
        $tcpdf_found = false;
        
        // Check if TCPDF is already loaded
        if (class_exists('TCPDF')) {
            $tcpdf_found = true;
        }
        
        // Try shared vendor (wp-content/plugins/vendor), then plugin's own vendor
        if (!$tcpdf_found) {
            $plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : plugin_dir_path(dirname(dirname(__FILE__)));
            $plugin_root = plugin_dir_path(dirname(dirname(__FILE__)));
            $shared_tcpdf = $plugins_dir . '/vendor/tecnickcom/tcpdf/tcpdf.php';
            $own_tcpdf    = $plugin_root . 'vendor/tecnickcom/tcpdf/tcpdf.php';
            foreach (array($shared_tcpdf, $own_tcpdf) as $vendor_tcpdf_path) {
                if (file_exists($vendor_tcpdf_path)) {
                    require_once $vendor_tcpdf_path;
                    $tcpdf_found = true;
                    break;
                }
            }
        }
        
        // Try to load from lib directory
        if (!$tcpdf_found) {
            $lib_tcpdf_path = WEVOI_INVOICE_PATH . 'lib/tcpdf/tcpdf.php';
            if (file_exists($lib_tcpdf_path)) {
                require_once $lib_tcpdf_path;
                $tcpdf_found = true;
            }
        }
        
        // Only show notice if TCPDF is still not found
        if (!$tcpdf_found) {
            add_action('admin_notices', array($this, 'missing_tcpdf_notice'));
        }
    }
    
    /**
     * Display admin notice for missing TCPDF library
     */
    public function missing_tcpdf_notice() {
        // Only show on our plugin pages
        $screen = get_current_screen();
        if ($screen->id !== 'wevoi_page_wevoi-competitions' || !isset($_GET['tab']) || $_GET['tab'] !== 'invoices') {
            return;
        }
        
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>WeVoi Invoice Module Error:</strong> The TCPDF library is missing. PDF generation is currently unavailable.</p>';
        echo '<p>To fix this issue, please download the <a href="https://github.com/tecnickcom/TCPDF/releases" target="_blank">TCPDF library</a> and install it in: <code>' . WEVOI_INVOICE_PATH . 'lib/tcpdf/</code></p>';
        echo '</div>';
    }

    public function display_invoice_tab() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'create':
            case 'edit':
                require_once WEVOI_INVOICE_MODULE_PATH . 'views/invoice-form.php';
                break;
            case 'view':
                require_once WEVOI_INVOICE_MODULE_PATH . 'views/invoice-detail.php';
                break;
            case 'list':
            default:
                require_once WEVOI_INVOICE_MODULE_PATH . 'views/invoice-list.php';
                break;
        }
    }
    
    public function enqueue_scripts($hook) {
        // Only load on the competitions page
        if (strpos($hook, 'page_wevoi-competitions') === false) {
            return;
        }

        // Check if we're on the invoices tab
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'invoices') {
            return;
        }
        
        // Register and enqueue styles
        wp_enqueue_style(
            'wevoi-invoice-styles',
            WEVOI_INVOICE_MODULE_URL . 'assets/css/invoice-styles.css',
            array(),
            WEVOI_INVOICE_VERSION
        );
        
        // Register and enqueue scripts
        wp_enqueue_script(
            'wevoi-invoice-scripts',
            WEVOI_INVOICE_MODULE_URL . 'assets/js/invoice-scripts.js',
            array('jquery'),
            WEVOI_INVOICE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'wevoi-invoice-scripts',
            'wevoiInvoice',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('wevoi_invoice_nonce'),
                'strings' => array(
                    'invoice_error'  => __('An error occurred. Please try again.', 'wevoi-competition-manager'),
                    'confirm_delete' => __('Are you sure you want to delete this invoice?', 'wevoi-competition-manager'),
                    'confirm_send'   => __('Are you sure you want to send this invoice?', 'wevoi-competition-manager')
                )
            )
        );
    }
}

// Modülü başlat
Wevoi_Invoice_Module::get_instance();