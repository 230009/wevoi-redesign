<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Registrations_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'registration',
            'plural'   => 'registrations',
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
            'student_details'   => __('Student Details', 'wevoi-competition-manager'),
            'competition'       => __('Competition', 'wevoi-competition-manager'),
            'payment_status'    => __('Payment Status', 'wevoi-competition-manager'),
            'application_status'=> __('Application Status', 'wevoi-competition-manager'),
            'student_result'    => __('Result', 'wevoi-competition-manager'),
            'actions'          => __('Actions', 'wevoi-competition-manager')
        ];
    }

    /**
     * Define sortable columns
     */
    public function get_sortable_columns() {
        return [
            'registration_date' => ['registration_date', true],
            'student_details'   => ['display_name', false],
            'payment_status'    => ['paid_status', false],
            'application_status'=> ['application_status', false]
        ];
    }

public function prepare_items() {
    global $wpdb;

    // Items per page
    $per_page = 20;

    // Set up columns
    $columns = $this->get_columns();
    $hidden = [];
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = [$columns, $hidden, $sortable];

    // Get search and filter parameters
    $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
    $payment_status = isset($_REQUEST['payment_status']) ? sanitize_text_field($_REQUEST['payment_status']) : '';
    $application_status = isset($_REQUEST['application_status']) ? sanitize_text_field($_REQUEST['application_status']) : '';
    $competition_filter = isset($_REQUEST['competition_filter']) ? intval($_REQUEST['competition_filter']) : 0;

    // Base SQL
    $sql_select = "SELECT r.*,
                    u.display_name, u.user_email,
                    c.competition_name, c.competition_status,
                    c.competition_type, c.forum_payment_phase,
                    co.countryname as payer_country,
                    supervisor_u.user_email as supervisor_email_raw,
                    supervisor_u.ID as supervisor_user_id,
                    supervisor_u.user_email as supervisor_email,
                    supervisor_first.meta_value as supervisor_first_name,
                    supervisor_last.meta_value as supervisor_last_name,
                    supervisor_co.countryname as supervisor_country";

    $sql_from = " FROM {$wpdb->prefix}wevoi_registrations r
                  LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                  LEFT JOIN {$wpdb->usermeta} um_student_fn ON u.ID = um_student_fn.user_id AND um_student_fn.meta_key = 'first_name'
                  LEFT JOIN {$wpdb->usermeta} um_student_ln ON u.ID = um_student_ln.user_id AND um_student_ln.meta_key = 'last_name'
                  LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                  LEFT JOIN {$wpdb->prefix}wevoi_countries co ON r.payer_countryid = co.countryid
                  LEFT JOIN {$wpdb->users} supervisor_u ON r.supervisor_id = supervisor_u.ID
                  LEFT JOIN {$wpdb->usermeta} supervisor_first ON supervisor_u.ID = supervisor_first.user_id AND supervisor_first.meta_key = 'first_name'
                  LEFT JOIN {$wpdb->usermeta} supervisor_last ON supervisor_u.ID = supervisor_last.user_id AND supervisor_last.meta_key = 'last_name'
                  LEFT JOIN {$wpdb->usermeta} supervisor_um_country ON supervisor_u.ID = supervisor_um_country.user_id AND supervisor_um_country.meta_key = 'countryid'
                  LEFT JOIN {$wpdb->prefix}wevoi_countries supervisor_co ON supervisor_um_country.meta_value = supervisor_co.countryid";

    $sql_where = " WHERE 1=1";

    // Search conditions (match Student Details: first_name/last_name meta, then display_name / email)
    if ($search) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $sql_where .= $wpdb->prepare(
            " AND (um_student_fn.meta_value LIKE %s OR um_student_ln.meta_value LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s OR CONCAT(COALESCE(um_student_fn.meta_value,''), ' ', COALESCE(um_student_ln.meta_value,'')) LIKE %s)",
            $like,
            $like,
            $like,
            $like,
            $like
        );
    }

    // Add payment status filter
    if ($payment_status) {
        $sql_where .= $wpdb->prepare(" AND r.paid_status = %s", $payment_status);
    }

    // Add application status filter
    if ($application_status) {
        $sql_where .= $wpdb->prepare(" AND r.application_status = %s", $application_status);
    }

    // Add competition filter
    if ($competition_filter) {
        $sql_where .= $wpdb->prepare(" AND r.competition_code = %d", $competition_filter);
    }

    // Get sort parameters
    $orderby = isset($_REQUEST['orderby']) ? trim($_REQUEST['orderby']) : 'registration_date';
    $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'DESC';

    // Add ORDER BY clause
    $sql_orderby = " ORDER BY $orderby $order";

    // Get total items count
    $total_items = $wpdb->get_var("SELECT COUNT(*) " . $sql_from . $sql_where);

    // Pagination
    $current_page = $this->get_pagenum();
    $offset = ($current_page - 1) * $per_page;

    // Final query
    $this->items = $wpdb->get_results($sql_select . $sql_from . $sql_where . $sql_orderby . " LIMIT $per_page OFFSET $offset");

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
            '<input type="checkbox" name="registrations[]" value="%s" />',
            $item->register_id
        );
    }

    /**
     * Registration Date column
     */
    protected function column_registration_date($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->registration_date));
    }

    /**
     * Student Details column
     */
    protected function column_student_details($item) {
        $full_name = wevoi_format_display_name(
            get_user_meta($item->user_id, 'first_name', true),
            get_user_meta($item->user_id, 'last_name', true),
            $item->display_name
        );

        // Supervisor information
        $supervisor_info = '';
        $supervisor_email = !empty($item->supervisor_email) ? $item->supervisor_email : (!empty($item->supervisor_email_raw) ? $item->supervisor_email_raw : '');

        if (!empty($supervisor_email)) {
            $supervisor_full_name = wevoi_format_display_name(
                isset($item->supervisor_first_name) ? $item->supervisor_first_name : '',
                isset($item->supervisor_last_name) ? $item->supervisor_last_name : '',
                ''
            );
            if ($supervisor_full_name === '—' && !empty($item->supervisor_user_id)) {
                $supervisor_user = get_user_by('id', $item->supervisor_user_id);
                if ($supervisor_user) {
                    $supervisor_full_name = wevoi_proper_name($supervisor_user->display_name);
                }
            }

            if (!empty($supervisor_full_name)) {
                $supervisor_info = sprintf(
                    '<div class="supervisor-info"><a href="mailto:%s">%s</a></div>',
                    esc_attr($supervisor_email),
                    esc_html($supervisor_full_name)
                );
            } else {
                // If no name available, just show email as link
                $supervisor_info = sprintf(
                    '<div class="supervisor-info"><a href="mailto:%s">%s</a></div>',
                    esc_attr($supervisor_email),
                    esc_html($supervisor_email)
                );
            }
        }

        return sprintf(
            '<div class="student-name"><a href="mailto:%s">%s</a></div>
             %s',
            esc_attr($item->user_email),
            esc_html($full_name),
            $supervisor_info
        );
    }

    /**
     * Competition column
     */
    protected function column_competition($item) {
        return sprintf(
            '<strong>%s</strong>
             <div class="competition-status">
                <span class="status-badge status-%s">%s</span>
             </div>
             <div class="row-actions">
                <span class="code">Code: %s</span>
             </div>',
            esc_html($item->competition_name),
            sanitize_html_class(strtolower(str_replace(' ', '-', $item->competition_status))),
            esc_html($item->competition_status),
            esc_html($item->competition_code)
        );
    }

    /**
     * Payment Status column
     */
    protected function column_payment_status($item) {
        $payment_info = '';
        if ($item->price > 0) {
            $payment_info = sprintf(
                '<div class="payment-info">£%s</div>',
                number_format($item->price, 2)
            );
        }

        if ($item->refund_amount > 0) {
            $payment_info .= sprintf(
                '<div class="refund-info">%s: £%s</div>',
                __('Refunded', 'wevoi-competition-manager'),
                number_format($item->refund_amount, 2)
            );
        }

        // Add coupon code display for FREE status
        if ($item->paid_status === 'FREE' && !empty($item->coupon_code)) {
            $payment_info .= sprintf(
                '<div class="coupon-info">%s: %s</div>',
                __('Coupon', 'wevoi-competition-manager'),
                esc_html($item->coupon_code)
            );
        }

        $paid_raw = (string) $item->paid_status;
        $paid_upper = strtoupper($paid_raw);
        $badge_class = sanitize_html_class(strtolower($paid_raw));
        $badge_label = ($paid_upper === 'PRE_REGISTERED')
            ? __('Pre-registered', 'wevoi-competition-manager')
            : $paid_raw;

        $forum_hint = '';
        if ($paid_upper === 'PRE_REGISTERED'
            && isset($item->competition_type)
            && strtoupper(trim((string) $item->competition_type)) === 'FORUM'
        ) {
            $phase = isset($item->forum_payment_phase) ? (string) $item->forum_payment_phase : '';
            if ($phase === 'payment_required') {
                $forum_hint = sprintf(
                    '<div class="forum-payment-phase-hint" style="font-size:11px;color:#555;margin-top:6px;max-width:240px;line-height:1.35;">%s</div>',
                    esc_html__('Payment window is open; student has not completed payment yet.', 'wevoi-competition-manager')
                );
            } else {
                $forum_hint = sprintf(
                    '<div class="forum-payment-phase-hint" style="font-size:11px;color:#555;margin-top:6px;max-width:240px;line-height:1.35;">%s</div>',
                    esc_html__('Pre-registration only; full payment is not open yet.', 'wevoi-competition-manager')
                );
            }
        }

        return sprintf(
            '<span class="status-badge status-%1$s">%2$s</span>%3$s%4$s',
            $badge_class,
            esc_html($badge_label),
            $forum_hint,
            $payment_info
        );
    }

    /**
     * Application Status column
     */
    protected function column_application_status($item) {
        $youtube_link = !empty($item->youtube_link) ?
            sprintf('<a href="%s" target="_blank" class="youtube-link">%s</a>',
                esc_url($item->youtube_link),
                __('Watch Video', 'wevoi-competition-manager')
            ) : '';

        return sprintf(
            '<span class="status-badge status-%s">%s</span>%s',
            strtolower($item->application_status),
            esc_html($item->application_status),
            $youtube_link
        );
    }

    /**
     * Student Result column
     */
    protected function column_student_result($item) {
        $result_html = '';

        if (!empty($item->student_medal)) {
            $result_html = sprintf(
                '<div class="medal-badge medal-%s">
                    <span class="dashicons dashicons-awards"></span>
                    %s
                </div>
                <div class="result-details">
                    <div>%s: %s</div>
                    <div>%s: #%d</div>',
                strtolower($item->student_medal),
                esc_html($item->student_medal),
                __('Points', 'wevoi-competition-manager'),
                number_format($item->student_point, 2),
                __('Rank', 'wevoi-competition-manager'),
                intval($item->student_rank)
            );

            // Add Summit coupon code if exists
            if (!empty($item->claimed_summit_coupon_code)) {
                $result_html .= sprintf(
                    '<div class="summit-coupon-info"><strong>%s:</strong> %s</div>',
                    __('Global Summit Coupon', 'wevoi-competition-manager'),
                    esc_html($item->claimed_summit_coupon_code)
                );
            }

            $result_html .= '</div>';
        } else {
            // Even if no medal, show summit coupon if exists
            if (!empty($item->claimed_summit_coupon_code)) {
                $result_html = sprintf(
                    '<div class="result-details">
                        <div class="summit-coupon-info"><strong>%s:</strong> %s</div>
                    </div>',
                    __('Global Summit Coupon', 'wevoi-competition-manager'),
                    esc_html($item->claimed_summit_coupon_code)
                );
            } else {
                return '-';
            }
        }

        return $result_html;
    }

    protected function column_actions($item) {
        $actions = [
            'edit' => sprintf(
                '<button type="button" class="button button-small edit-details" data-registration-id="%d">%s</button>',
                $item->register_id,
                __('Edit', 'wevoi-competition-manager')
            ),
            'view' => sprintf(
                '<button type="button" class="button button-small view-details" data-registration-id="%d">%s</button>',
                $item->register_id,
                __('View Details', 'wevoi-competition-manager')
            ),
            'email' => sprintf(
                '<button type="button" class="button button-small email-compose" data-email="%s"><span class="dashicons dashicons-email"></span></button>',
                esc_attr($item->user_email)
            ),
        ];

        // Bank Details butonu - sadece para ödülü almayı hak eden öğrencilerde göster
        if ($this->is_prize_winner($item)) {
            $actions['bank_details'] = sprintf(
                '<button type="button" class="button button-small button-primary bank-details" data-registration-id="%d">%s</button>',
                $item->register_id,
                __('Bank Details', 'wevoi-competition-manager')
            );
        }

        // Delete butonu APPLICATION_ACCEPTED dışındaki tüm durumlarda gösterilecek
        if ($item->application_status !== 'APPLICATION_ACCEPTED') {
            $actions['delete'] = sprintf(
                '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(
                    sprintf(
                        'admin.php?page=wevoi-competitions&tab=registrations&action=delete&registration=%d',
                        $item->register_id
                    ),
                    'delete_registration_' . $item->register_id
                ),
                esc_js(__('Are you sure you want to delete this registration?', 'wevoi-competition-manager')),
                __('Delete', 'wevoi-competition-manager')
            );
        }

        return implode(' ', $actions);
    }

    /**
     * Check if student is eligible for prize money (top 3 in their age group)
     */
    private function is_prize_winner($item) {
        // Sadece GOLD, SILVER, BRONZE medal alan öğrenciler para ödülü alabilir
        $prize_medals = ['GOLD', 'SILVER', 'BRONZE'];

        if (!empty($item->student_medal) && in_array($item->student_medal, $prize_medals)) {
            // Rank kontrolü - sadece ilk 3
            if (!empty($item->student_rank) && $item->student_rank <= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * Default column display
     */
    protected function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }
}
