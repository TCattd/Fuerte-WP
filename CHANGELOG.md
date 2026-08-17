# Changelog

# 1.11.4 / 2026-08-17
- Updated dependencies.

# 1.11.3 / 2026-08-10
- Updated dependencies.

# 1.11.2 / 2026-08-09
- **Changed**: Bundled HyperFields library updated from 1.5.1 to 1.5.2. Dependency-only release; no plugin code changes. Synced `composer.lock` and `vendor/composer/installed.*`.
- **Security (upstream)**: HyperFields 1.5.2 hardens the save handlers Fuerte-WP's admin tabs rely on. Save paths now require a verified form-origin nonce (the legacy user/term meta paths that accepted a weaker check), unslash posted values before validation so sanitization sees the real input, let a multiselect submit an empty selection via a hidden sentinel (a stored value can now actually be removed) and filter submitted multiselect values against the allowed option keys, and compile `AND`/`OR` conditional-logic relations into boolean groups instead of flattening them. The association-field lookup is also bounded (skips row counting, considers all post statuses so a draft/private association is no longer silently dropped on save). No Fuerte-WP action needed; the hardened saves apply automatically.

# 1.11.1 / 2026-08-04
- **Changed**: Logger activation is now a single explicit OR gate in `Fuerte_Wp_Logger::init_from_constant()`. Logging runs only when `WP_DEBUG` is true OR the plugin-specific `FUERTEWP_DEBUG` constant is true. Previously the gate was split across two call sites: the bootstrap called `enable(WP_DEBUG)` and `init_from_constant()` could only flip it ON for `FUERTEWP_DEBUG`, never off. Functionally equivalent but order-dependent and implicit. The OR is now visible in one place; the redundant `enable(WP_DEBUG)` line is removed from `fuerte-wp.php`.
- **Note**: Logging ships OFF by default. On a standard install `WP_DEBUG` is false and `FUERTEWP_DEBUG` is undefined, so nothing is logged unless a site operator explicitly enables either constant in `wp-config.php`.
- **Changed**: The plugin debug-log opt-in constant was renamed from `FUERTEWP_DEBUG_LOGGING` to `FUERTEWP_DEBUG` (shorter, consistent with the `WP_DEBUG` naming pattern).
- **Docs**: New `docs/DEBUG-LOGGING.md` documents the debug-logging opt-in (the `WP_DEBUG` / `FUERTEWP_DEBUG` OR gate, where logs land, how to disable). Registered in `docs/INDEX.md`.
- **Changed**: Bundled HyperFields library updated from 1.5.0 to 1.5.1 (maintenance release: dependency updates). Synced `composer.lock` and `vendor/composer/installed.*`.
- **Changed**: Code-formatting pass on the review-notice methods in `Fuerte_Wp_Admin` (blank lines after early-return guards, one array alignment fix). No behavior change.

