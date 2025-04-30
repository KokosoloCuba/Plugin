<?php
/*
Plugin Name: WooCommerce Cuba Shipping Zones
Plugin URI: https://electromatanzas.supertienda.cu
Description: Gestión personalizada de zonas de envío para provincias y municipios de Cuba
Version: 2.6.1
Author: Odecte Rodríguez Madruga
Author URI: https://electromatanzas.supertienda.cu
License: GPLv2 or later
Text Domain: wc-cuba-shipping
*/

defined('ABSPATH') || exit;

// Cargar archivos principales
$plugin_path = plugin_dir_path(__FILE__);
require_once $plugin_path . 'includes/class-wc-cuba-shipping-providers.php';
require_once $plugin_path . 'includes/class-wc-cuba-shipping.php';

register_activation_hook(__FILE__, 'wc_cuba_shipping_activate');

function wc_cuba_shipping_activate() {
    if (!get_option('wc_cuba_shipping_providers')) {
        update_option('wc_cuba_shipping_providers', WC_Cuba_Shipping_Providers::get_default_providers());
    }
    
    if (!get_option('wc_cuba_shipping_rates')) {
        update_option('wc_cuba_shipping_rates', array());
    }
}

add_action('plugins_loaded', 'wc_cuba_shipping_init', 11);

function wc_cuba_shipping_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_cuba_shipping_missing_notice');
        return;
    }
    
    load_plugin_textdomain('wc-cuba-shipping', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Inicializar las clases principales
    if (class_exists('WC_Cuba_Shipping_Providers')) {
        WC_Cuba_Shipping_Providers::init();
    }
    
    if (class_exists('WC_Cuba_Shipping_Plugin')) {
        new WC_Cuba_Shipping_Plugin();
    }
}

function wc_cuba_shipping_missing_notice() {
    echo '<div class="notice notice-error"><p>';
    printf(
        __('%s requiere WooCommerce. %s', 'wc-cuba-shipping'),
        '<strong>WC Cuba Shipping Zones</strong>',
        '<a href="' . esc_url(admin_url('plugin-install.php?tab=search&s=woocommerce')) . '">' . __('Instalar WooCommerce', 'wc-cuba-shipping') . '</a>'
    );
    echo '</p></div>';
}

register_uninstall_hook(__FILE__, 'wc_cuba_shipping_uninstall');

function wc_cuba_shipping_uninstall() {
    delete_option('wc_cuba_shipping_providers');
    delete_option('wc_cuba_shipping_rates');
}