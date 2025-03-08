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
                    $option.prop('disabled', false);
                    isInStock = true;
                } else if (stockStatus.max_qty) {
                    if (stockStatus.max_qty <= wc_ajax_object.low_stock_threshold) {
                        newText += ' - ' + wc_ajax_object.strings.low_stock.replace('{stock}', stockStatus.max_qty);
                    } else {
                        newText += ' - ' + wc_ajax_object.strings.x_in_stock.replace('{stock}', stockStatus.max_qty);
                    }
                    $option.prop('disabled', false);
                    isInStock = true;
                } else if (stockStatus.in_stock) {
                    newText += ' - ' + wc_ajax_object.strings.in_stock;
                    $option.prop('disabled', false);
                    isInStock = true;
                } else {
                    newText += ' - ' + wc_ajax_object.strings.out_of_stock;
                    $option.prop('disabled', wc_ajax_object.disable_out_of_stock === 'yes');
                }
            }

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
            
            var $select = $lastDropdown;
            var currentValue = $select.val();
            var $options = $select.find('option').not(':first');
            var $firstOption = $select.find('option:first');
            
            var sortedOptions = $options.toArray().sort(function(a, b) {
                var aInStock = $(a).data('in-stock');
                var bInStock = $(b).data('in-stock');
                
                if (wc_ajax_object.stock_order === 'in_stock_first') {
                    return (aInStock === bInStock) ? 0 : aInStock ? -1 : 1;
                } else {
                    return (aInStock === bInStock) ? 0 : aInStock ? 1 : -1;
                }
            });

            $select.find('option').remove();
            $select.append($firstOption);
            $(sortedOptions).each(function() {
                $select.append(this);
            });
            
            if (currentValue) {
                $select.val(currentValue);
            } else {
                $select.val('');
            }
        }

        function updateVariationStatus() {
            var selectedAttrs = {};
            var lastAttrName = $lastDropdown.attr('name');
            var allPreviousSelected = true;

            $form.find('.variations select').each(function() {
                var $select = $(this);
                var name = $select.attr('name');
                var value = $select.val() || '';
                
                if (name === lastAttrName) {
                    return false;
                }
                
                if (!value) {
                    allPreviousSelected = false;
                    return false;
                }
                
                selectedAttrs[name] = value;
            });

            if (!allPreviousSelected) {
                $lastDropdown.find('option').each(function() {
                    var $option = $(this);
                    if (!$option.val()) return;
                    $option.text(originalTexts[$option.val()])
                           .prop('disabled', false)
                           .data('in-stock', true);
                });
                return;
            }

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
                $lastDropdown.find('option').not(':first').each(function() {
                    var $option = $(this);
                    var value = $option.val();
                    if (!value) return;

                    var testAttrs = Object.assign({}, selectedAttrs);
                    testAttrs[$lastDropdown.attr('name')] = value;

                    var matchingVariation = variations.find(function(variation) {
                        return Object.entries(testAttrs).every(function([name, value]) {
                            return variation.attributes[name] === '' || 
                                   variation.attributes[name] === value;
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

                var allAvailable = true;
                $lastDropdown.find('option').not(':first').each(function() {
                    if (!$(this).data('in-stock')) {
                        allAvailable = false;
                        return false;
                    }
                });

                var currentValue = $lastDropdown.val();

                if (allAvailable) {
                    var $select = $lastDropdown;
                    var optionsInOriginalOrder = [];
                    
                    Object.keys(originalTexts).forEach(function(value) {
                        var $option = $select.find('option[value="' + value + '"]');
                        if ($option.length) {
                            optionsInOriginalOrder.push($option[0]);
                        }
                    });

                    var $firstOption = $select.find('option:first');
                    $select.find('option').remove();
                    $select.append($firstOption);
                    $(optionsInOriginalOrder).each(function() {
                        $select.append(this);
                    });

                    if (currentValue) {
                        $select.val(currentValue);
                    }
                } else {
                    reorderOptions();
                }

                if (!currentValue) {
                    $lastDropdown.val('');
                }
            } finally {
                processVariations.isProcessing = false;
            }
        }

        function initializeVariationForm() {
            updateVariationStatus();
            
            $form.addClass('ovsi-variations-form');
            
            var style = document.createElement('style');
            style.textContent = '\
                .ovsi-variations-form .reset_variations {\
                    position: relative !important;\
                    clear: both !important;\
                    margin: 0 !important;\
                    visibility: hidden;\
                }\
                .ovsi-variations-form .reset_variations.visible {\
                    visibility: visible;\
                }\
            ';
            document.head.appendChild(style);
            
            $form.on('woocommerce_variation_has_changed check_variations', function(event) {
                if (!event.originalEvent) {
                    updateVariationStatus();
                }
            });
            
            $lastDropdown.on('change', function(event) {
                if (event.originalEvent) {
                    setTimeout(function() {
                        $form.trigger('check_variations');
                    }, 100);
                }
            });

            var $resetLink = $form.find('.reset_variations');
            var originalShowHideLogic = function() {
                var hasValue = false;
                $form.find('.variations select').each(function() {
                    if ($(this).val()) {
                        hasValue = true;
                        return false;
                    }
                });
                $resetLink.toggleClass('visible', hasValue);
            };

            $form.on('woocommerce_variation_has_changed check_variations', originalShowHideLogic);
            $lastDropdown.on('change', originalShowHideLogic);
            originalShowHideLogic();
        }

        initializeVariationForm();
    });
});
</script>
