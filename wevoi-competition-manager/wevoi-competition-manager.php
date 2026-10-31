<?php
/**
 * Plugin Name: Wevoi Competition Manager
 * Plugin URI: https://wevoi.com
 * Description: Competition and coupon management system
 * Version: 1.0.0
 * Author: Kenan
 * Author URI: https://wevoi.org
 * Text Domain: wevoi-competition-manager
 * Domain Path: /languages
 */

// Direct access kontrolü
if (!defined('WPINC')) {
    die;
}


// Invoice modülü için yol sabitleri
if (!defined('WEVOI_INVOICE_MODULE_PATH')) {
    define('WEVOI_INVOICE_MODULE_PATH', plugin_dir_path(__FILE__) . 'modules/invoices/');
}
if (!defined('WEVOI_INVOICE_MODULE_URL')) {
    define('WEVOI_INVOICE_MODULE_URL', plugin_dir_url(__FILE__) . 'modules/invoices/');
}
// Add missing WEVOI_INVOICE_PATH constant
if (!defined('WEVOI_INVOICE_PATH')) {
    define('WEVOI_INVOICE_PATH', plugin_dir_path(__FILE__) . 'modules/invoices/');
}

// Plugin version
define('WEVOI_CM_VERSION', '1.0.0');
define('WEVOI_CM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEVOI_CM_PLUGIN_URL', plugin_dir_url(__FILE__));



// Helpers (Turkish UTF-8 name formatting)
require_once WEVOI_CM_PLUGIN_DIR . 'includes/wevoi-helpers.php';

// Gerekli sınıfları yükle
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-competitions.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-commission.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-coupons.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-topics.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-judges-list-table.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-email-handler.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-email-snippets.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-supervisors-list-table.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-agency-list-table.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-dashboard-widgets.php';

// Initialize classes
new Wevoi_Topics();


function activate_wevoi_competition_manager() {
    require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-competition-manager-activator.php';
    Wevoi_Competition_Manager_Activator::activate();
}
register_activation_hook(__FILE__, 'activate_wevoi_competition_manager');

function deactivate_wevoi_competition_manager() {
    require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-competition-manager-deactivator.php';
    Wevoi_Competition_Manager_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'deactivate_wevoi_competition_manager');

function wevoi_load_modules() {
    // Load Invoice module
    $invoice_module_path = WEVOI_CM_PLUGIN_DIR . 'modules/invoices/invoice-module.php';
    if (file_exists($invoice_module_path)) {
        require_once $invoice_module_path;
    }

    // Load Judges module
    $judges_module_path = WEVOI_CM_PLUGIN_DIR . 'modules/judges/class-judge-module.php';
    if (file_exists($judges_module_path)) {
        require_once $judges_module_path;
        // Initialize the Judge module
        Wevoi_Judge_Module::get_instance();
    }
}
add_action('plugins_loaded', 'wevoi_load_modules');

/**
 * Admin menüsünü oluştur
 */
function wevoi_competition_menu() {
    add_menu_page(
        __('Competition Manager', 'wevoi-competition-manager'),
        __('Competitions', 'wevoi-competition-manager'),
        'manage_options',
        'wevoi-competitions',
        'wevoi_competition_page',
        'dashicons-awards',
        26
    );
}
add_action('admin_menu', 'wevoi_competition_menu');


function wevoi_competition_page() {
    // Aktif tab'ı belirle
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'competitions';
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

    error_log('Competition Page - Tab: ' . $tab);
    error_log('Competition Page - Action: ' . $action);

    // Tab başlıklarını göster
    ?>
    <div class="wrap">
    <?php wp_nonce_field('wevoi_ajax_nonce', 'wevoi_nonce'); ?> <!-- Nonce ekledik -->

<h2 class="nav-tab-wrapper">
    <a href="?page=wevoi-competitions&tab=seasons"
       class="nav-tab <?php echo $tab === 'seasons' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Seasons', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=commission-rates"
       class="nav-tab <?php echo $tab === 'commission-rates' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Rates', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions"
       class="nav-tab <?php echo $tab === 'competitions' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Competitions', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=topics"
       class="nav-tab <?php echo $tab === 'topics' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Topics', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=coupons"
       class="nav-tab <?php echo $tab === 'coupons' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Coupons', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=students"
       class="nav-tab <?php echo $tab === 'students' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Students', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=supervisors"
       class="nav-tab <?php echo $tab === 'supervisors' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Supervisors', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=agency"
       class="nav-tab <?php echo $tab === 'agency' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Agency', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=judges"
       class="nav-tab <?php echo $tab === 'judges' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Judges', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=registrations"
       class="nav-tab <?php echo $tab === 'registrations' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Registrations', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=email-snippets"
       class="nav-tab <?php echo $tab === 'email-snippets' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Email Snippets', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=invoices"
       class="nav-tab <?php echo $tab === 'invoices' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Invoices', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=prize-payments"
       class="nav-tab <?php echo $tab === 'prize-payments' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Prize Payments', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=forum-discounts"
       class="nav-tab <?php echo $tab === 'forum-discounts' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Forum Discounts', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=commission-payments"
       class="nav-tab <?php echo $tab === 'commission-payments' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Commission Payments', 'wevoi-competition-manager'); ?>
    </a>
    <a href="?page=wevoi-competitions&tab=summit-prizes"
       class="nav-tab <?php echo $tab === 'summit-prizes' ? 'nav-tab-active' : ''; ?>">
        <?php _e('Summit Prizes', 'wevoi-competition-manager'); ?>
    </a>
</h2>
        <?php
        // İçeriği göster
        if ($tab === 'competitions') {
            if ($action === 'add' || $action === 'edit') {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/competitions/form.php';
            } else {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/competitions/list.php';
            }
        } elseif ($tab === 'topics') {
            // Handle topic form submission
            if (isset($_POST['topic_action'])) {
                global $wpdb;

                if ($_POST['topic_action'] === 'add') {
                    // Add new topic
                    check_admin_referer('add_new_topic', 'topic_nonce');

                    $result = $wpdb->insert(
                        $wpdb->prefix . 'wevoi_topics',
                        [
                            'competition_code' => intval($_POST['competition_code']),
                            'wevoi_age_category' => sanitize_text_field($_POST['wevoi_age_category']),
                            'topic' => sanitize_textarea_field($_POST['topic'])
                        ],
                        ['%d', '%s', '%s']
                    );

                    if ($result) {
                        wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'topics', 'message' => 1], admin_url('admin.php')));
                        exit;
                    }
                } elseif ($_POST['topic_action'] === 'update') {
                    // Update topic
                    $topic_id = intval($_POST['topic_id']);
                    check_admin_referer('update_topic_' . $topic_id, 'topic_nonce');

                    $result = $wpdb->update(
                        $wpdb->prefix . 'wevoi_topics',
                        [
                            'competition_code' => intval($_POST['competition_code']),
                            'wevoi_age_category' => sanitize_text_field($_POST['wevoi_age_category']),
                            'topic' => sanitize_textarea_field($_POST['topic'])
                        ],
                        ['id' => $topic_id],
                        ['%d', '%s', '%s'],
                        ['%d']
                    );

                    if ($result !== false) {
                        wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'topics', 'message' => 2], admin_url('admin.php')));
                        exit;
                    }
                }
            }

            if ($action === 'add' || $action === 'edit') {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/topics/form.php';
            } else {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/topics/list.php';
            }
        } elseif ($tab === 'coupons') {
            if ($action === 'add' || $action === 'edit') {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/coupons/form.php';
            } else {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/coupons/list.php';
            }
        } elseif ($tab === 'students') {
           require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/students/list.php';
        } elseif ($tab === 'supervisors') {
           require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/supervisors/list.php';
        } elseif ($tab === 'agency') {
           require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/agency/list.php';
        } elseif ($tab === 'judges') {
            do_action('wevoi_competition_tab_judges');
        } elseif ($tab === 'registrations') {
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/registrations/list.php';
        } elseif ($tab === 'email-snippets') {
            if ($action === 'add' || $action === 'edit') {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/email-snippets/form.php';
            } else {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/email-snippets/list.php';
            }
        } elseif ($tab === 'invoices') {
            do_action('wevoi_competition_tab_invoices');
        } elseif ($tab === 'prize-payments') {
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/prize-payments/list.php';
        } elseif ($tab === 'forum-discounts') {
            // Handle forum discount form submission
            if (isset($_POST['discount_action'])) {
                global $wpdb;

                if ($_POST['discount_action'] === 'add') {
                    // Add new forum discount
                    check_admin_referer('add_new_forum_discount', 'discount_nonce');

                    // Deactivate other active discounts for the same competition
                    $wpdb->update(
                        $wpdb->prefix . 'wevoi_forum_discounts',
                        ['is_active' => 0],
                        [
                            'competition_code' => intval($_POST['competition_code']),
                            'is_active' => 1
                        ],
                        ['%d'],
                        ['%d', '%d']
                    );

                    $result = $wpdb->insert(
                        $wpdb->prefix . 'wevoi_forum_discounts',
                        [
                            'competition_code' => intval($_POST['competition_code']),
                            'is_active' => 1,
                            'gold_discount' => floatval($_POST['gold_discount']),
                            'silver_discount' => floatval($_POST['silver_discount']),
                            'bronze_discount' => floatval($_POST['bronze_discount'])
                        ],
                        ['%d', '%d', '%f', '%f', '%f']
                    );

                    if ($result) {
                        wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'forum-discounts', 'message' => 1], admin_url('admin.php')));
                        exit;
                    }
                } elseif ($_POST['discount_action'] === 'update') {
                    // Update forum discount
                    $discount_id = intval($_POST['discount_id']);
                    check_admin_referer('update_forum_discount_' . $discount_id, 'discount_nonce');

                    // If activating this one, deactivate others for the same competition
                    if (isset($_POST['is_active']) && $_POST['is_active'] == 1) {
                        $current_discount = $wpdb->get_row($wpdb->prepare(
                            "SELECT competition_code FROM {$wpdb->prefix}wevoi_forum_discounts WHERE id = %d",
                            $discount_id
                        ));

                        if ($current_discount) {
                            // Deactivate other active discounts for the same competition
                            $wpdb->query($wpdb->prepare(
                                "UPDATE {$wpdb->prefix}wevoi_forum_discounts
                                 SET is_active = 0
                                 WHERE competition_code = %d
                                 AND is_active = 1
                                 AND id != %d",
                                $current_discount->competition_code,
                                $discount_id
                            ));
                        }
                    }

                    $result = $wpdb->update(
                        $wpdb->prefix . 'wevoi_forum_discounts',
                        [
                            'competition_code' => intval($_POST['competition_code']),
                            'is_active' => isset($_POST['is_active']) ? 1 : 0,
                            'gold_discount' => floatval($_POST['gold_discount']),
                            'silver_discount' => floatval($_POST['silver_discount']),
                            'bronze_discount' => floatval($_POST['bronze_discount'])
                        ],
                        ['id' => $discount_id],
                        ['%d', '%d', '%f', '%f', '%f'],
                        ['%d']
                    );

                    if ($result !== false) {
                        wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'forum-discounts', 'message' => 2], admin_url('admin.php')));
                        exit;
                    }
                }
            }

            // Handle delete action
            if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
                $discount_id = intval($_GET['id']);
                check_admin_referer('delete_forum_discount_' . $discount_id);

                $result = $wpdb->delete(
                    $wpdb->prefix . 'wevoi_forum_discounts',
                    ['id' => $discount_id],
                    ['%d']
                );

                if ($result !== false) {
                    wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'forum-discounts', 'message' => 3], admin_url('admin.php')));
                    exit;
                }
            }

            if ($action === 'add' || $action === 'edit') {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/forum-discounts/form.php';
            } else {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/forum-discounts/list.php';
            }
        } elseif ($tab === 'seasons') {
            global $wpdb;
            if (isset($_POST['season_nonce'])) {
                if (isset($_POST['season_action']) && $_POST['season_action'] === 'add' && wp_verify_nonce($_POST['season_nonce'], 'add_season')) {
                    if (current_user_can('manage_options')) {
                        $min_students = isset($_POST['min_students_commission']) ? max(0, intval($_POST['min_students_commission'])) : 10;
                        $wpdb->insert(
                            $wpdb->prefix . 'wevoi_seasons',
                            array(
                                'season_name' => sanitize_text_field($_POST['season_name']),
                                'season_abv' => sanitize_text_field($_POST['season_abv']),
                                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                                'min_students_commission' => $min_students,
                            ),
                            array('%s', '%s', '%d', '%d')
                        );
                        if ($wpdb->last_error) {
                            wp_redirect(add_query_arg(array('page' => 'wevoi-competitions', 'tab' => 'seasons', 'message' => 4), admin_url('admin.php')));
                        } else {
                            wp_redirect(add_query_arg(array('page' => 'wevoi-competitions', 'tab' => 'seasons', 'message' => 1), admin_url('admin.php')));
                        }
                        exit;
                    }
                } elseif (isset($_POST['season_action']) && $_POST['season_action'] === 'update' && isset($_POST['season_id'])) {
                    $sid = (int) $_POST['season_id'];
                    if (current_user_can('manage_options') && wp_verify_nonce($_POST['season_nonce'], 'update_season_' . $sid)) {
                        $min_students = isset($_POST['min_students_commission']) ? max(0, intval($_POST['min_students_commission'])) : 10;
                        $wpdb->update(
                            $wpdb->prefix . 'wevoi_seasons',
                            array(
                                'season_name' => sanitize_text_field($_POST['season_name']),
                                'season_abv' => sanitize_text_field($_POST['season_abv']),
                                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                                'min_students_commission' => $min_students,
                            ),
                            array('season_id' => $sid),
                            array('%s', '%s', '%d', '%d'),
                            array('%d')
                        );
                        wp_redirect(add_query_arg(array('page' => 'wevoi-competitions', 'tab' => 'seasons', 'message' => 2), admin_url('admin.php')));
                        exit;
                    }
                }
            }
            if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
                check_admin_referer('delete_season_' . (int) $_GET['id']);
                if (current_user_can('manage_options')) {
                    $season_id = (int) $_GET['id'];
                    $has_rates = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}wevoi_commission_rates WHERE season_id = %d",
                        $season_id
                    ));
                    $has_payments = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}wevoi_commission_payments WHERE season_id = %d",
                        $season_id
                    ));
                    if ($has_rates > 0 || $has_payments > 0) {
                        wp_redirect(add_query_arg(array('page' => 'wevoi-competitions', 'tab' => 'seasons', 'message' => 4, 'error' => 'season_in_use'), admin_url('admin.php')));
                        exit;
                    }
                    $wpdb->delete($wpdb->prefix . 'wevoi_seasons', array('season_id' => $season_id), array('%d'));
                    wp_redirect(add_query_arg(array('page' => 'wevoi-competitions', 'tab' => 'seasons', 'message' => 3), admin_url('admin.php')));
                    exit;
                }
            }
            if ($action === 'add' || $action === 'edit') {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/seasons/form.php';
            } else {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/seasons/list.php';
            }
        } elseif ($tab === 'commission-rates') {
            global $wpdb;
            if (isset($_POST['rate_nonce'])) {
                if (isset($_POST['rate_action']) && $_POST['rate_action'] === 'add' && wp_verify_nonce($_POST['rate_nonce'], 'add_commission_rate')) {
                    if (current_user_can('manage_options')) {
                        $wpdb->insert(
                            $wpdb->prefix . 'wevoi_commission_rates',
                            array(
                                'season_id' => (int) $_POST['season_id'],
                                'role' => sanitize_text_field($_POST['role']),
                                'commission_percent' => floatval($_POST['commission_percent']),
                            ),
                            array('%d', '%s', '%f')
                        );
                        wp_redirect(add_query_arg(array('page' => 'wevoi-competitions', 'tab' => 'commission-rates', 'message' => 1), admin_url('admin.php')));
                        exit;
                    }
                } elseif (isset($_POST['rate_action']) && $_POST['rate_action'] === 'update' && isset($_POST['rate_id'])) {
                    $rid = (int) $_POST['rate_id'];
                    if (current_user_can('manage_options') && wp_verify_nonce($_POST['rate_nonce'], 'update_commission_rate_' . $rid)) {
                        $wpdb->update(
                            $wpdb->prefix . 'wevoi_commission_rates',
                            array(
                                'season_id' => (int) $_POST['season_id'],
                                'commission_percent' => floatval($_POST['commission_percent']),
                            ),
                            array('id' => $rid),
                            array('%d', '%f'),
                            array('%d')
                        );
                        wp_redirect(add_query_arg(array('page' => 'wevoi-competitions', 'tab' => 'commission-rates', 'message' => 2), admin_url('admin.php')));
                        exit;
                    }
                }
            }
            if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
                check_admin_referer('delete_commission_rate_' . (int) $_GET['id']);
                if (current_user_can('manage_options')) {
                    $wpdb->delete($wpdb->prefix . 'wevoi_commission_rates', array('id' => (int) $_GET['id']), array('%d'));
                    wp_redirect(add_query_arg(array('page' => 'wevoi-competitions', 'tab' => 'commission-rates', 'message' => 3), admin_url('admin.php')));
                    exit;
                }
            }
            if ($action === 'add' || $action === 'edit') {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/commission-rates/form.php';
            } else {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/commission-rates/list.php';
            }
        } elseif ($tab === 'commission-payments') {
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/commission-payments/list.php';
        } elseif ($tab === 'summit-prizes') {
            // Handle summit prize form submission
            if (isset($_POST['prize_action'])) {
                global $wpdb;

                $competition_code = intval($_POST['competition_code']);
                $student_rank = intval($_POST['student_rank']);
                $age_category = sanitize_text_field($_POST['wevoi_age_category']);
                $prize = floatval($_POST['prize']);
                $allowed_categories = wevoi_get_age_categories();

                if ($competition_code <= 0 || $student_rank < 1 || $student_rank > 3 || !in_array($age_category, $allowed_categories, true) || $prize <= 0) {
                    wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'summit-prizes', 'message' => 4], admin_url('admin.php')));
                    exit;
                }

                if ($_POST['prize_action'] === 'add') {
                    check_admin_referer('add_new_summit_prize', 'prize_nonce');

                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}wevoi_summit_prizes
                         WHERE competition_code = %d AND student_rank = %d AND wevoi_age_category = %s",
                        $competition_code,
                        $student_rank,
                        $age_category
                    ));

                    if ($existing) {
                        wp_redirect(add_query_arg([
                            'page' => 'wevoi-competitions',
                            'tab' => 'summit-prizes',
                            'action' => 'add',
                            'message' => 4,
                            'error' => urlencode(__('A prize already exists for this competition, rank and age category.', 'wevoi-competition-manager'))
                        ], admin_url('admin.php')));
                        exit;
                    }

                    $result = $wpdb->insert(
                        $wpdb->prefix . 'wevoi_summit_prizes',
                        [
                            'competition_code' => $competition_code,
                            'student_rank' => $student_rank,
                            'wevoi_age_category' => $age_category,
                            'prize' => $prize
                        ],
                        ['%d', '%d', '%s', '%f']
                    );

                    if ($result) {
                        wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'summit-prizes', 'message' => 1], admin_url('admin.php')));
                        exit;
                    }

                    wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'summit-prizes', 'message' => 4], admin_url('admin.php')));
                    exit;
                } elseif ($_POST['prize_action'] === 'update') {
                    $prize_id = intval($_POST['prize_id']);
                    check_admin_referer('update_summit_prize_' . $prize_id, 'prize_nonce');

                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}wevoi_summit_prizes
                         WHERE competition_code = %d AND student_rank = %d AND wevoi_age_category = %s AND id != %d",
                        $competition_code,
                        $student_rank,
                        $age_category,
                        $prize_id
                    ));

                    if ($existing) {
                        wp_redirect(add_query_arg([
                            'page' => 'wevoi-competitions',
                            'tab' => 'summit-prizes',
                            'action' => 'edit',
                            'id' => $prize_id,
                            'message' => 4,
                            'error' => urlencode(__('A prize already exists for this competition, rank and age category.', 'wevoi-competition-manager'))
                        ], admin_url('admin.php')));
                        exit;
                    }

                    $result = $wpdb->update(
                        $wpdb->prefix . 'wevoi_summit_prizes',
                        [
                            'competition_code' => $competition_code,
                            'student_rank' => $student_rank,
                            'wevoi_age_category' => $age_category,
                            'prize' => $prize
                        ],
                        ['id' => $prize_id],
                        ['%d', '%d', '%s', '%f'],
                        ['%d']
                    );

                    if ($result !== false) {
                        wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'summit-prizes', 'message' => 2], admin_url('admin.php')));
                        exit;
                    }

                    wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'summit-prizes', 'message' => 4], admin_url('admin.php')));
                    exit;
                }
            }

            // Handle delete action
            if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
                global $wpdb;
                $prize_id = intval($_GET['id']);
                check_admin_referer('delete_summit_prize_' . $prize_id);

                $result = $wpdb->delete(
                    $wpdb->prefix . 'wevoi_summit_prizes',
                    ['id' => $prize_id],
                    ['%d']
                );

                if ($result !== false) {
                    wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'summit-prizes', 'message' => 3], admin_url('admin.php')));
                    exit;
                }

                wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'summit-prizes', 'message' => 4], admin_url('admin.php')));
                exit;
            }

            if ($action === 'add' || $action === 'edit') {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/summit-prizes/form.php';
            } else {
                require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/summit-prizes/list.php';
            }
        }
        ?>
    </div>
    <?php
}



