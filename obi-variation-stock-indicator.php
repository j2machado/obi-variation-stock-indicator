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
    }

    /**
     * Add variation handling JavaScript
     */
    public function add_variation_script() {
        if (!is_product()) return;
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var $form = $('form.variations_form');
            
            function updateLastDropdownOptions() {
                console.log('Starting updateLastDropdownOptions');
                
                // Get the form data
                var formData = $form.data();
                console.log('Form data:', formData);
                
                // Get variations directly from WooCommerce's variationForm object
                var variations = $form.data('product_variations');
                console.log('Variation form data:', variations);
                
                if (variations) {
                    console.log('Found variations:', variations);
                    processVariations(variations);
                } else {
                    // If we can't get variations directly, let's hook into WooCommerce's variation events
                    console.log('No variations found in initial state, waiting for WC events');
                }
            }
            
            function processVariations(variations) {
                if (!variations || typeof variations !== 'object') {
                    console.log('Invalid variations data:', variations);
                    return;
                }
                
                console.log('Processing variations:', variations);
                
                var $lastDropdown = $('.last-attribute');
                if (!$lastDropdown.length) {
                    console.log('No last-attribute dropdown found');
                    return;
                }
                
                var $dropdowns = $form.find('.variations select');
                var currentSelections = {};
                
                // Build current selections
                $dropdowns.each(function() {
                    var $this = $(this);
                    var name = $this.data('attribute_name') || $this.attr('name');
                    var value = $this.val();
                    
                    if (value) {
                        // Store the name without the 'attribute_' prefix
                        var cleanName = name.replace('attribute_', '');
                        currentSelections[cleanName] = value;
                    }
                });
                
                console.log('Current selections:', currentSelections);
                
                // Update each option in the last dropdown
                $lastDropdown.find('option').each(function() {
                    if (!$(this).val()) return; // Skip empty option
                    
                    var option = $(this);
                    var originalText = option.text().split(' - ')[0];
                    var optionValue = option.val();
                    var lastAttributeName = $lastDropdown.data('attribute_name') || $lastDropdown.attr('name');
                    
                    // Test this combination
                    var testAttributes = {...currentSelections};
                    testAttributes[lastAttributeName] = optionValue;
                    
                    var isAvailable = false;
                    var stockInfo = '';
                    
                    // Check if this combination exists in variations
                    variations.forEach(function(variation) {
                        console.log('Checking variation:', variation);
                        var matches = true;
                        
                        // Check each attribute for this variation
                        Object.entries(testAttributes).forEach(function([attrName, attrValue]) {
                            // Remove 'attribute_' if it's already in the name
                            var cleanAttrName = attrName.replace('attribute_', '');
                            var wcAttrName = 'attribute_' + cleanAttrName.toLowerCase();
                            console.log('Checking attribute:', wcAttrName, 'Expected:', attrValue, 'Actual:', variation.attributes[wcAttrName]);
                            
                            // Only check if the variation specifies this attribute
                            if (variation.attributes[wcAttrName] !== '' && 
                                variation.attributes[wcAttrName] !== attrValue) {
                                console.log('Attribute mismatch');
                                matches = false;
                            }
                        });
                        
                        if (matches) {
                            console.log('Found matching variation:', variation);
                            isAvailable = variation.is_purchasable && variation.is_in_stock;
                            if (isAvailable) {
                                stockInfo = variation.max_qty ? 
                                          ` - ${variation.max_qty} in stock` : 
                                          ' - In Stock';
                                console.log('Stock info:', stockInfo);
                            }
                        }
                    });
                    
                    console.log('Final status for ' + optionValue + ':', { isAvailable, stockInfo });
                    
                    // Update option text and state
                    option.text(originalText + (isAvailable ? stockInfo : ' - Out of Stock'))
                         .prop('disabled', !isAvailable);
                });
            }
            
            // Listen for WooCommerce's variation events
            $form.on('wc_variation_form', function() {
                console.log('WC variation form initialized');
                updateLastDropdownOptions();
            });
            
            $form.on('woocommerce_update_variation_values', function() {
                console.log('WC variation values updated');
                updateLastDropdownOptions();
            });
            
            $form.on('found_variation', function(event, variation) {
                console.log('Found variation:', variation);
                updateLastDropdownOptions();
            });
            
            $form.on('check_variations', function() {
                console.log('Checking variations');
                updateLastDropdownOptions();
            });
            
            // Standard change events
            $form.on('change', 'select', function() {
                console.log('Select changed');
                updateLastDropdownOptions();
            });
            
            // Initial setup
            console.log('Setting up initial handlers');
            setTimeout(updateLastDropdownOptions, 500);
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