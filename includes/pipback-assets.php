<?php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('jquery');
    wp_enqueue_style(
        'bootstrap-css', 
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        [],
        '5.3.0'
    );
    
    // Enqueue Bootstrap JS bundle (includes Popper.js)
    wp_enqueue_script(
        'bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        ['jquery'],
        '5.3.0',
        true // Load in footer
    );

    wp_enqueue_style('inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', [], null);

    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
    wp_enqueue_style('flatpickr-dark', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css', ['flatpickr-css'], null);
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
});

?>