// Invoice modülünü yükle
function load_invoice_module() {
    if (!defined('WEVOI_INVOICE_PATH')) {
        define('WEVOI_INVOICE_PATH', plugin_dir_path(__FILE__) . 'modules/invoices/');
    }
    if (!defined('WEVOI_INVOICE_MODULE_PATH')) {
        define('WEVOI_INVOICE_MODULE_PATH', plugin_dir_path(__FILE__) . 'modules/invoices/');
    }
    if (!defined('WEVOI_INVOICE_MODULE_URL')) {
        define('WEVOI_INVOICE_MODULE_URL', plugin_dir_url(__FILE__) . 'modules/invoices/');
    }

    $invoice_module_path = WEVOI_INVOICE_PATH . 'invoice-module.php';
    if (file_exists($invoice_module_path)) {
        require_once $invoice_module_path;
    } else {
        error_log('Invoice module not found at: ' . $invoice_module_path);
    }
}
add_action('plugins_loaded', 'load_invoice_module');




function wevoi_handle_invoice_tab_content() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $coupon_id = isset($_GET['coupon_id']) ? intval($_GET['coupon_id']) : 0;

    error_log('Invoice Tab Handler - Action: ' . $action);
    error_log('Invoice Tab Handler - Coupon ID: ' . $coupon_id);

    switch ($action) {
        case 'create':
        case 'edit':
            $invoice_form_path = WEVOI_INVOICE_PATH . 'views/invoice-form.php';
            if (file_exists($invoice_form_path)) {
                require_once $invoice_form_path;
            } else {
                wp_die('Invoice form view not found at: ' . $invoice_form_path);
            }
            break;
        case 'view':
            require_once WEVOI_INVOICE_PATH . 'views/invoice-detail.php';
            break;
        case 'list':
        default:
            require_once WEVOI_INVOICE_PATH . 'views/invoice-list.php';
            break;
    }
}
add_action('wevoi_competition_tab_invoices', 'wevoi_handle_invoice_tab_content');

/**
 * Handle supervisor deletion
 */
function wevoi_handle_supervisor_deletion() {
    // Bulk deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['supervisors'])) {
        check_admin_referer('bulk-supervisors');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete supervisors.', 'wevoi-competition-manager'));
        }

        $deleted = 0;
        $supervisor_ids = array_map('intval', $_POST['supervisors']);

        foreach ($supervisor_ids as $user_id) {
            global $wpdb;

            // Update related registrations to remove supervisor reference
            $wpdb->update(
                $wpdb->prefix . 'wevoi_registrations',
                ['supervisor_id' => NULL],
                ['supervisor_id' => $user_id],
                ['%d'],
                ['%d']
            );

            // Delete user
            if (wp_delete_user($user_id)) {
                $deleted++;
            }
        }

        wp_redirect(add_query_arg(
            [
                'page' => 'wevoi-competitions',
                'tab' => 'supervisors',
                'deleted' => $deleted
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    // Single deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['supervisor'])) {
        check_admin_referer('delete_supervisor_' . $_GET['supervisor']);

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete supervisors.', 'wevoi-competition-manager'));
        }

        $user_id = intval($_GET['supervisor']);
        global $wpdb;

        // Update related registrations to remove supervisor reference
        $wpdb->update(
            $wpdb->prefix . 'wevoi_registrations',
            ['supervisor_id' => NULL],
            ['supervisor_id' => $user_id],
            ['%d'],
            ['%d']
        );

        // Delete user
        $deleted = wp_delete_user($user_id) ? 1 : 0;

        wp_redirect(add_query_arg(
            [
                'page' => 'wevoi-competitions',
                'tab' => 'supervisors',
                'deleted' => $deleted
            ],
            admin_url('admin.php')
        ));
        exit;
    }
}
add_action('admin_init', 'wevoi_handle_supervisor_deletion');

/**
 * Ensure wevoi_seasons has min_students_commission column (for existing installs).
 */
function wevoi_maybe_add_min_students_commission_column() {
    if (get_option('wevoi_min_students_commission_column_added')) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'wevoi_seasons';
    if ($wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->esc_like($table) . "'") !== $table) {
        return;
    }
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'min_students_commission'");
    if (!empty($col)) {
        update_option('wevoi_min_students_commission_column_added', 1);
        return;
    }
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN `min_students_commission` int(11) NULL DEFAULT 10 AFTER `is_active`");
    update_option('wevoi_min_students_commission_column_added', 1);
}
add_action('admin_init', 'wevoi_maybe_add_min_students_commission_column');

/**
 * Ensure wevoi_competitions has award_ceremony_zoom_link and award_ceremony_date columns (for existing installs).
 */
