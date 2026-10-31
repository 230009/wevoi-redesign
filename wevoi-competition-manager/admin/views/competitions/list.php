<?php
// Direct access kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// List Table sınıfını yükle
require_once WEVOI_CM_PLUGIN_DIR . 'admin/class-competitions-list-table.php';

// Tablo örneğini oluştur
$competitionsTable = new Competitions_List_Table();
$competitionsTable->prepare_items();

// Mesaj parametrelerini kontrol et
$message = isset($_GET['message']) ? intval($_GET['message']) : 0;
$messages = [
    1 => __('Competition added successfully.', 'wevoi-competition-manager'),
    2 => __('Competition updated successfully.', 'wevoi-competition-manager'),
    3 => __('Competition(s) deleted successfully.', 'wevoi-competition-manager'),
    4 => __('Error occurred.', 'wevoi-competition-manager')
];
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Competitions', 'wevoi-competition-manager'); ?></h1>
    <a href="?page=<?php echo $_REQUEST['page']; ?>&action=add" class="page-title-action">
        <?php _e('Add New', 'wevoi-competition-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <?php
    // Mesaj gösterimi
    if ($message && isset($messages[$message])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <div class="notice notice-info wevoi-competition-workflow">
        <h2><?php _e('How to Finalize Results (After Jury Evaluation)', 'wevoi-competition-manager'); ?></h2>
        <p><?php _e('Follow these steps in order once all judges have finished evaluating. Do not skip steps — results are not available until rankings are saved, and public visibility requires COMPLETED status.', 'wevoi-competition-manager'); ?></p>
        <ol>
            <li>
                <strong><?php _e('Confirm evaluations are complete', 'wevoi-competition-manager'); ?></strong>
                — <?php _e('While status is EVALUATING, judges can still submit scores. Finish all jury evaluations first.', 'wevoi-competition-manager'); ?>
            </li>
            <li>
                <strong><?php _e('Change status to RANKINGS_FINALIZED', 'wevoi-competition-manager'); ?></strong>
                — <?php _e('Edit the competition and set Status to RANKINGS_FINALIZED, then save. This locks further judge submissions.', 'wevoi-competition-manager'); ?>
            </li>
            <li>
                <strong><?php _e('Open Competition Rankings', 'wevoi-competition-manager'); ?></strong>
                — <?php _e('Go to the Rankings admin page. Rankings are calculated automatically when you open the page. The Save Rankings button appears only after status is RANKINGS_FINALIZED.', 'wevoi-competition-manager'); ?>
            </li>
            <li>
                <strong><?php _e('Save Rankings', 'wevoi-competition-manager'); ?></strong>
                — <?php _e('Review the rankings shown on the page, then write points, ranks, and prize data to the database. Required before exporting certificates or results.', 'wevoi-competition-manager'); ?>
            </li>
            <li>
                <strong><?php _e('Export (optional)', 'wevoi-competition-manager'); ?></strong>
                — <?php _e('Use Export/Import for certificates, Top 5, competition results, or bank details for Top-3 winners.', 'wevoi-competition-manager'); ?>
            </li>
            <li>
                <strong><?php _e('Mark Award Ceremony as Completed → COMPLETED', 'wevoi-competition-manager'); ?></strong>
                — <?php _e('After the ceremony, use “Mark Award Ceremony as Completed” on the Rankings page. Status becomes COMPLETED and results become visible to students, supervisors, and public result pages.', 'wevoi-competition-manager'); ?>
            </li>
        </ol>
        <p class="wevoi-competition-workflow-summary">
            <strong><?php _e('Status flow:', 'wevoi-competition-manager'); ?></strong>
            <?php _e('EVALUATING → RANKINGS_FINALIZED → (Save Rankings) → COMPLETED', 'wevoi-competition-manager'); ?>
        </p>
    </div>

    <!-- Arama ve filtreleme formu -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
        <?php if (isset($_REQUEST['tab'])) : ?>
            <input type="hidden" name="tab" value="<?php echo $_REQUEST['tab']; ?>">
        <?php endif; ?>

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="status_filter">
                    <option value=""><?php _e('All Statuses', 'wevoi-competition-manager'); ?></option>
                    <?php
                    $statuses = [
                        'NOT YET', 'CONFIRMED', 'ON AIR', 'IN THE QUE',
                        'CLOSED', 'CANCELLED', 'POSTPONED', 'COMPLETED'
                    ];
                    $current_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
                    foreach ($statuses as $status) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($status),
                            selected($current_status, $status, false),
                            esc_html($status)
                        );
                    }
                    ?>
                </select>

                <select name="date_filter">
                    <option value=""><?php _e('All Dates', 'wevoi-competition-manager'); ?></option>
                    <option value="upcoming" <?php selected(isset($_GET['date_filter']) ? $_GET['date_filter'] : '', 'upcoming'); ?>>
                        <?php _e('Upcoming', 'wevoi-competition-manager'); ?>
                    </option>
                    <option value="past" <?php selected(isset($_GET['date_filter']) ? $_GET['date_filter'] : '', 'past'); ?>>
                        <?php _e('Past', 'wevoi-competition-manager'); ?>
                    </option>
                </select>

                <?php submit_button(__('Filter', 'wevoi-competition-manager'), 'action', false, false); ?>
            </div>

            <div class="alignright">
                <p class="search-box">
                    <label class="screen-reader-text" for="competition-search">
                        <?php _e('Search Competitions', 'wevoi-competition-manager'); ?>:
                    </label>
                    <input type="search" id="competition-search" name="s" value="<?php _admin_search_query(); ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, ['id' => 'search-submit']); ?>
                </p>
            </div>
        </div>

        <?php
        // Tabloyu göster
        $competitionsTable->display();
        ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Status badge'lerinin renklendirilmesi
    $('.status-badge').each(function() {
        var status = $(this).text().trim();
        switch(status) {
            case 'NOT YET':
                $(this).addClass('status-not-yet');
                break;
            case 'CONFIRMED':
                $(this).addClass('status-confirmed');
                break;
            case 'ON AIR':
                $(this).addClass('status-on-air');
                break;
            case 'IN THE QUE':
                $(this).addClass('status-in-que');
                break;
            case 'CLOSED':
                $(this).addClass('status-closed');
                break;
            case 'CANCELLED':
                $(this).addClass('status-cancelled');
                break;
            case 'POSTPONED':
                $(this).addClass('status-postponed');
                break;
            case 'EVALUATING':
                $(this).addClass('status-evaluating');
                break;
            case 'RANKINGS_FINALIZED':
                $(this).addClass('status-rankings-finalized');
                break;
            case 'COMPLETED':
                $(this).addClass('status-completed');
                break;
        }
    });
});
</script>
