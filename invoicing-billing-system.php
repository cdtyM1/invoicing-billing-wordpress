<?php
/**
 * Plugin Name: TagihPro Billing System
 * Description: WordPress plugin untuk dashboard penagihan, invoice, customer, reminder otomatis, dan simulasi WhatsApp/Email.
 * Version: 1.0.0
 * Author: Copilot
 * Text Domain: tagihpro-billing
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-ibs-plugin.php';

add_action('plugins_loaded', function () {
    IBS_Plugin::instance();
});