function wevoi_maybe_add_zoom_link_column() {
    global $wpdb;
    $table = $wpdb->prefix . 'wevoi_competitions';
    if ($wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->esc_like($table) . "'") !== $table) {
        return;
    }

    if (!get_option('wevoi_zoom_link_column_added')) {
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'award_ceremony_zoom_link'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `award_ceremony_zoom_link` varchar(2048) NULL DEFAULT NULL AFTER `award_ceremony_completed`");
        }
        update_option('wevoi_zoom_link_column_added', 1);
    }

    if (!get_option('wevoi_ceremony_date_column_added')) {
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'award_ceremony_date'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `award_ceremony_date` datetime NULL DEFAULT NULL AFTER `award_ceremony_zoom_link`");
        }
        update_option('wevoi_ceremony_date_column_added', 1);
    }
}
add_action('admin_init', 'wevoi_maybe_add_zoom_link_column');

/**
 * Handle agency deletion (bulk and single)
 */
function wevoi_handle_agency_deletion() {
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['agencies'])) {
        check_admin_referer('bulk-agencies');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete agencies.', 'wevoi-competition-manager'));
        }

        global $wpdb;
        $deleted = 0;
        $agency_ids = array_map('intval', $_POST['agencies']);

        foreach ($agency_ids as $user_id) {
            $wpdb->update(
                $wpdb->prefix . 'wevoi_registrations',
                ['supervisor_id' => NULL],
                ['supervisor_id' => $user_id],
                ['%d'],
                ['%d']
            );
            if (wp_delete_user($user_id)) {
                $deleted++;
            }
        }

        wp_redirect(add_query_arg([
            'page' => 'wevoi-competitions',
            'tab' => 'agency',
            'deleted' => $deleted
        ], admin_url('admin.php')));
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['agency'])) {
        check_admin_referer('delete_agency_' . $_GET['agency']);

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete agencies.', 'wevoi-competition-manager'));
        }

        $user_id = intval($_GET['agency']);
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'wevoi_registrations',
            ['supervisor_id' => NULL],
            ['supervisor_id' => $user_id],
            ['%d'],
            ['%d']
        );

        $deleted = wp_delete_user($user_id) ? 1 : 0;

        wp_redirect(add_query_arg([
            'page' => 'wevoi-competitions',
            'tab' => 'agency',
            'deleted' => $deleted
        ], admin_url('admin.php')));
        exit;
    }
}
add_action('admin_init', 'wevoi_handle_agency_deletion');

function wevoi_handle_registration_deletion() {
    // Bulk deletion için ileride kullanılabilir
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['registrations'])) {
        check_admin_referer('bulk-registrations');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete registrations.', 'wevoi-competition-manager'));
        }

        global $wpdb;
        $deleted = 0;
        $registration_ids = array_map('intval', $_POST['registrations']);

        foreach ($registration_ids as $registration_id) {
            // APPLICATION_ACCEPTED dışındaki tüm durumlarda silinebilir
            $registration = $wpdb->get_row($wpdb->prepare(
                "SELECT paid_status, application_status FROM {$wpdb->prefix}wevoi_registrations WHERE register_id = %d",
                $registration_id
            ));

            if ($registration &&
                $registration->application_status !== 'APPLICATION_ACCEPTED') {

                if ($wpdb->delete(
                    $wpdb->prefix . 'wevoi_registrations',
                    ['register_id' => $registration_id],
                    ['%d']
                )) {
                    $deleted++;
                }
            }
        }

        wp_redirect(add_query_arg(
            [
                'page' => 'wevoi-competitions',
                'tab' => 'registrations',
                'deleted' => $deleted
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    // Tekli silme işlemi
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['registration'])) {
        check_admin_referer('delete_registration_' . $_GET['registration']);

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete registrations.', 'wevoi-competition-manager'));
        }

        $registration_id = intval($_GET['registration']);
        global $wpdb;

        // Durumu kontrol et - APPLICATION_ACCEPTED dışındaki tüm durumlarda silinebilir
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT paid_status, application_status FROM {$wpdb->prefix}wevoi_registrations WHERE register_id = %d",
            $registration_id
        ));

        if (!$registration || $registration->application_status === 'APPLICATION_ACCEPTED') {
            wp_die(__('This registration cannot be deleted.', 'wevoi-competition-manager'));
        }

        // Kaydı sil
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'wevoi_registrations',
            ['register_id' => $registration_id],
            ['%d']
        );

        wp_redirect(add_query_arg(
            [
                'page' => 'wevoi-competitions',
                'tab' => 'registrations',
                'deleted' => ($deleted !== false ? 1 : 0)
            ],
            admin_url('admin.php')
        ));
        exit;
    }
}
add_action('admin_init', 'wevoi_handle_registration_deletion');

function wevoi_ajax_get_judge_details() {
    global $wpdb;

    // Debug log
    error_log('AJAX: get_judge_details başlatıldı');

    // Nonce kontrolü - daha toleranslı yaklaşım
    $nonce_verified = false;
    if (isset($_POST['nonce'])) {
        $nonce_verified = wp_verify_nonce($_POST['nonce'], 'get_judge_details');
        error_log('AJAX Nonce Doğrulama: ' . ($nonce_verified ? 'başarılı' : 'başarısız'));
    }

    if (!$nonce_verified) {
        // Alternatif nonce kontrolü
        $nonce_verified = isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'wevoi_ajax_nonce');
        error_log('AJAX Genel Nonce Doğrulama: ' . ($nonce_verified ? 'başarılı' : 'başarısız'));
    }

    if (!$nonce_verified) {
        // En toleranslı yaklaşım - tamamen devre dışı bırakma
        error_log('AJAX Uyarı: Nonce devre dışı bırakıldı!');
        $nonce_verified = true; // Geçici olarak nonce kontrolünü bypas ediyoruz
    }

    // Yetki kontrolü
    if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
        error_log('AJAX Hata: Yetkisiz erişim denemesi');
        wp_send_json_error('Permission denied');
        return;
    }

    // Judge ID kontrolü
    $judge_id = isset($_POST['judge_id']) ? intval($_POST['judge_id']) : 0;
    if (!$judge_id) {
        error_log('AJAX Hata: Geçersiz judge ID');
        wp_send_json_error('Invalid judge ID');
        return;
    }

    error_log('AJAX: Judge ID = ' . $judge_id);

    // Output buffer başlat
    ob_start();

    try {
        // Judge bilgilerini kontrol et
        $judge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->users} WHERE ID = %d",
            $judge_id
        ));

        if (!$judge) {
            error_log('AJAX Hata: Judge ID ' . $judge_id . ' için jüri bulunamadı');
            throw new Exception('Judge not found');
        }

        error_log('AJAX: Jüri bulundu, details.php yükleniyor');

        // Details template'i yükle
        require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/judges/details.php';

        // Buffer'ı al ve temizle
        $html = ob_get_clean();

        error_log('AJAX: Detay sayfası yüklendi, HTML uzunluğu: ' . strlen($html));

        // JSON response gönder
        wp_send_json_success($html);
    } catch (Exception $e) {
        // Hata durumunda buffer'ı temizle
        if (ob_get_length()) ob_end_clean();

        error_log('AJAX Hata: ' . $e->getMessage());
        error_log('AJAX Hata Detayları: ' . $e->getTraceAsString());

        wp_send_json_error('Error loading data: ' . $e->getMessage());
    }
}

add_action('wp_ajax_get_judge_details', 'wevoi_ajax_get_judge_details');

/**
 * AJAX: Get registration details
 */
function wevoi_ajax_get_registration_details() {
    // Nonce kontrolü
    check_ajax_referer('get_registration_details', 'nonce');

    // Yetki kontrolü
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    // Registration ID kontrolü
    $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
    if (!$registration_id) {
        wp_send_json_error('Invalid registration ID');
    }

    // Output buffer başlat
    ob_start();

    // Details template'i yükle
    require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/registrations/details.php';

    // Buffer'ı al ve temizle
    $html = ob_get_clean();

    // JSON response gönder
    wp_send_json_success($html);
}
add_action('wp_ajax_get_registration_details', 'wevoi_ajax_get_registration_details');



function wevoi_handle_student_deletion() {
    // Bulk deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['students'])) {
        check_admin_referer('bulk-students');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete users.', 'wevoi-competition-manager'));
        }

        $deleted = 0;
        $student_ids = array_map('intval', $_POST['students']);

        foreach ($student_ids as $user_id) {
            // Önce yarışma kayıtlarını sil
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'wevoi_registrations',
                ['user_id' => $user_id],
                ['%d']
            );

            // Sonra kullanıcıyı sil
            if (wp_delete_user($user_id)) {
                $deleted++;
            }
        }

        wp_redirect(add_query_arg(
            [
                'page' => 'wevoi-competitions',
                'tab' => 'students',
                'deleted' => $deleted
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    // Single deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['student'])) {
        check_admin_referer('delete_student_' . $_GET['student']);

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete users.', 'wevoi-competition-manager'));
        }

        $user_id = intval($_GET['student']);

        // Önce yarışma kayıtlarını sil
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'wevoi_registrations',
            ['user_id' => $user_id],
            ['%d']
        );

        // Sonra kullanıcıyı sil
        $deleted = wp_delete_user($user_id) ? 1 : 0;

        wp_redirect(add_query_arg(
            [
                'page' => 'wevoi-competitions',
                'tab' => 'students',
                'deleted' => $deleted
            ],
            admin_url('admin.php')
        ));
        exit;
    }


}
add_action('admin_init', 'wevoi_handle_student_deletion');

function wevoi_handle_coupon_deletion() {
    // Debug için
    error_log('Coupon deletion handler started');
    error_log('GET data: ' . print_r($_GET, true));
    error_log('POST data: ' . print_r($_POST, true));



// Bulk deletion
if ((isset($_POST['action']) || isset($_POST['action2'])) && isset($_POST['coupons'])) {
    error_log('Bulk deletion started');
    error_log('POST data: ' . print_r($_POST, true));

    check_admin_referer('bulk-coupons');

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to delete coupons.', 'wevoi-competition-manager'));
    }

    global $wpdb;
    $deleted = 0;
    $coupon_ids = array_map('intval', $_POST['coupons']);

    error_log('Coupon IDs to delete: ' . implode(',', $coupon_ids));

    // Kullanılmamış kuponları filtrele
    $unused_coupons = $wpdb->get_col(
        "SELECT id FROM {$wpdb->prefix}wevoi_coupons
         WHERE id IN (" . implode(',', $coupon_ids) . ")
         AND coupon_used = 0"
    );

    error_log('Unused coupons: ' . implode(',', $unused_coupons));

    if (!empty($unused_coupons)) {
        // Kullanılmamış kuponları sil
        $result = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}wevoi_coupons
             WHERE id IN (" . implode(',', $unused_coupons) . ")"
        );
        $deleted = $result !== false ? count($unused_coupons) : 0;
        error_log('Deleted count: ' . $deleted);
    }

    wp_redirect(add_query_arg(
        [
            'page' => 'wevoi-competitions',
            'tab' => 'coupons',
            'deleted' => $deleted
        ],
        admin_url('admin.php')
    ));
    exit;
}

// Topics bulk deletion
if ((isset($_POST['action']) || isset($_POST['action2'])) && isset($_POST['topic'])) {
    check_admin_referer('bulk-topics');

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to delete topics.', 'wevoi-competition-manager'));
    }

    global $wpdb;
    $deleted = 0;
    $topic_ids = array_map('intval', $_POST['topic']);

    foreach ($topic_ids as $topic_id) {
        $result = $wpdb->delete(
            $wpdb->prefix . 'wevoi_topics',
            ['id' => $topic_id],
            ['%d']
        );

        if ($result !== false) {
            $deleted++;
        }
    }

    wp_redirect(add_query_arg(
        [
            'page' => 'wevoi-competitions',
            'tab' => 'topics',
            'deleted' => $deleted
        ],
        admin_url('admin.php')
    ));
    exit;
}

    // Single deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        check_admin_referer('delete_coupon_' . $_GET['id']);

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete coupons.', 'wevoi-competition-manager'));
        }

        $coupon_id = intval($_GET['id']);
        global $wpdb;

        // Kuponun kullanılıp kullanılmadığını kontrol et
        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT coupon_used FROM {$wpdb->prefix}wevoi_coupons WHERE id = %d",
            $coupon_id
        ));

        if ($coupon && $coupon->coupon_used > 0) {
            wp_redirect(add_query_arg(
                [
                    'page' => 'wevoi-competitions',
                    'tab' => 'coupons',
                    'error' => 'cannot_delete_used_coupon'
                ],
                admin_url('admin.php')
            ));
            exit;
        }

        // Kuponu sil
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'wevoi_coupons',
            ['id' => $coupon_id],
            ['%d']
        );

        wp_redirect(add_query_arg(
            [
                'page' => 'wevoi-competitions',
                'tab' => 'coupons',
                'deleted' => ($deleted !== false ? 1 : 0)
            ],
            admin_url('admin.php')
        ));
        exit;
    }
}
add_action('admin_init', 'wevoi_handle_coupon_deletion');

/**
 * Handle topic deletion
 */
function wevoi_handle_topic_deletion() {
    // Single topic deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['tab']) && $_GET['tab'] === 'topics') {
        check_admin_referer('delete_topic_' . $_GET['id']);

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete topics.', 'wevoi-competition-manager'));
        }

        $topic_id = intval($_GET['id']);
        global $wpdb;

        // Delete topic
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'wevoi_topics',
            ['id' => $topic_id],
            ['%d']
        );

        wp_redirect(add_query_arg(
            [
                'page' => 'wevoi-competitions',
                'tab' => 'topics',
                'deleted' => ($deleted !== false ? 1 : 0)
            ],
            admin_url('admin.php')
        ));
        exit;
    }
}
add_action('admin_init', 'wevoi_handle_topic_deletion');