# 1.11.0 / 2026-07-30
- **Bug Fix (regression)**: The five Advanced Restrictions settings (`removed_menus`, `removed_submenus`, `removed_adminbar_menus`, `restricted_scripts`, `restricted_pages`) were silently dead since the 1.7.0 HyperFields migration. `Fuerte_Wp_Config::normalize_settings()` wrote them nested under a `advanced_restrictions` key that no enforcer reader ever consulted, while `remove_menus` / `apply_page_restrictions` / `remove_adminbar_menus` / `should_register_restriction_hooks` all read the flat top-level key. On any install without legacy Carbon Fields rows, the admin's saved values never reached the enforcer. The five keys are now emitted flat; the dead nested block is removed. (tests/unit/AdvancedRestrictionsWiringTest.php)
- **New Feature**: Discovery-driven admin menu, submenu, and admin-bar visibility. The three manual textareas are replaced by searchable multiselects populated from the live `$GLOBALS['menu']` / `$GLOBALS['submenu']` and a captured admin-bar node set (captured at `admin_bar_menu` priority 900, before Fuerte removes nodes at 999). One selection now both hides and blocks: hide selections are routed into the block vectors by slug type. A collapsed manual textarea per field remains as a precision escape hatch; multiselect and manual entries are merged and de-duplicated at load time. Saved slugs that are no longer registered (plugin uninstalled) render as `[Missing]` instead of being silently dropped. (tests/unit/MenuDiscoveryTest.php, MenuBlockRoutingTest.php, MenuManualMergeTest.php, StaleSelectionMergeTest.php)
- **Security**: Unified hide + block is deliberately narrowed to avoid over-blocking. The `edit.php` family (Posts / Pages / CPTs share the script), `index.php` (the `/wp-admin` redirect target), and `profile.php` (every user's own profile) are hide-only; blocking them by `$pagenow` would hit siblings or strand non-super users. Single-purpose core scripts (`themes.php`, `tools.php`, `plugins.php`, ...) auto-block by `$pagenow`; foreign plugin pages block by `?page=`. Core submenu children (`.php` files under a core parent, e.g. `tools.php|export.php`) block by `$pagenow`; foreign submenu children block by `?page=`.
- **Note**: Because the regression fix makes Advanced Restrictions actually apply, the field defaults also go live for non-super users: `restricted_scripts` defaults block `export.php`, `update.php`, and `update-core.php`. Saved installs will start enforcing these on upgrade. This is the intended behavior; announce it on release.
- **Bug Fix**: The "Disable App Passwords" restriction was a no-op. `Fuerte_Wp_Hook_Manager` read a non-existent config key (`rest_api.disable_app_passwords`) instead of the normalized `restrictions.restapi_disable_app_passwords`, so the `wp_is_application_passwords_available` filter was never registered and application passwords stayed enabled regardless of the checkbox. Now actually disables application passwords site-wide.
- **Bug Fix**: The App Passwords and XML-RPC restriction hooks were registered at the wrong filter priority (1 instead of 10). Root cause: an argument-shape error in the `add_hook()` wrapper — `add_hook($hook, '__return_false', 10, true)` passed `10` into the `$method` slot and `true` (coerced to 1) into `$priority`. Functionally harmless for `__return_false`, now registered at the default priority 10.
- **Changed**: Bundled HyperFields library updated from 1.4.0 to 1.5.0. HyperFields 1.5.0 adds automatic cache invalidation on settings saves: `CacheInvalidator` clears stale transients and resets OPcache whenever a real value change is persisted, is object-cache-aware (uses `wp_cache_flush_group()` on persistent backends, direct DB purge otherwise), and is controllable via `hyperfields/cache/*` filters (on by default; full `wp_cache_flush()` is an explicit opt-in).
- **Note**: HyperFields 1.5.0 raises its own runtime minimum to WordPress 6.5+ and PHP 8.2+. fuerte-wp's declared header still reads `Requires at least: 6.4` / `Requires PHP: 8.1`; update the header to match before release, or 1.11.0 will advertise support for environments the bundled dependency no longer runs on.
- Added 7 regression tests covering the App Passwords config-key path, the filter-priority argument-shape fix, the XML-RPC priority, the source-of-truth decision to ignore the legacy `rest_api` file-config key, and the migrated file-config namespace (`tests/unit/HookManagerTest.php`, `tests/unit/FileConfigFormatTest.php`).

# 1.10.0 / 2026-07-10
- **New Feature**: Bundled the official WordPress Two-Factor plugin (v0.16.0) as a library, with a site-enforced provider policy and crash-safe coexistence with the standalone plugin.
- Two-Factor providers limited to Email, Authenticator App (`Two_Factor_Totp`), and Recovery Codes (`Two_Factor_Backup_Codes`). Dummy Method (`Two_Factor_Dummy`) stripped site-wide via the `two_factor_providers` filter at priority 20, running after core's `enable_dummy_method_for_debug` so Dummy stays hidden even under `WP_DEBUG`.
- The upstream `Settings → Two-Factor` screen is hidden: submenu removed at `admin_menu` priority 99, and direct `?page=two-factor-settings` URLs redirect to `options-general.php` (provider policy is code-enforced, not admin-editable).
- Added an **Enable 2FA** checkbox on the Login Security tab (default ON). Unchecking stops the bundled library from loading. Truthiness logic: only `false / 0 / '0' / 'no' / 'off' / ''` disable it; everything else loads.
- Added **Enforce 2FA for Admins** checkbox on the Login Security tab (default ON). When enabled, Administrator and Super Admin accounts are challenged with an emailed code at login even without prior setup. Each admin can switch to Authenticator App (TOTP) from their own profile page. Enforced via `two_factor_enabled_providers_for_user` filter (priority 20) — read-only, never persists to user meta, so disabling releases admins immediately. Fuerte super users always bypass enforcement (recovery lever). Conditional logic hides the checkbox when Enable 2FA is off.
- Added `FUERTEWP_DISABLE_2FA` constant (operator escape hatch, filesystem tier) as a higher-priority off switch than the admin checkboxes.
- Crash safety: when the standalone Two-Factor plugin is already active (user had it from before Fuerte-WP), `boot()` detects `Two_Factor_Core` already declared via a `class_exists` guard and skips loading the bundled copy. WordPress loads all active plugin main files before `plugins_loaded`, so the standalone always wins the declaration race and no class/function redeclare fatal occurs.
- The Enable 2FA and Enforce checkboxes are hidden when the standalone plugin is active, replaced with a notice explaining the standalone is running the show.
- Collision protection: while the bundled copy is active, the standalone plugin's Activate link is hidden and activation is blocked (single-row, bulk, multisite). Super users bypass this after setting `FUERTEWP_DISABLE_2FA`.
- **Changed**: Moved auto-update settings (WordPress core, Plugins, Themes, Translations, and Update check frequency) from the Main tab to the Updates tab, now grouped under an Auto-Updates heading above the Deferred/Blocked lists.
- Added 25 regression tests for the Two-Factor integration (provider policy, boot gate precedence, config wiring, truthiness logic, enforcement layer with super-user bypass, public API surface, crash safety).

# 1.9.6 / 2026-06-06
- **New Feature**: Disable Comments site-wide with a single toggle (Restrictions tab).
- Closes comments and pings on all post types via `comments_open`/`pings_open` filters.
- Empties existing comments array and zeroes comment count.
- Removes Comments admin menu, dashboard widget, admin bar node, and list table column.
- Strips comments/trackbacks post type support on all registered post types.
- Blocks comment feeds (403), removes comment feed links from `<head>`.
- Blocks REST API comment endpoints (`/wp/v2/comments`) with 403 error.
- Blocks all XML-RPC comment methods (newComment, getComment, getComments, deleteComment, editComment, getCommentCount, getCommentStatusList) and `pingback.ping`.
- Removes X-Pingback HTTP header.
- Deregisters `comment-reply` script.
- Replaces theme comment template with blank file (defense-in-depth against themes that ignore `comments_open()`).
- Enabled by default; super-user bypass intentionally not applied (site-wide means everyone).

# 1.9.5 / 2026-06-02
- **Bug Fix**: Super-users were incorrectly affected by restrictions meant only for non-super-users (Permalinks, ACF, Theme/Plugin Editor, Theme/Plugin Install, Customizer CSS).
- Root cause: `Fuerte_Wp_Hook_Manager` registered restriction hooks (menu removal, admin bar removal, `DISALLOW_FILE_EDIT`) before the enforcer's super-user check could run.
- Added `Fuerte_Wp_Helper::is_super_user()` and `Fuerte_Wp_Helper::bypasses_restrictions()` as single source of truth, replacing 7 duplicated inline email-matching patterns.
- Fixed latent case-sensitivity bug in admin class where super-user email comparison was not lowercasing the config values.
- Fixed wrong config key `disable_file_edit` in Hook Manager (now checks `disable_theme_editor`/`disable_plugin_editor`).
- Added 9 tests for super-user restriction bypass.

# 1.9.4 / 2026-05-08
- **Bug Fix**: Fixed WooCommerce Action Scheduler async runner being blocked by Login URL Hider.
- `early_wp_admin_check()` now bypasses for AJAX (`wp_doing_ajax()`) and cron (`wp_doing_cron()`) requests.
- Previous code had no `DOING_AJAX` guard, causing unauthenticated `wp_ajax_nopriv_` requests to `/wp-admin/admin-ajax.php` (used by Action Scheduler's async queue runner) to be redirected to 404.
- Also normalized `protect_wp_admin_access()` to use `wp_doing_ajax()` wrapper instead of bare `defined('DOING_AJAX')`.
- Added 8 regression tests covering AJAX/cron bypass and Action Scheduler compatibility.

# 1.9.3 / 2026-04-29
- **Removed**: The "Enable Fuerte-WP" checkbox option has been removed. If the plugin is active, Fuerte-WP is active.
- **Changed**: Auto-update settings now default to enabled: WordPress core, plugins, themes, and translations.
- **Changed**: Fatal Error email notification now defaults to enabled.
- **Changed**: Login Security and Registration Protection now default to enabled.
- **Changed**: Disable App Passwords now defaults to enabled.
- **Changed**: Disable XML-RPC API now defaults to enabled.
- **Changed**: Disable Weak Passwords now defaults to enabled.
- **Changed**: Restrict Permalinks, Restrict ACF, Disable Theme Editor, Disable Plugin Editor, Disable Theme Install, Disable Plugin Install, and Disable Customizer CSS Editor now default to enabled.
- **Changed**: Block Common Admin Usernames now defaults to disabled.
- **Changed**: Enable Registration Protection (IP Lists tab) now defaults to disabled.
- Removed `fuertewp_status` from configuration normalization, legacy database loader, enforcer, hook manager, activator, migrator, and config file samples.
- Updated test fixtures to remove all `_fuertewp_status` references.

# 1.9.1 / 2026-04-25
- **Bug Fix**: Fixed multiselect fields not saving data on deferred updates and restriction settings pages.
- Fixed `fuertewp_restrictions_disable_admin_bar_roles`, `fuertewp_deferred_plugins`, `fuertewp_deferred_themes`, `fuertewp_blocked_plugins`, and `fuertewp_blocked_themes` fields now properly persist selections.
- Updated HyperFields enhanced multiselect template to include hidden select element for form submission.
- Updated HyperFields JavaScript to properly find and update the hidden select element.
- Added HyperFields initialization with version parameter for proper cache busting.
- **Bug Fix**: Fixed plugin status not being enabled by default on fresh installations.
- Added `setup_default_status()` method to activator to ensure `fuertewp_status` defaults to 'enabled' on new installs.
- Enhanced migrator to default plugin status to 'enabled' if not present in legacy options during upgrades.
- Ensured seamless upgrade path from Carbon Fields (1.7.x) to HyperFields (1.8+) with proper status migration.

# 1.9.0 / 2026-04-17
- Added **Blocked Updates** feature to completely prevent updates for specific plugins and themes.
- **Critical Security Feature**: Protect against supply chain attacks by locking plugins at safe versions when developer accounts are compromised
- Prevents auto-installing malicious code during supply chain attacks or developer account breaches
- Implemented full update blocking system that removes items from update transients.
- Blocked items are prevented from both automatic and manual updates.
- Added admin interface fields for selecting plugins/themes to block.
- Implemented update notice hiding for blocked items in WordPress admin.
- Added comprehensive configuration storage and migration support.
- Added unit and integration tests for blocked updates functionality.
- Enhanced documentation with supply chain attack scenarios and security guidance.
- Maintained backward compatibility with existing deferred updates feature.

# 1.8.1 / 2026-04-11
- Migrated entire configuration system from Carbon Fields to the new HyperFields library.
- Implemented `Fuerte_Wp_Config` as a centralized, thread-safe configuration manager.
- Introduced single-array storage pattern (`fuertewp_settings`) for improved performance and cleaner database footprint.
- Added recursive legacy configuration fallback to ensure 0-downtime migrations.
- Integrated HyperFields Data Tools for settings maintenance and migration.
- Removed legacy dependency on `htmlburger/carbon-fields`.
- Purged technical debt: removed manual Carbon Fields asset injection and Elementor conflict workarounds.
- Full PHP 8.2+ compatibility audit and strict types implementation.
- Added comprehensive unit tests for the new configuration and storage logic.

# 1.7.5 / 2025-11-18
- Prevent Carbon Fields from booting in Elementor editor to avoid JS conflicts.

# 1.7.4 / 2025-11-13
- Added comprehensive Login Security system with rate limiting and IP lockout functionality.
- Implemented failed login attempt tracking with configurable thresholds and lockout durations.
- Added real-time AJAX-powered admin interface for monitoring login attempts and managing lockouts.
- Introduced GDPR Privacy Notice feature with customizable message display on login and registration forms.
- Enhanced security with IP-based and username-based lockout mechanisms.
- Added support for blacklisted usernames during registration process.
- Implemented increasing lockout durations for repeated security violations.
- Added comprehensive logging system for security monitoring and forensic analysis.
- Introduced individual unblock functionality for specific IP/username combinations.
- Enhanced admin interface with export capabilities for security data.
- Performance optimizations and database cleanup automation for security logs.
- Improved code organization with dedicated login management and logging classes.
- Added comprehensive Login URL Hiding functionality to obscure wp-login.php access points.
- Implemented support for both query parameter mode (?custom-slug) and pretty URL mode (/custom-slug/).
- Added customizable login slug with default 'secure-login' option for easy configuration.
- Integrated wp-admin protection to redirect unauthorized admin area requests to custom login URL.
- Added hidden field validation to login forms for enhanced security against direct POST attacks.
- Comprehensive URL filtering system covering site_url, login_url, logout_url, lostpassword_url, and register_url.
- Full integration with existing super user bypass system and security logging.
- Implemented proper redirect handling with 404-like behavior for blocked login attempts.
- Enhanced security through obscurity while maintaining WordPress core compatibility.

# 1.6.0 / 2025-09-20
- Refactored auto-update system into dedicated `Fuerte_Wp_Auto_Update_Manager` class for better code organization and maintainability.
- Updated Carbon Fields dependency to the latest version for better PHP 8.x+ compatibility.
- Bug fixes and performance improvements.

# 1.5.1 / 2024-07-24
- Improved auto-updates. Added a new scheduled task to force WordPress to perform the update routine every 6 hours, only when some auto-updates are enabled.

# 1.4.12 / 2023-11-06
- Enhanced disabling of XML-RPC API.
- Disable re-running of the WP setup wizard.
- Disable execution of PHP files inside uploads folder.
- Updated Carbon Fields to fix PHP 8.x and WP 6.2 compatibility issues.

# 1.4.4 / 2023-02-20
- Avoids a PHP Warning when getting the website's domain name.

# 1.4.3 / 2022-11-28
- REST API enabled by default for all users. Needed for the modern WordPress themes and other plugins.
- Resolved an issue with Elementor Template Kits import.

# 1.4.2 / 2022-07-22
- PHP 8.x compatibility check.
- Tested up to WordPress 6.0.
- Added an option to enable/disble the sender email address.
- Added an option to disable Customizer additional CSS editor.
- Added an option to force REST API usage to logged in users only.
- Fixed a compatibility bug with Elementor's editor.

# 1.3.11 / 2022-01-11
- Fixed bug with un-trimmed advanced restrictions.
- Fixed a bug with cached options values (transient).

# 1.3.8 / 2021-10-08
- Option to configure Theme Editor restriction.
- Option to configure Plugin Editor restriction.
- Option to configure new plugins installation restriction.
- Option to configure new themes installation restriction.
- Fixed some translation errors.

# 1.3.5 / 2021-09-13
- PHP 7.3 compatibility.

# 1.3.1 / 2021-08-27
- Reworked as full featured plugin.
- Added an options page for easy configuration.
- New logo, courtesy of [Nicolás Franz](https://nicolasfranz.com). Many thanks, pal!

# 1.2.0 / 2021-08-13
- Converted to a plugin.
- Added ability to access plugins management, but don't allow install or upload new plugins. Also Fuerte-WP will auto-protect itself from deactivation.
- Added ability to restrict access to Permalinks configuration.
- Added ability to use custom site logo (from Customizer) as WP login logo.

# 1.1.3 / 2021-04-22
- Added ability to disable the old XML-RPC API.
- Now it hides ACF cog inside ACF meta fields UI, and prevent opening ACF custom post type (acf-field-group), to avoid non super admins to access ACF editing UI. So, non super-admin users can't access ACF Custom Fields UI, even if they put the URL directly into the address bar.

# 1.1.2 / 2021-04-16
- Added missing support to disable update emails for plugins and themes.

## 1.1.1 / 2021-04-09
- Added support to control several WP's automatic emails.
- Added support to disable WP admin bar for specific roles.

## 1.1.0 / 2021-04-07
- Fuerte-WP configuration file now lives outside wp-config.php file, into his own wp-config-fuerte.php file. This to make it easier to deploy it to several WP installations, without the need to edit the wp-config.php file in all of them. Check the readme on how to install it.
- Added option to enable or disable strong passwords enforcing.
- Added support to prevent use of weak passwords.
- Added support for remove_menu_page.
- Added ability to disable WordPress's new Application Passwords feature.

## 1.0.1 / 2020-10-29
- Now using a proper Class.
- Added option to change WP sender email address.
- Added configuration to remove custom submenu items (remove_submenu_page).
- Force user creation and editing to use WP default strong password suggestion, for non super users.
- Prevent admin accounts creation or edition, for non super users.
- Customizable not allowed error message.

## 1.0.0 / 2020-10-27
- Initial release.
- Enable and force auto updates for WP core.
- Enable and force auto updates for plugins.
- Enable and force auto updates for themes.
- Enable and force auto updates for translations.
- Disables email triggered when WP auto updates.
- Change [WP recovery email](https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/) so WP crashes will go to a different email than the Administration Email Address in WP General Settings.
- Disables WP theme and plugin editor for non super users.
- Remove items from WP menu for non super users.
- Restrict editing or deleting super users.
- Disable ACF Custom Fields editor access for non super users.
- Restrict access to some pages inside wp-admin, like plugins or theme uploads, for non super users. Restricted pages can be extended vía configuration.
