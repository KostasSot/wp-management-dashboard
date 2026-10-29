# WP Dashboard Management

A modular site-management dashboard for WordPress. One admin screen ("Management") that shows everything you need to run a site — plugin & theme updates, PHP error logs, security posture, Site Health, activity charts — plus power tools for development sites: a WordPress version switcher and full database snapshots.

Every module is optional. A setup wizard lets you choose per site which modules are enabled, so a client site can run a slim read-only dashboard while your dev box gets the full toolkit.

- **Version:** 1.2.1
- **Author:** Konstantinos Sotiriou
- **Requires:** WordPress 5.8+, PHP 7.4+
- **License:** GPL-2.0-or-later

---

## Installation

1. Copy the `wp-dashboard-management` folder into `wp-content/plugins/`.
2. Activate it from **Plugins** (or `wp plugin activate wp-dashboard-management`).
3. On first activation you are redirected to the **setup wizard** — pick the modules this site should have.
4. The dashboard lives under the **Management** menu (top of the admin sidebar).

To reconfigure at any time: **Management → Setup**. Your current selection is pre-checked; saving applies immediately.

Uninstalling (deleting the plugin from wp-admin) removes its settings and cached data. **Log files and database snapshots in `wp-content/uploads/` are intentionally kept** — snapshots may be the only backup a site has. Delete `uploads/wpdm-logs/` and `uploads/wpdm-backups/` manually if you want them gone.

---

## Modules

All modules are chosen in the setup wizard. Disabled modules are removed from the page **and** blocked server-side — their AJAX endpoints reject requests, so they are off, not merely hidden.

### Monitoring & Maintenance