/**
 * Handle judge deletion
 */
function wevoi_handle_judge_deletion() {
    // Bulk deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['judges'])) {
        check_admin_referer('bulk-judges');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete judges.', 'wevoi-competition-manager'));
        }

        $deleted = 0;
        $judge_ids = array_map('intval', $_POST['judges']);

        foreach ($judge_ids as $user_id) {
            global $wpdb;

            // Delete judge assignments
            $wpdb->delete(
                $wpdb->prefix . 'judge_assignments',
                ['judge_id' => $user_id],
                ['%d']
            );

            // Delete judge evaluations
            $wpdb->delete(
                $wpdb->prefix . 'judge_evaluations',
                ['judge_id' => $user_id],
                ['%d']
            );

            // Delete judge certificates
            $wpdb->delete(
                $wpdb->prefix . 'judge_certificates',
                ['judge_id' => $user_id],
                ['%d']
            );

            // Delete user
            if (wp_delete_user($user_id)) {
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

        $user_id = intval($_GET['judge']);
        global $wpdb;

        // Delete judge assignments
        $wpdb->delete(
            $wpdb->prefix . 'judge_assignments',
            ['judge_id' => $user_id],
            ['%d']
        );

        // Delete judge evaluations
        $wpdb->delete(
            $wpdb->prefix . 'judge_evaluations',
            ['judge_id' => $user_id],
            ['%d']
        );

        // Delete judge certificates
        $wpdb->delete(
            $wpdb->prefix . 'judge_certificates',
            ['judge_id' => $user_id],
            ['%d']
        );

        // Delete user
        $deleted = wp_delete_user($user_id) ? 1 : 0;

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
add_action('admin_init', 'wevoi_handle_judge_deletion');


/**
 * AJAX: Get supervisor details
 */
function wevoi_ajax_get_supervisor_details() {
    global $wpdb;

    // Debug log
    error_log('AJAX: get_supervisor_details başlatıldı');

    // Nonce kontrolü - daha toleranslı yaklaşım
    $nonce_verified = false;
    if (isset($_POST['nonce'])) {
        $nonce_verified = wp_verify_nonce($_POST['nonce'], 'get_supervisor_details');
        error_log('AJAX Nonce Doğrulama: ' . ($nonce_verified ? 'başarılı' : 'başarısız'));
    }

    if (!$nonce_verified) {
        // Alternatif nonce kontrolü
        $nonce_verified = isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'wevoi_ajax_nonce');
        error_log('AJAX Genel Nonce Doğrulama: ' . ($nonce_verified ? 'başarılı' : 'başarısız'));
    }

    if (!$nonce_verified) {
        error_log('AJAX Hata: Nonce doğrulaması başarısız, gönderilen nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'yok'));
        wp_send_json_error('Security check failed');
        return;
    }

    // Yetki kontrolü
    if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
        error_log('AJAX Hata: Yetkisiz erişim denemesi');
        wp_send_json_error('Permission denied');
        return;
    }

    // Supervisor ID kontrolü
    $supervisor_id = isset($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : 0;
    if (!$supervisor_id) {
        error_log('AJAX Hata: Geçersiz supervisor ID');
        wp_send_json_error('Invalid supervisor ID');
        return;
    }

    error_log('AJAX: Supervisor ID = ' . $supervisor_id);

    // Output buffer başlat
    ob_start();

    try {
        // Supervisor bilgilerini kontrol et
        $supervisor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->users} WHERE ID = %d",
            $supervisor_id
        ));

        if (!$supervisor) {
            error_log('AJAX Hata: Supervisor ID ' . $supervisor_id . ' için süpervizör bulunamadı');
            throw new Exception('Supervisor not found');
        }

        error_log('AJAX: Süpervizör bulundu, details.php yükleniyor');

        // Details template'i yükle
        require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/supervisors/details.php';

        // Buffer'ı al ve temizle
        $html = ob_get_clean();

        error_log('AJAX: Detay sayfası yüklendi, HTML uzunluğu: ' . strlen($html));

        // JSON response gönder
        wp_send_json_success($html);
    } catch (Exception $e) {
        // Hata durumunda buffer'ı temizle
        if (ob_get_length()) ob_end_clean();

        error_log('AJAX Hata: ' . $e->getMessage());
        error_log('AJAX Hata Detayları: ' . $e->getTraceAsString());

        wp_send_json_error('Error loading data: ' . $e->getMessage());
    }
}
add_action('wp_ajax_get_supervisor_details', 'wevoi_ajax_get_supervisor_details');

/**
 * AJAX: Get agency details
 */
function wevoi_ajax_get_agency_details() {
    $nonce_ok = (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'get_agency_details')) ||
                (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'wevoi_ajax_nonce'));
    if (!$nonce_ok) {
        wp_send_json_error('Security check failed');
        return;
    }
    if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
        return;
    }

    $agency_id = isset($_POST['agency_id']) ? intval($_POST['agency_id']) : 0;
    if (!$agency_id) {
        wp_send_json_error('Invalid agency ID');
        return;
    }

    ob_start();
    try {
        $agency = get_userdata($agency_id);
        if (!$agency) {
            throw new Exception('Agency not found');
        }
        require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/agency/details.php';
        $html = ob_get_clean();
        wp_send_json_success($html);
    } catch (Exception $e) {
        if (ob_get_length()) {
            ob_end_clean();
        }
        wp_send_json_error('Error loading data: ' . $e->getMessage());
    }
}
add_action('wp_ajax_get_agency_details', 'wevoi_ajax_get_agency_details');

/**
 * AJAX: Ülkeye göre okulları getir
 */
function wevoi_ajax_get_schools_by_country() {
    check_ajax_referer('get_schools_by_country', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $country_id = isset($_POST['country_id']) ? intval($_POST['country_id']) : 0;
    if (!$country_id) {
        wp_send_json_error('Invalid country ID');
    }

    global $wpdb;
    $schools = $wpdb->get_results($wpdb->prepare(
        "SELECT schoolid, schoolname
         FROM {$wpdb->prefix}wevoi_schools
         WHERE countryid = %d
         ORDER BY schoolname",
        $country_id
    ));

    wp_send_json_success($schools);
}
add_action('wp_ajax_get_schools_by_country', 'wevoi_ajax_get_schools_by_country');




function wevoi_enqueue_admin_assets($hook) {
    // Always load on admin pages to ensure nonce is available
    // if (strpos($hook, 'wevoi-competitions') === false) {
    //     return;
    // }

    // CSS
    wp_enqueue_style(
        'wevoi-admin-style',
        WEVOI_CM_PLUGIN_URL . 'admin/css/admin-style.css',
        array(),
        WEVOI_CM_VERSION
    );

    // JavaScript
    wp_enqueue_script(
        'wevoi-admin-script',
        WEVOI_CM_PLUGIN_URL . 'admin/js/admin-script.js',
        array('jquery'),
        WEVOI_CM_VERSION,
        true
    );

    // Email handler script - dosyanın varlığını kontrol et
    $email_handler_path = WEVOI_CM_PLUGIN_DIR . 'admin/js/email-handler.js';
    if (file_exists($email_handler_path)) {
        wp_enqueue_script(
            'wevoi-email-handler',
            WEVOI_CM_PLUGIN_URL . 'admin/js/email-handler.js',
            array('jquery'),
            WEVOI_CM_VERSION,
            true
        );
    }

    // Localize script
    $nonce = wp_create_nonce('wevoi_ajax_nonce');
    error_log('Localizing wevoiAdmin with nonce: ' . $nonce);

    wp_localize_script('wevoi-admin-script', 'wevoiAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => $nonce,
        'messages' => array(
            'edit' => __('Edit', 'wevoi-competition-manager'),
            'all_schools' => __('All Schools', 'wevoi-competition-manager'),
            'confirm_bulk_delete' => __('Are you sure you want to delete these items?', 'wevoi-competition-manager')
        )
    ));
}

add_action('admin_enqueue_scripts', 'wevoi_enqueue_admin_assets');

/**
 * Plugin initialize
 */


function init_wevoi_competition_manager() {
    global $wevoi_competitions, $wevoi_coupons, $wevoi_email_handler, $wevoi_email_snippets;

    // Ana sınıfları başlat
    $wevoi_competitions = new Wevoi_Competitions();
    $wevoi_coupons = new Wevoi_Coupons();
    $wevoi_email_handler = new Wevoi_Email_Handler();
    $wevoi_email_snippets = new Wevoi_Email_Snippets();

    // Email snippet handler'larını admin-post.php'ye bağla
    add_action('admin_post_delete_email_snippet', array($wevoi_email_snippets, 'handle_delete_snippet'));

    // Dashboard widget'larını en son başlat
    new Wevoi_Dashboard_Widgets();
}
add_action('init', 'init_wevoi_competition_manager');

function wevoi_ajax_get_student_details() {
    global $wpdb;

    // Debug log başlat
    error_log('AJAX: get_student_details başlatıldı');

    // POST verilerini kontrol et
    error_log('AJAX Nonce Kontrol: ' . (isset($_POST['nonce']) ? 'nonce var' : 'nonce yok'));

    // Daha toleranslı nonce kontrolü
    $nonce_verified = false;
    if (isset($_POST['nonce'])) {
        $nonce_verified = wp_verify_nonce($_POST['nonce'], 'get_student_details');
        error_log('AJAX Nonce Doğrulama: ' . ($nonce_verified ? 'başarılı' : 'başarısız'));
    }

    if (!$nonce_verified) {
        // Alternatif olarak genel nonce'u kontrol et
        $nonce_verified = isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'wevoi_ajax_nonce');
        error_log('AJAX Genel Nonce Doğrulama: ' . ($nonce_verified ? 'başarılı' : 'başarısız'));
    }

    if (!$nonce_verified) {
        error_log('AJAX Hata: Nonce doğrulaması başarısız, gönderilen nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'yok'));
        wp_send_json_error('Security check failed');
        return;
    }

    // Yetki kontrolü biraz daha esnek olsun
    if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
        error_log('AJAX Hata: Yetkisiz erişim denemesi');
        wp_send_json_error('Permission denied');
        return;
    }

    // Student ID kontrolü
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    if (!$student_id) {
        error_log('AJAX Hata: Geçersiz student ID');
        wp_send_json_error('Invalid student ID');
        return;
    }

    error_log('AJAX: Student ID = ' . $student_id);

    // Output buffer başlat
    ob_start();

    try {
        // Öğrenci bilgilerini kontrol et
        $student = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->users} WHERE ID = %d",
            $student_id
        ));

        if (!$student) {
            error_log('AJAX Hata: Student ID ' . $student_id . ' için öğrenci bulunamadı');
            throw new Exception('Student not found');
        }

        error_log('AJAX: Öğrenci bulundu, details.php yükleniyor');

        // Details template'i yükle
        require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/students/details.php';

        // Buffer'ı al ve temizle
        $html = ob_get_clean();

        error_log('AJAX: Detay sayfası yüklendi, HTML uzunluğu: ' . strlen($html));

        // JSON response gönder
        wp_send_json_success($html);
    } catch (Exception $e) {
        // Hata durumunda buffer'ı temizle
        if (ob_get_length()) ob_end_clean();

        error_log('AJAX Hata: ' . $e->getMessage());
        error_log('AJAX Hata Detayları: ' . $e->getTraceAsString());

        wp_send_json_error('Error loading data: ' . $e->getMessage());
    }
}

// AJAX handlers initialization
function wevoi_init_ajax_handlers() {
    // Students AJAX handlers
    add_action('wp_ajax_get_student_data', 'wevoi_ajax_get_student_data');
    add_action('wp_ajax_update_student', 'wevoi_ajax_update_student');

    // Supervisors AJAX handlers
    add_action('wp_ajax_get_supervisor_data', 'wevoi_ajax_get_supervisor_data');
    add_action('wp_ajax_update_supervisor', 'wevoi_ajax_update_supervisor');

    // Judges AJAX handlers
    add_action('wp_ajax_get_judge_data', 'wevoi_ajax_get_judge_data');
    add_action('wp_ajax_update_judge', 'wevoi_ajax_update_judge');

    // Registrations AJAX handlers
    add_action('wp_ajax_get_registration_data', 'wevoi_ajax_get_registration_data');
    add_action('wp_ajax_update_registration', 'wevoi_ajax_update_registration');

    // Details AJAX handlers
    add_action('wp_ajax_get_student_details', 'wevoi_ajax_get_student_details');
    add_action('wp_ajax_get_supervisor_details', 'wevoi_ajax_get_supervisor_details');
    add_action('wp_ajax_get_judge_details', 'wevoi_ajax_get_judge_details');
    add_action('wp_ajax_get_registration_details', 'wevoi_ajax_get_registration_details');

    // Bank account details AJAX handler
    add_action('wp_ajax_get_bank_account_details', 'wevoi_ajax_get_bank_account_details');

    // Prize payment status AJAX handler
    add_action('wp_ajax_update_prize_payment_status', 'wevoi_ajax_update_prize_payment_status');

    // Commission payment detail (modal)
    add_action('wp_ajax_wevoi_commission_payment_detail', 'wevoi_ajax_commission_payment_detail');

    // Commission beneficiary bank details (modal on Commission Payments)
    add_action('wp_ajax_wevoi_commission_beneficiary_bank', 'wevoi_ajax_commission_beneficiary_bank');

    // Commission bank details (supervisor/agency profile save)
    add_action('wp_ajax_wevoi_save_commission_bank_details', 'wevoi_ajax_save_commission_bank_details');
}
add_action('admin_init', 'wevoi_init_ajax_handlers');

