=== BuddyPress Followers ===
Contributors: apeatling, r-a-y
Tags: buddypress, following, followers, connections
Requires at least: WP 3.2 / BP 1.5
Tested up to: WP 3.5.x / BP 1.7
Stable tag: trunk

== Description ==

Add the ability for users to follow others and keep track of their site activity. This plugin works exactly the same way that the friends component of BuddyPress works, however the connection does not need to be accepted by the person being followed.

The plugin integrates seamlessly with your site, adding a new activity stream tab, follow/unfollow buttons to user profiles and adds menus to display the users following/followed and total counts.

== Installation ==

1. Download, install and activate the plugin.
1. To follow a user, simply visit their profile and hit the follow button under their name.

== Frequently Asked Questions ==

#### Where to find support? ####

Please post on the [BuddyPress Followers support forum](http://buddypress.org/community/groups/buddypress-followers/forum/) at buddypress.org.
The forums on wordpress.org are rarely checked.

== Changelog ==

= 1.2 =
* Add BuddyPress 1.7 theme compatibility
* Add AJAX filtering to a user's "Following" and "Followers" pages
* Refactor plugin to use BP 1.5's component API
* Bump version requirements to use at least BP 1.5 (BP 1.2 is no longer supported)
* Deprecate older templates and use newer format (/buddypress/members/single/follow.php)

= 1.1.1 =
* Show the following / followers tabs even when empty.
* Add better support for WP Toolbar.
* Add better support for parent / child themes.
* Fix issues with following buttons when javascript is disabled.
* Fix issues with following activity overriding other member activity pages.
* Fix issue when a user has already been notified of their new follower.
* Fix issue when a user has disabled new follow notifications.
* Adjust some hooks so 3rd-party plugins can properly run their code.

= 1.1 =
* Add BuddyPress 1.5 compatibility.
* Add WP Admin Bar support.
* Add localization support.
* Add AJAX functionality to all follow buttons.
* Add follow button to group members page.
* Fix following count when a user is deleted.
* Fix dropdown activity filter for following tabs.
* Fix member profile following pagination
* Fix BuddyBar issues when a logged-in user is on another member's page.
* Thanks to mrjarbenne for sponsoring this release.

= 1.0 =
* Initial release.