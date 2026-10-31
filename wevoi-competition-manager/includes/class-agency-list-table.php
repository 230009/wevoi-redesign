<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Agency_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'agency',
            'plural'   => 'agencies',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'                => '<input type="checkbox" />',
            'registration_date' => __('Registration Date', 'wevoi-competition-manager'),
            'agency_details'    => __('Agency Details', 'wevoi-competition-manager'),
            'agency_phone'      => __('Agency Phone', 'wevoi-competition-manager'),
            'country'           => __('Country', 'wevoi-competition-manager'),
            'school'            => __('School', 'wevoi-competition-manager'),
            'total_students'    => __('Total Students', 'wevoi-competition-manager'),
            'video_submissions' => __('Students who submit Videos', 'wevoi-competition-manager'),
            'pending_videos'     => __('Students without Video', 'wevoi-competition-manager'),
            'actions'           => __('Actions', 'wevoi-competition-manager')
        ];
    }

    public function get_sortable_columns() {
        return [
            'registration_date' => ['user_registered', true],
            'agency_details'    => ['display_name', false],
            'total_students'   => ['student_count', false],
            'video_submissions'=> ['video_count', false]
        ];
    }

    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wevoi-competition-manager')
        ];
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = 20;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $sql_select = "SELECT DISTINCT u.*,
               um_country.meta_value as countryid,
               c.countryname,
               um_school.meta_value as schoolid,
               s.schoolname,
               (
                   SELECT COUNT(DISTINCT r.user_id)
                   FROM {$wpdb->prefix}wevoi_registrations r
                   WHERE r.supervisor_id = u.ID
               ) as student_count,
               (
                   SELECT um_phone.meta_value
                   FROM {$wpdb->prefix}usermeta um_phone
                   WHERE um_phone.user_id = u.ID
                   AND um_phone.meta_key = 'supervisorwhatsapp'
                   AND um_phone.meta_value IS NOT NULL
                   AND um_phone.meta_value <> ''
                   LIMIT 1
               ) as agency_phone,
               (
                   SELECT COUNT(DISTINCT r.user_id)
                   FROM {$wpdb->prefix}wevoi_registrations r
                   WHERE r.supervisor_id = u.ID
                   AND r.youtube_link IS NOT NULL
               ) as video_count";

        $sql_from = " FROM {$wpdb->prefix}users u
              JOIN {$wpdb->prefix}usermeta um_role
                  ON u.ID = um_role.user_id
                  AND um_role.meta_key = 'wpie_capabilities'
              LEFT JOIN {$wpdb->prefix}usermeta um_country
                  ON u.ID = um_country.user_id
                  AND um_country.meta_key = 'countryid'
              LEFT JOIN {$wpdb->prefix}usermeta um_school
                  ON u.ID = um_school.user_id
                  AND um_school.meta_key = 'schoolid'
              LEFT JOIN {$wpdb->prefix}wevoi_countries c
                  ON c.countryid = um_country.meta_value
              LEFT JOIN {$wpdb->prefix}wevoi_schools s
                  ON s.schoolid = um_school.meta_value";

        $sql_where = " WHERE um_role.meta_value LIKE '%agency%'";

        if (!empty($_REQUEST['s'])) {
            $search = sanitize_text_field($_REQUEST['s']);
            $sql_where .= $wpdb->prepare(
                " AND (u.display_name LIKE %s OR u.user_email LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        if (!empty($_REQUEST['country'])) {
            $sql_where .= $wpdb->prepare(" AND um_country.meta_value = %d", intval($_REQUEST['country']));
        }

        if (!empty($_REQUEST['school'])) {
            $sql_where .= $wpdb->prepare(" AND um_school.meta_value = %d", intval($_REQUEST['school']));
        }

        $sql_orderby = " ORDER BY u.user_registered DESC";
        $final_sql = $sql_select . $sql_from . $sql_where . $sql_orderby;

        $results = $wpdb->get_results($final_sql);

        $total_items = count($results);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $this->items = array_slice($results, $offset, $per_page);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'   => $per_page,
            'total_pages'=> ceil($total_items / $per_page)
        ]);
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="agencies[]" value="%s" />',
            $item->ID
        );
    }

    protected function column_registration_date($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->user_registered));
    }

    protected function column_agency_details($item) {
        $full_name = wevoi_format_display_name(
            get_user_meta($item->ID, 'first_name', true),
            get_user_meta($item->ID, 'last_name', true),
            $item->display_name
        );
        return sprintf(
            '<div class="agency-name">%s</div>
             <div class="agency-email">
                 <a href="#" class="email-trigger" data-email="%s">%s</a>
             </div>',
            esc_html($full_name),
            esc_attr($item->user_email),
            esc_html($item->user_email)
        );
    }

    protected function column_agency_phone($item) {
        $phone = isset($item->agency_phone) ? trim((string)$item->agency_phone) : '';
        if ($phone === '') {
            return '-';
        }
        $tel = preg_replace('/\s+/', '', $phone);
        return sprintf(
            '<a href="tel:%s">%s</a>',
            esc_attr($tel),
            esc_html($phone)
        );
    }

    protected function column_country($item) {
        if (empty($item->countryname)) {
            return '-';
        }
        return sprintf(
            '<div title="ID: %s">%s</div>',
            esc_attr($item->countryid),
            esc_html($item->countryname)
        );
    }

    protected function column_school($item) {
        if (empty($item->schoolname)) {
            return '-';
        }
        return sprintf(
            '<div title="ID: %s">%s</div>',
            esc_attr($item->schoolid),
            esc_html($item->schoolname)
        );
    }

    protected function column_total_students($item) {
        return sprintf(
            '<span class="student-count">%d</span>',
            intval($item->student_count ?? 0)
        );
    }

    protected function column_video_submissions($item) {
        return sprintf(
            '<span class="video-count">%d</span>',
            intval($item->video_count)
        );
    }

    protected function column_pending_videos($item) {
        $total = intval($item->student_count ?? 0);
        $with_video = intval($item->video_count ?? 0);
        $pending = max(0, $total - $with_video);
        return sprintf(
            '<span class="pending-video-count">%d</span>',
            $pending
        );
    }

    protected function column_actions($item) {
        $actions = [
            'edit' => sprintf(
                '<button type="button" class="button button-small edit-details" data-agency-id="%d">%s</button>',
                $item->ID,
                __('Edit', 'wevoi-competition-manager')
            ),
            'view' => sprintf(
                '<button type="button" class="button button-small view-details" data-agency-id="%d">%s</button>',
                $item->ID,
                __('View Details', 'wevoi-competition-manager')
            ),
            'delete' => sprintf(
                '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(
                    sprintf(
                        'admin.php?page=wevoi-competitions&tab=agency&action=delete&agency=%d',
                        $item->ID
                    ),
                    'delete_agency_' . $item->ID
                ),
                esc_js(__('Are you sure you want to delete this agency?', 'wevoi-competition-manager')),
                __('Delete', 'wevoi-competition-manager')
            ),
            'email' => sprintf(
                '<button type="button" class="button button-small email-compose" data-email="%s"><span class="dashicons dashicons-email"></span></button>',
                esc_attr($item->user_email)
            )
        ];
        return implode(' ', $actions);
    }

    protected function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }
}
