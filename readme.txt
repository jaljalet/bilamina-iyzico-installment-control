# Bilamina Iyzico Installment Control

Bilamina Iyzico Installment Control is an add-on for WooCommerce that allows you to set **custom installment restrictions** for the **Iyzico WooCommerce plugin**, based on product categories, tags, and individual products.

## 💡 Features

- Limit installments per product category.
- Limit installments per product tag.
- Limit installments per individual product.
- Define "excluded" categories, tags, or products — force full payment (1 installment only).
- Seamless integration with the Iyzico WooCommerce plugin.

## ⚙️ How it works

By default, the Iyzico WooCommerce plugin displays all possible installments to customers. This add-on plugin filters and restricts available installment options according to your rules.

When the plugin is active, you can configure rules via **WooCommerce → Bilamina Installment Control** in the WordPress admin panel.

## 🛠️ Required modification to Iyzico WooCommerce plugin

> ⚠️ You must manually add a custom filter hook to the **Iyzico WooCommerce plugin** code so that Bilamina Installment Control can override the installment options.

### Edit file

`includes/Checkout/CheckoutForm.php`

Inside the `create_payment()` function, **after** creating the `$request` object but **before** calling `CheckoutFormInitialize::create(...)`, add:

```php
// ===== START Bilamina Custom Installment Logic =====

// By default allow all installments
$enabled_installments = range(1, 12);

// Allow plugins (e.g. Bilamina Installment Control) to override
$enabled_installments = apply_filters('iyzico_checkout_installment_override', $enabled_installments, $orderId);

// Set installments
$request->setEnabledInstallments($enabled_installments);

// ===== END Bilamina Custom Installment Logic =====