| Module | What it does |
|---|---|
| **Plugins & Themes Overview** | Tables of every installed plugin and theme: version, Active/Inactive badge, available update (updates sorted first). "Check for updates" forces a fresh check against wordpress.org for plugins, themes, and core. |
| **Plugin & Theme Actions** | The buttons on those tables: one-click **Update now** (uses WordPress core's own update endpoints), **Activate/Deactivate** plugins, and **Activate** a theme (switching deactivates the current one). Disable this to make the overview read-only — useful on client sites. |
| **XLSX Update Report** | "Export updates (XLSX)" downloads a styled two-sheet report (Plugins / Themes) of **active plugins and all themes with pending updates**: blue header band, zebra rows, bold-orange status on major-version jumps, and a live `=COUNTA` total. Works standalone even with the overview disabled. Generated natively with ZipArchive — no libraries. |
| **PHP Error Logs** | Two log sources with tabs: **Plugin log** — the plugin captures PHP warnings/notices/deprecations/fatals itself (own error + shutdown handlers) into `uploads/wpdm-logs/php-errors.log`, independent of `WP_DEBUG`, auto-rotated at 5 MB, web-blocked by `.htaccess`; and **WP debug.log** — the standard file. Severity filters (Fatal/Warning/Notice/Deprecated), per-source **Clear log**, reads only the newest 256 KB so huge files stay fast. |
| **Security Health Check** | Hardening checklist with a fix hint per failure, and a one-click **Fix** button where the fix is safe to automate (file editing, "admin" username rename, PHP errors displayed, XML-RPC, open registration, `wp-config.php` permissions). Checks: HTTPS, `DISALLOW_FILE_EDIT`, no "admin" username, core version not flagged insecure on wordpress.org, PHP errors not displayed publicly, custom table prefix, XML-RPC disabled, open registration disabled, `wp-config.php` permissions. |
| **Site Health Summary** | Runs WordPress core's `WP_Site_Health` direct tests and shows good/recommended/critical counts, critical issues first. A **Fix** button repairs the safe ones: debug mode left on, missing `wp-content/upgrade-temp-backup`, and search engines discouraged (asks first). Cached 15 minutes ("Re-run tests" forces a refresh) because some core tests make loopback HTTP requests. |
| **Activity Log (Beqlic)** | Integration with the Beqlic Activity Log plugin: events-per-day bar chart, severity & top-action donuts, most-active-users list, and the 10 latest events (charts are dependency-free canvas). If Beqlic is installed but inactive → one-click **Activate**. If not installed → one-click **Install now from GitHub** (downloads the repo zip, installs via core's `Plugin_Upgrader`, activates). |
| **Email Update Reports** | Scheduled email (daily/weekly/monthly, or disabled) to a configurable address with the core version + pending core update and the available updates for **active plugins and all themes**, as a styled HTML table. "Send report now" for on-demand sends. Delivered via WP-Cron and `wp_mail()` — on real sites pair it with an SMTP plugin so mail actually leaves the server. |
| **Debug Mode Toggle** | One Enable/Disable switch in the Environment panel. **Enable** sets `WP_DEBUG = true`, `WP_DEBUG_DISPLAY = false` (log errors, never print them on the site) and points `WP_DEBUG_LOG` at a private file instead of the public `wp-content/debug.log`: a random-named log in `wpdm-logs/` above the WordPress root when that is writable and outside the document root, otherwise in the web-blocked `uploads/wpdm-logs/`. An existing `wp-content/debug.log` is moved there. Site Health still shows a recommendation while logging is on; turn debug off when done. **Disable** turns all three off. Edits `wp-config.php` surgically, keeps a backup at `wp-config.php.wpdm-bak`, and invalidates OPcache so the change is visible immediately. |

### Power Tools (development/staging sites)

| Module | What it does |
|---|---|
| **WordPress Version Switcher** | Dropdown of every WordPress 6.0+ release (from the wordpress.org API, latest-per-branch, insecure releases marked ⚠️). Install any of them — upgrade **or rollback** — via core's `Core_Upgrader`. |
| **Database Snapshots** | Full-database SQL dumps: **Back up now**, plus an automatic snapshot before every version switch. Each snapshot records the WordPress version it was taken on. **Restore** replays the dump (every table in it is dropped and rebuilt); **Delete** removes the file. Pure-PHP dump/restore — no `mysqldump` needed. Stored in `uploads/wpdm-backups/`, web-blocked by `.htaccess`. |

**Clean rollback recipe:** install the older version from the dropdown, then restore the snapshot whose badge shows that version. Files and database match again. Restoring a snapshot from a *different* version than the one running triggers an extra warning — core files are never part of a snapshot.

Also always visible (no module needed): stat cards (WordPress version + core update, PHP, database) and the Environment panel (site URL, theme, timezone, memory limits, execution time, upload size, multisite).

---

## Configuration

### Constants (define in `wp-config.php` to override)

| Constant | Default | Purpose |
|---|---|---|
| `WPDM_GITHUB_REPO` | *(empty)* | GitHub repo (`owner/name`) this plugin **updates itself from**. When set, the plugin compares its version against the `Version:` header on the repo's `main` branch and offers the update through the normal WordPress update UI. Empty disables self-updates. |
| `WPDM_BEQLIC_REPO_ZIP` | `https://github.com/Becliq-Devs/beqlic-activity-log/archive/refs/heads/main.zip` | Zip URL the Beqlic installer downloads. Point at a release asset for versioned installs. |

### Filters

| Filter | Purpose |
|---|---|
| `wpdm_github_repo` | Override the self-update repository at runtime. |
| `wpdm_beqlic_repo_zip` | Override the Beqlic download URL at runtime. |

### Release workflow

1. Make changes and **bump the version** in `wp-dashboard-management.php` (both the `Version:` header and `WPDM_VERSION`) and `readme.txt` (`Stable tag`).
2. Push to the repo's `main` branch.
3. Sites check GitHub every 6 hours (or immediately via the dashboard's "Check for updates" button) and offer the new version on the Plugins/Updates screens and in this plugin's own table — one-click update as usual.

If you forget the version bump, sites will never see the release — the check is purely `version_compare()` against the header.

### Stored data

| Where | What |
|---|---|
| Option `wpdm_settings` | Feature flags, wizard-completed marker, report email + frequency. |
| Cron event `wpdm_send_report` | Scheduled report delivery (cleared on deactivation). |
| Site transient `wpdm_core_versions` | Cached wordpress.org release list (12 h). |
| Transient `wpdm_site_health` | Cached Site Health results (15 min). |
| `uploads/wpdm-logs/` | Plugin PHP error log (+ `.1` rotation). |
| `uploads/wpdm-backups/` | Database snapshots (`snapshot-<date>-wp<version>[-label].sql`). |
| `wp-config.php.wpdm-bak` | Backup written before every debug-toggle edit. |

---

## Security model

- Every page and endpoint requires the `manage_options` capability; destructive actions additionally require the matching core capability (`activate_plugins`, `switch_themes`, `update_plugins`, `update_themes`, `update_core`, `install_plugins`).
- All plugin AJAX endpoints are nonce-protected and re-check the module's feature flag server-side.
- One-click updates go through WordPress core's own `update-plugin` / `update-theme` AJAX endpoints (core's `updates` nonce and upgrader).
- The `wp-config.php` editor can touch **only** `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, `DISALLOW_FILE_EDIT` (hard whitelist; booleans only, plus a file path for `WP_DEBUG_LOG`), rewrites a single `define()` line, and backs the file up first.
- Snapshot filenames are strictly validated (`snapshot-*.sql` in the snapshots directory only); log and snapshot directories are blocked from the web via `.htaccess` + silence `index.php`. On nginx, replicate the block in your server config — nginx ignores `.htaccess`.
- Restore, delete, deactivate, theme-switch, and log-clear actions all require an explicit confirmation in the UI.
- The dashboard never shows a Deactivate button for itself (you'd kill the page you're standing on); use the Plugins screen for that.

## Notes & limitations

- **Snapshots are database-only.** Plugin/theme/core *files* and uploads are not included — this is a rollback tool, not a full-site backup.
- Restore replaces only tables present in the snapshot; tables created after it survive. Restoring an older snapshot on a newer WordPress makes core immediately re-run its DB upgrade.
- The XLSX export lists **active** plugins with pending updates (inactive ones are excluded by design) and all themes with pending updates.
- Version switching and snapshot restore are inherently disruptive operations — they're grouped under Power Tools so you can keep them off production sites.
- The plugin error logger respects `error_reporting()` / `@`-suppression and always lets PHP's normal error handling continue.

## File structure

```
wp-dashboard-management/
├── wp-dashboard-management.php      Bootstrap: constants, requires, activation hook
├── uninstall.php                    Removes options/transients (keeps logs & snapshots)
├── readme.txt                       WordPress.org-style readme
├── README.md                        This file
├── includes/
│   ├── class-wpdm-admin.php         Menu, assets, dashboard rendering
│   ├── class-wpdm-ajax.php          Nonce/capability/feature-guarded AJAX endpoints
│   ├── class-wpdm-settings.php      Feature flags (wpdm_settings option)
│   ├── class-wpdm-wizard.php        Setup wizard page + save handler
│   ├── class-wpdm-system-info.php   WP/PHP/DB/server environment info
│   ├── class-wpdm-plugins-status.php Plugin list, updates, refresh
│   ├── class-wpdm-themes-status.php Theme list, updates
│   ├── class-wpdm-php-logs.php      Log tailing/parsing for both sources
│   ├── class-wpdm-error-logger.php  Plugin-owned PHP error capture
│   ├── class-wpdm-activity-log.php  Beqlic detection/install/activate + chart data
│   ├── class-wpdm-core-manager.php  wordpress.org release list + core install
│   ├── class-wpdm-db-backup.php     Snapshot create/list/restore/delete
│   ├── class-wpdm-config.php        Whitelisted wp-config.php edits + debug mode
│   ├── class-wpdm-security-check.php Hardening checklist
│   ├── class-wpdm-site-health.php   Core WP_Site_Health wrapper with caching
│   ├── class-wpdm-export.php        Native XLSX report writer (ZipArchive)
│   └── views/
│       ├── dashboard.php            Dashboard template
│       └── wizard.php               Wizard template
└── assets/
    ├── css/admin.css, css/wizard.css
    └── js/admin.js, js/wizard.js    Vanilla JS: AJAX, filters, canvas charts, wizard steps
```

No build step, no Composer, no external libraries — vanilla PHP/JS/CSS throughout. Assets are cache-busted by file modification time.

## Troubleshooting

- **A button does nothing** → an old cached script; assets version by mtime, so a normal reload fixes it.
- **"This module is disabled"** → enable it in Management → Setup.
- **Debug toggle says wp-config.php is not writable** → check file ownership/permissions for the web server user.
- **No WP debug.log entries** → the Debug Mode toggle must be enabled (the *Plugin log* tab works regardless).
- **Site Health shows a REST/loopback error on Docker** → the container often can't reach its own public URL; usually harmless locally.
- **XLSX export fails** → the `zip` PHP extension is required.
