<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wevoi_email_snippets;
$snippets = $wevoi_email_snippets->get_snippets();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Email Snippets', 'wevoi-competition-manager'); ?></h1>
    <a href="?page=wevoi-competitions&tab=email-snippets&action=add" class="page-title-action">
        <?php _e('Add New', 'wevoi-competition-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <?php
    $message = isset($_GET['message']) ? intval($_GET['message']) : 0;
    $messages = array(
        1 => __('Snippet added successfully.', 'wevoi-competition-manager'),
        2 => __('Snippet updated successfully.', 'wevoi-competition-manager'),
        3 => __('Snippet deleted successfully.', 'wevoi-competition-manager'),
        4 => __('Error occurred.', 'wevoi-competition-manager')
    );
    if ($message && isset($messages[$message])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-id"><?php _e('#', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="manage-column column-name"><?php _e('Snippet Name', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="manage-column column-target"><?php _e('For Whom', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="manage-column column-date"><?php _e('Date Created', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'wevoi-competition-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($snippets): ?>
<?php foreach ($snippets as $snippet): ?>
    <tr>
        <td><?php echo esc_html($snippet->id); ?></td>
        <td><?php echo esc_html($snippet->snippet_name); ?></td>
        <td><?php echo esc_html($snippet->target_group); ?></td>
        <td><?php echo date_i18n(get_option('date_format'), strtotime($snippet->date_created)); ?></td>
        <td>
            <a href="?page=wevoi-competitions&tab=email-snippets&action=edit&id=<?php echo $snippet->id; ?>" 
               class="button button-small">
                <?php _e('Edit', 'wevoi-competition-manager'); ?>
            </a>
            <a href="javascript:void(0);" 
               class="button button-small button-link-delete delete-snippet" 
               data-id="<?php echo $snippet->id; ?>"
               data-nonce="<?php echo wp_create_nonce('delete_snippet_' . $snippet->id); ?>">
                <?php _e('Delete', 'wevoi-competition-manager'); ?>
            </a>
        </td>
    </tr>
<?php endforeach; ?>
                
                
            <?php else: ?>
                <tr>
                    <td colspan="5"><?php _e('No snippets found.', 'wevoi-competition-manager'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.delete-snippet').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to delete this snippet?', 'wevoi-competition-manager'); ?>')) {
            const id = $(this).data('id');
            const nonce = $(this).data('nonce');
            
            // AJAX ile silme işlemi
            $.post(ajaxurl, {
                action: 'delete_snippet_ajax',
                id: id,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    // Başarılı silme işlemi sonrası sayfayı yenile
                    window.location.href = '?page=wevoi-competitions&tab=email-snippets&message=3';
                } else {
                    alert(response.data || 'Error deleting snippet');
                }
            }).fail(function() {
                alert('<?php _e('Error deleting snippet. Please try again.', 'wevoi-competition-manager'); ?>');
            });
        }
    });
});
</script>