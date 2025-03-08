<?php
namespace OVSI\Admin;

class Settings {
    private $default_strings;

    public function __construct() {
        //add_action('admin_init', [$this, 'init_settings']);
        $this->default_strings = $this->get_default_strings();
    }

    public function get_default_strings() {
        return array(
            'in_stock' => 'In stock',
            'out_of_stock' => 'Out of stock',
            'on_backorder' => 'On backorder',
            'x_in_stock' => '{stock} in stock',
            'low_stock' => 'Only {stock} left in stock'
        );
    }

    public function init_settings() {
        register_setting(
            'ovsi_options', 
            'ovsi_settings',
            array($this, 'sanitize_settings')
        );
        
        // Add settings sections and fields
        $this->add_general_section();
        $this->add_text_section();
    }

    private function add_general_section() {
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
    }

    private function add_text_section() {
        // Text Customization Section
        add_settings_section(
            'ovsi_text_section',
            'Text Customization',
            array($this, 'render_text_section_info'),
            'ovsi_settings'
        );

        /**
     * Backend: Default text strings for stock status
     */
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

    /**
     * Render the text section info
     */
    public function render_text_section_info() {
        echo '<p>Customize the text strings used to display stock status. Leave empty to use default values.</p>';
    }

    /**
     * Render the text field
     */
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
}
