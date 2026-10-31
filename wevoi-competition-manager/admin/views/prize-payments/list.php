<?php
/**
 * Prize Payment Status Management Page
 */

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;

// Handle AJAX update
if (isset($_POST['action']) && $_POST['action'] === 'update_prize_payment_status') {
    check_ajax_referer('wevoi_prize_payment_nonce', 'nonce');
    
    $register_id = intval($_POST['register_id']);
    $new_status = sanitize_text_field($_POST['status']);
    
    if (!in_array($new_status, ['PAID', 'NOT PAID'])) {
        wp_send_json_error('Invalid status');
    }
    
    $updated = $wpdb->update(
        $wpdb->prefix . 'wevoi_registrations',
        ['claimed_prize_paid_status' => $new_status],
        ['register_id' => $register_id],
        ['%s'],
        ['%d']
    );
    
    if ($updated !== false) {
        wp_send_json_success('Status updated successfully');
    } else {
        wp_send_json_error('Failed to update status');
    }
}

// Filters
$competition_filter = isset($_GET['competition_filter']) ? intval($_GET['competition_filter']) : 0;
$rank_filter = isset($_GET['rank_filter']) ? intval($_GET['rank_filter']) : 0;
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

$allowed_ranks = [1, 2, 3];
$allowed_statuses = ['PAID', 'NOT PAID'];

if ($rank_filter > 0 && !in_array($rank_filter, $allowed_ranks, true)) {
    $rank_filter = 0;
}
if ($status_filter !== '' && !in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = '';
}

// Competitions that have prize winners (for filter dropdown)
$filter_competitions = $wpdb->get_results(
    "SELECT DISTINCT c.competition_code, c.competition_name
     FROM {$wpdb->prefix}wevoi_registrations r
     JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
     WHERE r.claimed_prize_amount IS NOT NULL
     AND r.claimed_prize_amount > 0
     ORDER BY c.competition_code DESC"
);

$where = [
    'r.claimed_prize_amount IS NOT NULL',
    'r.claimed_prize_amount > 0',
];
$values = [];

if ($competition_filter > 0) {
    $where[] = 'r.competition_code = %d';
    $values[] = $competition_filter;
}

if ($rank_filter > 0) {
    $where[] = 'r.student_rank = %d';
    $values[] = $rank_filter;
}

if ($status_filter !== '') {
    if ($status_filter === 'NOT PAID') {
        $where[] = "(r.claimed_prize_paid_status = %s OR r.claimed_prize_paid_status IS NULL OR r.claimed_prize_paid_status = '')";
        $values[] = 'NOT PAID';
    } else {
        $where[] = 'r.claimed_prize_paid_status = %s';
        $values[] = $status_filter;
    }
}

$where_sql = implode(' AND ', $where);

$query = "
    SELECT
        r.register_id,
        r.user_id,
        r.competition_code,
        r.claimed_prize_amount,
        r.claimed_prize_paid_status,
        r.student_rank,
        r.student_medal,
        c.competition_name,
        u.user_email,
        CONCAT(um_first.meta_value, ' ', um_last.meta_value) as student_name,
        supervisor_u.user_email as supervisor_email,
        TRIM(CONCAT(COALESCE(sup_first.meta_value,''), ' ', COALESCE(sup_last.meta_value,''))) as supervisor_name
    FROM {$wpdb->prefix}wevoi_registrations r
    JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
    JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
    LEFT JOIN {$wpdb->prefix}usermeta um_first ON r.user_id = um_first.user_id AND um_first.meta_key = 'first_name'
    LEFT JOIN {$wpdb->prefix}usermeta um_last ON r.user_id = um_last.user_id AND um_last.meta_key = 'last_name'
    LEFT JOIN {$wpdb->prefix}users supervisor_u ON r.supervisor_id = supervisor_u.ID
    LEFT JOIN {$wpdb->prefix}usermeta sup_first ON supervisor_u.ID = sup_first.user_id AND sup_first.meta_key = 'first_name'
    LEFT JOIN {$wpdb->prefix}usermeta sup_last ON supervisor_u.ID = sup_last.user_id AND sup_last.meta_key = 'last_name'
    WHERE {$where_sql}
    ORDER BY r.registration_date DESC
";

if (!empty($values)) {
    $prize_winners = $wpdb->get_results($wpdb->prepare($query, $values));
} else {
    $prize_winners = $wpdb->get_results($query);
}

$has_active_filters = ($competition_filter > 0 || $rank_filter > 0 || $status_filter !== '');

?>

