<?php

class Wevoi_Dashboard_Widgets {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Dashboard widget'larını ekle
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
    
        // Assets'leri yükle
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
        
        // CSS'i yükle
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_styles'));

        // AJAX action'ı ekle
        add_action('wp_ajax_get_students_modal_stats', array($this, 'get_students_modal_stats'));
        add_action('wp_ajax_get_judges_modal_stats', array($this, 'get_judges_modal_stats'));
        add_action('wp_ajax_get_supervisors_modal_stats', array($this, 'get_supervisors_modal_stats'));
    }
    
    public function get_students_modal_stats() {
        check_ajax_referer('students_modal_stats', 'nonce');

        $total_students = $this->get_student_stats()['total'];

        $stats = [
            'total_applications' => [
                'total' => $total_students,
                'active' => $this->get_student_stats()['participants'],
                'pending' => 0,
                'last_30_days' => $this->get_student_stats()['last_30_days']
            ],
            'partner_type_distribution' => $this->get_partner_type_distribution(),
            'monthly_trends' => $this->get_monthly_student_trends(),
            'top_countries' => $this->get_top_student_countries()
        ];

        wp_send_json_success($stats);
    }

    private function get_partner_type_distribution() {
        return [
            [
                'type' => 'Students', 
                'count' => $this->get_student_stats()['total'], 
                'percentage' => 100
            ]
        ];
    }

    public function render_students_widget() {
        $stats = $this->get_student_stats();
        wp_nonce_field('students_modal_stats', 'students_modal_nonce');
        ?>
        <div class="wevoi-dashboard-widget students-widget">
            <div class="stats-boxes">
                <div class="stat-box">
                    <span class="stat-number"><?php echo esc_html($stats['total']); ?></span>
                    <span class="stat-label">Total Students</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo esc_html($stats['participants']); ?></span>
                    <span class="stat-label">Competition Participants</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo esc_html($stats['medal_winners']); ?></span>
                    <span class="stat-label">Medal Winners</span>
                </div>
            </div>

            <div class="stats-info">
                <p>Last 30 Days: <?php echo esc_html($stats['last_30_days']); ?> new students</p>
            </div>

            <div class="widget-actions">
                <a href="<?php echo admin_url('admin.php?page=wevoi-competitions&tab=students'); ?>" 
                   class="button button-primary">View All Students</a>
                <button type="button" class="button button-secondary show-students-stats-modal">View Statistics</button>
            </div>

            <div id="students-stats-modal" class="wevoi-modal" style="display: none;">
                <div class="wevoi-modal-content">
                    <div class="wevoi-modal-header">
                        <h2>Student Statistics</h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    
                    <div class="wevoi-modal-body">
                        <div class="stats-cards">
                            <div class="stats-card" id="total-applications">
                                <h3>Total Applications</h3>
                                <div class="stats-card-content">
                                    <div class="stats-loading">Loading...</div>
                                </div>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stats-card" id="partner-type-distribution">
                                <h3>Student Type Distribution</h3>
                                <div class="stats-card-content">
                                    <div class="stats-loading">Loading...</div>
                                </div>
                            </div>

                            <div class="stats-card" id="top-countries">
                                <h3>Top Countries</h3>
                                <div class="stats-card-content">
                                    <div class="stats-loading">Loading...</div>
                                </div>
                            </div>
                        </div>

                        <div class="stats-card" id="monthly-trends">
                            <h3>Monthly Trends</h3>
                            <div class="stats-card-content">
                                <div class="stats-loading">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_student_type_distribution() {
        return $this->wpdb->get_results(
            "SELECT 'Student' as type, COUNT(*) as count 
             FROM {$this->wpdb->users} u
             JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
             AND um.meta_value LIKE '%\"student\"%'"
        );
    }

    private function get_monthly_student_trends() {
        return $this->wpdb->get_results(
            "SELECT 
                DATE_FORMAT(user_registered, '%M %Y') as month,
                COUNT(*) as total
             FROM {$this->wpdb->users} u
             JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
             AND um.meta_value LIKE '%\"student\"%'
             GROUP BY month
             ORDER BY user_registered DESC
             LIMIT 6",
            ARRAY_A
        );
    }

    private function get_top_student_countries() {
        return $this->wpdb->get_results(
            "SELECT 
                c.countryname as country, 
                COUNT(DISTINCT u.ID) as applications,
                ROUND(COUNT(DISTINCT u.ID) * 100.0 / (
                    SELECT COUNT(*) 
                    FROM {$this->wpdb->users} u2
                    JOIN {$this->wpdb->usermeta} um2 ON u2.ID = um2.user_id
                    WHERE um2.meta_key = '{$this->wpdb->prefix}capabilities'
                    AND um2.meta_value LIKE '%\"student\"%'
                ), 2) as percentage
             FROM {$this->wpdb->users} u
             JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
             JOIN {$this->wpdb->usermeta} um_country ON u.ID = um_country.user_id
             JOIN {$this->wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
             WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
             AND um.meta_value LIKE '%\"student\"%'
             AND um_country.meta_key = 'countryid'
             GROUP BY c.countryid
             ORDER BY applications DESC
             LIMIT 10",
            ARRAY_A
        );
    }

    public function enqueue_dashboard_styles($hook) {
        if ('index.php' !== $hook) {
            return;
    }

        wp_enqueue_style(
            'wevoi-dashboard-widgets',
            WEVOI_CM_PLUGIN_URL . 'includes/css/dashboard-widgets.css',
            array(),
            WEVOI_CM_VERSION
        );
    }

    public function enqueue_dashboard_assets($hook) {
        if ('index.php' !== $hook) {
            return;
    }

    // CSS
    wp_enqueue_style(
        'wevoi-dashboard-widgets',
        WEVOI_CM_PLUGIN_URL . 'includes/css/dashboard-widgets.css',
        array(),
        WEVOI_CM_VERSION
    );

    // JavaScript
    wp_enqueue_script(
        'wevoi-dashboard-widgets',
        WEVOI_CM_PLUGIN_URL . 'includes/js/dashboard-widgets.js',
        array('jquery'),
        WEVOI_CM_VERSION,
        true
    );
}

public function add_dashboard_widgets() {
    // Mevcut students widget
    wp_add_dashboard_widget(
        'wevoi_students_overview',
        'Students Overview',
        array($this, 'render_students_widget')
    );

    // Yeni judge widget
    wp_add_dashboard_widget(
        'wevoi_judges_overview',
        'Judges Overview',
        array($this, 'render_judges_widget')
    );

    // Yeni supervisor widget
    wp_add_dashboard_widget(
        'wevoi_supervisors_overview',
        'Supervisors Overview',
        array($this, 'render_supervisors_widget')
    );
}
 
    private function get_student_stats() {
        // Toplam öğrenci sayısı
        $total_students = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT u.ID) 
             FROM {$this->wpdb->users} u 
             JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id 
             WHERE um.meta_key = '{$this->wpdb->prefix}capabilities' 
             AND um.meta_value LIKE '%\"student\"%'"
        );

        // Son 30 gün kayıt olan öğrenciler
        $last_30_days = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT u.ID) 
             FROM {$this->wpdb->users} u 
             JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id 
             WHERE um.meta_key = '{$this->wpdb->prefix}capabilities' 
             AND um.meta_value LIKE '%\"student\"%'
             AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Yarışmaya katılan öğrenciler
        $competition_participants = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$this->wpdb->prefix}wevoi_registrations"
        );

        // Madalya kazanan öğrenciler
        $medal_winners = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$this->wpdb->prefix}wevoi_registrations 
             WHERE student_medal IS NOT NULL"
        );

        return array(
            'total' => $total_students,
            'last_30_days' => $last_30_days,
            'participants' => $competition_participants,
            'medal_winners' => $medal_winners
        );
    }
    
    private function get_student_country_distribution() {
    return $this->wpdb->get_results(
        "SELECT c.countryname, COUNT(DISTINCT u.ID) as student_count 
         FROM {$this->wpdb->users} u 
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id 
         JOIN {$this->wpdb->usermeta} um_country ON u.ID = um_country.user_id 
         JOIN {$this->wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities' 
         AND um.meta_value LIKE '%\"student\"%'
         AND um_country.meta_key = 'countryid'
         GROUP BY c.countryid
         ORDER BY student_count DESC
         LIMIT 5"
    );
}

    private function get_competition_participation_stats() {
    return $this->wpdb->get_results(
        "SELECT 
            c.competition_name,
            COUNT(DISTINCT r.user_id) as participant_count,
            SUM(CASE WHEN r.student_medal IS NOT NULL THEN 1 ELSE 0 END) as medal_count
         FROM {$this->wpdb->prefix}wevoi_competitions c
         LEFT JOIN {$this->wpdb->prefix}wevoi_registrations r ON c.competition_code = r.competition_code
         GROUP BY c.competition_code
         ORDER BY participant_count DESC
         LIMIT 5"
    );
}

    private function get_medal_distribution() {
    return $this->wpdb->get_results(
        "SELECT 
            student_medal,
            COUNT(*) as count
         FROM {$this->wpdb->prefix}wevoi_registrations
         WHERE student_medal IS NOT NULL
         GROUP BY student_medal"
    );
}

public function render_judges_widget() {
    $stats = $this->get_judge_stats();
    wp_nonce_field('judges_modal_stats', 'judges_modal_nonce');
    ?>
    <div class="wevoi-dashboard-widget judges-widget">
        <div class="stats-boxes">
            <div class="stat-box">
                <span class="stat-number"><?php echo esc_html($stats['total']); ?></span>
                <span class="stat-label">Total Judges</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo esc_html($stats['active']); ?></span>
                <span class="stat-label">Active Assignments</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo esc_html($stats['evaluations']); ?></span>
                <span class="stat-label">Total Evaluations</span>
            </div>
        </div>

        <div class="stats-info">
            <p>Last 30 Days: <?php echo esc_html($stats['last_30_days']); ?> new judges</p>
        </div>

        <div class="widget-actions">
            <a href="<?php echo admin_url('admin.php?page=wevoi-competitions&tab=judges'); ?>" 
               class="button button-primary">View All Judges</a>
            <button type="button" class="button button-secondary show-judges-stats-modal">View Statistics</button>
        </div>

        <!-- Judges Stats Modal -->
        <div id="judges-stats-modal" class="wevoi-modal" style="display: none;">
             <div class="wevoi-modal-content">
        <div class="wevoi-modal-header">
            <h2>Judge Statistics</h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="wevoi-modal-body">
            <div class="stats-cards">
                <div class="stats-card" id="judge-total-applications">
                    <h3>Total Applications</h3>
                    <div class="stats-card-content">
                        <div class="stats-loading">Loading...</div>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stats-card" id="judge-type-distribution">
                    <h3>Judge Type Distribution</h3>
                    <div class="stats-card-content">
                        <div class="stats-loading">Loading...</div>
                    </div>
                </div>

                <div class="stats-card" id="judge-top-countries">
                    <h3>Top Countries</h3>
                    <div class="stats-card-content">
                        <div class="stats-loading">Loading...</div>
                    </div>
                </div>
            </div>

            <div class="stats-card" id="judge-monthly-trends">
                <h3>Monthly Trends</h3>
                <div class="stats-card-content">
                    <div class="stats-loading">Loading...</div>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
    <?php
}

public function render_supervisors_widget() {
    $stats = $this->get_supervisor_stats();
    wp_nonce_field('supervisors_modal_stats', 'supervisors_modal_nonce');
    ?>
    <div class="wevoi-dashboard-widget supervisors-widget">
        <div class="stats-boxes">
            <div class="stat-box">
                <span class="stat-number"><?php echo esc_html($stats['total']); ?></span>
                <span class="stat-label">Total Supervisors</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo esc_html($stats['active_students']); ?></span>
                <span class="stat-label">Active Students</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo esc_html($stats['video_submissions']); ?></span>
                <span class="stat-label">Video Submissions</span>
            </div>
        </div>

        <div class="stats-info">
            <p>Last 30 Days: <?php echo esc_html($stats['last_30_days']); ?> new supervisors</p>
        </div>

        <div class="widget-actions">
            <a href="<?php echo admin_url('admin.php?page=wevoi-competitions&tab=supervisors'); ?>" 
               class="button button-primary">View All Supervisors</a>
            <button type="button" class="button button-secondary show-supervisors-stats-modal">View Statistics</button>
        </div>

        <!-- Supervisors Stats Modal -->
<div id="supervisors-stats-modal" class="wevoi-modal" style="display: none;">
    <div class="wevoi-modal-content">
        <div class="wevoi-modal-header">
            <h2>Supervisor Statistics</h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="wevoi-modal-body">
            <div class="stats-cards">
                <div class="stats-card" id="supervisor-total-applications">
                    <h3>Total Applications</h3>
                    <div class="stats-card-content">
                        <div class="stats-loading">Loading...</div>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stats-card" id="supervisor-type-distribution">
                    <h3>Supervisor Type Distribution</h3>
                    <div class="stats-card-content">
                        <div class="stats-loading">Loading...</div>
                    </div>
                </div>

                <div class="stats-card" id="supervisor-top-countries">
                    <h3>Top Countries</h3>
                    <div class="stats-card-content">
                        <div class="stats-loading">Loading...</div>
                    </div>
                </div>
            </div>

            <div class="stats-card" id="supervisor-monthly-trends">
                <h3>Monthly Trends</h3>
                <div class="stats-card-content">
                    <div class="stats-loading">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
    <?php
}
    
private function get_judge_stats() {
    // Toplam jüri üyesi
    $total_judges = $this->wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$this->wpdb->users} u 
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities' 
         AND um.meta_value LIKE '%\"judge\"%'"
    );

    // Son 30 gün kayıt olan jüriler
    $last_30_days = $this->wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$this->wpdb->users} u 
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities' 
         AND um.meta_value LIKE '%\"judge\"%'
         AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );

    // Aktif görevlendirmeler
    $active_assignments = $this->wpdb->get_var(
        "SELECT COUNT(DISTINCT assignment_id) 
         FROM {$this->wpdb->prefix}judge_assignments 
         WHERE status = 'ACTIVE'"
    );

    // Toplam değerlendirmeler
    $total_evaluations = $this->wpdb->get_var(
        "SELECT COUNT(DISTINCT evaluation_id) 
         FROM {$this->wpdb->prefix}judge_evaluations"
    );

    return array(
        'total' => $total_judges,
        'last_30_days' => $last_30_days,
        'active' => $active_assignments,
        'evaluations' => $total_evaluations
    );
}

