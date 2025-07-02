<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Pip_Firm_FAQs_Table extends WP_List_Table
{
    private $data;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'faq',
            'plural' => 'faqs',
            'ajax' => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'group' => '<p style="text-align: center; margin: 0;">FAQ Group</p>',
            'title' => '<p style="text-align: center; margin: 0;">Title</p>',
            'content' => '<p style="text-align: center; margin: 0;">Content</p>',
            'actions' => '<p style="text-align: center; margin: 0;">Actions</p>',
        ];
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="faq_id[]" value="%s" class="faq-checkbox" />', esc_attr($item['id']));
    }

    public function column_group($item)
    {
        return esc_html($item['group_title'] ?? '(No Group)');
    }

    public function column_title($item)
    {
        return esc_html($item['title']);
    }

    public function column_content($item)
    {
        return wp_kses_post($item['content']);
    }

    public function column_actions($item)
    {
        return sprintf(
            '<a data-id="%d" class="button button-small button-primary edit-faq">Edit</a> <a data-id="%d" class="button button-small delete-faq" onclick="return confirm(\'Are you sure you want to delete this FAQ?\')">Delete</a>',
            $item['id'],
            $item['id']
        );
    }

    public function display_tablenav($which)
    {
        if ($which === 'top') {
            parent::display_tablenav($which); // show top bulk actions
        }
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => 'Delete',
        ];
    }

    public function extra_tablenav($which)
    {
        if ($which === 'top') {
            global $wpdb;
            $group_table = $wpdb->prefix . 'firm_faq_groups';
            $groups = $wpdb->get_results("SELECT id, title FROM $group_table ORDER BY title ASC");

            $selected_group = isset($_POST['faq_group_filter']) ? intval($_POST['faq_group_filter']) : '';
            $search_title = isset($_POST['faq_title_filter']) ? sanitize_text_field($_POST['faq_title_filter']) : '';
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

                <input type="text" name="faq_title_filter" placeholder="Search Title..."
                    value="<?php echo esc_attr($search_title); ?>" />

                <input type="button" id="faq_search_btn" class="button" value="Search">
            </div>
            <script>
                jQuery(function ($) {
                    console.log($(this.form))

                    function refreshCheckboxes() {
                        let selectedIds = $('#faq-hidden-values').val().split(',').filter(id => id);

                        $('.faq-checkbox').each(function () {
                            const id = $(this).val();
                            $(this).prop('checked', selectedIds.includes(id));
                        });
                    }

                    function loadByFilter() {
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'load_faq_table',
                                faq_group_filter: $('#faq_group_filter_auto_submit').val(),
                                faq_title_filter: $('[name="faq_title_filter"').val()
                            },
                            success: function (response) {
                                $('#faq-table-div').html(response);
                                refreshCheckboxes();
                            }
                        });
                    }

                    $('#faq_group_filter_auto_submit').on('change', loadByFilter)
                    $('#faq_search_btn').on('click', loadByFilter)
                })
            </script>
            <?php
        }
    }

    public function prepare_items()
    {
        global $wpdb;
        $faq_table = $wpdb->prefix . 'firm_faqs';
        $group_table = $wpdb->prefix . 'firm_faq_groups';

        $this->process_bulk_action();

        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($_POST['faq_group_filter'])) {
            $where .= ' AND f.group_id = %d';
            $params[] = intval($_POST['faq_group_filter']);
        }

        if (!empty($_POST['faq_title_filter'])) {
            $where .= ' AND f.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like($_POST['faq_title_filter']) . '%';
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
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    public function no_items()
    {
        _e('No FAQs found.', 'pipback');
    }
}
