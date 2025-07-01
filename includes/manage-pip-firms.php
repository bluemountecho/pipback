<?php

/**
 * Plugin Name: PropFirm Data Manager
 * Description: Manages prop firm data for the Chrome extension, including custom post type, admin fields, and JSON API endpoint.
 * Version: 1.0
 * Author: Your Name/Company Name
 * License: GPL2
 */

// Ensure WordPress is loaded directly.
defined('ABSPATH') || exit;


// Register Custom Post Type 'Firm'
function propfirm_register_firm_post_type()
{
    $labels = array(
        'name'                  => _x('Firms', 'Post Type General Name', 'textdomain'),
        'singular_name'         => _x('Firm', 'Post Type Singular Name', 'textdomain'),
        'menu_name'             => __('Firms', 'textdomain'),
        'name_admin_bar'        => __('Firm', 'textdomain'),
        'archives'              => __('Firm Archives', 'textdomain'),
        'attributes'            => __('Firm Attributes', 'textdomain'),
        'parent_item_colon'     => __('Parent Firm:', 'textdomain'),
        'all_items'             => __('All Firm Endpoints', 'textdomain'),
        'add_new_item'          => __('Add New Firm', 'textdomain'),
        'add_new'               => __('Add New', 'textdomain'),
        'new_item'              => __('New Firm', 'textdomain'),
        'edit_item'             => __('Edit Firm', 'textdomain'),
        'update_item'           => __('Update Firm', 'textdomain'),
        'view_item'             => __('View Firm', 'textdomain'),
        'view_items'            => __('View Firms', 'textdomain'),
        'search_items'          => __('Search Firm', 'textdomain'),
        'not_found'             => __('Not found', 'textdomain'),
        'not_found_in_trash'    => __('Not found in Trash', 'textdomain'),
        'featured_image'        => __('Featured Image', 'textdomain'),
        'set_featured_image'    => __('Set featured image', 'textdomain'),
        'remove_featured_image' => __('Remove featured image', 'textdomain'),
        'use_featured_image'    => __('Use as featured image', 'textdomain'),
        'insert_into_item'      => __('Insert into firm', 'textdomain'),
        'uploaded_to_this_item' => __('Uploaded to this firm', 'textdomain'),
        'items_list'            => __('Firms list', 'textdomain'),
        'items_list_navigation' => __('Firms list navigation', 'textdomain'),
        'filter_items_list'     => __('Filter firms list', 'textdomain'),
    );
    $args = array(
        'label'                 => __('Firm', 'textdomain'),
        'description'           => __('Prop Firm data for Chrome Extension', 'textdomain'),
        'labels'                => $labels,
        'supports'              => array('title'), // Only 'title' is needed; all other data will be in meta boxes
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => 'pipback-dashboard',
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-admin-post', // You can change this icon
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false, // Set to false since we only need the API endpoint
        'capability_type'       => 'post',
        'show_in_rest'          => true, // IMPORTANT: Expose to REST API
        'rest_base'             => 'firms', // The base slug for the REST API endpoint (e.g., wp-json/wp/v2/firms)
    );
    register_post_type('firm', $args);
}
add_action('init', 'propfirm_register_firm_post_type', 0);


// Add a meta box for firm details
function propfirm_add_firm_meta_box()
{
    add_meta_box(
        'propfirm_firm_details',               // Unique ID
        __('Firm Details', 'textdomain'),    // Title of the meta box
        'propfirm_firm_details_callback',      // Callback function to render the fields
        'firm',                                // Post type to attach the meta box to
        'normal',                              // Context (normal, advanced, side)
        'high'                                 // Priority (high, core, default, low)
    );
}
add_action('add_meta_boxes', 'propfirm_add_firm_meta_box');

