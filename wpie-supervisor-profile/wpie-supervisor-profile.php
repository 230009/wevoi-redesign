<?php
/**
 * Plugin Name: WPIE Supervisor Profile
 * Plugin URI: https://wevoi.org
 * Description: Personal information page for supervisors showing their profile, students, results, and certificates
 * Version: 1.0.0
 * Author: Kenan
 * Author URI: https://wevoi.org
 * Text Domain: wpie-supervisor-profile
 * Domain Path: /languages
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPIE_SUPERVISOR_PROFILE_VERSION', '1.0.0');
define('WPIE_SUPERVISOR_PROFILE_PATH', plugin_dir_path(__FILE__));
define('WPIE_SUPERVISOR_PROFILE_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once WPIE_SUPERVISOR_PROFILE_PATH . 'includes/class-supervisor-profile.php';
require_once WPIE_SUPERVISOR_PROFILE_PATH . 'includes/class-supervisor-profile-shortcode.php';

/**
 * Register plugin assets
 */
function wpie_supervisor_profile_register_assets() {
    wp_register_style(
        'wpie-supervisor-profile',
        WPIE_SUPERVISOR_PROFILE_URL . 'assets/css/supervisor-profile.css',
        array(),
        WPIE_SUPERVISOR_PROFILE_VERSION
    );
}
add_action('wp_enqueue_scripts', 'wpie_supervisor_profile_register_assets');

/**
 * Initialize Supervisor Profile Module
 */
function wpie_init_supervisor_profile_module() {
    // Register shortcode
    $shortcode = new WPIE_Supervisor_Profile_Shortcode();
    $shortcode->register();

    // Initialize profile class
    $profile = new WPIE_Supervisor_Profile();
    $profile->init();
}
add_action('init', 'wpie_init_supervisor_profile_module');

/**
 * Load plugin textdomain
 */
function wpie_supervisor_profile_load_textdomain() {
    load_plugin_textdomain(
        'wpie-supervisor-profile',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'wpie_supervisor_profile_load_textdomain');

/**
 * Add ID, Created Date columns to front and Country column between Email and Role
 */
function wpie_add_user_id_column($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['user_id'] = __('ID', 'wpie-supervisor-profile');
    $new_columns['registration_date'] = __('Created Date', 'wpie-supervisor-profile');
    unset($columns['cb']);

    $result = $new_columns;
    foreach ($columns as $key => $label) {
        $result[$key] = $label;
        if ('email' === $key) {
            $result['user_country'] = __('Country', 'wpie-supervisor-profile');
        }
    }
    return $result;
}
add_filter('manage_users_columns', 'wpie_add_user_id_column');

/**
 * Display user ID, registration date, and country in columns
 */
function wpie_show_user_id_column($value, $column_name, $user_id) {
    if ('user_id' === $column_name) {
        return $user_id;
    }
    if ('registration_date' === $column_name) {
        $user = get_userdata($user_id);
        if ($user && $user->user_registered) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($user->user_registered));
        }
        return '-';
    }
    if ('user_country' === $column_name) {
        global $wpdb;
        $country_id = get_user_meta($user_id, 'countryid', true);
        if (empty($country_id)) {
            return '-';
        }
        $country = $wpdb->get_var($wpdb->prepare(
            "SELECT countryname FROM {$wpdb->prefix}wevoi_countries WHERE countryid = %d",
            intval($country_id)
        ));
        return $country ? esc_html($country) : '-';
    }
    return $value;
}
add_filter('manage_users_custom_column', 'wpie_show_user_id_column', 10, 3);

/**
 * Make ID and Created Date columns sortable
 * The array format [column_name => orderby_value] allows WordPress to handle sorting
 */
function wpie_make_user_id_column_sortable($columns) {
    $columns['user_id'] = 'ID';
    $columns['registration_date'] = 'registered';
    return $columns;
}
add_filter('manage_users_sortable_columns', 'wpie_make_user_id_column_sortable');


/**
 * Extend admin user search to include first_name and last_name meta fields
 */
function wpie_extend_user_search($query) {
    global $pagenow, $wpdb;

    if ('users.php' !== $pagenow) {
        return;
    }

    $search = isset($query->query_vars['search']) ? trim($query->query_vars['search'], '*') : '';
    if (empty($search)) {
        return;
    }

    $like = '%' . $wpdb->esc_like($search) . '%';

    $meta_user_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$wpdb->usermeta}
             WHERE (meta_key = 'first_name' OR meta_key = 'last_name')
             AND meta_value LIKE %s",
            $like
        )
    );

    if (empty($meta_user_ids)) {
        return;
    }

    $ids_in = implode(',', array_map('intval', $meta_user_ids));

    // WordPress search WHERE: AND (user_login LIKE '%x%' OR user_nicename LIKE ... OR display_name LIKE ...)
    // Inject matching meta user IDs as extra OR condition at the start of the search block.
    // The pattern targets the first search column so we don't accidentally touch role or other conditions.
    $query->query_where = preg_replace(
        '/AND\s*\(\s*(user_login\s+LIKE|user_nicename\s+LIKE|user_email\s+LIKE|display_name\s+LIKE)/i',
        "AND ( {$wpdb->users}.ID IN ({$ids_in}) OR \\1",
        $query->query_where,
        1
    );
}
add_action('pre_user_query', 'wpie_extend_user_search');

