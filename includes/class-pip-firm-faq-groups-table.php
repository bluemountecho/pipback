<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Pip_Firm_FAQ_Groups_Table extends WP_List_Table {
    private $data;

    public function __construct() {
        parent::__construct([
            'singular' => 'FAQ Group',
            'plural'   => 'FAQ Groups',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'id'         => 'ID',
            'firm_name'  => 'Firm',
            'title'      => 'Group Title',
            'created_at' => 'Created At',
            'actions'    => 'Actions',
        ];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="group_id[]" value="%s" />', esc_attr($item['id']));
    }

    public function column_id($item) {
        return esc_html($item['id']);
    }

    public function column_firm_name($item) {
        return esc_html($item['firm_name']);
    }

    public function column_title($item) {
        return esc_html($item['title']);
    }

    public function column_created_at($item) {
        return esc_html($item['created_at']);
    }

    public function column_actions($item) {
        return sprintf(
            '<a data-id="%d" class="button button-small button-primary edit-faq-group">Edit</a> <a data-id="%d" class="button button-small delete-faq-group" onclick="return confirm(\'Are you sure you want to delete this FAQ?\')">Delete</a>',
            $item['id'],
            $item['id']
        );
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'Delete',
        ];
    }

    public function process_bulk_action() {
        if (
            (isset($_POST['action']) && $_POST['action'] === 'delete') ||
            (isset($_POST['action2']) && $_POST['action2'] === 'delete')
        ) {
            global $wpdb;
            $table = $wpdb->prefix . 'firm_faq_groups';

            if (!empty($_POST['group_id']) && is_array($_POST['group_id'])) {
                $ids = array_map('intval', $_POST['group_id']);
                foreach ($ids as $id) {
                    $wpdb->delete($table, ['id' => $id]);
                }
                echo '<div class="notice notice-success"><p>Selected FAQ groups deleted.</p></div>';
            }
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'firm_faq_groups';

        $this->process_bulk_action();

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        $this->data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ), ARRAY_A);

        $this->items = $this->data;

        $this->_column_headers = [$this->get_columns(), [], []];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    public function no_items() {
        _e('No FAQ groups found.', 'pipback');
    }
}
