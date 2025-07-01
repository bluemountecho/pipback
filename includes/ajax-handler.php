<?php

add_action('wp_ajax_pipback_cashback_action', 'pipback_cashback_action_handler');

function pipback_cashback_action_handler() {
    if (!current_user_can('manage_options') || !current_user_can('manage_cashback_requests')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cashbackrequests';
    $id = intval($_POST['request_id']);
    $action = sanitize_text_field($_POST['sub_action']);
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    
    if (!$request) {
        wp_send_json_error('Request not found');
    }
    
    $user_id = $request->user_id;
    $fund_id = isset($_POST['fund_id']) ? intval($_POST['fund_id']) : $request->fund_id;
    $user_email = get_userdata($user_id)->user_email;
    $request_id = $request->request_id;
    $user = get_userdata($user_id);

    if (!$id || !$action) {
        wp_send_json_error('Invalid request');
    }

    if ($action === 'approve') {
        $wpdb->update($table, [
            'status' => 'APPROVED',
            'status_changed_by' => get_current_user_id(),
            'status_changed_at' => current_time('mysql'),
            'amount' => $amount,
        ], ['id' => $id]);

        $pending = floatval(get_user_meta($user_id, 'pending_balance', true));
        $new_pending = $pending + $amount;
        update_user_meta($user_id, 'pending_balance', $new_pending);

        $analysis_table = $wpdb->prefix . 'pip_offer_clicks';
        $wpdb->insert($analysis_table, [
            'fund_id'    => $fund_id,
            'user_id'    => $user_id ? $user_id : null,
            'ip_address' => "-",
            'user_agent' => "-",
            'converted' => 1,
            'created_at' => current_time('mysql')
        ]);

        $to = $user_email;
        $subject = 'Cashback Request Approved';
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $amount_string = $amount.'$';
        $current_user = $user;

        $template_path = get_stylesheet_directory() . '/emails/views/cashback-request-approved.php';
        
        ob_start();
        include $template_path;
        $message = ob_get_clean();
        
        $sent = wp_mail($to, $subject, $message, $headers);

        // $affiliate_id = get_user_meta($user_id, '_referred_by_affiliate_id', true);

        // if ($affiliate_id && $amount > 0) {
        //     $referral_id = affwp_add_referral([
        //         'affiliate_id' => $affiliate_id,
        //         'reference'    => 'cashback_' . time(),
        //         'description'  => 'Cashback Earned',
        //         'amount'       => $amount,
        //         'context'      => 'cashback',
        //         'status'       => 'unpaid',
        //     ]);

        //     if ($referral_id) {
        //         affwp_set_referral_status($referral_id, 'paid');
        //     }
        // }

        $referral = affwp_get_referral_by( 'reference', $user_id, 'user_registration' );

        if ( $referral && $referral->affiliate_id && $amount > 0 ) {
            $affiliate_id = $referral->affiliate_id;

            $referral_id = affwp_add_referral([
                'affiliate_id' => $affiliate_id,
                'reference'    => $user_id,
                'description'  => 'Cashback Earned',
                'amount'       => $amount,
                'context'      => 'cashback',
                'status'       => 'unpaid',
            ]);

            if ( $referral_id ) {
                affwp_set_referral_status( $referral_id, 'paid' );
            }
        }
    } elseif ($action === 'decline') {
        $deny_reason = sanitize_textarea_field($_POST['deny_reason'] ?? '');
        $wpdb->update($table, [
            'status' => 'DENIED',
            'status_changed_by' => get_current_user_id(),
            'status_changed_at' => current_time('mysql'),
            'deny_reason' => $deny_reason,
            'amount' => $amount,
        ], ['id' => $id]);

        $to = $user_email;
        $subject = 'Cashback Request Declined';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $template_path = get_stylesheet_directory() . '/emails/views/cashback-request-declined.php';
        $reason = $deny_reason;
        $current_user = $user;

        ob_start();
        include $template_path;
        $message = ob_get_clean();

        $sent = wp_mail($to, $subject, $message, $headers);
    } elseif ($action === 'hide') {
        $wpdb->update($table, ['hide' => 1], ['id' => $id]);
    } else {
        wp_send_json_error('Unknown action');
    }

    wp_send_json_success();
}


add_action('wp_ajax_pipback_withdrawal_action', 'pipback_withdrawal_action_handler');

function pipback_withdrawal_action_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'withdrawalrequests';
    $id = intval($_POST['request_id']);
    $action = sanitize_text_field($_POST['sub_action']);

    if (!$id || !$action) {
        wp_send_json_error('Invalid request');
    }

    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

    if (!$request) {
        wp_send_json_error('Request not found');
    }

    $user_id = $request->user_id;
    $amount  = floatval($request->amount);
    $user = get_userdata($user_id);
    $user_email = get_userdata($user_id)->user_email;

    if ($action === 'approve') {
        $full_balance = floatval(get_user_meta($user_id, 'full_balance', true));
        if ($amount > $full_balance) {
            $wpdb->update($table, [
                'status' => 'DENIED',
                'status_changed_by' => get_current_user_id(),
                'status_changed_at' => current_time('mysql'),
                'deny_reason' => 'Your ballence is not enough to approve!',
            ], ['id' => $id]);
        } else {
            // Deduct balance immediately
            update_user_meta($user_id, 'full_balance', $full_balance - $amount);

            $wpdb->update($table, [
                'status' => 'APPROVED',
                'status_changed_by' => get_current_user_id(),
                'status_changed_at' => current_time('mysql'),
            ], ['id' => $id]);

            $to = $user_email;
            $subject = 'Withdrawal Request Approved';
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            $template_path = get_stylesheet_directory() . '/emails/views/withdrawal-request-approved.php';
            $formatted = number_format((float)$amount, 2, '.', '');
            list($intPart, $decimalPart) = explode('.', $formatted);
            $price_full = '$' . $intPart;
            $price_float = '.' . $decimalPart;
            
            $payment_detail = 'FSafWFFawf54f65#@F5fASDDAS#R#RWEDsad664=r3jhkyr[=i3';
            $payment_detail = strlen($payment_detail) > 30 ? substr($payment_detail, 0, 30) . '...' : $payment_detail;
            $payment_method = $request->payment_method;
            $current_user = $user;
            
            ob_start();
            include $template_path;
            $message = ob_get_clean();

            $sent = wp_mail($to, $subject, $message, $headers);
        }
    } elseif ($action === 'decline') {
        $deny_reason = sanitize_textarea_field($_POST['deny_reason'] ?? '');

        $wpdb->update($table, [
            'status' => 'DENIED',
            'status_changed_by' => get_current_user_id(),
            'status_changed_at' => current_time('mysql'),
            'deny_reason' => $deny_reason,
        ], ['id' => $id]);

        $to = $user_email;
        $subject = 'Withdrawal Request Declined';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $template_path = get_stylesheet_directory() . '/emails/views/withdrawal-request-declined.php';
        $reason = $deny_reason;
        $current_user = $user;
        
        ob_start();
        include $template_path;
        $message = ob_get_clean();

        $sent = wp_mail($to, $subject, $message, $headers);

        // Send denial email
        // wp_mail($user->user_email, 'Withdrawal Denied', "Hi {$user->display_name},\n\nYour withdrawal request has been denied.\nReason: $deny_reason");

    } elseif ($action === 'hide') {
        $wpdb->update($table, ['hide' => 1], ['id' => $id]);
    } else {
        wp_send_json_error('Unknown action');
    }


    wp_send_json_success();
}


