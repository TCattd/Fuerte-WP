# Fuerte-WP

<p align="center">
	<img src="https://github.com/TCattd/Fuerte-WP/blob/master/.wp-org-assets/icon-256x256.png?raw=true" alt="Fuerte-WP Logo" />
</p>

Stronger WP. Limit access to critical WordPress areas, even for other admins.

Fuerte-WP is a WordPress Plugin to enforce certain limits for users with wp-admin administrator access, and to force some other security related tweaks into WordPress.

Available at the official [WordPress.org plugins repository](https://wordpress.org/plugins/fuerte-wp/).

## Why?

Because even if you choose to set an user only as Editor, some plugins require users to be an Administrator. And so many Administrators without limits could become an issue, security-wise.

Not only because admins can edit every single configuration inside WordPress. Administrators can also upload plugins or themes, or even edit plugins and theme files (on by default), and with those capabitilies, compromise your WordPress installation.

Fuerte-WP will limit some administrators from access critical WordPress areas that you can define.

Fuerte-WP auto-protect itself and cannot be disabled, unless your account is declared as super user, or you have access to the server (FTP, SFTP, SSH, cPanel/Plesk, etc.).

## Features

- Configure your own super users that will not be affected by changes and tweaks enforced by Fuerte-WP.
- Enable and force auto updates for WordPress core, plugins, themes & translations.
- Disables several WP email notification to site admin or network admin.
- Disables WP Application Passwords feature.
- Disables XML-RPC API.
- Change WordPress [recovery email](https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/) so WP crashes will go to a different email than the Administration Email Address in WP General Settings.
- Change WordPress sender email address to match WP installed domain, to avoid receiving WP emails as spam (assuming your domain SPF records are properly configured).
- Customizable not allowed error message.
- Disables WordPress theme and plugin editor, for non super users.
- Remove (and restrict) items from WordPress menus, for non super users.
- Restrict editing or deleting super users, for non super users.
- Disables ACF Custom Fields editor access (ACF editor/creator backend UI), for non super users.
- Force users to use WordPress default strong password suggestion, for non super users.
- Prevent the use of weak passwords disabling the "Confirm use of weak password" checkbox.
- Prevent admin accounts creation or edition, for non super users.
- Restrict access to some pages inside WordPress admin panel, like plugins or theme uploads, for non super users. Restricted pages and areas can be extended vía configuration.
- Disable WP admin bar for specific roles. Defaults to disable it for: subscriber, customer.
- Disable access to Permalinks configuration.
- Disable Plugins installation (via WP's repo or upload). Also disable plugins deletion.
- Enable using Customizer custom logo as a WP login logo.

## How to install

1. Install Fuerte-WP from WordPress repository. Plugins > Add New > Search for: Fuerte-WP. Activate it.
2. Configure Fuerte-WP at Settings > Fuerte-WP.
3. Enjoy.

### Harder configuration (optional)

Fuerte-WP allows you to configure it "harder". This way, Fuerte-WP options inside wp-admin panel aren't even shown at all. Useful to mass deploy Fuerte-WP configuration to multiple WordPress installations.

To use the harder configuration, follow this steps:

- Download a copy of [```config-sample/wp-config-fuerte.php```](https://github.com/TCattd/Fuerte-WP/blob/master/config-sample/wp-config-fuerte.php) file, and set it up with your desired settings. Edit and tweak the configuration array as needed.

- Upload your tweaked ```wp-config-fuerte.php``` file to your WordPress's root directory. This usually is where your wp-config.php file resides.

- When Fuerte-WP detects that file, it will load the configuration from it. This will bypass the DB values from the options page, completely.

#### Config file updates

To check if your ```wp-config-fuerte.php``` file need an update, follow this steps:

Check the default [```config-sample/wp-config-fuerte.php```](https://github.com/TCattd/Fuerte-WP/blob/master/config-sample/wp-config-fuerte.php) file. The header of the sample config will have the version when it was last modified.

Then check out your own ```wp-config-fuerte.php``` file. If yours has a lower version number, then you need to update your settings array.

Compare your config with the [default wp-config-fuerte.php file](https://github.com/TCattd/Fuerte-WP/blob/master/config-sample/wp-config-fuerte.php) and add the new/missing settings to your file. You can use [Meld](https://meldmerge.org), [WinMerge](https://winmerge.org), [Beyond Compare](https://www.scootersoftware.com), [Kaleidoscope](https://kaleidoscope.app), [Araxis Merge](https://www.araxis.com/merge/), [Diffchecker](https://www.diffchecker.com) or any similar software diff to help you here.

Upload your updated ```wp-config-fuerte.php``` to your WordPress's root directory and replace the old one.

Don't worry. New Fuerte-WP features that need new configuration values will not run or affect you until you upgrade your config file and add the new/missing settings.

## FAQ

Check the [full FAQ here](https://github.com/TCattd/Fuerte-WP/blob/master/FAQ.md).

## Suggestions, Support

Please, open [a discussion](https://github.com/TCattd/Fuerte-WP/discussions).

## Bugs and Error reporting

Please, open [an issue](https://github.com/TCattd/Fuerte-WP/issues).

## Changelog

[Available here](https://github.com/TCattd/Fuerte-WP/blob/master/CHANGELOG.md).
