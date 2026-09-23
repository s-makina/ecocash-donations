=== EcoCash Donations ===
Contributors: yourorganisation
Tags: donations, ecocash, zimbabwe, mobile money, fundraising
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Accept EcoCash donations in WordPress via the official EcoCash Open API (C2B instant payments) — no aggregator or middleman fees.

== Description ==

Donors choose an amount, enter their EcoCash number, and approve a payment prompt (STK push) on their phone. Donations are recorded in your WordPress admin with amount, phone, and EcoCash reference.

**Requirements**

* An approved EcoCash **merchant account** (apply via ecocash.co.zw/merchants or any Econet Shop)
* An API key from **developers.ecocash.co.zw** (register after merchant approval)
* HTTPS on your site

**Supported currencies:** USD, ZWL, ZiG (must match what's enabled on your merchant account)

== Installation ==

1. Upload the `ecocash-donations` folder to `/wp-content/plugins/` (or upload the ZIP via Plugins → Add New → Upload Plugin) and activate.
2. Go to **EcoCash Donations → Settings** in wp-admin.
3. Paste your **API key** from the developer portal. Keep the environment on **Sandbox** while testing.
4. Add the shortcode to any page or post:

`[ecocash_donate]`

Options: `amounts="5,10,25,50"`, `currency="USD"`, `title="Support our cause"`, `desc="Your message"`.

== Sandbox testing ==

1. With Environment = Sandbox, do a test donation using the test MSISDNs provided in the developer portal docs.
2. Confirm the donation appears under **EcoCash Donations** in wp-admin with status **Completed** and an EcoCash reference.
3. When ready, get your **live** API key from the portal, switch Environment to **Live**, and run one small real donation (e.g., USD 1) to your own number to verify end-to-end.

== Notes ==

* The EcoCash C2B API is synchronous: when the donor approves the prompt, the payment is confirmed and the plugin records it via the status lookup.
* A donation that stays **pending** longer than ~90 seconds in the browser will still be verified on the next status poll; you can also check status manually from the donations list.
* Refunds: use the `Ecocash_API::refund()` method (see includes/class-ecocash-api.php) or process refunds from the EcoCash merchant portal.
* Developers: hooks available — `ecocash_donation_completed` and `ecocash_donation_failed` fire with ($donation_id, $api_response) when a donation resolves.

== Changelog ==

= 1.1.0 =
* New: donor name, email, and optional message fields on the donation form.
* New: HTML email receipts to donors when payment is confirmed.
* New: admin email notifications for new donations (configurable).
* New: donation details metabox in wp-admin with receipt status.

= 1.0.0 =
* Initial release: donation form shortcode, C2B instant payments, status polling, admin settings, donation records.
