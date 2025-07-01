<?php
/**
 * Class to handle payment methods for prop firms
 */
class Pip_Payment_Methods {
    
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('wp_ajax_submit_payment_method', array($this, 'submit_payment_method'));
        add_action('wp_ajax_nopriv_submit_payment_method', array($this, 'submit_payment_method'));
        add_action('wp_ajax_get_payment_form', array($this, 'get_payment_form_ajax'));
        add_action('wp_ajax_nopriv_get_payment_form', array($this, 'get_payment_form_ajax'));
        add_shortcode('pip_payment_form', array($this, 'payment_form_shortcode'));
    }
    
    /**
     * Shortcode to display payment form
     */
    public function payment_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'firm_id' => 0,
        ), $atts, 'pip_payment_form');
        
        $firm_id = intval($atts['firm_id']);
        
        if (!$firm_id) {
            return '<p>Please specify a valid prop firm ID.</p>';
        }
        
        // Get form fields for this firm
        global $wpdb;
        $form_fields_table = $wpdb->prefix . 'pipfirm_forms';
        $fields_json = $wpdb->get_var($wpdb->prepare(
            "SELECT form_fields FROM $form_fields_table WHERE firm_id = %d",
            $firm_id
        ));
        
        if (!$fields_json) {
            return '<p>No payment form configured for this prop firm.</p>';
        }
        
        $fields = json_decode($fields_json, true);
        
        if (empty($fields)) {
            return '<p>No payment form fields configured for this prop firm.</p>';
        }
        
        // Get firm details
        $firms_table = $wpdb->prefix . 'pipfunds';
        $firm = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $firms_table WHERE id = %d",
            $firm_id
        ));
        
        if (!$firm) {
            return '<p>Prop firm not found.</p>';
        }
        
        // Get user's saved payment methods
        $user_id = get_current_user_id();
        $payment_methods = get_user_meta($user_id, 'payment_methods_' . $firm_id, true);
        
        if (!is_array($payment_methods)) {
            $payment_methods = array();
        }
        
        ob_start();
        ?>
        <div class="pip-payment-form-container" data-firm-id="<?php echo esc_attr($firm_id); ?>">
            <h2 class="payment-form-title"><?php echo esc_html($firm->title); ?> Payment Method</h2>
            
            <div class="payment-methods-wrapper">
                <?php if (!empty($payment_methods)): ?>
                    <div class="saved-payment-methods">
                        <?php foreach ($payment_methods as $method_id => $method_data): ?>
                            <div class="payment-method-card" data-method-id="<?php echo esc_attr($method_id); ?>">
                                <div class="payment-method-header">
                                    <h3><?php echo esc_html($method_data['_method_type'] ?? 'Payment Method'); ?></h3>
                                    <div class="payment-method-actions">
                                        <button class="edit-method-btn" data-method-id="<?php echo esc_attr($method_id); ?>">Edit</button>
                                        <button class="delete-method-btn" data-method-id="<?php echo esc_attr($method_id); ?>">Delete</button>
                                    </div>
                                </div>
                                <div class="payment-method-details">
                                    <?php foreach ($method_data as $key => $value): ?>
                                        <?php if ($key !== '_method_type' && $key !== '_method_id'): ?>
                                            <div class="payment-detail">
                                                <span class="detail-label"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</span>
                                                <span class="detail-value"><?php echo esc_html($value); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <button id="add-payment-method" class="add-payment-method-btn">Add Payment Method</button>
            </div>
            
            <div id="payment-form-modal" class="payment-form-modal" style="display: none;">
                <div class="payment-form-modal-content">
                    <span class="close-modal">&times;</span>
                    <h3 id="payment-form-title">Add Payment Method</h3>
                    
                    <form id="payment-method-form" class="payment-method-form">
                        <input type="hidden" id="method_id" name="method_id" value="">
                        <input type="hidden" id="firm_id" name="firm_id" value="<?php echo esc_attr($firm_id); ?>">
                        <?php wp_nonce_field('submit_payment_method', 'payment_nonce'); ?>
                        
                        <div id="dynamic-form-fields">
                            <!-- Form fields will be loaded dynamically -->
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="save-method-btn">Save Payment Method</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}