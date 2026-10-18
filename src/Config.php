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
 * The saved settings, and the rules for the two ids. A bad id counts as no id, so a value that
 * slipped into the preferences by another route still never reaches a page.
 */
final class Config
{
    public const GA4_PATTERN   = '/^G-[A-Z0-9]{4,12}$/';
    public const PIXEL_PATTERN = '/^[0-9]{5,20}$/';

    /** fn(string $name): mixed -- replaces the settings store in tests. */
    public static ?Closure $reader = null;

    public static function ga4(): string
    {
        $id = strtoupper(trim((string) self::value('ga4_id')));

        return preg_match(self::GA4_PATTERN, $id) === 1 ? $id : '';
    }

    public static function pixel(): string
    {
        $id = trim((string) self::value('pixel_id'));

        return preg_match(self::PIXEL_PATTERN, $id) === 1 ? $id : '';
    }

    /** The consent bar is on unless the owner switched it off. */
    public static function consent(): bool
    {
        $value = self::value('consent');

        return $value === null || $value === '' ? true : (bool) (int) $value;
    }

    public static function consentText(): string
    {
        $text = trim((string) self::value('consent_text'));

        return $text !== '' ? $text : self::defaultText();
    }

    public static function defaultText(): string
    {
        return __('We use analytics cookies to see how the shop is used. Allow them?', 'analytics');
    }

    public static function validGa4(string $id): bool
    {
        return preg_match(self::GA4_PATTERN, $id) === 1;
    }

    public static function validPixel(string $id): bool
    {
        return preg_match(self::PIXEL_PATTERN, $id) === 1;
    }

    /** @return mixed */
    private static function value(string $name)
    {
        if (self::$reader !== null) {
            return (self::$reader)($name);
        }

        return osc_settings_value(Plugin::PAGE, $name);
    }
}
