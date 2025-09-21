<?php

/**
 * Plugin Name: Sigma Signet
 * Description: WordPress OIDC integration plugin for SIGMA authentication system
 * Version: 0.1.0
 * Author: Seán Stickle (Folger Shakespeare Library)
 * Text Domain: sigma-signet
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize the plugin
add_action('wp_loaded', function () {
    if (class_exists('SigmaSignet\Plugin')) {
        $plugin = SigmaSignet\Plugin::getInstance();
        if (isset($plugin->settings)) {
            $plugin->settings->debugLog('Sigma Signet Plugin Loaded (with Plugin class)');
        }
    } else {
        error_log('Sigma Signet Plugin Loaded (Plugin class not found)');
    }
});
