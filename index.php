<?php
/*
Plugin Name: Analytics
Plugin URI: https://github.com/mindstellar/shopclass-plugin-analytics
Description: Google Analytics 4 and Meta Pixel shop events from the theme, with a built-in consent bar. Nothing loads until you add an id and the visitor accepts.
Version: 0.1.0
Author: Navjot Tomer (Mindstellar)
Author URI: https://mindstellar.com
Short Name: analytics
Requires Shopclass: 7.0.0
Tested up to: 7.0
Requires PHP: 8.0
Support URI: https://github.com/mindstellar/shopclass-plugin-analytics/issues
*/

/*
 * This file is part of the Analytics plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use mindstellar\analytics\Plugin;

if (!defined('ABS_PATH')) {
    exit('Direct access is not allowed.');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'mindstellar\\analytics\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

osc_register_plugin(osc_plugin_path(__FILE__), array(Plugin::class, 'install'));
osc_add_hook(osc_plugin_path(__FILE__) . '_uninstall', array(Plugin::class, 'uninstall'));

// Needs Shopclass 7.0's settings pages. On a core without them it loads and does nothing but say so.
if (!function_exists('osc_register_settings_page') || !function_exists('osc_settings_value')) {
    osc_add_hook('admin_page_header', static function (): void {
        echo '<div class="flashmessage flashmessage-warning" role="status">'
            . osc_esc_html(__('Analytics needs Shopclass 7.0 or later and is switched off. Update Shopclass to use it.', 'analytics'))
            . '</div>';
    }, 10);

    return;
}

Plugin::$file = __FILE__;

osc_register_settings_page(Plugin::PAGE, require __DIR__ . '/settings.php');
osc_add_hook(osc_plugin_path(__FILE__) . '_configure', static function () {
    osc_redirect_to(osc_settings_page_url(Plugin::PAGE));
});

osc_add_hook('header', array(Plugin::class, 'header'), 10);