private function get_supervisor_stats() {
    // Toplam süpervizör
    $total_supervisors = $this->wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$this->wpdb->users} u 
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities' 
         AND um.meta_value LIKE '%\"supervisor\"%'"
    );

    // Son 30 gün kayıt olan süpervizörler
    $last_30_days = $this->wpdb->get_var(
        "SELECT COUNT(DISTINCT u.ID) 
         FROM {$this->wpdb->users} u 
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id 
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities' 
         AND um.meta_value LIKE '%\"supervisor\"%'
         AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );

    // Active students (students with at least one registration that has a supervisor assigned)
    $active_students = $this->wpdb->get_var(
        "SELECT COUNT(DISTINCT r.user_id) 
         FROM {$this->wpdb->prefix}wevoi_registrations r
         WHERE r.supervisor_id IS NOT NULL"
    );

    // Students with video submissions (and supervisor assigned)
    $video_submissions = $this->wpdb->get_var(
        "SELECT COUNT(DISTINCT r.user_id) 
         FROM {$this->wpdb->prefix}wevoi_registrations r
         WHERE r.supervisor_id IS NOT NULL
         AND r.youtube_link IS NOT NULL"
    );

    return array(
        'total' => $total_supervisors,
        'last_30_days' => $last_30_days,
        'active_students' => $active_students,
        'video_submissions' => $video_submissions
    );
}