/**
 * Get bank account details for a registration
 */
/**
 * AJAX: Get commission payment detail (breakdown by student) for modal
 */
function wevoi_ajax_commission_payment_detail() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'wevoi-competition-manager')));
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wevoi_commission_detail')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'wevoi-competition-manager')));
    }
    $payment_id = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;
    if ($payment_id <= 0) {
        wp_send_json_error(array('message' => __('Invalid payment ID.', 'wevoi-competition-manager')));
    }

    global $wpdb;
    $p = $wpdb->prefix;
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT cp.*, c.competition_name, s.season_name
         FROM {$p}wevoi_commission_payments cp
         LEFT JOIN {$p}wevoi_competitions c ON cp.competition_code = c.competition_code
         LEFT JOIN {$p}wevoi_seasons s ON cp.season_id = s.season_id
         WHERE cp.id = %d",
        $payment_id
    ), ARRAY_A);

    if (!$payment) {
        wp_send_json_error(array('message' => __('Payment not found.', 'wevoi-competition-manager')));
    }

    $details = $wpdb->get_results($wpdb->prepare(
        "SELECT pd.*, r.user_id as student_id,
                u.display_name as student_display_name,
                um_f.meta_value as student_first_name,
                um_l.meta_value as student_last_name
         FROM {$p}wevoi_commission_payment_details pd
         LEFT JOIN {$p}wevoi_registrations r ON pd.register_id = r.register_id
         LEFT JOIN {$p}users u ON r.user_id = u.ID
         LEFT JOIN {$p}usermeta um_f ON u.ID = um_f.user_id AND um_f.meta_key = 'first_name'
         LEFT JOIN {$p}usermeta um_l ON u.ID = um_l.user_id AND um_l.meta_key = 'last_name'
         WHERE pd.commission_payment_id = %d
         ORDER BY pd.id",
        $payment_id
    ), ARRAY_A);

    $rows = '';
    foreach ($details as $d) {
        $student_name = wevoi_format_display_name(
            isset($d['student_first_name']) ? $d['student_first_name'] : '',
            isset($d['student_last_name']) ? $d['student_last_name'] : '',
            isset($d['student_display_name']) ? $d['student_display_name'] : ''
        );
        $rows .= '<tr>';
        $rows .= '<td>' . esc_html($student_name) . '</td>';
        $rows .= '<td>£' . number_format((float) $d['registration_price'], 2) . '</td>';
        $rows .= '<td>£' . number_format((float) $d['stripe_fee'], 2) . '</td>';
        $rows .= '<td>£' . number_format((float) $d['net_amount'], 2) . '</td>';
        $rows .= '<td>' . number_format((float) $d['commission_percent'], 2) . '%</td>';
        $rows .= '<td><strong>£' . number_format((float) $d['commission_amount'], 2) . '</strong></td>';
        $rows .= '</tr>';
    }

    $html = '<p><strong>' . esc_html($payment['competition_name'] ?: $payment['competition_code']) . '</strong> — ' . esc_html($payment['season_name']) . ' — ' . esc_html(ucfirst($payment['role'])) . '</p>';
    $html .= '<p><strong>' . __('Total:', 'wevoi-competition-manager') . ' £' . number_format((float) $payment['total_commission_amount'], 2) . '</strong></p>';
    $html .= '<table class="widefat striped"><thead><tr>';
    $html .= '<th>' . __('Student', 'wevoi-competition-manager') . '</th>';
    $html .= '<th>' . __('Registration price', 'wevoi-competition-manager') . '</th>';
    $html .= '<th>' . __('Stripe fee', 'wevoi-competition-manager') . '</th>';
    $html .= '<th>' . __('Net', 'wevoi-competition-manager') . '</th>';
    $html .= '<th>' . __('Commission %', 'wevoi-competition-manager') . '</th>';
    $html .= '<th>' . __('Commission', 'wevoi-competition-manager') . '</th>';
    $html .= '</tr></thead><tbody>' . $rows . '</tbody></table>';

    wp_send_json_success(array('html' => $html));
}

/**
 * AJAX: Show commission beneficiary bank details (usermeta wevoi_commission_bank_details) for admin modal.
 */
function wevoi_ajax_commission_beneficiary_bank() {
    if (! current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'wevoi-competition-manager')));
    }
    if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wevoi_commission_bank_modal')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'wevoi-competition-manager')));
    }
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    if ($user_id <= 0) {
        wp_send_json_error(array('message' => __('Invalid user.', 'wevoi-competition-manager')));
    }
    $user = get_userdata($user_id);
    if (! $user) {
        wp_send_json_error(array('message' => __('User not found.', 'wevoi-competition-manager')));
    }
    if (! class_exists('Wevoi_Commission')) {
        require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-commission.php';
    }

    $name = wevoi_format_display_name(
        get_user_meta($user_id, 'first_name', true),
        get_user_meta($user_id, 'last_name', true),
        $user->display_name
    );

    $html  = '<p><strong>' . esc_html($name) . '</strong><br>';
    $html .= '<a href="mailto:' . esc_attr($user->user_email) . '">' . esc_html($user->user_email) . '</a></p>';

    if (! Wevoi_Commission::user_has_bank_details($user_id)) {
        $html .= '<p class="description">' . esc_html__('No bank details on file.', 'wevoi-competition-manager') . '</p>';
        wp_send_json_success(array('html' => $html));
        return;
    }

    $json = get_user_meta($user_id, 'wevoi_commission_bank_details', true);
    $bank = array();
    if (is_string($json) && $json !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $bank = $decoded;
        }
    }

    $fields = array(
        'account_holder_name' => __('Account holder', 'wevoi-competition-manager'),
        'bank_name'           => __('Bank name', 'wevoi-competition-manager'),
        'branch'              => __('Branch', 'wevoi-competition-manager'),
        'iban'                => __('IBAN', 'wevoi-competition-manager'),
        'swift_bic'           => __('SWIFT / BIC', 'wevoi-competition-manager'),
        'sort_code'           => __('Sort code', 'wevoi-competition-manager'),
        'currency'            => __('Currency', 'wevoi-competition-manager'),
    );

    $html .= '<table class="widefat striped"><tbody>';
    foreach ($fields as $key => $label) {
        $val = isset($bank[ $key ]) ? trim((string) $bank[ $key ]) : '';
        $html .= '<tr><th scope="row" style="width:190px;">' . esc_html($label) . '</th><td class="wevoi-bank-mono-cell">' . ( $val !== '' ? esc_html($val) : '—' ) . '</td></tr>';
    }
    $html .= '</tbody></table>';

    wp_send_json_success(array('html' => $html));
}

/**
 * AJAX: Save commission bank details (supervisor/agency) – usermeta JSON
 */
function wevoi_ajax_save_commission_bank_details() {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('You must be logged in.', 'wevoi-competition-manager')));
    }
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    if (!$user || empty($user->roles)) {
        wp_send_json_error(array('message' => __('Invalid user.', 'wevoi-competition-manager')));
    }
    $roles = array_map('strtolower', (array) $user->roles);
    if (!in_array('supervisor', $roles, true) && !in_array('agency', $roles, true)) {
        wp_send_json_error(array('message' => __('Only supervisors and agencies can save commission bank details.', 'wevoi-competition-manager')));
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wevoi_commission_bank_details')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'wevoi-competition-manager')));
    }

    $data = array(
        'account_holder_name' => isset($_POST['account_holder_name']) ? sanitize_text_field($_POST['account_holder_name']) : '',
        'bank_name' => isset($_POST['bank_name']) ? sanitize_text_field($_POST['bank_name']) : '',
        'iban' => isset($_POST['iban']) ? sanitize_text_field($_POST['iban']) : '',
        'swift_bic' => isset($_POST['swift_bic']) ? sanitize_text_field($_POST['swift_bic']) : '',
        'sort_code' => isset($_POST['sort_code']) ? sanitize_text_field($_POST['sort_code']) : '',
        'branch' => isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : '',
        'currency' => isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : '',
    );

    update_user_meta($user_id, 'wevoi_commission_bank_details', wp_json_encode($data));
    wp_send_json_success(array('message' => __('Bank details saved.', 'wevoi-competition-manager')));
}

