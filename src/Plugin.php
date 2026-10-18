<?php

/*
 * This file is part of the Analytics plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace mindstellar\analytics;

use Closure;

/**
 * Prints the one config block and queues the script on public pages, when an id is set.
 */
final class Plugin
{
    public const PAGE = 'analytics';

    /** The plugin's index.php, set by it. */
    public static string $file = '';

    /** fn(): bool -- replaces the page checks in tests. */
    public static ?Closure $skip = null;

    public static function install(): void
    {
    }

    public static function uninstall(): void
    {
        foreach (array('ga4_id', 'pixel_id', 'consent', 'consent_text') as $name) {
            osc_delete_preference($name, self::PAGE);
        }
    }

    /** Settings validation for the GA4 id. */
    public static function validateGa4($value, array $field = array()): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' || Config::validGa4($value)
            ? null
            : __('Use a GA4 measurement id such as G-ABC123XYZ4.', 'analytics');
    }

    /** Settings validation for the pixel id. */
    public static function validatePixel($value, array $field = array()): ?string
    {
        $value = trim((string) $value);

        return $value === '' || Config::validPixel($value)
            ? null
            : __('Use the numeric Meta pixel id, 5 to 20 digits.', 'analytics');
    }

    /** True on the admin, API and customiser preview, which never get a tag. */
    public static function skip(): bool
    {
        if (self::$skip !== null) {
            return (bool) (self::$skip)();
        }
        if (defined('OC_ADMIN') && OC_ADMIN === true) {
            return true;
        }
        if (function_exists('osc_is_api_request') && osc_is_api_request()) {
            return true;
        }

        return \Params::getParamString('osc_preview') === '1';
    }

    /**
     * The JSON the script reads. Escaped for a script element: no tag, ampersand or quote in it
     * can end the block early.
     */
    public static function configJson(): string
    {
        return (string) json_encode(array(
            'ga4'     => Config::ga4(),
            'pixel'   => Config::pixel(),
            'consent' => Config::consent(),
            'text'    => Config::consentText(),
            'accept'  => __('Accept', 'analytics'),
            'decline' => __('Decline', 'analytics'),
        ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }

    /** The `header` hook: the config block and the script, or nothing. */
    public static function header(): void
    {
        if (self::skip() || (Config::ga4() === '' && Config::pixel() === '')) {
            return;
        }
        echo '<script type="application/json" id="analytics-config">' . self::configJson() . '</script>' . "\n";
        osc_register_script('analytics', osc_asset_url_versioned(osc_plugin_url(self::$file) . 'public/analytics.js'));
        osc_enqueue_script('analytics');
    }
}
