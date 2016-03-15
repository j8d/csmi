=== Country Specific Menu Items ===
Contributors: Ryan Stutzman
Donate Link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=E2QJ57DQMCTHY
Plugin Name: Country Specific Menu Items
Tags: menu, nav-menu, navigation, navigation menu, geoip, location, country, menu items
Requires at least: 3.1
Tested up to: 4.4.2
Stable tag: 1.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Control the visibility of individual menu items based on a user's country.

== Description ==

Country Specific Menu Items* allows you to either hide or show individual menu items to your website's visitors located in different countries. The plugin adds additional settings to each menu item listed under Appearance > Menus. Just select one or more countries and choose whether to show or hide the menu item from those countries. CSMI provides the option to use MaxMind Geolite data to determine users' locations from their IPv4 or IPv6 Addresses. MaxMind databases are automatically updated every 30 days. Visit http://www.maxmind.com to learn more. *A City Specific Menu Items plugin is in the works. 

= Usage =

1. After installation, go to Appearance > Menus and create or edit a menu item.
2. Choose one or more countries in the select box titled "Set Visibility"
3. Select the radio button titled "Hide from these countries" to hide the menu item from all the countries you selected above, or select the radio button titled "Only show to these countries" to display the menu item only to the countries you selected above

== Installation ==

1. Upload the `/location-specific-menu-items-by-country/` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Optionally, click the button in the admin notification to download Geolite databases
4. Locate the 'Menus' item on the 'Appearance' menu
5. While editing a menu item, you will see a new option called Set Visibility Select one or more countries, and then select whether to only show or to hide these menu items from the specified countries.

== Screenshots ==

1. Go to Menus Page
2. Select Countries
3. Select Visibility

== Changelog ==

= 1.0.0 =
* First release!

= 1.0.1 =
* Changed the plugin to store user location is a session variable. This drastically increased page load speed by reducing calls to the database.
* Fixed problem with conflicting CSS from other plugins

= 1.0.2 =
* Changed plugin name from `Location Specific Menu Items by Country` to `Country Specific Menu Items`. I was originally planning to add different types of locations (city, state, continent, etc.) to this plugin and then remove the `by Country`, but have decided instead to just create a completely new plugin for each type of location.