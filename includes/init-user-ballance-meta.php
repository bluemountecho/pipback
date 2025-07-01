<?php

add_action('wp_login', 'pipback_initialize_user_balances', 10, 2);

function pipback_initialize_user_balances($user_login, $user) {
    $user_id = $user->ID;

    if (get_user_meta($user_id, 'pending_balance', true) === '') {
        update_user_meta($user_id, 'pending_balance', 0);
    }

    if (get_user_meta($user_id, 'full_balance', true) === '') {
        update_user_meta($user_id, 'full_balance', 0);
    }
}