// Callback function to render the firm details fields
function propfirm_firm_details_callback($post)
{
    // Add a nonce field so we can check it later for security
    wp_nonce_field('propfirm_save_firm_details', 'propfirm_firm_details_nonce');

    // Get existing meta values (or set empty strings for new posts)
    $main_domains = get_post_meta($post->ID, '_propfirm_main_domains', true); // New field
    $trigger_domains = get_post_meta($post->ID, '_propfirm_trigger_domains', true);
    $coupon_field_selector = get_post_meta($post->ID, '_propfirm_coupon_field_selector', true);
    $coupon_field_apply_selector = get_post_meta($post->ID, '_propfirm_coupon_field_apply_selector', true);
    $discount_code = get_post_meta($post->ID, '_propfirm_discount_code', true);
    $affiliate_url = get_post_meta($post->ID, '_propfirm_affiliate_url', true);
    $cashback_percentage = get_post_meta($post->ID, '_propfirm_cashback_percentage', true);
    $discount_percentage = get_post_meta($post->ID, '_propfirm_discount_percentage', true);
    // New fields
    $category = get_post_meta($post->ID, '_propfirm_category', true);
    $first_time_offer = get_post_meta($post->ID, '_propfirm_first_time_offer', true);
    $prop_firm_logo_url = get_post_meta($post->ID, '_propfirm_prop_firm_logo_url', true);
    $prop_firm_rating = get_post_meta($post->ID, '_propfirm_prop_firm_rating', true);

    // Define allowed categories
    $categories = ['CFDs', 'Futures'];
?>
    <table class="form-table">
        <tbody>
            <tr>
                <th><label for="propfirm_main_domains"><?php _e('Main Domains', 'textdomain'); ?></label></th>
                <td>
                    <textarea id="propfirm_main_domains" name="propfirm_main_domains" rows="3" class="large-text code" placeholder="Enter each main domain on a new line (e.g.,&#10;maindomain.com&#10;anothermain.net)"><?php echo esc_textarea($main_domains); ?></textarea>
                    <p class="description"><?php _e('Enter one main domain URL per line. These will be the primary domains associated with the firm.', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_trigger_domains"><?php _e('Trigger Domains', 'textdomain'); ?></label></th>
                <td>
                    <textarea id="propfirm_trigger_domains" name="propfirm_trigger_domains" rows="5" class="large-text code" placeholder="Enter each domain on a new line (e.g.,&#10;example.com&#10;sub.example.net)"><?php echo esc_textarea($trigger_domains); ?></textarea>
                    <p class="description"><?php _e('Enter one domain URL per line. These domains will trigger the extension.', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_coupon_field_selector"><?php _e('Coupon Field Selector', 'textdomain'); ?></label></th>
                <td>
                    <input type="text" id="propfirm_coupon_field_selector" name="propfirm_coupon_field_selector" value="<?php echo esc_attr($coupon_field_selector); ?>" class="regular-text code" />
                    <p class="description"><?php _e('CSS selector for the coupon input field (e.g., #coupon_code).', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_coupon_field_apply_selector"><?php _e('Coupon Apply Selector', 'textdomain'); ?></label></th>
                <td>
                    <input type="text" id="propfirm_coupon_field_apply_selector" name="propfirm_coupon_field_apply_selector" value="<?php echo esc_attr($coupon_field_apply_selector); ?>" class="regular-text code" />
                    <p class="description"><?php _e('CSS selector for the apply button (e.g., .coupon-form button).', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_discount_code"><?php _e('Discount Code', 'textdomain'); ?></label></th>
                <td>
                    <input type="text" id="propfirm_discount_code" name="propfirm_discount_code" value="<?php echo esc_attr($discount_code); ?>" class="regular-text" />
                    <p class="description"><?php _e('The actual discount code (e.g., SAVE10).', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_affiliate_url"><?php _e('Affiliate URL', 'textdomain'); ?></label></th>
                <td>
                    <input type="url" id="propfirm_affiliate_url" name="propfirm_affiliate_url" value="<?php echo esc_url($affiliate_url); ?>" class="regular-text code" />
                    <p class="description"><?php _e('The full affiliate tracking URL.', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_cashback_percentage"><?php _e('Cashback Percentage', 'textdomain'); ?></label></th>
                <td>
                    <input type="number" id="propfirm_cashback_percentage" name="propfirm_cashback_percentage" value="<?php echo esc_attr($cashback_percentage); ?>" class="small-text" min="0" max="100" /> %
                    <p class="description"><?php _e('Cashback percentage (e.g., 15).', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_discount_percentage"><?php _e('Discount Percentage', 'textdomain'); ?></label></th>
                <td>
                    <input type="number" id="propfirm_discount_percentage" name="propfirm_discount_percentage" value="<?php echo esc_attr($discount_percentage); ?>" class="small-text" min="0" max="100" /> %
                    <p class="description"><?php _e('Discount percentage (e.g., 20).', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_category"><?php _e('Category', 'textdomain'); ?></label></th>
                <td>
                    <select id="propfirm_category" name="propfirm_category" class="regular-text">
                        <?php foreach ($categories as $option) : ?>
                            <option value="<?php echo esc_attr($option); ?>" <?php selected($category, $option); ?>><?php echo esc_html($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the category of the prop firm.', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_first_time_offer"><?php _e('First Time Purchase Offer', 'textdomain'); ?></label></th>
                <td>
                    <input type="checkbox" id="propfirm_first_time_offer" name="propfirm_first_time_offer" value="1" <?php checked(1, $first_time_offer, true); ?> />
                    <p class="description"><?php _e('Check if this is a first-time offer.', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_prop_firm_logo_url"><?php _e('Prop Firm Logo URL', 'textdomain'); ?></label></th>
                <td>
                    <input type="url" id="propfirm_prop_firm_logo_url" name="propfirm_prop_firm_logo_url" value="<?php echo esc_url($prop_firm_logo_url); ?>" class="regular-text code" />
                    <p class="description"><?php _e('URL to the prop firm logo image.', 'textdomain'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="propfirm_prop_firm_rating"><?php _e('Prop Firm Rating', 'textdomain'); ?></label></th>
                <td>
                    <input type="number" id="propfirm_prop_firm_rating" name="propfirm_prop_firm_rating" value="<?php echo esc_attr($prop_firm_rating); ?>" class="small-text" step="0.1" min="0" max="5" />
                    <p class="description"><?php _e('Rating of the prop firm (e.g., 4).', 'textdomain'); ?></p>
                </td>
            </tr>
            </tbody>
    </table>
<?php
}


// Save the firm details meta box data
function propfirm_save_firm_details_meta($post_id)
{
    // Check if our nonce is set.
    if (!isset($_POST['propfirm_firm_details_nonce'])) {
        return $post_id;
    }

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($_POST['propfirm_firm_details_nonce'], 'propfirm_save_firm_details')) {
        return $post_id;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Check the user's permissions.
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // Sanitize and save/update post meta.
    // Main Domains (New Field - Text Area)
    $main_domains_data = isset($_POST['propfirm_main_domains']) ? sanitize_textarea_field($_POST['propfirm_main_domains']) : '';
    update_post_meta($post_id, '_propfirm_main_domains', $main_domains_data);

    // Trigger Domains (Text Area)
    $trigger_domains_data = isset($_POST['propfirm_trigger_domains']) ? sanitize_textarea_field($_POST['propfirm_trigger_domains']) : '';
    update_post_meta($post_id, '_propfirm_trigger_domains', $trigger_domains_data);

    // Coupon Field Selector
    $coupon_field_selector_data = isset($_POST['propfirm_coupon_field_selector']) ? sanitize_text_field($_POST['propfirm_coupon_field_selector']) : '';
    update_post_meta($post_id, '_propfirm_coupon_field_selector', $coupon_field_selector_data);

    // Coupon Field Apply Selector
    $coupon_field_apply_selector_data = isset($_POST['propfirm_coupon_field_apply_selector']) ? sanitize_text_field($_POST['propfirm_coupon_field_apply_selector']) : '';
    update_post_meta($post_id, '_propfirm_coupon_field_apply_selector', $coupon_field_apply_selector_data);

    // Discount Code
    $discount_code_data = isset($_POST['propfirm_discount_code']) ? sanitize_text_field($_POST['propfirm_discount_code']) : '';
    update_post_meta($post_id, '_propfirm_discount_code', $discount_code_data);

    // Affiliate URL
    $affiliate_url_data = isset($_POST['propfirm_affiliate_url']) ? esc_url_raw($_POST['propfirm_affiliate_url']) : '';
    update_post_meta($post_id, '_propfirm_affiliate_url', $affiliate_url_data);

    // Cashback Percentage (cast to integer)
    $cashback_percentage_data = isset($_POST['propfirm_cashback_percentage']) ? (int) $_POST['propfirm_cashback_percentage'] : 0;
    update_post_meta($post_id, '_propfirm_cashback_percentage', $cashback_percentage_data);

    // Discount Percentage (cast to integer)
    $discount_percentage_data = isset($_POST['propfirm_discount_percentage']) ? (int) $_POST['propfirm_discount_percentage'] : 0;
    update_post_meta($post_id, '_propfirm_discount_percentage', $discount_percentage_data);

    // New fields
    // Category (validate against allowed categories)
    $allowed_categories = ['CFDs', 'Futures'];
    $category_data = isset($_POST['propfirm_category']) && in_array($_POST['propfirm_category'], $allowed_categories) ? sanitize_text_field($_POST['propfirm_category']) : '';
    update_post_meta($post_id, '_propfirm_category', $category_data);


    // First Time Offer (checkbox)
    $first_time_offer_data = isset($_POST['propfirm_first_time_offer']) ? 1 : 0;
    update_post_meta($post_id, '_propfirm_first_time_offer', $first_time_offer_data);

    // Prop Firm Logo URL
    $prop_firm_logo_url_data = isset($_POST['propfirm_prop_firm_logo_url']) ? esc_url_raw($_POST['propfirm_prop_firm_logo_url']) : '';
    update_post_meta($post_id, '_propfirm_prop_firm_logo_url', $prop_firm_logo_url_data);

    // Prop Firm Rating (float)
    $prop_firm_rating_data = isset($_POST['propfirm_prop_firm_rating']) ? (float) $_POST['propfirm_prop_firm_rating'] : 0.0;
    update_post_meta($post_id, '_propfirm_prop_firm_rating', $prop_firm_rating_data);
}
add_action('save_post', 'propfirm_save_firm_details_meta');


// Register a custom REST API endpoint for firms data
function propfirm_register_rest_endpoint()
{
    register_rest_route('propfirm-extension/v1', '/firms/', array(
        'methods'             => 'GET',
        'callback'            => 'propfirm_get_firms_data_from_db',
        'permission_callback' => '__return_true', // Allows public access. Adjust if you need authentication.
        'args'                => array(
            'post_type' => array(
                'default'           => 'firm',
                'sanitize_callback' => 'sanitize_key',
            ),
        ),
    ));
}
add_action('rest_api_init', 'propfirm_register_rest_endpoint');

// Callback function to get and format firms data
function propfirm_get_firms_data_from_db(WP_REST_Request $request)
{
    $firms_data = array();

    $args = array(
        'post_type'      => 'firm',
        'posts_per_page' => -1, // Get all posts
        'post_status'    => 'publish', // Only published firms
        'no_found_rows'  => true,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    );

    $firms_query = new WP_Query($args);

    if ($firms_query->have_posts()) {
        while ($firms_query->have_posts()) {
            $firms_query->the_post();
            $post_id = get_the_ID();

            // Retrieve saved custom meta data
            $main_domains_raw = get_post_meta($post_id, '_propfirm_main_domains', true); // New field
            $trigger_domains_raw = get_post_meta($post_id, '_propfirm_trigger_domains', true);

            // Process the text area content into an array of domains
            $formatted_main_domains = [];
            if (!empty($main_domains_raw)) {
                $lines = array_map('trim', explode("\n", $main_domains_raw));
                $formatted_main_domains = array_values(array_filter($lines)); // Remove empty lines and re-index
            }

            // Process the text area content into an array of domains
            $formatted_trigger_domains = [];
            if (!empty($trigger_domains_raw)) {
                $lines = array_map('trim', explode("\n", $trigger_domains_raw));
                $formatted_trigger_domains = array_values(array_filter($lines)); // Remove empty lines and re-index
            }

            $firm = array(
                'name'                     => get_the_title(),
                'mainDomains'              => $formatted_main_domains, // New field for API output
                'triggerDomains'           => $formatted_trigger_domains,
                'couponFieldSelector'      => get_post_meta($post_id, '_propfirm_coupon_field_selector', true),
                'couponFieldApplySelector' => get_post_meta($post_id, '_propfirm_coupon_field_apply_selector', true),
                'discountCode'             => get_post_meta($post_id, '_propfirm_discount_code', true),
                'affiliateUrl'             => get_post_meta($post_id, '_propfirm_affiliate_url', true),
                'cashbackPercentage'       => (int) get_post_meta($post_id, '_propfirm_cashback_percentage', true),
                'discountPercentage'       => (int) get_post_meta($post_id, '_propfirm_discount_percentage', true),
                // New fields for API output
                'category'                 => get_post_meta($post_id, '_propfirm_category', true),
                'firstTimeOffer'           => (bool) get_post_meta($post_id, '_propfirm_first_time_offer', true),
                'propFirmLogoUrl'          => get_post_meta($post_id, '_propfirm_prop_firm_logo_url', true),
                'propFirmRating'           => (float) get_post_meta($post_id, '_propfirm_prop_firm_rating', true),
            );
            $firms_data[] = $firm;
        }
        wp_reset_postdata(); // Restore original post data
    }

    return new WP_REST_Response(array('firms' => $firms_data), 200);
}

// Add submenu for sorting firms
function propfirm_add_sort_menu_page() {
    add_submenu_page(
        'pipback-dashboard',
        'Sort Firms',
        'Sort Firms',
        'edit_posts',
        'sort-firms',
        'propfirm_sort_firms_page'
    );
}
add_action('admin_menu', 'propfirm_add_sort_menu_page');

// Render sorting UI
function propfirm_sort_firms_page() {
    $firms = get_posts([
        'post_type' => 'firm',
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);
    ?>
    <div class="wrap">
        <h1>Sort Firms</h1>
        <div id="firm-sort-message" style="display:none; padding:10px; margin-bottom:15px;"></div>
        <ul id="firm-sortable">
            <?php foreach ($firms as $firm): ?>
                <li class="ui-state-default" data-id="<?= esc_attr($firm->ID); ?>">
                    <?= esc_html($firm->post_title); ?> &nbsp; &nbsp;  (<?= esc_html($firm->_propfirm_main_domains); ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <style>
        #firm-sortable { list-style-type: none; margin: 0; padding: 0; width: 400px; }
        #firm-sortable li { margin: 5px 0; padding: 10px; background: #fff; border: 1px solid #ccc; cursor: move; }
    
        #firm-sort-message.success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        #firm-sort-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

    </style>
    <script>
        jQuery(function($) {
            $('#firm-sortable').sortable({
                update: function(event, ui) {
                    let order = [];
                    $('#firm-sortable li').each(function(index) {
                        order.push({ id: $(this).data('id'), position: index });
                    });
                    $.post(ajaxurl, {
                        action: 'propfirm_update_order',
                        order: order,
                        nonce: '<?= wp_create_nonce("propfirm_order_nonce"); ?>'
                    }, function(response) {
                        let $msg = $('#firm-sort-message');
                        if (response.success) {
                            $msg
                                .removeClass('error')
                                .addClass('success')
                                .text('Order updated successfully!')
                                .fadeIn()
                                .delay(3000)
                                .fadeOut();
                        } else {
                            $msg
                                .removeClass('success')
                                .addClass('error')
                                .text('Failed to update order.')
                                .fadeIn()
                                .delay(3000)
                                .fadeOut();
                        }
                    });
                }
            }).disableSelection();
        });
    </script>
    <?php
}

// Handle AJAX sort order update
add_action('wp_ajax_propfirm_update_order', function () {
    check_ajax_referer('propfirm_order_nonce', 'nonce');

    if (!isset($_POST['order']) || !is_array($_POST['order'])) {
        wp_send_json_error('Invalid input');
    }

    foreach ($_POST['order'] as $item) {
        $post_id = intval($item['id']);
        $position = intval($item['position']);
        wp_update_post([
            'ID' => $post_id,
            'menu_order' => $position
        ]);
    }

    wp_send_json_success();
});

// Ensure menu_order sorting in admin
add_filter('pre_get_posts', function ($query) {
    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'firm' && !$query->get('orderby')) {
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
});


add_action('admin_enqueue_scripts', function ($hook) {
    // Only enqueue on the custom sorting page
    if (isset($_GET['page']) && $_GET['page'] === 'sort-firms') {
        wp_enqueue_script('jquery-ui-sortable');
    }
});