function pipback_ajax_update_user_balance() {
    check_ajax_referer('update_balance_nonce', 'security');

     if (!current_user_can('edit_users') || !check_ajax_referer('update_balance_nonce', 'security', false)) {
        wp_send_json_error(['message' => 'Permission denied or nonce failed']);
    }

    $user_id = intval($_POST['user_id']);
    $new_pending = floatval($_POST['pending_balance']);
    $new_full = floatval($_POST['full_balance']);
    $admin_id = get_current_user_id();

    // Fetch original values
    $original_pending = floatval(get_user_meta($user_id, 'pending_balance', true));
    $original_full = floatval(get_user_meta($user_id, 'full_balance', true));

    // Update balances
    update_user_meta($user_id, 'pending_balance', $new_pending);
    update_user_meta($user_id, 'full_balance', $new_full);

    // Ensure table schema is up to date
    ensure_cashback_log_table_schema_updated();

    // Insert into log
    global $wpdb;
    $table = $wpdb->prefix . 'cashback_move_log';
    $wpdb->insert($table, [
        'user_id' => $user_id,
        'amount' => $new_full - $original_full,
        'moved_at' => current_time('mysql'),
        'action_type' => 'admin_balance_update',
        'original_pending' => $original_pending,
        'original_full' => $original_full,
        'updated_pending' => $new_pending,
        'updated_full' => $new_full,
        'changed_by' => $admin_id
    ]);

    wp_send_json_success(['message' => 'Balance updated']);
}
add_action('wp_ajax_update_user_balance_ajax', 'pipback_ajax_update_user_balance');


