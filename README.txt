=== Fuerte-WP | WordPress Security, Auto-Updates and Admin Control ===
Contributors: tcattd
Tags: security, auto-updates, two-factor, login-security, maintenance
Stable tag: 1.12.0
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

WordPress security and maintenance plugin: schedule auto-updates, block malicious updates during supply chain attacks, enforce two-factor authentication, hide the login page, and control what administrators can do.

== Description ==

🛡️ **WordPress Security and Maintenance That Prevents Problems Before They Happen**

Every day, WordPress sites are compromised through supply chain attacks. A trustworthy plugin developer has their account hacked, malicious code ships as an "update", and thousands of sites auto-install it within hours. Fuerte-WP protects your site when developers cannot protect their own update systems.

Fuerte-WP combines four defenses in one lightweight plugin: **update management**, **admin oversight**, **login security**, and **two-factor authentication**. It is built for agencies, e-commerce stores, and anyone who manages WordPress sites and needs to sleep at night.

**🚨 CRITICAL: SUPPLY CHAIN ATTACK AND MALICIOUS UPDATE PROTECTION**

A supply chain attack happens when an attacker compromises a developer account and pushes a malicious update that thousands of sites auto-install before anyone notices. When you learn an attack is in progress, you need to act in minutes, not days.

Fuerte-WP gives you three update modes so you can react correctly:

* **Scheduled Updates**: choose how often WordPress checks for and applies updates. Options are 6, 12, 24, or 48 hours. Slower cycles give you a review window. Faster cycles keep client sites current.
* **Deferred Updates**: keep a plugin out of auto-updates while still letting you update it manually. Useful when you want to test a release on staging before it reaches production.
* **Blocked Updates**: completely freeze a plugin or theme. Neither WordPress nor manual clicks can update it. This is the supply chain defense. When a developer account is compromised, you block the plugin at its last known safe version and wait until the all-clear.

This is not a generic "disable updates" toggle. Deferred and Blocked are separate, intentional controls, so you can hold one compromised plugin back while the rest of the site keeps updating normally.

**⚡ AUTO-UPDATE MANAGEMENT FOR CORE, PLUGINS, THEMES, AND TRANSLATIONS**

Granular control over every update channel:

* **WordPress core** auto-updates (on or off)
* **Plugin** auto-updates (on or off, with per-plugin defer and block)
* **Theme** auto-updates (on or off, with per-theme defer and block)
* **Translation** auto-updates (on or off)
* **Update check frequency**: 6, 12, 24, or 48 hours
* **Zero performance impact**: all checks run in the background through a dedicated cron event. Page load times are unaffected.

You can configure this once on your main site and reuse the same file-based configuration across every site you manage.

**👑 ADMINISTRATOR OVERSIGHT AND ACCESS CONTROL**

Most WordPress security plugins assume the administrator is the threat. Fuerte-WP assumes the administrator is trusted but busy, and that you want to protect them from themselves and from each other.

* **Super User Access**: designate one or more super users by email. Super users bypass every restriction and are the only accounts that can change Fuerte-WP settings or disable the plugin.
* **Restrict Other Administrators**: prevent non-super administrators from installing unstable plugins, editing theme and plugin code, changing permalinks, or touching sensitive WordPress settings.
* **Hide and Block Admin Menus**: a searchable, discovery-driven interface lists every registered admin menu, submenu, and admin-bar node on your site. You select what to hide. Hide and block are unified: hiding a page also blocks direct URL access to it. A manual textarea stays available as a precision escape hatch for slugs the discovery scan does not surface.
* **Smart Block Targeting**: the block engine avoids over-blocking. Shared scripts like `edit.php` (Posts, Pages, and custom post types) and `index.php` are hide-only so you never strand a non-super user on a blank screen. Single-purpose core scripts (`themes.php`, `tools.php`, `plugins.php`) block by `$pagenow`. Plugin pages block by their `?page=` query argument.
* **Account Protection**: protect your own admin account from being modified or deleted by another administrator.

**🔒 LOGIN SECURITY (OPTIONAL, ON BY DEFAULT)**

Brute force attacks against `wp-login.php` and XML-RPC are the most common way WordPress sites are compromised. Fuerte-WP ships with a full login hardening suite:

* **Rate Limiting and Brute Force Protection**: block an IP address after too many failed login attempts. Progressive penalties apply to repeat offenders.
* **Lockout Protection**: escalating lockout windows for repeated security violations.
* **Hide Login URL / Custom Login URL**: move your login page away from the default `wp-login.php` and `wp-admin` paths so automated bots that scan for those endpoints find nothing. Your real login URL is whatever you choose.
* **Real-Time Monitoring**: a live dashboard shows login attempts, lockouts, and security events.
* **Registration Protection**: control who can register and from which IP ranges.

**🔐 TWO-FACTOR AUTHENTICATION (2FA) FOR ADMINS**

Since version 1.10.0, Fuerte-WP bundles the official WordPress Two-Factor library and enforces a safe provider policy:

