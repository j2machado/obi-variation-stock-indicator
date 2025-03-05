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
        
        // Add AJAX URL to script
        wp_localize_script('jquery', 'wc_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php')
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
                        $option.prop('disabled', !stockStatus.in_stock);
                    } else {
                        $option.prop('disabled', true);
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