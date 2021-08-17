=== Fuerte-WP ===
Contributors: tcattd
Tags: security
Stable tag: 1.2.0
Requires at least: 5.8
Tested up to: 5.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Stronger WP. Limit access to critical WordPress areas, even other for admins.

== Description ==
Stronger WP. Limit access to critical WordPress areas, even other for admins.

Fuerte-WP is a WordPress Plugin to enforce certain limits for users with wp-admin access, and to force some other security related tweaks.

Learn more at [GitHub](https://github.com/TCattd/WP-Fuerte).

== Installation ==
1. Install Fuerte-WP from WP's repos. Plugins -> Add New -> Search for: Fuerte_WP. Activate it.
2. Grab a copy of [config-sample/wp-fuerte-config.php](https://github.com/TCattd/WP-Fuerte/blob/master/config-sample/wp-config-fuerte.php) an tweak the configuration array as you like.
3. Upload your tweaked [wp-fuerte-config.php](https://github.com/TCattd/WP-Fuerte/blob/master/wp-config-fuerte.php) file to your WordPress root directory. This usually is where your wp-config.php file resides. WP-Fuerte will not run at all if ```wp-config-fuerte.php``` file doesn't exist in that location.
4. Enjoy.

== Frequently Asked Questions ==
= Suggestions? =
Please, open [a discussion](https://github.com/TCattd/WP-Fuerte/discussions).

= Support?, Bugs? =
Please, open [an issue](https://github.com/TCattd/WP-Fuerte/issues).

== Screenshots ==

== Changelog ==
[Check the changelog at GitHub](https://github.com/TCattd/WP-Fuerte/blob/master/CHANGELOG.md).

== Upgrade Notice ==
After updating WP-Fuerte, please check WP-Fuerte version number in your WP's plugins area. WP-Fuerte config file version will match that number.

Then check your own wp-config-fuerte.php file. If yours has a lower version number, then compare your config with the [default wp-config-fuerte.php file](https://github.com/TCattd/WP-Fuerte/blob/master/wp-config-fuerte.php) and add the new/missing settings to your file. You can use [Meld](https://meldmerge.org/) (or similars) to help you here.

Upload your updated wp-config-fuerte.php to your WordPress's root directory, as per install instructions.

Don't worry. New features will not run or affect you until you upgrade your config file and add the new/missing settings.
