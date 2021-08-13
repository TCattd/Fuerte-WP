# WP-Fuerte

![WP-Fuerte Logo](https://github.com/TCattd/WP-Fuerte/blob/master/assets-wp-repo/icon-256x256.png?raw=true)

Stronger WP. Limit access to critical WordPress areas, even other for admins.

WP-Fuerte is a WordPress Plugin to enforce certain limits for users with wp-admin access, and to force some other security related tweaks.

## Why?

Because even if you choose to set an user only as Editor, some plugins require users to be an Administrator. And so many Administrators without limits could become an issue, security-wise.

Not only because admins can edit every single configuration inside WordPress. Administrators can also upload plugins or themes, and with them, compromise your WordPress installation.

WP-Fuerte will limit some administrators from access critical WordPress areas that you can define.

WP-Fuerte auto-protect itself and cannot be disabled unless you have access to the server (FTP, SFTP, SSH, cPanel/Plesk, etc.) or your account is declared as a super-admin in WP-Fuerte configuration file.

## Features

- Configure your own super users that will not be affected by changes and tweaks enforced by WP-Fuerte.
- Enable and force auto updates for WP core.
- Enable and force auto updates for plugins.
- Enable and force auto updates for themes.
- Enable and force auto updates for translations.
- Disables several WP email notification to site admin or network admin.
- Disables WP Application Passwords feature.
- Disables XML-RPC API.
- Change [WP recovery email](https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/) so WP crashes will go to a different email than the Administration Email Address in WP General Settings.
- Change WP sender email address to match WP installed domain, to avoid receiving WP emails as spam (assuming your domain SPF records are properly configured).
- Customizable not allowed error message.
- Disables WP theme and plugin editor, for non super users.
- Remove items from WP menus, for non super users.
- Restrict editing or deleting super users, for non super users.
- Disables ACF Custom Fields editor access (ACF editor/creator backend UI), for non super users.
- Force users to use WP default strong password suggestion, for non super users.
- Prevent the use of weak passwords disabling the "Confirm use of weak password" checkbox.
- Prevent admin accounts creation or edition, for non super users.
- Restrict access to some pages inside wp-admin, like plugins or theme uploads, for non super users. Restricted pages can be extended vía configuration.
- Disable WP admin bar for specific roles. Defaults to disable it for: subscriber, customer.
- Disable access to Permalinks configuration.
- Disable Plugins installation (via WP's repo or upload). Also disable plugins deletion.
- Enable using Customizer custom logo as a WP login logo.

## How to install

Install the plugin. Activate it.

Set up a copy of the ```wp-config-fuerte.php``` file with your desired settings. Edit and tweak the configuration array as needed.

Upload your tweaked ```wp-config-fuerte.php``` file to your WordPress's root directory. This usually is where your wp-config.php file resides. WP-Fuerte will not run at all if ```wp-config-fuerte.php``` file doesn't exist in that location.

### Upgrade instructions

After updating WP-Fuerte, please check WP-Fuerte version number in your WP's plugins area. WP-Fuerte config file version will match that number.

Then check your own wp-config-fuerte.php file. If yours has a lower version number, then compare your config with the [default wp-config-fuerte.php file](https://github.com/TCattd/WP-Fuerte/blob/master/wp-config-fuerte.php) and add the new/missing settings to your file. You can use [Meld](https://meldmerge.org/) (or similars) to help you here.

Upload your updated wp-config-fuerte.php to your WordPress's root directory, as per install instructions.

Don't worry. New features will not run or affect you until you upgrade your config file and add the new/missing settings.

## FAQ
### Suggestions?

Please, open [a discussion](https://github.com/TCattd/WP-Fuerte/discussions).

### Support?, Bugs?

Please, open [an issue](https://github.com/TCattd/WP-Fuerte/issues).

### Changelog

[Available here](https://github.com/TCattd/WP-Fuerte/blob/master/CHANGELOG.md).
