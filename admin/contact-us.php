<?php 

function display_contact_us() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    global $wpdb;
    $log_table = $wpdb->prefix . 'contact_us';

    if (isset($_GET['delete'])) {
        $id = absint($_GET['delete']);

        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_contact_' . $id)) {
            $wpdb->delete($log_table, ['id' => $id]);
            echo '<div class="notice notice-success is-dismissible"><p>Deleted request ID ' . esc_html($id) . '.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Security check failed.</p></div>';
        }
    }

    $results = $wpdb->get_results("SELECT * FROM $log_table ORDER BY id DESC");

    echo '<div class="wrap"><h1>Contact Requests</h1>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Email</th><th>Full Name</th><th>Message</th><th>Created At</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    if ($results) {
        foreach ($results as $row) {
            $delete_url = admin_url('admin.php?page=contact_us&delete=' . $row->id);
            $delete_url = wp_nonce_url($delete_url, 'delete_contact_' . $row->id);

            echo '<tr>';
            echo '<td>' . esc_html($row->email) . '</td>';
            echo '<td>' . esc_html($row->full_name) . '</td>';
            echo '<td>' . esc_html($row->message) . '</td>';
            echo '<td>' . esc_html($row->created_at) . '</td>';
            echo '<td><a href="' . esc_url($delete_url) . '" style="color: red;" onclick="return confirm(\'Are you sure you want to delete this request?\')">Delete</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">No requests found.</td></tr>';
    }

    echo '</tbody></table></div>';
}
