<?php 
function display_cashback_move_log() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    global $wpdb;
    $log_table = $wpdb->prefix . 'cashback_move_log';

    $results = $wpdb->get_results("SELECT * FROM $log_table ORDER BY moved_at DESC LIMIT 100");

    echo '<div class="wrap"><h1>Cashback Move Log</h1>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>
        <th>User ID</th>
        <th>Amount</th>
        <th>Date</th>
        <th>Action Type</th>
        <th>Original Pending</th>
        <th>Original Full</th>
        <th>Updated Pending</th>
        <th>Updated Full</th>
        <th>Changed By</th>
    </tr></thead>';
    echo '<tbody>';

    if ($results) {
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->user_id) . '</td>';
            echo '<td>' . esc_html($row->amount) . '</td>';
            echo '<td>' . esc_html($row->moved_at) . '</td>';
            echo '<td>' . esc_html($row->action_type) . '</td>';
            echo '<td>' . esc_html(isset($row->original_pending) ? $row->original_pending : '-') . '</td>';
            echo '<td>' . esc_html(isset($row->original_full) ? $row->original_full : '-') . '</td>';
            echo '<td>' . esc_html(isset($row->updated_pending) ? $row->updated_pending : '-') . '</td>';
            echo '<td>' . esc_html(isset($row->updated_full) ? $row->updated_full : '-') . '</td>';
            echo '<td>' . esc_html(isset($row->changed_by) ? $row->changed_by : '-') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="9">No log entries found.</td></tr>';
    }

    echo '</tbody></table></div>';
}


function pipback_account_balance_profile_field_top($user) {
    $pending_balance = get_user_meta($user->ID, 'pending_balance', true);
    $full_balance = get_user_meta($user->ID, 'full_balance', true);

    $pending_balance = floatval($pending_balance);
    $full_balance = floatval($full_balance);

    $formatted_pending = number_format($pending_balance, 2);
    $formatted_full = number_format($full_balance, 2);

    list($pending_whole, $pending_decimal) = explode('.', $formatted_pending);
    list($full_whole, $full_decimal) = explode('.', $formatted_full);
    ?>

    <style>
        .balance-card {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
        }
        .balance-row {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f1f1f1;
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .balance-label {
            font-weight: bold;
            font-size: 16px;
        }
        .balance-value {
            font-size: 18px;
            font-weight: bold;
            color: #0073aa;
        }
        .balance-value sup {
            font-size: 13px;
            vertical-align: super;
            color: #888;
        }
        .balance-edit-btn {
            margin-top: 10px;
        }

        /* Modal styles */
        .balance-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background: rgba(0, 0, 0, 0.5);
        }
        .balance-modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 30px;
            border: 1px solid #888;
            width: 400px;
            border-radius: 8px;
            position: relative;
        }
        .balance-modal-content h3 {
            margin-top: 0;
        }
        .balance-modal-content label {
            display: block;
            margin: 10px 0 5px;
        }
        .balance-modal-content input[type="number"] {
            width: 100%;
            padding: 8px;
            font-size: 15px;
        }
        .modal-actions {
            margin-top: 20px;
            text-align: right;
        }
        .modal-actions button {
            margin-left: 10px;
        }
    </style>

    <div id="balance-card">
        <h2>PIPBack Account Balance</h2>
        <div class="balance-card">
            <div class="balance-row">
                <div class="balance-label">Pending Balance:</div>
                <div class="balance-value"><?php echo esc_html($pending_whole); ?>.<sup><?php echo esc_html($pending_decimal); ?></sup></div>
            </div>
            <div class="balance-row">
                <div class="balance-label">Full Balance:</div>
                <div class="balance-value"><?php echo esc_html($full_whole); ?>.<sup><?php echo esc_html($full_decimal); ?></sup></div>
            </div>
        </div>
        <button type="button" class="button" id="open-balance-modal">Edit Balances</button>
    </div>
    <?php
}
add_action('show_user_profile', 'pipback_account_balance_profile_field_top');
add_action('edit_user_profile', 'pipback_account_balance_profile_field_top');

function move_custom_profile_field_to_top() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var field = $('#balance-card');
        if (field.length) {
            field.prependTo($('#your-profile').closest('form'));
        }
    });
    </script>
    <?php
}
add_action('admin_footer-user-edit.php', 'move_custom_profile_field_to_top');
add_action('admin_footer-profile.php', 'move_custom_profile_field_to_top');


function pipback_balance_modal_html() {
    // Only load on user-edit/profile page
    $screen = get_current_screen();
    if (!in_array($screen->id, ['profile', 'user-edit'])) return;

    $user_id = get_current_user_id();
    if (isset($_GET['user_id']) && current_user_can('edit_users')) {
        $user_id = intval($_GET['user_id']);
    }

    $pending = get_user_meta($user_id, 'pending_balance', true);
    $full = get_user_meta($user_id, 'full_balance', true);
    $nonce = wp_create_nonce('update_balance_nonce');

    ?>
    <div id="balance-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
        <div style="background:#fff; width:400px; margin:10% auto; padding:20px; border-radius:8px; position:relative;">
            <h3>Edit Balances</h3>
            <form id="balance-ajax-form">
                <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
                <input type="hidden" name="action" value="update_user_balance_ajax">
                <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
                <div>
                    <label>Pending Balance:</label>
                    <input type="number" step="0.01" name="pending_balance" required value="<?php echo esc_attr($pending); ?>">
                </div>
                <br>
                <div>
                    <label>Full Balance:</label>
                    <input type="number" step="0.01" name="full_balance" required value="<?php echo esc_attr($full); ?>">
                </div>
                <br><br>
                <button type="submit" class="button button-primary">Save</button>
                <button type="button" class="button" id="close-balance-modal">Cancel</button>
                <p id="balance-message" style="margin-top:10px;"></p>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#open-balance-modal').on('click', function() {
            $('#balance-modal').fadeIn();
        });

        $('#close-balance-modal').on('click', function() {
            $('#balance-modal').fadeOut();
        });

        $('#balance-ajax-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const message = $('#balance-message').text('Saving...');

            $.post(ajaxurl, form.serialize(), function(res) {
                if (res.success) {
                    message.css('color', 'green').text('Updated!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    message.css('color', 'red').text(res.data?.message || 'Error.');
                }
            }).fail(function() {
                message.css('color', 'red').text('AJAX error.');
            });
        });
    });
    </script>
    <?php
}
add_action('admin_footer-user-edit.php', 'pipback_balance_modal_html');
add_action('admin_footer-profile.php', 'pipback_balance_modal_html');


function hide_personal_options_section() {
    echo '
    <style>
        .user-syntax-highlighting-wrap,
        .user-admin-color-wrap,
        .user-comment-shortcuts-wrap,
        .show-admin-bar,
        .user-language-wrap,
        .user-url-wrap,
        .user-facebook-wrap,
        .user-instagram-wrap,
        .user-linkedin-wrap,
        .user-myspace-wrap,
        .user-pinterest-wrap,
        .user-soundcloud-wrap,
        .user-tumblr-wrap,
        .user-wikipedia-wrap,
        .user-twitter-wrap,
        .user-youtube-wrap,
        .user-yim-wrap,
        .user-jabber-wrap,
        .user-aim-wrap,
        .user-description-wrap,
        .yoast.yoast-settings,
        .application-passwords {
            display: none !important;
        }

        /* Also remove the heading */
        #your-profile > h2:first-of-type {
            display: none !important;
        }
    </style>';
}
add_action('admin_head', 'hide_personal_options_section');