/**
 * Set default sorting by ID DESC and handle ID and Created Date column sorting
 */
function wpie_users_list_default_sort($query) {
    global $pagenow;

    if ('users.php' !== $pagenow) {
        return;
    }

    // Set default sorting to ID DESC when no orderby is specified
    if (!isset($_GET['orderby'])) {
        $query->query_vars['orderby'] = 'ID';
        $query->query_vars['order'] = 'DESC';
        return;
    }

    // When sorting by ID column
    if (isset($_GET['orderby']) && 'ID' === $_GET['orderby']) {
        $query->query_vars['orderby'] = 'ID';

        // If order is explicitly in URL, use it (WordPress sets this when toggling)
        if (isset($_GET['order']) && in_array(strtoupper($_GET['order']), array('ASC', 'DESC'))) {
            $query->query_vars['order'] = strtoupper($_GET['order']);
        } else {
            // If orderby=ID but no order in URL, this is likely first click
            // WordPress typically defaults to ASC for first click, but since our default is DESC,
            // we should toggle to ASC on first click
            $query->query_vars['order'] = 'ASC';
        }
    }

    // When sorting by registration date (created date)
    if (isset($_GET['orderby']) && 'registered' === $_GET['orderby']) {
        $query->query_vars['orderby'] = 'registered';

        // If order is explicitly in URL, use it
        if (isset($_GET['order']) && in_array(strtoupper($_GET['order']), array('ASC', 'DESC'))) {
            $query->query_vars['order'] = strtoupper($_GET['order']);
        } else {
            // Default to DESC for registration date
            $query->query_vars['order'] = 'DESC';
        }
    }
}
add_action('pre_get_users', 'wpie_users_list_default_sort');

/**
 * Add JavaScript to ensure ID and Created Date columns toggle properly
 */
function wpie_fix_user_id_column_toggle() {
    global $pagenow;
    if ('users.php' !== $pagenow) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Fix ID column header link
        var $idLink = $('th.column-user_id.sortable a, th.sortable.column-user_id a');
        if ($idLink.length) {
            $idLink.on('click', function(e) {
                var url = new URL(window.location);
                var currentOrderby = url.searchParams.get('orderby');
                var currentOrder = url.searchParams.get('order');

                // Determine current state: if no orderby, we're on default (DESC)
                // If orderby is ID, use the order from URL
                var isDesc = true;
                if (currentOrderby === 'ID') {
                    isDesc = (currentOrder && currentOrder.toUpperCase() === 'DESC') || (!currentOrder);
                }

                // Toggle order
                var newOrder = isDesc ? 'ASC' : 'DESC';

                // Update URL and navigate
                url.searchParams.set('orderby', 'ID');
                url.searchParams.set('order', newOrder);

                window.location.href = url.toString();
                e.preventDefault();
                return false;
            });
        }

        // Fix Created Date column header link
        var $regDateLink = $('th.column-registration_date.sortable a, th.sortable.column-registration_date a');
        if ($regDateLink.length) {
            $regDateLink.on('click', function(e) {
                var url = new URL(window.location);
                var currentOrderby = url.searchParams.get('orderby');
                var currentOrder = url.searchParams.get('order');

                // Determine current state
                var isDesc = true;
                if (currentOrderby === 'registered') {
                    isDesc = (currentOrder && currentOrder.toUpperCase() === 'DESC') || (!currentOrder);
                }

                // Toggle order
                var newOrder = isDesc ? 'ASC' : 'DESC';

                // Update URL and navigate
                url.searchParams.set('orderby', 'registered');
                url.searchParams.set('order', newOrder);

                window.location.href = url.toString();
                e.preventDefault();
                return false;
            });
        }
    });
    </script>
    <?php
}
add_action('admin_footer-users.php', 'wpie_fix_user_id_column_toggle');

/**
 * Add CSS to adjust column widths in users list table
 */
function wpie_users_list_column_styles() {
    global $pagenow;
    if ('users.php' !== $pagenow) {
        return;
    }
    ?>
    <style type="text/css">
        .wp-list-table .column-user_id {
            width: 60px;
            text-align: center;
        }
        .wp-list-table .column-registration_date {
            width: 150px;
        }
        .wp-list-table .column-user_country {
            width: 130px;
        }
    </style>
    <?php
}
add_action('admin_head-users.php', 'wpie_users_list_column_styles');
