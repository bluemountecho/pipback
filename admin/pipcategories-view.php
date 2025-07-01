<?php

function pipback_pipcategories_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pipcategories';

    // Handle add form
    if (isset($_POST['pip_category_submit']) && check_admin_referer('save_pip_category')) {
        $title = sanitize_text_field($_POST['title']);
        if (!empty($title)) {
            $wpdb->insert($table_name, ['title' => $title]);
            echo '<div class="notice notice-success is-dismissible"><p>Category added.</p></div>';
        }
    }

    if (isset($_POST['id'], $_POST['title']) && check_admin_referer('edit_pip_category_' . $_POST['id'])) {
        $updated_title = sanitize_text_field($_POST['title']);
        $category_id = absint($_POST['id']);
    
        if (!empty($updated_title)) {
            $wpdb->update(
                $table_name,
                ['title' => $updated_title],
                ['id' => $category_id]
            );
            echo '<div class="notice notice-success is-dismissible"><p>Category updated successfully.</p></div>';
        }
    }

    
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
        $id = absint($_GET['id']);
    
        if (current_user_can('manage_options') && check_admin_referer('delete_pip_category_' . $id)) {
            $deleted = $wpdb->delete($wpdb->prefix . 'pipcategories', ['id' => $id]);
            if ($deleted !== false) {
                echo `<a href="<?php echo esc_url(admin_url('admin.php?page=pipback-pipcategories')); ?>" class="button">Back</a>`;
                echo '<div class="notice notice-success is-dismissible"><p>Category deleted successfully.</p></div>';
            } else {
                echo `<a href="<?php echo esc_url(admin_url('admin.php?page=pipback-pipcategories')); ?>" class="button">Back</a>`;
                echo '<div class="notice notice-error is-dismissible"><p>Failed to delete category.</p></div>';
            }
        }
    }

    // Edit form
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
        global $wpdb;
        $id = absint($_GET['id']);
        $table = $wpdb->prefix . 'pipcategories';
    
        $category = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
    
        if (!$category) {
            echo '<div class="notice notice-error"><p>Category not found.</p></div>';
        } else {
            // Show edit form
            ?>
            <div class="wrap">
                <h1>Edit Prop Firm Category</h1>
                <form method="post">
                    <?php wp_nonce_field('edit_pip_category_' . $id); ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($category['id']); ?>">
                    <div style="display: flex; align-items: center; padding: 20px 0;">
                        <label for="title">Title:</label>
                        <input name="title" type="text" id="title" value="<?php echo esc_attr($category['title']); ?>" class="regular-text" required>
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 1rem;">
                        <input type="submit" class="button button-primary" value="Update Category" />
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pipback-pipcategories')); ?>" class="button">Cancel</a>
                    </div>
                </form>
            </div>
            <?php
            return; // Stop here so table doesn't render
        }
    }

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading" style="margin-bottom: 20px;">Prop Firm Categories</h1>';
    echo '<button id="add-new-category" class="button button-primary">Add New Category</button>';

    // Modal form
    ?>
    <div id="pip-category-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:#fff; padding:20px; max-width:500px; width:90%; border-radius:8px; position:relative;">
            <h2 style="text-align: center;">Add New Prop Firm Category</h2>
            <form method="post">
                <?php wp_nonce_field('save_pip_category'); ?>
                <div style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
                    <label for="title">Title:</label>
                    <input name="title" type="text" id="title" value="" class="regular-text" required>
                </div>
                <div style="display: flex; align-items: baseline; gap: 1rem; justify-content: end;">
                    <?php submit_button('Add Category', 'primary', 'pip_category_submit'); ?>
                    <button type="button" class="button" id="close-category-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.getElementById('add-new-category').addEventListener('click', function() {
        document.getElementById('pip-category-modal').style.display = 'flex';
    });
    document.getElementById('close-category-modal').addEventListener('click', function() {
        document.getElementById('pip-category-modal').style.display = 'none';
    });
    document.getElementById('pip-category-modal').addEventListener('click', function (e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
    </script>
    <?php

    // Show table
    if (class_exists('Pip_Categories_List_Table')) {
        echo '<form method="post">';
        $table = new Pip_Categories_List_Table();
        $table->prepare_items();
        $table->display();
        echo '</form>';
    } else {
        echo '<p>Pip_Categories_List_Table class not found.</p>';
    }
    echo '</div>';
}


