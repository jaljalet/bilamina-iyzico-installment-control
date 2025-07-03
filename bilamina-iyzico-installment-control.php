<?php
/**
 * Plugin Name: Bilamina Iyzico Installment Control
 * Plugin URI: https://bilamina.com
 * Description: Adds advanced installment control per category, product, and tag for Iyzico WooCommerce gateway.
 * Version: 1.0.0
 * Author: Islam Ataev
 * Author URI: https://bilamina.com
 * Text Domain: bilamina-iyzico-installment-control
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.9
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || exit;

define('BL_IC_VERSION', '1.0.0');
define('BL_IC_PATH', plugin_dir_path(__FILE__));
define('BL_IC_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, 'bl_ic_activate');
register_deactivation_hook(__FILE__, 'bl_ic_deactivate');

/**
 * Activation hook: check PHP and Iyzico dependencies.
 */
function bl_ic_activate() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Bilamina Iyzico Installment Control requires PHP version 7.4 or higher.', 'bilamina-iyzico-installment-control'),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }

    if (!class_exists('\Iyzico\IyzipayWoocommerce\Core\Plugin')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Iyzico WooCommerce plugin must be installed and active.', 'bilamina-iyzico-installment-control'),
            'Plugin Dependency Check',
            ['back_link' => true]
        );
    }
}

/**
 * Deactivation hook: placeholder for future cleanup.
 */
function bl_ic_deactivate() {
    // Remove our filter to restore default installments
    remove_filter('iyzico_checkout_installment_override', ['BL\Bilamina\Helper', 'calculate_installments'], 10);
    // Delete options
    delete_option('bl_ic_installment_rules');
}

/**
 * Dummy callback to ensure the filter exists.
 */
/*add_filter('iyzico_checkout_installment_override', 'bl_ic_dummy_callback', 10, 2);
function bl_ic_dummy_callback($enabledInstallments, $orderId) {
    return $enabledInstallments;
}*/

/**
 * Check if required Iyzico filter exists.
 */
/*function bl_ic_check_filter_exists() {
    // Можно проверять, что фильтр действительно внедрён в код Iyzico
    if (!has_filter('iyzico_checkout_installment_override')) {
        deactivate_plugins(plugin_basename(__FILE__));
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('Required Iyzico filter not found. Please re-add the line in Iyzico plugin.', 'bilamina-iyzico-installment-control')
                . '</p></div>';
        });
    }
}*/

add_action('plugins_loaded', 'bl_ic_init', 20);

function bl_ic_init() {
    if (!class_exists('\Iyzico\IyzipayWoocommerce\Core\Plugin')) {
        deactivate_plugins(plugin_basename(__FILE__));
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('Bilamina Iyzico Installment Control has been deactivated because Iyzico WooCommerce plugin is not active.', 'bilamina-iyzico-installment-control')
                . '</p></div>';
        });
        return;
    }

    //bl_ic_check_filter_exists();

    require_once BL_IC_PATH . 'includes/class-bl-admin.php';
    require_once BL_IC_PATH . 'includes/class-bl-ajax.php';
    require_once BL_IC_PATH . 'includes/class-bl-settings.php';
    require_once BL_IC_PATH . 'includes/class-bl-helper.php';

    // Register our filter after classes are loaded
    add_filter('iyzico_checkout_installment_override', ['BL\Bilamina\Helper', 'calculate_installments'], 10, 2);

    if (is_admin()) {
        \BL\Bilamina\Admin::instance();
    }
}

/**
 * Auto-deactivate addon if Iyzico plugin is deactivated.
 */
add_action('deactivated_plugin', 'bl_ic_check_iyzico_deactivation', 10, 2);

function bl_ic_check_iyzico_deactivation($plugin, $network_deactivating) {
    if ($plugin === 'iyzico-woocommerce/iyzico-woocommerce.php') {
        deactivate_plugins(plugin_basename(__FILE__));
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible"><p>'
                . esc_html__('Bilamina Iyzico Installment Control has been deactivated because Iyzico plugin was deactivated.', 'bilamina-iyzico-installment-control')
                . '</p></div>';
        });
    }
}
