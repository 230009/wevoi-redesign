<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Judges_List_Table extends WP_List_Table {

    public $total_filtered_items = 0;

    public function __construct() {
        parent::__construct([
            'singular' => 'judge',
            'plural'   => 'judges',
            'ajax'     => false
        ]);
    }

    /**
     * Define table columns
     */
public function get_columns() {
    return [
        'cb'                => '<input type="checkbox" />',
        'registration_date' => __('Registration Date', 'wevoi-competition-manager'),
        'judge_details'     => __('Judge Details', 'wevoi-competition-manager'),
        'location'          => __('Country & School', 'wevoi-competition-manager'),
        'assignments'       => __('Evaluations', 'wevoi-competition-manager') . ' <span class="assignment-help">' . __('(Evaluated/Assigned)', 'wevoi-competition-manager') . '</span>',
        'actions'          => __('Actions', 'wevoi-competition-manager')
    ];
}

    /**
     * Define sortable columns
     */
    public function get_sortable_columns() {
        return [
           'registration_date' => ['user_registered', true],
            'judge_details'     => ['display_name', false],
            'assignments'       => ['evaluated_count', false]
        ];
    }

    /**
     * Bulk actions
     */
    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wevoi-competition-manager')
        ];
    }

    /**
     * Prepare items for table
     */
public function prepare_items() {
    global $wpdb;

    // Filtre uygulanmışsa tüm kayıtları göster, değilse sayfalama yap
    $is_filtered = isset($_REQUEST['country']) ||
                   isset($_REQUEST['school']) ||
                   isset($_REQUEST['evaluation_status']) ||
                   !empty($_REQUEST['s']);

    // Items per page
    $per_page = $is_filtered ? 9999 : 20; // Filtre varsa yüksek bir sayı ile sınırla (pratik olarak tümünü göster)

    // Set up columns
    $columns = $this->get_columns();
    $hidden = [];
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = [$columns, $hidden, $sortable];

    // Get search and filter parameters
    $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
    $country = isset($_REQUEST['country']) ? intval($_REQUEST['country']) : 0;
    $school = isset($_REQUEST['school']) ? intval($_REQUEST['school']) : 0;
    $evaluation_status = isset($_REQUEST['evaluation_status']) ? sanitize_text_field($_REQUEST['evaluation_status']) : '';

    // SQL sorgusunu güncelliyoruz - evaluated_count ekliyoruz
    // Sadece EVALUATING durumundaki yarışmalardaki değerlendirmeleri göster
    $sql_select = "SELECT DISTINCT u.*,
               um_country.meta_value as countryid,
               c.countryname,
               um_school.meta_value as schoolid,
               s.schoolname,
               COUNT(DISTINCT CASE WHEN comp.competition_code IS NOT NULL THEN ja.assignment_id END) as assignment_count,
               COUNT(DISTINCT CASE WHEN je.evaluation_id IS NOT NULL AND comp.competition_code IS NOT NULL THEN je.evaluation_id END) as evaluated_count";

    $sql_from = " FROM {$wpdb->users} u
                  LEFT JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'
                  LEFT JOIN {$wpdb->usermeta} um_country ON u.ID = um_country.user_id AND um_country.meta_key = 'countryid'
                  LEFT JOIN {$wpdb->usermeta} um_school ON u.ID = um_school.user_id AND um_school.meta_key = 'schoolid'
                  LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
                  LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = um_school.meta_value
                  LEFT JOIN {$wpdb->prefix}judge_assignments ja ON u.ID = ja.judge_id
                  LEFT JOIN {$wpdb->prefix}wevoi_competitions comp ON ja.competition_code = comp.competition_code AND comp.competition_status = 'EVALUATING'
                  LEFT JOIN {$wpdb->prefix}judge_evaluations je ON ja.assignment_id = je.assignment_id AND je.judge_id = ja.judge_id";

    $sql_where = " WHERE um_role.meta_key = '{$wpdb->prefix}capabilities' AND um_role.meta_value LIKE '%\"judge\"%'";

    // Search conditions
    if ($search) {
        $sql_where .= $wpdb->prepare(
            " AND (u.display_name LIKE %s OR u.user_email LIKE %s)",
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }

    if ($country) {
        $sql_where .= $wpdb->prepare(" AND um_country.meta_value = %d", $country);
    }

    if ($school) {
        $sql_where .= $wpdb->prepare(" AND um_school.meta_value = %d", $school);
    }

    // Prepare HAVING clause for evaluation status filtering
    $sql_having = "";
    if ($evaluation_status) {
        switch ($evaluation_status) {
            case 'not_started':
                $sql_having = " HAVING assignment_count > 0 AND evaluated_count = 0";
                break;
            case 'evaluating':
                $sql_having = " HAVING evaluated_count > 0 AND evaluated_count < assignment_count";
                break;
            case 'completed':
                $sql_having = " HAVING assignment_count > 0 AND evaluated_count = assignment_count";
                break;
        }
    }

    // Get sort parameters
    $orderby = isset($_REQUEST['orderby']) ? trim($_REQUEST['orderby']) : 'user_registered';
    $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'DESC';

    // Prepare ORDER BY clause
    $sql_orderby = " GROUP BY u.ID";

    // Add HAVING clause after GROUP BY
    $sql_orderby .= $sql_having;

    switch($orderby) {
        case 'display_name':
            $sql_orderby .= " ORDER BY u.display_name";
            break;
        case 'assignment_count':
            $sql_orderby .= " ORDER BY assignment_count";
            break;
        case 'evaluated_count': // Değerlendirme sayısına göre sıralama ekliyoruz
            $sql_orderby .= " ORDER BY evaluated_count";
            break;
        case 'user_registered':
        default:
            $sql_orderby .= " ORDER BY u.user_registered";
            break;
    }
    $sql_orderby .= " " . $order;

    // Get total items count
    $count_sql = "SELECT COUNT(*) FROM (SELECT u.ID " . $sql_from . $sql_where . $sql_orderby . ") as filtered_judges";
    $total_items = $wpdb->get_var($count_sql);

    // Pagination - filtreleme yapıldığında offset olmadan tümünü getir
    $current_page = $this->get_pagenum();
    $offset = $is_filtered ? 0 : ($current_page - 1) * $per_page;
    $limit = $is_filtered ? "" : " LIMIT $per_page OFFSET $offset";

// Final query
$final_sql = $sql_select . $sql_from . $sql_where . $sql_orderby . $limit;

// Get items
$this->items = $wpdb->get_results($final_sql);

// Toplam öğe sayısını kaydet
$this->total_filtered_items = $total_items;

// Set up pagination
$this->set_pagination_args([
    'total_items' => $total_items,
    'per_page'   => $per_page,
    'total_pages'=> ceil($total_items / $per_page)
]);

}




    /**
     * Checkbox column
     */
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="judges[]" value="%s" />',
            $item->ID
        );
    }

    /**
     * Registration Date column
     */
    protected function column_registration_date($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->user_registered));
    }

    /**
     * Judge Details column
     */
    protected function column_judge_details($item) {
        $full_name = wevoi_format_display_name(
            get_user_meta($item->ID, 'first_name', true),
            get_user_meta($item->ID, 'last_name', true),
            $item->display_name
        );

    return sprintf(
        '<div class="judge-name">%s</div>
         <div class="judge-email">
             <a href="mailto:%s" class="email-trigger">%s</a>
         </div>',
        esc_html($full_name),
        esc_attr($item->user_email),
        esc_html($item->user_email)
    );
    }

    /**
     * Location column
     */
    protected function column_location($item) {
        $country = !empty($item->countryname) ?
            sprintf('<div title="ID: %s">%s</div>', esc_attr($item->countryid), esc_html($item->countryname)) :
            '-';

        $school = !empty($item->schoolname) ?
            sprintf('<div title="ID: %s">%s</div>', esc_attr($item->schoolid), esc_html($item->schoolname)) :
            '-';

        return $country . $school;
    }

    /**
     * Assignments column
     */