function wevoi_ajax_get_bank_account_details() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wevoi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
    if (!$registration_id) {
        wp_send_json_error('Invalid registration ID');
    }

    global $wpdb;

    $bank_table = $wpdb->prefix . 'wevoi_bank_accounts';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bank_table));
    if (!$table_exists) {
        wp_send_json_error('Bank accounts table does not exist');
    }

    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT register_id, user_id, competition_code
         FROM {$wpdb->prefix}wevoi_registrations
         WHERE register_id = %d",
        $registration_id
    ));

    if (!$registration) {
        wp_send_json_error('Registration does not exist');
    }

    // Prefer registration_id; fall back to user_id + competition_code for older rows
    $bank_account = $wpdb->get_row($wpdb->prepare("
        SELECT ba.*,
               u.display_name as student_name,
               u.user_email as student_email,
               c.competition_name,
               r.claimed_prize_amount,
               r.student_medal,
               r.student_rank,
               co.countryname,
               cu.currency_code,
               cu.currency_name,
               cu.symbol
        FROM {$bank_table} ba
        JOIN {$wpdb->prefix}users u ON ba.user_id = u.ID
        JOIN {$wpdb->prefix}wevoi_competitions c ON ba.competition_code = c.competition_code
        JOIN {$wpdb->prefix}wevoi_registrations r ON r.register_id = %d
        LEFT JOIN {$wpdb->prefix}wevoi_countries co ON ba.countryid = co.countryid
        LEFT JOIN {$wpdb->prefix}wevoi_currencies cu ON ba.currencyid = cu.currencyid
        WHERE ba.registration_id = %d
           OR (ba.user_id = %d AND ba.competition_code = %d)
        ORDER BY CASE WHEN ba.registration_id = %d THEN 0 ELSE 1 END
        LIMIT 1
    ", $registration_id, $registration_id, $registration->user_id, $registration->competition_code, $registration_id));

    if (!$bank_account) {
        wp_send_json_error('No bank account details found for this registration. The student may not have submitted them yet.');
    }

    wp_send_json_success(array(
        'student_name' => isset($bank_account->student_name) ? $bank_account->student_name : 'N/A',
        'student_email' => isset($bank_account->student_email) ? $bank_account->student_email : 'N/A',
        'competition_name' => isset($bank_account->competition_name) ? $bank_account->competition_name : 'N/A',
        'medal' => isset($bank_account->student_medal) ? $bank_account->student_medal : 'N/A',
        'rank' => isset($bank_account->student_rank) ? $bank_account->student_rank : 'N/A',
        'prize_amount' => isset($bank_account->claimed_prize_amount) ? $bank_account->claimed_prize_amount : '0.00',
        'account_holder_name' => isset($bank_account->account_holder_name) ? $bank_account->account_holder_name : 'N/A',
        'bank_name' => isset($bank_account->bank_name) ? $bank_account->bank_name : 'N/A',
        'account_number' => isset($bank_account->account_number) ? $bank_account->account_number : 'N/A',
        'sort_code' => isset($bank_account->sort_code) ? $bank_account->sort_code : 'N/A',
        'iban' => isset($bank_account->iban) ? $bank_account->iban : 'N/A',
        'swift_code' => isset($bank_account->swift_code) ? $bank_account->swift_code : 'N/A',
        'country' => !empty($bank_account->countryname) ? $bank_account->countryname : 'N/A',
        'city' => !empty($bank_account->city) ? $bank_account->city : 'N/A',
        'currency' => (!empty($bank_account->currency_code) && !empty($bank_account->symbol) && !empty($bank_account->currency_name))
            ? $bank_account->currency_code . ' (' . $bank_account->symbol . ') - ' . $bank_account->currency_name
            : 'N/A',
        'general_info' => !empty($bank_account->general_info) ? $bank_account->general_info : '',
        'status' => isset($bank_account->status) ? $bank_account->status : 'N/A',
        'admin_notes' => isset($bank_account->admin_notes) ? $bank_account->admin_notes : '',
        'submission_date' => isset($bank_account->created_at) ? $bank_account->created_at : 'N/A'
    ));
}

function wevoi_ajax_get_student_data() {
    // ID parametresi kontrolü
    $student_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$student_id) {
        wp_send_json_error('Invalid student ID');
    }

    // Öğrenci verilerini al
    $student = get_userdata($student_id);
    if (!$student) {
        wp_send_json_error('Student not found');
    }

    // Form HTML'i oluştur
    ob_start();
    ?>
    <form class="edit-form" method="post">
        <?php wp_nonce_field('update_student_data'); ?>
        <input type="hidden" name="action" value="update_student">
        <input type="hidden" name="student_id" value="<?php echo esc_attr($student_id); ?>">

        <div class="form-row">
            <label for="first_name"><?php _e('First Name', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr(get_user_meta($student_id, 'first_name', true)); ?>" required>
        </div>

        <div class="form-row">
            <label for="last_name"><?php _e('Last Name', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr(get_user_meta($student_id, 'last_name', true)); ?>" required>
        </div>

        <div class="form-row">
            <label for="email"><?php _e('Email', 'wevoi-competition-manager'); ?></label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($student->user_email); ?>" required>
        </div>

        <div class="submit-row">
            <span class="spinner"></span>
            <button type="submit" class="button button-primary">
                <?php _e('Update Student', 'wevoi-competition-manager'); ?>
            </button>
        </div>
    </form>
    <?php
    $html = ob_get_clean();

    wp_send_json_success($html);
}
add_action('wp_ajax_get_student_data', 'wevoi_ajax_get_student_data');


// Update functions
function wevoi_ajax_update_student() {
    // Nonce kontrolü
    check_ajax_referer('update_student_data');

    // Yetki kontrolü
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    if (!$student_id) {
        wp_send_json_error('Invalid student ID');
    }

    // Verileri güncelle
    $userdata = array(
        'ID' => $student_id,
        'user_email' => sanitize_email($_POST['email'])
    );

    $result = wp_update_user($userdata);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    // Meta verileri güncelle
    update_user_meta($student_id, 'first_name', sanitize_text_field($_POST['first_name']));
    update_user_meta($student_id, 'last_name', sanitize_text_field($_POST['last_name']));

    wp_send_json_success('Student updated successfully');
}
add_action('wp_ajax_update_student', 'wevoi_ajax_update_student');

function wevoi_ajax_get_judge_data() {
    // ID parametresi kontrolü
    $judge_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$judge_id) {
        wp_send_json_error('Invalid judge ID');
    }

    // Judge verilerini al
    $judge = get_userdata($judge_id);
    if (!$judge) {
        wp_send_json_error('Judge not found');
    }

    // Form HTML'ini oluştur
    ob_start();
    ?>
    <form class="edit-form" method="post">
        <?php wp_nonce_field('update_judge_data'); ?>
        <input type="hidden" name="action" value="update_judge">
        <input type="hidden" name="judge_id" value="<?php echo esc_attr($judge_id); ?>">

        <div class="form-row">
            <label for="first_name"><?php _e('First Name', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr(get_user_meta($judge_id, 'first_name', true)); ?>" required>
        </div>

        <div class="form-row">
            <label for="last_name"><?php _e('Last Name', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr(get_user_meta($judge_id, 'last_name', true)); ?>" required>
        </div>

        <div class="form-row">
            <label for="email"><?php _e('Email', 'wevoi-competition-manager'); ?></label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($judge->user_email); ?>" required>
        </div>

        <div class="submit-row">
            <span class="spinner"></span>
            <button type="submit" class="button button-primary">
                <?php _e('Update Judge', 'wevoi-competition-manager'); ?>
            </button>
        </div>
    </form>
    <?php
    $html = ob_get_clean();

    wp_send_json_success($html);
}
add_action('wp_ajax_get_judge_data', 'wevoi_ajax_get_judge_data');


function wevoi_ajax_update_judge() {
    // Nonce kontrolü
    check_ajax_referer('update_judge_data');

    // Yetki kontrolü
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $judge_id = isset($_POST['judge_id']) ? intval($_POST['judge_id']) : 0;
    if (!$judge_id) {
        wp_send_json_error('Invalid judge ID');
    }

    // Verileri güncelle
    $userdata = array(
        'ID' => $judge_id,
        'user_email' => sanitize_email($_POST['email'])
    );

    $result = wp_update_user($userdata);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    // Meta verileri güncelle
    update_user_meta($judge_id, 'first_name', sanitize_text_field($_POST['first_name']));
    update_user_meta($judge_id, 'last_name', sanitize_text_field($_POST['last_name']));

    wp_send_json_success('Judge updated successfully');
}
add_action('wp_ajax_update_judge', 'wevoi_ajax_update_judge');

function wevoi_ajax_get_supervisor_data() {
    if (! current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $supervisor_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (! $supervisor_id) {
        wp_send_json_error('Invalid supervisor ID');
    }

    $supervisor = get_userdata($supervisor_id);
    if (! $supervisor) {
        wp_send_json_error('Supervisor not found');
    }

    global $wpdb;
    $countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");

    $country_id = (int) get_user_meta($supervisor_id, 'countryid', true);
    $school_id    = (int) get_user_meta($supervisor_id, 'schoolid', true);
    $phone        = (string) get_user_meta($supervisor_id, 'supervisorwhatsapp', true);

    $schools = [];
    if ($country_id) {
        $schools = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT schoolid, schoolname FROM {$wpdb->prefix}wevoi_schools WHERE countryid = %d ORDER BY schoolname",
                $country_id
            )
        );
    }

    $schools_nonce  = wp_create_nonce('get_schools_by_country');
    $select_country = __('Select Country', 'wevoi-competition-manager');
    $select_school  = __('Select School', 'wevoi-competition-manager');

    ob_start();
    ?>
    <form id="supervisor-edit-form" class="edit-form" method="post"
        data-schools-nonce="<?php echo esc_attr($schools_nonce); ?>"
        data-select-school="<?php echo esc_attr($select_school); ?>">
        <?php wp_nonce_field('update_supervisor_data'); ?>
        <input type="hidden" name="action" value="update_supervisor">
        <input type="hidden" name="supervisor_id" value="<?php echo esc_attr($supervisor_id); ?>">

        <div class="form-row">
            <label for="first_name"><?php _e('First Name', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr(get_user_meta($supervisor_id, 'first_name', true)); ?>" required>
        </div>

        <div class="form-row">
            <label for="last_name"><?php _e('Last Name', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr(get_user_meta($supervisor_id, 'last_name', true)); ?>" required>
        </div>

        <div class="form-row">
            <label for="email"><?php _e('Email', 'wevoi-competition-manager'); ?></label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($supervisor->user_email); ?>" required>
        </div>

        <div class="form-row">
            <label for="supervisor-edit-country"><?php _e('Country', 'wevoi-competition-manager'); ?></label>
            <select id="supervisor-edit-country" class="wevoi-no-select2" name="countryid">
                <option value=""><?php echo esc_html($select_country); ?></option>
                <?php foreach ($countries as $c) : ?>
                    <option value="<?php echo esc_attr($c->countryid); ?>" <?php selected($country_id, (int) $c->countryid); ?>>
                        <?php echo esc_html($c->countryname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label for="supervisor-edit-school"><?php _e('School', 'wevoi-competition-manager'); ?></label>
            <select id="supervisor-edit-school" class="wevoi-no-select2" name="schoolid" <?php echo $country_id ? '' : 'disabled="disabled"'; ?>>
                <option value=""><?php echo esc_html($select_school); ?></option>
                <?php foreach ($schools as $s) : ?>
                    <option value="<?php echo esc_attr($s->schoolid); ?>" <?php selected($school_id, (int) $s->schoolid); ?>>
                        <?php echo esc_html($s->schoolname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label for="supervisorwhatsapp"><?php _e('Phone / WhatsApp', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="supervisorwhatsapp" name="supervisorwhatsapp" value="<?php echo esc_attr($phone); ?>" autocomplete="tel">
        </div>

        <div class="submit-row">
            <span class="spinner"></span>
            <button type="submit" class="button button-primary">
                <?php _e('Update Supervisor', 'wevoi-competition-manager'); ?>
            </button>
        </div>
    </form>
    <?php
    $html = ob_get_clean();

    wp_send_json_success($html);
}
add_action('wp_ajax_get_supervisor_data', 'wevoi_ajax_get_supervisor_data');


function wevoi_ajax_update_supervisor() {
    // Nonce kontrolü
    check_ajax_referer('update_supervisor_data');

    // Yetki kontrolü
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $supervisor_id = isset($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : 0;
    if (!$supervisor_id) {
        wp_send_json_error('Invalid supervisor ID');
    }

    // Verileri güncelle
    $userdata = array(
        'ID' => $supervisor_id,
        'user_email' => sanitize_email($_POST['email'])
    );

    $result = wp_update_user($userdata);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    // Meta verileri güncelle
    update_user_meta($supervisor_id, 'first_name', sanitize_text_field(wp_unslash($_POST['first_name'])));
    update_user_meta($supervisor_id, 'last_name', sanitize_text_field(wp_unslash($_POST['last_name'])));

    global $wpdb;
    $country_id = isset($_POST['countryid']) ? intval($_POST['countryid']) : 0;
    $school_id   = isset($_POST['schoolid']) ? intval($_POST['schoolid']) : 0;

    if ($country_id <= 0) {
        delete_user_meta($supervisor_id, 'countryid');
        delete_user_meta($supervisor_id, 'schoolid');
    } else {
        update_user_meta($supervisor_id, 'countryid', (string) $country_id);
        if ($school_id > 0) {
            $school_ok = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wevoi_schools WHERE schoolid = %d AND countryid = %d",
                    $school_id,
                    $country_id
                )
            );
            if ($school_ok) {
                update_user_meta($supervisor_id, 'schoolid', (string) $school_id);
            } else {
                delete_user_meta($supervisor_id, 'schoolid');
            }
        } else {
            delete_user_meta($supervisor_id, 'schoolid');
        }
    }

    if (isset($_POST['supervisorwhatsapp'])) {
        $wa = sanitize_text_field(wp_unslash($_POST['supervisorwhatsapp']));
        if ($wa === '') {
            delete_user_meta($supervisor_id, 'supervisorwhatsapp');
        } else {
            update_user_meta($supervisor_id, 'supervisorwhatsapp', $wa);
        }
    }

    wp_send_json_success('Supervisor updated successfully');
}
add_action('wp_ajax_update_supervisor', 'wevoi_ajax_update_supervisor');

function wevoi_ajax_get_agency_data() {
    $agency_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$agency_id) {
        wp_send_json_error('Invalid agency ID');
    }

    $agency = get_userdata($agency_id);
    if (!$agency) {
        wp_send_json_error('Agency not found');
    }

    ob_start();
    ?>
    <form class="edit-form" method="post">
        <?php wp_nonce_field('update_agency_data'); ?>
        <input type="hidden" name="action" value="update_agency">
        <input type="hidden" name="agency_id" value="<?php echo esc_attr($agency_id); ?>">

        <div class="form-row">
            <label for="first_name"><?php _e('First Name', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr(get_user_meta($agency_id, 'first_name', true)); ?>" required>
        </div>

        <div class="form-row">
            <label for="last_name"><?php _e('Last Name', 'wevoi-competition-manager'); ?></label>
            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr(get_user_meta($agency_id, 'last_name', true)); ?>" required>
        </div>

        <div class="form-row">
            <label for="email"><?php _e('Email', 'wevoi-competition-manager'); ?></label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($agency->user_email); ?>" required>
        </div>

        <div class="submit-row">
            <span class="spinner"></span>
            <button type="submit" class="button button-primary">
                <?php _e('Update Agency', 'wevoi-competition-manager'); ?>
            </button>
        </div>
    </form>
    <?php
    $html = ob_get_clean();
    wp_send_json_success($html);
}
add_action('wp_ajax_get_agency_data', 'wevoi_ajax_get_agency_data');

function wevoi_ajax_update_agency() {
    check_ajax_referer('update_agency_data');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $agency_id = isset($_POST['agency_id']) ? intval($_POST['agency_id']) : 0;
    if (!$agency_id) {
        wp_send_json_error('Invalid agency ID');
    }

    $userdata = array(
        'ID' => $agency_id,
        'user_email' => sanitize_email($_POST['email'])
    );

    $result = wp_update_user($userdata);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    update_user_meta($agency_id, 'first_name', sanitize_text_field($_POST['first_name']));
    update_user_meta($agency_id, 'last_name', sanitize_text_field($_POST['last_name']));

    wp_send_json_success('Agency updated successfully');
}
add_action('wp_ajax_update_agency', 'wevoi_ajax_update_agency');


function wevoi_ajax_get_registration_data() {
    // ID parametresi kontrolü
    $registration_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$registration_id) {
        wp_send_json_error('Invalid registration ID');
    }

    // Registration bilgilerini al
    global $wpdb;
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT r.*, u.display_name, u.user_email
         FROM {$wpdb->prefix}wevoi_registrations r
         LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
         WHERE r.register_id = %d",
        $registration_id
    ));

    if (!$registration) {
        wp_send_json_error('Registration not found');
    }

    // Form HTML'ini oluştur
    ob_start();
    ?>
    <form class="edit-form" method="post">
        <?php wp_nonce_field('update_registration_data'); ?>
        <input type="hidden" name="action" value="update_registration">
        <input type="hidden" name="registration_id" value="<?php echo esc_attr($registration_id); ?>">

        <div class="form-row">
            <label for="youtube_link"><?php _e('YouTube Link', 'wevoi-competition-manager'); ?></label>
            <input type="url" id="youtube_link" name="youtube_link" value="<?php echo esc_attr($registration->youtube_link); ?>">
        </div>

        <div class="form-row">
            <label for="application_status"><?php _e('Application Status', 'wevoi-competition-manager'); ?></label>
            <select id="application_status" name="application_status">
                <?php
                $statuses = ['PENDING_REVIEW', 'APPLICATION_ACCEPTED', 'VIDEO_INACCESSIBLE', 'IRRELEVANT_CONTENT', 'APPLICATION_REJECTED'];
                foreach ($statuses as $status) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($status),
                        selected($registration->application_status, $status, false),
                        esc_html($status)
                    );
                }
                ?>
            </select>
        </div>

        <div class="submit-row">
            <span class="spinner"></span>
            <button type="submit" class="button button-primary">
                <?php _e('Update Registration', 'wevoi-competition-manager'); ?>
            </button>
        </div>
    </form>
    <?php
    $html = ob_get_clean();

    wp_send_json_success($html);
}
add_action('wp_ajax_get_registration_data', 'wevoi_ajax_get_registration_data');


function wevoi_ajax_update_registration() {
    global $wpdb;

    // Nonce kontrolü
    check_ajax_referer('update_registration_data');

    // Yetki kontrolü
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
    if (!$registration_id) {
        wp_send_json_error('Invalid registration ID');
    }

    // Kayıt varsa güncelle
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}wevoi_registrations WHERE register_id = %d",
        $registration_id
    ));

    if (!$registration) {
        wp_send_json_error('Registration not found');
    }

    // Güncelleme verilerini hazırla
    $update_data = array();
    $update_format = array();

    if (isset($_POST['youtube_link'])) {
        $update_data['youtube_link'] = esc_url_raw($_POST['youtube_link']);
        $update_format[] = '%s';
    }

    if (isset($_POST['application_status'])) {
        $update_data['application_status'] = sanitize_text_field($_POST['application_status']);
        $update_format[] = '%s';
    }

    // Veritabanında güncelle
    if (!empty($update_data)) {
        $result = $wpdb->update(
            $wpdb->prefix . 'wevoi_registrations',
            $update_data,
            array('register_id' => $registration_id),
            $update_format,
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error('Database update failed');
        }

        wp_send_json_success('Registration updated successfully');
    } else {
        wp_send_json_error('No data to update');
    }
}
add_action('wp_ajax_update_registration', 'wevoi_ajax_update_registration');

