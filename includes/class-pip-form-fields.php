<?php
/**
 * Class to handle prop firm form fields
 */
class Pip_Form_Fields {
    
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('init', array($this, 'create_tables'));
        add_action('wp_ajax_save_form_fields', array($this, 'save_form_fields'));
        add_action('wp_ajax_get_form_fields', array($this, 'get_form_fields_ajax'));
    }
    
    /**
     * Create necessary database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for prop firm form configurations
        $table_name = $wpdb->prefix . 'pipfirm_forms';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            firm_id mediumint(9) NOT NULL,
            form_fields longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY firm_id (firm_id)
        ) $charset_collate;";
        
        // Table for cashback requests
        $cashback_table = $wpdb->prefix . 'cashbackrequests';
        
        // Check if custom_fields column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $cashback_table LIKE 'custom_fields'");
        
        if (empty($column_exists)) {
            // Add custom_fields column if it doesn't exist
            $wpdb->query("ALTER TABLE $cashback_table ADD COLUMN custom_fields longtext DEFAULT NULL");
        }
        
        // Table for user payment methods
        $payment_methods_table = $wpdb->prefix . 'user_payment_methods';
        
        $payment_methods_sql = "CREATE TABLE $payment_methods_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            method_type varchar(50) NOT NULL,
            method_data longtext NOT NULL,
            is_default tinyint(1) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY method_type (method_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($payment_methods_sql);
    }
    
    /**
     * Enqueue necessary scripts for the admin page
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_script('jquery');
    
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-mouse');
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $this->enqueue_admin_scripts();
        global $wpdb;
        
        // Get all prop firms
        $firms_table = $wpdb->prefix . 'pipfunds';
        $firms = $wpdb->get_results("SELECT id, title FROM $firms_table ORDER BY title ASC");
        
        ?>
        <div class="wrap">
            <h1>Prop Firm Form Fields</h1>
            
            <div class="form-fields-container">
                <div class="form-fields-header">
                    <label for="firm-selector">Select Prop Firm:</label>
                    <select id="firm-selector" class="firm-selector">
                        <option value="">-- Select a Prop Firm --</option>
                        <?php foreach ($firms as $firm): ?>
                            <option value="<?php echo esc_attr($firm->id); ?>"><?php echo esc_html($firm->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="form-fields-editor" class="form-fields-editor" style="display: none;">
                    <h2>Form Fields for <span id="selected-firm-name"></span></h2>
                    
                    <div id="fields-container" class="fields-container">
                        <!-- Fields will be added here dynamically -->
                    </div>
                    
                    <div class="field-actions">
                        <button id="add-field" class="button button-secondary">Add Field</button>
                        <button id="save-fields" class="button button-primary">Save Form Fields</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Field Template (Hidden) -->
        <div id="field-template" style="display: none;">
            <div class="form-field-row">
                <div class="field-controls">
                    <span class="dashicons dashicons-menu handle"></span>
                    <span class="dashicons dashicons-trash delete-field"></span>
                </div>
                <div class="field-settings">
                    <div class="field-setting">
                        <label>Label: <span class="required">*</span></label>
                        <input type="text" class="field-label" placeholder="Enter field label" required>
                    </div>
                    <div class="field-setting">
                        <label>Field Type:</label>
                        <select class="field-type">
                            <option value="text">Text</option>
                            <option value="date">Date</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div class="field-setting">
                        <label>Field Name: <span class="required">*</span></label>
                        <input type="text" class="field-name" placeholder="Enter field name (no spaces)" required>
                        <small>Used in form submission data. Will be auto-generated if left empty.</small>
                    </div>
                    <div class="field-setting">
                        <label>Placeholder:</label>
                        <input type="text" class="field-placeholder" placeholder="Enter placeholder text">
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            function initSortable() {
                $('#fields-container').sortable({
                    handle: '.handle',
                    axis: 'y',
                    opacity: 0.7
                });
            }
            
            // Show/hide options field based on field type
            $(document).on('change', '.field-type', function() {
                var fieldType = $(this).val();
                var optionsContainer = $(this).closest('.field-settings').find('.field-options-container');
                
                if (fieldType === 'select' || fieldType === 'checkbox' || fieldType === 'radio') {
                    optionsContainer.show();
                } else {
                    optionsContainer.hide();
                }
            });
            
            // Add new field
            $('#add-field').on('click', function() {
                var template = $('#field-template').html();
                var $fieldRow = $(template);
                
                var fieldCount = $('.form-field-row').length + 1;
                $fieldRow.find('.field-label').val('Field ' + fieldCount);
                $fieldRow.find('.field-name').val('field_' + fieldCount);
                
                $('#fields-container').append($fieldRow);
                initSortable();
                
                $fieldRow.find('.field-label').focus().select();
            });
            
            // Delete field
            $(document).on('click', '.delete-field', function() {
                $(this).closest('.form-field-row').remove();
            });
            
            // Auto-generate field name from label
            $(document).on('blur', '.field-label', function() {
                var fieldName = $(this).closest('.field-settings').find('.field-name');
                if (fieldName.val() === '') {
                    // Convert label to snake_case for field name
                    var name = $(this).val().toLowerCase()
                        .replace(/[^\w\s]/gi, '')
                        .replace(/\s+/g, '_');
                    fieldName.val(name);
                }
            });
            
            // Load form fields when firm is selected
            $('#firm-selector').on('change', function() {
                var firmId = $(this).val();
                
                if (firmId === '') {
                    $('#form-fields-editor').hide();
                    return;
                }
                
                var firmName = $(this).find('option:selected').text();
                $('#selected-firm-name').text(firmName);
                
                // Clear existing fields
                $('#fields-container').empty();
                
                // Show editor
                $('#form-fields-editor').show();
                
                // Load existing fields for this firm
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_form_fields',
                        firm_id: firmId,
                        nonce: '<?php echo wp_create_nonce('get_form_fields_nonce'); ?>'
                    },
                    success: function(response) {
                        console.log('Response:', response);
                        
                        if (response.success && response.data && response.data.fields) {
                            try {
                                // Log the raw JSON for debugging
                                console.log('Raw JSON:', response.data.fields);
                                
                                // Parse the JSON string into an array
                                var fields = JSON.parse(response.data.fields);
                                console.log('Parsed fields:', fields);
                                
                                if (Array.isArray(fields) && fields.length > 0) {
                                    // Clear existing fields first
                                    $('#fields-container').empty();
                                    
                                    // Add each field to the form
                                    fields.forEach(function(field) {
                                        var template = $('#field-template').html();
                                        var $fieldRow = $(template);
                                        
                                        $fieldRow.find('.field-label').val(field.label || '');
                                        $fieldRow.find('.field-type').val(field.type || 'text');
                                        $fieldRow.find('.field-name').val(field.name || '');
                                        $fieldRow.find('.field-required').prop('checked', !!field.required);
                                        $fieldRow.find('.field-placeholder').val(field.placeholder || '');
                                        
                                        // Handle options for select, checkbox, radio
                                        if (field.options && Array.isArray(field.options)) {
                                            $fieldRow.find('.field-options').val(field.options.join('\n'));
                                            if (field.type === 'select' || field.type === 'checkbox' || field.type === 'radio') {
                                                $fieldRow.find('.field-options-container').show();
                                            }
                                        }
                                        
                                        $('#fields-container').append($fieldRow);
                                    });
                                    
                                    initSortable();
                                } else {
                                    // No fields found, add a default one
                                    $('#add-field').click();
                                }
                            } catch (e) {
                                console.error('Error parsing JSON:', e);
                                console.error('Raw JSON:', response.data.fields);
                                
                                // Try to fix common JSON issues and parse again
                                try {
                                    // Replace escaped quotes
                                    var fixedJson = response.data.fields.replace(/\\"/g, '"');
                                    console.log('Attempting to fix JSON:', fixedJson);
                                    var fields = JSON.parse(fixedJson);
                                    
                                    // Clear existing fields first
                                    $('#fields-container').empty();
                                    
                                    // Add each field to the form
                                    fields.forEach(function(field) {
                                        var template = $('#field-template').html();
                                        var $fieldRow = $(template);
                                        
                                        $fieldRow.find('.field-label').val(field.label || '');
                                        $fieldRow.find('.field-type').val(field.type || 'text');
                                        $fieldRow.find('.field-name').val(field.name || '');
                                        $fieldRow.find('.field-required').prop('checked', !!field.required);
                                        $fieldRow.find('.field-placeholder').val(field.placeholder || '');
                                        
                                        // Handle options for select, checkbox, radio
                                        if (field.options && Array.isArray(field.options)) {
                                            $fieldRow.find('.field-options').val(field.options.join('\n'));
                                            if (field.type === 'select' || field.type === 'checkbox' || field.type === 'radio') {
                                                $fieldRow.find('.field-options-container').show();
                                            }
                                        }
                                        
                                        $('#fields-container').append($fieldRow);
                                    });
                                    
                                    initSortable();
                                } catch (e2) {
                                    console.error('Failed to fix JSON:', e2);
                                    // Add a default field if parsing fails
                                    $('#add-field').click();
                                }
                            }
                        } else {
                            // No valid response, add a default field
                            $('#add-field').click();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        // Add a default field if request fails
                        $('#add-field').click();
                    }
                });
            });
            
            // Save form fields
            $('#save-fields').on('click', function() {
                var firmId = $('#firm-selector').val();
                
                if (!firmId) {
                    alert('Please select a prop firm first.');
                    return;
                }
                
                var fields = [];
                
                $('#fields-container .form-field-row').each(function() {
                    var $field = $(this);
                    var fieldType = $field.find('.field-type').val();
                    var fieldLabel = $field.find('.field-label').val().trim();
                    var fieldName = $field.find('.field-name').val().trim();
                    
                    // Skip completely empty fields
                    if (fieldLabel === '' && fieldName === '') {
                        return;
                    }
                    
                    var fieldData = {
                        label: fieldLabel,
                        type: fieldType,
                        name: fieldName || fieldLabel.toLowerCase().replace(/[^\w\s]/gi, '').replace(/\s+/g, '_'),
                        required: $field.find('.field-required').is(':checked'),
                        placeholder: $field.find('.field-placeholder').val().trim()
                    };
                    
                    if (fieldType === 'select' || fieldType === 'checkbox' || fieldType === 'radio') {
                        var options = $field.find('.field-options').val().split('\n')
                            .map(function(option) { return option.trim(); })
                            .filter(function(option) { return option !== ''; });
                        
                        fieldData.options = options;
                    }
                    
                    fields.push(fieldData);
                });
                
                // Don't save if no valid fields
                if (fields.length === 0) {
                    alert('Please add at least one field with a label or name.');
                    return;
                }
                
                console.log("field",fields);
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_form_fields',
                        firm_id: firmId,
                        fields: JSON.stringify(fields),
                        nonce: '<?php echo wp_create_nonce('save_form_fields_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Form fields saved successfully!');
                        } else {
                            alert('Error saving form fields: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while saving form fields.');
                    }
                });
            });
            
            // Initialize
            initSortable();
        });
        </script>
        
        <style>
        .form-fields-container {
            margin-top: 20px;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* Add this new CSS */
        .required {
            color: #d63638;
        }

        .field-setting label {
            font-weight: 500;
        }

        .field-setting small {
            display: block;
            color: #666;
            margin-top: 2px;
        }

        .form-fields-header {
            margin-bottom: 20px;
        }
        
        .firm-selector {
            min-width: 300px;
            padding: 5px;
        }
        
        .fields-container {
            margin-bottom: 20px;
        }
        
        .form-field-row {
            display: flex;
            margin-bottom: 15px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 3px;
        }
        
        .field-controls {
            width: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-right: 10px;
        }
        
        .field-controls .dashicons {
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        .handle {
            cursor: move;
            color: #999;
        }
        
        .delete-field {
            color: #d63638;
        }
        
        .field-settings {
            flex: 1;
        }
        
        .field-setting {
            margin-bottom: 10px;
        }
        
        .field-setting label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .field-setting input[type="text"],
        .field-setting input[type="email"],
        .field-setting input[type="number"],
        .field-setting select,
        .field-setting textarea {
            width: 100%;
            max-width: 400px;
        }
        
        .field-options {
            height: 80px;
        }
        
        .field-actions {
            margin-top: 20px;
        }
        
        .field-actions button {
            margin-right: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Save form fields via AJAX
     */
    public function save_form_fields() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_form_fields_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
            return;
        }
        
        $firm_id = isset($_POST['firm_id']) ? intval($_POST['firm_id']) : 0;
        $fields = isset($_POST['fields']) ? sanitize_text_field($_POST['fields']) : '';
        
        if (!$firm_id || empty($fields)) {
            wp_send_json_error(array('message' => 'Missing required data'));
            return;
        }
        
        // Validate JSON
        $decoded_fields = json_decode(stripslashes($fields), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => 'Invalid JSON data: ' . json_last_error_msg(),
                'raw_json' => $fields
            ));
            return;
        }
        
        // Filter out empty fields
        $valid_fields = array();
        foreach ($decoded_fields as $field) {
            if (!empty($field['label']) || !empty($field['name'])) {
                // Ensure field name is set
                if (empty($field['name']) && !empty($field['label'])) {
                    $field['name'] = sanitize_title($field['label']);
                }

                $valid_fields[] = $field;
            }
        }
        
        // Re-encode with only valid fields
        $fields_json = json_encode($valid_fields);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pipfirm_forms';
        
        // Check if form already exists for this firm
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE firm_id = %d",
            $firm_id
        ));
        
        if ($existing) {
            // Update existing form
            $result = $wpdb->update(
                $table_name,
                array('form_fields' => $fields_json),
                array('firm_id' => $firm_id)
            );
        } else {
            // Insert new form
            $result = $wpdb->insert(
                $table_name,
                array(
                    'firm_id' => $firm_id,
                    'form_fields' => $fields_json
                )
            );
        }
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
    }
    
    /**
     * Get form fields via AJAX
     */
    public function get_form_fields_ajax() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_form_fields_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        $firm_id = isset($_POST['firm_id']) ? intval($_POST['firm_id']) : 0;
        
        if (!$firm_id) {
            wp_send_json_error(array('message' => 'Missing firm ID'));
            return;
        }
        
        // Get form fields for this firm
        $fields_json = $this->get_form_fields($firm_id);
        
        // Fix JSON escaping issues
        $fields_json = stripslashes($fields_json);
        
        // Return the fields
        wp_send_json_success(array('fields' => $fields_json));
    }
    
    /**
     * Get form fields for a specific prop firm
     */
    public function get_form_fields($firm_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pipfirm_forms';
        
        $form_fields = $wpdb->get_var($wpdb->prepare(
            "SELECT form_fields FROM $table_name WHERE firm_id = %d",
            $firm_id
        ));
        // Return empty array if no fields found
        return empty($form_fields) ? '[]' : $form_fields;
    }
    
    /**
     * Render form for a specific prop firm
     */
    public function render_form($firm_id, $form_id = 'prop-firm-form') {
        $fields_json = $this->get_form_fields($firm_id);
        $fields = json_decode($fields_json, true);
        
        if (empty($fields)) {
            return '';
        }
        
        ob_start();
        ?>
        <?php foreach ($fields as $field): ?>
            <?php switch ($field['type']):
                case 'text': ?>
                    <div class="mb-4 pb-2">
                        <div class="position-relative">
                            <input type="text" 
                                   id="<?php echo esc_attr($field['name']); ?>" 
                                   name="<?php echo esc_attr($field['name']); ?>" 
                                   class="request-form--input" 
                                   placeholder="<?php echo esc_attr($field['placeholder']); ?><?php echo !empty($field['required']) ? ' *' : ''; ?>"
                                   <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <div class="request-form--input-after"></div>
                        </div>
                    </div>
                    <?php break;
                    
                case 'email': ?>
                    <div class="mb-4 pb-2">
                        <div class="position-relative">
                            <input type="email" 
                                   id="<?php echo esc_attr($field['name']); ?>" 
                                   name="<?php echo esc_attr($field['name']); ?>" 
                                   class="request-form--input email" 
                                   placeholder="<?php echo esc_attr($field['placeholder']); ?><?php echo !empty($field['required']) ? ' *' : ''; ?>"
                                   <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <div class="request-form--input-after"></div>
                        </div>
                    </div>
                    <?php break;
                   
                case 'date': ?>
                    <div class="mb-4 pb-2">
                        <div class="position-relative">
                            <input type="text" 
                                   id="<?php echo esc_attr($field['name']); ?>" 
                                   name="<?php echo esc_attr($field['name']); ?>" 
                                   class="request-form--input date" 
                                   placeholder="<?php echo esc_attr($field['label']); ?><?php echo !empty($field['required']) ? ' *' : ''; ?>"
                                   <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <div class="request-form--input-after"></div>
                        </div>
                    </div>
                    <?php break;
                
            endswitch; ?>
        <?php endforeach; ?>
        
        <?php
        return ob_get_clean();
    }
}

// Initialize the class
$pip_form_fields = new Pip_Form_Fields();


