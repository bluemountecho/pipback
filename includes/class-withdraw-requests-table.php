<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Withdrawal_Requests_List_Table extends WP_List_Table {

    private $data;

    public function __construct() {
        parent::__construct([
            'singular' => 'withdrawal_request',
            'plural'   => 'withdrawal_requests',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'request_id'     => 'Request ID',
            'user_email'     => 'User Email',
            'amount'         => 'Amount',
            'payment_method' => 'Payment Method',
            'status'         => 'Status',
            'created_at'     => 'Created At',
            'actions'        => 'Actions',
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="request_id[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    public function get_bulk_actions() {
        return [
            'hide'    => 'Delete'
        ];
    }

    
    public function extra_tablenav($which) {
        if ($which !== 'top') return;
    
        $selected_status = $_POST['filter_status'] ?? 'REVIEW';
        $selected_email = $_POST['filter_user_email'] ?? '';
        $ids = get_option('last_exported_withdrawals');

        ?>
        <div class="alignleft actions">
            <style>
                .status-tab {
                    display: inline-block;
                    padding: 6px 12px;
                    margin-right: 5px;
                    border: 1px solid #ccc;
                    background: #f1f1f1;
                    cursor: pointer;
                    border-radius: 3px;
                    text-decoration: none;
                    color: #000;
                }
                .status-tab.active {
                    background: #0073aa;
                    color: #fff;
                    border-color: #0073aa;
                }
            </style>
            <form method="post" style="display: inline;">
                <input type="hidden" name="filter_status" id="filter_status_input" value="<?php echo esc_attr($selected_status); ?>" />

                <a href="#" class="status-tab <?php echo $selected_status === '' ? 'active' : ''; ?>" data-status="">All</a>
                <a href="#" class="status-tab <?php echo $selected_status === 'REVIEW' ? 'active' : ''; ?>" data-status="REVIEW">Review</a>
                <a href="#" class="status-tab <?php echo $selected_status === 'APPROVED' ? 'active' : ''; ?>" data-status="APPROVED">Approved</a>
                <a href="#" class="status-tab <?php echo $selected_status === 'DENIED' ? 'active' : ''; ?>" data-status="DENIED">Denied</a>

                <input type="text" name="filter_user_email" placeholder="User Email"
                    value="<?php echo esc_attr($selected_email); ?>"
                    onkeypress="if(event.key === 'Enter'){ event.preventDefault(); this.form.submit(); }" />

                <button type="submit" name="action" value="filter" class="button">Search</button>
            </form>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.status-tab').forEach(function (tab) {
                        tab.addEventListener('click', function (e) {
                            e.preventDefault();
                            document.getElementById('filter_status_input').value = this.dataset.status;
                            this.closest('form').submit();
                        });
                    });
                });
            </script>

            <form method="post" style="display: inline;">
                <input type="hidden" name="export_withdrawals" value="1">
                <button type="submit" class="button button-primary">Download CSV</button>
            </form>

            <form method="post" style="display: inline;">
                <input type="hidden" name="approve_withdrawals" value="1">
                <button type="submit" class="button button-secondary">Mark All as Approved</button>
            </form>
        </div>
        <?php
    }

    public function process_bulk_action() {
        if (!empty($_POST['request_id']) && is_array($_POST['request_id']) && $this->current_action()) {
            global $wpdb;
            $table = $wpdb->prefix . 'withdrawalrequests';
            $ids   = array_map('intval', $_POST['request_id']);
            $action = $this->current_action();
    
            foreach ($ids as $id) {
                if ($action === 'hide') {
                    $wpdb->update($table, ['hide' => 1], ['id' => $id]);
                }
            }
        }
    }

    public function prepare_items() {
        global $wpdb;
        $this->process_bulk_action();

        $table        = $wpdb->prefix . 'withdrawalrequests';
        $users_table  = $wpdb->prefix . 'users';
        $funds_table  = $wpdb->prefix . 'pipfunds';

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        // Capture filters
        $filter_status     = $_POST['filter_status'] ?? '';
        $filter_user_email = trim($_POST['filter_user_email'] ?? '');

        // Base SQL and conditions
        $where = ['r.hide = 0'];
        $params = [];

        if (!empty($filter_status)) {
            $where[]  = 'r.status = %s';
            $params[] = $filter_status;
        }

        if (!empty($filter_user_email)) {
            $where[]  = 'u.user_email LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filter_user_email) . '%';
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count query
        $count_sql = "SELECT COUNT(*) FROM $table r LEFT JOIN $users_table u ON r.user_id = u.ID $where_sql";
        if (!empty($params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_sql, ...$params));
        } else {
            $total_items = $wpdb->get_var($count_sql);
        }
        // Main query
        $sql = "
            SELECT 
                r.*,
                u.user_email,
                f.title as fund_title,
                reviewer.user_email AS reviewer_email
            FROM $table r
            LEFT JOIN $users_table u ON r.user_id = u.ID
            LEFT JOIN $users_table reviewer ON r.status_changed_by = reviewer.ID
            LEFT JOIN $funds_table f ON r.fund_id = f.id
            $where_sql
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $per_page;
        $params[] = $offset;

        $this->data = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        $this->items = $this->data;

        $this->_column_headers = [$this->get_columns(), [], []];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    public function column_request_id($item) {
        return esc_html($item['request_id']);
    }

    public function column_user_email($item) {
        return '<a href="' . esc_url(admin_url("user-edit.php?user_id={$item['user_id']}")) . '">' . esc_html($item['user_email']) . '</a>';
    }

    public function column_amount($item) {
        return '$' . number_format((float)$item['amount'], 2);
    }

    public function column_payment_method($item) {
        return esc_html($item['payment_method']);
    }

    public function column_status($item) {
        $color = $item['status'] === 'APPROVED' ? 'green' : ($item['status'] === 'REVIEW' ? 'orange' : 'red');
        return '<p style="background:' . $color . ';color:white;width: 65px;padding:5px 10px;border-radius:5px;text-align:center;">' . esc_html($item['status']) . '</p>';
    }   

    public function column_created_at($item) {
        $date = date('Y-m-d', strtotime($item['created_at']));
        return esc_html($date);
    }

    public function column_actions($item) {
        ob_start();
        ?>
        <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:center;" data-id="<?php echo esc_attr($item['id']); ?>" data-request='<?php echo json_encode($item); ?>'>
            <?php if ($item['status'] === 'REVIEW'): ?>
                <button type="button" class="button button-small withdraw-approve-btn" style="background:green;color:white;border:none;width:60px;">Approve</button>
                <button type="button" class="button button-small withdraw-decline-btn" style="background:red;color:white;border:none;width:60px;">Decline</button>
            <?php endif; ?>
            <button type="button" class="button button-small button-primary withdraw-details-btn" style="width:60px;">Details</button>
            <button class="button button-small withdraw-delete-btn" style="width:60px;">Delete</button>
        </div>
        <?php
        return ob_get_clean();
    }

    public function no_items() {
        _e('No withdrawal requests found.', 'pipback');
    }
}

