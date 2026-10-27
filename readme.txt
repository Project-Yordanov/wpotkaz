=== OtkazPro – Withdrawal Button for WooCommerce ===
Contributors: projectyordanov
Tags: woocommerce, withdrawal, right of withdrawal, returns, gdpr
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The online “withdraw from the contract” button and form required by EU Directive 2023/2673 – for WooCommerce stores in Bulgaria and the EU.

== Description ==

Since 2026, online shops in the EU must let consumers withdraw from a distance contract through an easily accessible online function (EU Directive 2023/2673; in Bulgaria Art. 52a of the Consumer Protection Act, in force since 24 September 2026). OtkazPro adds exactly that to WooCommerce – without forcing the customer to log in or give a reason.

**For the customer – two clicks**

* A clearly labelled **“Withdraw from the contract here”** button: in order emails, in My account → Orders, and on a dedicated page you can link from the footer.
* Guests find their order with the order number + email; links from emails open the order directly.
* All eligible products are pre-selected; the customer can untick products or lower quantities (**partial withdrawal**), then clicks **“Confirm withdrawal”**.
* A confirmation with the statement number, date, time, products and the full text of the statement is emailed immediately (durable medium).

**For the shop**

* Register of all statements (WooCommerce → Withdrawals) with filters, CSV export, IP and user agent as evidence, and “Resend confirmation”.
* **Refund deadline tracking:** every statement shows how many days are left of the 14-day refund deadline. A daily email reminds the store of withdrawals whose deadline is near or past, and a withdrawal is marked as processed automatically when you refund the order.
* **“Prepare refund”** opens the order with the WooCommerce refund form already filled in with the products and quantities of the statement – you check the amount and confirm the refund.
* Order note, order meta box, badge in the orders list and an indicative “in time / late” marker (the form itself never closes).
* Store notification email with a link to the order.
* Products, variations or categories can be marked as **excluded from the right of withdrawal** (e.g. personalised or perishable goods) – soft mode (selectable with a warning) or strict mode. Optional storefront label in product, cart and checkout pages (classic and block checkout).
* Optional custom order status “Withdrawal requested”.

**Built to keep working**

* Works with any theme (shortcode `[otkazpro]`), page builders such as Elementor, classic and block themes.
* If the form page is missing or unpublished, links automatically fall back to a built-in page, so the function never disappears.
* Sends “do not cache” signals to page caches and CDNs and **checks itself** whether the form page is cached (WooCommerce settings and Tools → Site Health).
* Adds the withdrawal link to order emails even when an email template plugin replaces the WooCommerce templates.
* HPOS compatible, WPML / Polylang ready, accessible (keyboard, screen readers), works without JavaScript.
* GDPR: personal data exporter, retention period with automatic deletion, privacy policy text suggestion.

**Languages:** English. Translations, including Bulgarian, are delivered automatically through translate.wordpress.org.

This plugin does not provide legal advice. Your terms and conditions and the information on the right of withdrawal remain your responsibility.

== Installation ==

1. Install and activate the plugin (WooCommerce 8.0 or newer is required).
2. A page “Withdrawal from contract” with the `[otkazpro]` shortcode is created automatically. If a page with that address already exists, you are asked whether to use it or create a new one – nothing is overwritten.
3. Go to **WooCommerce → Settings → Withdrawal** and set the store notification email, accent colour and return instructions.
4. Add a footer / menu link with the text “Withdraw from the contract here” pointing to the form page (the URL is shown in the settings).
5. Exclude the form page from your page cache and run **“Check cache now”** in the settings.
6. Mark products or categories that are excluded from the right of withdrawal (Product → Advanced, or the category edit screen).

== Frequently Asked Questions ==

= Does the customer need an account? =

No. Guests find the order with its number and the email used for the order, or open the direct link from the order email.

= Does the plugin cancel the order or refund the payment? =

No. It records the statement, notifies you and confirms it to the customer. Refunds and returns are processed manually by the shop.

= Does the form close after 14 days? =

No. The function is always available for eligible orders; the indicative deadline is shown only to the shop in the admin.

= Is a reason required? =

No. An optional, never mandatory reason field can be enabled in the settings.

= Is it compatible with my cache plugin? =

The plugin tells page caches not to cache the form page (DONOTCACHEPAGE, Cache-Control, CDN headers, LiteSpeed, FlyingPress, WP Rocket) and the form keeps working even if a cache ignores this. The built-in cache check tells you if the page is still cached and what to exclude.

= Can I change the texts? =

Yes – all texts (buttons, statement template, return instructions) are editable in the settings and translatable with WPML / Polylang. Templates can be overridden in your theme under `woocommerce/otkazpro/`.

= What data is stored? =

Name, email, order, selected products, statement text, date/time, IP address and user agent – as evidence of the statement. Records are deleted automatically after the configured retention period (default 5 years).

= Does the plugin connect to external services? =

No. All data stays in your WordPress database and nothing is sent to third parties. The only HTTP requests the plugin makes are to your own site: the cache check loads your withdrawal form page to verify that it is not served from a page cache.

== Screenshots ==

1. Step 2 – the customer selects products and confirms the withdrawal.
2. Success screen with statement number, date and time.
3. Register of withdrawal statements in the admin.
4. Settings.

== Changelog ==

= 2.1.0 =
* New: 14-day refund deadline for every withdrawal (days left / overdue) in the register, the statement view and the order.
* New: daily “Withdrawal refund reminder” email to the store for withdrawals whose refund deadline is near or past (WooCommerce → Settings → Emails).
* New: withdrawals are marked as processed automatically when a refund is created in the order; “Mark as processed” / “Reopen” for the rest.
* New: “Prepare refund” fills in the WooCommerce refund form with the products and quantities of the statement.
* New: refund status filter and CSV columns.

= 2.0.0 =
* Published on WordPress.org as OtkazPro (previously distributed as WPOtkaz). Activating OtkazPro deactivates the previous edition and keeps all settings and records.
* Brand-consistent URL parameters, CSS classes and form fields (`otkazpro_*`); old links from emails keep working.
* Fallback page when the form page is missing or unpublished.
* Cache self-check and additional “do not cache” headers.

= 1.3.0 =
* Fixed links in My account when another plugin imitates WPML.

== Upgrade Notice ==

= 2.1.0 =
Adds refund deadline tracking, a store reminder email and “Prepare refund”. Existing withdrawals of orders that were already refunded are marked as processed.

= 2.0.0 =
If you used WPOtkaz from GitHub: install and activate OtkazPro, then delete WPOtkaz – settings and records are kept.
