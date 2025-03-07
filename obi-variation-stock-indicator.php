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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
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
    /**
     * Get default text strings
     */
    public function get_default_strings() {
        return array(
            'in_stock' => 'In stock',
            'out_of_stock' => 'Out of stock',
            'on_backorder' => 'On backorder',
            'x_in_stock' => '{stock} in stock',
            'low_stock' => 'Only {stock} left in stock'
        );
    }

    /**
     * Get customized or default text string
     *
     * @param string $key The text string key
     * @param string|int $quantity Optional quantity for strings that use it
     * @return string The customized or default text string
     */
    public function get_text_string($key, $quantity = null) {
        $options = get_option('ovsi_settings', array());
        $default_strings = $this->get_default_strings();
        
        // Get the custom text if set, otherwise use default
        $text_key = 'text_' . $key;
        $text = isset($options[$text_key]) && !empty($options[$text_key]) 
            ? $options[$text_key] 
            : $default_strings[$key];
        
        // Replace merge tag with quantity if provided
        if ($quantity !== null) {
            $text = str_replace('{stock}', $quantity, $text);
        }
        
        return $text;
    }

    public function init_settings() {
        register_setting(
            'ovsi_options', 
            'ovsi_settings',
            array($this, 'sanitize_settings')
        );
        
        // General Settings Section
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

        add_settings_field(
            'stock_order',
            'Stock Order Preference',
            array($this, 'render_stock_order_setting'),
            'ovsi_settings',
            'ovsi_general_section'
        );

        add_settings_field(
            'low_stock_threshold',
            'Low Stock Threshold',
            array($this, 'render_low_stock_threshold_setting'),
            'ovsi_settings',
            'ovsi_general_section'
        );

        // Text Customization Section
        add_settings_section(
            'ovsi_text_section',
            'Text Customization',
            array($this, 'render_text_section_info'),
            'ovsi_settings'
        );

        $default_strings = $this->get_default_strings();
        foreach ($default_strings as $key => $default_value) {
            add_settings_field(
                'text_' . $key,
                ucwords(str_replace('_', ' ', $key)),
                array($this, 'render_text_field'),
                'ovsi_settings',
                'ovsi_text_section',
                array('key' => $key, 'default' => $default_value)
            );
        }
    }
    
    /**
     * Render the settings section info
     */
    public function render_section_info() {
        echo '<p>Configure how the variation stock indicator behaves.</p>';
    }

    public function render_text_section_info() {
        echo '<p>Customize the text strings used to display stock status. Leave empty to use default values.</p>';
    }

    public function render_text_field($args) {
        $options = get_option('ovsi_settings', array());
        $key = $args['key'];
        $default = $args['default'];
        $value = isset($options['text_' . $key]) ? $options['text_' . $key] : '';
        $needs_merge_tag = $key === 'x_in_stock' || $key === 'low_stock';
        ?>
        <div class="text-field-wrapper">
            <input type='text' 
                   name='ovsi_settings[text_<?php echo esc_attr($key); ?>]' 
                   value='<?php echo esc_attr($value); ?>' 
                   class='regular-text <?php echo $needs_merge_tag ? 'requires-merge-tag' : ''; ?>'
                   placeholder='<?php echo esc_attr($default); ?>'
                   data-requires-merge-tag="<?php echo $needs_merge_tag ? 'true' : 'false'; ?>"
            />
            <?php if ($needs_merge_tag): ?>
                <p class="description">Use {stock} as a placeholder for the stock quantity.</p>
                <div class="merge-tag-validation" style="display: none; color: #d63638; margin-top: 5px;"></div>
            <?php endif;
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
     * Render the stock order preference setting
     */
    public function render_stock_order_setting() {
        $options = get_option('ovsi_settings', array());
        $current = isset($options['stock_order']) ? $options['stock_order'] : 'disabled';
        ?>
        <select name='ovsi_settings[stock_order]'>
            <option value='disabled' <?php selected($current, 'disabled'); ?>>
                Default Order (No Reordering)
            </option>
            <option value='in_stock_first' <?php selected($current, 'in_stock_first'); ?>>
                In Stock First
            </option>
            <option value='out_of_stock_first' <?php selected($current, 'out_of_stock_first'); ?>>
                Out of Stock First
            </option>
        </select>
        <p class="description">
            Choose how to order the variation options in the dropdown.
            This affects the last attribute dropdown only.
        </p>
        <?php
    }

    /**
     * Render the low stock threshold setting
     */
    public function render_low_stock_threshold_setting() {
        $options = get_option('ovsi_settings', array());
        $threshold = isset($options['low_stock_threshold']) ? absint($options['low_stock_threshold']) : 10;
        ?>
        <input type='number' 
               name='ovsi_settings[low_stock_threshold]' 
               value='<?php echo esc_attr($threshold); ?>'
               min='0'
               step='1'
               class='small-text'
        />
        <p class="description">
            When the stock quantity is at or below this number, the "Low Stock" message will be displayed instead of the regular stock message.
            Set to 0 to disable the low stock message.
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
        if (isset($input['stock_order'])) {
            $sanitized['stock_order'] = in_array($input['stock_order'], array('in_stock_first', 'out_of_stock_first', 'disabled')) 
                ? $input['stock_order'] 
                : 'disabled';
        }
        if (isset($input['low_stock_threshold'])) {
            $sanitized['low_stock_threshold'] = absint($input['low_stock_threshold']);
        }

        // Sanitize text strings
        $default_strings = $this->get_default_strings();
        foreach ($default_strings as $key => $default_value) {
            $text_key = 'text_' . $key;
            if (isset($input[$text_key])) {
                $sanitized[$text_key] = sanitize_text_field($input[$text_key]);
            }
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
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ($hook !== 'woocommerce_page_variation-stock-indicator') {
            return;
        }
        
        // Add inline script for merge tag validation
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                function validateMergeTag(input) {
                    var $input = $(input);
                    var $wrapper = $input.closest(".text-field-wrapper");
                    var $validation = $wrapper.find(".merge-tag-validation");
                    var value = $input.val();
                    
                    if (value && !value.includes("{stock}")) {
                        $validation.html("This field must contain the {stock} merge tag").show();
                        $input.css("border-color", "#d63638");
                        return false;
                    } else {
                        $validation.hide();
                        $input.css("border-color", "");
                        return true;
                    }
                }
                
                // Validate on input change
                $(".requires-merge-tag").on("input", function() {
                    validateMergeTag(this);
                });
                
                // Initial validation
                $(".requires-merge-tag").each(function() {
                    validateMergeTag(this);
                });
                
                // Validate before form submission
                $("form").on("submit", function(e) {
                    var isValid = true;
                    $(".requires-merge-tag").each(function() {
                        if (!validateMergeTag(this)) {
                            isValid = false;
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert("Please fix the merge tag errors before saving.");
                    }
                });
            });
        ');
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
        $stock_order = isset($options['stock_order']) ? $options['stock_order'] : 'disabled';
        
        // Get text strings
        $strings = array();
        foreach ($this->get_default_strings() as $key => $default) {
            $strings[$key] = $this->get_text_string($key);
        }
        
        // Add AJAX URL, settings and strings to script
        // Get low stock threshold
        $low_stock_threshold = isset($options['low_stock_threshold']) ? absint($options['low_stock_threshold']) : 10;

        wp_localize_script('jquery', 'wc_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'disable_out_of_stock' => $disable_out_of_stock,
            'stock_order' => $stock_order,
            'strings' => $strings,
            'low_stock_threshold' => $low_stock_threshold
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
                    var isInStock = false;

                    if (stockStatus) {
                        if (stockStatus.on_backorder) {
                            newText += ' - ' + wc_ajax_object.strings.on_backorder;
                            // Don't disable backorderable items
                            $option.prop('disabled', false);
                            isInStock = true;
                        } else if (stockStatus.max_qty) {
                            // Check if this is low stock
                            if (stockStatus.max_qty <= wc_ajax_object.low_stock_threshold) {
                                newText += ' - ' + wc_ajax_object.strings.low_stock.replace('{stock}', stockStatus.max_qty);
                            } else {
                                newText += ' - ' + wc_ajax_object.strings.x_in_stock.replace('{stock}', stockStatus.max_qty);
                            }
                            isInStock = true;
                            // Only disable if setting is enabled and not in stock
                            if (wc_ajax_object.disable_out_of_stock === 'yes') {
                                $option.prop('disabled', !stockStatus.in_stock);
                            }
                        } else if (stockStatus.in_stock) {
                            newText += ' - ' + wc_ajax_object.strings.in_stock;
                            isInStock = true;
                            // Only disable if setting is enabled and not in stock
                            if (wc_ajax_object.disable_out_of_stock === 'yes') {
                                $option.prop('disabled', !stockStatus.in_stock);
                            }
                        } else if (stockStatus.backorders_allowed) {
                            newText += ' - ' + wc_ajax_object.strings.on_backorder;
                            // Don't disable backorderable items
                            $option.prop('disabled', false);
                            isInStock = true;
                        } else {
                            newText += ' - ' + wc_ajax_object.strings.out_of_stock;
                            // Only disable if setting is enabled
                            if (wc_ajax_object.disable_out_of_stock === 'yes') {
                                $option.prop('disabled', true);
                            }
                        }
                    } else {
                        // Only disable if the setting is enabled
                        if (wc_ajax_object.disable_out_of_stock === 'yes') {
                            $option.prop('disabled', true);
                        }
                        newText += ' - Out of Stock';
                    }

                    console.log('Setting stock status for', originalText, ':', isInStock);
                    $option.text(newText);
                    $option.data('in-stock', isInStock);

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

                function reorderOptions() {
                    if (wc_ajax_object.stock_order === 'disabled') return;
                    
                    // Store current state
                    var $select = $lastDropdown;
                    var currentValue = $select.val();
                    var $options = $select.find('option').not(':first');
                    var $firstOption = $select.find('option:first');
                    
                    // Sort options
                    var sortedOptions = $options.toArray().sort(function(a, b) {
                        var aInStock = $(a).data('in-stock');
                        var bInStock = $(b).data('in-stock');
                        
                        if (wc_ajax_object.stock_order === 'in_stock_first') {
                            return (aInStock === bInStock) ? 0 : aInStock ? -1 : 1;
                        } else {
                            return (aInStock === bInStock) ? 0 : aInStock ? 1 : -1;
                        }
                    });

                    // Reorder options without detaching the select
                    $select.find('option').remove();
                    $select.append($firstOption);
                    $(sortedOptions).each(function() {
                        $select.append(this);
                    });
                    
                    // Restore selected value if it existed
                    if (currentValue) {
                        $select.val(currentValue);
                    }

                    // Only auto-select if there's exactly one enabled option and disable_out_of_stock is enabled
                    if (wc_ajax_object.disable_out_of_stock === 'yes') {
                        var $enabledOptions = $select.find('option:not(:disabled)').not(':first');
                        if ($enabledOptions.length === 1 && !currentValue) {
                            var newValue = $enabledOptions.val();
                            $select.val(newValue).trigger('change');
                        }
                    }
                }

                function updateVariationStatus() {
                    var selectedAttrs = {};
                    var lastAttrName = $lastDropdown.attr('name');
                    var allPreviousSelected = true;

                    // Check if all previous attributes are selected
                    $form.find('.variations select').each(function() {
                        var $select = $(this);
                        var name = $select.attr('name');
                        var value = $select.val() || '';
                        
                        if (name === lastAttrName) {
                            return false; // break the loop
                        }
                        
                        if (!value) {
                            allPreviousSelected = false;
                            return false;
                        }
                        
                        selectedAttrs[name] = value;
                    });

                    if (!allPreviousSelected) {
                        // Reset options to original text if not all previous attributes are selected
                        $lastDropdown.find('option').each(function() {
                            var $option = $(this);
                            if (!$option.val()) return;
                            $option.text(originalTexts[$option.val()])
                                   .prop('disabled', false)
                                   .data('in-stock', true);
                        });
                        return;
                    }

                    // Get variations
                    var variations = variationsCache || $form.data('product_variations');
                    
                    if (variations === false && !variationsCache) {
                        loadVariationsAjax().then(function(response) {
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
                    // Update option texts and disabled states
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
                            var isOnBackorder = matchingVariation.backorders_allowed || 
                                               (matchingVariation.availability_html && 
                                                matchingVariation.availability_html.includes('available-on-backorder'));
                            
                            var stockStatus = {
                                in_stock: matchingVariation.is_in_stock && matchingVariation.is_purchasable,
                                max_qty: matchingVariation.max_qty,
                                on_backorder: isOnBackorder,
                                backorders_allowed: matchingVariation.backorders_allowed
                            };

                            updateOptionText(this, stockStatus);
                        }
                    });

                    // Reorder options after updating their status
                    reorderOptions();
                }

                function initializeVariationForm() {
                    // Initial update
                    updateVariationStatus();
                    
                    // Add a class to the form to prevent WooCommerce's reset link repositioning
                    $form.addClass('ovsi-variations-form');
                    
                    // Add CSS to maintain reset link position
                    var style = document.createElement('style');
                    style.textContent = `
                        .ovsi-variations-form .reset_variations {
                            position: relative !important;
                            clear: both !important;
                            margin: 0 !important;
                            visibility: hidden;
                        }
                        .ovsi-variations-form .reset_variations.visible {
                            visibility: visible;
                        }
                    `;
                    document.head.appendChild(style);
                    
                    // Handle variation changes
                    $form.on('woocommerce_variation_has_changed check_variations', function(event) {
                        if (!event.originalEvent) {
                            updateVariationStatus();
                        }
                    });
                    
                    // Handle direct changes to the last dropdown
                    $lastDropdown.on('change', function(event) {
                        if (event.originalEvent) {
                            setTimeout(function() {
                                $form.trigger('check_variations');
                            }, 100);
                        }
                    });

                    // Override WooCommerce's reset link visibility handling
                    var $resetLink = $form.find('.reset_variations');
                    var originalShowHideLogic = function() {
                        var $selects = $form.find('.variations select');
                        var hasValue = false;
                        
                        $selects.each(function() {
                            if ($(this).val()) {
                                hasValue = true;
                                return false; // break loop
                            }
                        });
                        
                        $resetLink.toggleClass('visible', hasValue);
                    };

                    // Apply our custom visibility logic
                    $form.on('woocommerce_variation_has_changed check_variations', originalShowHideLogic);
                    $lastDropdown.on('change', originalShowHideLogic);
                    
                    // Initial visibility check
                    originalShowHideLogic();
                }

                // Initialize the form
                initializeVariationForm();
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