add_action('init', function () {
    global $wpdb;
    $withdrawals_table = $wpdb->prefix . 'withdrawalrequests';

    if (isset($_POST['export_withdrawals'])) {
        $payment_table = $wpdb->prefix . 'user_payment_methods';

        $withdrawals = $wpdb->get_results("
            SELECT w.id, w.amount, w.user_id, p.method_data
            FROM {$withdrawals_table} w
            INNER JOIN {$payment_table} p ON w.user_id = p.user_id
            WHERE w.status = 'REVIEW'
            AND w.payment_method = 'paypal'
        ");

        if (!$withdrawals) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible">
                    <p><strong>No Pending PayPal withdrawals found for export.</strong></p>
                </div>';
            });
            return;
        }

        $export_ids = [];

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payouts.csv"');

        $output = fopen('php://output', 'w');

        foreach ($withdrawals as $row) {
            $method_data = json_decode($row->method_data, true);
            $paypal_email = $method_data['paypal']['email'] ?? '';

            if (filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) {
                fputcsv($output, [$paypal_email, $row->amount, 'USD']);
                $export_ids[] = $row->id;
            }
        }

        fclose($output);

        update_option('last_exported_withdrawals', $export_ids);
        exit;
    }

    if (isset($_POST['approve_withdrawals'])) {
        $ids = get_option('last_exported_withdrawals');
        
        if (!$ids || !is_array($ids)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible">
                    <p><strong>You need to export pending request first!</strong></p>
                </div>';
            });
            return;
        }
        
        

        foreach($ids as $withdrawal_id) {
            
            $withdrawal = $wpdb->get_row("SELECT * FROM $withdrawals_table WHERE id = $withdrawal_id");
            $user_id = $withdrawal->user_id;
            $amount  = floatval($withdrawal->amount);
            $user = get_userdata($user_id);
            $user_email = get_userdata($user_id)->user_email;

            $full_balance = floatval(get_user_meta($user_id, 'full_balance', true));

            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $paymant_table = $wpdb->prefix . 'user_payment_methods';
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $paymant_table WHERE user_id = %d", $user_id));

            $paypal_method = null;
            $bank_method = null;

            if ($row) {
                $method_data = json_decode($row->method_data, true);
                if (isset($method_data['paypal'])) {
                    $paypal_method = $method_data['paypal'];
                }

                if (isset($method_data['bank'])) {
                    $bank_method = $method_data['bank'];
                }
            }

            if ($amount > $full_balance) {
                $wpdb->update($withdrawals_table, [
                    'status' => 'DENIED',
                    'status_changed_by' => get_current_user_id(),
                    'status_changed_at' => current_time('mysql'),
                    'deny_reason' => 'Your ballence is not enough to approve!',
                ], ['id' => $withdrawal_id]);
            } else {
                update_user_meta($user_id, 'full_balance', $full_balance - $amount);
                
                $wpdb->update($withdrawals_table, [
                    'status' => 'APPROVED',
                    'status_changed_by' => get_current_user_id(),
                    'status_changed_at' => current_time('mysql'),
                ], ['id' => $withdrawal_id]);
                
                $to = $user_email;
                $subject = 'Withdrawal Request Approved';
                $headers = ['Content-Type: text/html; charset=UTF-8'];

                $template_path = get_stylesheet_directory() . '/emails/views/withdrawal-request-approved.php';
                $formatted = number_format((float)$amount, 2, '.', '');
                list($intPart, $decimalPart) = explode('.', $formatted);
                $price_full = '$' . $intPart;
                $price_float = '.' . $decimalPart;
                
                $payment_method = $withdrawal->payment_method;

                if($payment_method == 'bank') {
                    if(isset($bank_method['accountNumber'])) {
                        $payment_detail = $bank_method['accountNumber'];
                    } else {
                        $payment_detail = $bank_method['accountHolderName'];

                    }
                } else if($payment_method == 'paypal') {
                    $payment_detail = $paypal_method['email'];
                } else {
                    $payment_detail = 'Placeholder';
                }
                
                ob_start();
                include $template_path;
                $message = ob_get_clean();

                $sent = wp_mail($to, $subject, $message, $headers);
            }
        }

        delete_option('last_exported_withdrawals');
        wp_redirect($_SERVER['HTTP_REFERER']);
        exit;
    }
});

