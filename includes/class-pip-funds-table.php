<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Pip_Funds_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pip_fund',
            'plural'   => 'pip_funds',
            'ajax'     => true
        ]);
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'title'         => '<p style="text-align:center; margin:0;">Title</p>',
            'category'      => '<p style="text-align:center; margin:0;">Category</p>',
            'cashback'      => '<p style="text-align:center; margin:0;">Cashback(%)</p>',
            'discount'      => '<p style="text-align:center; margin:0;">Discount(%)</p>',
            'review'        => '<p style="text-align:center; margin:0;">Review</p>',
            'first_time'    => '<p style="text-align:center; margin:0;">First Time</p>',
            'created_at'    => '<p style="text-align:center; margin:0;">Created</p>',
            'actions'       => '<p style="text-align:center; margin:0;">Actions</p>'
        ];
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'Delete'
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    public function process_bulk_action() {
        global $wpdb;

        $redirect_url = admin_url('admin.php?page=pipback-pipfunds');
    
        // Bulk delete
        if ('delete' === $this->current_action() && !empty($_POST['id']) && is_array($_POST['id'])) {
            $ids = array_map('intval', $_POST['id']);
            $table = $wpdb->prefix . 'pipfunds';
    
            foreach ($ids as $id) {
                $wpdb->delete($table, ['id' => $id]);
            }
    
            return $redirect_url;
        }
    
        // Single delete
        if (isset($_GET['action'], $_GET['fund_id']) && $_GET['action'] === 'delete') {
            $fund_id = intval($_GET['fund_id']);
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_fund_' . $fund_id)) {
                $wpdb->delete($wpdb->prefix . 'pipfunds', ['id' => $fund_id]);
                return $redirect_url;
            } else {
                wp_die(__('Security check failed. Deletion not allowed.', 'pipback'));
            }
        }
    
        return null;
    }

    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        global $wpdb;

        $category_table = $wpdb->prefix . 'pipcategories';
        $categories = $wpdb->get_results("SELECT id, title FROM $category_table ORDER BY title DESC");

        $current_category = isset($_GET['category_filter']) ? intval($_GET['category_filter']) : intval($categories[0]->id);

        $base_url = remove_query_arg(['category_filter', 'paged']);
        echo '<div class="pip-category-tabs" style="display: flex; gap: 10px; flex-wrap: wrap;">';

        foreach ($categories as $category) {
            $is_active = $current_category === intval($category->id);
            $class = $is_active ? 'button button-primary' : 'button';
            $url = add_query_arg('category_filter', intval($category->id), $base_url);
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($category->title) . '</a>';
        }

        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'pipfunds';
        $category_table = $wpdb->prefix . 'pipcategories';
        $orderby = 'display_order';
        $order = 'ASC';

        $where = '1=1';
        $categories = $wpdb->get_results("SELECT id FROM $category_table ORDER BY title DESC");
        $category_filter = isset($_GET['category_filter']) ? intval($_GET['category_filter']) : (isset($categories[0]) ? $categories[0]->id : 0);

        if ($category_filter > 0) {
            $where .= $wpdb->prepare(" AND f.category_id = %d", $category_filter);
        }

        $query = "
            SELECT f.*, c.title as category_name 
            FROM $table f
            LEFT JOIN $category_table c ON f.category_id = c.id
            WHERE $where
            ORDER BY $orderby $order
        ";

        $total_query = "
            SELECT COUNT(*) 
            FROM $table f
            WHERE " . ($category_filter > 0 ? $wpdb->prepare("f.category_id = %d", $category_filter) : "1=1");

        $total_items = $wpdb->get_var($total_query);
        $items = $wpdb->get_results($query, ARRAY_A);

        $this->items = $items;
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->set_pagination_args([
            'total_items' => $total_items,
        ]);
    }

    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? '<p style="text-align:center; margin:0; padding: 2px 10px;">' . esc_html($item[$column_name]) . '</p>' : '';
    }

    public function column_actions($item) {
        $edit_url = admin_url('admin.php?page=pipback-pipfunds&action=edit&fund_id=' . intval($item['id']));
        $delete_url = wp_nonce_url(admin_url('admin.php?page=pipback-pipfunds&action=delete&fund_id=' . intval($item['id'])), 'delete_fund_' . intval($item['id']));
        ob_start();
        ?>
        <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:center;" data-id="<?php echo esc_attr($item['id']); ?>" data-request='<?php echo json_encode($item); ?>'>
            <?php if (current_user_can('manage_pip_funds') || current_user_can('manage_options')): ?>
                <a href="<?php echo esc_url($edit_url); ?>" class="button button-primary">Edit</a>
                <a href="<?php echo esc_url($delete_url); ?>" class="button button-danger">Delete</a>
            <?php else: ?>
                <p style="text-align:center; margin:0; padding: 2px 10px;">-</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function single_row($item) {
        echo '<tr id="fund-' . esc_attr($item['id']) . '" data-id="' . esc_attr($item['id']) . '">';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    public function no_items() {
        _e('No Pip Funds found.', 'pipback');
    }

    public function column_category($item) {
        $category_name = !empty($item['category_name']) ? esc_html($item['category_name']) : 'Uncategorized';
        return '<p style="text-align:center; margin:0; padding: 2px 10px;">' . $category_name . '</p>';
    }

    public function display() {
        wp_enqueue_script('jquery-ui-sortable');
        wp_add_inline_script('jquery-ui-sortable', '
            jQuery(document).ready(function($) {
                var $table = $(".wp-list-table tbody");
                
                $table.sortable({
                    axis: "y",
                    cursor: "move",
                    opacity: 0.7,
                    update: function(event, ui) {
                        var order = [];
                        $table.find("tr").each(function() {
                            var id = $(this).attr("id").replace("fund-", "");
                            if (id) {
                                order.push(id);
                            }
                        });

                        if (order.length === 0) {
                            console.error("No valid IDs found");
                            return;
                        }

                        // Send AJAX request to save order
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "save_pipfunds_order",
                                order: order,
                                nonce: "' . wp_create_nonce("pipfunds_order_nonce") . '"
                            },
                            success: function(response) {
                                if (!response.success) {
                                    console.error("Error saving order:", response.data);
                                    alert("Error saving order. Please try again.");
                                } else {
                                    console.log("Order saved successfully");
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error("AJAX error:", error);
                                alert("Error saving order. Please try again.");
                            }
                        });
                    }
                });
            });
        ');
        
        echo '<style>
            .wp-list-table tbody tr {
                cursor: move;
            }
            .wp-list-table tbody tr.ui-sortable-helper {
                background-color:rgb(83, 70, 70);
                box-shadow: 0 0 8px rgba(0,0,0,0.1);
            }
            .wp-list-table tbody tr.ui-sortable-placeholder {
                visibility: visible !important;
                background-color: #f0f0f0;
                height: 37px; /* Adjust based on your row height */
            }
        </style>';
        
        parent::display();
    }
}