<div class="wrap">
    <h1><?php _e('Prize Payments', 'wevoi-competition-manager'); ?></h1>

    <div class="notice notice-info">
        <p><?php _e('This page shows students who have won prizes and allows you to update their payment status.', 'wevoi-competition-manager'); ?></p>
    </div>

    <form method="get" class="prize-payments-filters">
        <input type="hidden" name="page" value="wevoi-competitions">
        <input type="hidden" name="tab" value="prize-payments">

        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="competition_filter" class="screen-reader-text"><?php _e('Filter by competition', 'wevoi-competition-manager'); ?></label>
                <select name="competition_filter" id="competition_filter">
                    <option value=""><?php _e('All Competitions', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($filter_competitions as $competition): ?>
                        <option value="<?php echo esc_attr($competition->competition_code); ?>"
                            <?php selected($competition_filter, (int) $competition->competition_code); ?>>
                            <?php echo esc_html($competition->competition_code . ' - ' . $competition->competition_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="rank_filter" class="screen-reader-text"><?php _e('Filter by rank', 'wevoi-competition-manager'); ?></label>
                <select name="rank_filter" id="rank_filter">
                    <option value=""><?php _e('All Ranks', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($allowed_ranks as $rank): ?>
                        <option value="<?php echo esc_attr($rank); ?>" <?php selected($rank_filter, $rank); ?>>
                            <?php echo esc_html(sprintf(__('Rank %d', 'wevoi-competition-manager'), $rank)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="status_filter" class="screen-reader-text"><?php _e('Filter by payment status', 'wevoi-competition-manager'); ?></label>
                <select name="status_filter" id="status_filter">
                    <option value=""><?php _e('All Payment Statuses', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($allowed_statuses as $status): ?>
                        <option value="<?php echo esc_attr($status); ?>" <?php selected($status_filter, $status); ?>>
                            <?php echo esc_html($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'wevoi-competition-manager'); ?>">
                <?php if ($has_active_filters): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wevoi-competitions&tab=prize-payments')); ?>" class="button">
                        <?php _e('Clear', 'wevoi-competition-manager'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if (empty($prize_winners)): ?>
        <div class="notice notice-warning">
            <p>
                <?php
                echo $has_active_filters
                    ? esc_html__('No prize winners found for the selected filters.', 'wevoi-competition-manager')
                    : esc_html__('No prize winners found.', 'wevoi-competition-manager');
                ?>
            </p>
        </div>
    <?php else: ?>
        <p class="description" style="margin-bottom: 8px;">
            <?php echo esc_html(sprintf(__('Showing %d prize winner(s).', 'wevoi-competition-manager'), count($prize_winners))); ?>
        </p>
        <table class="wp-list-table widefat striped prize-payments-table">
            <thead>
                <tr>
                    <th><?php _e('Student Name', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Email', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Rank', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Medal', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Prize Amount', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Payment Status', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Supervisor', 'wevoi-competition-manager'); ?></th>
                    <th class="column-actions"><?php _e('Actions', 'wevoi-competition-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prize_winners as $winner): ?>
                    <?php
                    $display_status = !empty($winner->claimed_prize_paid_status)
                        ? $winner->claimed_prize_paid_status
                        : 'NOT PAID';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($winner->student_name ?: 'N/A'); ?></strong>
                        </td>
                        <td><?php echo esc_html($winner->user_email); ?></td>
                        <td><?php echo esc_html($winner->competition_name); ?></td>
                        <td>
                            <?php if ($winner->student_rank): ?>
                                <span class="rank-badge rank-<?php echo (int) $winner->student_rank; ?>">
                                    <?php echo (int) $winner->student_rank; ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($winner->student_medal): ?>
                                <span class="medal-badge medal-<?php echo esc_attr(strtolower($winner->student_medal)); ?>">
                                    <?php echo esc_html($winner->student_medal); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong>£<?php echo number_format($winner->claimed_prize_amount, 2); ?></strong>
                        </td>
                        <td>
                            <span class="payment-status-badge status-<?php echo esc_attr(strtolower(str_replace(' ', '-', $display_status))); ?>">
                                <?php echo esc_html($display_status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($winner->supervisor_name): ?>
                                <?php echo esc_html($winner->supervisor_name); ?><br>
                                <small><?php echo esc_html($winner->supervisor_email); ?></small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="prize-payment-actions column-actions">
                            <button type="button"
                                    class="button button-small update-payment-status"
                                    data-register-id="<?php echo (int) $winner->register_id; ?>"
                                    data-current-status="<?php echo esc_attr($display_status); ?>">
                                <?php _e('Update', 'wevoi-competition-manager'); ?>
                            </button>
                            <button type="button"
                                    class="button button-small button-primary bank-details"
                                    data-registration-id="<?php echo (int) $winner->register_id; ?>">
                                <?php _e('Bank', 'wevoi-competition-manager'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Payment Status Update Modal -->
<div id="payment-status-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('Update Payment Status', 'wevoi-competition-manager'); ?></h2>
        <form id="payment-status-form">
            <input type="hidden" id="register_id" name="register_id" value="">
            <input type="hidden" name="action" value="update_prize_payment_status">
            <?php wp_nonce_field('wevoi_prize_payment_nonce', 'nonce'); ?>
            
            <p>
                <label for="payment_status"><?php _e('Payment Status:', 'wevoi-competition-manager'); ?></label>
                <select id="payment_status" name="status" required>
                    <option value="NOT PAID"><?php _e('NOT PAID', 'wevoi-competition-manager'); ?></option>
                    <option value="PAID"><?php _e('PAID', 'wevoi-competition-manager'); ?></option>
                </select>
            </p>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Update Status', 'wevoi-competition-manager'); ?></button>
                <button type="button" class="button cancel-modal"><?php _e('Cancel', 'wevoi-competition-manager'); ?></button>
            </p>
        </form>
    </div>
</div>

<!-- Bank Details Modal -->
<div id="bank-details-modal" class="modal" style="display: none;">
    <div class="modal-content bank-details-modal-content">
        <div class="modal-header">
            <h2><?php _e('Bank Account Details', 'wevoi-competition-manager'); ?></h2>
            <span class="close bank-details-close">&times;</span>
        </div>
        <div class="modal-body">
            <div id="bank-details-content"></div>
        </div>
    </div>
</div>

<style>
/* Badge Styles */
.rank-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-align: center;
    min-width: 20px;
}

.rank-1 { background: #ffd700; color: #000; }
.rank-2 { background: #c0c0c0; color: #000; }
.rank-3 { background: #cd7f32; color: #fff; }

.medal-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-align: center;
    min-width: 60px;
}

.medal-gold { background: #ffd700; color: #000; }
.medal-silver { background: #c0c0c0; color: #000; }
.medal-bronze { background: #cd7f32; color: #fff; }
.medal-success { background: #28a745; color: #fff; }

.payment-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    min-width: 80px;
    text-align: center;
}

.status-paid { background: #28a745; color: #fff; }
.status-not-paid { background: #ffc107; color: #000; }

.wp-list-table .column-actions {
    width: 140px;
    white-space: nowrap;
}

.prize-payment-actions {
    white-space: nowrap;
}

.prize-payment-actions .button {
    display: inline-block;
    margin: 0 4px 0 0;
    vertical-align: middle;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 400px;
    border-radius: 5px;
}

.bank-details-modal-content {
    width: 640px;
    max-width: 95%;
    margin: 5% auto;
    padding: 0;
}

.bank-details-modal-content .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
}

.bank-details-modal-content .modal-header h2 {
    margin: 0;
    font-size: 1.3em;
}

.bank-details-modal-content .modal-body {
    padding: 20px;
}

.bank-details-info .form-table th {
    width: 160px;
    padding: 8px 10px 8px 0;
}

.bank-details-info h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.bank-details-info .bank-info {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

#payment-status-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

#payment-status-form select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

#payment-status-form .submit {
    margin-top: 20px;
    text-align: right;
}

#payment-status-form .submit button {
    margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Open payment status modal
    $('.update-payment-status').click(function() {
        var registerId = $(this).data('register-id');
        var currentStatus = $(this).data('current-status');
        
        $('#register_id').val(registerId);
        $('#payment_status').val(currentStatus);
        $('#payment-status-modal').show();
    });
    
    // Close payment status modal
    $('#payment-status-modal .close, #payment-status-modal .cancel-modal').click(function() {
        $('#payment-status-modal').hide();
    });

    // Close bank details modal
    $('.bank-details-close').click(function() {
        $('#bank-details-modal').hide();
    });
    
    // Close modal when clicking outside
    $(window).click(function(event) {
        if (event.target == document.getElementById('payment-status-modal')) {
            $('#payment-status-modal').hide();
        }
        if (event.target == document.getElementById('bank-details-modal')) {
            $('#bank-details-modal').hide();
        }
    });

    // Bank details
    $('.bank-details').on('click', function() {
        var registrationId = $(this).data('registration-id');
        var modal = $('#bank-details-modal');
        var ajaxUrl = (typeof wevoiAdmin !== 'undefined' && wevoiAdmin.ajaxurl) ? wevoiAdmin.ajaxurl : ajaxurl;
        var nonce = (typeof wevoiAdmin !== 'undefined' && wevoiAdmin.nonce) ? wevoiAdmin.nonce :
                    '<?php echo esc_js(wp_create_nonce('wevoi_ajax_nonce')); ?>';

        $('#bank-details-content').html('<div style="text-align:center;padding:20px;"><span class="spinner is-active" style="float:none;"></span> <?php echo esc_js(__('Loading bank details...', 'wevoi-competition-manager')); ?></div>');
        modal.show();

        $.post(ajaxUrl, {
            action: 'get_bank_account_details',
            registration_id: registrationId,
            nonce: nonce
        }, function(response) {
            if (response && response.success && response.data) {
                var data = response.data;
                var html = '' +
                    '<div class="bank-details-info">' +
                        '<div class="student-info">' +
                            '<h3><?php echo esc_js(__('Student Information', 'wevoi-competition-manager')); ?></h3>' +
                            '<table class="form-table">' +
                                '<tr><th><?php echo esc_js(__('Name', 'wevoi-competition-manager')); ?>:</th><td>' + (data.student_name || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Email', 'wevoi-competition-manager')); ?>:</th><td>' + (data.student_email || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Competition', 'wevoi-competition-manager')); ?>:</th><td>' + (data.competition_name || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Medal', 'wevoi-competition-manager')); ?>:</th><td><span class="medal-badge medal-' + String(data.medal || 'N/A').toLowerCase() + '">' + (data.medal || 'N/A') + '</span></td></tr>' +
                                '<tr><th><?php echo esc_js(__('Rank', 'wevoi-competition-manager')); ?>:</th><td>#' + (data.rank || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Prize Amount', 'wevoi-competition-manager')); ?>:</th><td><strong>£' + parseFloat(data.prize_amount || 0).toFixed(2) + '</strong></td></tr>' +
                            '</table>' +
                        '</div>' +
                        '<div class="bank-info">' +
                            '<h3><?php echo esc_js(__('Bank Account Information', 'wevoi-competition-manager')); ?></h3>' +
                            '<table class="form-table">' +
                                '<tr><th><?php echo esc_js(__('Account Holder', 'wevoi-competition-manager')); ?>:</th><td>' + (data.account_holder_name || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Bank Name', 'wevoi-competition-manager')); ?>:</th><td>' + (data.bank_name || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Account Number', 'wevoi-competition-manager')); ?>:</th><td>' + (data.account_number || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Sort Code', 'wevoi-competition-manager')); ?>:</th><td>' + (data.sort_code || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('IBAN', 'wevoi-competition-manager')); ?>:</th><td>' + (data.iban || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('SWIFT/BIC Code', 'wevoi-competition-manager')); ?>:</th><td>' + (data.swift_code || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Country', 'wevoi-competition-manager')); ?>:</th><td>' + (data.country || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('City', 'wevoi-competition-manager')); ?>:</th><td>' + (data.city || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Currency', 'wevoi-competition-manager')); ?>:</th><td>' + (data.currency || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('General Information', 'wevoi-competition-manager')); ?>:</th><td>' + (data.general_info ? String(data.general_info).replace(/\n/g, '<br>') : 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Status', 'wevoi-competition-manager')); ?>:</th><td>' + (data.status || 'N/A') + '</td></tr>' +
                                '<tr><th><?php echo esc_js(__('Submission Date', 'wevoi-competition-manager')); ?>:</th><td>' + (data.submission_date && data.submission_date !== 'N/A' ? new Date(data.submission_date).toLocaleString() : 'N/A') + '</td></tr>' +
                                (data.admin_notes ? '<tr><th><?php echo esc_js(__('Admin Notes', 'wevoi-competition-manager')); ?>:</th><td>' + data.admin_notes + '</td></tr>' : '') +
                            '</table>' +
                        '</div>' +
                    '</div>';
                $('#bank-details-content').html(html);
            } else {
                var errorMsg = '<?php echo esc_js(__('Unable to load bank details.', 'wevoi-competition-manager')); ?>';
                if (response && response.data) {
                    if (typeof response.data === 'string') {
                        errorMsg = response.data;
                    } else if (response.data.message) {
                        errorMsg = response.data.message;
                    }
                }
                $('#bank-details-content').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
            }
        }).fail(function() {
            $('#bank-details-content').html('<div class="notice notice-error"><p><?php echo esc_js(__('Failed to load bank details. Please try again.', 'wevoi-competition-manager')); ?></p></div>');
        });
    });
    
    // Handle form submission
    $('#payment-status-form').submit(function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'update_prize_payment_status');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Payment status updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while updating the status.');
            }
        });
    });
});
</script> 