// Add modals and JavaScript for the admin page
add_action('admin_footer', function() {
    ?>
    <!-- Approve Confirmation Modal -->
    <div id="withdraw-approve-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:1000;">
        <div style="background:white; padding:20px; width:600px; margin:10% auto; border-radius:8px; position:relative;">
            <h2>Approve Withdrawal Request</h2>
            <p style="font-weight:bold;">Are you sure you want to approve this withdrawal request?</p>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <div><strong>User:</strong> <span id="approve-user-email"></span></div>
                <div><strong>Amount:</strong> $<span id="approve-amount"></span></div>
                <div><strong>Payment Method:</strong> <span id="approve-payment-method"></span></div>
                <div><strong>Request Date:</strong> <span id="approve-created-at"></span></div>
            </div>
            
            <div id="payment-details-container">
                <h3 style="margin-top:0; margin-bottom: 5px; border-bottom:1px solid #eee; padding-bottom:5px; font-size:14px;">Payment Details</h3>
                <div id="payment-details-content"></div>
            </div>
            
            <div style="margin: 15px 0; border-left: 4px solid rgb(231, 143, 60); background: rgb(247, 215, 186);">
                <p style="padding: 10px;">This will deduct the amount from the user's balance immediately.</p>
            </div>
            
            <input type="hidden" id="withdraw-approve-request-id">
            <input type="hidden" id="withdraw-user-id">
            
            <div style="margin-top:20px; text-align:right;">
                <button id="withdraw-approve-cancel" class="button">Cancel</button>
                <button id="withdraw-approve-submit" class="button button-primary">Approve</button>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <div id="withdraw-deny-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:1000;">
        <div style="background:white; padding:20px; width:400px; margin:10% auto; border-radius:8px; position:relative;">
            <h2>Deny Reason</h2>
            <textarea id="withdraw-deny-reason-text" style="width:100%; height:100px;" placeholder="Enter reason here..."></textarea>
            <input type="hidden" id="withdraw-request-id">
            <div style="margin-top:10px; text-align:right;">
                <button id="withdraw-deny-cancel" class="button">Cancel</button>
                <button id="withdraw-deny-submit" class="button button-primary">Submit</button>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="withdraw-details-modal" style="display:none; align-items:center; justify-content:center; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:9999;">
        <div id="withdraw-modal-content" style="background:#fff; border-radius:12px; padding:20px; max-width:600px; margin:0 auto; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        function ajaxAction(id, action, data = {}) {
            $.post(ajaxurl, {
                action: 'pipback_withdrawal_action',
                sub_action: action,
                request_id: id,
                ...data
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Error processing action.');
                }
            });
        }

        // Approve button click
        $('.withdraw-approve-btn').on('click', function() {
            const requestData = $(this).closest('[data-request]').data('request');
            
            $('#withdraw-approve-request-id').val(requestData.id);
            $('#withdraw-user-id').val(requestData.user_id);
            $('#approve-user-email').text(requestData.user_email);
            $('#approve-amount').text(parseFloat(requestData.amount).toFixed(2));
            $('#approve-payment-method').text(requestData.payment_method || 'Not specified');
            $('#approve-created-at').text(new Date(requestData.created_at).toLocaleDateString('en-CA'));
            
            // Fetch payment method details
            fetchPaymentMethodDetails(requestData.user_id, requestData.payment_method);
            
            $('#withdraw-approve-modal').fadeIn();
        });
        
        // Function to fetch payment method details
        function fetchPaymentMethodDetails(userId, paymentMethod) {
            $('#payment-details-content').html('<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading payment details...</div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'get_user_payment_method_details',
                    user_id: userId,
                    payment_method: paymentMethod,
                    nonce: "<?php echo wp_create_nonce('get_user_payment_method_details') ?>"
                },
                success: function(response) {
                    console.log(response)
                    if (response.success && response.data) {
                        displayPaymentMethodDetails(paymentMethod, response.data);
                    } else {
                        $('#payment-details-content').html(
                            '<div class="no-payment-details alert alert-info">' +
                            '<p><i class="fas fa-info-circle"></i> No payment details found for this method.</p>' +
                            (response.message ? '<p>' + response.message + '</p>' : '') +
                            '</div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'Failed to load payment details.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
                    $('#payment-details-content').html(
                        '<div class="payment-details-error alert alert-danger">' +
                        '<p><i class="fas fa-exclamation-triangle"></i> ' + errorMsg + '</p>' +
                        '</div>'
                    );
                }
            });
        }

        /**
         * Display payment method details from method_data
         */
        function displayPaymentMethodDetails(paymentMethod, data) {
            try {
                const methodData = data.data.method_data;
                console.log(methodData, data, paymentMethod);
                let html = '<div class="payment-details-container">';
                html += '<h4 class="payment-method-title" style="margin: 0; font-size: 13px;">' + 
                        paymentMethod.charAt(0).toUpperCase() + paymentMethod.slice(1) + ':</h4>';
                html += '<div class="payment-details-grid" style="display:grid; grid-template-columns:1fr 1fr;">';
                
                // Display all available fields
                for (const [key, value] of Object.entries(methodData)) {
                    if (value !== null && value !== undefined && value !== '') {
                        const formattedKey = formatKeyForDisplay(key);
                        html += `
                            <div class="detail-row" style="display: flex; align-items: center; gap: 10px; margin: 5px;">
                                <div class="detail-label">${formattedKey}:</div>
                                <div class="detail-value">${sanitizeValueForDisplay(value, key)}</div>
                            </div>
                        `;
                    }
                }

                html += '</div></div>';
                $('#payment-details-content').html(html);
            } catch (e) {
                console.error('Error displaying payment details:', e);
                $('#payment-details-content').html(
                    '<div class="payment-details-error alert alert-danger">' +
                    '<p><i class="fas fa-exclamation-triangle"></i> Could not display payment details.</p>' +
                    '</div>'
                );
            }
        }

        // Helper function to format keys for display
        function formatKeyForDisplay(key) {
            return key
                .replace(/_/g, ' ')
                .replace(/([a-z])([A-Z])/g, '$1 $2')
                .replace(/\b\w/g, l => l.toUpperCase());
        }

        // Helper function to sanitize values for display
        function sanitizeValueForDisplay(value, key) {
            if (key.toLowerCase().includes('email')) {
                return '<a href="mailto:' + value + '">' + value + '</a>';
            }
            if (typeof value === 'object') {
                return JSON.stringify(value);
            }
            return value;
        }

        // Approve modal buttons
        $('#withdraw-approve-cancel').on('click', function() {
            $('#withdraw-approve-modal').fadeOut();
        });

        $('#withdraw-approve-submit').on('click', function() {
            const requestId = $('#withdraw-approve-request-id').val();
            ajaxAction(requestId, 'approve');
            $('#withdraw-approve-modal').fadeOut();
        });

        // Decline button click
        $('.withdraw-decline-btn').on('click', function() {
            const id = $(this).closest('[data-id]').data('id');
            $('#withdraw-request-id').val(id);
            $('#withdraw-deny-reason-text').val('');
            $('#withdraw-deny-modal').fadeIn();
        });

        // Decline modal buttons
        $('#withdraw-deny-cancel').on('click', function() {
            $('#withdraw-deny-modal').fadeOut();
        });

        $('#withdraw-deny-submit').on('click', function() {
            const id = $('#withdraw-request-id').val();
            const reason = $('#withdraw-deny-reason-text').val().trim();
            
            if (!reason) {
                alert('Please enter a reason for declining.');
                return;
            }
            
            ajaxAction(id, 'decline', { deny_reason: reason });
            $('#withdraw-deny-modal').fadeOut();
        });

        // Delete button click
        $('.withdraw-delete-btn').on('click', function() {
            const id = $(this).closest('[data-id]').data('id');
            if (confirm('Are you sure you want to delete this request?')) {
                ajaxAction(id, 'hide');
            }
        });

        // Details button click
        $('.withdraw-details-btn').on('click', function() {
            const data = $(this).closest('[data-request]').data('request');
            console.log(data);

            let html = `
                <div style="font-family:Arial, sans-serif;">
                    <h2 style="margin-bottom:15px; font-size:1.5rem; border-bottom:1px solid #eee; padding-bottom:10px;">Withdrawal Request Details</h2>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px 20px; font-size:0.95rem;">
                        <div><strong>Request ID:</strong><p style="margin:4px 0 0;">${data.request_id}</p></div>
                        <div><strong>User Email:</strong><p style="margin:4px 0 0;">${data.user_email}</p></div>
                        <div><strong>Status:</strong><p style="margin:4px 0 0;">${data.status}</p></div>
                        <div><strong>Amount:</strong><p style="margin:4px 0 0;">$${parseFloat(data.amount).toFixed(2)}</p></div>
                        <div><strong>Created At:</strong><p style="margin:4px 0 0;">${new Date(data.created_at).toLocaleDateString('en-CA')}</p></div>
                        <div><strong>Payment Method:</strong><p style="margin:4px 0 0;">${data.payment_method || 'Not specified'}</p></div>
            `;

            if (data.payment_method == 'paypal') {
                html += `
                        <div><strong>Paypal Email:</strong><p style="margin:4px 0 0;">${data.reviewer_email}</p></div>
                `;
            }

            if (data.reviewer_email) {
                html += `
                        <div><strong>Reviewed By:</strong><p style="margin:4px 0 0;">${data.reviewer_email}</p></div>
                        <div><strong>Status Changed At:</strong><p style="margin:4px 0 0;">${new Date(data.status_changed_at).toLocaleDateString('en-CA')}</p></div>
                `;
            }

            if (data.status === 'DENIED' && data.deny_reason) {
                html += `
                        <div style="grid-column:1 / -1; background:#ffe5e5; border-left:4px solid #e74c3c; padding:10px; margin-top:10px; font-style:italic;">
                            <strong>Deny Reason:</strong> 
                            <p style="margin:4px 0 0;">${data.deny_reason}</p>
                        </div>
                `;
            }

            html += `
                    </div>
                    <div style="text-align:right; margin-top:20px;">
                        <button class="button button-primary withdraw-details-close">Close</button>
                    </div>
                </div>
            `;

            $('#withdraw-modal-content').html(html);
            $('#withdraw-details-modal').css('display', 'flex').hide().fadeIn();
        });

        $(document).on('click', '.withdraw-details-close', function() {
            $('#withdraw-details-modal').fadeOut();
        });

        $('#withdraw-details-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).fadeOut();
            }
        });
    });
    </script>
    <?php
});
