<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Pip_Firm_FAQs_Table extends WP_List_Table {
    private $data;

    public function __construct() {
        parent::__construct([
            'singular' => 'faq',
            'plural'   => 'faqs',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'      => '<input type="checkbox" />',
            'group'   => '<p style="text-align: center; margin: 0;">FAQ Group</p>',
            'title'   => '<p style="text-align: center; margin: 0;">Title</p>',
            'content' => '<p style="text-align: center; margin: 0;">Content</p>',
            'actions' => '<p style="text-align: center; margin: 0;">Actions</p>',
        ];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="faq_id[]" value="%s" class="faq-checkbox" />', esc_attr($item['id']));
    }

    public function column_group($item) {
        return esc_html($item['group_title'] ?? '(No Group)');
    }

    public function column_title($item) {
        return esc_html($item['title']);
    }

    public function column_content($item) {
        return wp_kses_post($item['content']);
    }

    public function column_actions($item) {
        $edit_url = add_query_arg([
            'page'       => 'pipback-firm-faqs',
            'faq_action' => 'edit',
            'id'         => $item['id'],
        ], admin_url('admin.php'));

        $delete_url = wp_nonce_url(add_query_arg([
            'page'       => 'pipback-firm-faqs',
            'faq_action' => 'delete_single',
            'id'         => $item['id'],
        ], admin_url('admin.php')), 'delete_faq_' . $item['id']);

        return sprintf(
            '<a href="%s" class="button button-small button-primary">Edit</a> <a href="%s" class="button button-small" onclick="return confirm(\'Are you sure you want to delete this FAQ?\')">Delete</a>',
            esc_url($edit_url),
            esc_url($delete_url)
        );
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'Delete',
        ];
    }

    public function process_bulk_action() {
        if ((isset($_POST['action']) && $_POST['action'] === 'delete') || (isset($_POST['action2']) && $_POST['action2'] === 'delete')) {
            global $wpdb;
            $table = $wpdb->prefix . 'firm_faqs';

            if (!empty($_POST['faq_id']) && is_array($_POST['faq_id'])) {
                $ids = array_map('intval', $_POST['faq_id']);
                foreach ($ids as $id) {
                    $wpdb->delete($table, ['id' => $id]);
                }
                echo '<div class="notice notice-success"><p>Selected FAQs deleted.</p></div>';
            }
        }

        if (
            isset($_GET['faq_action'], $_GET['id']) &&
            $_GET['faq_action'] === 'delete_single' &&
            current_user_can('manage_options')
        ) {
            global $wpdb;
            $table = $wpdb->prefix . 'firm_faqs';

            $id = intval($_GET['id']);
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_faq_' . $id)) {
                $wpdb->delete($table, ['id' => $id]);
                echo '<div class="notice notice-success"><p>FAQ deleted.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
            }
        }
    }

    public function extra_tablenav($which) {
        if ($which === 'top') {
            global $wpdb;
            $group_table = $wpdb->prefix . 'firm_faq_groups';
            $groups = $wpdb->get_results("SELECT id, title FROM $group_table ORDER BY title ASC");

            $selected_group = isset($_GET['faq_group_filter']) ? intval($_GET['faq_group_filter']) : '';
            $search_title   = isset($_GET['faq_title_filter']) ? sanitize_text_field($_GET['faq_title_filter']) : '';
            ?>
            <div class="alignleft actions">
                <select name="faq_group_filter" id="faq_group_filter_auto_submit">
                    <option value="">All Groups</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo esc_attr($group->id); ?>" <?php selected($selected_group, $group->id); ?>>
                            <?php echo esc_html($group->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="faq_title_filter" placeholder="Search Title..." value="<?php echo esc_attr($search_title); ?>" />

                <input type="submit" class="button" value="Search">
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const groupFilter = document.getElementById('faq_group_filter_auto_submit');
                    if (groupFilter) {
                        groupFilter.addEventListener('change', function () {
                            this.form.submit();
                        });
                    }
                });
            </script>
            <?php
        }
    }

    public function prepare_items() {
        global $wpdb;
        $faq_table   = $wpdb->prefix . 'firm_faqs';
        $group_table = $wpdb->prefix . 'firm_faq_groups';

        $this->process_bulk_action();

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($_GET['faq_group_filter'])) {
            $where .= ' AND f.group_id = %d';
            $params[] = intval($_GET['faq_group_filter']);
        }

        if (!empty($_GET['faq_title_filter'])) {
            $where .= ' AND f.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like($_GET['faq_title_filter']) . '%';
        }

        // Count for pagination
        $count_sql = "SELECT COUNT(*) FROM $faq_table f $where";
        if (!empty($params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_sql, ...$params));
        } else {
            $total_items = $wpdb->get_var($count_sql);
        }
        // Fetch filtered data
        $data_sql = "SELECT f.*, g.title AS group_title 
                     FROM $faq_table f
                     LEFT JOIN $group_table g ON f.group_id = g.id
                     $where
                     ORDER BY f.id DESC
                     LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        if (!empty($params)) {
            $this->data = $wpdb->get_results($wpdb->prepare($data_sql, ...$params), ARRAY_A);
        } else {
            $this->data = $wpdb->get_results($data_sql, ARRAY_A);
        }
        $this->items = $this->data;

        $this->_column_headers = [$this->get_columns(), [], []];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    public function no_items() {
        _e('No FAQs found.', 'pipback');
    }
}
