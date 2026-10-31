<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;

// Stripe fee config (save on POST)
if (isset($_POST['stripe_fee_nonce']) && wp_verify_nonce($_POST['stripe_fee_nonce'], 'save_stripe_fee')) {
    $pct = isset($_POST['wevoi_stripe_fee_percent']) ? floatval($_POST['wevoi_stripe_fee_percent']) : 2.9;
    $fixed = isset($_POST['wevoi_stripe_fee_fixed']) ? floatval($_POST['wevoi_stripe_fee_fixed']) : 0.20;
    update_option('wevoi_stripe_fee_percent', $pct);
    update_option('wevoi_stripe_fee_fixed', $fixed);
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Stripe fee settings saved.', 'wevoi-competition-manager') . '</p></div>';
}

$stripe_pct = get_option('wevoi_stripe_fee_percent', '2.9');
$stripe_fixed = get_option('wevoi_stripe_fee_fixed', '0.20');

$rates = $wpdb->get_results(
    "SELECT cr.*, s.season_name, s.season_abv, s.min_students_commission FROM {$p}wevoi_commission_rates cr
     LEFT JOIN {$p}wevoi_seasons s ON cr.season_id = s.season_id
     ORDER BY cr.season_id DESC, cr.role ASC",
    ARRAY_A
);
$seasons = $wpdb->get_results("SELECT season_id, season_name, season_abv FROM {$p}wevoi_seasons ORDER BY season_id DESC", ARRAY_A);
$message = isset($_GET['message']) ? intval($_GET['message']) : 0;

if ($message === 1) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Commission rate saved.', 'wevoi-competition-manager') . '</p></div>';
} elseif ($message === 2) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Commission rate updated.', 'wevoi-competition-manager') . '</p></div>';
} elseif ($message === 3) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Commission rate deleted.', 'wevoi-competition-manager') . '</p></div>';
}
?>

<div class="wrap">
    <h1><?php _e('Rates', 'wevoi-competition-manager'); ?></h1>

    <div class="card" style="max-width: 400px; margin-bottom: 20px;">
        <h2 class="title"><?php _e('Stripe Fee (for commission calculation)', 'wevoi-competition-manager'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('save_stripe_fee', 'stripe_fee_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="wevoi_stripe_fee_percent"><?php _e('Percent (%)', 'wevoi-competition-manager'); ?></label></th>
                    <td><input type="number" name="wevoi_stripe_fee_percent" id="wevoi_stripe_fee_percent" value="<?php echo esc_attr($stripe_pct); ?>" step="0.01" min="0" class="small-text"> %</td>
                </tr>
                <tr>
                    <th><label for="wevoi_stripe_fee_fixed"><?php _e('Fixed (e.g. GBP)', 'wevoi-competition-manager'); ?></label></th>
                    <td><input type="number" name="wevoi_stripe_fee_fixed" id="wevoi_stripe_fee_fixed" value="<?php echo esc_attr($stripe_fixed); ?>" step="0.01" min="0" class="small-text"></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Stripe Fee', 'wevoi-competition-manager'); ?>"></p>
        </form>
    </div>

    <h2 class="title"><?php _e('Rates by Season', 'wevoi-competition-manager'); ?></h2>
    <a href="?page=wevoi-competitions&tab=commission-rates&action=add" class="page-title-action"><?php _e('Add Rate', 'wevoi-competition-manager'); ?></a>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Season', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Role', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Min. students', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Commission %', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Actions', 'wevoi-competition-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rates)): ?>
                <?php foreach ($rates as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r['season_name']); ?></td>
                        <td><?php echo esc_html(ucfirst($r['role'])); ?></td>
                        <td><?php echo isset($r['min_students_commission']) && $r['min_students_commission'] !== '' ? esc_html((int) $r['min_students_commission']) : '—'; ?></td>
                        <td><strong><?php echo esc_html(number_format($r['commission_percent'], 2)); ?>%</strong></td>
                        <td>
                            <a href="?page=wevoi-competitions&tab=commission-rates&action=edit&id=<?php echo (int) $r['id']; ?>" class="button button-small"><?php _e('Edit', 'wevoi-competition-manager'); ?></a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wevoi-competitions&tab=commission-rates&action=delete&id=' . (int) $r['id']), 'delete_commission_rate_' . (int) $r['id'])); ?>"
                               class="button button-small button-link-delete"
                               onclick="return confirm('<?php echo esc_attr(__('Delete this rate?', 'wevoi-competition-manager')); ?>');"><?php _e('Delete', 'wevoi-competition-manager'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5"><?php _e('No commission rates. Add seasons first, then add rates per season (supervisor / agency).', 'wevoi-competition-manager'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
