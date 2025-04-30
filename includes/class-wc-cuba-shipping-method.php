<?php
defined('ABSPATH') || exit;

if (class_exists('WC_Shipping_Method') && !class_exists('WC_Cuba_Shipping_Method')) {

class WC_Cuba_Shipping_Method extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'cuba_shipping_method';
        $this->instance_id = absint($instance_id);
        $this->title = __('Envío a Cuba', 'wc-cuba-shipping');
        $this->method_title = __('Envío a Cuba', 'wc-cuba-shipping');
        $this->method_description = __('Configuración de envíos para provincias y municipios de Cuba con múltiples proveedores', 'wc-cuba-shipping');
        
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal'
        );
        
        $this->init();
    }
    
    protected function init() {
        $this->init_form_fields();
        $this->init_settings();
        
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->title = $this->get_option('title', __('Envío a Cuba', 'wc-cuba-shipping'));
        $this->default_cost = $this->get_option('default_cost', 0);
        $this->show_without_address = $this->get_option('show_without_address', 'no');
        
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Título', 'wc-cuba-shipping'),
                'type' => 'text',
                'description' => __('Título que el usuario verá durante el checkout.', 'wc-cuba-shipping'),
                'default' => __('Envío a Cuba', 'wc-cuba-shipping'),
                'desc_tip' => true
            ),
            'default_cost' => array(
                'title' => __('Costo por defecto', 'wc-cuba-shipping'),
                'type' => 'number',
                'description' => __('Costo de envío cuando no hay una tarifa específica configurada.', 'wc-cuba-shipping'),
                'default' => 0,
                'desc_tip' => true,
                'min' => 0,
                'step' => 0.01
            ),
            'show_without_address' => array(
                'title' => __('Mostrar sin dirección', 'wc-cuba-shipping'),
                'type' => 'checkbox',
                'label' => __('Mostrar método de envío incluso cuando no se ha seleccionado provincia/municipio', 'wc-cuba-shipping'),
                'default' => 'no',
                'description' => __('Habilita esta opción para mostrar el método de envío con un mensaje instructivo cuando no se ha seleccionado dirección completa.', 'wc-cuba-shipping')
            )
        );
    }
    
    public function calculate_shipping($package = array()) {
        $has_complete_address = !empty($package['destination']['state']) && !empty($package['destination']['city']);
        
        if (!$has_complete_address && 'no' === $this->show_without_address) {
            return;
        }
        
        if (!$has_complete_address) {
            $this->add_rate(array(
                'id' => $this->get_rate_id() . ':no-address',
                'label' => __('Seleccione provincia y municipio para ver opciones de envío', 'wc-cuba-shipping'),
                'cost' => 0,
                'package' => $package,
                'meta_data' => array('incomplete_address' => true)
            ));
            return;
        }
        
        $providers = WC_Cuba_Shipping_Providers::init()->get_providers();
        $provider_costs = array();
        
        foreach ($package['contents'] as $item) {
            $product_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
            $provider = WC_Cuba_Shipping_Providers::init()->get_product_provider($product_id);
            $quantity = $item['quantity'];
            
            if ($provider && isset($providers[$provider])) {
                $provider_cost = $this->get_shipping_cost(
                    $package['destination']['state'],
                    $package['destination']['city'],
                    $provider
                );
                
                if ($provider_cost === false) {
                    $provider_cost = $providers[$provider]['default_cost'];
                }
                
                if (!isset($provider_costs[$provider])) {
                    $provider_costs[$provider] = array(
                        'cost' => 0,
                        'label' => $providers[$provider]['name'],
                        'items' => 0
                    );
                }
                
                $provider_costs[$provider]['cost'] += $provider_cost * $quantity;
                $provider_costs[$provider]['items'] += $quantity;
            }
        }
        
        foreach ($provider_costs as $provider_id => $data) {
            $this->add_rate(array(
                'id' => $this->get_rate_id() . ':' . $provider_id,
                'label' => sprintf(__('%s: %s (%d items)', 'wc-cuba-shipping'), 
                    $this->title, 
                    $data['label'], 
                    $data['items']),
                'cost' => $data['cost'],
                'package' => $package,
                'meta_data' => array('provider' => $provider_id)
            ));
        }
        
        if (empty($provider_costs) && $this->default_cost > 0) {
            $this->add_rate(array(
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => $this->default_cost,
                'package' => $package
            ));
        }
    }
    
    private function get_shipping_cost($province, $municipality, $provider = '') {
        $rates = get_option('wc_cuba_shipping_rates', array());
        
        foreach ($rates as $rate) {
            if ($rate['province'] === $province && 
                $rate['municipality'] === $municipality &&
                (!empty($provider) && isset($rate['provider']) && $rate['provider'] === $provider)) {
                return floatval($rate['cost']);
            }
        }
        
        foreach ($rates as $rate) {
            if ($rate['province'] === $province && 
                empty($rate['municipality']) &&
                (!empty($provider) && isset($rate['provider']) && $rate['provider'] === $provider)) {
                return floatval($rate['cost']);
            }
        }
        
        foreach ($rates as $rate) {
            if ($rate['province'] === $province && 
                $rate['municipality'] === $municipality &&
                empty($rate['provider'])) {
                return floatval($rate['cost']);
            }
        }
        
        foreach ($rates as $rate) {
            if ($rate['province'] === $province && 
                empty($rate['municipality']) &&
                empty($rate['provider'])) {
                return floatval($rate['cost']);
            }
        }
        
        return false;
    }
}

} // Fin del if class_exists