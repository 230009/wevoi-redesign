<?php
/**
 * Bank Account Management Page for Admin
 */

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;

// Handle status updates
if (isset($_POST['update_status']) && wp_verify_nonce($_POST['bank_account_nonce'], 'update_bank_status')) {
    $account_id = intval($_POST['account_id']);
    $new_status = sanitize_text_field($_POST['new_status']);
    $admin_notes = sanitize_textarea_field($_POST['admin_notes']);
    
    $update_data = array(
        'status' => $new_status,
        'admin_notes' => $admin_notes
    );
    
    if ($new_status === 'VERIFIED') {
        $update_data['verified_date'] = current_time('mysql');
        $update_data['verified_by'] = get_current_user_id();
    }
    
    $result = $wpdb->update(
        $wpdb->prefix . 'wevoi_bank_accounts',
        $update_data,
        array('id' => $account_id),
        array('%s', '%s', '%s', '%d'),
        array('%d')
    );
    
    if ($result !== false) {
        echo '<div class="notice notice-success"><p>Bank account status updated successfully!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Error updating bank account status.</p></div>';
    }
}

// Get bank accounts with user and competition details
$bank_accounts = $wpdb->get_results("
    SELECT ba.*, 
           u.display_name as student_name,
           u.user_email as student_email,
           c.competition_name,
           r.claimed_prize_amount,
           co.countryname,
           cu.currency_code,
           cu.currency_name,
           cu.symbol
    FROM {$wpdb->prefix}wevoi_bank_accounts ba
    JOIN {$wpdb->prefix}users u ON ba.user_id = u.ID
    JOIN {$wpdb->prefix}wevoi_competitions c ON ba.competition_code = c.competition_code
    JOIN {$wpdb->prefix}wevoi_registrations r ON ba.registration_id = r.register_id
    JOIN {$wpdb->prefix}wevoi_countries co ON ba.countryid = co.countryid
    JOIN {$wpdb->prefix}wevoi_currencies cu ON ba.currencyid = cu.currencyid
    ORDER BY ba.submission_date DESC
");
?>

<div class="wrap">
    <h1>Bank Account Management</h1>
    <p>Manage bank account details submitted by students for prize payments.</p>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="status-filter">
                <option value="">All Statuses</option>
                <option value="PENDING">Pending</option>
                <option value="VERIFIED">Verified</option>
                <option value="REJECTED">Rejected</option>
            </select>
            <button type="button" class="button" onclick="filterAccounts()">Filter</button>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Student</th>
                <th>Competition</th>
                <th>Prize Amount</th>
                <th>Account Holder</th>
                <th>Bank Name</th>
                <th>Country</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bank_accounts)): ?>
                <tr>
                    <td colspan="9">No bank account details found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($bank_accounts as $account): ?>
                    <tr class="account-row" data-status="<?php echo esc_attr($account->status); ?>">
                        <td>
                            <strong><?php echo esc_html($account->student_name); ?></strong><br>
                            <small><?php echo esc_html($account->student_email); ?></small>
                        </td>
                        <td><?php echo esc_html($account->competition_name); ?></td>
                        <td>£<?php echo number_format($account->claimed_prize_amount, 2); ?></td>
                        <td><?php echo esc_html($account->account_holder_name); ?></td>
                        <td><?php echo esc_html($account->bank_name); ?></td>
                        <td><?php echo esc_html($account->countryname); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($account->status); ?>">
                                <?php echo esc_html($account->status); ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y H:i', strtotime($account->submission_date)); ?></td>
                        <td>
                            <button type="button" class="button button-small" 
                                    onclick="viewDetails(<?php echo $account->id; ?>)">
                                View Details
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal for viewing/updating account details -->
<div id="account-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Bank Account Details</h2>
        <div id="account-details"></div>
        
        <form method="post" id="status-update-form">
            <?php wp_nonce_field('update_bank_status', 'bank_account_nonce'); ?>
            <input type="hidden" name="account_id" id="modal-account-id">
            
            <table class="form-table">
                <tr>
                    <th><label for="new_status">Status</label></th>
                    <td>
                        <select name="new_status" id="new_status" required>
                            <option value="PENDING">Pending</option>
                            <option value="VERIFIED">Verified</option>
                            <option value="REJECTED">Rejected</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="admin_notes">Admin Notes</label></th>
                    <td>
                        <textarea name="admin_notes" id="admin_notes" rows="4" cols="50" 
                                  placeholder="Add notes about verification or rejection..."></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="update_status" class="button button-primary" value="Update Status">
            </p>
        </form>
    </div>
</div>

<style>
.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-verified {
    background-color: #d4edda;
    color: #155724;
}

.status-rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 5px;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.account-details {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.account-details table {
    width: 100%;
    border-collapse: collapse;
}

.account-details td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}

.account-details td:first-child {
    font-weight: bold;
    width: 30%;
}
</style>

<script>
function filterAccounts() {
    const filter = document.getElementById('status-filter').value;
    const rows = document.querySelectorAll('.account-row');
    
    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        if (!filter || status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function viewDetails(accountId) {
    // AJAX call to get account details
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_bank_account_details&account_id=' + accountId + '&nonce=' + '<?php echo wp_create_nonce("get_bank_account_details"); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const account = data.data;
            document.getElementById('modal-account-id').value = accountId;
            document.getElementById('new_status').value = account.status;
            document.getElementById('admin_notes').value = account.admin_notes || '';
            
            document.getElementById('account-details').innerHTML = `
                <div class="account-details">
                    <table>
                        <tr><td>Student:</td><td>${account.student_name} (${account.student_email})</td></tr>
                        <tr><td>Competition:</td><td>${account.competition_name}</td></tr>
                        <tr><td>Prize Amount:</td><td>£${parseFloat(account.claimed_prize_amount).toFixed(2)}</td></tr>
                        <tr><td>Account Holder:</td><td>${account.account_holder_name}</td></tr>
                        <tr><td>Bank Name:</td><td>${account.bank_name}</td></tr>
                        <tr><td>Account Number:</td><td>${account.account_number}</td></tr>
                        <tr><td>Sort Code:</td><td>${account.sort_code || 'N/A'}</td></tr>
                        <tr><td>IBAN:</td><td>${account.iban || 'N/A'}</td></tr>
                        <tr><td>SWIFT Code:</td><td>${account.swift_code || 'N/A'}</td></tr>
                        <tr><td>Country:</td><td>${account.countryname}</td></tr>
                        <tr><td>City:</td><td>${account.city || 'N/A'}</td></tr>
                        <tr><td>Currency:</td><td>${account.currency_code} (${account.symbol}) - ${account.currency_name}</td></tr>
                        <tr><td>General Information:</td><td>${account.general_info ? String(account.general_info).replace(/\n/g, '<br>') : 'N/A'}</td></tr>
                        <tr><td>Submitted:</td><td>${account.submission_date}</td></tr>
                    </table>
                </div>
            `;
            
            document.getElementById('account-modal').style.display = 'block';
        }
    });
}

// Close modal when clicking on X or outside
document.querySelector('.close').onclick = function() {
    document.getElementById('account-modal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('account-modal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script> 