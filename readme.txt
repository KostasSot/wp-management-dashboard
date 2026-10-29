=== WP Dashboard Management ===
Contributors: Konstantinos Sotiriou
Tags: dashboard, management, updates, logs, activity
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A site management dashboard: plugin updates, active/inactive status, PHP error logs, environment info, and Beqlic Activity Log charts.

== Description ==

WP Dashboard Management adds a single "Management" screen to wp-admin that shows:

* WordPress version (with available core update) and PHP version
* Every installed plugin, whether it is active or inactive, and whether an update is available
* A one-click "Check for updates" refresh
* The PHP error log (WP_DEBUG_LOG / error_log) with severity filters and a clear-log action
* Environment details: database, memory limits, execution time, debug flags, theme, timezone
* Beqlic Activity Log integration: one-click activation if installed but inactive, plus charts
  (events per day, by severity, top actions, most active users) and the latest events

== Changelog ==

= 1.3.2 =
* Dashboard warning when the early error-logger drop-in cannot be written to wp-content/mu-plugins (not writable, or a file with the same name is in the way).

= 1.3.1 =
* PHP error logging now starts from a must-use drop-in (mu-plugins/0-wpdm-error-logger.php), so fatal errors in other plugins' main files (broken updates, PHP upgrades) are logged and emailed. Installed on activation, kept in sync on admin pages, removed on deactivation.
* Fatal error alerts load pluggable.php when a fatal happens before WordPress defines wp_mail().
* Error handlers are registered only once.

= 1.3.0 =
* New module: Fatal Error Alerts — emails the report address on a PHP fatal error, at most once per hour.
* New module: Abandoned Plugin Detection — daily wordpress.org check for closed plugins and plugins without a release in two years.
* New module: Vulnerability Scan — daily check of core, plugins, and themes against WPVulnerability.net. Off by default (sends slugs and the WordPress version to that service).
* New module: Snapshot Before Updates — database snapshot before plugin and theme updates, including auto-updates; keeps the newest five. Off by default.
* New module: Plugin Version Rollback — install any of the last 30 wordpress.org releases of a plugin, with a database snapshot first.
* Security check: unique secret keys and salts (Fix generates new ones), usernames not exposed via the REST API or ?author= (Fix blocks both for logged-out visitors), uploads directory listing, inactive plugins, inactive themes.
* Email report: adds failing security checks, Site Health counts, PHP errors of the last 24 hours, known vulnerabilities, and abandoned plugins.
* WP-CLI: wp wpdm backup create|list|restore|delete, wp wpdm report send, wp wpdm security, wp wpdm scan.
* wp-config.php edits are applied in a single write with one backup; quoted values are matched whole, so salts containing ");" can no longer corrupt the file.

= 1.0.0 =
* Initial release.
