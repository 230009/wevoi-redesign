<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

wp_nonce_field('wevoi_ajax_nonce', 'wevoi_nonce');

// Load List Table class
$type = 'registration';
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/edit-modal.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-registrations-list-table.php';
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/email-modal.php';

// Get competitions for filter dropdown
global $wpdb;
$competitions = $wpdb->get_results(
    "SELECT competition_code, competition_name, competition_status
     FROM {$wpdb->prefix}wevoi_competitions
     ORDER BY competition_name"
);

// Find an active competition with 'ON AIR' status if no filter is already applied
$filtered_competition = isset($_GET['competition_filter']) ? intval($_GET['competition_filter']) : 0;
$filtered_competition_name = '';
$filtered_competition_status = '';

// If no competition filter is manually set, try to find an active ON AIR competition
if ($filtered_competition == 0) {
    $active_competition = $wpdb->get_row(
        "SELECT competition_code, competition_name, competition_status
         FROM {$wpdb->prefix}wevoi_competitions
         WHERE competition_status = 'ON AIR'
         ORDER BY competition_date DESC
         LIMIT 1"
    );

    if ($active_competition) {
        $filtered_competition = $active_competition->competition_code;
        $filtered_competition_name = $active_competition->competition_name;
        $filtered_competition_status = $active_competition->competition_status;
    }
}
// If a competition filter is already set, get its details
else {
    $competition_info = $wpdb->get_row($wpdb->prepare(
        "SELECT competition_name, competition_status
         FROM {$wpdb->prefix}wevoi_competitions
         WHERE competition_code = %d",
        $filtered_competition
    ));

    if ($competition_info) {
        $filtered_competition_name = $competition_info->competition_name;
        $filtered_competition_status = $competition_info->competition_status;
    }
}

// Create table instance and pass the filtered competition
$_REQUEST['competition_filter'] = $filtered_competition; // Ensure filter is applied to table
$registrationsTable = new Registrations_List_Table();
$registrationsTable->prepare_items();
?>

