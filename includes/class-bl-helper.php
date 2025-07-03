<?php
namespace BL\Bilamina;

defined('ABSPATH') || exit;

class Helper {

    public static function calculate_installments($enabledInstallments, $orderId) {
        $logger = wc_get_logger();
        $context = ['source' => 'bilamina-installment'];

        $logger->info('Original enabledInstallments: ' . print_r($enabledInstallments, true), $context);

        $rules = get_option('bl_ic_installment_rules', []);

        if (empty($rules)) {
            $logger->info('Rules empty, fallback to [1]', $context);
            return [1];
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            $logger->info('Order not found, fallback to [1]', $context);
            return [1];
        }

        $months = [];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $product_id = $product->get_id();
            $product_slug = $product->get_slug();

            // Excluded products
            if (!empty($rules['products']['exclude']) &&
                (in_array(strval($product_id), $rules['products']['exclude'], true) || in_array($product_slug, $rules['products']['exclude'], true))) {
                $logger->info("Product {$product_id} excluded by product rule", $context);
                return [1];
            }

            // Excluded categories
            $cat_slugs = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
            if (!empty($rules['categories']['exclude']) &&
                array_intersect($rules['categories']['exclude'], $cat_slugs)) {
                $logger->info("Product {$product_id} excluded by category rule", $context);
                return [1];
            }

            // Excluded tags
            $tag_slugs = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'slugs']);
            if (!empty($rules['tags']['exclude']) &&
                array_intersect($rules['tags']['exclude'], $tag_slugs)) {
                $logger->info("Product {$product_id} excluded by tag rule", $context);
                return [1];
            }

            $matched = false;

            // Included products
            if (!empty($rules['products']['include']) &&
                (in_array(strval($product_id), $rules['products']['include'], true) || in_array($product_slug, $rules['products']['include'], true))) {
                $months[] = intval($rules['products']['months']);
                $logger->info("Product {$product_id} matched in products include, months: {$rules['products']['months']}", $context);
                $matched = true;
            }

            // Included categories
            if (!$matched && !empty($rules['categories']['include']) &&
                array_intersect($rules['categories']['include'], $cat_slugs)) {
                $months[] = intval($rules['categories']['months']);
                $logger->info("Product {$product_id} matched in categories include, months: {$rules['categories']['months']}", $context);
                $matched = true;
            }

            // Included tags
            if (!$matched && !empty($rules['tags']['include']) &&
                array_intersect($rules['tags']['include'], $tag_slugs)) {
                $months[] = intval($rules['tags']['months']);
                $logger->info("Product {$product_id} matched in tags include, months: {$rules['tags']['months']}", $context);
                $matched = true;
            }

            if (!$matched) {
                $logger->info("Product {$product_id} not matched anywhere, forcing [1]", $context);
                return [1];
            }
        }

        if (!empty($months)) {
            $max_allowed = min($months);
            $installments = range(2, $max_allowed);
            $logger->info('Final calculated installments: ' . implode(',', $installments), $context);
            return $installments;
        }

        $logger->info('No months set, fallback to [1]', $context);
        return [1];
    }
}
