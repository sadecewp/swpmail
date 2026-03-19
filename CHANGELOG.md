# Changelog

All notable changes to the SWPMail plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] — 2026-03-19

### Added

- GitHub Actions CI/CD pipeline with PHPCS and PHPUnit matrix (PHP 7.4–8.3).
- `SWPM_Dashboard_Data` class for dashboard data encapsulation (MVC separation).
- Unit test infrastructure: `phpunit.xml.dist`, `tests/bootstrap.php`, and initial test files.
- CHANGELOG.md to track project history.

### Changed

- Tracking cleanup (`cleanup_old_tracking`) now uses a batch-delete loop instead of a single `LIMIT 1000` query; batch size is configurable via `swpm_cleanup_batch_size` filter.
- `enqueue_bulk()` uses multi-row INSERT instead of N individual queries; batch size configurable via `swpm_bulk_enqueue_batch_size` filter (default 50).
- Subscribe rate limit configurable via `swpm_subscribe_rate_limit` / `swpm_subscribe_rate_window` filters.
- Confirm rate limit configurable via `swpm_confirm_rate_limit` / `swpm_confirm_rate_window` filters.
- Unsubscribe rate limit configurable via `swpm_unsubscribe_rate_limit` / `swpm_unsubscribe_rate_window` filters.
- `display-dashboard.php` no longer contains direct `$wpdb` queries; data is provided by `SWPM_Dashboard_Data`.

## [1.0.0] — 2026-03-01

### Added

- Initial release.
- SMTP and API-based email delivery with 20+ provider support.
- Email subscription system with double opt-in.
- Template engine with Blade-like syntax.
- Mail queue with retry logic and failover routing.
- Alarm system (Discord, Slack, Teams, Twilio, Custom Webhook).
- Trigger-based automation engine.
- Click/open tracking and analytics dashboard.
- DNS checker (SPF, DKIM, DMARC, MX).
- WP-CLI commands for all operations.
- REST API endpoints.
- Internationalization for 8 languages.
- Setup wizard for first-run configuration.
