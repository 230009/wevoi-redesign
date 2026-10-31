<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

// Check registration ID parameter
if (!isset($registration_id)) {
    wp_die(__('Registration ID is required.', 'wevoi-competition-manager'));
}

// Get registration details
global $wpdb;

$registration = $wpdb->get_row($wpdb->prepare(
    "SELECT r.*, 
            u.display_name, u.user_email,
            c.competition_name, c.competition_status,
            co.countryname as payer_country
     FROM {$wpdb->prefix}wevoi_registrations r
     LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
     LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
     LEFT JOIN {$wpdb->prefix}wevoi_countries co ON r.payer_countryid = co.countryid
     WHERE r.register_id = %d",
    $registration_id
));

if (!$registration) {
    wp_die(__('Registration not found.', 'wevoi-competition-manager'));
}
?>

<div class="registration-details-wrapper">
    <!-- Student Information -->
    <div class="section">
        <h3><?php _e('Student Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Name:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html(wevoi_proper_name($registration->display_name)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Email:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($registration->user_email); ?></td>
            </tr>
            <tr>
                <th><?php _e('Registration Date:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration->registration_date)); ?></td>
            </tr>
        </table>
    </div>

    <!-- Competition Information -->
    <div class="section">
        <h3><?php _e('Competition Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Competition:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php echo esc_html($registration->competition_name); ?>
                    <div class="row-actions">
                        <span class="code">Code: <?php echo esc_html($registration->competition_code); ?></span>
                    </div>
                </td>
            </tr>
            <tr>
                <th><?php _e('YouTube Link:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php if ($registration->youtube_link): ?>
                        <a href="<?php echo esc_url($registration->youtube_link); ?>" target="_blank" class="youtube-link">
                            <?php _e('Watch Video', 'wevoi-competition-manager'); ?>
                        </a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Application Status:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <span class="status-badge status-<?php echo strtolower($registration->application_status); ?>">
                        <?php echo esc_html($registration->application_status); ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Payment Information -->
    <div class="section">
        <h3><?php _e('Payment Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Payment Status:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php
                    $paid_badge = strtoupper((string) $registration->paid_status) === 'PRE_REGISTERED'
                        ? __('Pre-registered', 'wevoi-competition-manager')
                        : $registration->paid_status;
                    ?>
                    <span class="status-badge status-<?php echo esc_attr(strtolower($registration->paid_status)); ?>">
                        <?php echo esc_html($paid_badge); ?>
                    </span>
                </td>
            </tr>
            <?php if ($registration->price > 0): ?>
            <tr>
                <th><?php _e('Price:', 'wevoi-competition-manager'); ?></th>
                <td>£<?php echo number_format($registration->price, 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($registration->payer_name): ?>
            <tr>
                <th><?php _e('Payer Name:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($registration->payer_name); ?></td>
            </tr>
            <tr>
                <th><?php _e('Payer Country:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($registration->payer_country); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($registration->coupon_code): ?>
            <tr>
                <th><?php _e('Coupon Code:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($registration->coupon_code); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($registration->payment_intent_id): ?>
            <tr>
                <th><?php _e('Payment Intent ID:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($registration->payment_intent_id); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($registration->refund_amount > 0): ?>
            <tr>
                <th><?php _e('Refund Amount:', 'wevoi-competition-manager'); ?></th>
                <td>£<?php echo number_format($registration->refund_amount, 2); ?></td>
            </tr>
            <tr>
                <th><?php _e('Refund Date:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo date_i18n(get_option('date_format'), strtotime($registration->refund_date)); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Results Information -->
    <?php if ($registration->student_medal || $registration->student_point || $registration->student_rank): ?>
    <div class="section">
        <h3><?php _e('Results Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <?php if ($registration->student_medal): ?>
            <tr>
                <th><?php _e('Medal:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <span class="medal-badge medal-<?php echo strtolower($registration->student_medal); ?>">
                        <span class="dashicons dashicons-awards"></span>
                        <?php echo esc_html($registration->student_medal); ?>
                    </span>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($registration->student_point): ?>
            <tr>
                <th><?php _e('Points:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo number_format($registration->student_point, 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($registration->student_rank): ?>
            <tr>
                <th><?php _e('Rank:', 'wevoi-competition-manager'); ?></th>
                <td>#<?php echo intval($registration->student_rank); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Certificates Information -->
    <?php if ($registration->student_certficates_folder || $registration->supervisor_certficates_folder): ?>
    <div class="section">
        <h3><?php _e('Certificates Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <?php if ($registration->student_certficates_folder): ?>
            <tr>
                <th><?php _e('Student Certificate:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <a href="<?php echo esc_url($registration->student_certficates_folder); ?>" 
                       target="_blank" 
                       class="certificate-link">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php _e('View Certificate', 'wevoi-competition-manager'); ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($registration->supervisor_certficates_folder): ?>
            <tr>
                <th><?php _e('Supervisor Certificate:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <a href="<?php echo esc_url($registration->supervisor_certficates_folder); ?>" 
                       target="_blank" 
                       class="certificate-link">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php _e('View Certificate', 'wevoi-competition-manager'); ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Prize Information -->
    <?php if ($registration->claimed_summit_coupon_code || $registration->claimed_prize_amount): ?>
    <div class="section">
        <h3><?php _e('Prize Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <?php if ($registration->claimed_summit_coupon_code): ?>
            <tr>
                <th><?php _e('Summit Coupon Code:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($registration->claimed_summit_coupon_code); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($registration->claimed_prize_amount): ?>
            <tr>
                <th><?php _e('Prize Amount:', 'wevoi-competition-manager'); ?></th>
                <td>£<?php echo number_format($registration->claimed_prize_amount, 2); ?></td>
            </tr>
            <tr>
                <th><?php _e('Prize Payment Status:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <span class="status-badge status-<?php echo strtolower($registration->claimed_prize_paid_status); ?>">
                        <?php echo esc_html($registration->claimed_prize_paid_status); ?>
                    </span>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
/* Section stili */
.section {
    background: #fff;
    border: 1px solid #e2e4e7;
    border-radius: 6px;
    margin-bottom: 20px;
}

.section h3 {
    margin: 0;
    padding: 12px 15px;
    background: #f0f0f1;
    border-bottom: 1px solid #e2e4e7;
    font-size: 14px;
}

.section table {
    margin: 0;
}

/* Badge stilleri */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    min-width: 80px;
    text-align: center;
}

.status-pre_registered {
    background: #f3e5f5;
    color: #6a1b9a;
}

/* Link stilleri */
.youtube-link,
.certificate-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    transition: all 0.2s ease;
}

.youtube-link {
    background: #ff0000;
    color: #ffffff;
}

.certificate-link {
    background: #f0f6fc;
    border: 1px solid #c5d9ed;
    color: #2271b1;
}

.youtube-link:hover {
    background: #cc0000;
    color: #ffffff;
}

.certificate-link:hover {
    background: #2271b1;
    color: #ffffff;
}

.dashicons {
    margin-right: 5px;
}
</style>