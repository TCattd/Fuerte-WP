# WP Fuerte

A WordPress MU Plugin to enforce certain limits for users with wp-admin access.

## Why?

Because even if you choose to set an user only as Editor, some plugins require users to be an Administrator. And so many Administrators without limits could become an issue, security-wise.

Not only because admins can edit every single configuration inside WordPress. Administrators can also upload plugins or themes, and with them, compromise your WordPress installation.

WP Fuerte will limit some administrators from access critical WordPress areas that you can define.

And, as WP Fuerte lives as a WordPress [Must Use plugin](https://wordpress.org/support/article/must-use-plugins/), it cannot be disabled unless you have access to the server (FTP, SFTP, SSH, cPanel, etc.). 

## How to install

Upload ```wp-fuerte.php``` to ```/wp-content/plugins/mu-plugins/```

Copy _WP Fuerte default config array_ from inside ```wp-fuerte.php``` and paste it to your WordPress's ```wp-config.php```

Edit and tweak the config array as needed.

## Bugs, suggestions?

Please, open [an issue](https://github.com/TCattd/wp-fuerte/issues).

## Changelog
[Available here](https://github.com/TCattd/wp-fuerte/blob/main/CHANGELOG.md).