// Assignments sütununu güncelliyoruz
protected function column_assignments($item) {
    $total = intval($item->assignment_count);
    $evaluated = intval($item->evaluated_count);

    // Değerlendirme durumuna göre sınıf ve durum metni ekliyoruz
    $class = '';
    $status_text = '';

    if ($total == 0) {
        $status_text = '';
    } else if ($evaluated == 0) {
        $class = 'not-started';
        $status_text = '(Not started)';
    } else if ($evaluated < $total) {
        $class = 'in-progress';
        $status_text = '(Evaluating)';
    } else if ($evaluated == $total) {
        $class = 'completed';
        $status_text = '(Completed)';
    }

    return sprintf(
        '<span class="assignment-count %s">%d/%d</span> <span class="evaluation-status %s">%s</span>',
        $class,
        $evaluated,
        $total,
        $class,
        $status_text
    );
}

protected function column_actions($item) {
    $actions = [
       'edit' => sprintf(
            '<button type="button" class="button button-small edit-details" data-judge-id="%d">%s</button>',
            $item->ID,
            __('Edit', 'wevoi-competition-manager')
        ),
        'view' => sprintf(
            '<button type="button" class="button button-small view-details" data-judge-id="%d">%s</button>',
            $item->ID,
            __('View Details', 'wevoi-competition-manager')
        ),
        'delete' => sprintf(
            '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>',
            wp_nonce_url(
                sprintf(
                    'admin.php?page=wevoi-competitions&tab=judges&action=delete&judge=%d',
                    $item->ID
                ),
                'delete_judge_' . $item->ID
            ),
            esc_js(__('Are you sure you want to delete this judge?', 'wevoi-competition-manager')),
            __('Delete', 'wevoi-competition-manager')
        ),
        'email' => sprintf(
            '<button type="button" class="button button-small email-compose" data-email="%s"><span class="dashicons dashicons-email"></span></button>',
            esc_attr($item->user_email)
        )

    ];

    return implode(' ', $actions);
}

    /**
     * Default column display
     */
    protected function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }
}
