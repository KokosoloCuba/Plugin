jQuery(document).ready(function($) {
    // Funciones para mostrar mensajes
    function showMessage(type, message) {
        $('#wc-cuba-shipping-messages').html(
            '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>'
        );
    }

    // Carga inicial de tarifas
    function loadRates() {
        $('.wc-cuba-shipping-loading').show();
        $('#cuba-shipping-rates-body').html('');
        
        $.ajax({
            url: wc_cuba_shipping_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_cuba_shipping_rates',
                nonce: wc_cuba_shipping_vars.nonce
            },
            success: function(response) {
                $('.wc-cuba-shipping-loading').hide();
                
                if (response.success) {
                    $('#cuba-shipping-rates-body').html(response.data.html);
                } else {
                    showMessage('error', response.data || wc_cuba_shipping_vars.i18n.load_error);
                }
            },
            error: function(xhr) {
                $('.wc-cuba-shipping-loading').hide();
                showMessage('error', wc_cuba_shipping_vars.i18n.load_error);
            }
        });
    }

    // Inicialización
    loadRates();

    // Añadir nueva tarifa
    $('#add-new-rate').on('click', function(e) {
        e.preventDefault();
        
        if ($('#cuba-shipping-rates-body .new-rate').length > 0) {
            showMessage('error', wc_cuba_shipping_vars.i18n.complete_current_rate);
            return;
        }
        
        var template = wp.template('cuba-shipping-new-rate');
        $('#cuba-shipping-rates-body').prepend(template({}));
        
        // Manejar cambio de provincia
        $('.province-select').on('change', function() {
            var province = $(this).val();
            var municipalitySelect = $(this).closest('tr').find('.municipality-select');
            
            municipalitySelect.empty().append(
                '<option value="">' + wc_cuba_shipping_vars.i18n.select_municipality + '</option>'
            ).prop('disabled', true);
            
            if (province && wc_cuba_shipping_vars.municipalities[province]) {
                municipalitySelect.prop('disabled', false);
                
                // Ordenar municipios alfabéticamente
                var municipalities = wc_cuba_shipping_vars.municipalities[province].sort();
                
                $.each(municipalities, function(index, municipality) {
                    municipalitySelect.append(
                        '<option value="' + municipality + '">' + municipality + '</option>'
                    );
                });
                
                // Añadir opción "Todos los municipios"
                municipalitySelect.append(
                    '<option value="">' + wc_cuba_shipping_vars.i18n.all_municipalities + '</option>'
                );
            }
        });
    });

    // Guardar tarifa
    $(document).on('click', '.save-rate', function() {
        var row = $(this).closest('tr');
        var province = row.find('.province-select').val();
        var municipality = row.find('.municipality-select').val();
        var provider = row.find('.provider-select').val();
        var cost = row.find('.shipping-cost').val();
        
        if (!province || !cost) {
            showMessage('error', wc_cuba_shipping_vars.i18n.required_fields);
            return;
        }
        
        $.ajax({
            url: wc_cuba_shipping_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'save_cuba_shipping_rate',
                nonce: wc_cuba_shipping_vars.nonce,
                province: province,
                municipality: municipality || '',
                provider: provider || '',
                cost: cost
            },
            beforeSend: function() {
                row.find('.save-rate').prop('disabled', true).text(wc_cuba_shipping_vars.i18n.saving);
            },
            success: function(response) {
                if (response.success) {
                    loadRates();
                    showMessage('success', wc_cuba_shipping_vars.i18n.rate_saved);
                } else {
                    showMessage('error', response.data || wc_cuba_shipping_vars.i18n.save_error);
                    row.find('.save-rate').prop('disabled', false).text(wc_cuba_shipping_vars.i18n.save);
                }
            },
            error: function(xhr) {
                showMessage('error', xhr.responseJSON && xhr.responseJSON.data ? 
                    xhr.responseJSON.data : wc_cuba_shipping_vars.i18n.connection_error);
                row.find('.save-rate').prop('disabled', false).text(wc_cuba_shipping_vars.i18n.save);
            }
        });
    });
    
    // Eliminar tarifa
    $(document).on('click', '.delete-rate', function() {
        if (confirm(wc_cuba_shipping_vars.i18n.confirm_delete)) {
            var rateId = $(this).data('id');
            var $button = $(this);
            
            $.ajax({
                url: wc_cuba_shipping_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_cuba_shipping_rate',
                    nonce: wc_cuba_shipping_vars.nonce,
                    id: rateId
                },
                beforeSend: function() {
                    $button.prop('disabled', true).text(wc_cuba_shipping_vars.i18n.deleting);
                },
                success: function(response) {
                    if (response.success) {
                        loadRates();
                        showMessage('success', wc_cuba_shipping_vars.i18n.rate_deleted);
                    } else {
                        showMessage('error', response.data || wc_cuba_shipping_vars.i18n.delete_error);
                        $button.prop('disabled', false).text(wc_cuba_shipping_vars.i18n.delete);
                    }
                },
                error: function(xhr) {
                    showMessage('error', xhr.responseJSON && xhr.responseJSON.data ? 
                        xhr.responseJSON.data : wc_cuba_shipping_vars.i18n.connection_error);
                    $button.prop('disabled', false).text(wc_cuba_shipping_vars.i18n.delete);
                }
            });
        }
    });
    
    // Cancelar nueva tarifa
    $(document).on('click', '.cancel-rate', function() {
        $(this).closest('tr').remove();
    });
});