<?php
/**
 * Add is_charged field to cashbackrequests table
 */

function pipback_add_is_charged_field() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cashbackrequests';
    
    // Check if the column already exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'is_charged'");
    
    if (empty($column_exists)) {
        // Add the column if it doesn't exist
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN is_charged TINYINT(1) DEFAULT 0");
        
        echo "The 'is_charged' field has been added successfully.";
    } else {
        echo "The 'is_charged' field already exists.";
    }
}

// Run this function once to add the field
// pipback_add_is_charged_field();