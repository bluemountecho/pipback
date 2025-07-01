<?php
/**
 * Conversion Analytics Page
 */

function pipback_analytics_page() {
    global $wpdb;
    
    $funds_table = $wpdb->prefix . 'pipfunds';
    $clicks_table = $wpdb->prefix . 'pip_offer_clicks';
    $cashback_table = $wpdb->prefix . 'cashbackrequests';
    $withdraw_table = $wpdb->prefix . 'withdrawalrequests';
    
    // Get date range filters
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
    
    // Add one day to end date for inclusive results
    $end_date_query = date('Y-m-d', strtotime($end_date . ' +1 day'));
    
    // Get overall statistics
    $total_clicks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $clicks_table 
        WHERE created_at BETWEEN %s AND %s",
        $start_date,
        $end_date_query
    )) ?: 0;
    
    $total_conversions = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $clicks_table 
        WHERE converted = 1 AND conversion_date BETWEEN %s AND %s",
        $start_date,
        $end_date_query
    )) ?: 0;
    
    $conversion_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;
    
    
    $total_pending_cashback = $wpdb->get_var("
        SELECT SUM(amount) 
        FROM $cashback_table 
        WHERE status = 'APPROVED' 
        AND hide = 0 
        AND is_charged = 0
    ") ?: 0;
    
    $total_withdrawal_balance = $wpdb->get_var("
        SELECT SUM(meta_value) 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'full_balance'
    ") ?: 0;
    
    $total_pending_withdrawal = $wpdb->get_var("
        SELECT SUM(amount) 
        FROM $withdraw_table 
        WHERE status = 'REVIEW' 
        AND hide = 0
    ") ?: 0;
    
    $total_balance = $total_pending_cashback + $total_withdrawal_balance + $total_pending_withdrawal;
    
    // Get fund-specific statistics
    $fund_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            f.id,
            f.title,
            COUNT(c.id) as total_clicks,
            SUM(c.converted) as conversions
        FROM 
            $funds_table f
        LEFT JOIN 
            $clicks_table c ON f.id = c.fund_id AND c.created_at BETWEEN %s AND %s
        GROUP BY 
            f.id
        ORDER BY 
            total_clicks DESC",
        $start_date,
        $end_date_query
    ));
    
    // Get user statistics (top converters)
    $user_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            c.user_id,
            COUNT(c.id) as total_clicks,
            SUM(c.converted) as conversions
        FROM 
            $clicks_table c
        WHERE 
            c.user_id IS NOT NULL
            AND c.created_at BETWEEN %s AND %s
        GROUP BY 
            c.user_id
        ORDER BY 
            conversions DESC
        LIMIT 10",
        $start_date,
        $end_date_query
    ));
    
    ?>
    <div class="wrap">
        <h1>Conversion Analytics</h1>
        
        <div class="analytics-filters">
            <form method="get">
                <input type="hidden" name="page" value="pipback-analytics">
                <div class="date-filters">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                    
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                    
                    <button type="submit" class="button">Apply Filters</button>
                </div>
            </form>
        </div>
        
        <!-- Financial Overview Section -->
        <div class="analytics-overview financial-overview">
            <h2>Financial Overview</h2>
            
            <div class="analytics-cards financial-cards">
                <div class="analytics-card financial-card">
                    <h3>Pending Cashback</h3>
                    <div class="analytics-value">$<?php echo number_format($total_pending_cashback, 2); ?></div>
                    <div class="card-description">Total amount of approved cashbacks in 30-day window</div>
                </div>
                
                <div class="analytics-card financial-card">
                    <h3>Withdrawal Balance</h3>
                    <div class="analytics-value">$<?php echo number_format($total_withdrawal_balance, 2); ?></div>
                    <div class="card-description">Total amount ready for withdrawal across all users</div>
                </div>
                
                <div class="analytics-card financial-card">
                    <h3>Pending Withdrawals</h3>
                    <div class="analytics-value">$<?php echo number_format($total_pending_withdrawal, 2); ?></div>
                    <div class="card-description">Total amount requested for withdrawal but not yet paid</div>
                </div>
                
                <div class="analytics-card financial-card total-balance">
                    <h3>Total Balance</h3>
                    <div class="analytics-value">$<?php echo number_format($total_balance, 2); ?></div>
                    <div class="card-description">Combined total of all balances in the system</div>
                </div>
            </div>
        </div>
        
        <!-- Conversion Overview Section -->
        <div class="analytics-overview">
            <h2>Conversion Overview (<?php echo esc_html($start_date); ?> to <?php echo esc_html($end_date); ?>)</h2>
            
            <div class="analytics-cards">
                <div class="analytics-card">
                    <h3>Total Clicks</h3>
                    <div class="analytics-value"><?php echo number_format($total_clicks); ?></div>
                </div>
                
                <div class="analytics-card">
                    <h3>Total Conversions</h3>
                    <div class="analytics-value"><?php echo number_format($total_conversions); ?></div>
                </div>
                
                <div class="analytics-card">
                    <h3>Conversion Rate</h3>
                    <div class="analytics-value"><?php echo $conversion_rate; ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="analytics-tables">
            <div class="analytics-table-container">
                <h2>Fund Performance</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Fund</th>
                            <th>Clicks</th>
                            <th>Conversions</th>
                            <th>Conversion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fund_stats as $fund): ?>
                            <?php 
                            $fund_conversion_rate = $fund->total_clicks > 0 
                                ? round(($fund->conversions / $fund->total_clicks) * 100, 2) 
                                : 0; 
                            ?>
                            <tr>
                                <td><?php echo esc_html($fund->title); ?></td>
                                <td><?php echo number_format($fund->total_clicks); ?></td>
                                <td><?php echo number_format($fund->conversions); ?></td>
                                <td><?php echo $fund_conversion_rate; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($fund_stats)): ?>
                            <tr>
                                <td colspan="4">No data available for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="analytics-table-container">
                <h2>Top Users</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Clicks</th>
                            <th>Conversions</th>
                            <th>Conversion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_stats as $user): ?>
                            <?php 
                            $user_conversion_rate = $user->total_clicks > 0 
                                ? round(($user->conversions / $user->total_clicks) * 100, 2) 
                                : 0;
                                
                            $user_info = get_userdata($user->user_id);
                            $user_display = $user_info ? $user_info->display_name : 'User #' . $user->user_id;
                            ?>
                            <tr>
                                <td><?php echo esc_html($user_display); ?></td>
                                <td><?php echo number_format($user->total_clicks); ?></td>
                                <td><?php echo number_format($user->conversions); ?></td>
                                <td><?php echo $user_conversion_rate; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($user_stats)): ?>
                            <tr>
                                <td colspan="4">No user data available for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <style>
    .analytics-filters {
        margin: 20px 0;
        background: #fff;
        padding: 15px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .date-filters {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .analytics-overview {
        margin: 30px 0;
    }
    
    .analytics-cards {
        display: flex;
        gap: 20px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    
    .analytics-card {
        background: #fff;
        padding: 20px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        flex: 1;
        text-align: center;
        min-width: 200px;
    }
    
    .financial-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .financial-card {
        background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
        border-left: 4px solid #0073aa;
    }
    
    .total-balance {
        background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
        border-left: 4px solid #00acc1;
        grid-column: 1 / -1;
    }
    
    .card-description {
        color: #666;
        font-size: 0.85em;
        margin-top: 10px;
    }
    
    .analytics-card h3 {
        margin-top: 0;
        color: #555;
    }
    
    .analytics-value {
        font-size: 2.5em;
        font-weight: bold;
        color: #0073aa;
    }
    
    .total-balance .analytics-value {
        color: #00acc1;
    }
    
    .analytics-tables {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
    }
    
    .analytics-table-container {
        flex: 1;
        min-width: 45%;
    }
    
    @media (max-width: 782px) {
        .date-filters {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .analytics-cards {
            flex-direction: column;
        }
        
        .analytics-tables {
            flex-direction: column;
        }
    }
    </style>
    <?php
}
