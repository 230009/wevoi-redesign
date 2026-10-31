<?php
// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// General statistics
$total_students = $wpdb->get_var(
    "SELECT COUNT(DISTINCT u.ID) 
     FROM {$wpdb->users} u 
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
     WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
     AND um.meta_value LIKE '%\"student\"%'"
);

// Last 30 days registrations
$last_30_days = $wpdb->get_var(
    "SELECT COUNT(DISTINCT u.ID) 
     FROM {$wpdb->users} u 
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
     WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
     AND um.meta_value LIKE '%\"student\"%'
     AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);

// Active participants
$active_participants = $wpdb->get_var(
    "SELECT COUNT(DISTINCT user_id) 
     FROM {$wpdb->prefix}wevoi_registrations"
);

// Medal statistics
$medal_stats = $wpdb->get_results(
    "SELECT student_medal, COUNT(*) as count
     FROM {$wpdb->prefix}wevoi_registrations 
     WHERE student_medal IS NOT NULL 
     GROUP BY student_medal"
);

// Monthly trends
$monthly_trends = $wpdb->get_results(
    "SELECT 
        DATE_FORMAT(user_registered, '%Y-%m') as month,
        COUNT(*) as count,
        COUNT(DISTINCT r.user_id) as active_count
     FROM {$wpdb->users} u
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
     LEFT JOIN {$wpdb->prefix}wevoi_registrations r ON u.ID = r.user_id
     WHERE um.meta_key = '{$wpdb->prefix}capabilities'
     AND um.meta_value LIKE '%\"student\"%'
     GROUP BY month
     ORDER BY month DESC
     LIMIT 12"
);

// Country distribution
$country_distribution = $wpdb->get_results(
    "SELECT 
        c.countryname,
        COUNT(DISTINCT u.ID) as count,
        ROUND(COUNT(DISTINCT u.ID) * 100.0 / $total_students, 1) as percentage
     FROM {$wpdb->users} u
     JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
     JOIN {$wpdb->usermeta} um_country ON u.ID = um_country.user_id
     JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
     WHERE um.meta_key = '{$wpdb->prefix}capabilities'
     AND um.meta_value LIKE '%\"student\"%'
     AND um_country.meta_key = 'countryid'
     GROUP BY c.countryid
     ORDER BY count DESC
     LIMIT 10"
);

?>

<div class="wrap">
    <h1><?php echo esc_html__('Student Statistics', 'wevoi-competition-manager'); ?></h1>

    <!-- Overview Statistics -->
    <div class="wevoi-stats-section">
        <div class="wevoi-stats-grid">
            <div class="wevoi-stats-card total">
                <div class="stats-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stats-content">
                    <h3><?php echo number_format_i18n($total_students); ?></h3>
                    <p><?php _e('Total Students', 'wevoi-competition-manager'); ?></p>
                </div>
            </div>

            <div class="wevoi-stats-card active">
                <div class="stats-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stats-content">
                    <h3><?php echo number_format_i18n($active_participants); ?></h3>
                    <p><?php _e('Active Participants', 'wevoi-competition-manager'); ?></p>
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

    <!-- Medal Distribution -->
    <div class="wevoi-stats-section">
        <div class="wevoi-stats-card">
            <h2><?php _e('Medal Distribution', 'wevoi-competition-manager'); ?></h2>
            <div class="medal-distribution">
                <?php
                $medal_classes = [
                    'GOLD' => 'medal-gold',
                    'SILVER' => 'medal-silver',
                    'BRONZE' => 'medal-bronze',
                    'SUCCESS' => 'medal-success'
                ];
                
                foreach ($medal_stats as $medal): ?>
                    <div class="medal-item <?php echo esc_attr($medal_classes[$medal->student_medal]); ?>">
                        <span class="medal-type"><?php echo esc_html($medal->student_medal); ?></span>
                        <span class="medal-count"><?php echo number_format_i18n($medal->count); ?></span>
                    </div>
                <?php endforeach; ?>
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
                            <th><?php _e('New Students', 'wevoi-competition-manager'); ?></th>
                            <th><?php _e('Active', 'wevoi-competition-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_trends as $trend): ?>
                            <tr>
                                <td><?php echo esc_html(date('F Y', strtotime($trend->month . '-01'))); ?></td>
                                <td><?php echo number_format_i18n($trend->count); ?></td>
                                <td><?php echo number_format_i18n($trend->active_count); ?></td>
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
                            <th><?php _e('Students', 'wevoi-competition-manager'); ?></th>
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
</div>