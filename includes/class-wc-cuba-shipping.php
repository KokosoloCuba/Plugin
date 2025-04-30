<?php
defined('ABSPATH') || exit;

if (!class_exists('WC_Cuba_Shipping_Plugin')) {

class WC_Cuba_Shipping_Plugin {
    private $provinces;
    private $municipalities;
    
    public function __construct() {
        $this->load_location_data();
        $this->init_hooks();
    }
    
    private function load_location_data() {
        $base_path = plugin_dir_path(__FILE__) . '../data/';
        
        $provinces_file = $base_path . 'cuba-provinces.php';
        $municipalities_file = $base_path . 'cuba-municipalities.php';
        
        if (file_exists($provinces_file)) {
            $this->provinces = include $provinces_file;
        } else {
            $this->provinces = array();
            error_log('WC Cuba Shipping: Archivo de provincias no encontrado');
        }
        
        if (file_exists($municipalities_file)) {
            $this->municipalities = include $municipalities_file;
        } else {
            $this->municipalities = array();
            error_log('WC Cuba Shipping: Archivo de municipios no encontrado');
        }
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        add_action('wp_ajax_get_cuba_shipping_rates', array($this, 'ajax_get_shipping_rates'));
        add_action('wp_ajax_save_cuba_shipping_rate', array($this, 'ajax_save_shipping_rate'));
        add_action('wp_ajax_delete_cuba_shipping_rate', array($this, 'ajax_delete_shipping_rate'));
        add_action('wp_ajax_get_cuba_municipalities', array($this, 'ajax_get_municipalities'));
        add_action('wp_ajax_nopriv_get_cuba_municipalities', array($this, 'ajax_get_municipalities'));
        add_filter('woocommerce_states', array($this, 'add_cuba_states'));
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_filter('default_checkout_country', array($this, 'set_default_country'));
    }
    
    public function set_default_country() {
        return 'CU';
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('wc-cuba-shipping', false, dirname(plugin_dir_path(__FILE__)) . '/languages/');
    }
    
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=cuba-shipping-settings') . '">' . __('Configuración', 'wc-cuba-shipping') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Zonas de Envío Cuba', 'wc-cuba-shipping'),
            __('Zonas Cuba', 'wc-cuba-shipping'),
            'manage_woocommerce',
            'cuba-shipping-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('wc_cuba_shipping_settings', 'wc_cuba_shipping_settings');
        
        add_settings_section(
            'wc_cuba_shipping_section',
            __('Configuración de Envíos para Cuba', 'wc-cuba-shipping'),
            array($this, 'render_settings_section'),
            'wc_cuba_shipping'
        );
    }
    
    public function render_settings_section() {
        echo '<p>' . esc_html__('Configura los costos de envío para cada provincia y municipio de Cuba.', 'wc-cuba-shipping') . '</p>';
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'wc-cuba-shipping'));
        }

        if (!class_exists('WooCommerce')) {
            echo '<div class="error notice"><p>';
            _e('WooCommerce debe estar activo para usar este plugin.', 'wc-cuba-shipping');
            echo '</p></div>';
            return;
        }

        try {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                
                <div id="cuba-shipping-settings-container">
                    <div id="wc-cuba-shipping-messages"></div>
                    
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('wc_cuba_shipping_settings');
                        do_settings_sections('wc_cuba_shipping');
                        submit_button(__('Guardar Configuración', 'wc-cuba-shipping'));
                        ?>
                    </form>
                    
                    <div id="cuba-shipping-rates-container">
                        <h2><?php esc_html_e('Gestión de Tarifas por Provincia/Municipio', 'wc-cuba-shipping'); ?></h2>
                        
                        <div class="wc-cuba-shipping-loading" style="display: none;">
                            <p><?php esc_html_e('Cargando tarifas...', 'wc-cuba-shipping'); ?></p>
                        </div>
                        
                        <div class="wc-cuba-shipping-error" style="display: none;">
                            <p class="error-message"></p>
                        </div>
                        
                        <table class="wp-list-table widefat fixed striped" id="cuba-shipping-rates-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Provincia', 'wc-cuba-shipping'); ?></th>
                                    <th><?php esc_html_e('Municipio', 'wc-cuba-shipping'); ?></th>
                                    <th><?php esc_html_e('Proveedor', 'wc-cuba-shipping'); ?></th>
                                    <th><?php esc_html_e('Costo', 'wc-cuba-shipping'); ?></th>
                                    <th><?php esc_html_e('Acciones', 'wc-cuba-shipping'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="cuba-shipping-rates-body">
                                <!-- Las tarifas se cargarán aquí via AJAX -->
                            </tbody>
                        </table>
                        
                        <button id="add-new-rate" class="button button-primary">
                            <?php esc_html_e('Añadir Nueva Tarifa', 'wc-cuba-shipping'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <script type="text/html" id="tmpl-cuba-shipping-new-rate">
                <tr class="new-rate">
                    <td>
                        <select class="province-select" required>
                            <option value=""><?php echo esc_html__('Seleccione provincia', 'wc-cuba-shipping'); ?></option>
                            <?php foreach ($this->provinces as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select class="municipality-select" disabled required>
                            <option value=""><?php echo esc_html__('Seleccione municipio', 'wc-cuba-shipping'); ?></option>
                        </select>
                    </td>
                    <td>
                        <select class="provider-select">
                            <option value=""><?php echo esc_html__('Todos', 'wc-cuba-shipping'); ?></option>
                            <?php foreach (WC_Cuba_Shipping_Providers::init()->get_providers() as $id => $provider): ?>
                            <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($provider['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" class="shipping-cost" step="0.01" min="0" required>
                    </td>
                    <td>
                        <button class="button button-primary save-rate"><?php esc_html_e('Guardar', 'wc-cuba-shipping'); ?></button>
                        <button class="button cancel-rate"><?php esc_html_e('Cancelar', 'wc-cuba-shipping'); ?></button>
                    </td>
                </tr>
            </script>
            <?php
        } catch (Exception $e) {
            error_log('Error en WC_Cuba_Shipping_Plugin::render_settings_page: ' . $e->getMessage());
            echo '<div class="error"><p>';
            _e('Ocurrió un error al cargar la página de configuración. Por favor revisa los logs de errores.', 'wc-cuba-shipping');
            echo '</p></div>';
        }
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'woocommerce_page_cuba-shipping-settings') {
            return;
        }
        
        wp_enqueue_style(
            'wc-cuba-shipping-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            array(),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/admin.css')
        );
        
        wp_enqueue_script(
            'wc-cuba-shipping-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            array('jquery', 'wp-util', 'wp-i18n'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin.js'),
            true
        );
        
        wp_localize_script('wc-cuba-shipping-admin', 'wc_cuba_shipping_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_cuba_shipping_nonce'),
            'provinces' => $this->provinces,
            'municipalities' => $this->municipalities,
            'providers' => WC_Cuba_Shipping_Providers::init()->get_providers(),
            'i18n' => array(
                'select_province' => __('Seleccione provincia', 'wc-cuba-shipping'),
                'select_municipality' => __('Seleccione municipio', 'wc-cuba-shipping'),
                'save' => __('Guardar', 'wc-cuba-shipping'),
                'cancel' => __('Cancelar', 'wc-cuba-shipping'),
                'required_fields' => __('Por favor complete todos los campos requeridos.', 'wc-cuba-shipping'),
                'confirm_delete' => __('¿Está seguro que desea eliminar esta tarifa?', 'wc-cuba-shipping'),
                'all_municipalities' => __('Todos', 'wc-cuba-shipping'),
                'all_providers' => __('Todos los proveedores', 'wc-cuba-shipping'),
                'load_error' => __('Error al cargar las tarifas. Por favor recarga la página.', 'wc-cuba-shipping'),
                'complete_current_rate' => __('Por favor complete o cancele la tarifa actual antes de añadir una nueva.', 'wc-cuba-shipping'),
                'saving' => __('Guardando...', 'wc-cuba-shipping'),
                'deleting' => __('Eliminando...', 'wc-cuba-shipping'),
                'rate_saved' => __('Tarifa guardada correctamente', 'wc-cuba-shipping'),
                'rate_deleted' => __('Tarifa eliminada correctamente', 'wc-cuba-shipping'),
                'save_error' => __('Error al guardar la tarifa', 'wc-cuba-shipping'),
                'delete_error' => __('Error al eliminar la tarifa', 'wc-cuba-shipping'),
                'connection_error' => __('Error de conexión', 'wc-cuba-shipping')
            )
        ));
        
        wp_set_script_translations('wc-cuba-shipping-admin', 'wc-cuba-shipping');
    }
    
    public function enqueue_frontend_assets() {
        if (is_checkout()) {
            wp_enqueue_script(
                'wc-cuba-shipping-frontend',
                plugins_url('assets/js/frontend.js', dirname(__FILE__)),
                array('jquery', 'wc-checkout'),
                filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/frontend.js'),
                true
            );

            wp_localize_script('wc-cuba-shipping-frontend', 'wc_cuba_shipping_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_cuba_shipping_nonce'),
                'provinces' => $this->provinces,
                'i18n' => array(
                    'select_province_first' => __('Primero seleccione una provincia', 'wc-cuba-shipping'),
                    'select_municipality' => __('Seleccione un municipio', 'wc-cuba-shipping'),
                    'no_shipping_options' => __('Por favor seleccione provincia y municipio para ver las opciones de envío', 'wc-cuba-shipping')
                )
            ));
        }
    }
    
    public function init_shipping_method() {
        require_once plugin_dir_path(__FILE__) . 'class-wc-cuba-shipping-method.php';
    }
    
    public function add_shipping_method($methods) {
        $methods['cuba_shipping_method'] = 'WC_Cuba_Shipping_Method';
        return $methods;
    }
    
    public function ajax_get_shipping_rates() {
        check_ajax_referer('wc_cuba_shipping_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'wc-cuba-shipping'));
        }
        
        $rates = get_option('wc_cuba_shipping_rates', array());
        $providers = WC_Cuba_Shipping_Providers::init()->get_providers();
        $html = '';
        
        if (!empty($rates)) {
            foreach ($rates as $id => $rate) {
                $provider_name = isset($rate['provider']) && isset($providers[$rate['provider']]) ? 
                    $providers[$rate['provider']]['name'] : __('Todos', 'wc-cuba-shipping');
                
                $html .= '<tr data-id="' . esc_attr($id) . '">';
                $html .= '<td>' . esc_html($rate['province']) . '</td>';
                $html .= '<td>' . (!empty($rate['municipality']) ? esc_html($rate['municipality']) : __('Todos', 'wc-cuba-shipping')) . '</td>';
                $html .= '<td>' . esc_html($provider_name) . '</td>';
                $html .= '<td>' . wc_price($rate['cost']) . '</td>';
                $html .= '<td><button class="button delete-rate" data-id="' . esc_attr($id) . '">' . __('Eliminar', 'wc-cuba-shipping') . '</button></td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="5">' . __('No hay tarifas configuradas aún.', 'wc-cuba-shipping') . '</td></tr>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_save_shipping_rate() {
        check_ajax_referer('wc_cuba_shipping_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'wc-cuba-shipping'));
        }
        
        if (!isset($_POST['province']) || !isset($_POST['cost'])) {
            wp_send_json_error(__('Datos incompletos.', 'wc-cuba-shipping'));
        }
        
        $rates = get_option('wc_cuba_shipping_rates', array());
        $new_rate = array(
            'province' => sanitize_text_field($_POST['province']),
            'municipality' => isset($_POST['municipality']) ? sanitize_text_field($_POST['municipality']) : '',
            'provider' => isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '',
            'cost' => floatval($_POST['cost'])
        );
        
        $rates[] = $new_rate;
        update_option('wc_cuba_shipping_rates', $rates);
        
        wp_send_json_success();
    }
    
    public function ajax_delete_shipping_rate() {
        check_ajax_referer('wc_cuba_shipping_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción.', 'wc-cuba-shipping'));
        }
        
        if (!isset($_POST['id'])) {
            wp_send_json_error(__('ID no proporcionado.', 'wc-cuba-shipping'));
        }
        
        $id = intval($_POST['id']);
        $rates = get_option('wc_cuba_shipping_rates', array());
        
        if (isset($rates[$id])) {
            unset($rates[$id]);
            update_option('wc_cuba_shipping_rates', array_values($rates));
            wp_send_json_success();
        }
        
        wp_send_json_error(__('Tarifa no encontrada.', 'wc-cuba-shipping'));
    }
    
    public function ajax_get_municipalities() {
        check_ajax_referer('wc_cuba_shipping_nonce', 'nonce');
        
        if (!isset($_POST['province'])) {
            wp_send_json_error(__('Provincia no especificada.', 'wc-cuba-shipping'));
        }
        
        $province = sanitize_text_field($_POST['province']);
        $municipalities = isset($this->municipalities[$province]) ? $this->municipalities[$province] : array();
        
        wp_send_json_success(array('municipalities' => $municipalities));
    }
    
    public function add_cuba_states($states) {
        $states['CU'] = array();
        
        foreach ($this->provinces as $province_code => $province_name) {
            $states['CU'][$province_code] = $province_name;
        }
        
        return $states;
    }
    
    public function customize_checkout_fields($fields) {
        $fields['billing']['billing_state'] = array(
            'label' => __('Provincia', 'wc-cuba-shipping'),
            'required' => true,
            'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
            'type' => 'select',
            'options' => array_merge(
                array('' => __('Seleccione una provincia', 'wc-cuba-shipping')),
                $this->provinces
            ),
            'priority' => 50
        );

        $fields['billing']['billing_city'] = array(
            'label' => __('Municipio', 'wc-cuba-shipping'),
            'required' => true,
            'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
            'type' => 'select',
            'options' => array('' => __('Primero seleccione una provincia', 'wc-cuba-shipping')),
            'priority' => 60
        );

        if (isset($fields['shipping']) && $fields['billing']['billing_state'] !== $fields['shipping']['shipping_state']) {
            $fields['shipping']['shipping_state'] = $fields['billing']['billing_state'];
            $fields['shipping']['shipping_state']['priority'] = 50;
            
            $fields['shipping']['shipping_city'] = $fields['billing']['billing_city'];
            $fields['shipping']['shipping_city']['priority'] = 60;
        }

        return $fields;
    }
}

} // Fin del if !class_exists