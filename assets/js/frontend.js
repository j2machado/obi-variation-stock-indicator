/**
     * Frontend: Adds variation handling JavaScript to footer
     * This is the main frontend JavaScript that handles:
     * - Variation dropdown modifications
     * - Stock status updates
     * - Option reordering
     * - AJAX communication
     */

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
                    } else {
                        // Ensure no option is selected if there wasn't one before
                        $select.val('');
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
                    if (processVariations.isProcessing) return;
                    processVariations.isProcessing = true;

                    try {
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

                        // Check if all options are available (in stock or backorderable)
                        var allAvailable = true;
                        $lastDropdown.find('option').not(':first').each(function() {
                            if (!$(this).data('in-stock')) {
                                allAvailable = false;
                                return false; // break the loop
                            }
                        });

                        // Store current selection before any reordering
                        var currentValue = $lastDropdown.val();

                        if (allAvailable) {
                            // Restore original order
                            var $select = $lastDropdown;
                            var optionsInOriginalOrder = [];
                            
                            // Store original order using the originalTexts object
                            Object.keys(originalTexts).forEach(function(value) {
                                var $option = $select.find('option[value="' + value + '"]');
                                if ($option.length) {
                                    optionsInOriginalOrder.push($option[0]);
                                }
                            });

                            // Reorder to original state
                            var $firstOption = $select.find('option:first');
                            $select.find('option').remove();
                            $select.append($firstOption);
                            $(optionsInOriginalOrder).each(function() {
                                $select.append(this);
                            });
                        } else {
                            // Apply normal reordering
                            reorderOptions();
                        }

                        // Always restore the previous selection state
                        if (currentValue) {
                            $lastDropdown.val(currentValue);
                        } else {
                            $lastDropdown.val('');
                        }
                    } finally {
                        processVariations.isProcessing = false;
                    }
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




