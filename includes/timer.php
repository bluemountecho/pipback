<?php

add_filter('cron_schedules', 'pipback_custom_cron_interval');
function pipback_custom_cron_interval($schedules) {
    $schedules['every_six_hours'] = [
        'interval' => 21600,
        'display'  => __('Every 6 Hours'),
    ];
    return $schedules;
}

add_action('wp', function() {
    if (!wp_next_scheduled('pipback_check_cashback_expiry')) {
        wp_schedule_event(time(), 'every_six_hours', 'pipback_check_cashback_expiry');
    }
});

add_action('pipback_check_cashback_expiry', 'pipback_process_cashback_to_full_balance');

function pipback_process_cashback_to_full_balance() {
    global $wpdb;

    $table = $wpdb->prefix . 'cashbackrequests';

    $rows = $wpdb->get_results("
        SELECT * FROM $table 
        WHERE status = 'APPROVED' 
        AND hide = 0 
        AND is_charged = 0
        AND status_changed_at IS NOT NULL
        AND DATE_ADD(status_changed_at, INTERVAL 30 DAY) <= NOW()
    "); 

    foreach ($rows as $row) {
        $user_id = $row->user_id;
        $amount  = floatval($row->amount);

        $pending = floatval(get_user_meta($user_id, 'pending_balance', true));
        $full    = floatval(get_user_meta($user_id, 'full_balance', true));
        
        $new_pending = max(0, $pending - $amount);
        $new_full = $full + $amount;

        update_user_meta($user_id, 'pending_balance', $new_pending);
        update_user_meta($user_id, 'full_balance', $new_full);
        $wpdb->update($table, ['is_charged' => 1], ['id' => $row->id]);

        $wpdb->insert(
            $log_table,
            [
                'user_id'     => $user_id,
                'amount'      => $amount,
                'moved_at'    => current_time('mysql'),
                'action_type' => 'cron_job',
            ]
        );
    }
}