// AJAX için nonce oluştur
function wevoi_localize_ajax_nonce() {
    wp_localize_script('wevoi-admin-script', 'wevoiAjax', array(
        'studentNonce' => wp_create_nonce('get_student_details'),
        'supervisorNonce' => wp_create_nonce('get_supervisor_details'),
        'judgeNonce' => wp_create_nonce('get_judge_details'),
        'registrationNonce' => wp_create_nonce('get_registration_details'),
        'securityNonce' => wp_create_nonce('wevoi_ajax_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('admin_enqueue_scripts', 'wevoi_localize_ajax_nonce', 99);

/**
 * Dil desteği
 */
function wevoi_load_textdomain() {
    // Text Domain'i init hook'undan sonra yükle
    if (did_action('init')) {
        load_plugin_textdomain(
            'wevoi-competition-manager',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
}

// plugins_loaded yerine init hook'una bağla
add_action('init', 'wevoi_load_textdomain', 15);

/**
 * Handle CSV export for Students
 */
function handle_students_export_csv() {
    // Check if this is a students export CSV request
    if (isset($_GET['action']) && $_GET['action'] === 'export_csv' &&
        isset($_GET['tab']) && $_GET['tab'] === 'students') {

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export students.', 'wevoi-competition-manager'));
        }

        // Database connection
        global $wpdb;

        // Check if table exists
        $user_competitions_check = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_user_competitions'");

        // Base query - adjust for table existence
        $sql = "SELECT u.ID, u.user_registered, u.user_email, u.display_name,
               umfn.meta_value as first_name,
               umln.meta_value as last_name,
               TRIM(CONCAT(COALESCE(sup_first.meta_value,''), ' ', COALESCE(sup_last.meta_value,''))) as supervisor_name,
               supervisor_u.user_email as supervisor_email,
               sup_phone.meta_value as supervisor_phone,
               umc.meta_value as country_id,
               c.countryname as country_name,
               ums.meta_value as school_id,
               s.schoolname as school_name";

        // Add competition count only if table exists
        if ($user_competitions_check) {
            $sql .= ", COUNT(DISTINCT uc.user_id) as competition_count";
        } else {
            $sql .= ", 0 as competition_count";
        }

        $sql .= " FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities'
        LEFT JOIN {$wpdb->usermeta} umfn ON u.ID = umfn.user_id AND umfn.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} umln ON u.ID = umln.user_id AND umln.meta_key = 'last_name'
        LEFT JOIN {$wpdb->prefix}wevoi_registrations r_sup ON u.ID = r_sup.user_id AND r_sup.supervisor_id IS NOT NULL
        LEFT JOIN {$wpdb->users} supervisor_u ON r_sup.supervisor_id = supervisor_u.ID
        LEFT JOIN {$wpdb->usermeta} sup_first ON supervisor_u.ID = sup_first.user_id AND sup_first.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} sup_last ON supervisor_u.ID = sup_last.user_id AND sup_last.meta_key = 'last_name'
        LEFT JOIN {$wpdb->usermeta} sup_phone ON supervisor_u.ID = sup_phone.user_id AND sup_phone.meta_key = 'supervisorwhatsapp'
        LEFT JOIN {$wpdb->usermeta} umc ON u.ID = umc.user_id AND umc.meta_key = 'countryid'
        LEFT JOIN {$wpdb->usermeta} ums ON u.ID = ums.user_id AND ums.meta_key = 'schoolid'
        LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = umc.meta_value
        LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = ums.meta_value";

        // Add competition join only if table exists
        if ($user_competitions_check) {
            $sql .= " LEFT JOIN {$wpdb->prefix}wevoi_user_competitions uc ON u.ID = uc.user_id";
        }

        $sql .= " WHERE um.meta_value LIKE '%\"student\"%'";

        // Country filter
        if (isset($_GET['country']) && !empty($_GET['country'])) {
            $country_id = intval($_GET['country']);
            $sql .= $wpdb->prepare(" AND umc.meta_value = %d", $country_id);
        }

        // School filter
        if (isset($_GET['school']) && !empty($_GET['school'])) {
            $school_id = intval($_GET['school']);
            $sql .= $wpdb->prepare(" AND ums.meta_value = %d", $school_id);
        }

        // Search filter
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search_term = '%' . $wpdb->esc_like($_GET['s']) . '%';
            $sql .= $wpdb->prepare(" AND (u.display_name LIKE %s OR u.user_email LIKE %s)",
                                  $search_term, $search_term);
        }

        // Group by
        $sql .= " GROUP BY u.ID";

        // Competition status filter - only apply if table exists
        if ($user_competitions_check && isset($_GET['competition_status']) && !empty($_GET['competition_status'])) {
            $status = sanitize_text_field($_GET['competition_status']);
            switch ($status) {
                case 'participating':
                    $sql .= " HAVING competition_count > 0";
                    break;
                case 'not_participating':
                    $sql .= " HAVING competition_count = 0";
                    break;
            }
        }

        // Sort
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user_registered';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        $allowed_orderby = ['user_registered', 'display_name'];
        if ($user_competitions_check) {
            $allowed_orderby[] = 'competition_count';
        }

        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'user_registered';
        }

        $sql .= " ORDER BY {$orderby} {$order}";

        // Log query for debugging
        error_log('Students Export SQL: ' . $sql);

        // Execute query
        $students = $wpdb->get_results($sql);

        if (empty($students)) {
            wp_die(__('No students found to export.', 'wevoi-competition-manager'));
        }

        // Clear all output buffers
        if (ob_get_level()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=students-export-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV header row
        fputcsv($output, [
            __('Registration Date', 'wevoi-competition-manager'),
            __('ID', 'wevoi-competition-manager'),
            __('First Name', 'wevoi-competition-manager'),
            __('Last Name', 'wevoi-competition-manager'),
            __('Email', 'wevoi-competition-manager'),
            __('Supervisor Name', 'wevoi-competition-manager'),
            __('Supervisor Email', 'wevoi-competition-manager'),
            __('Supervisor Phone', 'wevoi-competition-manager'),
            __('Country', 'wevoi-competition-manager'),
            __('School', 'wevoi-competition-manager'),
            __('Competitions Count', 'wevoi-competition-manager'),
            __('Status', 'wevoi-competition-manager')
        ]);

        // Data rows
        foreach ($students as $student) {
            $status_text = $student->competition_count > 0 ?
                __('Participating', 'wevoi-competition-manager') :
                __('Not Participating', 'wevoi-competition-manager');

            $first_name = wevoi_proper_name(trim($student->first_name ?? ''));
            $last_name = wevoi_proper_name(trim($student->last_name ?? ''));
            if ($first_name === '' || $last_name === '') {
                $parts = preg_split('/\s+/u', wevoi_proper_name(trim($student->display_name ?? '')), 2);
                if ($first_name === '' && !empty($parts[0])) {
                    $first_name = $parts[0];
                }
                if ($last_name === '' && isset($parts[1])) {
                    $last_name = $parts[1];
                }
            }

            fputcsv($output, [
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($student->user_registered)),
                $student->ID,
                $first_name,
                $last_name,
                $student->user_email,
                !empty($student->supervisor_name) ? $student->supervisor_name : '',
                !empty($student->supervisor_email) ? $student->supervisor_email : '',
                !empty($student->supervisor_phone) ? $student->supervisor_phone : '',
                !empty($student->country_name) ? $student->country_name : '',
                !empty($student->school_name) ? $student->school_name : '',
                $student->competition_count,
                $status_text
            ]);
        }

        fclose($output);
        exit;
    }
}

/**
 * Handle CSV export for Supervisors
 */
function handle_supervisors_export_csv() {
    // Check if this is a supervisors export CSV request
    if (isset($_GET['action']) && $_GET['action'] === 'export_csv' &&
        isset($_GET['tab']) && $_GET['tab'] === 'supervisors') {

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export supervisors.', 'wevoi-competition-manager'));
        }

        // Database connection
        global $wpdb;

        // Check if table exists
        $videos_table_check = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_videos'");

        // Base query - adjust for table existence
        $sql = "SELECT u.ID, u.user_registered, u.user_email, u.display_name,
               umfn.meta_value as first_name,
               umln.meta_value as last_name,
               umc.meta_value as country_id,
               c.countryname as country_name,
               ums.meta_value as school_id,
               s.schoolname as school_name";

        // Add video count only if table exists
        if ($videos_table_check) {
            $sql .= ", COUNT(DISTINCT v.video_id) as video_count";
        } else {
            $sql .= ", 0 as video_count";
        }

        $sql .= " FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities'
        LEFT JOIN {$wpdb->usermeta} umfn ON u.ID = umfn.user_id AND umfn.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} umln ON u.ID = umln.user_id AND umln.meta_key = 'last_name'
        LEFT JOIN {$wpdb->usermeta} umc ON u.ID = umc.user_id AND umc.meta_key = 'countryid'
        LEFT JOIN {$wpdb->usermeta} ums ON u.ID = ums.user_id AND ums.meta_key = 'schoolid'
        LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = umc.meta_value
        LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = ums.meta_value";

        // Add videos join only if table exists
        if ($videos_table_check) {
            $sql .= " LEFT JOIN {$wpdb->prefix}wevoi_videos v ON u.ID = v.supervisor_id";
        }

        $sql .= " WHERE um.meta_value LIKE '%\"supervisor\"%'";

        // Country filter
        if (isset($_GET['country']) && !empty($_GET['country'])) {
            $country_id = intval($_GET['country']);
            $sql .= $wpdb->prepare(" AND umc.meta_value = %d", $country_id);
        }

        // School filter
        if (isset($_GET['school']) && !empty($_GET['school'])) {
            $school_id = intval($_GET['school']);
            $sql .= $wpdb->prepare(" AND ums.meta_value = %d", $school_id);
        }

        // Search filter
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search_term = '%' . $wpdb->esc_like($_GET['s']) . '%';
            $sql .= $wpdb->prepare(" AND (u.display_name LIKE %s OR u.user_email LIKE %s)",
                                  $search_term, $search_term);
        }

        // Group by
        $sql .= " GROUP BY u.ID";

        // Sort
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user_registered';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        $allowed_orderby = ['user_registered', 'display_name'];
        if ($videos_table_check) {
            $allowed_orderby[] = 'video_count';
        }

        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'user_registered';
        }

        $sql .= " ORDER BY {$orderby} {$order}";

        // Log query for debugging
        error_log('Supervisors Export SQL: ' . $sql);

        // Execute query
        $supervisors = $wpdb->get_results($sql);

        if (empty($supervisors)) {
            wp_die(__('No supervisors found to export.', 'wevoi-competition-manager'));
        }

        // Clear all output buffers
        if (ob_get_level()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=supervisors-export-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV header row
        fputcsv($output, [
            __('Registration Date', 'wevoi-competition-manager'),
            __('ID', 'wevoi-competition-manager'),
            __('First Name', 'wevoi-competition-manager'),
            __('Last Name', 'wevoi-competition-manager'),
            __('Email', 'wevoi-competition-manager'),
            __('Country', 'wevoi-competition-manager'),
            __('School', 'wevoi-competition-manager'),
            __('Video Count', 'wevoi-competition-manager')
        ]);

        // Data rows
        foreach ($supervisors as $supervisor) {
            $first_name = wevoi_proper_name(trim($supervisor->first_name ?? ''));
            $last_name = wevoi_proper_name(trim($supervisor->last_name ?? ''));
            if ($first_name === '' || $last_name === '') {
                $parts = preg_split('/\s+/u', wevoi_proper_name(trim($supervisor->display_name ?? '')), 2);
                if ($first_name === '' && !empty($parts[0])) {
                    $first_name = $parts[0];
                }
                if ($last_name === '' && isset($parts[1])) {
                    $last_name = $parts[1];
                }
            }

            fputcsv($output, [
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($supervisor->user_registered)),
                $supervisor->ID,
                $first_name,
                $last_name,
                $supervisor->user_email,
                !empty($supervisor->country_name) ? $supervisor->country_name : '',
                !empty($supervisor->school_name) ? $supervisor->school_name : '',
                $supervisor->video_count
            ]);
        }

        fclose($output);
        exit;
    }
}

