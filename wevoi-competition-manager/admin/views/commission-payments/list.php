<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;

// Run commission for a specific competition (manual trigger)
if (isset($_POST['run_commission_nonce']) && wp_verify_nonce($_POST['run_commission_nonce'], 'wevoi_run_commission') && current_user_can('manage_options')) {
    $competition_code = isset($_POST['competition_code']) ? (int) $_POST['competition_code'] : 0;
    if ($competition_code > 0) {
        if (!class_exists('Wevoi_Commission')) {
            require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-commission.php';
        }
        $commission = new Wevoi_Commission();
        $result = $commission->run_for_competition($competition_code);
        $redirect = add_query_arg(array(
            'page' => 'wevoi-competitions',
            'tab' => 'commission-payments',
            'run_commission' => $result['ok'] ? 1 : 0,
            'created_count' => isset($result['created_count']) ? (int) $result['created_count'] : 0,
            'run_message' => isset($result['message']) ? urlencode($result['message']) : '',
        ), admin_url('admin.php'));
        wp_redirect($redirect);
        exit;
    }
}

$run_commission_msg = isset($_GET['run_commission']) ? (int) $_GET['run_commission'] : 0;
$run_created = isset($_GET['created_count']) ? (int) $_GET['created_count'] : 0;
$run_message = isset($_GET['run_message']) ? sanitize_text_field($_GET['run_message']) : '';

$payments = $wpdb->get_results(
    "SELECT cp.*, c.competition_name, s.season_name, s.season_abv,
            u.user_email, u.display_name,
            um_f.meta_value as first_name, um_l.meta_value as last_name
     FROM {$p}wevoi_commission_payments cp
     LEFT JOIN {$p}wevoi_competitions c ON cp.competition_code = c.competition_code
     LEFT JOIN {$p}wevoi_seasons s ON cp.season_id = s.season_id
     LEFT JOIN {$p}users u ON cp.user_id = u.ID
     LEFT JOIN {$p}usermeta um_f ON u.ID = um_f.user_id AND um_f.meta_key = 'first_name'
     LEFT JOIN {$p}usermeta um_l ON u.ID = um_l.user_id AND um_l.meta_key = 'last_name'
     ORDER BY cp.created_at DESC",
    ARRAY_A
);

$has_bank = array();
foreach ($payments as $pay) {
    $has_bank[$pay['user_id']] = Wevoi_Commission::user_has_bank_details($pay['user_id']);
}

// COMPLETED Spark/Reload/Summit competitions for "Run commission" dropdown
$comp_columns = $wpdb->get_col("SHOW COLUMNS FROM {$p}wevoi_competitions");
$has_season = in_array('season_id', $comp_columns, true);
$completion_competitions = $wpdb->get_results(
    "SELECT c.competition_code, c.competition_name, c.competition_status" . ($has_season ? ", c.season_id, s.season_name" : "") . "
     FROM {$p}wevoi_competitions c
     " . ($has_season ? " LEFT JOIN {$p}wevoi_seasons s ON c.season_id = s.season_id" : "") . "
     WHERE c.competition_status = 'COMPLETED'
     AND (LOWER(c.competition_name) LIKE '%spark%' OR LOWER(c.competition_name) LIKE '%reload%' OR LOWER(c.competition_name) LIKE '%summit%')
     ORDER BY c.competition_date DESC",
    ARRAY_A
);
?>