* **Email codes** (default for enforced admins)
* **Authenticator app / TOTP** (Time-based One-Time Password, Google Authenticator, Authy, 1Password, etc.)
* **Recovery / backup codes**
* The insecure **Dummy Method** is stripped site-wide, even under `WP_DEBUG`.

**Enforce 2FA for Administrators** is on by default. Administrators and Super Admins are challenged with an emailed code at login even before they set up an authenticator app. Each admin can switch to TOTP from their own profile page. Enforcement is read-only: it never writes to user meta, so unchecking the box releases admins immediately. Fuerte super users always bypass enforcement.

Crash-safe coexistence: if you already run the standalone Two-Factor plugin, Fuerte-WP detects it and steps aside. No class-redeclare fatal, no duplicate provider screens.

Operator escape hatch: define `FUERTEWP_DISABLE_2FA` in `wp-config.php` to skip the bundled library entirely.

**🛠 REST API, XML-RPC, AND APP PASSWORD HARDENING**

Modern WordPress exposes several attack surfaces beyond the login form:

* **Disable Application Passwords** site-wide (on by default)
* **Disable the XML-RPC API** (on by default), removing the pingback vector and brute force amplification
* **Disable weak passwords** during user creation and password reset
* REST API and authentication filters centralized so you can lock down application access without editing code

**📧 EMAIL CONTROLS AND RECOVERY**

WordPress sends a lot of email. Fuerte-WP lets you redirect and silence it:

* Rewrite the sender address and name on every outgoing `wp_mail()` (falls back to `no-reply@<your-domain>` when left empty)
* Redirect **Recovery Mode** and fatal-error emails to a monitored address
* Toggle individual notifications: fatal errors, automatic updates, comment moderation, comment publication, password resets, personal-data export requests, new-user creation

**🌐 MULTISITE, PERFORMANCE, AND DEVELOPER FRIENDLINESS**

* **Multisite compatible**: network-activate for centralized management across every site on the network
* **Self-protecting**: non-super users cannot disable Fuerte-WP or change its settings
* **Performance optimized**: background cron processing, no per-request overhead
* **File-based configuration**: define `$fuertewp` in `wp-config-fuerte.php` for mass deployment. File config wins over the database, so the same settings ship to every site without touching the admin UI
* **Translation ready**: ships with Spanish (es_CL / es_ES); contribute more via translate.wordpress.org

**🔧 HOW FUERTE-WP WORKS**

Fuerte-WP follows a single-source-of-truth model. Configuration lives in one normalized array and is read the same way everywhere:

1. **Load**: a transient-cached config loader checks a `wp-config-fuerte.php` file first, then falls back to the database option saved by the admin UI. File always wins.
2. **Enforce**: a singleton enforcer applies every restriction, login rule, and update policy from that normalized array.
3. **Recover**: super users (matched by email, case-insensitive) bypass restrictions. Define `FUERTEWP_FORCE` to enforce even on super users, or `FUERTEWP_DISABLE` to switch the whole plugin off without uninstalling.

Because the enforcer reads one normalized array, there is no drift between what the admin UI shows and what the site enforces. After editing config logic in code, bust the transient with `delete_transient('fuertewp_config')` so the new rules take effect.

**📁 FILE-BASED CONFIGURATION FOR MASS DEPLOYMENT**

For agencies and platform teams, Fuerte-WP can be configured entirely from a file, with no admin UI clicks. Drop a `wp-config-fuerte.php` file in your `ABSPATH` directory defining a `$fuertewp` array:

```
<?php
$fuertewp = array(
    'general'      => array( 'sender_email_enable' => true ),
    'super_users'  => array( 'you@agency.com' ),
    'auto_updates' => array(
        'core' => true, 'plugins' => true, 'themes' => true,
        'translations' => true, 'frequency' => '12h',
    ),
    'restrictions' => array(
        'disable_theme_editor'  => true,
        'disable_plugin_editor' => true,
        'restapi_disable_app_passwords' => true,
        'disable_xmlrpc'        => true,
    ),
    'login_security' => array( 'login_security_enable' => true, 'two_factor_enable' => true ),
);
```

Commit this file to your deployment pipeline and every site in your fleet ships the same security baseline. The admin UI still renders for inspection, but saved values never override the file. This is the recommended path for WordPress multisite networks and managed-hosting platforms.

**📋 SECURITY HARDENING CHECKLIST**

Fuerte-WP ships with safe defaults so a fresh install is already hardened. The following are on by default and can be toggled on the Restrictions and Login Security tabs:

* Disable the Theme Editor and Plugin Editor (prevents code injection from the admin)
* Disable Theme Install and Plugin Install (prevents untrusted uploads)
* Disable the Customizer CSS Editor
* Restrict access to Permalinks and Advanced Custom Fields
* Disable Application Passwords and the XML-RPC API
* Disable weak passwords
* Enable login security, brute force protection, and registration protection
* Enforce two-factor authentication for administrators
* Send fatal-error and recovery-mode emails to a monitored address

Review the Restrictions tab after your first install and adjust to your workflow.

**🎯 PERFECT FOR:**

