<?php
/**
 * Plugin Name: PipBack
 * Description: Manage Cashback Requests and Pip Funds from the WordPress Admin.
 * Version: 1.0
 * Author: sokolovvladyslav856@gmail.com
 */
if (!defined('ABSPATH')) exit;

// Define constants
define('PIPBACK_DIR', plugin_dir_path(__FILE__));
define('PIPBACK_URL', plugin_dir_url(__FILE__));

// Includes
require_once PIPBACK_DIR . 'admin/cashback-view.php';
require_once PIPBACK_DIR . 'admin/pipfunds-view.php';
require_once PIPBACK_DIR . 'admin/pipcategories-view.php';
require_once PIPBACK_DIR . 'admin/withdraw-view.php';
require_once PIPBACK_DIR . 'admin/balance-change-log.php';
require_once PIPBACK_DIR . 'admin/partnership-requests.php';
require_once PIPBACK_DIR . 'admin/tradingtools-view.php';
require_once PIPBACK_DIR . 'admin/pipfirm-faq-view.php';
require_once PIPBACK_DIR . 'admin/pipfirm-faq-groups-view.php';

require_once PIPBACK_DIR . 'public/firm-page.php';

require_once PIPBACK_DIR . 'includes/ajax-handler.php';
require_once PIPBACK_DIR . 'includes/class-cashback-requests-table.php';
require_once PIPBACK_DIR . 'includes/class-withdraw-requests-table.php';
require_once PIPBACK_DIR . 'includes/class-pip-categories-table.php';
require_once PIPBACK_DIR . 'includes/class-pip-funds-table.php';
require_once PIPBACK_DIR . 'includes/class-pip-firm-faqs-table.php';
require_once PIPBACK_DIR . 'includes/class-pip-firm-faq-groups-table.php';
require_once PIPBACK_DIR . 'includes/timer.php';
require_once PIPBACK_DIR . 'includes/class-pip-form-fields.php';
require_once PIPBACK_DIR . 'includes/init-user-ballance-meta.php';
require_once PIPBACK_DIR . 'includes/pipback-assets.php';
require_once PIPBACK_DIR . 'includes/manage-pip-firms.php';

// Create required tables on activation
register_activation_hook(__FILE__, 'pipback_create_tables');
function pipback_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $cashback = $wpdb->prefix . 'cashbackrequests';
    $funds = $wpdb->prefix . 'pipfunds';
    $categories = $wpdb->prefix . 'pipcategories';
    $withdraw = $wpdb->prefix . 'withdrawalrequests';
    $tradingtools = $wpdb->prefix . 'tradingtools';
    $faqs = $wpdb->prefix . 'firm_faqs';
    $faqgroups = $wpdb->prefix . 'firm_faq_groups';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE $categories (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;");

    dbDelta("CREATE TABLE $funds (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        cashback FLOAT DEFAULT 0,
        discount FLOAT DEFAULT 0,
        review FLOAT DEFAULT 0,
        description TEXT,
        second_description TEXT,
        link TEXT,
        code VARCHAR(100),
        image_link TEXT,
        first_time TINYINT(1) DEFAULT 0,
        display_order INT DEFAULT 0,
        category_id BIGINT UNSIGNED DEFAULT NULL,
        created_by BIGINT UNSIGNED,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        faq_ids TEXT,
        FOREIGN KEY (category_id) REFERENCES $categories(id) ON DELETE SET NULL
    ) $charset;");

    dbDelta("CREATE TABLE $tradingtools (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        cashback FLOAT DEFAULT 0,
        discount FLOAT DEFAULT 0,
        review FLOAT DEFAULT 0,
        description TEXT,
        link TEXT,
        code VARCHAR(100),
        image_link TEXT,
        first_time TINYINT(1) DEFAULT 0,
        display_order INT DEFAULT 0,
        category_id BIGINT UNSIGNED DEFAULT NULL,
        created_by BIGINT UNSIGNED,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES $categories(id) ON DELETE SET NULL
    ) $charset;");

    dbDelta("CREATE TABLE $cashback (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id VARCHAR(20) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        amount FLOAT NOT NULL,
        status VARCHAR(20) DEFAULT 'REVIEW',
        status_changed_by BIGINT DEFAULT NULL,
        status_changed_at DATETIME DEFAULT NULL,
        fund_id BIGINT UNSIGNED DEFAULT NULL,
        hide TINYINT(1) DEFAULT 0,
        challenge_completed_at DATETIME DEFAULT NULL,
        is_charged TINYINT(1) DEFAULT 0,
        deny_reason TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fund_id) REFERENCES $funds(id) ON DELETE SET NULL
    ) $charset;");

    dbDelta("CREATE TABLE $withdraw (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id VARCHAR(20) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        amount FLOAT NOT NULL,
        status VARCHAR(20) DEFAULT 'REVIEW',
        status_changed_by BIGINT DEFAULT NULL,
        status_changed_at DATETIME DEFAULT NULL,
        fund_id BIGINT UNSIGNED DEFAULT NULL,
        deny_reason TEXT DEFAULT NULL,
        hide TINYINT(1) DEFAULT 0,
        payment_method VARCHAR(50) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fund_id) REFERENCES $funds(id) ON DELETE SET NULL
    ) $charset;");
    
    dbDelta("CREATE TABLE $faqgroups (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        firm_name VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;");

    dbDelta("CREATE TABLE $faqs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        group_id BIGINT UNSIGNED NOT NULL,
        title TEXT NOT NULL,
        content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES $faqgroups(id) ON DELETE CASCADE
    ) $charset;");
}

