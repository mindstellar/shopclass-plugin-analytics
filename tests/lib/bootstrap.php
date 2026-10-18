<?php
/*
 * This file is part of the Analytics plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Loads the plugin's classes with no core, no database and no network. The few core names the
 * header code calls are stood in for here and record what they were asked.
 */

require_once __DIR__ . '/harness.php';

ini_set('display_errors', '1');

spl_autoload_register(static function (string $class): void {
    $prefix = 'mindstellar\\analytics\\';
    if (strncmp($class, $prefix, strlen($prefix)) === 0) {
        require __DIR__ . '/../../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

if (!function_exists('__')) {
    function __($text, $domain = '')
    {
        return $text;
    }
}

$GLOBALS['scripts'] = array();
function osc_register_script($id, $url)
{
    $GLOBALS['scripts']['registered'][$id] = $url;
}
function osc_enqueue_script($id)
{
    $GLOBALS['scripts']['enqueued'][] = $id;
}
function osc_asset_url_versioned($url)
{
    return $url . '?v=1';
}
function osc_plugin_url($file)
{
    return 'https://shop.test/oc-content/plugins/analytics/';
}

/** Settings over an array, the page checks on or off. */
function setup(array $settings, bool $skip = false): void
{
    $GLOBALS['scripts'] = array();
    \mindstellar\analytics\Config::$reader = static fn (string $name) => $settings[$name] ?? null;
    \mindstellar\analytics\Plugin::$skip   = static fn (): bool => $skip;
    \mindstellar\analytics\Plugin::$file   = '/x/analytics/index.php';
}

/** What header() printed. */
function printed(): string
{
    ob_start();
    \mindstellar\analytics\Plugin::header();

    return (string) ob_get_clean();
}