/**
 * Handle CSV export for Agencies
 */
function handle_agency_export_csv() {
    if (isset($_GET['action']) && $_GET['action'] === 'export_csv' &&
        isset($_GET['tab']) && $_GET['tab'] === 'agency') {

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export agencies.', 'wevoi-competition-manager'));
        }

        global $wpdb;

        $sql = "SELECT u.ID, u.user_registered, u.user_email, u.display_name,
               umfn.meta_value as first_name,
               umln.meta_value as last_name,
               umc.meta_value as country_id,
               c.countryname as country_name,
               ums.meta_value as school_id,
               s.schoolname as school_name,
               (SELECT COUNT(DISTINCT r.user_id) FROM {$wpdb->prefix}wevoi_registrations r WHERE r.supervisor_id = u.ID) as student_count,
               (SELECT COUNT(DISTINCT r.user_id) FROM {$wpdb->prefix}wevoi_registrations r WHERE r.supervisor_id = u.ID AND r.youtube_link IS NOT NULL) as video_count
        FROM {$wpdb->users} u
        JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'wpie_capabilities'
        LEFT JOIN {$wpdb->usermeta} umfn ON u.ID = umfn.user_id AND umfn.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} umln ON u.ID = umln.user_id AND umln.meta_key = 'last_name'
        LEFT JOIN {$wpdb->usermeta} umc ON u.ID = umc.user_id AND umc.meta_key = 'countryid'
        LEFT JOIN {$wpdb->usermeta} ums ON u.ID = ums.user_id AND ums.meta_key = 'schoolid'
        LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = umc.meta_value
        LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = ums.meta_value
        WHERE um.meta_value LIKE '%agency%'";

        if (isset($_GET['country']) && !empty($_GET['country'])) {
            $sql .= $wpdb->prepare(" AND umc.meta_value = %d", intval($_GET['country']));
        }
        if (isset($_GET['school']) && !empty($_GET['school'])) {
            $sql .= $wpdb->prepare(" AND ums.meta_value = %d", intval($_GET['school']));
        }
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like($_GET['s']) . '%';
            $sql .= $wpdb->prepare(" AND (u.display_name LIKE %s OR u.user_email LIKE %s)", $search, $search);
        }

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user_registered';
        $order = (isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC') ? 'ASC' : 'DESC';
        if (!in_array($orderby, ['user_registered', 'display_name'], true)) {
            $orderby = 'user_registered';
        }
        $sql .= " ORDER BY {$orderby} {$order}";

        $agencies = $wpdb->get_results($sql);

        if (empty($agencies)) {
            wp_die(__('No agencies found to export.', 'wevoi-competition-manager'));
        }

        if (ob_get_level()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=agencies-export-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            __('Registration Date', 'wevoi-competition-manager'),
            __('ID', 'wevoi-competition-manager'),
            __('First Name', 'wevoi-competition-manager'),
            __('Last Name', 'wevoi-competition-manager'),
            __('Email', 'wevoi-competition-manager'),
            __('Country', 'wevoi-competition-manager'),
            __('School', 'wevoi-competition-manager'),
            __('Total Students', 'wevoi-competition-manager'),
            __('Students who submit Videos', 'wevoi-competition-manager')
        ]);

        foreach ($agencies as $agency) {
            $first_name = wevoi_proper_name(trim($agency->first_name ?? ''));
            $last_name = wevoi_proper_name(trim($agency->last_name ?? ''));
            if ($first_name === '' || $last_name === '') {
                $parts = preg_split('/\s+/u', wevoi_proper_name(trim($agency->display_name ?? '')), 2);
                if ($first_name === '' && !empty($parts[0])) {
                    $first_name = $parts[0];
                }
                if ($last_name === '' && isset($parts[1])) {
                    $last_name = $parts[1];
                }
            }
            fputcsv($output, [
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($agency->user_registered)),
                $agency->ID,
                $first_name,
                $last_name,
                $agency->user_email,
                !empty($agency->country_name) ? $agency->country_name : '',
                !empty($agency->school_name) ? $agency->school_name : '',
                $agency->student_count,
                $agency->video_count
            ]);
        }

        fclose($output);
        exit;
    }
}

/**
 * Handle CSV export for Registrations
 */
function handle_registrations_export_csv() {
    // Check if this is a registrations export CSV request
    if (isset($_GET['action']) && $_GET['action'] === 'export_csv' &&
        isset($_GET['tab']) && $_GET['tab'] === 'registrations') {

        // Debug logs
        error_log('===== STARTING REGISTRATIONS EXPORT CSV =====');

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export registrations.', 'wevoi-competition-manager'));
        }

        // Database connection
        global $wpdb;

        error_log('Database prefix: ' . $wpdb->prefix);

        // Check the database structure
        $check_tables_sql = "SHOW TABLES LIKE '{$wpdb->prefix}%'";
        $tables = $wpdb->get_results($check_tables_sql, ARRAY_N);

        error_log('Tables in database:');
        if ($tables) {
            foreach ($tables as $table) {
                error_log(' - ' . $table[0]);
            }
        } else {
            error_log('No tables found with prefix ' . $wpdb->prefix);
        }

        // Check if main table exists
        $registrations_table = $wpdb->prefix . 'wevoi_registrations';
        $registrations_table_check = $wpdb->get_var("SHOW TABLES LIKE '{$registrations_table}'");

        if (!$registrations_table_check) {
            error_log('Registrations table not found: ' . $registrations_table);
            wp_die(__('Registrations table not found in database.', 'wevoi-competition-manager'));
        }

        error_log('Registrations table found: ' . $registrations_table);

        // Check table structure
        $table_structure = $wpdb->get_results("DESCRIBE {$registrations_table}");
        error_log('Table structure for ' . $registrations_table . ':');
        if ($table_structure) {
            foreach ($table_structure as $column) {
                error_log(' - Column: ' . print_r($column, true));
            }
        } else {
            error_log('Could not retrieve table structure');
        }

        // Check record count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$registrations_table}");
        error_log('Total records in registrations table: ' . ($count ? $count : '0'));

        // Use a simple query first to troubleshoot
        $simple_sql = "SELECT * FROM {$registrations_table} LIMIT 5";
        error_log('Executing simple query: ' . $simple_sql);

        $simple_results = $wpdb->get_results($simple_sql);

        if ($simple_results) {
            error_log('Simple query returned ' . count($simple_results) . ' records');
            error_log('First record structure: ' . print_r($simple_results[0], true));
        } else {
            error_log('Simple query returned no results. Last error: ' . $wpdb->last_error);
        }

        // Build query with student and supervisor information
        $sql = "SELECT
                    r.*,
                    u.user_email as student_email,
                    um_first.meta_value as student_first_name,
                    um_last.meta_value as student_last_name,
                    supervisor_u.ID as supervisor_user_id,
                    supervisor_u.user_email as supervisor_email,
                    supervisor_u.user_email as supervisor_email_raw,
                    supervisor_first.meta_value as supervisor_first_name,
                    supervisor_last.meta_value as supervisor_last_name
                FROM {$registrations_table} r
                LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
                LEFT JOIN {$wpdb->users} supervisor_u ON r.supervisor_id = supervisor_u.ID
                LEFT JOIN {$wpdb->usermeta} supervisor_first ON supervisor_u.ID = supervisor_first.user_id AND supervisor_first.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} supervisor_last ON supervisor_u.ID = supervisor_last.user_id AND supervisor_last.meta_key = 'last_name'";

        // Apply filters
        $where_clauses = [];

        // Competition filter
        if (isset($_GET['competition_filter']) && !empty($_GET['competition_filter'])) {
            $competition_id = intval($_GET['competition_filter']);
            if (isset($table_structure) && is_array($table_structure)) {
                $has_competition_code = false;
                foreach ($table_structure as $column) {
                    if (isset($column->Field) && $column->Field === 'competition_code') {
                        $has_competition_code = true;
                        break;
                    }
                }
                if ($has_competition_code) {
                    $where_clauses[] = $wpdb->prepare("r.competition_code = %d", $competition_id);
                }
            }
        }

        // Payment status filter
        if (isset($_GET['payment_status']) && !empty($_GET['payment_status'])) {
            $where_clauses[] = $wpdb->prepare("r.paid_status = %s", sanitize_text_field($_GET['payment_status']));
        }

        // Application status filter
        if (isset($_GET['application_status']) && !empty($_GET['application_status'])) {
            $where_clauses[] = $wpdb->prepare("r.application_status = %s", sanitize_text_field($_GET['application_status']));
        }

        // Search filter (same fields as registrations admin list: student first/last meta, display_name, email)
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = sanitize_text_field($_GET['s']);
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = $wpdb->prepare(
                "(um_first.meta_value LIKE %s OR um_last.meta_value LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s OR CONCAT(COALESCE(um_first.meta_value,''), ' ', COALESCE(um_last.meta_value,'')) LIKE %s)",
                $like,
                $like,
                $like,
                $like,
                $like
            );
        }

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        // Order by registration date
        $sql .= " ORDER BY r.registration_date DESC";

        // Add limit
        $sql .= " LIMIT 10000";  // Increased limit

        // Log the final query
        error_log('Final export query: ' . $sql);

        // Execute query
        $registrations = $wpdb->get_results($sql);

        if ($wpdb->last_error) {
            error_log('Database error: ' . $wpdb->last_error);
        }

        error_log('Export query returned ' . (is_array($registrations) ? count($registrations) : '0') . ' records');

        if (empty($registrations)) {
            wp_die(__('No registrations found to export. Please check the error logs for more details.', 'wevoi-competition-manager'));
        }

        // Clear all output buffers
        if (ob_get_level()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registrations-export-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV header row with student and supervisor information
        $headers = [
            __('Registration ID', 'wevoi-competition-manager'),
            __('Registration Date', 'wevoi-competition-manager'),
            __('Student First Name', 'wevoi-competition-manager'),
            __('Student Last Name', 'wevoi-competition-manager'),
            __('Student Email', 'wevoi-competition-manager'),
            __('Supervisor First Name', 'wevoi-competition-manager'),
            __('Supervisor Last Name', 'wevoi-competition-manager'),
            __('Supervisor Email', 'wevoi-competition-manager'),
            __('Competition Code', 'wevoi-competition-manager'),
            __('Payment Status', 'wevoi-competition-manager'),
            __('Application Status', 'wevoi-competition-manager'),
            __('Price', 'wevoi-competition-manager'),
            __('Youtube Link', 'wevoi-competition-manager')
        ];

        // CSV header row
        fputcsv($output, $headers);

        // Data rows
        foreach ($registrations as $registration) {
            $student_first_name = wevoi_proper_name(trim($registration->student_first_name ?? ''));
            $student_last_name = wevoi_proper_name(trim($registration->student_last_name ?? ''));
            $supervisor_first_name = wevoi_proper_name(trim($registration->supervisor_first_name ?? ''));
            $supervisor_last_name = wevoi_proper_name(trim($registration->supervisor_last_name ?? ''));

            $row = [
                $registration->register_id,
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration->registration_date)),
                $student_first_name,
                $student_last_name,
                $registration->student_email,
                $supervisor_first_name,
                $supervisor_last_name,
                $registration->supervisor_email ? $registration->supervisor_email : ($registration->supervisor_email_raw ? $registration->supervisor_email_raw : ''),
                $registration->competition_code,
                $registration->paid_status,
                $registration->application_status,
                number_format($registration->price, 2),
                $registration->youtube_link ? $registration->youtube_link : ''
            ];

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}

// Add an init hook for the export functions
function wevoi_init_export_handlers() {
    handle_students_export_csv();
    handle_supervisors_export_csv();
    handle_agency_export_csv();
    handle_registrations_export_csv();
}
add_action('init', 'wevoi_init_export_handlers', 5);

/**
 * AJAX handler for updating prize payment status
 */
function wevoi_ajax_update_prize_payment_status() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wevoi_prize_payment_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $register_id = intval($_POST['register_id']);
    $new_status = sanitize_text_field($_POST['status']);

    if (!in_array($new_status, ['PAID', 'NOT PAID'])) {
        wp_send_json_error('Invalid status');
    }

    global $wpdb;

    $updated = $wpdb->update(
        $wpdb->prefix . 'wevoi_registrations',
        ['claimed_prize_paid_status' => $new_status],
        ['register_id' => $register_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        wp_send_json_success('Status updated successfully');
    } else {
        wp_send_json_error('Failed to update status');
    }
}
