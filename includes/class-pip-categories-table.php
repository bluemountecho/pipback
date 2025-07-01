<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Pip_Categories_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pip_category',
            'plural'   => 'pip_categories',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'id'         => '<p style="text-align:center; margin:0;">ID</p>',
            'title'      => '<p style="text-align:center; margin:0;">Title</p>',
            'created_at' => '<p style="text-align:center; margin:0;">Created At</p>',
            'actions'    => '<p style="text-align:center; margin:0;">Actions</p>',
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'Delete'
        ];
    }

    public function process_bulk_action() {
        if ($this->current_action() === 'delete' && !empty($_POST['id']) && is_array($_POST['id'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);

            global $wpdb;
            $table = $wpdb->prefix . 'pipcategories';
            $ids = array_map('intval', $_POST['id']);

            foreach ($ids as $id) {
                $wpdb->delete($table, ['id' => $id]);
            }
        }
    }

    public function prepare_items() {
        global $wpdb;

        $this->process_bulk_action(); 
        
        $table = $wpdb->prefix . 'pipcategories';

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        $query = $wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset);
        $this->items = $wpdb->get_results($query, ARRAY_A);

        $this->_column_headers = [$this->get_columns(), [], []];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? '<p style="text-align:center; margin:0; padding: 2px 10px;">' . esc_html($item[$column_name]) . '</p>' : '';
    }


    public function column_actions($item) {
        $edit_url = add_query_arg([
            'page'   => 'pipback-pipcategories',
            'action' => 'edit',
            'id'     => $item['id']
        ]);
    
        $delete_url = wp_nonce_url(add_query_arg([
            'page'   => 'pipback-pipcategories',
            'action' => 'delete',
            'id'     => $item['id']
        ]), 'delete_pip_category_' . $item['id']);

        ob_start();
        ?>
        <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:center;" data-id="<?php echo esc_attr($item['id']); ?>" data-request='<?php echo json_encode($item); ?>'>
            <?php if (current_user_can('manage_pip_categories') || current_user_can('manage_options')): ?>
                <a href="<?php echo esc_url($edit_url); ?>" class="button button-primary">Edit</a>
                <a href="<?php echo esc_url($delete_url); ?>" class="button button-danger">Delete</a>
            <?php else: ?>
                <p style="text-align:center; margin:0; padding: 2px 10px;">-</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function no_items() {
        _e('No pip categories found.', 'pipback');
    }
}
