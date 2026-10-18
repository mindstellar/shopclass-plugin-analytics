/*
 * Loads public/analytics.js into a stub window and document (no jsdom) and checks consent and
 * the event map.
 */
'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');
const assert = require('assert');

const code = fs.readFileSync(path.join(__dirname, '..', 'public', 'analytics.js'), 'utf8');
let failed = 0;

function test(name, fn) {
  try { fn(); console.log('PASS  ' + name); } catch (e) { failed++; console.log('FAIL  ' + name + '\n      ' + e.message); }
}

function page(config, store, queue) {
  const loaded = [];
  const listeners = {};
  const body = { children: [], appendChild(el) { el.parentNode = body; this.children.push(el); }, removeChild(c) { this.children = this.children.filter((x) => x !== c); } };
  const el = () => ({
    style: {}, children: [], attrs: {}, listeners: {},
    setAttribute(k, v) { this.attrs[k] = v; },
    appendChild(c) { this.children.push(c); },
    addEventListener(t, f) { this.listeners[t] = f; },
    get parentNode() { return this._p; }, set parentNode(v) { this._p = v; },
    removeChild(c) { body.children = body.children.filter((x) => x !== c); },
  });
  const gtagCalls = [];
  const fbqCalls = [];
  const win = {
    aisleTrack: queue || [],
    gtag(...a) { gtagCalls.push(a); },
    fbq(...a) { fbqCalls.push(a); },
    localStorage: { getItem: (k) => (k in store ? store[k] : null), setItem: (k, v) => { store[k] = String(v); } },
    addEventListener() {},
  };
  const doc = {
    readyState: 'complete', body, head: { appendChild: (s) => loaded.push(s.src) },
    getElementById: (id) => (id === 'analytics-config' && config ? { textContent: JSON.stringify(config) } : null),
    createElement: el,
    addEventListener(t, f) { listeners[t] = f; },
    querySelectorAll: () => [],
  };
  win.window = win;
  win.document = doc;
  vm.runInNewContext(code, Object.assign(win, { Date, JSON, Math, Number, String, Array, Object, encodeURIComponent }), { filename: 'analytics.js' });
  return { win, doc, body, loaded, listeners, gtagCalls, fbqCalls };
}

const cfg = { ga4: 'G-ABCD1234', pixel: '123456', consent: true, text: 'Allow?', accept: 'Accept', decline: 'Decline' };
const addToCart = { event: 'add_to_cart', currency: 'INR', value: 499, items: [{ item_id: '7', item_name: 'Mug', price: 499, quantity: 2 }] };

test('no config block: nothing happens', () => {
  const p = page(null, {});
  assert.strictEqual(p.loaded.length, 0);
  assert.strictEqual(p.body.children.length, 0);
});

test('before consent: bar drawn, nothing loaded, nothing sent', () => {
  const p = page(cfg, {}, [addToCart]);
  assert.strictEqual(p.body.children.length, 1);
  assert.strictEqual(p.loaded.length, 0);
  assert.strictEqual(p.gtagCalls.length, 0);
  assert.strictEqual(p.fbqCalls.length, 0);
});

test('Accept loads both tags, stores yes, drains the queue with mapped names', () => {
  const store = {};
  const p = page(cfg, store, [addToCart]);
  const buttons = p.body.children[0].children[1].children;
  buttons[1].listeners.click();
  assert.strictEqual(store['analytics-consent'], 'yes');
  assert.strictEqual(p.body.children.length, 0);
  assert.ok(p.loaded.some((s) => s.startsWith('https://www.googletagmanager.com/gtag/js?id=G-ABCD1234')));
  assert.ok(p.loaded.some((s) => s.startsWith('https://connect.facebook.net/')));
  const g = p.gtagCalls.find((c) => c[0] === 'event');
  assert.strictEqual(g[1], 'add_to_cart');
  assert.strictEqual(g[2].items[0].item_id, '7');
  const f = p.fbqCalls.find((c) => c[0] === 'track' && c[1] === 'AddToCart');
  assert.ok(f, 'AddToCart sent');
  assert.strictEqual(f[2].value, 499);
  assert.strictEqual(f[2].currency, 'INR');
  assert.strictEqual(JSON.stringify(f[2].contents), JSON.stringify([{ id: '7', quantity: 2, item_price: 499 }]));
});

