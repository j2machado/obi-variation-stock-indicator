<?php
/**
 * Plugin Name: Obi Variation Stock Indicator
 * Description: A WordPress plugin that displays the stock indicator for each variation of a product.
 * Version: 1.0
 * Author: Obi Juan
 * Author URI: https://www.obijuan.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: obi-variation-stock-indicator
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('OVSI_VERSION', '1.0.0');
define('OVSI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OVSI_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class using singleton pattern
 */
class OBI_Variation_Stock_Indicator {
    /**
     * Single instance of the class
     *
     * @var OBI_Variation_Stock_Indicator|null
     */
    private static $instance = null;


    /**
     * Get single instance of the class
     *
     * @return OBI_Variation_Stock_Indicator
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        
        // Add AJAX handlers
        add_action('wp_ajax_get_product_variations', array($this, 'get_product_variations'));
        add_action('wp_ajax_nopriv_get_product_variations', array($this, 'get_product_variations'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Variation Stock Indicator',
            'Stock Indicator',
            'manage_options',
            'variation-stock-indicator',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Initialize plugin settings
     */
    public function init_settings() {
        register_setting(
            'ovsi_options', 
            'ovsi_settings',
            array($this, 'sanitize_settings')
        );
        
        add_settings_section(
            'ovsi_general_section',
            'General Settings',
            array($this, 'render_section_info'),
            'ovsi_settings'
        );
        
        add_settings_field(
            'disable_out_of_stock',
            'Out of Stock Variations',
            array($this, 'render_disable_setting'),
            'ovsi_settings',
            'ovsi_general_section'
        );
    }
    
    /**
     * Render the settings section info
     */
    public function render_section_info() {
        echo '<p>Configure how the variation stock indicator behaves.</p>';
    }
    
