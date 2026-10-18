<?php

/*
 * This file is part of the Analytics plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/*
 * The settings page declaration. Core renders the form, checks CSRF and the admin's capability,
 * and stores each field under the "analytics" section.
 */

use mindstellar\analytics\Plugin;

return array(
    'title'  => __('Analytics', 'analytics'),
    'menu'   => 'settings',
    'intro'  => __('Paste an id to turn a tag on. With no id nothing is added to your pages. Events come from your theme; page views work on any theme.', 'analytics'),
    'fields' => array(
        array(
            'type'      => 'text',
            'name'      => 'ga4_id',
            'label'     => __('Google Analytics 4 measurement id', 'analytics'),
            'help'      => __('Looks like G-ABC123XYZ4. Leave empty for none. Google anonymises visitor IP addresses in GA4 by default.', 'analytics'),
            'maxlength' => 20,
            'default'   => '',
            'validate'  => array(Plugin::class, 'validateGa4'),
        ),
        array(
            'type'      => 'text',
            'name'      => 'pixel_id',
            'label'     => __('Meta pixel id', 'analytics'),
            'help'      => __('Digits only. Leave empty for none.', 'analytics'),
            'maxlength' => 20,
            'default'   => '',
            'validate'  => array(Plugin::class, 'validatePixel'),
        ),
        array(
            'type'    => 'checkbox',
            'name'    => 'consent',
            'label'   => __('Ask visitors before loading a tag', 'analytics'),
            'help'    => __('Shows a bar with Accept and Decline. Nothing loads until Accept; Decline is remembered for 180 days. Turn it off only if another tool asks for consent.', 'analytics'),
            'default' => true,
        ),
        array(
            'type'      => 'text',
            'name'      => 'consent_text',
            'label'     => __('Consent bar text', 'analytics'),
            'help'      => __('Shown in the bar. Leave empty for the default text.', 'analytics'),
            'maxlength' => 300,
            'default'   => '',
            'depends'   => 'consent',
        ),
    ),
);