test('consent yes already stored: loads at once, later aisle:track events reach both', () => {
  const p = page(cfg, { 'analytics-consent': 'yes' });
  assert.ok(p.loaded.length >= 2);
  p.listeners['aisle:track']({ detail: { event: 'begin_checkout', currency: 'INR', value: 10, items: [] } });
  assert.ok(p.gtagCalls.some((c) => c[1] === 'begin_checkout'));
  assert.ok(p.fbqCalls.some((c) => c[1] === 'InitiateCheckout'));
});

test('every Meta mapping', () => {
  const p = page(cfg, { 'analytics-consent': 'yes' });
  const map = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', add_payment_info: 'AddPaymentInfo', purchase: 'Purchase', search: 'Search', sign_up: 'Lead' };
  Object.keys(map).forEach((k) => p.listeners['aisle:track']({ detail: { event: k, search_term: 'mug' } }));
  Object.keys(map).forEach((k) => assert.ok(p.fbqCalls.some((c) => c[0] === 'track' && c[1] === map[k]), k));
  assert.ok(p.fbqCalls.some((c) => c[1] === 'Search' && c[2].search_string === 'mug'));
});

test('events with no Meta mapping go to GA4 only', () => {
  const p = page(cfg, { 'analytics-consent': 'yes' });
  const before = p.fbqCalls.length;
  p.listeners['aisle:track']({ detail: { event: 'view_cart', items: [] } });
  assert.strictEqual(p.fbqCalls.length, before);
  assert.ok(p.gtagCalls.some((c) => c[1] === 'view_cart'));
});

test('consent no (fresh): loads nothing, draws nothing', () => {
  const p = page(cfg, { 'analytics-consent': 'no', 'analytics-consent-at': String(Date.now()) }, [addToCart]);
  assert.strictEqual(p.loaded.length, 0);
  assert.strictEqual(p.body.children.length, 0);
  assert.strictEqual(p.gtagCalls.length, 0);
});

test('consent no older than 180 days: the bar returns, still nothing loaded', () => {
  const old = Date.now() - 181 * 24 * 3600 * 1000;
  const p = page(cfg, { 'analytics-consent': 'no', 'analytics-consent-at': String(old) });
  assert.strictEqual(p.body.children.length, 1);
  assert.strictEqual(p.loaded.length, 0);
});

test('Decline stores no and loads nothing', () => {
  const store = {};
  const p = page(cfg, store);
  p.body.children[0].children[1].children[0].listeners.click();
  assert.strictEqual(store['analytics-consent'], 'no');
  assert.ok(Number(store['analytics-consent-at']) > 0);
  assert.strictEqual(p.loaded.length, 0);
});

test('bar off: loads at once with no bar', () => {
  const p = page(Object.assign({}, cfg, { consent: false }), {});
  assert.strictEqual(p.body.children.length, 0);
  assert.ok(p.loaded.length >= 2);
});

test('one id only loads one tag', () => {
  const p = page({ ga4: 'G-ABCD1234', pixel: '', consent: false }, {});
  assert.strictEqual(p.loaded.length, 1);
  assert.strictEqual(p.fbqCalls.length, 0);
});

test('no aisleTrack queue: page views only, no error', () => {
  const p = page(Object.assign({}, cfg, { consent: false }), {}, undefined);
  assert.ok(p.gtagCalls.some((c) => c[0] === 'config'));
  assert.ok(p.fbqCalls.some((c) => c[1] === 'PageView'));
});

test('owner text goes in with textContent, never as markup', () => {
  const src = code.replace(/\/\*[\s\S]*?\*\//g, '');
  assert.ok(!/innerHTML|insertAdjacentHTML|document\.write/.test(src));
});

process.exit(failed ? 1 : 0);
