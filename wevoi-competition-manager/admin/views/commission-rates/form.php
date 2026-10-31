<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$rate = null;
$is_edit = isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id']);

if ($is_edit) {
    $rate = $wpdb->get_row($wpdb->prepare(
        "SELECT cr.*, s.season_name, s.season_abv FROM {$p}wevoi_commission_rates cr
         LEFT JOIN {$p}wevoi_seasons s ON cr.season_id = s.season_id
         WHERE cr.id = %d",
        (int) $_GET['id']
    ), ARRAY_A);
    if (!$rate) {
        wp_die(__('Commission rate not found.', 'wevoi-competition-manager'));
    }
}

$seasons = $wpdb->get_results("SELECT season_id, season_name, season_abv FROM {$p}wevoi_seasons ORDER BY season_id DESC", ARRAY_A);
?>

<div class="wrap">
    <h1><?php echo $is_edit ? __('Edit Commission Rate', 'wevoi-competition-manager') : __('Add Commission Rate', 'wevoi-competition-manager'); ?></h1>
    <form method="post" action="">
        <?php wp_nonce_field($is_edit ? 'update_commission_rate_' . (int) $rate['id'] : 'add_commission_rate', 'rate_nonce'); ?>
        <input type="hidden" name="rate_action" value="<?php echo $is_edit ? 'update' : 'add'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="rate_id" value="<?php echo (int) $rate['id']; ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="season_id"><?php _e('Season', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                <td>
                    <select name="season_id" id="season_id" required>
                        <option value=""><?php _e('Select season', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($seasons as $s): ?>
                            <option value="<?php echo (int) $s['season_id']; ?>"
                                <?php selected($is_edit ? $rate['season_id'] : '', $s['season_id']); ?>>
                                <?php echo esc_html(isset($s['season_name']) ? $s['season_name'] : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="role"><?php _e('Role', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                <td>
                    <select name="role" id="role" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                        <option value=""><?php _e('Select role', 'wevoi-competition-manager'); ?></option>
                        <option value="supervisor" <?php selected($is_edit ? $rate['role'] : '', 'supervisor'); ?>><?php _e('Supervisor', 'wevoi-competition-manager'); ?></option>
                        <option value="agency" <?php selected($is_edit ? $rate['role'] : '', 'agency'); ?>><?php _e('Agency', 'wevoi-competition-manager'); ?></option>
                    </select>
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="role" value="<?php echo esc_attr($rate['role']); ?>">
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="commission_percent"><?php _e('Commission (%)', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="number" name="commission_percent" id="commission_percent" step="0.01" min="0" max="100" required
                           value="<?php echo $is_edit ? esc_attr($rate['commission_percent']) : ''; ?>"> %
                </td>
            </tr>
        </table>
        <p class="submit">
            <?php submit_button($is_edit ? __('Update', 'wevoi-competition-manager') : __('Add', 'wevoi-competition-manager'), 'primary', 'submit', false); ?>
            <a href="?page=wevoi-competitions&tab=commission-rates" class="button"><?php _e('Cancel', 'wevoi-competition-manager'); ?></a>
        </p>
    </form>
</div>

<style>.required{color:#d63638;}</style>
