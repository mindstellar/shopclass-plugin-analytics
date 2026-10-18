<?php
/*
 * This file is part of the Analytics plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * The header hook: nothing without a valid id, nothing on admin or preview pages, and one JSON
 * config block (never executable) with the ids when there is one.
 */

require __DIR__ . '/lib/bootstrap.php';

use mindstellar\analytics\Config;
use mindstellar\analytics\Plugin;

harness_section('no id');
setup(array());
pin('prints nothing', '', printed());
pin('queues no script', array(), $GLOBALS['scripts']);

harness_section('a bad id counts as none');
setup(array('ga4_id' => 'UA-123', 'pixel_id' => '12ab'));
pin('prints nothing for bad ids', '', printed());
setup(array('ga4_id' => "G-ABCD\"></script><script>alert(1)"));
pin('a script-breaking id prints nothing', '', printed());

harness_section('a valid id');
setup(array('ga4_id' => 'g-abc123xyz4', 'pixel_id' => '123456789012'));
$out = printed();
check('one JSON block, not executable', substr_count($out, '<script') === 1 && strpos($out, 'type="application/json"') !== false, $out);
$json = json_decode(preg_replace('/^<script[^>]*>|<\/script>\s*$/', '', $out), true);
pin('ga4 upper-cased', 'G-ABC123XYZ4', $json['ga4'] ?? null);
pin('pixel', '123456789012', $json['pixel'] ?? null);
pin('consent defaults on', true, $json['consent'] ?? null);
pin('config holds only these keys', array('accept', 'consent', 'decline', 'ga4', 'pixel', 'text'), (function () use ($json) {
    $k = array_keys($json);
    sort($k);

    return $k;
})());
pin('script queued', array('analytics'), $GLOBALS['scripts']['enqueued'] ?? array());
check('script url is the plugin file', strpos($GLOBALS['scripts']['registered']['analytics'] ?? '', '/analytics/public/analytics.js') !== false);

harness_section('only one id');
setup(array('ga4_id' => 'G-ABCD1234'));
$json = json_decode(preg_replace('/^<script[^>]*>|<\/script>\s*$/', '', printed()), true);
pin('pixel empty', '', $json['pixel'] ?? null);

harness_section('owner text is escaped for a script block');
setup(array('ga4_id' => 'G-ABCD1234', 'consent_text' => '</script><b>"hi" & \'x\'</b>'));
$out = printed();
pin('one closing tag only', 1, substr_count($out, '</script'));
$inner = substr($out, strpos($out, '>') + 1);
$inner = substr($inner, 0, strrpos($inner, '</script>'));
check('inner JSON has no raw < > & \'', strpbrk($inner, "<>&'") === false, $inner);
pin('text survives the round trip', '</script><b>"hi" & \'x\'</b>', json_decode($inner, true)['text']);

harness_section('consent');
setup(array('ga4_id' => 'G-ABCD1234', 'consent' => '0'));
pin('off when saved as 0', false, Config::consent());
setup(array('consent' => '1'));
pin('on when saved as 1', true, Config::consent());
setup(array());
pin('on when never saved', true, Config::consent());
pin('empty text falls back to the default', Config::defaultText(), Config::consentText());

harness_section('admin and preview pages');
setup(array('ga4_id' => 'G-ABCD1234'), true);
pin('prints nothing', '', printed());
pin('queues nothing', array(), $GLOBALS['scripts']);

harness_section('pages whose address carries a secret');
setup_page(array('ga4_id' => 'G-ABCD1234'), array('page' => 'search', 'sPattern' => 'mug'));
check('a plain page gets the block', strpos(printed(), 'analytics-config') !== false);
foreach (array('code' => 'a1b2c3', 'secret' => 's3cr3t', 'token' => 'f00d') as $name => $value) {
    setup_page(array('ga4_id' => 'G-ABCD1234'), array('page' => 'login', $name => $value));
    pin("nothing with ?$name=", '', printed());
    pin("no script with ?$name=", array(), $GLOBALS['scripts']);
}
setup_page(array('ga4_id' => 'G-ABCD1234'), array('page' => 'custom'));
$GLOBALS['filters']['analytics_skip'] = static fn ($skip) => true;
pin('a plugin skips its page through analytics_skip', '', printed());

harness_section('save validation');
pin('empty GA4 id is fine', null, Plugin::validateGa4(''));
pin('good GA4 id', null, Plugin::validateGa4(' g-abcd1234 '));
check('bad GA4 id refused with a message', is_string(Plugin::validateGa4('UA-1')));
check('too short GA4 id refused', is_string(Plugin::validateGa4('G-AB')));
pin('good pixel id', null, Plugin::validatePixel('12345'));
check('letters refused in pixel id', is_string(Plugin::validatePixel('12a45')));
check('4 digits refused', is_string(Plugin::validatePixel('1234')));
check('21 digits refused', is_string(Plugin::validatePixel(str_repeat('1', 21))));

exit(harness_result());