* Agencies managing many client WordPress websites
* E-commerce and WooCommerce stores that require maximum uptime
* Educational institutions and universities running WordPress multisite networks
* Enterprise and government deployments needing strict maintenance and change-control policies
* Developers who want a reproducible, file-driven security baseline
* Anyone serious about WordPress security, login protection, and update reliability

**⚡ INSTALL IN SECONDS, PROTECT FOR YEARS**

1. Install and activate Fuerte-WP
2. Add yourself as a super user (by email)
3. Configure your auto-update preferences and login security
4. Your site is now protected from supply chain attacks, brute force login attempts, and accidental admin changes

== Installation ==

1. Install Fuerte-WP from the WordPress plugin directory (Plugins > Add New > search "Fuerte-WP" or "WordPress security auto-updates").
2. Activate the plugin.
3. Go to Settings > Fuerte-WP.
4. Add your email as a super user. Super users bypass all restrictions.
5. Set your auto-update preferences (core, plugins, themes, translations, frequency).
6. Optionally enable login security, hide your login URL, and enforce 2FA for administrators.
7. You are protected.

For mass deployment, drop a `wp-config-fuerte.php` file in your `ABSPATH` directory defining a `$fuertewp` array. File configuration wins over the database. See the sample in `config-sample/`.

== Frequently Asked Questions ==

= How does supply chain attack protection work? =

When you learn that a plugin developer's account has been compromised and malicious updates are being distributed, open Fuerte-WP and block that plugin under Blocked Updates. This freezes the plugin at its last safe version and prevents your site from auto-installing malicious code, even while thousands of other sites are being compromised. Once the developer publishes a verified clean release, remove the block.

= What is the difference between Deferred and Blocked updates? =

**Deferred Updates**: the plugin will not auto-update, but you can still update it manually when you are ready. Good for testing a release on staging before it reaches production.

**Blocked Updates**: the plugin cannot update at all, neither automatically nor manually. Essential during a supply chain attack when any update could contain malicious code.

= Does Fuerte-WP include two-factor authentication (2FA)? =

Yes. Since 1.10.0, Fuerte-WP bundles the official WordPress Two-Factor library. Administrators can use Email codes, an Authenticator App (TOTP, compatible with Google Authenticator, Authy, 1Password), and Recovery Codes. "Enforce 2FA for Admins" is on by default. If you already run the standalone Two-Factor plugin, Fuerte-WP detects it and steps aside.

= Can other administrators disable Fuerte-WP? =

No. Only super users can modify Fuerte-WP settings or disable the plugin. Other administrators are restricted from making changes that could compromise your site's security. This makes Fuerte-WP self-protecting.

= How does the hide login URL / custom login URL feature work? =

You set a secret login slug. Fuerte-WP redirects the default `wp-login.php` and `wp-admin` paths away from unauthenticated visitors, so bots that scan for those endpoints find nothing. You and your team log in at your custom URL instead. Brute force attacks against the default login form stop almost entirely.

= Can I disable XML-RPC and Application Passwords? =

Yes, both are one-click toggles and both are on by default. Disabling XML-RPC removes the pingback vector and brute force amplification. Disabling Application Passwords prevents leaked credentials from being used against the REST API.

= Will this slow down my website? =

No. All maintenance and update checks run in the background through a dedicated cron event. Page load times are unaffected. Fuerte-WP is performance optimized.

= Does this work with WordPress multisite? =

Yes. Fuerte-WP is fully compatible with WordPress multisite and can be network-activated for centralized management across every site on the network.

= Can I configure Fuerte-WP without the admin UI? =

Yes. Define a `$fuertewp` array in `wp-config-fuerte.php` inside your `ABSPATH` directory. File configuration wins over the database, so the same baseline ships to every site you manage. See `config-sample/wp-config-fuerte.php` for the full shape.

= Is there an emergency kill switch? =

Yes. Server operators can create empty marker files in the WordPress root or one level above it: `.fuertewp-disable` disables Fuerte-WP completely, `.fuertewp-disable-mfa` disables only the bundled Two-Factor feature. The presence of the file is enough; remove it to re-enable. A marker in the parent directory applies to every site sharing that parent.

= Where can I find more documentation? =

[Full documentation is on GitHub](https://github.com/EstebanForge/Fuerte-WP/blob/master/README.md).

== Screenshots ==

1. Main settings page with super user configuration
2. Auto-update management with scheduling options
3. Deferred and Blocked Updates configuration
4. Two-Factor and Login Security dashboard with real-time monitoring
5. Discovery-driven admin menu and access control management

== Changelog ==

[See the complete changelog on GitHub](https://github.com/EstebanForge/Fuerte-WP/blob/master/CHANGELOG.md)

== Upgrade Notice ==

= 1.11.0 =
Advanced Restrictions now actually apply, and admin menu management is searchable. Review your Restrictions tab after upgrading; sensible defaults are enabled for non-super users.

= 1.10.0 =
Adds bundled Two-Factor authentication with admin enforcement. If you already run the standalone Two-Factor plugin, Fuerte-WP detects it and steps aside automatically.