function ensure_cashback_log_table_schema_updated() {
    global $wpdb;
    $table = $wpdb->prefix . 'cashback_move_log';

    // Check existing columns
    $columns = $wpdb->get_col("DESC $table", 0);

    $alterations = [];

    if (!in_array('original_pending', $columns)) {
        $alterations[] = "ADD COLUMN original_pending DECIMAL(10,2) DEFAULT 0";
    }
    if (!in_array('original_full', $columns)) {
        $alterations[] = "ADD COLUMN original_full DECIMAL(10,2) DEFAULT 0";
    }
    if (!in_array('updated_pending', $columns)) {
        $alterations[] = "ADD COLUMN updated_pending DECIMAL(10,2) DEFAULT 0";
    }
    if (!in_array('updated_full', $columns)) {
        $alterations[] = "ADD COLUMN updated_full DECIMAL(10,2) DEFAULT 0";
    }
    if (!in_array('changed_by', $columns)) {
        $alterations[] = "ADD COLUMN changed_by BIGINT UNSIGNED DEFAULT NULL";
    }

    if (!empty($alterations)) {
        $sql = "ALTER TABLE $table " . implode(', ', $alterations);
        $wpdb->query($sql);
    }
}

add_action('wp_ajax_save_pipfunds_order', 'save_pipfunds_order');
function save_pipfunds_order() {
    check_ajax_referer('pipfunds_order_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    if (empty($_POST['order'])) {
        wp_send_json_error('No order data received');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pipfunds';

    foreach ($_POST['order'] as $position => $fund_id) {
        $wpdb->update(
            $table_name,
            array('display_order' => $position),
            array('id' => $fund_id),
            array('%d'),
            array('%d')
        );
    }

    wp_send_json_success();
}

add_action('wp_ajax_pipback_get_all_user_requests', function() {
    global $wpdb;
    
    $request_id = intval($_POST['request_id']);
    
    if (!$request_id) {
        wp_send_json_error('Invalid request ID');
        return;
    }
    
    $table = $wpdb->prefix . 'cashbackrequests';
    $funds_table = $wpdb->prefix . 'pipfunds';
    
    // First get the user ID from the current request
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $table WHERE id = %d",
        $request_id
    ));
    
    if (!$user_id) {
        wp_send_json_error('Request not found');
        return;
    }
    
    // Then get all requests for this user
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, f.title as fund_title 
         FROM $table r
         LEFT JOIN $funds_table f ON r.fund_id = f.id
         WHERE r.user_id = %d AND r.hide = 0
         ORDER BY r.created_at DESC",
        $user_id
    ), ARRAY_A);
    
    wp_send_json_success($results);
});


add_action('wp_ajax_get_user_payment_method_details', 'handle_get_user_payment_method_details');
function handle_get_user_payment_method_details() {
    check_ajax_referer('get_user_payment_method_details', 'nonce');
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
    
    if (!$user_id || !$payment_method) {
        wp_send_json_error([
            'message' => 'Missing required parameters'
        ], 400);
    }
    
    global $wpdb;
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT method_data FROM {$wpdb->prefix}user_payment_methods 
         WHERE user_id = %d",
        $user_id,
    ));
    
    $method_data = json_decode($result->method_data, true);
    
    if (!isset($method_data[$payment_method])) {
        wp_send_json_error([
            'message' => 'Payment method not found for this user'
        ], 404);
    }
    
    if (!$result) {
        wp_send_json_error([
            'message' => 'Payment method not found for this user'
        ], 404);
    }
    
    $response_data = [
        'method_data' => $method_data[$payment_method]
    ];
    
    
    wp_send_json_success([
        'data' => $response_data
    ]);
}