public function get_judges_modal_stats() {
    check_ajax_referer('judges_modal_stats', 'nonce');

    $total_judges = $this->get_judge_stats()['total'];

    $stats = [
        'total_applications' => [
            'total' => $total_judges,
            'active' => $this->get_judge_stats()['active'],
            'evaluations' => $this->get_judge_stats()['evaluations'],
            'last_30_days' => $this->get_judge_stats()['last_30_days']
        ],
        'judge_type_distribution' => $this->get_judge_type_distribution(),
        'monthly_trends' => $this->get_monthly_judge_trends(),
        'top_countries' => $this->get_top_judge_countries()
    ];

    wp_send_json_success($stats);
}


public function get_supervisors_modal_stats() {
    check_ajax_referer('supervisors_modal_stats', 'nonce');

    $total_supervisors = $this->get_supervisor_stats()['total'];

    $stats = [
        'total_applications' => [
            'total' => $total_supervisors,
            'active_students' => $this->get_supervisor_stats()['active_students'],
            'video_submissions' => $this->get_supervisor_stats()['video_submissions'],
            'last_30_days' => $this->get_supervisor_stats()['last_30_days']
        ],
        'supervisor_type_distribution' => $this->get_supervisor_type_distribution(),
        'monthly_trends' => $this->get_monthly_supervisor_trends(),
        'top_countries' => $this->get_top_supervisor_countries()
    ];

    wp_send_json_success($stats);
}

