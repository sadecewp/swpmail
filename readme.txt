=== SWPMail ===
Contributors:      yourwpusername
Tags:              smtp, email, newsletter, mailgun, sendgrid, postmark, brevo, ses, resend
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Professional email delivery, subscriptions and notifications for WordPress — 7 providers, wp_mail override, HTML templates.

== Description ==
SWPMail replaces WordPress's default mail system with a robust, provider-based delivery engine supporting:

* **7 Mail Providers:** Generic SMTP, Mailgun, SendGrid, Postmark, Brevo, Amazon SES, Resend
* **Global wp_mail Override:** All WordPress emails (including WooCommerce, CF7, etc.) go through your chosen provider
* **Subscription System:** Instant, daily digest, weekly digest with double opt-in
* **HTML Templates:** Fully customizable per-template with theme override support
* **Trigger System:** New post, user registration, login, comment, password reset — plus custom triggers
* **GDPR Compliant:** Double opt-in, unsubscribe links, WordPress Privacy API integration
* **Multilingual:** English and Turkish included (TR/EN)

== Installation ==
1. Upload to `/wp-content/plugins/swpmail`
2. Activate via Plugins menu
3. Go to **SWPMail > Mail Settings**, choose your provider, enter credentials
4. Click **Send Test Email** to verify
5. Add `[swpmail_subscribe]` to any page

== Frequently Asked Questions ==

= Will it affect other plugins' emails? =
Yes, intentionally. Once configured, all wp_mail() calls site-wide use your chosen provider.

= Is it GDPR compliant? =
Yes. Double opt-in is on by default, every email includes an unsubscribe link, and it integrates with WordPress's Privacy API.

= Can I build custom triggers? =
Yes. Use the `swpm_register_triggers` action hook to add your own triggers.

== Changelog ==
= 1.0.0 =
* Initial release.
