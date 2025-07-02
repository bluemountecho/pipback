<?php

function render_faqs_admin_page()
{
    global $wpdb;

    $table = $wpdb->prefix . 'firm_faqs';
    $groups_table = $wpdb->prefix . 'firm_faq_groups';

    // Load groups for dropdown
    $groups = $wpdb->get_results("SELECT id, title FROM $groups_table ORDER BY title ASC", ARRAY_A);

    // Check if we're editing an existing FAQ
    $is_edit = isset($_POST['faq_action'], $_POST['id']) && $_POST['faq_action'] === 'edit';
    $faq_data = ['id' => '', 'title' => '', 'content' => '', 'group_id' => ''];

    if ($is_edit) {
        $id = intval($_POST['id']);
        $faq = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);

        if ($faq) {
            $faq_data = $faq;
        } else {
            echo '<div class="notice notice-error"><p>FAQ not found.</p></div>';
        }
    }
    ?>

    <div class="wrap">
        <h1><?php echo $is_edit ? 'Edit FAQ' : 'Add FAQ'; ?></h1>

        <form id="save-faq-form" method="post">
            <input type="hidden" name="action" value="save_faq" />
            <?php wp_nonce_field('save_faq', 'faq_nonce'); ?>
            <?php if ($is_edit): ?>
                <input type="hidden" name="faq_id" value="<?php echo esc_attr($faq_data['id']); ?>">
            <?php endif; ?>

            <table class="form-table" style="background: white; border-radius: 5px;">
                <tr>
                    <th><label for="group_id" style="padding: 0 1rem">FAQ Group</label></th>
                    <td>
                        <select name="group_id" id="group_id" required>
                            <option value="">Select Group</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo esc_attr($group['id']); ?>" <?php selected($faq_data['group_id'], $group['id']); ?>>
                                    <?php echo esc_html($group['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="faq_title" style="padding: 0 1rem">Title</label></th>
                    <td><input type="text" name="faq_title" id="faq_title" class="regular-text" required
                            value="<?php echo esc_attr($faq_data['title']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="faq_content" style="padding: 0 1rem">Content</label></th>
                    <td>
                        <?php
                        wp_editor(
                            $faq_data['content'],
                            'faq_content',
                            [
                                'textarea_name' => 'faq_content',
                                'media_buttons' => false,
                                'textarea_rows' => 10,
                                'teeny' => false,
                                'quicktags' => true,
                            ]
                        );
                        ?>
                    </td>
                </tr>
            </table>
            <div style="display: flex; align-items: baseline; gap: 10px;">
                <button class="button button-primary"><?php echo $is_edit ? 'Update FAQ' : 'Save FAQ'; ?></button>
                <button type="button" class="button" id="close-edit-faq-modal">Cancel</button>
                <p id="faq-message" style="margin-top:10px;"></p>
            </div>
        </form>
    </div>
    <script>
        jQuery(function ($) {
            function refreshCheckboxes() {
                let selectedIds = $('#faq-hidden-values').val().split(',').filter(id => id);

                $('.faq-checkbox').each(function () {
                    const id = $(this).val();
                    $(this).prop('checked', selectedIds.includes(id));
                });
            }

            $('#save-faq-form').on('submit', function (e) {
                e.preventDefault();
                const form = $(this);
                const message = $('#faq-message').text('Saving...');

                $.post(ajaxurl, form.serialize(), function (res) {
                    if (res.success) {
                        $('#edit-faq-modal').fadeOut();
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'load_faq_table',
                            },
                            success: function (response) {
                                $('#faq-table-div').html(response);
                                refreshCheckboxes();
                            }
                        });
                    } else {
                        message.css('color', 'red').text(res.data?.message || 'Error.');
                    }
                }).fail(function () {
                    message.css('color', 'red').text('AJAX error.');
                });
            })
        });
    </script>
    <?php
}