private function get_judge_type_distribution() {
    return $this->wpdb->get_results(
        "SELECT 'Judge' as type, COUNT(*) as count 
         FROM {$this->wpdb->users} u
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
         AND um.meta_value LIKE '%\"judge\"%'",
        ARRAY_A
    );
}

private function get_monthly_judge_trends() {
    return $this->wpdb->get_results(
        "SELECT 
            DATE_FORMAT(user_registered, '%M %Y') as month,
            COUNT(*) as total
         FROM {$this->wpdb->users} u
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
         AND um.meta_value LIKE '%\"judge\"%'
         GROUP BY month
         ORDER BY user_registered DESC
         LIMIT 6",
        ARRAY_A
    );
}

private function get_top_judge_countries() {
    return $this->wpdb->get_results(
        "SELECT 
            c.countryname as country, 
            COUNT(DISTINCT u.ID) as applications,
            ROUND(COUNT(DISTINCT u.ID) * 100.0 / (
                SELECT COUNT(*) 
                FROM {$this->wpdb->users} u2
                JOIN {$this->wpdb->usermeta} um2 ON u2.ID = um2.user_id
                WHERE um2.meta_key = '{$this->wpdb->prefix}capabilities'
                AND um2.meta_value LIKE '%\"judge\"%'
            ), 2) as percentage
         FROM {$this->wpdb->users} u
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
         JOIN {$this->wpdb->usermeta} um_country ON u.ID = um_country.user_id
         JOIN {$this->wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
         AND um.meta_value LIKE '%\"judge\"%'
         AND um_country.meta_key = 'countryid'
         GROUP BY c.countryid
         ORDER BY applications DESC
         LIMIT 10",
        ARRAY_A
    );
}

private function get_supervisor_type_distribution() {
    return $this->wpdb->get_results(
        "SELECT 'Supervisor' as type, COUNT(*) as count 
         FROM {$this->wpdb->users} u
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
         AND um.meta_value LIKE '%\"supervisor\"%'",
        ARRAY_A
    );
}

private function get_monthly_supervisor_trends() {
    return $this->wpdb->get_results(
        "SELECT 
            DATE_FORMAT(user_registered, '%M %Y') as month,
            COUNT(*) as total
         FROM {$this->wpdb->users} u
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
         AND um.meta_value LIKE '%\"supervisor\"%'
         GROUP BY month
         ORDER BY user_registered DESC
         LIMIT 6",
        ARRAY_A
    );
}

private function get_top_supervisor_countries() {
    return $this->wpdb->get_results(
        "SELECT 
            c.countryname as country, 
            COUNT(DISTINCT u.ID) as applications,
            ROUND(COUNT(DISTINCT u.ID) * 100.0 / (
                SELECT COUNT(*) 
                FROM {$this->wpdb->users} u2
                JOIN {$this->wpdb->usermeta} um2 ON u2.ID = um2.user_id
                WHERE um2.meta_key = '{$this->wpdb->prefix}capabilities'
                AND um2.meta_value LIKE '%\"supervisor\"%'
            ), 2) as percentage
         FROM {$this->wpdb->users} u
         JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
         JOIN {$this->wpdb->usermeta} um_country ON u.ID = um_country.user_id
         JOIN {$this->wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
         WHERE um.meta_key = '{$this->wpdb->prefix}capabilities'
         AND um.meta_value LIKE '%\"supervisor\"%'
         AND um_country.meta_key = 'countryid'
         GROUP BY c.countryid
         ORDER BY applications DESC
         LIMIT 10",
        ARRAY_A
    );
}

    
}
