# Changelog

## 1.0.1 / 2020-10-29
- Now using a proper Class
- Added option to change WP sender email address.

## 1.0.0 / 2020-10-27
- Initial release
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
