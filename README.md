# WP Fuerte

A WordPress MU Plugin to enforce certain limits for users with wp-admin access, and to force some other security related tweaks.

## Why?

Because even if you choose to set an user only as Editor, some plugins require users to be an Administrator. And so many Administrators without limits could become an issue, security-wise.

Not only because admins can edit every single configuration inside WordPress. Administrators can also upload plugins or themes, and with them, compromise your WordPress installation.

WP Fuerte will limit some administrators from access critical WordPress areas that you can define.

And, as WP Fuerte lives as a WordPress [Must Use plugin](https://wordpress.org/support/article/must-use-plugins/), it cannot be disabled unless you have access to the server (FTP, SFTP, SSH, cPanel, etc.).

## Features

- Configure your own super users that will not be affected by changes and tweaks enforced by WP Fuerte.
- Enable and force auto updates for WP core.
- Enable and force auto updates for plugins.
- Enable and force auto updates for themes.
- Enable and force auto updates for translations.
- Disables email notification triggered when WP auto updates.
- Disables WP Application Passwords feature.
- Change [WP recovery email](https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/) so WP crashes will go to a different email than the Administration Email Address in WP General Settings.
- Change WP sender email address to match WP installed domain, to avoid receiving WP emails as spam (assuming your domain SPF records are properly configured).
- Customizable not allowed error message.
- Disables WP theme and plugin editor, for non super users.
- Remove items from WP menus, for non super users.
- Restrict editing or deleting super users, for non super users.
- Disables ACF Custom Fields editor access, for non super users.
- Force users to use WP default strong password suggestion, for non super users.
- Prevent the use of weak passwords disabling the "Confirm use of weak password" checkbox.
- Prevent admin accounts creation or edition, for non super users.
- Restrict access to some pages inside wp-admin, like plugins or theme uploads, for non super users. Restricted pages can be extended vía configuration.

## How to install

Upload ```wp-fuerte.php``` to ```/wp-content/plugins/mu-plugins/wp-fuerte.php```

Set up a copy of the ```wp-config-fuerte.php``` file with your desired settings. Edit and tweak the configuration array as needed.

Upload your already set up ```wp-config-fuerte.php``` file to your WordPress's root directory. This usually is where your wp-config.php file resides. WP Fuerte will not run at all if ```wp-config-fuerte.php``` file doesn't exist in that location.

## Suggestions?

Please, open [a discussion](https://github.com/TCattd/wp-fuerte/discussions).

## Bugs?

Please, open [an issue](https://github.com/TCattd/wp-fuerte/issues).

## Changelog
[Available here](https://github.com/TCattd/wp-fuerte/blob/master/CHANGELOG.md).
