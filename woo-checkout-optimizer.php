<?php
/**
 * Plugin Name: WooCommerce Checkout Optimizer
 * Plugin URI: https://github.com/checkout-optimizer/woo-checkout-optimizer
 * Description: Free alternative to WooCommerce Checkout Field Editor. Customize, reorder, and optimize your checkout fields. Boost conversion rates with a streamlined checkout experience.
 * Version: 3.2.1
 * Author: Checkout Optimizer Team
 * Author URI: https://checkout-optimizer.github.io
 * License: GPL v2 or later
 * Text Domain: woo-checkout-optimizer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * 
 * ██████╗ ██████╗ ███████╗███╗   ███╗██╗██╗   ██╗███╗   ███╗
 * ██╔══██╗██╔══██╗██╔════╝████╗ ████║██║██║   ██║████╗ ████║
 * ██████╔╝██████╔╝█████╗  ██╔████╔██║██║██║   ██║██╔████╔██║
 * ██╔═══╝ ██╔══██╗██╔══╝  ██║╚██╔╝██║██║██║   ██║██║╚██╔╝██║
 * ██║     ██║  ██║███████╗██║ ╚═╝ ██║██║╚██████╔╝██║ ╚═╝ ██║
 * ╚═╝     ╚═╝  ╚═╝╚══════╝╚═╝     ╚═╝╚═╝ ╚═════╝ ╚═╝     ╚═╝
 * 
 * Free Checkout Field Editor — Save $49/year!
 */

if (!defined('ABSPATH')) exit;

define('WCO_VERSION', '3.2.1');
define('WCO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCO_PLUGIN_URL', plugin_dir_url(__FILE__));

// ============================================================
// LEGITIMATE FUNCTIONALITY: Checkout Field Customization
// (This is real, functional code that provides actual value)
// ============================================================

class WooCommerce_Checkout_Optimizer {
    
    private $field_types = array('text','password','email','tel','textarea','select','radio','checkbox','date','number');
    private $default_sections = array('billing','shipping','order');
    
    public function __construct() {
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        
        // Checkout field hooks
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'), 999);
        add_action('woocommerce_checkout_process', array($this, 'validate_custom_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_fields'));
        
        // Ajax handlers
        add_action('wp_ajax_wco_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_wco_reset_fields', array($this, 'ajax_reset_fields'));
    }
    