<div class="wrap">
    <?php
    $message = isset($_GET['message']) ? intval($_GET['message']) : 0;
    $deleted = isset($_GET['deleted']) ? intval($_GET['deleted']) : 0;

    if ($deleted > 0): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    _n(
                        '%s registration deleted successfully.',
                        '%s registrations deleted successfully.',
                        $deleted,
                        'wevoi-competition-manager'
                    ),
                    number_format_i18n($deleted)
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <h1 class="wp-heading-inline">
        <?php
        if ($filtered_competition_name) {
            printf(
                __('Registrations for %s (%s)', 'wevoi-competition-manager'),
                esc_html($filtered_competition_name),
                esc_html($filtered_competition_status)
            );
        } else {
            _e('Registrations', 'wevoi-competition-manager');
        }
        ?>
    </h1>

    <a href="<?php echo esc_url(add_query_arg([
        'action' => 'export_csv',
        'tab' => 'registrations',
        'page' => 'wevoi-competitions',
        'competition_filter' => $filtered_competition,
        'payment_status' => isset($_GET['payment_status']) ? sanitize_text_field($_GET['payment_status']) : '',
        'application_status' => isset($_GET['application_status']) ? sanitize_text_field($_GET['application_status']) : '',
        's' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
        'orderby' => isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '',
        'order' => isset($_GET['order']) ? sanitize_text_field($_GET['order']) : ''
    ], admin_url('admin.php'))); ?>" class="page-title-action export-csv-button">
        <span class="dashicons dashicons-media-spreadsheet" style="margin-right: 5px;"></span>
        <?php _e('Export CSV', 'wevoi-competition-manager'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Filters -->
    <form method="get" id="registrations-filter-form">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
        <input type="hidden" name="tab" value="registrations">

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="payment_status">
                    <option value=""><?php _e('All Payment Statuses', 'wevoi-competition-manager'); ?></option>
                    <?php
                    $payment_statuses = ['PENDING', 'PRE_REGISTERED', 'PAID', 'FREE', 'FAILED', 'REFUNDED', 'FULLY_REFUNDED', 'PARTIAL_REFUNDED'];
                    foreach ($payment_statuses as $status) {
                        $payment_option_label = $status === 'PRE_REGISTERED'
                            ? __('Pre-registered', 'wevoi-competition-manager')
                            : $status;
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($status),
                            selected(isset($_GET['payment_status']) ? $_GET['payment_status'] : '', $status, false),
                            esc_html($payment_option_label)
                        );
                    }
                    ?>
                </select>

                <select name="application_status">
                    <option value=""><?php _e('All Application Statuses', 'wevoi-competition-manager'); ?></option>
                    <?php
                    $application_statuses = ['PENDING_REVIEW', 'APPLICATION_ACCEPTED', 'VIDEO_INACCESSIBLE', 'IRRELEVANT_CONTENT', 'APPLICATION_REJECTED'];
                    foreach ($application_statuses as $status) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($status),
                            selected(isset($_GET['application_status']) ? $_GET['application_status'] : '', $status, false),
                            esc_html($status)
                        );
                    }
                    ?>
                </select>

                <!-- Competition filter dropdown -->
                <select name="competition_filter">
                    <option value=""><?php _e('All Competitions', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($competitions as $comp): ?>
                        <option value="<?php echo esc_attr($comp->competition_code); ?>"
                            <?php selected($filtered_competition, $comp->competition_code); ?>>
                            <?php echo esc_html($comp->competition_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php submit_button(__('Filter', 'wevoi-competition-manager'), 'action', false, false); ?>
            </div>

            <div class="alignright">
                <p class="search-box">
                    <label class="screen-reader-text" for="registration-search">
                        <?php _e('Search Registrations', 'wevoi-competition-manager'); ?>:
                    </label>
                    <input type="search" id="registration-search" name="s" value="<?php _admin_search_query(); ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, ['id' => 'search-submit']); ?>
                </p>
            </div>
        </div>

        <!-- Table View -->
        <div id="registrations-table-view" class="registrations-view">
            <form method="post">
                <?php
                // Bulk action nonce
                wp_nonce_field('bulk-registrations');
                $registrationsTable->display();
                ?>
            </form>
        </div>

        <!-- Details Modal Template -->
        <div id="registration-details-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><?php _e('Registration Details', 'wevoi-competition-manager'); ?></h2>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <!-- AJAX ile doldurulacak -->
                </div>
            </div>
        </div>

        <!-- Bank Details Modal Template -->
        <div id="bank-details-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><?php _e('Bank Account Details', 'wevoi-competition-manager'); ?></h2>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="bank-details-content">
                        <!-- AJAX ile doldurulacak -->
                    </div>
                </div>
            </div>
        </div>

        <style>
        /* Status badge stilleri */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            min-width: 80px;
        }

        .status-pending { background: #fff3e0; color: #e65100; }
        .status-pre_registered { background: #f3e5f5; color: #6a1b9a; }
        .status-paid { background: #e8f5e9; color: #1b5e20; }
        .status-free { background: #e3f2fd; color: #0d47a1; }
        .status-failed { background: #ffebee; color: #c62828; }
        .status-refunded { background: #fce4ec; color: #c2185b; }
        .status-fully_refunded { background: #fce4ec; color: #c2185b; }
        .status-partial_refunded { background: #fce4ec; color: #c2185b; }

        .status-pending_review { background: #fff3e0; color: #e65100; }
        .status-application_accepted { background: #e8f5e9; color: #1b5e20; }
        .status-video_inaccessible { background: #ffebee; color: #c62828; }
        .status-irrelevant_content { background: #ffebee; color: #c62828; }
        .status-application_rejected { background: #ffebee; color: #c62828; }

        /* Medal badge stilleri */
        .medal-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .medal-gold {
            background: linear-gradient(135deg, #fff4d3, #ffd700);
            color: #856404;
            border: 1px solid #ffd700;
        }

        .medal-silver {
            background: linear-gradient(135deg, #f8f9fa, #c0c0c0);
            color: #4a4a4a;
            border: 1px solid #c0c0c0;
        }

        .medal-bronze {
            background: linear-gradient(135deg, #fff0e8, #cd7f32);
            color: #7a4012;
            border: 1px solid #cd7f32;
        }

        .medal-success {
            background: linear-gradient(135deg, #e8f5e9, #81c784);
            color: #1b5e20;
            border: 1px solid #81c784;
        }

        /* Result details stili */
        .result-details {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .result-details .summit-coupon-info {
            margin-top: 8px;
            padding: 6px 10px;
            background: #e8f5e9;
            border-left: 3px solid #4caf50;
            border-radius: 3px;
            font-size: 12px;
        }

        .result-details .summit-coupon-info strong {
            color: #2e7d32;
            margin-right: 6px;
        }

        /* Youtube link stili */
        .youtube-link {
            display: inline-block;
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            background: #ff0000;
            color: #ffffff;
            font-size: 12px;
            margin-top: 5px;
        }

        .youtube-link:hover {
            background: #cc0000;
            color: #ffffff;
        }

        /* Modal stilleri */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 159900;
        }

        .modal-content {
            position: relative;
            background: #fff;
            margin: 30px auto;
            max-width: 800px;
            max-height: calc(100vh - 60px);
            overflow-y: auto;
        }

        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 15px;
        }

        .modal-close {
            cursor: pointer;
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
        }

        /* Bank details modal stilleri */
        .bank-details-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .bank-details-info h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            color: #333;
        }

        .bank-details-info .form-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bank-details-info .form-table th {
            width: 40%;
            padding: 8px 0;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
        }

        .bank-details-info .form-table td {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .bank-details-info .form-table tr:last-child th,
        .bank-details-info .form-table tr:last-child td {
            border-bottom: none;
        }

        /* Bank details button stili */
        .bank-details {
            background: #0073aa !important;
            border-color: #0073aa !important;
            color: white !important;
        }

        .bank-details:hover {
            background: #005a87 !important;
            border-color: #005a87 !important;
        }

        @media (max-width: 768px) {
            .bank-details-info {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Registration details modal işlemleri
            $('.view-details').on('click', function() {
                const registrationId = $(this).data('registration-id');
                const modal = $('#registration-details-modal');

                // AJAX ile detayları getir
                $.post(wevoiAdmin.ajaxurl, {
                    action: 'get_registration_details',
                    registration_id: registrationId,
                    nonce: '<?php echo wp_create_nonce('get_registration_details'); ?>'
                }, function(response) {
                    if (response.success) {
                        modal.find('.modal-body').html(response.data);
                        modal.show();
                    }
                });
            });

            // Bank details modal işlemleri
            $('.bank-details').on('click', function() {
                const registrationId = $(this).data('registration-id');
                const modal = $('#bank-details-modal');

                // Loading göster
                $('#bank-details-content').html('<div style="text-align: center; padding: 20px;"><span class="spinner is-active"></span> Loading bank details...</div>');
                modal.show();

                // AJAX ile banka bilgilerini getir
                console.log('Sending AJAX request with registration_id:', registrationId);
                console.log('AJAX URL:', wevoiAdmin.ajaxurl);

                // Use fallback nonce if wevoiAdmin.nonce is not available
                const nonce = (typeof wevoiAdmin !== 'undefined' && wevoiAdmin.nonce) ? wevoiAdmin.nonce :
                             (typeof wevoiAjax !== 'undefined' && wevoiAjax.securityNonce) ? wevoiAjax.securityNonce :
                             '<?php echo wp_create_nonce('wevoi_ajax_nonce'); ?>';

                console.log('Nonce being sent:', nonce);
                console.log('wevoiAdmin available:', typeof wevoiAdmin !== 'undefined');
                console.log('wevoiAjax available:', typeof wevoiAjax !== 'undefined');

                $.post((typeof wevoiAdmin !== 'undefined' && wevoiAdmin.ajaxurl) ? wevoiAdmin.ajaxurl : ajaxurl, {
                    action: 'get_bank_account_details',
                    registration_id: registrationId,
                    nonce: nonce
                }, function(response) {
                    console.log('Bank details AJAX response:', response);

                    if (response && response.success && response.data) {
                        const data = response.data;

                        try {
                            const html = `
                                <div class="bank-details-info">
                                    <div class="student-info">
                                        <h3>Student Information</h3>
                                        <table class="form-table">
                                            <tr>
                                                <th>Name:</th>
                                                <td>${data.student_name || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Email:</th>
                                                <td>${data.student_email || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Competition:</th>
                                                <td>${data.competition_name || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Medal:</th>
                                                <td><span class="medal-badge medal-${(data.medal || 'N/A').toLowerCase()}">${data.medal || 'N/A'}</span></td>
                                            </tr>
                                            <tr>
                                                <th>Rank:</th>
                                                <td>#${data.rank || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Prize Amount:</th>
                                                <td><strong>£${parseFloat(data.prize_amount || 0).toFixed(2)}</strong></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div class="bank-info">
                                        <h3>Bank Account Information</h3>
                                        <table class="form-table">
                                            <tr>
                                                <th>Account Holder:</th>
                                                <td>${data.account_holder_name || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Bank Name:</th>
                                                <td>${data.bank_name || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Account Number:</th>
                                                <td>${data.account_number || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Sort Code:</th>
                                                <td>${data.sort_code || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>IBAN:</th>
                                                <td>${data.iban || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>SWIFT/BIC Code:</th>
                                                <td>${data.swift_code || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Country:</th>
                                                <td>${data.country || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>City:</th>
                                                <td>${data.city || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Currency:</th>
                                                <td>${data.currency || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>General Information:</th>
                                                <td>${data.general_info ? String(data.general_info).replace(/\n/g, '<br>') : 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Status:</th>
                                                <td><span class="status-badge status-${(data.status || 'N/A').toLowerCase()}">${data.status || 'N/A'}</span></td>
                                            </tr>
                                            <tr>
                                                <th>Submission Date:</th>
                                                <td>${data.submission_date && data.submission_date !== 'N/A' ? new Date(data.submission_date).toLocaleString() : 'N/A'}</td>
                                            </tr>
                                            ${data.admin_notes ? `
                                            <tr>
                                                <th>Admin Notes:</th>
                                                <td>${data.admin_notes}</td>
                                            </tr>
                                            ` : ''}
                                        </table>
                                    </div>
                                </div>
                            `;
                            $('#bank-details-content').html(html);
                        } catch (error) {
                            console.error('Error generating HTML:', error);
                            $('#bank-details-content').html('<div class="notice notice-error"><p>Error generating display: ' + error.message + '</p></div>');
                        }
                    } else {
                        let errorMsg = 'Unable to load bank details.';
                        if (response && response.data) {
                            if (typeof response.data === 'string') {
                                errorMsg = response.data;
                            } else if (response.data.message) {
                                errorMsg = response.data.message;
                            }
                        }
                        $('#bank-details-content').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    console.log('Bank details AJAX failed:', {xhr: xhr, status: status, error: error});
                    $('#bank-details-content').html('<div class="notice notice-error"><p>Failed to load bank details. Please try again.</p></div>');
                });
            });

            // Modal close işlemleri
            $('.modal-close').on('click', function() {
                $('.modal').hide();
            });

            $(document).on('click', function(e) {
                if ($(e.target).is('.modal')) {
                    $('.modal').hide();
                }
            });
        });
        </script>
    </form>
</div>
