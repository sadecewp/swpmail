# SWPMail — WordPress Email Sending & Subscription Plugin

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)](#)

SWPMail is a fully customizable **email subscription, notification, and sending infrastructure plugin** that integrates with WordPress sites, supports **19 professional mail services**, applies the configured SMTP/API setting to **all WordPress emails** sent via `wp_mail()`, and features enterprise-grade capabilities such as smart routing, failover, OAuth 2.0, email tracking & analytics, alarm system, and DNS verification.

---

## Table of Contents

- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Quick Start (Setup Wizard)](#quick-start-setup-wizard)
- [Supported Mail Services (18 Providers)](#supported-mail-services-18-providers)
- [wp_mail Override System](#wp_mail-override-system)
- [Smart Routing](#smart-routing)
- [Connection Management & Failover](#connection-management--failover)
- [OAuth 2.0 Integration](#oauth-20-integration)
- [Subscription System](#subscription-system)
- [Shortcode Usage](#shortcode-usage)
- [HTML Email Templates](#html-email-templates)
- [Trigger System](#trigger-system)
- [Queue & Cron System](#queue--cron-system)
- [Email Tracking & Analytics](#email-tracking--analytics)
- [Alarm System](#alarm-system)
- [DNS Checker (SPF / DKIM / DMARC)](#dns-checker-spf--dkim--dmarc)
- [REST API](#rest-api)
- [WP-CLI Commands](#wp-cli-commands)
- [Admin Panel](#admin-panel)
- [Security](#security)
- [GDPR & Legal Compliance](#gdpr--legal-compliance)
- [Multilingual Support (i18n)](#multilingual-support-i18n)
- [Theme Developer Guide](#theme-developer-guide)
- [Hooks Reference (Developer API)](#hooks-reference-developer-api)
- [File Structure](#file-structure)
- [Database Design](#database-design)
- [Diagnostics & Repair Tools](#diagnostics--repair-tools)
- [Testing](#testing)
- [FAQ (Frequently Asked Questions)](#faq-frequently-asked-questions)
- [License](#license)

---

## Features

### Sending Infrastructure

- **18 Mail Service Providers:** PHP Mail, Generic SMTP, SendLayer, SMTP.com, Gmail, Outlook, Mailgun, SendGrid, Postmark, Brevo, Amazon SES, Resend, Elastic Email, Mailjet, MailerSend, SMTP2GO, SparkPost, Zoho Mail
- **Global wp_mail Override:** All WordPress emails (WooCommerce, Contact Form 7, password reset, etc.) are sent through the selected provider
- **Smart Routing:** Rule-based conditional email routing — different providers can be used based on criteria such as recipient, subject, source
- **Failover & Health Check:** Automatic fallback to backup provider when the primary provider fails, with health check mechanism
- **OAuth 2.0:** Full OAuth 2.0 flow for Gmail and Outlook, with automatic token refresh

### Subscription & Notification

- **Subscription System:** Instant, daily digest, and weekly digest options
- **Double Opt-in:** Double confirmation mechanism enabled by default
- **5 Built-in Triggers:** New post, new user, user login, new comment, password reset
- **Extensible Trigger System:** Custom trigger support for third-party plugins
- **Queue System:** WP-Cron based asynchronous email queue with retry mechanism

### Templates & Content

- **HTML Mail Templates:** Fully customizable responsive email templates with theme override support
- **Template Editor:** CodeMirror-based HTML template editing in the admin panel
- **Variable System:** Dynamic variables like `{{post_title}}`, `{{site_name}}` in templates

### Monitoring & Analytics

- **Email Tracking:** Open (open pixel) and click (click redirect) tracking
- **Analytics Dashboard:** Total/unique opens, total/unique clicks, rate calculations
- **Alarm System:** Instant error notifications via Slack, Discord, Microsoft Teams, Twilio SMS, and custom webhooks
- **DNS Checker:** Automatic verification of SPF, DKIM, DMARC records

### Developer & Management

- **REST API:** Programmatic subscriber management
- **WP-CLI:** Full management via terminal with 16+ commands
- **Setup Wizard:** Step-by-step configuration on first activation
- **Conflict Detector:** Detection of 18+ known conflicting plugins
- **Database Repair:** Table/column/index deficiency diagnostics and automatic repair
- **Email Logs:** Detailed sending records and filtering

### Security & Compliance

- **AES-256-CBC Encryption:** API keys are stored encrypted in the database with HMAC-SHA256 verification
- **Rate Limiting:** IP-based request limiting and bot protection (honeypot)
- **GDPR Compliant:** WordPress Privacy API integration, unsubscribe links
- **Multilingual:** Compliant with WordPress i18n standards, Turkish and English language support
- **Zero External Dependencies:** No external libraries required; uses WordPress HTTP API

---

## System Requirements

| Requirement | Minimum | Recommended |
| ----------- | ------- | ----------- |
| PHP         | 7.4     | 8.1+        |
| WordPress   | 6.0     | 6.7+        |
| MySQL       | 5.7     | 8.0+        |
| MariaDB     | 10.3    | 10.6+       |
| cURL        | Enabled | Enabled     |
| OpenSSL     | Enabled | Enabled     |

---

## Installation

### Via WordPress Admin Panel

1. Go to **Plugins > Add New**
2. Upload the `swpmail` folder as a ZIP file
3. Click the **Activate** button

### Manual Installation

1. Upload the `swpmail` folder to the `/wp-content/plugins/` directory:
   ```bash
   # Extract the zip file
   unzip swpmail.zip -d /path/to/wp-content/plugins/
   ```
2. Activate it from the **Plugins** page in the WordPress admin panel

### After Activation

When the plugin is activated, it automatically:

- Creates 4 database tables (`swpm_subscribers`, `swpm_queue`, `swpm_logs`, `swpm_tracking`)
- Saves default settings
- Schedules WP-Cron tasks (6 tasks)
- Sets up the setup wizard redirect (`swpm_activation_redirect` transient)

---

## Quick Start (Setup Wizard)

When the plugin is activated for the first time, the **Setup Wizard** opens automatically and guides you through the configuration process step by step.

### Wizard Steps

1. **Provider Selection** — Choose your mail provider from 19 services
2. **Credentials** — Enter API key or SMTP credentials
3. **Test Send** — Send a test email to verify the connection
4. **Complete** — `swpm_setup_complete` is marked, and you are redirected to the dashboard

### Manual Configuration

If you skipped the wizard or want to change settings later:

1. Go to the **SWPMail > Mail Settings** page
2. Select your mail service provider (e.g., SMTP, Mailgun, SendGrid)
3. Enter the required information (API key, host, port, etc.)
4. Click the **Send Test Email** button to verify the connection
5. Add the `[swpmail_subscribe]` shortcode to any page on your site
6. Choose which events will send emails from the **SWPMail > Triggers** page

---

## Supported Mail Services (18 Providers)

### SMTP-Based Providers

| #   | Service          | Method           | Description                               |
| --- | ---------------- | ---------------- | ----------------------------------------- |
| 1   | **PHP Mail**     | PHP `mail()`     | Server default, no configuration required |
| 2   | **Generic SMTP** | PHPMailer / SMTP | Any SMTP server                           |
| 3   | **Gmail**        | SMTP / OAuth 2.0 | Google account, App Password or OAuth     |
| 4   | **Outlook**      | SMTP / OAuth 2.0 | Microsoft 365 / Outlook.com               |
| 5   | **Zoho Mail**    | SMTP             | Zoho Mail account                         |

### HTTP API-Based Providers

| #   | Service                | Method              | Free Quota              |
| --- | ---------------------- | ------------------- | ----------------------- |
| 6   | **Mailgun**            | HTTP API            | 100/day                 |
| 7   | **SendGrid**           | HTTP API            | 100/day (60-day trial)  |
| 8   | **Postmark**           | HTTP API            | 100/month (test)        |
| 9   | **Brevo (Sendinblue)** | HTTP API            | 300/day                 |
| 10  | **Amazon SES**         | HTTP API (SDK-less) | 62,000/month (EC2)      |
| 11  | **Resend**             | HTTP API            | 3,000/month             |
| 12  | **Elastic Email**      | HTTP API            | 100/day                 |
| 13  | **Mailjet**            | HTTP API            | 200/day                 |
| 14  | **MailerSend**         | HTTP API            | 3,000/month             |
| 15  | **SendLayer**          | HTTP API            | 200/month (trial)       |
| 16  | **SMTP.com**           | HTTP API            | Plan-dependent          |
| 17  | **SMTP2GO**            | HTTP API            | 1,000/month             |
| 18  | **SparkPost**          | HTTP API            | 500/month (test)        |

### Provider Configuration

Required information for each provider:

- **PHP Mail:** No configuration required (server default)
- **Generic SMTP:** Host, Port, Username, Password, Encryption (TLS/SSL/None)
- **Gmail:** Email, App Password or OAuth 2.0 (Client ID + Secret)
- **Outlook:** Email, Password or OAuth 2.0 (Client ID + Secret + Tenant)
- **Zoho Mail:** Email, App Password
- **Mailgun:** API Key, Domain, Region (US/EU)
- **SendGrid:** API Key
- **Postmark:** Server Token, Message Stream
- **Brevo:** API Key
- **Amazon SES:** Access Key, Secret Key, Region
- **Resend:** API Key
- **Elastic Email:** API Key
- **Mailjet:** API Key, Secret Key
- **MailerSend:** API Token
- **SendLayer:** API Key
- **SMTP.com:** API Key, Sender ID
- **SMTP2GO:** API Key
- **SparkPost:** API Key
- **Zoho Mail:** Email, App Password

> **Note:** All API keys and passwords are encrypted with AES-256-CBC + HMAC-SHA256 and stored in the database. WordPress salt keys are used as the encryption key.

---

## wp_mail Override System

When the plugin is active and a provider is configured, **all `wp_mail()` calls in the system** are automatically sent through the selected provider.

### How It Works

- **SMTP Providers:** PHPMailer is configured via the `phpmailer_init` hook
- **API Providers:** Sending is fully taken over via the `pre_wp_mail` filter

### From Address Priority Order

```
1. From Email / Name in SWPMail settings   (highest priority)
2. wp_mail_from / wp_mail_from_name filters
3. WordPress default (wordpress@domain.com)
```

### Disabling the Override

```php
// Skip the override for a specific wp_mail call
add_filter( 'swpm_skip_override', '__return_true' );
wp_mail( $to, $subject, $body );
remove_filter( 'swpm_skip_override', '__return_true' );
```

Or disable the **SWPMail > Settings > Override wp_mail** option from the admin panel.

---

## Smart Routing

SWPMail allows you to send different emails through different providers using the conditional email routing engine.

### Rule Structure

Each routing rule consists of the following components:

| Component    | Description                                                   |
| ------------ | ------------------------------------------------------------- |
| **Name**     | Descriptive name for the rule                                 |
| **Priority** | Lower number = evaluated first                                |
| **Provider** | Which provider to use for emails matching this rule           |
| **Conditions** | Matching criteria (AND logic)                               |

### Condition Fields

| Field     | Description                                   |
| --------- | --------------------------------------------- |
| `to`      | Recipient email address                       |
| `subject` | Email subject                                 |
| `from`    | Sender address                                |
| `header`  | Any header value                              |
| `source`  | Source that triggered the email (plugin, etc.) |

### Condition Operators

`contains`, `not_contains`, `equals`, `not_equals`, `starts_with`, `ends_with`, `matches` (regex)

### Example Scenarios

```
Rule: "WooCommerce Orders via Postmark"
  Condition: subject contains "Order"
  Provider: postmark
  Priority: 10

Rule: "Bulk Mail via SendGrid"
  Condition: source equals "swpmail_trigger"
  Provider: sendgrid
  Priority: 20
```

Emails that don't match any rule are sent through the default (primary) provider.

Rules are stored in JSON format in the `swpm_routing_rules` option and managed from the **SWPMail > Smart Routing** page.

---

## Connection Management & Failover

SWPMail provides an automatic failover mechanism to prevent service interruptions when the primary provider goes down.

### How It Works

```
Email Sending
     ↓
Primary Provider → Successful? → Sent ✓
     ↓ (Failed)
Failure Counter +1
     ↓
Threshold Exceeded? (3 consecutive errors)
  YES → Switch to Backup Provider → Send
  NO  → Retry
     ↓
Health Check after 5 minutes → Return to primary if healthy
```

### Features

- **Automatic Failover:** Backup provider activates after 3 consecutive failures
- **Health Check:** Primary provider status monitored via lightweight test sends
- **Cooldown Period:** Health checks performed at 5-minute intervals
- **AJAX Status:** `swpm_health_check`, `swpm_get_connection_status` endpoints

---

## OAuth 2.0 Integration

OAuth 2.0 authorization flow is supported for Gmail and Outlook providers.

### Supported Services

| Service     | Auth Endpoint                                       | Token Endpoint                                       |
| ----------- | --------------------------------------------------- | ---------------------------------------------------- |
| **Gmail**   | `accounts.google.com/o/oauth2/v2/auth`              | `oauth2.googleapis.com/token`                        |
| **Outlook** | `login.microsoftonline.com/common/oauth2/v2.0/auth` | `login.microsoftonline.com/common/oauth2/v2.0/token` |

### OAuth Flow

1. Click the **Authorize** button in the admin panel
2. Sign in with your Google/Microsoft account and grant permissions
3. Token is received via the callback URL and stored encrypted
4. Automatic refresh when the token expires

### AJAX Endpoints

- `swpm_oauth_start` — Start OAuth flow
- `swpm_oauth_callback` — Callback processing
- `swpm_oauth_disconnect` — Disconnect OAuth

> **Note:** If OAuth is not configured, SMTP connection with App Password is used for Gmail and standard password for Outlook.

---

## Subscription System

### Subscription Flow

```
Form Submission → AJAX (nonce + rate limit + honeypot + GDPR)
     ↓
Double Opt-in Enabled?
  YES → status: pending → Confirmation email → Token verification → confirmed
  NO  → status: confirmed → Welcome email
     ↓
Frequency: instant / daily / weekly
```

### Subscription Statuses

| Status         | Description                      |
| -------------- | -------------------------------- |
| `pending`      | Awaiting confirmation (double opt-in) |
| `confirmed`    | Active subscriber                |
| `unsubscribed` | Unsubscribed                     |
| `bounced`      | Email bounced                    |

### Subscription Frequencies

| Frequency | Description                                |
| --------- | ------------------------------------------ |
| `instant` | Immediately (on every new post)            |
| `daily`   | Daily digest (configurable hour)           |
| `weekly`  | Weekly digest (configurable day)           |

---

## Shortcode Usage

### Basic Usage

```
[swpmail_subscribe]
```

### All Parameters

| Parameter           | Default     | Description                                  |
| ------------------- | ----------- | -------------------------------------------- |
| `title`             | From settings | Form title                                 |
| `show_name`         | `false`     | Show name field                              |
| `frequency`         | From settings | Show frequency selection (`true`/`false`)  |
| `frequency_default` | `instant`   | Default frequency                            |
| `style`             | `default`   | Form style (`default`/`minimal`)             |
| `button_text`       | "Subscribe" | Button text                                  |

### Examples

```
<!-- With name field -->
[swpmail_subscribe show_name="true" title="Subscribe to Newsletter"]

<!-- With specific frequency -->
[swpmail_subscribe frequency_default="weekly" frequency="false"]

<!-- Minimal style -->
[swpmail_subscribe style="minimal" button_text="Join"]
```

---

## HTML Email Templates

### Built-in Templates

| Template ID            | Usage                          | Variables                                                                                     |
| ---------------------- | ------------------------------ | --------------------------------------------------------------------------------------------- |
| `base`                 | Base layout for all emails     | `{{content}}`                                                                                 |
| `confirm-subscription` | Double opt-in email            | `{{confirm_url}}`, `{{subscriber_name}}`                                                      |
| `welcome`              | Welcome email                  | `{{subscriber_name}}`, `{{site_name}}`                                                        |
| `new-post`             | New post notification          | `{{post_title}}`, `{{post_url}}`, `{{post_excerpt}}`, `{{post_thumbnail}}`, `{{author_name}}` |
| `digest-daily`         | Daily digest                   | `{{post_list}}`, `{{date}}`                                                                   |
| `digest-weekly`        | Weekly digest                  | `{{post_list}}`, `{{week_start}}`, `{{week_end}}`                                             |

### Global Variables (Available in All Templates)

| Variable               | Description              |
| ---------------------- | ------------------------ |
| `{{site_name}}`        | Site name                |
| `{{site_url}}`         | Site URL                 |
| `{{site_logo}}`        | Site logo URL            |
| `{{year}}`             | Current year             |
| `{{from_name}}`        | Sender name              |
| `{{privacy_url}}`      | Privacy policy URL       |
| `{{unsubscribe_text}}` | "Unsubscribe" text       |
| `{{visit_site_text}}`  | "Visit Site" text        |

### Template Loading Priority

```
1. swpm_template_path filter          (highest)
2. Theme: /swpmail/templates/{id}.html
3. Database (saved via admin editor)
4. Plugin: /templates/default/{id}.html (lowest)
```

### Theme Override

You can override templates in your theme folder:

```
your-theme/
└── swpmail/
    └── templates/
        ├── new-post.html
        └── welcome.html
```

---

## Trigger System

### Built-in Triggers

| Trigger              | WordPress Hook           | Template         |
| -------------------- | ------------------------ | ---------------- |
| New Post Published   | `transition_post_status` | `new-post`       |
| New User Registered  | `user_register`          | `new-user`       |
| User Login           | `wp_login`               | `user-login`     |
| New Comment          | `comment_post`           | `new-comment`    |
| Password Reset       | `retrieve_password`      | `password-reset` |

### Creating Custom Triggers

Third-party plugins or theme developers can add their own triggers:

```php
class My_Custom_Trigger extends SWPM_Trigger_Base {

    public function get_key():         string { return 'my_custom_event'; }
    public function get_label():       string { return 'My Custom Event'; }
    public function get_hook():        string { return 'my_plugin_event_fired'; }
    public function get_template_id(): string { return 'my-custom-template'; }

    public function get_subject( array $data ): string {
        return 'Custom event: ' . $data['title'];
    }

    public function get_recipients( array $data ): array {
        return swpm( 'subscriber' )->get_confirmed_by_frequency( 'instant' );
    }

    protected function prepare_data( ...$args ): array {
        return [
            'title'   => $args[0],
            'content' => $args[1] ?? '',
        ];
    }
}

// Register the trigger
add_action( 'swpm_register_triggers', function( SWPM_Trigger_Manager $manager ) {
    $manager->register( new My_Custom_Trigger() );
} );
```

### Trigger Control Filters

```php
// Conditionally stop a specific trigger
add_filter( 'swpm_trigger_should_send_new_post', function( $proceed, $data ) {
    // Skip posts published from draft (custom logic)
    if ( $data['post_id'] && get_post_meta( $data['post_id'], '_skip_notification', true ) ) {
        return false;
    }
    return $proceed;
}, 10, 2 );

// Extend triggerable post types
add_filter( 'swpm_trigger_new_post_types', function( $types ) {
    $types[] = 'product';  // Also trigger for WooCommerce products
    return $types;
} );
```

---

## Queue & Cron System

### Queue Operation

- Emails are added to the `swpm_queue` table
- Processed by WP-Cron **every 5 minutes**
- Failed sends are retried with a **maximum of 3 attempts**
- Items stuck in "sending" status for more than 10 minutes are automatically reset

### Scheduled Tasks

| Task                      | Interval | Description                                       |
| ------------------------- | -------- | ------------------------------------------------- |
| `swpm_process_queue`      | 5 min    | Queue processing (batch: 50 items)                |
| `swpm_send_daily_digest`  | Daily    | Daily digest sending (default: 09:00)             |
| `swpm_send_weekly_digest` | Weekly   | Weekly digest sending (default: Monday)           |
| `swpm_cleanup_logs`       | Daily    | Clean up old log records                          |
| `swpm_cleanup_queue`      | Daily    | Clean up sent/failed queue items                  |
| `swpm_cleanup_tracking`   | Daily    | Clean up old tracking data                        |

### Configuration Options

- `swpm_daily_send_hour` — Hour for daily digest sending (default: 9)
- `swpm_weekly_send_day` — Day for weekly digest sending (default: monday)
- Cron tasks are automatically rescheduled when settings change

### Queue Settings

```php
// Customize batch size (for shared hosting)
add_filter( 'swpm_queue_batch_size', fn() => 10 );
```

---

## REST API

Namespace: `swpmail/v1`

### Endpoints

| Method   | Endpoint                       | Auth         | Description              |
| -------- | ------------------------------ | ------------ | ------------------------ |
| `POST`   | `/swpmail/v1/subscribe`        | Public       | Create new subscriber    |
| `GET`    | `/swpmail/v1/subscribers`      | Admin        | Subscriber list (paged)  |
| `DELETE` | `/swpmail/v1/subscribers/{id}` | Admin        | Delete subscriber        |

### Usage Examples

**New Subscriber:**

```bash
curl -X POST https://site.com/wp-json/swpmail/v1/subscribe \
  -H "Content-Type: application/json" \
  -d '{"email": "example@email.com", "name": "Name", "frequency": "instant"}'
```

**Subscriber List (Admin):**

```bash
curl https://site.com/wp-json/swpmail/v1/subscribers \
  -H "X-WP-Nonce: {nonce}" \
  --cookie "wordpress_logged_in_xxx=..."
```

### Response Formats

**Successful Subscription (201):**

```json
{
  "message": "Subscription successful.",
  "id": 42
}
```

**Error (422):**

```json
{
  "message": "This email is already subscribed."
}
```

---

## Email Tracking & Analytics

### Email Tracking System

SWPMail automatically adds tracking mechanisms to sent HTML emails:

| Feature            | Method               | Description                                             |
| ------------------ | -------------------- | ------------------------------------------------------- |
| **Open Tracking**  | 1×1 invisible pixel  | Sends request to `swpm_open` endpoint when email opened |
| **Click Tracking** | Link proxy redirect  | Links are rewritten through `swpm_click`                |

- Tracking data is stored in the `swpm_tracking` table with hash, queue_id, IP, and user agent information
- Rewrite rules are registered in the `init` hook (priority 5)

### Analytics Dashboard

Metrics displayed on the **SWPMail > Dashboard** page:

- **Total Opens** / **Unique Opens** counts
- **Total Clicks** / **Unique Clicks** counts
- **Open Rate** and **Click Rate**
- 30-day retrospective analysis (configurable)

---

## Alarm System

5 different alarm channels are supported to receive instant notifications when sending errors occur.

### Alarm Channels

| Channel             | Key       | Method    | Configuration                        |
| ------------------- | --------- | --------- | ------------------------------------ |
| **Slack**           | `slack`   | Webhook   | Incoming Webhook URL                 |
| **Discord**         | `discord` | Webhook   | Discord Webhook URL                  |
| **Microsoft Teams** | `teams`   | Webhook   | Teams Incoming Webhook URL           |
| **Twilio SMS**      | `twilio`  | REST API  | Account SID, Auth Token, Phone       |
| **Custom**          | `custom`  | HTTP POST | Target URL, Payload format           |

### Triggering

- Notifications are sent to active alarm channels when the `swpm_mail_failed` hook fires
- Each channel is configured and can be tested from the **SWPMail > Alarms** page
- AJAX endpoint: `swpm_test_alarm_channel`

---

## DNS Checker (SPF / DKIM / DMARC)

Verifying domain DNS records is critical for improving email deliverability.

### Verified Records

| Record    | Description                                                          |
| --------- | -------------------------------------------------------------------- |
| **SPF**   | Specifies which servers can send mail on behalf of the domain        |
| **DKIM**  | Ensures the authenticity of the email signature                      |
| **DMARC** | Defines what to do when SPF/DKIM fails                               |

### Features

- Verification using the `dns_get_record()` PHP function
- Automatically runs when mail settings are saved
- 15-minute transient caching (`swpm_dns_` + md5(domain))
- Analysis based on deliverability best practices
- Accessible from the **SWPMail > DNS Checker** page

---

## WP-CLI Commands

SWPMail offers comprehensive CLI commands under the `wp swpmail` namespace.

### General Commands

```bash
# General status and health report
wp swpmail status

# Send test email
wp swpmail test --to=example@email.com

# Reset all plugin settings
wp swpmail reset --yes
```

### Provider Management

```bash
# Active provider info
wp swpmail provider

# List available providers
wp swpmail provider list

# Switch provider
wp swpmail provider switch sendgrid
```

### Queue Management

```bash
# List queue items
wp swpmail queue list --status=pending --limit=20

# Process queue
wp swpmail queue process --limit=50

# Flush sent/failed items
wp swpmail queue flush --status=sent --older-than="7 days"
```

### Logs & Diagnostics

```bash
# Show recent log records
wp swpmail log --level=error --limit=50

# Database diagnostics
wp swpmail db diagnose

# Database repair (dry run)
wp swpmail db repair --dry-run

# Conflict detection
wp swpmail conflicts
```

### Cron & Subscriber Management

```bash
# List scheduled cron events
wp swpmail cron list

# Manually trigger cron event
wp swpmail cron run swpm_process_queue

# List subscribers
wp swpmail subscriber list --status=confirmed --limit=100
```

---

## Admin Panel

### Menu Structure

```
SWPMail (dashicon: email)
├── Dashboard        → Statistics, analytics, queue status, recent logs
├── Subscribers      → Subscriber management with WP_List_Table (search/filter/delete)
├── Email Templates  → CodeMirror-based HTML template editor
├── Triggers         → Enable/disable triggers, create custom triggers
├── Mail Settings    → 18 provider selection and configuration, test sending
├── DNS Checker      → SPF, DKIM, DMARC record verification
├── Smart Routing    → Rule-based conditional email routing
├── Email Logs       → Detailed sending records, filtering
├── Alarms           → Slack, Discord, Teams, Twilio, Custom alarm configuration
├── Settings         → General settings, GDPR, subscription, override settings
└── Tools            → Database repair, conflict detection, system info
```

### Dashboard

- Total / confirmed / pending / unsubscribed subscriber counts
- Active provider info and failover status
- Queue status (pending/sent/failed)
- Open / Click analytics metrics
- Last cron execution time
- DNS verification status
- Last 10 log records

### Subscriber Management

- **Search:** By email or name
- **Filter:** Status-based (pending, confirmed, unsubscribed, bounced)
- **Sort:** Email, status, registration date
- **Bulk Action:** Bulk delete selected subscribers
- **Single Delete:** Via row action

### Template Editor

- CodeMirror HTML editor
- Available variables list
- Save / Reset (restore to default)
- AJAX-based saving

---

## Security

| Topic                 | Method                                                             |
| --------------------- | ------------------------------------------------------------------ |
| CSRF                  | `wp_create_nonce()` + `check_ajax_referer()` / `wp_verify_nonce()` |
| XSS                   | `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`          |
| SQL Injection         | `$wpdb->prepare()` — in all queries                                |
| Direct File Access    | `if ( ! defined( 'ABSPATH' ) ) exit;` in every PHP file            |
| Permission Check      | `current_user_can( 'manage_options' )` in all admin operations     |
| Rate Limiting         | Transient API with 5 requests per IP / 10 minutes                  |
| Bot Protection        | Hidden honeypot field (`swpm_website`)                             |
| Token Security        | `random_bytes(32)` + `bin2hex()` (64 characters)                   |
| API Key Encryption    | AES-256-CBC + HMAC-SHA256 + WordPress salt                         |
| IP Validation         | `FILTER_VALIDATE_IP` (IPv4 + IPv6)                                 |
| OAuth Token Storage   | Encrypted token storage, automatic refresh                         |
| Conflict Detection    | wp_mail override analysis via ReflectionFunction                   |
| Origin/Referer        | Origin/referer validation in REST API                              |

---

## GDPR & Legal Compliance

- **Double Opt-in:** Enabled by default
- **Unsubscribe:** Unsubscribe link in every email
- **GDPR Consent Checkbox:** Personal data collection consent (with privacy policy link)
- **WordPress Privacy API:** Data export and deletion integration
- **IP Logging:** IP address recorded for KVKK/GDPR compliance
- **Log Cleanup:** Logs older than 30 days are automatically cleaned up (configurable)
- **Token Expiration:** Confirmation tokens expire after 48 hours (configurable)

### WordPress Privacy API

The plugin provides full integration with WordPress Privacy API:

- **Data Export:** Tools > Export Personal Data → Subscriber data is included
- **Data Deletion:** Tools > Erase Personal Data → Subscriber records are removed

---

## Multilingual Support (i18n)

- Text domain: `swpmail`
- Language files: `languages/` folder
- Built-in languages: English (default), Turkish (`tr_TR`)

### Adding Translations

1. Open the `languages/swpmail.pot` file with Poedit or a similar tool
2. Create your translations
3. Save the `.po` and `.mo` files to the `languages/` folder
4. File name format: `swpmail-{locale}.po` (e.g., `swpmail-de_DE.po`)

---

## Theme Developer Guide

### Shortcode Template Override

```php
// Load subscribe-form.php template from theme folder
add_filter( 'swpm_subscribe_template', function( $path, $atts ) {
    $theme_path = get_stylesheet_directory() . '/swpmail/subscribe-form.php';
    return file_exists( $theme_path ) ? $theme_path : $path;
}, 10, 2 );
```

### Email Template Override

Copy email templates as HTML files to the `your-theme/swpmail/templates/` directory. Templates in this directory are loaded before the plugin's default templates.

### Service Container Access

```php
// Global helper function
$mailer      = swpm( 'mailer' );
$subscriber  = swpm( 'subscriber' );
$provider    = swpm( 'provider' );
$queue       = swpm( 'queue' );
$engine      = swpm( 'template_engine' );
$router      = swpm( 'router' );
$tracker     = swpm( 'tracker' );
$analytics   = swpm( 'analytics' );
$connections = swpm( 'connections_manager' );

// Or static access
$mailer = SWPMail::get( 'mailer' );
```

---

## Hooks Reference (Developer API)

### Action Hooks

| Hook                           | Parameters                            | Description                                         |
| ------------------------------ | ------------------------------------- | --------------------------------------------------- |
| `swpm_subscriber_created`      | `$id`, `$email`, `$frequency`         | When a new subscriber is created                    |
| `swpm_subscriber_confirmed`    | `$id`, `$email`                       | When a subscriber is confirmed                      |
| `swpm_subscriber_unsubscribed` | `$id`, `$email`                       | When a subscription is cancelled                    |
| `swpm_register_triggers`       | `$trigger_manager`                    | Custom trigger registration point                   |
| `swpm_mail_sent`               | `$result`, `$recipient`, `$mail_data` | When an email is sent successfully                  |
| `swpm_mail_failed`             | `$result`, `$recipient`, `$mail_data` | When sending fails (triggers alarms)                |
| `swpm_auth_failure`            | `$provider_key`                       | API authentication failure                          |
| `swpm_smtp_exception`          | `$exception`, `$friendly_msg`         | When an SMTP error occurs                           |

### Filter Hooks

| Hook                             | Returns  | Description                                         |
| -------------------------------- | -------- | --------------------------------------------------- |
| `swpm_provider_registry`         | `array`  | Add new provider to the provider list               |
| `swpm_subscribe_template`        | `string` | Change shortcode form template path                 |
| `swpm_template_path`             | `string` | Change email template path                          |
| `swpm_trigger_should_send_{key}` | `bool`   | Stop/allow trigger sending                          |
| `swpm_trigger_new_post_types`    | `array`  | Extend triggerable post types                       |
| `swpm_pre_send`                  | `bool`   | Pre-send filter                                     |
| `swpm_mail_headers`              | `array`  | Modify mail headers                                 |
| `swpm_skip_override`             | `bool`   | Skip wp_mail override                               |
| `swpm_queue_batch_size`          | `int`    | Queue batch size (default: 50)                      |
| `swpm_api_timeout`               | `int`    | HTTP request timeout (default: 20s)                 |
| `swpm_confirm_token_expiry`      | `int`    | Token expiry (default: 172800s / 48 hours)          |
| `swpm_blocked_email_domains`     | `array`  | Blocked email domains                               |
| `swpm_log_retention_days`        | `int`    | Log retention period (default: 30 days)             |

### Usage Examples

```php
// 1. Add custom provider
add_filter( 'swpm_provider_registry', function( $registry ) {
    $registry['sparkpost'] = 'My_SparkPost_Provider';
    return $registry;
} );

// 2. Stop sending to specific recipient
add_filter( 'swpm_pre_send', function( $send, $recipient, $mail_data ) {
    if ( 'skip@example.com' === $recipient ) {
        return false;
    }
    return $send;
}, 10, 3 );

// 3. Increase API timeout
add_filter( 'swpm_api_timeout', fn() => 30 );

// 4. Add Reply-To header
add_filter( 'swpm_mail_headers', function( $headers ) {
    $headers[] = 'Reply-To: support@example.com';
    return $headers;
} );

// 5. Reduce token expiry to 24 hours
add_filter( 'swpm_confirm_token_expiry', fn() => DAY_IN_SECONDS );

// 6. Notify Slack about sending errors
add_action( 'swpm_mail_failed', function( $result, $recipient ) {
    wp_remote_post( SLACK_WEBHOOK_URL, [
        'body' => wp_json_encode( [
            'text' => "Mail failed: {$recipient} — " . $result->get_error_message(),
        ] ),
    ] );
}, 10, 2 );
```

---

## File Structure

```
swpmail/
├── swpmail.php                          ← Main file (header + bootstrap)
├── readme.txt                           ← WordPress.org readme
├── README.md                            ← Detailed project documentation
├── uninstall.php                        ← Uninstall cleanup
├── composer.json                        ← Composer configuration
├── phpcs.xml                            ← Code standards
│
├── includes/
│   ├── helpers.php                      ← Helper functions (encrypt/decrypt/log/swpm)
│   ├── class-swpmail.php                ← Bootstrap / Service Container
│   ├── class-loader.php                 ← Action/Filter loader
│   ├── class-i18n.php                   ← Language support
│   ├── class-activator.php              ← Activation (DB tables + defaults)
│   ├── class-deactivator.php            ← Deactivation (cron cleanup)
│   │
│   ├── core/
│   │   ├── class-subscriber.php         ← Subscriber CRUD + GDPR
│   │   ├── class-mailer.php             ← Sending via provider
│   │   ├── class-queue.php              ← Email queue
│   │   ├── class-cron.php               ← 6 scheduled tasks
│   │   ├── class-template-engine.php    ← Template engine (variable system)
│   │   ├── class-router.php             ← Smart routing engine
│   │   ├── class-connections-manager.php← Failover & health check
│   │   ├── class-analytics.php          ← Open/click analytics
│   │   ├── class-tracker.php            ← Open pixel & click redirect
│   │   ├── class-conflict-detector.php  ← 18+ conflict detection
│   │   └── class-db-repair.php          ← Database diagnostics & repair
│   │
│   ├── providers/
│   │   ├── interface-provider.php       ← Provider contract
│   │   ├── class-send-result.php        ← Send result DTO
│   │   ├── class-provider-factory.php   ← Provider factory (18 providers)
│   │   ├── class-provider-phpmail.php   ← PHP Mail (default)
│   │   ├── class-provider-smtp.php      ← Generic SMTP
│   │   ├── class-provider-gmail.php     ← Gmail (SMTP / OAuth 2.0)
│   │   ├── class-provider-outlook.php   ← Outlook (SMTP / OAuth 2.0)
│   │   ├── class-provider-zoho.php      ← Zoho Mail
│   │   ├── class-provider-mailgun.php   ← Mailgun HTTP API
│   │   ├── class-provider-sendgrid.php  ← SendGrid HTTP API
│   │   ├── class-provider-postmark.php  ← Postmark HTTP API
│   │   ├── class-provider-brevo.php     ← Brevo HTTP API
│   │   ├── class-provider-ses.php       ← Amazon SES (SDK-less)
│   │   ├── class-provider-resend.php    ← Resend HTTP API
│   │   ├── class-provider-elasticemail.php ← Elastic Email HTTP API
│   │   ├── class-provider-mailjet.php   ← Mailjet HTTP API
│   │   ├── class-provider-mailersend.php← MailerSend HTTP API
│   │   ├── class-provider-sendlayer.php ← SendLayer HTTP API
│   │   ├── class-provider-smtpcom.php   ← SMTP.com HTTP API
│   │   ├── class-provider-smtp2go.php   ← SMTP2GO HTTP API
│   │   └── class-provider-sparkpost.php ← SparkPost HTTP API
│   │
│   ├── hooks/
│   │   └── class-wp-mail-override.php   ← Global wp_mail interceptor
│   │
│   ├── triggers/
│   │   ├── class-trigger-base.php       ← Abstract trigger class
│   │   ├── class-trigger-manager.php    ← Trigger manager
│   │   ├── class-trigger-new-post.php   ← New post trigger
│   │   ├── class-trigger-new-user.php   ← New user trigger
│   │   ├── class-trigger-user-login.php ← User login trigger
│   │   ├── class-trigger-new-comment.php← New comment trigger
│   │   └── class-trigger-password-reset.php ← Password reset trigger
│   │
│   ├── alarms/
│   │   ├── interface-alarm-channel.php  ← Alarm channel contract
│   │   ├── class-alarm-dispatcher.php   ← Alarm dispatcher
│   │   ├── class-alarm-channel-slack.php    ← Slack webhook
│   │   ├── class-alarm-channel-discord.php  ← Discord webhook
│   │   ├── class-alarm-channel-teams.php    ← Teams webhook
│   │   ├── class-alarm-channel-twilio.php   ← Twilio SMS
│   │   └── class-alarm-channel-custom.php   ← Custom webhook
│   │
│   ├── admin/
│   │   ├── class-admin.php              ← Admin menu (12 pages) and asset loading
│   │   ├── class-settings.php           ← Settings API integration
│   │   ├── class-subscribers-list-table.php ← WP_List_Table
│   │   ├── class-template-editor.php    ← CodeMirror template editor
│   │   ├── class-logs-list-table.php    ← Email log table
│   │   ├── class-dns-checker.php        ← SPF/DKIM/DMARC verification
│   │   ├── class-oauth-manager.php      ← Gmail/Outlook OAuth 2.0
│   │   └── class-setup-wizard.php       ← First-run setup wizard
│   │
│   ├── cli/
│   │   └── class-cli.php                ← WP-CLI commands (16+ subcommands)
│   │
│   └── public/
│       ├── class-public.php             ← Frontend asset loading
│       ├── class-shortcode.php          ← [swpmail_subscribe] shortcode
│       ├── class-ajax-handler.php       ← AJAX endpoints
│       └── class-rest-api.php           ← REST API endpoints
│
├── templates/
│   └── default/
│       ├── base.html                    ← Base email layout
│       ├── new-post.html                ← New post template
│       ├── welcome.html                 ← Welcome template
│       ├── confirm-subscription.html    ← Double opt-in template
│       ├── digest-daily.html            ← Daily digest template
│       └── digest-weekly.html           ← Weekly digest template
│
├── admin/
│   ├── css/
│   │   ├── swpmail-admin.css            ← Admin styles
│   │   └── swpmail-setup-wizard.css     ← Setup wizard styles
│   ├── js/
│   │   ├── swpmail-admin.js             ← Admin scripts
│   │   └── swpmail-setup-wizard.js      ← Setup wizard scripts
│   └── partials/
│       ├── display-dashboard.php        ← Dashboard page
│       ├── display-subscribers.php      ← Subscriber list page
│       ├── display-templates.php        ← Template editor page
│       ├── display-triggers.php         ← Trigger management page
│       ├── display-mail-settings.php    ← Provider settings page (18 provider grid)
│       ├── display-dns-checker.php      ← DNS verification page
│       ├── display-routing.php          ← Smart routing page
│       ├── display-logs.php             ← Email logs page
│       ├── display-alarms.php           ← Alarm configuration page
│       ├── display-settings.php         ← General settings page
│       ├── display-tools.php            ← Tools page (DB repair, conflicts, system info)
│       ├── display-setup-wizard.php     ← Setup wizard page
│       └── provider-grid.php            ← Provider selection grid (partial)
│
├── public/
│   ├── css/swpmail-public.css           ← Frontend styles
│   ├── js/swpmail-public.js             ← Frontend scripts
│   ├── img/                             ← Images
│   └── partials/
│       └── subscribe-form.php           ← Subscription form template
│
└── languages/
    ├── swpmail.pot                      ← Translation template
    ├── swpmail-tr_TR.po                 ← Turkish translation source
    └── swpmail-tr_TR.mo                 ← Turkish translation (compiled)
```

---

## Database Design

The plugin creates 4 custom tables. All tables use the `{prefix}swpm_` prefix.

### `swpm_subscribers`

| Column         | Type                   | Description                               |
| -------------- | ---------------------- | ----------------------------------------- |
| `id`           | BIGINT(20) UNSIGNED PK | Auto-incrementing ID                      |
| `email`        | VARCHAR(200) UNIQUE    | Email address                             |
| `name`         | VARCHAR(100)           | Subscriber name                           |
| `status`       | ENUM                   | pending, confirmed, unsubscribed, bounced |
| `frequency`    | ENUM                   | instant, daily, weekly                    |
| `token`        | VARCHAR(64)            | Confirmation/subscription token           |
| `ip_address`   | VARCHAR(45)            | IPv4/IPv6 address                         |
| `confirmed_at` | DATETIME               | Confirmation date                         |
| `created_at`   | DATETIME               | Registration date                         |
| `updated_at`   | DATETIME               | Update date                               |

### `swpm_queue`

| Column            | Type                   | Description                             |
| ----------------- | ---------------------- | --------------------------------------- |
| `id`              | BIGINT(20) UNSIGNED PK | Auto-incrementing ID                    |
| `subscriber_id`   | BIGINT(20) UNSIGNED    | Related subscriber (NULL = general wp_mail) |
| `template_id`     | VARCHAR(100)           | Template identifier                     |
| `to_email`        | VARCHAR(200)           | Recipient email                         |
| `subject`         | VARCHAR(500)           | Subject                                 |
| `body`            | LONGTEXT               | HTML body                               |
| `headers`         | TEXT                   | JSON formatted headers                  |
| `attachments`     | TEXT                   | JSON file paths                         |
| `status`          | ENUM                   | pending, sending, sent, failed          |
| `attempts`        | TINYINT(3)             | Attempt count                           |
| `max_attempts`    | TINYINT(3)             | Maximum attempts (default: 3)           |
| `provider_used`   | VARCHAR(50)            | Provider used                           |
| `provider_msg_id` | VARCHAR(200)           | Provider message ID                     |
| `scheduled_at`    | DATETIME               | Scheduled send time                     |
| `sent_at`         | DATETIME               | Send date                               |
| `error_message`   | TEXT                   | Error message                           |
| `error_code`      | VARCHAR(50)            | Error code                              |
| `created_at`      | DATETIME               | Creation date                           |

### `swpm_logs`

| Column        | Type                   | Description                  |
| ------------- | ---------------------- | ---------------------------- |
| `id`          | BIGINT(20) UNSIGNED PK | Auto-incrementing ID         |
| `queue_id`    | BIGINT(20) UNSIGNED    | Related queue item           |
| `trigger_key` | VARCHAR(100)           | Trigger key                  |
| `provider`    | VARCHAR(50)            | Provider name                |
| `level`       | ENUM                   | debug, info, warning, error  |
| `message`     | TEXT                   | Log message                  |
| `context`     | LONGTEXT               | JSON details                 |
| `created_at`  | DATETIME               | Record date                  |

### `swpm_tracking`

| Column       | Type                   | Description                              |
| ------------ | ---------------------- | ---------------------------------------- |
| `id`         | BIGINT(20) UNSIGNED PK | Auto-incrementing ID                     |
| `hash`       | VARCHAR(64)            | Tracking hash (unique identifier)        |
| `queue_id`   | BIGINT(20) UNSIGNED    | Related queue item                       |
| `to_email`   | VARCHAR(200)           | Recipient email address                  |
| `subject`    | VARCHAR(500)           | Email subject                            |
| `event_type` | ENUM                   | open, click                              |
| `url`        | TEXT                   | Clicked URL (for click events)           |
| `ip_address` | VARCHAR(45)            | IPv4/IPv6 address                        |
| `user_agent` | VARCHAR(500)           | Browser/client information               |
| `created_at` | DATETIME               | Event date                               |

**Indexes:** `hash`, `queue_id`, `event_type`, `created_at`, (`to_email`, `event_type`)

---

## Diagnostics & Repair Tools

A three-tabbed diagnostics interface accessible from the **SWPMail > Tools** page:

### Database Repair (DB Repair)

| Function       | Description                                                                                                                                |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| **Diagnostics** | Missing tables, columns, indexes; orphaned queue items; stuck sends; missing options                                                      |
| **Repair**     | Schema update with `dbDelta()`; set orphan `subscriber_id`s to NULL; reset stuck sending→pending; restore missing options                  |

AJAX: `wp_ajax_swpm_db_diagnose`, `wp_ajax_swpm_db_repair`

### Conflict Detection (Conflict Detector)

Scans 18+ known conflicting plugins (WP Mail SMTP, Mailgun, SendGrid, etc.) and performs the following checks:

- `wp_mail()` override conflict (via ReflectionFunction)
- Cron status (alt-cron, disabled-cron)
- PHP `mail()` function accessibility
- Required PHP extensions (cURL, OpenSSL, json, mbstring)
- WordPress configuration issues
- Conflicts caused by mu-plugins

AJAX: `wp_ajax_swpm_detect_conflicts`

### System Information

Summary report of server environment, PHP configuration, WordPress settings, and plugin status.

---

## Testing

### Development Tools

```bash
# PHP CodeSniffer + WordPress standards
composer require --dev squizlabs/php_codesniffer
composer require --dev wp-coding-standards/wpcs:"^3.0"

# PHPUnit + Brain Monkey (WP mock)
composer require --dev phpunit/phpunit:"^9.0"
composer require --dev brain/monkey:"^2.6"

# Code standards check
vendor/bin/phpcs --standard=phpcs.xml .

# Connection test
# Admin > SWPMail > Mail Settings > "Send Test Email" button
```

### Test Checklist

- [ ] `if ( ! defined( 'ABSPATH' ) ) exit;` present in all PHP files
- [ ] All outputs are protected with `esc_*` functions
- [ ] All SQL queries are prepared with `$wpdb->prepare()`
- [ ] Nonce verification in AJAX endpoints
- [ ] `current_user_can()` check in admin operations
- [ ] API keys are stored encrypted
- [ ] `readme.txt` Stable Tag matches version

---

## FAQ (Frequently Asked Questions)

<details>
<summary><strong>Does it affect other plugins' emails?</strong></summary>

Yes, by design. When configured, all `wp_mail()` calls across the site (WooCommerce, Contact Form 7, password reset, etc.) are sent through the selected provider. You can disable the "Override wp_mail" option from the **SWPMail > Settings** page if desired.

</details>

<details>
<summary><strong>Is it GDPR compliant?</strong></summary>

Yes. Double opt-in is enabled by default, every email includes an unsubscribe link, and it fully integrates with the WordPress Privacy API.

</details>

<details>
<summary><strong>How do I add a custom trigger?</strong></summary>

You can register your own trigger class derived from `SWPM_Trigger_Base` using the `swpm_register_triggers` action hook. See the [Trigger System](#trigger-system) section for details.

</details>

<details>
<summary><strong>What happens to my data if I remove the plugin?</strong></summary>

When you only **deactivate** the plugin, your data is preserved and only cron tasks are cleaned up. When you **delete** it, `uninstall.php` runs: 4 database tables, all plugin settings, template data, and tracking data are permanently deleted.

</details>

<details>
<summary><strong>Are disposable email addresses supported?</strong></summary>

No. By default, temporary email services such as mailinator.com, tempmail.com, guerrillamail.com are blocked. You can extend this list using the `swpm_blocked_email_domains` filter.

</details>

<details>
<summary><strong>My email queue isn't working, what should I do?</strong></summary>

Make sure your WordPress cron is running. If you see a "Queue hasn't run for more than 1 hour" warning on the Dashboard, ensure your hosting provider supports WP-Cron, or set up a real cron job:

```bash
# Add via crontab -e
*/5 * * * * wget -q -O - https://site.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

Alternatively, you can manually process the queue with WP-CLI:

```bash
wp swpmail queue process --limit=50
```

</details>

<details>
<summary><strong>What happens if the primary provider goes down?</strong></summary>

The failover mechanism kicks in. If the primary provider fails 3 times consecutively, it automatically switches to the backup provider. After 5 minutes, a health check is performed on the primary provider, and if it's healthy, it switches back. You can monitor this process from the **SWPMail > Dashboard** page.

</details>

<details>
<summary><strong>How do I verify my DNS records?</strong></summary>

You can automatically verify your domain's SPF, DKIM, and DMARC records from the **SWPMail > DNS Checker** page. Results are cached for 15 minutes and rechecked every time you save your mail settings.

</details>

<details>
<summary><strong>How do I detect conflicting plugins?</strong></summary>

You can scan for 18+ known conflicting plugins (WP Mail SMTP, Mailgun, SendGrid, etc.) and wp_mail override issues from the **SWPMail > Tools > Conflict Detector** tab. You can also use the `wp swpmail conflicts` command from the terminal.

</details>

<details>
<summary><strong>How do I set up OAuth 2.0 for Gmail/Outlook?</strong></summary>

1. Select Gmail or Outlook as your provider
2. Create an OAuth 2.0 application from Google Cloud Console / Azure Portal
3. Enter the Client ID and Client Secret
4. Click the **Authorize** button to complete the OAuth flow

If OAuth is not configured, SMTP connection with App Password is used.

</details>

<details>
<summary><strong>How do I change the batch size?</strong></summary>

```php
// Reduce queue size for shared hosting
add_filter( 'swpm_queue_batch_size', fn() => 10 );
```

</details>

---

## License

This plugin is licensed under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
Copyright (C) 2024 SWPMail

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
```
