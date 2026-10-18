/*
 * Analytics plugin for Shopclass. Reads #analytics-config, asks for consent when the owner wants
 * it, then loads Google Analytics 4 and/or the Meta pixel and forwards the theme's shop events
 * (window.aisleTrack and the "aisle:track" document event). Nothing loads before consent.
 */
(function (win, doc) {
  'use strict';

  var KEY = 'analytics-consent';
  var STAMP = 'analytics-consent-at';
  var DECLINE_MS = 180 * 24 * 60 * 60 * 1000;

  var node = doc.getElementById('analytics-config');
  var cfg = null;
  try { cfg = node ? JSON.parse(node.textContent || '') : null; } catch (e) { cfg = null; }
  if (!cfg || (!cfg.ga4 && !cfg.pixel)) return;

  function read(k) {
    try { return win.localStorage.getItem(k); } catch (e) { return null; }
  }
  function write(k, v) {
    try { win.localStorage.setItem(k, v); } catch (e) { /* private mode: asked again next page */ }
  }

  var META = {
    view_item: 'ViewContent',
    add_to_cart: 'AddToCart',
    begin_checkout: 'InitiateCheckout',
    add_payment_info: 'AddPaymentInfo',
    purchase: 'Purchase',
    search: 'Search',
    sign_up: 'Lead'
  };

  function loadScript(src) {
    var s = doc.createElement('script');
    s.async = true;
    s.src = src;
    (doc.head || doc.documentElement).appendChild(s);
  }

  function metaData(p) {
    var items = Array.isArray(p.items) ? p.items : [];
    var out = {};
    if (p.value !== undefined) out.value = p.value;
    if (p.currency) out.currency = p.currency;
    if (items.length) {
      out.content_type = 'product';
      out.contents = items.map(function (it) {
        return { id: String(it.item_id), quantity: it.quantity || 1, item_price: it.price };
      });
      out.content_ids = items.map(function (it) { return String(it.item_id); });
      out.num_items = items.reduce(function (n, it) { return n + (it.quantity || 1); }, 0);
    }
    if (p.search_term) out.search_string = String(p.search_term);
    return out;
  }

  var started = false;

  // The address sent as the page, without values that carry a secret (the server skips such pages too).
  function pageLocation() {
    try {
      var u = new win.URL(win.location.href);
      ['code', 'secret', 'token'].forEach(function (k) { u.searchParams.delete(k); });
      u.hash = '';
      return u.href;
    } catch (e) { return ''; }
  }

  function send(ev) {
    if (!ev || typeof ev.event !== 'string') return;
    var payload = {};
    Object.keys(ev).forEach(function (k) { if (k !== 'event') payload[k] = ev[k]; });
    if (cfg.ga4 && typeof win.gtag === 'function') win.gtag('event', ev.event, payload);
    if (cfg.pixel && typeof win.fbq === 'function' && META[ev.event]) {
      win.fbq('track', META[ev.event], metaData(payload));
    }
  }

  function start() {
    if (started) return;
    started = true;

    if (cfg.ga4) {
      win.dataLayer = win.dataLayer || [];
      if (typeof win.gtag !== 'function') {
        win.gtag = function () { win.dataLayer.push(arguments); };
      }
      win.gtag('js', new Date());
      var where = pageLocation();
      if (where) win.gtag('config', cfg.ga4, { page_location: where });
      else win.gtag('config', cfg.ga4);
      loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.ga4));
    }
    if (cfg.pixel) {
      if (typeof win.fbq !== 'function') {
        var q = function () { (q.queue = q.queue || []).push(arguments); };
        win.fbq = q;
        win._fbq = q;
        q.loaded = true;
        q.version = '2.0';
        q.queue = [];
      }
      win.fbq('init', cfg.pixel);
      win.fbq('track', 'PageView');
      loadScript('https://connect.facebook.net/en_US/fbevents.js');
    }

    var queued = Array.isArray(win.aisleTrack) ? win.aisleTrack.slice() : [];
    queued.forEach(send);
    doc.addEventListener('aisle:track', function (e) {
      // The queue already holds every event; the document event only repeats later ones.
      if (e && e.detail && queued.indexOf(e.detail) === -1) send(e.detail);
    });
  }

  function remembered() {
    var v = read(KEY);
    if (v === 'yes') return 'yes';
    if (v === 'no') {
      var at = Number(read(STAMP));
      if (at > 0 && Date.now() - at < DECLINE_MS) return 'no';
    }
    return '';
  }

  function bar() {
    var el = doc.createElement('div');
    el.setAttribute('role', 'region');
    el.setAttribute('aria-label', cfg.text);
    el.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:20;display:flex;flex-wrap:wrap;gap:8px 16px;'
      + 'align-items:center;justify-content:space-between;padding:12px 16px;font:inherit;font-size:14px;'
      + 'color:CanvasText;background:Canvas;border-top:1px solid currentColor;box-sizing:border-box;';
    var p = doc.createElement('p');
    p.style.cssText = 'margin:0;flex:1 1 240px;';
    p.textContent = cfg.text;
    var row = doc.createElement('div');
    row.style.cssText = 'display:flex;gap:8px;';
    function button(label, accept) {
      var b = doc.createElement('button');
      b.type = 'button';
      b.textContent = label;
      b.style.cssText = 'font:inherit;color:inherit;cursor:pointer;padding:8px 16px;min-height:36px;border:1px solid currentColor;'
        + 'border-radius:6px;' + (accept ? 'background:CanvasText;color:Canvas;' : 'background:transparent;');
      b.addEventListener('click', function () {
        write(KEY, accept ? 'yes' : 'no');
        write(STAMP, String(Date.now()));
        if (el.parentNode) el.parentNode.removeChild(el);
        if (accept) start();
      });
      return b;
    }
    row.appendChild(button(cfg.decline, false));
    row.appendChild(button(cfg.accept, true));
    el.appendChild(p);
    el.appendChild(row);
    doc.body.appendChild(el);

    // Sit above a fixed bar the theme shows (such as a sticky buy bar), never over it.
    function lift() {
      var lifted = 0;
      var nodes = doc.querySelectorAll ? doc.querySelectorAll('[data-sticky-buy]') : [];
      Array.prototype.forEach.call(nodes, function (n) {
        var cs = win.getComputedStyle ? win.getComputedStyle(n) : null;
        if (cs && cs.position === 'fixed' && cs.display !== 'none') lifted = Math.max(lifted, n.offsetHeight || 0);
      });
      el.style.bottom = lifted + 'px';
    }
    lift();
    win.addEventListener('resize', lift);
  }

  if (!cfg.consent) {
    start();
    return;
  }
  var state = remembered();
  if (state === 'yes') {
    start();
  } else if (state === '') {
    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', bar);
    else bar();
  }
})(window, document);