<div class="wrap">
    <h1><?php _e('Commission Payments', 'wevoi-competition-manager'); ?></h1>
    <p class="description"><?php _e('Aggregated commission per supervisor/agency per competition. Calculated automatically when a Spark/Reload/Summit competition is set to COMPLETED.', 'wevoi-competition-manager'); ?></p>

    <?php if ($run_commission_msg !== 0): ?>
        <div class="notice notice-<?php echo $run_commission_msg === 1 ? 'success' : 'warning'; ?> is-dismissible">
            <p>
                <?php if ($run_commission_msg === 1): ?>
                    <?php printf(__('Commission run completed. Message: %s. New rows created: %d.', 'wevoi-competition-manager'), esc_html($run_message), $run_created); ?>
                <?php else: ?>
                    <?php printf(__('Commission run did not complete: %s', 'wevoi-competition-manager'), esc_html($run_message)); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($completion_competitions) && current_user_can('manage_options')): ?>
    <div class="card" style="max-width: 520px; margin-bottom: 20px;">
        <h2 class="title"><?php _e('Run commission for a competition', 'wevoi-competition-manager'); ?></h2>
        <p class="description"><?php _e('Select a COMPLETED Spark/Reload/Summit competition to calculate and write commission rows now (e.g. for season_id=9 Spark).', 'wevoi-competition-manager'); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field('wevoi_run_commission', 'run_commission_nonce'); ?>
            <p>
                <label for="competition_code"><?php _e('Competition', 'wevoi-competition-manager'); ?></label><br>
                <select name="competition_code" id="competition_code" class="regular-text" style="max-width: 100%;">
                    <?php foreach ($completion_competitions as $cc): ?>
                        <option value="<?php echo (int) $cc['competition_code']; ?>">
                            <?php echo esc_html($cc['competition_name'] . ' (Code: ' . $cc['competition_code'] . ')' . ($has_season && !empty($cc['season_name']) ? ' — ' . $cc['season_name'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <input type="submit" class="button button-primary" value="<?php esc_attr_e('Calculate commission now', 'wevoi-competition-manager'); ?>">
            </p>
        </form>
    </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Beneficiary', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Email', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Role', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Season', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Total Commission', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Bank Details', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Status', 'wevoi-competition-manager'); ?></th>
                <th><?php _e('Actions', 'wevoi-competition-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($payments)): ?>
                <?php foreach ($payments as $pay): ?>
                    <?php
                    $name = wevoi_format_display_name(
                        isset($pay['first_name']) ? $pay['first_name'] : '',
                        isset($pay['last_name']) ? $pay['last_name'] : '',
                        isset($pay['display_name']) ? $pay['display_name'] : ''
                    );
                    $bank_ok = isset($has_bank[$pay['user_id']]) && $has_bank[$pay['user_id']];
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($name); ?></strong></td>
                        <td><?php echo esc_html($pay['user_email']); ?></td>
                        <td><?php echo esc_html(ucfirst($pay['role'])); ?></td>
                        <td><?php echo esc_html($pay['competition_name'] ?: $pay['competition_code']); ?></td>
                        <td><?php echo esc_html($pay['season_name']); ?></td>
                        <td><strong>£<?php echo number_format($pay['total_commission_amount'], 2); ?></strong></td>
                        <td>
                            <?php if ($bank_ok): ?>
                                <span style="color:#00a32a;">✓ <?php _e('Yes', 'wevoi-competition-manager'); ?></span>
                            <?php else: ?>
                                <span style="color:#d63638;">— <?php _e('No', 'wevoi-competition-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr(strtolower($pay['status'])); ?>"><?php echo esc_html($pay['status']); ?></span>
                        </td>
                        <td>
                            <button type="button" class="button button-small wevoi-commission-detail" data-payment-id="<?php echo (int) $pay['id']; ?>">
                                <?php _e('Detail', 'wevoi-competition-manager'); ?>
                            </button>
                            <button type="button" class="button button-small wevoi-commission-bank" data-user-id="<?php echo (int) $pay['user_id']; ?>">
                                <?php _e('Bank Accounts', 'wevoi-competition-manager'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9"><?php _e('No commission payments yet. Complete a Spark, Reload or Summit competition to generate them.', 'wevoi-competition-manager'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal for commission detail -->
<div id="wevoi-commission-detail-modal" class="wevoi-modal" style="display:none;">
    <div class="wevoi-modal-backdrop"></div>
    <div class="wevoi-modal-content">
        <button type="button" class="wevoi-modal-close" aria-label="Close">&times;</button>
        <h2><?php _e('Commission breakdown', 'wevoi-competition-manager'); ?></h2>
        <div id="wevoi-commission-detail-body">
            <p><?php _e('Loading…', 'wevoi-competition-manager'); ?></p>
        </div>
    </div>
</div>

<!-- Modal for beneficiary bank details -->
<div id="wevoi-commission-bank-modal" class="wevoi-modal" style="display:none;">
    <div class="wevoi-modal-backdrop"></div>
    <div class="wevoi-modal-content">
        <button type="button" class="wevoi-modal-close" aria-label="Close">&times;</button>
        <h2><?php _e('Bank details for commission payments', 'wevoi-competition-manager'); ?></h2>
        <div id="wevoi-commission-bank-body">
            <p><?php _e('Loading…', 'wevoi-competition-manager'); ?></p>
        </div>
    </div>
</div>

<style>
.wevoi-modal { position: fixed; left: 0; top: 0; width: 100%; height: 100%; z-index: 100000; }
.wevoi-modal-backdrop { position: absolute; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.wevoi-modal-content { position: relative; max-width: 800px; margin: 5% auto; background: #fff; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); border-radius: 4px; max-height: 80vh; overflow: auto; }
.wevoi-modal-close { position: absolute; right: 15px; top: 10px; font-size: 24px; background: none; border: none; cursor: pointer; color: #666; }
.wevoi-modal-close:hover { color: #000; }
#wevoi-commission-detail-body table { width: 100%; margin-top: 10px; }
#wevoi-commission-detail-body th, #wevoi-commission-detail-body td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
#wevoi-commission-bank-body .wevoi-bank-mono-cell { font-family: Consolas, Monaco, monospace; font-size: 12px; word-break: break-word; }
.wevoi-commission-bank { margin-left: 6px; }
.status-pending { background: #f0b849; color: #000; padding: 2px 8px; border-radius: 3px; }
.status-paid { background: #00a32a; color: #fff; padding: 2px 8px; border-radius: 3px; }
</style>

<script>
jQuery(function($) {
    $('.wevoi-commission-detail').on('click', function() {
        var id = $(this).data('payment-id');
        $('#wevoi-commission-detail-modal').show();
        $('#wevoi-commission-detail-body').html('<p><?php echo esc_js(__('Loading…', 'wevoi-competition-manager')); ?></p>');
        $.post(ajaxurl, {
            action: 'wevoi_commission_payment_detail',
            nonce: '<?php echo esc_js(wp_create_nonce('wevoi_commission_detail')); ?>',
            payment_id: id
        }, function(res) {
            if (res.success && res.data && res.data.html) {
                $('#wevoi-commission-detail-body').html(res.data.html);
            } else {
                $('#wevoi-commission-detail-body').html('<p class="notice notice-error">' + (res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error loading detail.', 'wevoi-competition-manager')); ?>') + '</p>');
            }
        }).fail(function() {
            $('#wevoi-commission-detail-body').html('<p class="notice notice-error"><?php echo esc_js(__('Request failed.', 'wevoi-competition-manager')); ?></p>');
        });
    });
    $('#wevoi-commission-detail-modal .wevoi-modal-close, #wevoi-commission-detail-modal .wevoi-modal-backdrop').on('click', function() {
        $('#wevoi-commission-detail-modal').hide();
    });

    $('.wevoi-commission-bank').on('click', function() {
        var uid = $(this).data('user-id');
        $('#wevoi-commission-bank-modal').show();
        $('#wevoi-commission-bank-body').html('<p><?php echo esc_js(__('Loading…', 'wevoi-competition-manager')); ?></p>');
        $.post(ajaxurl, {
            action: 'wevoi_commission_beneficiary_bank',
            nonce: '<?php echo esc_js(wp_create_nonce('wevoi_commission_bank_modal')); ?>',
            user_id: uid
        }, function(res) {
            if (res.success && res.data && res.data.html) {
                $('#wevoi-commission-bank-body').html(res.data.html);
            } else {
                $('#wevoi-commission-bank-body').html('<p class="notice notice-error">' + (res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error loading bank details.', 'wevoi-competition-manager')); ?>') + '</p>');
            }
        }).fail(function() {
            $('#wevoi-commission-bank-body').html('<p class="notice notice-error"><?php echo esc_js(__('Request failed.', 'wevoi-competition-manager')); ?></p>');
        });
    });
    $('#wevoi-commission-bank-modal .wevoi-modal-close, #wevoi-commission-bank-modal .wevoi-modal-backdrop').on('click', function() {
        $('#wevoi-commission-bank-modal').hide();
    });
});
</script>
