<?php
/**
 * Database tools for PipBack
 */

// Add a submenu page for database tools
add_action('admin_menu', 'pipback_add_db_tools_page');

function pipback_add_db_tools_page() {
    add_submenu_page(
        'pipback-dashboard',
        'Database Tools',
        'Database Tools',
        'manage_options',
        'pipback-db-tools',
        'pipback_db_tools_page'
    );
}

function pipback_db_tools_page() {
    // Check if the user clicked the button
    if (isset($_POST['add_is_charged_field']) && check_admin_referer('pipback_db_tools')) {
        pipback_add_is_charged_field();
    }
    
    ?>
    <div class="wrap">
        <h1>PipBack Database Tools</h1>
        
        <div class="card">
            <h2>Add 'is_charged' Field</h2>
            <p>Click the button below to add the 'is_charged' field to the cashbackrequests table.</p>
            
            <form method="post">
                <?php wp_nonce_field('pipback_db_tools'); ?>
                <input type="submit" name="add_is_charged_field" class="button button-primary" value="Add Field">
            </form>
        </div>
    </div>
    <?php
}

function pipback_add_is_charged_field() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cashbackrequests';
    
    // Check if the column already exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'is_charged'");
    
    if (empty($column_exists)) {
        // Add the column if it doesn't exist
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN is_charged TINYINT(1) DEFAULT 0");
        
        echo '<div class="notice notice-success is-dismissible"><p>The \'is_charged\' field has been added successfully.</p></div>';
    } else {
        echo '<div class="notice notice-info is-dismissible"><p>The \'is_charged\' field already exists.</p></div>';
    }
}