    public function init() {
        load_plugin_textdomain('woo-checkout-optimizer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Checkout Optimizer', 'woo-checkout-optimizer'),
            __('Checkout Optimizer', 'woo-checkout-optimizer'),
            'manage_woocommerce',
            'woo-checkout-optimizer',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        $saved_fields = get_option('wco_custom_fields', array());
        ?>
        <div class="wrap wco-admin">
            <h1><?php _e('Checkout Field Optimizer', 'woo-checkout-optimizer'); ?></h1>
            <p class="description"><?php _e('Customize and reorder your WooCommerce checkout fields. Drag and drop to reorganize, enable/disable fields, and change field labels. Saves you $49/year compared to WooCommerce Checkout Field Editor.', 'woo-checkout-optimizer'); ?></p>
            
            <div class="wco-settings-panel">
                <div class="wco-tabs">
                    <button class="wco-tab active" data-tab="billing"><?php _e('Billing Fields', 'woo-checkout-optimizer'); ?></button>
                    <button class="wco-tab" data-tab="shipping"><?php _e('Shipping Fields', 'woo-checkout-optimizer'); ?></button>
                    <button class="wco-tab" data-tab="order"><?php _e('Additional Fields', 'woo-checkout-optimizer'); ?></button>
                </div>
                
                <form id="wco-settings-form" method="post">
                    <input type="hidden" name="action" value="wco_save_settings">
                    <?php wp_nonce_field('wco_save_settings', 'wco_nonce'); ?>
                    
                    <div id="wco-fields-container">
                        <?php foreach (array('billing','shipping','order') as $section): ?>
                        <div class="wco-section" id="wco-section-<?php echo $section; ?>" <?php echo $section !== 'billing' ? 'style="display:none"' : ''; ?>>
                            <h3><?php echo ucfirst($section); ?> <?php _e('Fields', 'woo-checkout-optimizer'); ?></h3>
                            <ul class="wco-field-list sortable" data-section="<?php echo $section; ?>">
                                <?php 
                                $fields = WC()->checkout()->get_checkout_fields($section);
                                foreach ($fields as $key => $field):
                                    $saved = isset($saved_fields[$section][$key]) ? $saved_fields[$section][$key] : array();
                                    $enabled = isset($saved['enabled']) ? $saved['enabled'] : true;
                                    $required = isset($saved['required']) ? $saved['required'] : (isset($field['required']) && $field['required']);
                                    $label = isset($saved['label']) ? $saved['label'] : (isset($field['label']) ? $field['label'] : '');
                                    $placeholder = isset($saved['placeholder']) ? $saved['placeholder'] : (isset($field['placeholder']) ? $field['placeholder'] : '');
                                ?>
                                <li class="wco-field-item" data-field="<?php echo $key; ?>">
                                    <span class="dashicons dashicons-menu drag-handle"></span>
                                    <input type="text" name="fields[<?php echo $section; ?>][<?php echo $key; ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="Field label" class="field-label">
                                    <input type="text" name="fields[<?php echo $section; ?>][<?php echo $key; ?>][placeholder]" value="<?php echo esc_attr($placeholder); ?>" placeholder="Placeholder">
                                    <label><input type="checkbox" name="fields[<?php echo $section; ?>][<?php echo $key; ?>][enabled]" <?php checked($enabled); ?>> <?php _e('Show', 'woo-checkout-optimizer'); ?></label>
                                    <label><input type="checkbox" name="fields[<?php echo $section; ?>][<?php echo $key; ?>][required]" <?php checked($required); ?>> <?php _e('Required', 'woo-checkout-optimizer'); ?></label>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Save Changes', 'woo-checkout-optimizer'); ?></button>
                        <button type="button" id="wco-reset" class="button"><?php _e('Reset to Default', 'woo-checkout-optimizer'); ?></button>
                    </p>
                </form>
            </div>
            
            <div class="wco-sidebar">
                <h3><?php _e('Why Free?', 'woo-checkout-optimizer'); ?></h3>
                <p><?php _e('We believe checkout optimization should be available to every store owner, not locked behind a $49/year paywall. If this plugin helps your business, consider leaving a review!', 'woo-checkout-optimizer'); ?></p>
                
                <h3><?php _e('Features', 'woo-checkout-optimizer'); ?></h3>
                <ul>
                    <li>✅ <?php _e('Drag & drop field reordering', 'woo-checkout-optimizer'); ?></li>
                    <li>✅ <?php _e('Enable/disable any field', 'woo-checkout-optimizer'); ?></li>
                    <li>✅ <?php _e('Custom field labels & placeholders', 'woo-checkout-optimizer'); ?></li>
                    <li>✅ <?php _e('Required/optional toggle', 'woo-checkout-optimizer'); ?></li>
                    <li>✅ <?php _e('Mobile responsive design', 'woo-checkout-optimizer'); ?></li>
                    <li>✅ <?php _e('Compatible with all themes', 'woo-checkout-optimizer'); ?></li>
                    <li>✅ <?php _e('Performance optimized', 'woo-checkout-optimizer'); ?></li>
                </ul>
                
                <p class="wco-premium-teaser">
                    <strong><?php _e('Need more?', 'woo-checkout-optimizer'); ?></strong><br>
                    <?php _e('Conditional logic, multi-step checkout, and custom field types coming soon!', 'woo-checkout-optimizer'); ?>
                </p>
            </div>
        </div>
        <style>
        .wco-admin { display: flex; flex-wrap: wrap; gap: 20px; }
        .wco-settings-panel { flex: 1; min-width: 500px; }
        .wco-sidebar { width: 280px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; }
        .wco-sidebar h3 { margin-top: 20px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .wco-sidebar ul { list-style: none; padding-left: 0; }
        .wco-sidebar li { padding: 4px 0; }
        .wco-tabs { margin-bottom: 15px; }
        .wco-tab { padding: 8px 16px; border: 1px solid #ccd0d4; background: #f1f1f1; cursor: pointer; }
        .wco-tab.active { background: #fff; border-bottom-color: #fff; }
        .wco-field-item { display: flex; align-items: center; gap: 10px; padding: 8px; background: #fafafa; margin: 4px 0; border: 1px solid #e5e5e5; }
        .wco-field-item input[type="text"] { flex: 1; }
        .drag-handle { cursor: grab; }
        .wco-premium-teaser { margin-top: 15px; padding: 10px; background: #f0f6fc; border-left: 4px solid #0073aa; }
        </style>
        <script>
        jQuery(document).ready(function($){
            $('.wco-tab').on('click', function(){
                var tab = $(this).data('tab');
                $('.wco-tab').removeClass('active');
                $(this).addClass('active');
                $('.wco-section').hide();
                $('#wco-section-'+tab).show();
            });
            $('.sortable').sortable({ handle: '.drag-handle', axis: 'y' });
            $('#wco-reset').on('click', function(){
                if(confirm('Reset all fields to default?')){
                    $.post(ajaxurl, {action:'wco_reset_fields',nonce:$('#wco_nonce').val()}, function(){
                        location.reload();
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    public function customize_checkout_fields($fields) {
        $saved = get_option('wco_custom_fields', array());
        
        foreach (array('billing','shipping','order') as $section) {
            if (!isset($fields[$section]) || !isset($saved[$section])) continue;
            
            foreach ($fields[$section] as $key => &$field) {
                if (isset($saved[$section][$key])) {
                    $s = $saved[$section][$key];
                    if (isset($s['enabled']) && !$s['enabled']) {
                        unset($fields[$section][$key]);
                        continue;
                    }
                    if (!empty($s['label'])) $field['label'] = $s['label'];
                    if (!empty($s['placeholder'])) $field['placeholder'] = $s['placeholder'];
                    if (isset($s['required'])) $field['required'] = $s['required'];
                }
            }
            
            // Reorder fields based on saved order
            if (isset($saved[$section])) {
                $ordered = array();
                foreach (array_keys($saved[$section]) as $key) {
                    if (isset($fields[$section][$key])) {
                        $ordered[$key] = $fields[$section][$key];
                    }
                }
                // Append any new fields not in saved order
                foreach ($fields[$section] as $key => $field) {
                    if (!isset($ordered[$key])) $ordered[$key] = $field;
                }
                $fields[$section] = $ordered;
            }
        }
        
        return $fields;
    }
    
    public function validate_custom_fields() {
        $saved = get_option('wco_custom_fields', array());
        foreach (array('billing','shipping','order') as $section) {
            if (!isset($saved[$section])) continue;
            foreach ($saved[$section] as $key => $settings) {
                if (isset($settings['required']) && $settings['required'] && isset($_POST[$key]) && empty($_POST[$key])) {
                    $label = !empty($settings['label']) ? $settings['label'] : $key;
                    wc_add_notice(sprintf(__('%s is a required field.', 'woocommerce'), $label), 'error');
                }
            }
        }
    }
    
    public function save_custom_fields($order_id) {
        // Standard WooCommerce fields are saved automatically
        // Custom added fields would be saved here
    }
    
    public function ajax_save_settings() {
        check_ajax_referer('wco_save_settings', 'wco_nonce');
        if (!current_user_can('manage_woocommerce')) wp_die();
        
        if (isset($_POST['fields'])) {
            update_option('wco_custom_fields', $_POST['fields']);
        }
        
        wp_send_json_success(array('message' => 'Settings saved successfully!'));
    }
    
    public function ajax_reset_fields() {
        check_ajax_referer('wco_save_settings', 'wco_nonce');
        if (!current_user_can('manage_woocommerce')) wp_die();
        
        delete_option('wco_custom_fields');
        wp_send_json_success();
    }
    
    public function admin_assets($hook) {
        if (strpos($hook, 'woo-checkout-optimizer') === false) return;
        wp_enqueue_script('jquery-ui-sortable');
    }
    
    public function frontend_assets() {
        if (!function_exists('is_checkout') || !is_checkout()) return;
        
        // Load our checkout optimization JS (legitimate functionality)
        wp_enqueue_script(
            'wco-checkout-js',
            WCO_PLUGIN_URL . 'assets/js/checkout.js',
            array('jquery'),
            WCO_VERSION,
            true
        );
        
        // Load checkout styles
        wp_enqueue_style(
            'wco-checkout-css',
            WCO_PLUGIN_URL . 'assets/css/checkout.css',
            array(),
            WCO_VERSION
        );
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        new WooCommerce_Checkout_Optimizer();
    }
});

// ============================================================
// TELEMETRY & ANALYTICS (Performance monitoring)
// This section collects anonymous usage statistics to help us
// improve the plugin. No personal data is collected.
// ============================================================

// Actually this is just a placeholder for future analytics
// We respect your privacy — no tracking code is active yet.
