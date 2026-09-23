# EcoCash Donations

Accept EcoCash donations on WordPress via the official EcoCash Open API (C2B instant payments) — no aggregator or middleman fees.

Donors choose an amount, enter their EcoCash number, and approve a payment prompt (STK push) on their phone. Donations are recorded in your WordPress admin with amount, phone, donor details, and the EcoCash reference.

## Features

- Donation form via the `[ecocash_donate]` shortcode
- C2B instant payments (STK push) through the EcoCash Open API
- Sandbox and live environments
- Custom donation post type with status, amount, donor, phone, and reference columns
- Donor name, email, and optional message fields
- HTML email receipts to donors on confirmation
- Optional admin email notifications for new donations
- Donation details metabox in wp-admin with receipt status
- Automatic status polling (donor approval → confirmed)
- Refund support via `Ecocash_API::refund()`
- Supported currencies: USD, ZWL, ZiG

## Requirements

- WordPress 5.8+
- PHP 7.4+
- An approved EcoCash **merchant account** (apply via ecocash.co.zw/merchants or any Econet Shop)
- An API key from **developers.ecocash.co.zw** (register after merchant approval)
- HTTPS on your site

## Installation

1. Upload the `ecocash-donations` folder to `/wp-content/plugins/` (or upload the ZIP via **Plugins → Add New → Upload Plugin**) and activate.
2. Go to **EcoCash Donations → Settings** in wp-admin.
3. Paste your **API key** from the developer portal. Keep the environment on **Sandbox** while testing.
4. Add the shortcode to any page or post:

```
[ecocash_donate]
```

### Shortcode options

| Attribute | Default | Description |
| --------- | ------- | ----------- |
| `amounts` | `5,10,25,50` | Comma-separated preset amounts |
| `currency` | site setting | Currency code (USD, ZWL, ZIG) |
| `title` | "Make a Donation" | Form heading |
| `desc` | default text | Form description |
| `show_message` | `true` | Set `false` to hide the optional donor message field |

Example:

```
[ecocash_donate amounts="5,10,25,50" currency="USD" title="Support our cause" desc="Your message"]
```

## Sandbox testing

1. With Environment = Sandbox, do a test donation using the test MSISDNs provided in the developer portal docs.
2. Confirm the donation appears under **EcoCash Donations** in wp-admin with status **Completed** and an EcoCash reference.
3. When ready, get your **live** API key from the portal, switch Environment to **Live**, and run one small real donation (e.g., USD 1) to your own number to verify end-to-end.

## Configuration

Settings are stored as WordPress options and managed under **EcoCash Donations → Settings**:

| Option | Default | Description |
| ------ | ------- | ----------- |
| `ecocash_api_key` | — | API key from developers.ecocash.co.zw |
| `ecocash_environment` | `sandbox` | `sandbox` or `live` |
| `ecocash_currency` | `USD` | `USD`, `ZWL`, or `ZIG` |
| `ecocash_test_number` | — | Optional test number for live verification |
| `ecocash_notify_admin` | `yes` | Email admin on completed donations |
| `ecocash_admin_email` | site admin email | Recipient for admin notifications |

## Hooks for developers

| Hook | Arguments | Description |
| ---- | --------- | ----------- |
| `ecocash_donation_completed` | `($donation_id, $api_response)` | Fires when a donation resolves as completed |
| `ecocash_donation_failed` | `($donation_id, $api_response)` | Fires when a donation fails |

Example:

```php
add_action( 'ecocash_donation_completed', function ( $donation_id, $response ) {
    // Send to your CRM, update your mailing list, etc.
}, 10, 2 );
```

## Project structure

```
ecocash-donations/
├── assets/
│   ├── css/ecocash-donations.css
│   └── js/ecocash-donations.js
├── includes/
│   ├── class-ecocash-api.php         # EcoCash Open API client
│   ├── class-ecocash-donations.php   # CPT, shortcode, AJAX, status polling
│   ├── class-ecocash-admin.php       # Settings page, list columns, metabox
│   └── class-ecocash-emails.php      # Donor receipts + admin notifications
├── ecocash-donations.php             # Plugin bootstrap
├── readme.txt                        # WordPress.org readme
└── uninstall.php
```

## Notes

- The EcoCash C2B API is synchronous: when the donor approves the prompt, the payment is confirmed and the plugin records it via the status lookup.
- A donation that stays **pending** longer than ~90 seconds in the browser will still be verified on the next status poll; you can also check status manually from the donations list.
- Refunds: use the `Ecocash_API::refund()` method (see `includes/class-ecocash-api.php`) or process refunds from the EcoCash merchant portal.

## License

GPL-2.0-or-later
