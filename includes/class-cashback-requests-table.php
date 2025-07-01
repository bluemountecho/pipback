<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Cashback_Requests_List_Table extends WP_List_Table {

    private $data;

    public function __construct() {
        parent::__construct([
            'singular' => 'cashback_request',
            'plural'   => 'cashback_requests',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'                   => '<input type="checkbox" />',
            'request_id'           => '<p style="text-align:center;margin:0;">Request ID</p>',
            'user_email'           => '<p style="text-align:center;margin:0;">User Email</p>',
            'amount'               => '<p style="text-align:center;margin:0;">Amount</p>',
            'fund_title'           => '<p style="text-align:center;margin:0;">Fund</p>',
            'status'               => '<p style="text-align:center;margin:0;">Status</p>',
            'is_charged'           => '<p style="text-align:center;margin:0;">Charged Status</p>',
            'days_remaining'       => '<p style="text-align:center;margin:0;">Days Remaining</p>',
            'actions'              => '<p style="text-align:center;margin:0;">Actions</p>',
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
            <input type="hidden" name="filter_status" id="filter_status_input" value="<?php echo esc_attr($selected_status); ?>" />

            <a href="#" class="status-tab <?php echo $selected_status === '' ? 'active' : ''; ?>" data-status="">All</a>
            <a href="#" class="status-tab <?php echo $selected_status === 'REVIEW' ? 'active' : ''; ?>" data-status="REVIEW">Review</a>
            <a href="#" class="status-tab <?php echo $selected_status === 'APPROVED' ? 'active' : ''; ?>" data-status="APPROVED">Approved</a>
            <a href="#" class="status-tab <?php echo $selected_status === 'DENIED' ? 'active' : ''; ?>" data-status="DENIED">Denied</a>

            <input type="text" name="filter_user_email" placeholder="User Email"
                value="<?php echo esc_attr($selected_email); ?>"
                onkeypress="if(event.key === 'Enter'){ event.preventDefault(); this.form.submit(); }" />
            <?php submit_button(__('Search'), '', 'filter_action', false); ?>
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
        </div>
        <?php
    }

    public function process_bulk_action() {
        error_log(print_r($_POST, true));
        if (!empty($_POST['request_id']) && is_array($_POST['request_id']) && $this->current_action()) {
            global $wpdb;
            $table = $wpdb->prefix . 'cashbackrequests';
            $ids   = array_map('intval', $_POST['request_id']);
            $action = $this->current_action();

            foreach ($ids as $id) {
                if ($action === 'approve') {
                    $wpdb->update($table, [
                        'status' => 'APPROVED',
                        'status_changed_by' => get_current_user_id(),
                        'status_changed_at' => current_time('mysql'),
                    ], ['id' => $id]);
                } elseif ($action === 'hide') {
                    $wpdb->update($table, ['hide' => 1], ['id' => $id]);
                }
            }
        }
    }

    public function prepare_items() {
        global $wpdb;
    
        $this->process_bulk_action();
    
        $table = $wpdb->prefix . 'cashbackrequests';
        $funds_table = $wpdb->prefix . 'pipfunds';
    
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;
    
        // Handle filters
        $where = "WHERE r.hide = 0";
        $params = [];
    
        $status = $_POST['filter_status'] ?? 'REVIEW';
        if (!empty($status)) {
            $where .= " AND r.status = %s";
            $params[] = $status;
        }

        if (!empty($_POST['filter_user_email'])) {
            $where .= " AND u.user_email LIKE %s";
            $params[] = '%' . $wpdb->esc_like($_POST['filter_user_email']) . '%';
        }
    
        $count_sql = "SELECT COUNT(*) FROM $table r 
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
            $where";
    
        if (!empty($params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_sql, ...$params));
        } else {
            $total_items = $wpdb->get_var($count_sql);
        }
        $sql = "
            SELECT 
                r.*, 
                u.user_email, 
                f.title as fund_title,
                reviewer.user_email AS reviewer_email
            FROM $table r
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            LEFT JOIN {$wpdb->users} reviewer ON r.status_changed_by = reviewer.ID
            LEFT JOIN $funds_table f ON r.fund_id = f.id
            $where
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

    public function column_challenge_completed($item) {
        if (!empty($item['challenge_completed_at'])) {
            $date = date('Y-m-d', strtotime($item['challenge_completed_at']));
            return esc_html($date);
        } else {
            return '<span style="color:red;">Missing</span>';
        }
    }

    public function column_request_id($item) {
        return '<div style="text-align:center;">' . esc_html($item['request_id']) . '</div>';
    }

    public function column_amount($item) {
        return '<div style="text-align:center;">' . esc_html($item['amount']) . '</div>';
    }

    public function column_user_email($item) {
        return '<a href="' . esc_url(admin_url("user-edit.php?user_id={$item['user_id']}")) . '" style="text-align:center; display:block;">' . esc_html($item['user_email']) . '</a>';
    }

    public function column_request_email($item) {
        return '<div style="text-align:center;">' . esc_html($item['email']) . '</div>';
    }

    public function column_status($item) {
        $color = $item['status'] === 'APPROVED'  ? 'green' :
                ($item['status'] === 'REVIEW'   ? 'orange' : 'red');
        return '<div style="display:flex;justify-content:center;"><p style="background:' . $color . ';color:white;width: 65px;padding:3px 10px;border-radius:5px;text-align:center;">' . esc_html($item['status']) . '</p></div>';
    }

    public function column_fund_title($item) {
        return '<div style="text-align:center;">' . esc_html($item['fund_title'] ?: '—') . '</div>';
    }

    public function column_created_at($item) {
        return '<div style="text-align:center;">' . esc_html($item['created_at']) . '</div>';
    }

    public function column_is_charged($item) {
        return $item['is_charged'] 
            ? '<div style="display:flex;justify-content:center;"><p style="background:green; padding:3px 3px 4px;width: 80px;text-align:center;color:white; border-radius:5px;">Charged</p></div>'
            : '<div style="display:flex;justify-content:center;"><p style="background:orange; padding:3px 3px 4px;width: 80px;text-align:center;color:white; border-radius:5px;">Not Charged</p></div>';
    }

    public function column_days_remaining($item) {
        $status = $item['status'] ?? null;

        if ($status === 'PENDING') {
            return '<p style="text-align:center;">N/A</p>';
        }

        if ($status === 'DENIED') {
            return '<p style="text-align:center;">X</p>';
        }

        if ($status !== 'APPROVED' || empty($item['status_changed_at'])) {
            return '<p style="text-align:center;">N/A</p>';
        }

        $changedAt = new DateTime($item['status_changed_at']);
        $releaseAt = clone $changedAt;
        $releaseAt->modify('+30 days');
        $now = new DateTime();

        if ($now >= $releaseAt) {
            return '<p style="text-align:center;">Transferred</p>';
        }

        $interval = $now->diff($releaseAt);
        return '<p style="text-align:center;">' . $interval->days . ' day(s) remaining</p>';
    }


    public function column_actions($item) {
        ob_start();
        ?>
        <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:center;" data-id="<?php echo esc_attr($item['id']); ?>" data-request='<?php echo json_encode($item); ?>'>
            <?php if ($item['status'] === 'REVIEW' && (current_user_can('manage_cashback_requests') || current_user_can('manage_options'))): ?>
                <button type="button" class="button button-small approve-btn" style="background:green;color:white;border:none;width:60px;">Approve</button>
                <button type="button" class="button button-small decline-btn" style="background:red;color:white;border:none;width:60px;">Decline</button>
            <?php endif; ?>
            <button type="button" class="button button-primary button-small details-btn" style="width:60px;">Details</button>
            <?php if (current_user_can('manage_cashback_requests') || current_user_can('manage_options')): ?>
                <button class="button button-small delete-btn" style="width:60px;">Delete</button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function no_items() {
        _e('No cashback requests found.', 'pipback');
    }
}

// Add modals and JavaScript for the admin page
add_action('admin_footer', function() {
    global $wpdb;
    $funds_table = $wpdb->prefix . 'pipfunds';
    $funds = $wpdb->get_results("SELECT id, title, cashback FROM $funds_table", ARRAY_A);
    $funds_json = json_encode($funds);
    ?>
    <!-- Approve Modal -->
    <div id="cashback-approve-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:1000;">
        <div style="background:white; padding:20px; width:500px; margin:10% auto; border-radius:8px; position:relative;">
            <h2>Approve Cashback Request</h2>
            <p>Fund: <span id="approve-fund-name"></span></p>
            <p>Cashback Rate: <span id="approve-cashback-rate">0</span>%</p>

            <!-- Entry mode switch -->
            <div style="margin-bottom:15px;">
                <label><input type="radio" name="entry-mode" value="purchase" checked> Enter Purchase Amount</label>
                <label style="margin-left:20px;"><input type="radio" name="entry-mode" value="cashback"> Enter Cashback Amount</label>
            </div>

            <!-- Purchase Mode Input -->
            <div id="purchase-input-group" style="margin-bottom:15px;">
                <label for="purchase-amount" style="display:block; margin-bottom:5px;">Purchase Amount (USD):</label>
                <input type="number" id="purchase-amount" style="width:100%;" step="0.01" min="0" placeholder="Enter purchase amount">
            </div>

            <!-- Cashback Mode Input -->
            <div id="cashback-input-group" style="margin-bottom:15px; display:none;">
                <label for="cashback-amount-input" style="display:block; margin-bottom:5px;">Cashback Amount (USD):</label>
                <input type="number" id="cashback-amount-input" style="width:100%;" step="0.01" min="0" placeholder="Enter cashback amount">
            </div>

            <!-- Final calculated field -->
            <div style="margin-bottom:15px;">
                <label for="cashback-amount" style="display:block; margin-bottom:5px;">Cashback Amount (USD):</label>
                <input type="number" id="cashback-amount" style="width:100%;" step="0.01" min="0" readonly>
            </div>

            <input type="hidden" id="approve-request-id">
            <input type="hidden" id="approve-fund-id">

            <div style="margin-top:20px; text-align:right;">
                <button id="approve-cancel" class="button">Cancel</button>
                <button id="approve-submit" class="button button-primary">Approve</button>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <div id="cashback-deny-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:1000;">
        <div style="background:white; padding:20px; width:400px; margin:10% auto; border-radius:8px; position:relative;">
            <h2>Deny Reason</h2>
            <textarea id="deny-reason-text" style="width:100%; height:100px;" placeholder="Enter reason here..."></textarea>
            <input type="hidden" id="deny-request-id">
            <div style="margin-top:10px; text-align:right;">
                <button id="deny-cancel" class="button">Cancel</button>
                <button id="deny-submit" class="button button-primary">Submit</button>
            </div>          
        </div>
    </div>

    <!-- Details Modal -->
    <div id="cashback-details-modal" style="display:none; align-items:center; justify-content:center; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:9999;">
        <div id="modal-content" style="width: 90%; max-width: 1200px;"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Store fund data for calculations
        const funds = <?php echo $funds_json; ?>;

        $('input[name="entry-mode"]').on('change', function () {
            const mode = $(this).val();
            if (mode === 'purchase') {
                $('#purchase-input-group').show();
                $('#cashback-input-group').hide();
                $('#purchase-amount').trigger('input');
            } else {
                $('#purchase-input-group').hide();
                $('#cashback-input-group').show();
                $('#cashback-amount-input').trigger('input');
            }
        });

        function ajaxAction(id, action, data = {}) {
            $.post(ajaxurl, {
                action: 'pipback_cashback_action',
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
        $('.approve-btn').on('click', function() {
            const requestData = $(this).closest('[data-request]').data('request');
            const fundId = requestData.fund_id;
            const fund = funds.find(f => f.id == fundId) || { title: 'Unknown', cashback_percentage: 0 };
            
            $('#approve-request-id').val(requestData.id);
            $('#approve-fund-id').val(fundId);
            $('#approve-fund-name').text(fund.title);
            $('#approve-cashback-rate').text(parseFloat(fund.cashback));
            $('#purchase-amount').val('');
            $('#cashback-amount').val('');
            
            $('#cashback-approve-modal').fadeIn();
        });

        // Calculate cashback amount when purchase amount changes
        $('#purchase-amount').on('input', function() {
            const purchaseAmount = parseFloat($(this).val()) || 0;
            const cashbackRate = parseFloat($('#approve-cashback-rate').text()) / 100;
            const cashbackAmount = purchaseAmount * cashbackRate;
            $('#cashback-amount').val(cashbackAmount.toFixed(2));
        });

        // Cashback → Purchase calculation
        $('#cashback-amount-input').on('input', function () {
            const cashbackAmount = parseFloat($(this).val()) || 0;
            const cashbackRate = parseFloat($('#approve-cashback-rate').text()) / 100;
            const purchaseAmount = cashbackRate > 0 ? cashbackAmount / cashbackRate : 0;
            $('#cashback-amount').val(cashbackAmount.toFixed(2));
            $('#purchase-amount').val(purchaseAmount.toFixed(2));
        });

        // Approve modal buttons
        $('#approve-cancel').on('click', function() {
            $('#cashback-approve-modal').fadeOut();
        });

        $('#approve-submit').on('click', function() {
            const requestId = $('#approve-request-id').val();
            const cashbackAmount = parseFloat($('#cashback-amount').val());
            
            if (isNaN(cashbackAmount) || cashbackAmount <= 0) {
                alert('Please enter a valid purchase amount.');
                return;
            }
            
            ajaxAction(requestId, 'approve', { amount: cashbackAmount });
            $('#cashback-approve-modal').fadeOut();
        });

        // Decline button click
        $('.decline-btn').on('click', function() {
            const id = $(this).closest('[data-id]').data('id');
            $('#deny-request-id').val(id);
            $('#deny-reason-text').val('');
            $('#cashback-deny-modal').fadeIn();
        });

        // Decline modal buttons
        $('#deny-cancel').on('click', function() {
            $('#cashback-deny-modal').fadeOut();
        });

        $('#deny-submit').on('click', function() {
            const id = $('#deny-request-id').val();
            const reason = $('#deny-reason-text').val().trim();
            
            if (!reason) {
                alert('Please enter a reason for declining.');
                return;
            }
            
            ajaxAction(id, 'decline', { deny_reason: reason });
            $('#cashback-deny-modal').fadeOut();
        });

        // Delete button click
        $('.delete-btn').on('click', function() {
            const id = $(this).closest('[data-id]').data('id');
            if (confirm('Are you sure you want to delete this request?')) {
                ajaxAction(id, 'hide');
            }
        });


        function showRequestDetails(data, allRequests = []) {
            // Store the full list of requests if provided
            if (allRequests.length > 0) {
                $('#modal-content').data('all-requests', allRequests);
                $('#modal-content').data('current-index', allRequests.findIndex(req => req.id == data.id));
            }

            let html = `
                <div style="background:#fff; border-radius:12px; padding:20px; margin:0 auto; box-shadow:0 4px 12px rgba(0,0,0,0.1); font-family:Arial, sans-serif;">
                    <h2 style="margin-bottom:15px; font-size:1.5rem; border-bottom:1px solid #eee; padding-bottom:10px;">Cashback Request Details</h2>
                    
                    ${allRequests.length > 0 ? `
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                        <button id="prev-request-btn" class="button" ${allRequests.findIndex(req => req.id == data.id) === 0 ? 'disabled' : ''}>← Previous</button>
                        <span>Request ${allRequests.findIndex(req => req.id == data.id) + 1} of ${allRequests.length}</span>
                        <button id="next-request-btn" class="button" ${allRequests.findIndex(req => req.id == data.id) === allRequests.length - 1 ? 'disabled' : ''}>Next →</button>
                    </div>
                    ` : ''}
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px 20px; font-size:0.95rem;">
                        <div><strong>Request ID:</strong><p style="margin:4px 0 0;">${data.request_id}</p></div>
                        <div><strong>User Email:</strong><p style="margin:4px 0 0;">${data.user_email}</p></div>
                        <div><strong>Status:</strong><p style="margin:4px 0 0;">${data.status}</p></div>
                        <div><strong>Amount:</strong><p style="margin:4px 0 0;">$${data.amount}</p></div>
                        <div><strong>Fund:</strong><p style="margin:4px 0 0;">${data.fund_title || 'N/A'}</p></div>
                        <div><strong>Created At:</strong><p style="margin:4px 0 0;">${new Date(data.created_at).toLocaleDateString('en-CA')}</p></div>
            `;

            if (data.reviewer_email) {
                html += `
                        <div><strong>Reviewed By:</strong><p style="margin:4px 0 0;">${data.reviewer_email}</p></div>
                        <div><strong>Status Changed At:</strong><p style="margin:4px 0 0;">${new Date(data.status_changed_at).toLocaleDateString('en-CA')}</p></div>
                        <div><strong>Charged Status:</strong><p style="margin:4px 0 0;">${parseInt(data.is_charged) ? 'Charged' : 'Not Charged'}</p></div>
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

            if (data.custom_fields) {
                try {
                    const customFields = JSON.parse(data.custom_fields);
                    if (customFields && Object.keys(customFields).length > 0) {
                        html += `
                            <div style="grid-column:1 / -1;">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px 20px;">
                        `;
                        
                        for (const [key, value] of Object.entries(customFields)) {
                            const formattedKey = key.replace(/_/g, ' ')
                                .replace(/\b\w/g, l => l.toUpperCase());
                            
                            html += `
                                <div><strong>${formattedKey}:</strong><p style="margin:4px 0 0;">${value}</p></div>
                            `;
                        }
                        
                        html += `
                                </div>
                            </div>
                        `;
                    }
                } catch (e) {
                    console.error('Error parsing custom fields:', e);
                }
            }

            html += `
                    </div>
                    <div style="margin-top:20px; display:flex; justify-content:space-between;">
                        ${allRequests.length > 0 ? '<button id="view-all-requests-btn" class="button">View All Requests</button>' : ''}
                        <button onclick="jQuery(\'#cashback-details-modal\').fadeOut()" class="button button-primary">Close</button>
                    </div>
                </div>
            `;

            $('#modal-content').html(html);
            $('#cashback-details-modal').css('display', 'flex').hide().fadeIn();
        }

        jQuery(document).on('click', '#prev-request-btn, #next-request-btn, #view-all-requests-btn', function() {
            const allRequests = $('#modal-content').data('all-requests');
            let currentIndex = $('#modal-content').data('current-index');
            
            if ($(this).is('#prev-request-btn')) {
                currentIndex--;
            } else if ($(this).is('#next-request-btn')) {
                currentIndex++;
            } else if ($(this).is('#view-all-requests-btn')) {
                showAllRequestsModal(allRequests);
                return;
            }
            
            $('#modal-content').data('current-index', currentIndex);
            showRequestDetails(allRequests[currentIndex], allRequests);
        });

        function showAllRequestsModal(allRequests) {
            let html = `
                <div style="background:#fff; border-radius:12px; padding:20px; margin:0 auto; box-shadow:0 4px 12px rgba(0,0,0,0.1); font-family:Arial, sans-serif; max-width:800px;">
                    <h2 style="margin-bottom:15px; font-size:1.5rem; border-bottom:1px solid #eee; padding-bottom:10px;">All Cashback Requests</h2>
                    <div style="max-height:500px; overflow-y:auto;">
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f8f9fa;">
                                    <th style="padding:10px; text-align:left; border-bottom:1px solid #ddd;">Request ID</th>
                                    <th style="padding:10px; text-align:left; border-bottom:1px solid #ddd;">Amount</th>
                                    <th style="padding:10px; text-align:left; border-bottom:1px solid #ddd;">Status</th>
                                    <th style="padding:10px; text-align:left; border-bottom:1px solid #ddd;">Date</th>
                                    <th style="padding:10px; text-align:left; border-bottom:1px solid #ddd;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            allRequests.forEach((request, index) => {
                html += `
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px;">${request.request_id}</td>
                        <td style="padding:10px;">$${request.amount}</td>
                        <td style="padding:10px;">
                            <span style="color:${request.status === 'APPROVED' ? 'green' : request.status === 'DENIED' ? 'red' : 'orange'}">
                                ${request.status}
                            </span>
                        </td>
                        <td style="padding:10px;">${new Date(request.created_at).toLocaleDateString('en-CA')}</td>
                        <td style="padding:10px;">
                            <button class="button button-small view-details-btn" data-index="${index}" style="padding:4px 8px;">View Details</button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                    <div style="text-align:right; margin-top:20px;">
                        <button onclick="jQuery('#modal-content').html(jQuery('#modal-content').data('original-content'))" 
                                class="button button-primary">
                            Back to Details
                        </button>
                    </div>
                </div>
            `;
            
            $('#modal-content').data('original-content', $('#modal-content').html());
            $('#modal-content').html(html);
        }

        // Handle click on view details buttons in the all requests view
        jQuery(document).on('click', '.view-details-btn', function() {
            const allRequests = $('#modal-content').data('all-requests');
            const index = $(this).data('index');
            showRequestDetails(allRequests[index], allRequests);
        });

        // Update the details button click handler to load all requests
        jQuery(document).on('click', '.details-btn', function() {
            const requestData = $(this).closest('[data-request]').data('request');
            
            // Then load all requests in the background
            jQuery.post(ajaxurl, {
                action: 'pipback_get_all_user_requests',
                request_id: requestData.id
            }, function(response) {
                if (response.success) {
                    // Update the modal with navigation controls
                    showRequestDetails(requestData, response.data);
                }
            });
        });
        $('#cashback-details-modal').on('click', function (e) {
            if (e.target === this) {
                $(this).fadeOut();
            }
        });
       
    });
    </script>
    <?php
});
