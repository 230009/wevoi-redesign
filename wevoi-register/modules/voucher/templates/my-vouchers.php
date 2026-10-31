<?php
/**
 * Template for displaying user vouchers
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if user is admin
$is_admin = current_user_can('administrator');
?>

<div class="wevoi-my-vouchers">

   
    <?php if ($is_admin): ?>
        <div class="wevoi-admin-notice">
            <p>You are viewing all vouchers as an administrator.</p>
        </div>
    <?php endif; ?>
    
    <?php foreach ($vouchers as $voucher): ?>
        <div class="wevoi-voucher-card">
            <div class="wevoi-voucher-header">
                <div class="wevoi-voucher-title">
                    <h3><?php echo esc_html($voucher->competition_name); ?></h3>
                    <?php if ($is_admin): ?>
                        <div class="wevoi-voucher-user">
                            <span class="email-label">Buyer:</span>
                            <span class="user-email"><?php echo esc_html($voucher->payer_email); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="wevoi-voucher-status <?php echo esc_attr(strtolower($voucher->paid_status)); ?>">
                    <?php echo esc_html($voucher->paid_status); ?>
                </div>
            </div>
            
            <div class="wevoi-voucher-content">
                <?php if ($voucher->voucher_type === 'VOUCHER' && !empty($voucher->voucher_code)): ?>
                    <div class="wevoi-voucher-code-container">
                        <h4>Voucher Code</h4>
                        <div class="wevoi-voucher-code">
                            <?php echo esc_html($voucher->voucher_code); ?>
                        </div>
                        <p class="wevoi-voucher-usage">
                            This code can be used for <?php echo intval($voucher->remaining_uses); ?> registration(s)
                        </p>
                        
                        <?php if ($voucher->used_count > 0): ?>
                            <p class="wevoi-voucher-used">
                                <?php echo intval($voucher->used_count); ?> of <?php echo intval($voucher->total_limit); ?> uses have been redeemed
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="wevoi-voucher-amount-container">
                        <h4>Paid Amount</h4>
                        <div class="wevoi-voucher-amount">
                            £<?php echo number_format($voucher->price, 2); ?>
                        </div>
                        <p class="wevoi-voucher-thanks">
                            You paid!!
                        </p>
                    </div>
                <?php endif; ?>
                
                
                
                <div class="wevoi-voucher-details">
                    <div class="wevoi-detail-row">
                        <span class="label">Payment Date:</span>
                        <span class="value"><?php echo esc_html(date('d M Y H:i', strtotime($voucher->payment_date))); ?></span>
                    </div>
                    
                    <div class="wevoi-detail-row">
                        <span class="label">Payer Name:</span>
                        <span class="value"><?php echo esc_html($voucher->payer_name); ?></span>
                    </div>
                    
                    <div class="wevoi-detail-row">
                        <span class="label">Country:</span>
                        <span class="value"><?php echo esc_html($voucher->countryname); ?></span>
                    </div>
                    
                    <?php if ($voucher->voucher_type === 'VOUCHER'): ?>
                        <div class="wevoi-detail-row">
                            <span class="label">Quantity:</span>
                            <span class="value"><?php echo intval($voucher->total_limit); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="wevoi-detail-row">
                        <span class="label">Amount:</span>
                        <span class="value">£<?php echo number_format($voucher->price, 2); ?></span>
                    </div>
                    
                        <?php if ($voucher->voucher_type === 'AMOUNT' && !empty($voucher->payment_purpose)): ?>
        <div class="wevoi-detail-row">
            <span class="label">Purpose:</span>
            <span class="value"><?php echo esc_html($voucher->payment_purpose); ?></span>
        </div>
    <?php endif; ?>
                    
                    <?php if ($is_admin): ?>
                        <div class="wevoi-detail-row">
                            <span class="label">Voucher ID:</span>
                            <span class="value"><?php echo intval($voucher->id); ?></span>
                        </div>
                        <div class="wevoi-detail-row">
                            <span class="label">Payment Intent:</span>
                            <span class="value"><?php echo esc_html($voucher->payment_intent_id); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>