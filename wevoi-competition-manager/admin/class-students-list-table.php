<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Students_List_Table extends WP_List_Table {

    // Sınıf seviyesinde öğe sayısını saklayacak değişken
    private $total_items_count = 0;

    public function __construct() {
        parent::__construct([
            'singular' => 'student',
            'plural'   => 'students',
            'ajax'     => false
        ]);
    }

    /**
     * Tablo sütunlarını tanımla
     */
    public function get_columns() {
        return [
            'cb'                => '<input type="checkbox" />',
            'registration_date' => __('Registration Date', 'wevoi-competition-manager'),
            'student_details'   => __('Student Details', 'wevoi-competition-manager'),
            'supervisor_name'   => __('Supervisor Name', 'wevoi-competition-manager'),
            'supervisor_email'  => __('Supervisor Email', 'wevoi-competition-manager'),
            'supervisor_phone'  => __('Supervisor Phone', 'wevoi-competition-manager'),
            'country'          => __('Country', 'wevoi-competition-manager'),
            'school'           => __('School', 'wevoi-competition-manager'),
            'competitions'      => __('Competitions', 'wevoi-competition-manager'),
            'actions'          => __('Actions', 'wevoi-competition-manager')
        ];
    }

    /**
     * Sıralanabilir sütunları tanımla
     */
    public function get_sortable_columns() {
        return [
            'registration_date' => ['user_registered', true],
            'student_details'   => ['display_name', false],
            'competitions'      => ['competition_count', false]
        ];
    }

    /**
     * Toplu işlemler
     */
    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wevoi-competition-manager')
        ];
    }

    /**
     * Get total items count
     */
    public function get_total_items() {
        return $this->total_items_count;
    }

    /**
     * Verileri hazırla
     */
    public function prepare_items() {
        global $wpdb;

        // Sayfa başına gösterilecek öğe sayısı
        $per_page = 20;

        // Sütunları ayarla
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Arama ve filtreleme parametrelerini al
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $country = isset($_REQUEST['country']) ? intval($_REQUEST['country']) : 0;
        $school = isset($_REQUEST['school']) ? intval($_REQUEST['school']) : 0;
        // Yeni competition_status filtresi
        $competition_status = isset($_REQUEST['competition_status']) ? sanitize_text_field($_REQUEST['competition_status']) : '';

        // Base SQL
        $sql_select = "SELECT DISTINCT u.*,
                   um_country.meta_value as countryid,
                   c.countryname,
                   um_school.meta_value as schoolid,
                   s.schoolname,
                   TRIM(CONCAT(COALESCE(um_super_name.meta_value,''), ' ', COALESCE(um_super_email.meta_value,''))) as supervisor_name,
                   supervisor_u.user_email as supervisor_email,
                   um_super_phone.meta_value as supervisor_phone,
                   COUNT(DISTINCT r.register_id) as competition_count";

        $sql_from = " FROM {$wpdb->users} u
                  LEFT JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'
                  LEFT JOIN {$wpdb->usermeta} um_country ON u.ID = um_country.user_id AND um_country.meta_key = 'countryid'
                  LEFT JOIN {$wpdb->usermeta} um_school ON u.ID = um_school.user_id AND um_school.meta_key = 'schoolid'
                  LEFT JOIN {$wpdb->prefix}wevoi_registrations r_sup ON u.ID = r_sup.user_id AND r_sup.supervisor_id IS NOT NULL
                  LEFT JOIN {$wpdb->users} supervisor_u ON r_sup.supervisor_id = supervisor_u.ID
                  LEFT JOIN {$wpdb->usermeta} um_super_name ON supervisor_u.ID = um_super_name.user_id AND um_super_name.meta_key = 'first_name'
                  LEFT JOIN {$wpdb->usermeta} um_super_email ON supervisor_u.ID = um_super_email.user_id AND um_super_email.meta_key = 'last_name'
                  LEFT JOIN {$wpdb->usermeta} um_super_phone ON supervisor_u.ID = um_super_phone.user_id AND um_super_phone.meta_key = 'supervisorwhatsapp'
                  LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
                  LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = um_school.meta_value
                  LEFT JOIN {$wpdb->prefix}wevoi_registrations r ON u.ID = r.user_id";

        $sql_where = " WHERE um_role.meta_value LIKE '%\"student\"%'";

        // Search koşulları
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

        // Yarışma katılım durumu filtresi
        $sql_having = "";
        if ($competition_status === 'participating') {
            $sql_having = " HAVING competition_count > 0";
        } elseif ($competition_status === 'not_participating') {
            $sql_having = " HAVING competition_count = 0";
        }

        // Sıralama parametrelerini al
        $orderby = isset($_REQUEST['orderby']) ? trim($_REQUEST['orderby']) : 'user_registered';
        $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'DESC';

        // Sıralama için ORDER BY kısmını hazırla
        $sql_orderby = " GROUP BY u.ID";

        // HAVING kısmını ekle
        $sql_orderby .= $sql_having;

        switch($orderby) {
            case 'display_name':
                $sql_orderby .= " ORDER BY u.display_name";
                break;
            case 'competition_count':
                $sql_orderby .= " ORDER BY competition_count";
                break;
            case 'user_registered':
            default:
                $sql_orderby .= " ORDER BY u.user_registered";
                break;
        }
        $sql_orderby .= " " . $order;

        // Toplam kayıt sayısını al
    $count_sql = "SELECT COUNT(*) FROM (
        SELECT DISTINCT u.ID,
        COUNT(DISTINCT r.register_id) as competition_count
        " . $sql_from . $sql_where . "
        GROUP BY u.ID
        " . $sql_having .
    ") as count_table";

        $total_items = $wpdb->get_var($count_sql);

        // Sınıf değişkenine toplam öğe sayısını kaydet
        $this->total_items_count = (int)$total_items;

        // Sayfalama
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Final sorguyu oluştur
        $final_sql = $sql_select . $sql_from . $sql_where . $sql_orderby . " LIMIT $per_page OFFSET $offset";

        // Verileri al
        $this->items = $wpdb->get_results($final_sql);

        // Sayfalama ayarlarını yap
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'   => $per_page,
            'total_pages'=> ceil($total_items / $per_page)
        ]);
    }

    /**
     * Checkbox sütunu
     */
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="students[]" value="%s" />',
            $item->ID
        );
    }

    /**
     * Registration Date sütunu
     */
    protected function column_registration_date($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->user_registered));
    }

    /**
     * Student Details sütunu
     */
    protected function column_student_details($item) {
        global $wpdb;

        $full_name = wevoi_format_display_name(
            get_user_meta($item->ID, 'first_name', true),
            get_user_meta($item->ID, 'last_name', true),
            $item->display_name
        );

        return sprintf(
            '<div class="student-name">%s</div>
             <div class="student-email"><a href="mailto:%s">%s</a></div>',
            esc_html($full_name),
            esc_attr($item->user_email),
            esc_html($item->user_email)
        );
    }

    /**
     * Supervisor Name sütunu
     */
    protected function column_supervisor_name($item) {
        $name = isset($item->supervisor_name) ? trim((string)$item->supervisor_name) : '';
        return $name !== '' ? esc_html($name) : '-';
    }

    /**
     * Supervisor Email sütunu
     */
    protected function column_supervisor_email($item) {
        $email = isset($item->supervisor_email) ? trim((string)$item->supervisor_email) : '';
        if ($email === '') {
            return '-';
        }
        return sprintf(
            '<a href="mailto:%s">%s</a>',
            esc_attr($email),
            esc_html($email)
        );
    }

    /**
     * Supervisor Phone sütunu
     */
    protected function column_supervisor_phone($item) {
        $phone = isset($item->supervisor_phone) ? trim((string)$item->supervisor_phone) : '';
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

    /**
     * Country sütunu
     */
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

    /**
     * School sütunu
     */
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

    /**
     * Competitions sütunu
     */
    protected function column_competitions($item) {
        return sprintf(
            '<span class="competition-count">%d</span>',
            intval($item->competition_count)
        );
    }

    /**
     * Actions sütunu
     */
    protected function column_actions($item) {
        $actions = [
            'edit' => sprintf(
                '<button type="button" class="button button-small edit-details" data-student-id="%d">%s</button>',
                $item->ID,
                __('Edit', 'wevoi-competition-manager')
            ),
            'view' => sprintf(
                '<button type="button" class="button button-small view-details" data-student-id="%d">%s</button>',
                $item->ID,
                __('View Details', 'wevoi-competition-manager')
            ),
            'delete' => sprintf(
                '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(
                    sprintf(
                        'admin.php?page=wevoi-competitions&tab=students&action=delete&student=%d',
                        $item->ID
                    ),
                    'delete_student_' . $item->ID
                ),
                esc_js(__('Are you sure you want to delete this student?', 'wevoi-competition-manager')),
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
     * Varsayılan sütun görünümü
     */
    protected function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }
}
