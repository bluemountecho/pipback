<?php

function render_faq_groups_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'firm_faq_groups';

    // Handle form submission
    if (isset($_POST['faq_group_nonce']) && wp_verify_nonce($_POST['faq_group_nonce'], 'save_faq_group')) {
        $firm_name = sanitize_text_field($_POST['firm_name']);
        $group_title = sanitize_text_field($_POST['group_title']);
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

        if (!empty($firm_name) && !empty($group_title)) {
            if ($group_id > 0) {
                $wpdb->update($table, [
                    'firm_name' => $firm_name,
                    'title'     => $group_title,
                ], ['id' => $group_id]);

                echo '<div class="notice notice-success"><p>FAQ group updated.</p></div>';
            } else {
                $wpdb->insert($table, [
                    'firm_name' => $firm_name,
                    'title'     => $group_title,
                ]);

                echo '<div class="notice notice-success"><p>FAQ group added.</p></div>';
            }
        }
    }

    // Handle delete
    if (isset($_GET['delete_group']) && current_user_can('manage_options')) {
        $delete_id = intval($_GET['delete_group']);
        $wpdb->delete($table, ['id' => $delete_id]);
        echo '<div class="notice notice-success"><p>FAQ group deleted.</p></div>';
    }

    // Load all groups
    $groups = $wpdb->get_results("SELECT * FROM $table ORDER BY firm_name ASC, title ASC", ARRAY_A);

    // If editing
    $editing = isset($_POST['edit_group']);
    $edit_group = ['id' => '', 'firm_name' => '', 'title' => ''];
    if ($editing) {
        $edit_id = intval($_POST['edit_group']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id), ARRAY_A);
        if ($row) {
            $edit_group = $row;
        }
    }
    ?>

    <div class="wrap">
        <h1><?php echo $editing ? 'Edit FAQ Group' : 'Add New FAQ Group'; ?></h1>
        <form method="post" id="save-faq-group-form">
            <input type="hidden" value="save_faq_group" name="action" />
            <?php wp_nonce_field('save_faq_group', 'faq_group_nonce'); ?>
            <?php if ($editing): ?>
                <input type="hidden" name="group_id" value="<?php echo esc_attr($edit_group['id']); ?>">
            <?php endif; ?>
            <table class="form-table" style="background: white; border-radius: 5px;">
                <tr>
                    <th style="padding: 20px 10px;"><label for="firm_name">Firm Name</label></th>
                    <td><input type="text" name="firm_name" id="firm_name" class="regular-text" required value="<?php echo esc_attr($edit_group['firm_name']); ?>"></td>
                </tr>
                <tr>
                    <th style="padding: 20px 10px;"><label for="group_title">Group Title</label></th>
                    <td><input type="text" name="group_title" id="group_title" class="regular-text" required value="<?php echo esc_attr($edit_group['title']); ?>"></td>
                </tr>
            </table>
            <div style="display: flex; align-items: baseline; gap: 10px;">
                <button class="button button-primary"><?php echo $editing ? 'Update Group' : 'Add Group'; ?></button>
                <button type="button" class="button" id="close-edit-faq-modal">Cancel</button>
            </div>
        </form>
    </div>
<script>
jQuery(function($){
    $('#close-edit-faq-modal').on('click', function () {
        $('#edit-faq-group-modal').fadeOut();
    })

    $('#save-faq-group-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const message = $('#faq-message').text('Saving...');

        $.post(ajaxurl, form.serialize(), function(res) {
            console.log(res)
            if (res.success) {
                $('#edit-faq-group-modal').fadeOut();
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'load_faq_group_table',
                    },
                    success: function (response) {
                        $('#faq-group-table-div').html(response);
                    }
                });
            } else {
                message.css('color', 'red').text(res.data?.message || 'Error.');
            }
        }).fail(function() {
            message.css('color', 'red').text('AJAX error.');
        });
    })
});
</script>
    <?php
}
