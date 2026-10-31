<?php
// Direct access kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// List Table sınıfını yükle
require_once WEVOI_CM_PLUGIN_DIR . 'admin/class-coupons-list-table.php';

// Tablo örneğini oluştur
$couponsTable = new Coupons_List_Table();
$couponsTable->prepare_items();

// Mesaj parametrelerini kontrol et
$message = isset($_GET['message']) ? intval($_GET['message']) : 0;
$messages = [
    1 => __('Coupon added successfully.', 'wevoi-competition-manager'),
    2 => __('Coupon updated successfully.', 'wevoi-competition-manager'),
    3 => __('Coupon(s) deleted successfully.', 'wevoi-competition-manager'),
    4 => __('Error occurred.', 'wevoi-competition-manager')
];

// Competition Code parametresi
$competition_code = isset($_GET['competition_code']) ? intval($_GET['competition_code']) : 0;
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php 
        if ($competition_code) {
            global $wpdb;
            $competition = $wpdb->get_row($wpdb->prepare(
                "SELECT competition_name FROM {$wpdb->prefix}wevoi_competitions WHERE competition_code = %d",
                $competition_code
            ));
            if ($competition) {
                printf(__('Coupons for %s', 'wevoi-competition-manager'), esc_html($competition->competition_name));
            }
        } else {
            _e('Coupons', 'wevoi-competition-manager');
        }
        ?>
    </h1>
    
    <?php if ($competition_code): ?>
        <a href="?page=<?php echo $_REQUEST['page']; ?>&tab=coupons&action=add&competition_code=<?php echo $competition_code; ?>" 
           class="page-title-action">
            <?php _e('Add New Coupon', 'wevoi-competition-manager'); ?>
        </a>
        <a href="?page=<?php echo $_REQUEST['page']; ?>&tab=coupons" 
           class="page-title-action">
            <?php _e('View All Coupons', 'wevoi-competition-manager'); ?>
        </a>
    <?php else: ?>
        <a href="?page=<?php echo $_REQUEST['page']; ?>&tab=coupons&action=add" 
           class="page-title-action">
            <?php _e('Add New Coupon', 'wevoi-competition-manager'); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <?php
    // Mesaj gösterimi
    if ($message && isset($messages[$message])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <!-- Arama ve filtreleme formu -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
        <input type="hidden" name="tab" value="coupons">
        <?php if ($competition_code): ?>
            <input type="hidden" name="competition_code" value="<?php echo $competition_code; ?>">
        <?php endif; ?>

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="status_filter">
                    <option value=""><?php _e('All Statuses', 'wevoi-competition-manager'); ?></option>
                    <option value="valid" <?php selected(isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'valid'); ?>>
                        <?php _e('Valid', 'wevoi-competition-manager'); ?>
                    </option>
                    <option value="expired" <?php selected(isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'expired'); ?>>
                        <?php _e('Expired', 'wevoi-competition-manager'); ?>
                    </option>
                    <option value="limit_reached" <?php selected(isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'limit_reached'); ?>>
                        <?php _e('Limit Reached', 'wevoi-competition-manager'); ?>
                    </option>
                </select>

                <?php 
                if (!$competition_code) {
                    global $wpdb;
                    $competitions = $wpdb->get_results(
                        "SELECT competition_code, competition_name 
                         FROM {$wpdb->prefix}wevoi_competitions 
                         ORDER BY competition_name"
                    );
                    if ($competitions): ?>
                        <select name="competition_filter">
                            <option value=""><?php _e('All Competitions', 'wevoi-competition-manager'); ?></option>
                            <?php foreach ($competitions as $comp): ?>
                                <option value="<?php echo esc_attr($comp->competition_code); ?>"
                                    <?php selected(isset($_GET['competition_filter']) ? $_GET['competition_filter'] : '', $comp->competition_code); ?>>
                                    <?php echo esc_html($comp->competition_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif;
                }
                ?>

                <?php submit_button(__('Filter', 'wevoi-competition-manager'), 'action', false, false); ?>
            </div>

            <div class="alignright">
                <p class="search-box">
                    <label class="screen-reader-text" for="coupon-search">
                        <?php _e('Search Coupons', 'wevoi-competition-manager'); ?>:
                    </label>
                    <input type="search" id="coupon-search" name="s" value="<?php _admin_search_query(); ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, ['id' => 'search-submit']); ?>
                </p>
            </div>
        </div>
    </form>

    <!-- Tablo formu -->
    <form method="post" action="<?php echo admin_url('admin.php'); ?>">
        <input type="hidden" name="page" value="wevoi-competitions">
        <input type="hidden" name="tab" value="coupons">
        <?php 
        // Bulk action nonce
        wp_nonce_field('bulk-coupons'); 
        $couponsTable->display(); 
        ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Status badge'lerinin renklendirilmesi
    $('.status-badge').each(function() {
        var status = $(this).text().trim();
        switch(status) {
            case 'VALID':
                $(this).addClass('status-valid');
                break;
            case 'EXPIRED':
                $(this).addClass('status-expired');
                break;
            case 'LIMIT REACHED':
                $(this).addClass('status-limit-reached');
                break;
        }
    });
});
</script>