    /**
     * Render the disable setting field
     */
    public function render_disable_setting() {
        $options = get_option('ovsi_settings', array());
        $checked = isset($options['disable_out_of_stock']) ? $options['disable_out_of_stock'] : 'yes';
        ?>
        <label>
            <input type='hidden' name='ovsi_settings[disable_out_of_stock]' value='no' />
            <input type='checkbox' name='ovsi_settings[disable_out_of_stock]' 
                   value='yes' <?php checked($checked, 'yes'); ?> />
            Disable selection of out-of-stock variations
        </label>
        <p class="description">
            When checked, out-of-stock variations will be disabled and cannot be selected.
            When unchecked, out-of-stock variations can still be selected but will show the stock status.
        </p>
        <?php
    }

    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        if (isset($input['disable_out_of_stock'])) {
            $sanitized['disable_out_of_stock'] = ($input['disable_out_of_stock'] === 'yes') ? 'yes' : 'no';
        }
        return $sanitized;
    }
    
    /**
     * Render the admin settings page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
            <?php
                settings_fields('ovsi_options');
                do_settings_sections('ovsi_settings');
                submit_button('Save Settings');
            ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for getting product variations
     */
    public function get_product_variations() {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('No product ID provided');
            return;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error('Invalid product');
            return;
        }
        
        $available_variations = $product->get_available_variations();
        wp_send_json_success($available_variations);
    }

    /**
     * Initialize plugin hooks
     */
    private function init_hooks() {
        // Track current attribute
        add_filter('woocommerce_dropdown_variation_attribute_options_args', 
            [$this, 'track_current_attribute'], 10, 1);

        // Add class to last dropdown
        add_filter('woocommerce_dropdown_variation_attribute_options_args', 
            [$this, 'add_last_attribute_class'], 20, 1);

        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Add JavaScript to footer
        add_action('wp_footer', [$this, 'add_variation_script']);
    }

    /**
     * Track which attribute is being processed
     */
    public function track_current_attribute($args) {
        global $current_attribute_name;
        $current_attribute_name = $args['attribute'];
        return $args;
    }

    /**
     * Add class to the last attribute dropdown
     */
    public function add_last_attribute_class($args) {
        global $product;
        if (!$product) return $args;
        
        $attributes = $product->get_variation_attributes();
        $last_attribute = array_key_last($attributes);
        
        if ($args['attribute'] === $last_attribute) {
            $args['class'] = isset($args['class']) ? 
                $args['class'] . ' last-attribute' : 'last-attribute';
        }
        
        return $args;
    }

    /**
     * Enqueue required scripts
     */
    public function enqueue_scripts() {
        if (!is_product()) return;

        wp_enqueue_script('jquery');
        
        // Get plugin settings
        $options = get_option('ovsi_settings', array());
        $disable_out_of_stock = isset($options['disable_out_of_stock']) ? $options['disable_out_of_stock'] : 'yes';
        
        // Add AJAX URL and settings to script
        wp_localize_script('jquery', 'wc_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'disable_out_of_stock' => $disable_out_of_stock
        ));
    }

    /**
     * Add variation handling JavaScript
     */
    public function add_variation_script() {
        if (!is_product()) return;
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Wait for WooCommerce to initialize
            $(document.body).on('wc_variation_form', 'form.variations_form', function(event) {
                var $form = $(this);
                var productId = $form.data('product_id');
                var $lastDropdown = $form.find('.last-attribute');
                var variationsCache = null;
                
                if (!$lastDropdown.length) {
                    console.log('No last-attribute dropdown found');
                    return;
                }

                // Store original text for each option
                var originalTexts = {};
                $lastDropdown.find('option').each(function() {
                    var $option = $(this);
                    originalTexts[$option.val()] = $option.text().split(' - ')[0];
                });

                function updateOptionText(option, stockStatus) {
                    var $option = $(option);
                    var value = $option.val();
                    if (!value) return; // Skip empty option

                    var originalText = originalTexts[value] || $option.text().split(' - ')[0];
                    var newText = originalText;

                    if (stockStatus) {
                        if (stockStatus.max_qty) {
                            newText += ` - ${stockStatus.max_qty} in stock`;
                        } else if (stockStatus.in_stock) {
                            newText += ' - In Stock';
                        } else {
                            newText += ' - Out of Stock';
                        }
                        // Only disable if the setting is enabled
                        if (wc_ajax_object.disable_out_of_stock === 'yes') {
                            $option.prop('disabled', !stockStatus.in_stock);
                        }
                    } else {
                        // Only disable if the setting is enabled
                        if (wc_ajax_object.disable_out_of_stock === 'yes') {
                            $option.prop('disabled', true);
                        }
                        newText += ' - Out of Stock';
                    }

                    $option.text(newText);
                }

                function loadVariationsAjax() {
                    return $.ajax({
                        url: wc_ajax_object.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'get_product_variations',
                            product_id: productId
                        }
                    });
                }

                function updateVariationStatus() {
                    var selectedAttrs = {};
                    $form.find('.variations select').each(function() {
                        var $select = $(this);
                        var name = $select.data('attribute_name') || $select.attr('name');
                        selectedAttrs[name] = $select.val() || '';
                    });

                    // Reset all options in last dropdown first
                    $lastDropdown.find('option').each(function() {
                        updateOptionText(this, null);
                    });

                    // Get variations - either from cache, direct data, or AJAX
                    var variations = variationsCache || $form.data('product_variations');
                    
                    if (variations === false && !variationsCache) {
                        // Load via AJAX
                        loadVariationsAjax().then(function(response) {
                            console.log('AJAX response', response);
                            if (response && response.success && Array.isArray(response.data)) {
                                variationsCache = response.data;
                                processVariations(response.data, selectedAttrs);
                            }
                        });
                    } else if (variations) {
                        processVariations(variations, selectedAttrs);
                    }
                }

                function processVariations(variations, selectedAttrs) {
                    $lastDropdown.find('option').each(function() {
                        var $option = $(this);
                        var value = $option.val();
                        
                        if (!value) return; // Skip empty option

                        // Test this specific value
                        var testAttrs = {...selectedAttrs};
                        testAttrs[$lastDropdown.attr('name')] = value;

                        // Find matching variation
                        var matchingVariation = variations.find(function(variation) {
                            return Object.entries(testAttrs).every(function([name, value]) {
                                return !value || variation.attributes[name] === '' || variation.attributes[name] === value;
                            });
                        });

                        if (matchingVariation) {
                            updateOptionText(this, {
                                in_stock: matchingVariation.is_in_stock && matchingVariation.is_purchasable,
                                max_qty: matchingVariation.max_qty
                            });
                        }
                    });
                }

                // Handle variation changes
                $form.on('woocommerce_variation_has_changed check_variations', updateVariationStatus);

                // Initial update
                updateVariationStatus();
            });
        });
        </script>
        <?php
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     */
    private function __wakeup() {}
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    OBI_Variation_Stock_Indicator::get_instance();
});