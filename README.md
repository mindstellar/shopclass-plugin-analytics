# Analytics

Google Analytics 4 and Meta Pixel for Shopclass shops, with a built-in consent bar. Nothing is
added to a page until you save an id, and no tag loads until the visitor accepts.

Needs Shopclass 7.0 or later. Shop events need a theme that publishes them, such as Aisle. On any
other theme you get page views only.

![Analytics settings under Settings in the admin](assets/screenshot-1.png)
![The consent bar on a phone](assets/screenshot-2.png)

## Install

Plugins, Add new, Analytics, then Install and Activate. Or unzip into `oc-content/plugins/`.

## Settings

Settings, Analytics.

- **GA4 measurement id**: `G-` and 4 to 12 letters or digits. Leave empty for none.
- **Meta pixel id**: 5 to 20 digits. Leave empty for none.
- **Ask visitors before loading a tag**: on by default. Shows a bar with Accept and Decline.
- **Consent bar text**: your wording. Empty uses the default.

A bad id is refused on save. GA4 anonymises visitor IP addresses by default.

## Events

The theme publishes GA4-named events to `window.aisleTrack` and to a `document` event
`aisle:track`. GA4 gets every one under its own name. The pixel gets these:

| Theme event | Meta event |
| --- | --- |
| `view_item` | ViewContent |
| `add_to_cart` | AddToCart |
| `begin_checkout` | InitiateCheckout |
| `add_payment_info` | AddPaymentInfo |
| `purchase` | Purchase |
| `search` | Search |
| `sign_up` | Lead |

## Consent

With the bar on, the visitor sees it on the first page. Accept loads the tags and sends the page
view and the events so far. Decline loads nothing and is remembered for 180 days. The choice is kept
in the browser's `localStorage` (`analytics-consent`), not a cookie, so cached pages stay cached.

The tags themselves set their own cookies after Accept. The bar is a plain opt-in with your
wording, not legal advice: check what your market requires and say so in your privacy page.

## What is sent

After Accept only: the page view, product ids, names, prices, quantities, order totals and search
terms, to Google (`googletagmanager.com`) and Meta (`connect.facebook.net`). The theme block holds
no name, e-mail or address.

## What it stores

Four preferences under `analytics` (the two ids, the bar on or off, the bar text), removed on
uninstall. Nothing on the server per visitor.

## Hooks

Prints one `<script type="application/json" id="analytics-config">` on the `header` hook and queues
`public/analytics.js`. Nothing on admin, API or `?osc_preview=1` pages.

## Tests

`tests/run.sh` (PHP and Node, no core or network needed).
