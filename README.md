# WP Fuerte

A WordPress MU Plugin to enforce certain limits for users with wp-admin access, and to force some other security related tweaks.

## Why?

Because even if you choose to set an user only as Editor, some plugins require users to be an Administrator. And so many Administrators without limits could become an issue, security-wise.

Not only because admins can edit every single configuration inside WordPress. Administrators can also upload plugins or themes, and with them, compromise your WordPress installation.

WP Fuerte will limit some administrators from access critical WordPress areas that you can define.

And, as WP Fuerte lives as a WordPress [Must Use plugin](https://wordpress.org/support/article/must-use-plugins/), it cannot be disabled unless you have access to the server (FTP, SFTP, SSH, cPanel, etc.). 

## What WP Fuerte currently does

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

## How to install WP Fuerte

Upload ```wp-fuerte.php``` to ```/wp-content/plugins/mu-plugins/```

Copy _WP Fuerte default config array_ from inside ```wp-fuerte.php``` and paste it to your WordPress's ```wp-config.php```

Edit and tweak the config array as needed.

## Bugs, suggestions?

Please, open [an issue](https://github.com/TCattd/wp-fuerte/issues).

## Changelog
[Available here](https://github.com/TCattd/wp-fuerte/blob/master/CHANGELOG.md).