// Delete created tables on deactivation
// register_deactivation_hook(__FILE__, 'pipback_delete_tables');
// function pipback_delete_tables() {
//     global $wpdb;

//     $tables = [
//         $wpdb->prefix . 'cashbackrequests',
//         $wpdb->prefix . 'withdrawalrequests',
//         $wpdb->prefix . 'pipfunds',
//         $wpdb->prefix . 'pipcategories',
//         $wpdb->prefix . 'pip_offer_clicks'
//     ];

//     foreach ($tables as $table) {
//         $wpdb->query("DROP TABLE IF EXISTS $table");
//     }

//     wp_clear_scheduled_hook('pipback_check_cashback_expiry');
// }

add_action('admin_menu', 'pipback_register_menu', 9);
function pipback_register_menu() {
    $view_pipback_cap = current_user_can('manage_options') ? 'manage_options' : 'view_cashback_requests';
    $view_withdrawals_cap = current_user_can('manage_options') ? 'manage_options' : 'view_withdrawal_requests';
    $view_pipfunds_cap = current_user_can('manage_options') ? 'manage_options' : 'view_pip_funds';
    $view_pipcategories_cap = current_user_can('manage_options') ? 'manage_options' : 'view_pip_categories';
    $view_cashback_move_log_cap = current_user_can('manage_options') ? 'manage_options' : 'view_cashback_move_log';
    $view_partnership_requests_cap = current_user_can('manage_options') ? 'manage_options' : 'view_partnership_requests';
    $view_tradingtools_cap = current_user_can('manage_options') ? 'manage_options' : 'view_trading_tools';

    add_menu_page('PipBack', 'PipBack', $view_pipback_cap, 'pipback-dashboard', 'pipback_cashback_page', 'dashicons-money', 56);
    add_submenu_page('pipback-dashboard', 'Cashback Requests', 'Cashback Requests', $view_pipback_cap, 'pipback-dashboard', 'pipback_cashback_page');
    add_submenu_page('pipback-dashboard', 'Withdrawal Requests', 'Withdrawal Requests', $view_withdrawals_cap, 'pipback-withdrawals', 'pipback_withdrawals_page');
    add_submenu_page('pipback-dashboard', 'Prop Firm Categories', 'Prop Firm Categories',  $view_pipcategories_cap, 'pipback-pipcategories', 'pipback_pipcategories_page');
    add_submenu_page('pipback-dashboard', 'Prop Firms', 'Prop Firms', $view_pipfunds_cap, 'pipback-pipfunds', 'pipback_pipfunds_page');
    add_submenu_page('pipback-dashboard', 'Cashback Move Log', 'Cashback Move Log', $view_cashback_move_log_cap, 'cashback-move-log', 'display_cashback_move_log');
    add_submenu_page('pipback-dashboard', 'Partnership Requests', 'Partnership Requests', $view_partnership_requests_cap, 'partnership_requests', 'display_partnership_requests');
    add_submenu_page('pipback-dashboard', 'Trading Tools', 'Trading Tools', $view_tradingtools_cap, 'pipback-tradingtools', 'pipback_tradingtools_page');
    add_submenu_page('pipback-dashboard', 'Manage FAQs', 'FAQs', 'manage_options', 'pipback-firm-faqs', 'render_faqs_admin_page');
    add_submenu_page('pipback-dashboard', 'FAQ Groups', 'FAQ Groups', 'manage_options', 'pipback-firm-faq-groups', 'render_faq_groups_admin_page');
}

add_action('init', 'pipback_update_schema_on_init');

function pipback_update_schema_on_init() {
    pipback_alter_firm_faqs_table();
    pipback_create_firm_faq_groups_table();
}

function pipback_alter_firm_faqs_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'firm_faqs';

    $columns = $wpdb->get_col("DESC $table", 0);
    if (!in_array('group_id', $columns)) {
        $wpdb->query("ALTER TABLE $table ADD group_id BIGINT UNSIGNED NOT NULL DEFAULT 0");
    }
}

function pipback_create_firm_faq_groups_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'firm_faq_groups';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table (
        firm_name VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    dbDelta($sql);
}