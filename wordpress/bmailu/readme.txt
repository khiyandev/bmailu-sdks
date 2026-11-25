=== Bmailu ===
Contributors: khiyandev
Tags: email, smtp, wp_mail, transactional email, email api
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send all WordPress email through the Bmailu Email API, signed with your own domain.

== Description ==

Bmailu replaces the way WordPress sends email. Every message that WordPress, your theme or another plugin sends with `wp_mail()` goes to the Bmailu Email API instead of the server's `mail()` function. Your email is signed (DKIM, SPF, DMARC) with your own verified domain, so it is far less likely to land in spam.

Features:

* Works with every plugin that uses `wp_mail()`: contact forms, WooCommerce, password resets, notifications.
* Plain text and HTML email, To, Cc, Bcc, Reply-To, custom headers and attachments.
* Set the From email and From name, or let other plugins change them with the usual `wp_mail_from` and `wp_mail_from_name` filters.
* Keep the API key out of the database with the `BMAILU_API_KEY` constant in `wp-config.php`.
* Optional fallback to the normal WordPress mail when the API cannot be reached.
* A "Send test email" button that shows the API's answer.
* Failed sends fire the standard `wp_mail_failed` action, so logging plugins keep working.

You need a Bmailu account with a verified sending domain and an API key.

= External service =

This plugin connects to the Bmailu Email API at `https://app.bmailu.com/api/v1`. Each time WordPress sends an email, the plugin sends that email (sender, recipients, subject, body, headers and attachments) and your API key to the API, which delivers it. Nothing is sent when no API key is set. The plugin sends nothing else and does no tracking.

* Service: [bmailu.com](https://bmailu.com)
* Terms of service: [https://bmailu.com/legal/terms.html](https://bmailu.com/legal/terms.html)
* Privacy policy: [https://bmailu.com/legal/privacy.html](https://bmailu.com/legal/privacy.html)

== Installation ==

1. Install the plugin from Plugins → Add New, or upload the `bmailu` folder to `/wp-content/plugins/`.
2. Activate it.
3. In your Bmailu account, verify a sending domain and create an API key on the Developers page.
4. Go to Settings → Bmailu. Paste the API key, and set the From email (an address on your verified domain) and the From name.
5. Click "Send test email".

To keep the key out of the database, add this line to `wp-config.php` instead of saving it on the settings page:

`define( 'BMAILU_API_KEY', 'bmk_your_key' );`

== Frequently Asked Questions ==

= Does it send my data elsewhere? =

Yes, that is how it works: every email WordPress sends goes to the Bmailu API at app.bmailu.com, which delivers it. The request holds the email itself (sender, recipients, subject, body, headers, attachments) and your API key. Bmailu keeps a log of sent emails in your account. See the [privacy policy](https://bmailu.com/legal/privacy.html) and [terms](https://bmailu.com/legal/terms.html). The plugin contacts no other service.

= What happens when the API fails? =

By default `wp_mail()` returns false and the `wp_mail_failed` action fires with the API's error message. If you tick "Fall back to the normal WordPress mail", WordPress sends the email the normal way instead.

= Which From address can I use? =

An address on a domain you verified in Bmailu. Other domains are refused with "not a verified sending domain".

= Is there a limit on recipients? =

Yes: at most 50 recipients (To + Cc + Bcc together), 10 Reply-To addresses and 20 attachments per email. Larger emails fail with an error instead of being sent.

= Can I point the plugin at another API address? =

Yes, with the `bmailu_api_base_url` filter, for example for testing:

`add_filter( 'bmailu_api_base_url', function () { return 'http://127.0.0.1:8080/api/v1'; } );`

== Changelog ==

= 0.1.0 =
* First release: sends `wp_mail()` through the Bmailu Email API, settings page, test email, fallback option.
