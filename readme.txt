=== Keep Pagination in Same Taxonomy ===
Contributors:      keith_wp
Author Link:       https://drakard.com/
Requires at least: 4.0 or higher
Tested up to:      6.5
Requires PHP:      5.6
Stable tag:        0.13
License:           BSD-3-Clause
License URI:       https://directory.fsf.org/wiki/License:BSD-3-Clause
Tags:              previous next posts, link posts, post navigation, navigation, previous post, next post, previous/next links, pagination, taxonomy pagination

Makes any previous/next post links use the same taxonomy as the current post.

== Description ==

Instantly join separate posts together by making the Previous/Next Post links on a Single Post look for other posts that share the same taxonomies.

This lightweight plugin simply adds a filter to your selected taxonomies (both default and custom) so that any theme can have its post navigation links stay in the same category as the currently viewed post.


== Installation ==

1. Upload the plugin files to the **/wp-content/plugins/keep-pagination-in-same-taxonomy/** directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to the Settings -> Reading page to configure the plugin, picking which taxonomies should be affected.


== Frequently Asked Questions ==

= Why Isn't It Linking To The Post I Expected? =

Be sure that you've only selected the taxonomies that are shared between the posts you want to link together AND that you aren't asking for the posts to match them all!

Eg. With the "ALL" option, if you select both Categories and Tags, then a post which has a category and some tags will NOT find other posts that only have the same category but no tags.

= Why Can't I See Any Difference? =

Check your theme is actually using Previous/Next Post links on the Single Post pages - eg. you may need to add that block to the default 2022 and 2023 WP themes, as they no longer seem to have that initially set up for you.


== Screenshots ==



== Changelog ==

= 0.13 =
* Tested with WP 6.4 and 6.5
* Bugfix: only show public taxonomies in the Settings
* Bugfix: call our Settings page later in the init() hook so we can include custom taxonomies (was inadvertently dependent on plugin activation order before)

= 0.12 =
* No changes, just a version number bump
* Tested with WP 6.3.2

= 0.11 =
* Bugfix: check if we've excluded all the terms from a taxonomy

= 0.1 =
* Initial release.



== Upgrade Notice ==


