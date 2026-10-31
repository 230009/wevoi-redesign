<?php
// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Total supervisors
$total_supervisors = $wpdb->get_var(
    "SELECT COUNT(DISTINCT u.ID) 
     FROM {$wpdb->users} u 
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
     WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
     AND um.meta_value LIKE '%\"supervisor\"%'"
);

// Last 30 days registrations
$last_30_days = $wpdb->get_var(
    "SELECT COUNT(DISTINCT u.ID) 
     FROM {$wpdb->users} u 
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
     WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
     AND um.meta_value LIKE '%\"supervisor\"%'
     AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);

// Active supervisors (who have at least one registration with video submission)
$active_supervisors = $wpdb->get_var(
    "SELECT COUNT(DISTINCT r.supervisor_id) 
     FROM {$wpdb->prefix}wevoi_registrations r
     WHERE r.supervisor_id IS NOT NULL
     AND r.youtube_link IS NOT NULL"
);

// Monthly trends (supervisors and their assigned students from registrations)
$monthly_trends = $wpdb->get_results(
    "SELECT 
        DATE_FORMAT(u.user_registered, '%Y-%m') as month,
        COUNT(DISTINCT u.ID) as new_supervisors,
        COUNT(DISTINCT r.user_id) as active_students
     FROM {$wpdb->users} u
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
     LEFT JOIN {$wpdb->prefix}wevoi_registrations r ON r.supervisor_id = u.ID
     WHERE um.meta_key = '{$wpdb->prefix}capabilities'
     AND um.meta_value LIKE '%\"supervisor\"%'
     GROUP BY month
     ORDER BY month DESC
     LIMIT 12"
);

// Country distribution
$country_distribution = $wpdb->get_results(
    "SELECT 
        c.countryname,
        COUNT(DISTINCT u.ID) as count,
        ROUND(COUNT(DISTINCT u.ID) * 100.0 / $total_supervisors, 1) as percentage
     FROM {$wpdb->users} u
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
     JOIN {$wpdb->usermeta} um_country ON u.ID = um_country.user_id
     JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
     WHERE um.meta_key = '{$wpdb->prefix}capabilities'
     AND um.meta_value LIKE '%\"supervisor\"%'
     AND um_country.meta_key = 'countryid'
     GROUP BY c.countryid
     ORDER BY count DESC
     LIMIT 10"
);

// School distribution (supervisors and students linked via wevoi_registrations.supervisor_id)
$school_distribution = $wpdb->get_results(
    "SELECT 
        s.schoolname,
        COUNT(DISTINCT u.ID) as supervisor_count,
        COUNT(DISTINCT r.user_id) as student_count
     FROM {$wpdb->users} u
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
     JOIN {$wpdb->usermeta} um_school ON u.ID = um_school.user_id
     JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = um_school.meta_value
     LEFT JOIN {$wpdb->prefix}wevoi_registrations r ON r.supervisor_id = u.ID
     WHERE um.meta_key = '{$wpdb->prefix}capabilities'
     AND um.meta_value LIKE '%\"supervisor\"%'
     AND um_school.meta_key = 'schoolid'
     GROUP BY s.schoolid
     ORDER BY student_count DESC
     LIMIT 10"
);
?>

<div class="wrap">
    <h1><?php echo esc_html__('Supervisor Statistics', 'wevoi-competition-manager'); ?></h1>

    <!-- Overview Statistics -->
    <div class="wevoi-stats-section">
        <div class="wevoi-stats-grid">
            <div class="wevoi-stats-card total">
                <div class="stats-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stats-content">
                    <h3><?php echo number_format_i18n($total_supervisors); ?></h3>
                    <p><?php _e('Total Supervisors', 'wevoi-competition-manager'); ?></p>
                </div>
            </div>

            <div class="wevoi-stats-card active">
                <div class="stats-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stats-content">
                    <h3><?php echo number_format_i18n($active_supervisors); ?></h3>
                    <p><?php _e('Active Supervisors', 'wevoi-competition-manager'); ?></p>
                </div>
            </div>

            <div class="wevoi-stats-card pending">
                <div class="stats-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stats-content">
                    <h3><?php echo number_format_i18n($last_30_days); ?></h3>
                    <p><?php _e('Last 30 Days', 'wevoi-competition-manager'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Trends and Distribution -->
    <div class="wevoi-stats-row">
        <!-- Monthly Trends -->
        <div class="wevoi-stats-column">
            <div class="wevoi-stats-card">
                <h2><?php _e('Monthly Trends', 'wevoi-competition-manager'); ?></h2>
                <table class="wevoi-stats-table">
                    <thead>
                        <tr>
                            <th><?php _e('Month', 'wevoi-competition-manager'); ?></th>
                            <th><?php _e('New Supervisors', 'wevoi-competition-manager'); ?></th>
                            <th><?php _e('Active Students', 'wevoi-competition-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_trends as $trend): ?>
                            <tr>
                                <td><?php echo esc_html(date('F Y', strtotime($trend->month . '-01'))); ?></td>
                                <td><?php echo number_format_i18n($trend->new_supervisors); ?></td>
                                <td><?php echo number_format_i18n($trend->active_students); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Country Distribution -->
        <div class="wevoi-stats-column">
            <div class="wevoi-stats-card">
                <h2><?php _e('Top 10 Countries', 'wevoi-competition-manager'); ?></h2>
                <table class="wevoi-stats-table">
                    <thead>
                        <tr>
                            <th><?php _e('Country', 'wevoi-competition-manager'); ?></th>
                            <th><?php _e('Supervisors', 'wevoi-competition-manager'); ?></th>
                            <th><?php _e('Percentage', 'wevoi-competition-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($country_distribution as $country): ?>
                            <tr>
                                <td><?php echo esc_html($country->countryname); ?></td>
                                <td><?php echo number_format_i18n($country->count); ?></td>
                                <td><?php echo number_format_i18n($country->percentage); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- School Statistics -->
    <div class="wevoi-stats-section">
        <div class="wevoi-stats-card">
            <h2><?php _e('Top 10 Schools', 'wevoi-competition-manager'); ?></h2>
            <table class="wevoi-stats-table">
                <thead>
                    <tr>
                        <th><?php _e('School', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Supervisors', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Active Students', 'wevoi-competition-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($school_distribution as $school): ?>
                        <tr>
                            <td><?php echo esc_html($school->schoolname); ?></td>
                            <td><?php echo number_format_i18n($school->supervisor_count); ?></td>
                            <td><?php echo number_format_i18n($school->student_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>