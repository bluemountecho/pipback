<?php

add_action('wp_ajax_pipback_cashback_action', 'pipback_cashback_action_handler');

function pipback_cashback_action_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cashbackrequests';
    $id = intval($_POST['request_id']);
    $action = sanitize_text_field($_POST['sub_action']);

    if (!$id || !$action) {
        wp_send_json_error('Invalid request');
    }

    if ($action === 'approve') {
        $wpdb->update($table, [
            'status' => 'APPROVED',
            'status_changed_by' => get_current_user_id(),
            'status_changed_at' => current_time('mysql'),
        ], ['id' => $id]);
    } elseif ($action === 'decline') {
        $deny_reason = sanitize_textarea_field($_POST['deny_reason'] ?? '');
        error_log('Deny reason received: ' . $_POST['deny_reason']);
        $wpdb->update($table, [
            'status' => 'DENIED',
            'status_changed_by' => get_current_user_id(),
            'status_changed_at' => current_time('mysql'),
            'deny_reason' => '$deny_reason',
        ], ['id' => $id]);
    } elseif ($action === 'hide') {
        $wpdb->update($table, ['hide' => 1], ['id' => $id]);
    } else {
        wp_send_json_error('Unknown action');
    }

    wp_send_json_success();
}
