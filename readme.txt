=== BuddyPress Follow ===
Contributors: apeatling, r-a-y
Tags: buddypress, following, followers, connections
Requires at least: WP 3.2 / BP 1.5
Tested up to: WP 4.4.x / BP 2.5.x
Stable tag: 1.2.2

== Description ==

Follow members on your BuddyPress site with this nifty plugin.

The plugin works similar to the friends component, however the connection does not need to be accepted by the person being followed.  Just like Twitter!

This plugin adds:

* Following / Followers tabs to user profile pages
* Follow / Unfollow buttons on user profile pages and in the members directory
* A new "Following" activity directory tab
* An "Activity > Following" subnav tab to a user's profile page
* Menu items to the WP Toolbar

**Translations**

BP Follow has been translated into the following languages by these awesome people:

* Brazilian Portuguese - [espellcaste](https://profiles.wordpress.org/espellcaste)
* German - [solhuebner](https://profiles.wordpress.org/solhuebner)

For bug reports or to add patches or translation files, visit the [BP Follow Github page](https://github.com/r-a-y/buddypress-followers).

== Installation ==

1. Download, install and activate the plugin.
1. To follow a user, simply visit their profile and hit the follow button under their name.


== Frequently Asked Questions ==

Check out the [BP Follow wiki](https://github.com/r-a-y/buddypress-followers/wiki).

== Changelog ==

= 1.2.2 =
* Fix deprecated notice in widget for those using WordPress 4.3+.
* Fix member filtering when custom follow slugs are in use.
* Increase selector scope in javascript so AJAX button works with pagination in member loops.
* Fix issue with bp_follow_stop_following() when relationship doesn't exist.
* Fix issue with member loop existence and follow user button defaults.
* Only show "Following" tab if user is logged in on member directory.
* Do not query for follow button if a user is on their own profile.
* Decode special characters in email subject and content.
* Do not an email notification to yourself.
* Allow plugins to bail out of saving a follow relationship into the database.

= 1.2.1 =
* Add "Mark as read" support for the Notifications component (only available on BP 1.9+)
* Add "Activity > Following" RSS feed support (only available on BP 1.8+)
* Allow users to immediately unfollow / follow a user after clicking on the "Follow" button
* Dynamically update follow count on profile navigation tabs after clicking on the "Follow" button
* Change follow button text to remove the username by popular request
* Add Brazilian Portuguese translation (props espellcaste)
* Add German translation (props solhuebner)
* Streamline javascript to use event delegation
* Fix various PHP warnings

= 1.2 =
* Add BuddyPress 1.7 theme compatibility
* Add AJAX filtering to a user's "Following" and "Followers" pages
* Refactor plugin to use BP 1.5's component API
* Bump version requirements to use at least BP 1.5 (BP 1.2 is no longer supported)
* Deprecate older templates and use newer format (/buddypress/members/single/follow.php)
* Add ability to change the widget title
* Thanks to the Hamilton-Wentworth District School Board for sponsoring this release

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