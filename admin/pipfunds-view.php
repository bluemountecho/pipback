<?php
function pipback_pipfunds_page() {
    global $wpdb;

    $table = $wpdb->prefix . 'pipfunds';
    $category_table = $wpdb->prefix . 'pipcategories';
    $faq_table = $wpdb->prefix . 'firm_faqs';

    // Fetch all FAQs
    $faqs = $wpdb->get_results("SELECT * FROM $faq_table ORDER BY title ASC");
    // Fetch categories for dropdown
    $categories = $wpdb->get_results("SELECT id, title FROM $category_table ORDER BY title ASC");
    
    $faq_table_list = new Pip_Firm_FAQs_Table();
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pipback_save_fund']) && check_admin_referer('save_pip_fund')) {
        $image_url = '';
        if (!empty($_FILES['image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded = wp_handle_upload($_FILES['image'], ['test_form' => false]);

            if (!isset($uploaded['error'])) {
                $image_url = $uploaded['url'];
            }
        }
        $wpdb->insert($table, [
            'title'              => sanitize_text_field($_POST['title']),
            'slug'               => sanitize_title($_POST['slug']),
            'cashback'           => intval($_POST['cashback']),
            'discount'           => intval($_POST['discount']),
            'review'             => floatval($_POST['review']),
            'description'        => sanitize_textarea_field($_POST['description']),
            'second_description' => sanitize_textarea_field($_POST['second_description']),
            'faq_ids'            => isset($_POST['faq_ids']) ? implode(',', array_map('intval', $_POST['faq_ids'])) : '',
            'link'               => esc_url_raw($_POST['link']),
            'image_link'         => esc_url_raw($image_url),
            'category_id'        => intval($_POST['category_id']),
            'created_by'         => get_current_user_id(),
            'first_time'         => isset($_POST['first_time']) && $_POST['first_time'] == 'on' ? 1 : 0,
            'created_at'         => current_time('mysql'),
        ]);

        echo '<div class="notice notice-success is-dismissible"><p>Fund added successfully.</p></div>';
    }

    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['fund_id'])) {
        $fund_id = intval($_GET['fund_id']);
        $fund = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pipfunds WHERE id = %d", $fund_id), ARRAY_A);
    
        if (!$fund) {
            echo '<div class="error notice"><p>Fund not found.</p></div>';
            return;
        }
    
        // Handle update form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pip_fund_update'])) {
            $image_url = $fund['image_link']; // Preserve old image if not changed
    
            if (!empty($_FILES['image']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $uploaded = wp_handle_upload($_FILES['image'], ['test_form' => false]);
    
                if (!isset($uploaded['error'])) {
                    $image_url = $uploaded['url'];
                }
            }
    
            $wpdb->update($table, [
                'title'              => sanitize_text_field($_POST['title']),
                'slug'               => sanitize_title($_POST['slug']),
                'cashback'           => intval($_POST['cashback']),
                'discount'           => intval($_POST['discount']),
                'review'             => floatval($_POST['review']),
                'description'        => sanitize_textarea_field($_POST['description']),
                'second_description' => sanitize_textarea_field($_POST['second_description']),
                'faq_ids'            => isset($_POST['faq_ids']) ? implode(',', array_map('intval', $_POST['faq_ids'])) : '',
                'link'               => esc_url_raw($_POST['link']),
                'image_link'         => esc_url_raw($image_url),
                'category_id'        => intval($_POST['category_id']),
                'created_by'         => get_current_user_id(),
                'first_time'         => isset($_POST['first_time']) && $_POST['first_time'] == 'on' ? 1 : 0,
                'created_at'         => current_time('mysql'),
            ], ['id' => $fund_id]);
    
            echo '<div class="notice notice-success is-dismissible"><p>Fund updated successfully.</p></div>';
    
            $fund = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pipfunds WHERE id = %d", $fund_id), ARRAY_A);
        }
    
        ?>
        <div class="wrap">
            <h2>Edit Prop Firm</h2>
            <a href="<?php echo admin_url('admin.php?page=pipback-pipfunds'); ?>">Back</a>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo esc_attr($fund['id']); ?>" />
                <table class="form-table">
                    <tr>
                        <th><label for="title">Title</label></th>
                        <td><input type="text" name="title" value="<?php echo esc_attr($fund['title']); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="slug">Slug</label></th>
                        <td><input type="text" name="slug" value="<?php echo esc_attr($fund['slug']); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="cashback">Cashback (%)</label></th>
                        <td><input type="number" step="0.01" name="cashback" value="<?php echo esc_attr($fund['cashback']); ?>" class="small-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="cashback">Discount (%)</label></th>
                        <td><input type="number" step="0.01" name="discount" value="<?php echo esc_attr($fund['discount']); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="review">Review (out of 5)</label></th>
                        <td><input type="number" step="0.1" max="5" name="review" value="<?php echo esc_attr($fund['review']); ?>" class="small-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="category_id">Category</label></th>
                        <td>
                            <select name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->id); ?>" <?php selected($cat->id, $fund['category_id']); ?>>
                                        <?php echo esc_html($cat->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="image">Image</label></th>
                        <td>
                            <?php if (!empty($fund['image_link'])): ?>
                                <p><img src="<?php echo esc_url($fund['image_link']); ?>" style="max-width:150px;" /></p>
                            <?php endif; ?>
                            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" />
                            <p class="description">Leave empty to keep existing image.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td><textarea name="description" rows="4" class="large-text"><?php echo esc_textarea($fund['description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="second_description">Longer Description</label></th>
                        <td><textarea name="second_description" rows="3" class="large-text"><?php echo esc_textarea($fund['second_description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="link">Link</label></th>
                        <td><input type="url" name="link" class="regular-text" value="<?php echo esc_attr($fund['link']); ?>"></td>
                    </tr>
                    <?php $selected_faqs = explode(',', $fund['faq_ids']); ?>
                     <tr>
                        <th><label for="faq_ids">FAQs</label></th>
                        <td>
                            <input type="text" readonly class="faq-select-field" placeholder="Click to select FAQs" style="width:100%; cursor:pointer;" value="<?php echo esc_attr( implode(', ', array_map(function($id) use ($faqs) {
                                    foreach ($faqs as $f) if ($f->id == $id) return $f->title;
                                    return '';
                                }, explode(',', $fund['faq_ids']))) ); ?>" />
                            <input type="hidden" name="faq_ids[]" id="faq-hidden-values" value="<?php echo esc_attr($fund['faq_ids']); ?>">
                            <div id="faq-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center; padding-top:50px;">
                                <div style="background:#fff; padding:20px; width:90%; max-width:900px; max-height:85vh; overflow:auto; border-radius:8px;">
                                    <h2>Select FAQs</h2>
                                    <form method="get" id="faq-list-table-form">
                                        <input type="hidden" name="page" value="pipback-firm-faqs">
                                        <?php $faq_table_list->prepare_items(); ?>
                                        <?php $faq_table_list->display(); ?>
                                        <p style="margin-top: 10px;">
                                            <button type="button" class="button button-primary" id="apply-selected-faqs">Apply FAQs</button>
                                            <button type="button" class="button" id="close-faq-modal">Close</button>
                                        </p>
                                    </form>
                                </div>
                            </div>
                            <p class="description">Click to select or add FAQs</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="code">First Time</label></th>
                        <td><input type="checkbox" name="first_time"  <?php checked( $fund['first_time'] ); ?>  class="regular-text"></td>
                    </tr>
                </table>
                <p>
                    <input type="submit" name="pip_fund_update" class="button button-primary" value="Update Fund">
                    <a href="<?php echo admin_url('admin.php?page=pipback-pipfunds'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>

        <script>
        jQuery(function($){
            let selectedIds = $('#faq-hidden-values').val().split(',').filter(id => id);

            function refreshCheckboxes() {
                $('.faq-checkbox').each(function() {
                    const id = $(this).val();
                    $(this).prop('checked', selectedIds.includes(id));
                });
            }

            $('.faq-select-field').on('click', function() {
                refreshCheckboxes();
                $('#faq-modal').fadeIn();
            });

            $('#close-faq-modal').on('click', function() {
                $('#faq-modal').fadeOut();
            });

            $('#apply-selected-faqs').on('click', function() {
                let newSelected = [];
                let newTitles = [];
                $('#faq-list-table-form input.faq-checkbox:checked').each(function() {
                    newSelected.push($(this).val());
                    newTitles.push($(this).closest('tr').find('td.column-title').text().trim());
                });

                $('#faq-hidden-values').val(newSelected.join(','));
                $('.faq-select-field').val(newTitles.join(', '));
                $('#faq-modal').fadeOut();
            });
        });
        </script>
        <?php
        
        return;
    }

    // Handle AJAX FAQ Save
    if (isset($_POST['action']) && $_POST['action'] === 'save_faq' && current_user_can('manage_options')) {
        $title = sanitize_text_field($_POST['faq_title']);
        $content = wp_kses_post($_POST['faq_content']);
        $wpdb->insert($faq_table, [
            'title' => $title,
            'content' => $content,
            'created_at' => current_time('mysql')
        ]);
        $new_id = $wpdb->insert_id;
        $new_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM $faq_table WHERE id = %d", $new_id));
        wp_send_json_success([ 'id' => $new_id, 'title' => $new_title ]);
    }

    echo '<div class="wrap">';
    echo '<h1 style="margin-bottom: 20px;">Prop Firms</h1>';
    echo '<button id="add-new-fund" class="button button-primary">Add New Prop Firm</button>';
    // Modal HTML
    ?>
    <div id="pip-fund-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:#fff; padding:10px 20px; max-width:600px; width:90%; border-radius:8px; position:relative; max-height: 92vh; height: 100%; overflow: auto;">
            <h2 style="text-align:center;">Add New Prop Firm</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('save_pip_fund'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="title">Title</label></th>
                        <td><input type="text" name="title" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="slug">Slug</label></th>
                        <td><input type="text" name="slug" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="cashback">Cashback (%)</label></th>
                        <td><input type="number" step="0.01" name="cashback" class="small-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="cashback">Discount (%)</label></th>
                        <td><input type="number" step="0.01" name="discount" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="review">Review (out of 5)</label></th>
                        <td><input type="number" step="0.1" max="5" name="review" class="small-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="category_id">Category</label></th>
                        <td>
                            <select name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->id); ?>"><?php echo esc_html($cat->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="image">Image</label></th>
                        <td>
                            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td><textarea name="description" class="large-text" rows="4"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="second_description">Longer Description</label></th>
                        <td><textarea name="second_description" class="large-text" rows="3"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="link">Link</label></th>
                        <td><input type="url" name="link" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="faq_ids">FAQs</label></th>
                        <td>
                            <select name="faq_ids[]" class="faq-select2" multiple="multiple" style="width: 100%;">
                                <?php foreach ($faqs as $faq): ?>
                                    <option value="<?php echo esc_attr($faq->id); ?>">
                                        <?php echo esc_html($faq->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Search and select multiple FAQs.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="code">First Time</label></th>
                        <td><input type="checkbox" name="first_time" class="regular-text"></td>
                    </tr>
                </table>
                <div class="form-group">
                    <input type="submit" class="button button-primary" name="pipback_save_fund" value="Add Prop Firm">
                    <button type="button" class="button" id="close-fund-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <style>
        .form-group {
            display: flex;
            margin-bottom: 1rem;
            gap: 0.5rem;
            justify-content: end;
            align-items: center;
        }
    </style>
    <script>
        jQuery(document).ready(function($) {
            // Show modal
            $('#add-new-fund').on('click', function() {
                $('#pip-fund-modal').css('display', 'flex');
                const $select = $(".faq-select2");

                // Destroy previous instance if exists
                if ($select.hasClass("select2-hidden-accessible")) {
                    $select.select2('destroy');
                }

                // Reinitialize Select2
                $select.select2({
                    placeholder: "Select FAQs",
                    allowClear: true,
                    width: "100%",
                    multiple: true,
                    tags: false,
                    dropdownParent: $('#pip-fund-modal')
                });
            });
            
            // Close modal handlers
            $('#close-fund-modal, #cancel-fund-modal').on('click', function() {
                $('#pip-fund-modal').hide();
            });
            
            // Close when clicking outside modal
            $('#pip-fund-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
        });
    </script>
    <?php

    // Show WP List Table
    if (class_exists('Pip_Funds_List_Table')) {
        echo '<form method="post">';
        $table = new Pip_Funds_List_Table();
        $table->process_bulk_action();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <!-- Add drag-and-drop UI -->
            <div id="pipfunds-drag-container">
                <form method="post">
                    <?php $table->display(); ?>
                </form>
            </div>
        </div>
        
        <style>
        #the-list tr {
            cursor: move;
        }
        #the-list tr.ui-sortable-helper {
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        </style>
        <?php
        echo '</form>';
    } else {
        echo '<p><strong>Error:</strong> Pip_Funds_List_Table class not found.</p>';
    }

    if (class_exists('Pip_Form_Fields') && (current_user_can('manage_options') || current_user_can('manage_pip_funds'))) {
        $pip_form_fields = new Pip_Form_Fields();
        $pip_form_fields->render_admin_page();
    }

    echo '</div>';
}

function pipback_enqueue_admin_scripts($hook) {
    if ($hook != 'toplevel_page_pipback-pipfunds' && $hook != 'pipback_page_pipback-pipfunds') {
        return;
    }

    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
    
    wp_add_inline_script('select2-js', '
        jQuery(document).ready(function($) {
            $(".faq-select2").select2({
                placeholder: "Select FAQs",
                allowClear: true,
                width: "100%",
                multiple: true,
                tags: false,
            });
        });
    ');
}
add_action('admin_enqueue_scripts', 'pipback_enqueue_admin_scripts');