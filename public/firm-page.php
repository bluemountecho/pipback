<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    add_rewrite_rule('^firms/([^/]+)/?', 'index.php?pip_firm=$matches[1]', 'top');
    add_rewrite_tag('%pip_firm%', '([^&]+)');
    flush_rewrite_rules();
});

add_action('template_redirect', function () {
    $slug = get_query_var('pip_firm');
    if (!$slug) return;

    global $wpdb;
    $table = $wpdb->prefix . 'pipfunds';

    $firm = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE slug = %s", $slug));
    if (!$firm) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }

    $faq_items = [];
    if (!empty($firm->faq_ids)) {
        $faq_ids = explode(',', $firm->faq_ids);
        $faq_ids = array_map('intval', $faq_ids);
        $placeholders = implode(',', array_fill(0, count($faq_ids), '%d'));
        $faq_table = $wpdb->prefix . 'firm_faqs';
        $faq_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $faq_table WHERE id IN ($placeholders)",
            ...$faq_ids
        ));
    }

    // Output firm page
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<title>' . esc_html($firm->title) . '</title>';
    echo '<style>
            body { font-family: sans-serif; padding: 40px; max-width: 800px; margin: auto; }
            .firm-image { max-width: 200px; margin-bottom: 20px; }
            .faq h3 { margin-top: 1rem; }
          </style>';
    echo '</head><body>';

    echo '<h1>' . esc_html($firm->title) . '</h1>';

    if ($firm->image_link) {
        echo '<img src="' . esc_url($firm->image_link) . '" class="firm-image" />';
    }

    echo '<p><strong>Cashback:</strong> ' . esc_html($firm->cashback) . '%</p>';
    echo '<p><strong>Discount:</strong> ' . esc_html($firm->discount) . '%</p>';
    echo '<p><strong>Review:</strong> ' . esc_html($firm->review) . '/5</p>';
    echo '<p><strong>First Time:</strong> ' . ($firm->first_time ? 'Yes' : 'No') . '</p>';
    echo '<p><strong>Link:</strong> <a href="' . esc_url($firm->link) . '" target="_blank">' . esc_url($firm->link) . '</a></p>';

    echo '<h2>Description</h2>';
    echo '<p>' . nl2br(esc_html($firm->description)) . '</p>';

    if (!empty($firm->second_description)) {
        echo '<h2>More Info</h2>';
        echo '<p>' . nl2br(esc_html($firm->second_description)) . '</p>';
    }

    if (!empty($faq_items)) {
        echo '<div class="faq">';
        echo '<h2>FAQs</h2>';
        foreach ($faq_items as $faq) {
            echo '<div class="faq-item">';
            echo '<h3>' . esc_html($faq->title) . '</h3>';
            echo '<p>' . nl2br(esc_html($faq->content)) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    echo '</body></html>';
    exit;
});
