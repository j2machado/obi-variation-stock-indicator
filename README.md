# Obi Variation Stock Indicator for WooCommerce

A WordPress plugin that enhances the display and management of stock status for WooCommerce product variations.

## Description

Obi Variation Stock Indicator integrates seamlessly with WooCommerce to provide a more intuitive and informative stock status display for variable products. It modifies how stock information is presented in variation dropdowns and adds enhanced functionality for both customers and store managers.

## Features

### Stock Status Display
- Shows stock status directly in variation dropdowns
- Customizable stock status messages
- Support for:
  - In stock products
  - Out of stock variations
  - Products on backorder
  - Low stock notifications
  - Exact stock quantity display

### Stock Management
- Configurable low stock threshold
- Option to disable out-of-stock variations
- Customizable stock order in dropdowns (in stock first/out of stock first)

### Compatibility
- Compatibility handler structure built-in for easy integration with other plugins/themes.
- Built-in support for popular WooCommerce extensions:
  - Express Shop Page by Kestrel
  - Quick View by Kestrel
- Theme compatibility:
  - Kadence theme support (disabled by default)

### Customization
- Customizable text strings for all stock status messages
- Support for stock quantity placeholders using {stock} tag
- Adjustable styling through CSS

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.0 or higher

## Installation

1. Upload the plugin files to `/wp-content/plugins/obi-variation-stock-indicator`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to WooCommerce → Stock Indicator to configure settings

## Configuration

### General Settings
- **Out of Stock Variations**: Choose whether to disable out-of-stock variations
- **Stock Order Preference**: Configure how variations are ordered in dropdowns
- **Low Stock Threshold**: Set the quantity at which to display low stock warnings

### Text Customization
Default messages (all customizable):
- In stock: "In stock"
- Out of stock: "Out of stock"
- On backorder: "On backorder"
- X in stock: "{stock} in stock"
- Low stock: "Only {stock} left in stock"

## Current Limitations

- No multilingual support for stock status messages
- No bulk editing of stock status display settings

## TO-DO:

- Multi-language support
- Hackability with actions and filters.
- Settings override per product on the single product settings.
- Bulk editing capabilities
- Additional WooCommerce extension compatibility

## Support

For bug reports and feature requests, please use the GitHub issue tracker.

## License

This project is licensed under the GNU General Public License v2 or later - see the LICENSE file for details.

## Credits

Developed by Obi Juan (https://www.obijuan.dev)