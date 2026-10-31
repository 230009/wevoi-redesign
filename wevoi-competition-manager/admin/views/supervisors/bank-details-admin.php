<?php
/**
 * Admin list: all supervisors with commission bank details (usermeta wevoi_commission_bank_details).
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wevoi-competition-manager'));
}

if (!class_exists('Wevoi_Commission')) {
    require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-commission.php';
}

global $wpdb;
$p = $wpdb->prefix;

$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

$sql = "SELECT DISTINCT u.ID, u.display_name, u.user_email,
        um_fn.meta_value AS first_name,
        um_ln.meta_value AS last_name,
        c.countryname,
        um_bank.meta_value AS bank_json
        FROM {$p}users u
        INNER JOIN {$p}usermeta um_role ON u.ID = um_role.user_id AND um_role.meta_key = 'wpie_capabilities'
        LEFT JOIN {$p}usermeta um_fn ON u.ID = um_fn.user_id AND um_fn.meta_key = 'first_name'
        LEFT JOIN {$p}usermeta um_ln ON u.ID = um_ln.user_id AND um_ln.meta_key = 'last_name'
        LEFT JOIN {$p}usermeta um_country ON u.ID = um_country.user_id AND um_country.meta_key = 'countryid'
        LEFT JOIN {$p}wevoi_countries c ON c.countryid = um_country.meta_value
        LEFT JOIN {$p}usermeta um_bank ON u.ID = um_bank.user_id AND um_bank.meta_key = 'wevoi_commission_bank_details'
        WHERE um_role.meta_value LIKE '%supervisor%'";

if ($search !== '') {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $sql .= $wpdb->prepare(' AND (u.display_name LIKE %s OR u.user_email LIKE %s)', $like, $like);
}

$sql .= ' ORDER BY u.display_name ASC';

$supervisors = $wpdb->get_results($sql);

$filled = 0;
foreach ($supervisors as $row) {
    if (class_exists('Wevoi_Commission') && Wevoi_Commission::user_has_bank_details((int) $row->ID)) {
        $filled++;
    }
}
?>

<div class="wrap wevoi-supervisor-banks-wrap">
    <h1><?php esc_html_e('Supervisor bank details', 'wevoi-competition-manager'); ?></h1>
    <p class="description">
        <?php esc_html_e('Commission payout bank information entered by supervisors on their dashboard (sensitive data — restrict admin access).', 'wevoi-competition-manager'); ?>
    </p>
    <p>
        <?php
        printf(
            /* translators: 1: total supervisors, 2: count with bank details saved */
            esc_html__('Total supervisors: %1$d — With bank details on file: %2$d', 'wevoi-competition-manager'),
            count($supervisors),
            $filled
        );
        ?>
    </p>

    <form method="get" class="wevoi-supervisor-banks-search" style="margin: 1em 0;">
        <input type="hidden" name="page" value="wevoi-competitions">
        <input type="hidden" name="tab" value="supervisor-banks">
        <label class="screen-reader-text" for="wevoi-supervisor-banks-s"><?php esc_html_e('Search supervisors', 'wevoi-competition-manager'); ?></label>
        <input type="search" id="wevoi-supervisor-banks-s" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by name or email…', 'wevoi-competition-manager'); ?>" class="regular-text">
        <?php submit_button(__('Search', 'wevoi-competition-manager'), 'secondary', 'submit', false); ?>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-supervisor"><?php esc_html_e('Supervisor', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Email', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Country', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Status', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Account holder', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Bank name', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Branch', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('IBAN', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('SWIFT / BIC', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Sort code', 'wevoi-competition-manager'); ?></th>
                <th scope="col"><?php esc_html_e('Currency', 'wevoi-competition-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($supervisors)) : ?>
                <tr>
                    <td colspan="11"><?php esc_html_e('No supervisors found.', 'wevoi-competition-manager'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($supervisors as $sup) : ?>
                    <?php
                    $uid = (int) $sup->ID;
                    $name = wevoi_format_display_name(
                        isset($sup->first_name) ? $sup->first_name : '',
                        isset($sup->last_name) ? $sup->last_name : '',
                        isset($sup->display_name) ? $sup->display_name : ''
                    );
                    $bank = array();
                    if (!empty($sup->bank_json) && is_string($sup->bank_json)) {
                        $decoded = json_decode($sup->bank_json, true);
                        if (is_array($decoded)) {
                            $bank = $decoded;
                        }
                    }
                    $has = class_exists('Wevoi_Commission') && Wevoi_Commission::user_has_bank_details($uid);
                    $cell = function ($key) use ($bank) {
                        $v = isset($bank[ $key ]) ? (string) $bank[ $key ] : '';
                        $v = trim($v);

                        return $v === '' ? '—' : esc_html($v);
                    };
                    ?>
                    <tr class="<?php echo $has ? '' : 'wevoi-bank-row-empty'; ?>">
                        <td class="column-supervisor">
                            <strong><?php echo esc_html($name); ?></strong>
                            <div class="row-actions">
                                <span class="id"><?php printf(esc_html__('ID: %d', 'wevoi-competition-manager'), $uid); ?></span>
                            </div>
                        </td>
                        <td><a href="mailto:<?php echo esc_attr($sup->user_email); ?>"><?php echo esc_html($sup->user_email); ?></a></td>
                        <td><?php echo !empty($sup->countryname) ? esc_html($sup->countryname) : '—'; ?></td>
                        <td>
                            <?php if ($has) : ?>
                                <span class="wevoi-bank-status-yes" style="color:#00a32a;font-weight:600;"><?php esc_html_e('On file', 'wevoi-competition-manager'); ?></span>
                            <?php else : ?>
                                <span class="wevoi-bank-status-no" style="color:#646970;"><?php esc_html_e('Not provided', 'wevoi-competition-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="wevoi-bank-mono"><?php echo $cell('account_holder_name'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td><?php echo $cell('bank_name'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td><?php echo $cell('branch'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td class="wevoi-bank-mono"><?php echo $cell('iban'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td class="wevoi-bank-mono"><?php echo $cell('swift_bic'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td class="wevoi-bank-mono"><?php echo $cell('sort_code'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td><?php echo $cell('currency'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.wevoi-supervisor-banks-wrap .wevoi-bank-mono { font-family: Consolas, Monaco, monospace; font-size: 12px; word-break: break-all; }
.wevoi-supervisor-banks-wrap tr.wevoi-bank-row-empty td { background: #fcfcfc; }
.wevoi-supervisor-banks-wrap .wp-list-table th,
.wevoi-supervisor-banks-wrap .wp-list-table td { vertical-align: top; }
</style>
