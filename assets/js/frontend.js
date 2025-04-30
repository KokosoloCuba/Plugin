jQuery(document).ready(function($) {
    // Carga de municipios dinámica
    $('body').on('change', '#billing_state, #shipping_state', function() {
        var $citySelect = $(this).closest('.address-field').next().find('select');
        $citySelect.html('<option value="">' + wc_cuba_shipping_vars.i18n.select_municipality + '</option>');

        if(!$(this).val()) {
            return;
        }

        $.ajax({
            url: wc_cuba_shipping_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_cuba_municipalities',
                province: $(this).val(),
                nonce: wc_cuba_shipping_vars.nonce
            },
            success: function(response) {
                if(response.success) {
                    var options = '<option value="">' + wc_cuba_shipping_vars.i18n.select_municipality + '</option>';
                    $.each(response.data.municipalities, function(i, item) {
                        options += '<option value="' + item + '">' + item + '</option>';
                    });
                    $citySelect.html(options);
                    
                    // Actualizar checkout
                    $(document.body).trigger('update_checkout');
                }
            },
            error: function() {
                $citySelect.html('<option value="">' + wc_cuba_shipping_vars.i18n.no_shipping_options + '</option>');
            }
        });
    });

    // Actualizar al cambiar municipio
    $('body').on('change', '#billing_city, #shipping_city', function() {
        $(document.body).trigger('update_checkout');
    });

    // Actualizar si ya hay valores
    if($('#billing_state').val() || $('#shipping_state').val()) {
        $(document.body).trigger('update_checkout');
    }
});