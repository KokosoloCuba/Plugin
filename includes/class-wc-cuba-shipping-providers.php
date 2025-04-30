<?php
defined('ABSPATH') || exit;

if (!class_exists('WC_Cuba_Shipping_Providers')) {

class WC_Cuba_Shipping_Providers {
    private static $instance;
    private $option_name = 'wc_cuba_shipping_providers';
    
    public static function init() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        add_action('woocommerce_product_options_shipping', array($this, 'add_product_provider_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_provider_field'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public static function get_default_providers() {
        return array(
            'correos_de_cuba' => array(
                'name' => __('Correos de Cuba', 'wc-cuba-shipping'),
                'default_cost' => 5.00,
                'enabled' => true
            ),
            'cubapack' => array(
                'name' => __('Cubapack', 'wc-cuba-shipping'),
                'default_cost' => 7.00,
                'enabled' => true
            ),
            'mensajeria_privada' => array(
                'name' => __('Mensajería Privada', 'wc-cuba-shipping'),
                'default_cost' => 10.00,
                'enabled' => true
            )
        );
    }
    
    public function get_providers() {
        $providers = get_option($this->option_name, self::get_default_providers());
        return array_filter($providers, function($provider) {
            return $provider['enabled'];
        });
    }
    
    public function add_product_provider_field() {
        $providers = $this->get_providers();
        $options = array('' => __('Seleccione un proveedor', 'wc-cuba-shipping'));
        
        foreach ($providers as $id => $provider) {
            $options[$id] = $provider['name'];
        }
        
        woocommerce_wp_select(array(
            'id' => '_cuba_shipping_provider',
            'label' => __('Proveedor de envío', 'wc-cuba-shipping'),
            'options' => $options,
            'desc_tip' => true,
            'description' => __('Seleccione el proveedor que manejará el envío de este producto', 'wc-cuba-shipping')
        ));
    }
    
    public function save_product_provider_field($product_id) {
        $provider = isset($_POST['_cuba_shipping_provider']) ? sanitize_text_field($_POST['_cuba_shipping_provider']) : '';
        update_post_meta($product_id, '_cuba_shipping_provider', $provider);
    }
    
    public function get_product_provider($product_id) {
        return get_post_meta($product_id, '_cuba_shipping_provider', true);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Proveedores de Envío Cuba', 'wc-cuba-shipping'),
            __('Proveedores Cuba', 'wc-cuba-shipping'),
            'manage_woocommerce',
            'cuba-shipping-providers',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('wc_cuba_shipping_providers', $this->option_name, array(
            'sanitize_callback' => array($this, 'validate_providers')
        ));
    }
    
    public function validate_providers($input) {
        $providers = array();
        
        if (isset($input['new_provider_name']) && !empty($input['new_provider_name'])) {
            $id = sanitize_title($input['new_provider_name']);
            $providers[$id] = array(
                'name' => sanitize_text_field($input['new_provider_name']),
                'default_cost' => floatval($input['new_provider_cost']),
                'enabled' => true
            );
        }
        
        if (isset($input['providers']) && is_array($input['providers'])) {
            foreach ($input['providers'] as $id => $provider) {
                $providers[$id] = array(
                    'name' => sanitize_text_field($provider['name']),
                    'default_cost' => floatval($provider['default_cost']),
                    'enabled' => isset($provider['enabled'])
                );
            }
        }
        
        return $providers;
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Proveedores de Envío para Cuba', 'wc-cuba-shipping'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_cuba_shipping_providers');
                $providers = get_option($this->option_name, self::get_default_providers());
                ?>
                
                <table class="wc-cuba-providers-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Habilitado', 'wc-cuba-shipping'); ?></th>
                            <th><?php esc_html_e('Nombre', 'wc-cuba-shipping'); ?></th>
                            <th><?php esc_html_e('Costo Predeterminado', 'wc-cuba-shipping'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $id => $provider): ?>
                        <tr>
                            <td>
                                <input type="checkbox" 
                                       name="<?php echo esc_attr($this->option_name); ?>[providers][<?php echo esc_attr($id); ?>][enabled]" 
                                       <?php checked($provider['enabled'], true); ?>>
                            </td>
                            <td>
                                <input type="text" 
                                       name="<?php echo esc_attr($this->option_name); ?>[providers][<?php echo esc_attr($id); ?>][name]" 
                                       value="<?php echo esc_attr($provider['name']); ?>" 
                                       required>
                            </td>
                            <td>
                                <input type="number" 
                                       name="<?php echo esc_attr($this->option_name); ?>[providers][<?php echo esc_attr($id); ?>][default_cost]" 
                                       value="<?php echo esc_attr($provider['default_cost']); ?>" 
                                       step="0.01" 
                                       min="0" 
                                       required>
                                <?php echo get_woocommerce_currency_symbol(); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <h3><?php esc_html_e('Añadir Nuevo Proveedor', 'wc-cuba-shipping'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="new_provider_name"><?php esc_html_e('Nombre del Proveedor', 'wc-cuba-shipping'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="new_provider_name" 
                                   name="<?php echo esc_attr($this->option_name); ?>[new_provider_name]" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="new_provider_cost"><?php esc_html_e('Costo Predeterminado', 'wc-cuba-shipping'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="new_provider_cost" 
                                   name="<?php echo esc_attr($this->option_name); ?>[new_provider_cost]" 
                                   step="0.01" 
                                   min="0" 
                                   value="0">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

} // Fin del if !class_exists