<?php
/**
 * Uninstall Bilamina Iyzico Installment Control
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Delete saved rules option
delete_option('bl_ic_installment_rules');
