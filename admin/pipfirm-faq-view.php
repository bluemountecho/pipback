<?php

function render_faqs_admin_page() {
    global $wpdb;

    $table = $wpdb->prefix . 'firm_faqs';
    $groups_table = $wpdb->prefix . 'firm_faq_groups';

    if (isset($_POST['faq_nonce']) && wp_verify_nonce($_POST['faq_nonce'], 'save_faq')) {
        if (!current_user_can('manage_options')) return;

        $title     = sanitize_text_field($_POST['faq_title']);
        $content   = wp_kses_post($_POST['faq_content']);
        $group_id  = intval($_POST['group_id']);

        if (!empty($title) && !empty($content) && $group_id > 0) {
            if (!empty($_POST['faq_id'])) {
                // EDIT mode
                $id = intval($_POST['faq_id']);
                $wpdb->update($table, [
                    'title'    => $title,
                    'content'  => $content,
                    'group_id' => $group_id,
                ], ['id' => $id]);

                echo '<div class="notice notice-success"><p>FAQ updated successfully.</p></div>';
            } else {
                // ADD mode
                $wpdb->insert($table, [
                    'title'    => $title,
                    'content'  => $content,
                    'group_id' => $group_id,
                ]);
                echo '<div class="notice notice-success"><p>FAQ added successfully.</p></div>';
            }
        }
    }

    // Load groups for dropdown
    $groups = $wpdb->get_results("SELECT id, title FROM $groups_table ORDER BY title ASC", ARRAY_A);

    // Check if we're editing an existing FAQ
    $is_edit = isset($_GET['faq_action'], $_GET['id']) && $_GET['faq_action'] === 'edit';
    $faq_data = ['id' => '', 'title' => '', 'content' => '', 'group_id' => ''];

    if ($is_edit) {
        $id = intval($_GET['id']);
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

        <form method="post">
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
                                <option value="<?php echo esc_attr($group['id']); ?>"
                                    <?php selected($faq_data['group_id'], $group['id']); ?>>
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
                                'teeny'         => false,
                                'quicktags'     => true,
                            ]
                        );
                        ?>
                    </td>
                </tr>
            </table>
            <div style="display: flex; align-items: baseline; gap: 10px;">
                <?php submit_button($is_edit ? 'Update FAQ' : 'Save FAQ'); ?>
                <?php if ($is_edit): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pipback-firm-faqs')); ?>" class="button">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="wrap">
        <h2>All FAQs</h2>
        <style>
            .wp-list-table .column-actions {
                width: 200px;
                white-space: nowrap;
                text-align: center;
                vertical-align: middle;
            }
            .wp-list-table .column-title {
                text-align: center;
                vertical-align: middle;
            }
            .wp-list-table .column-group {
                text-align: center;
                vertical-align: middle;
            }
        </style>
        <form method="get">
            <input type="hidden" name="page" value="pipback-firm-faqs" />
            <?php
            $table_list = new Pip_Firm_FAQs_Table();
            $table_list->prepare_items();
            $table_list->display();
            ?>
        </form>
    </div>
    <?php
}
