<?php

/**
 * Catches any visits to the "Followers (X)" tab on a users profile.
 *
 * @uses bp_core_load_template() Loads a template file.
 */
function bp_follow_screen_followers() {
	global $bp;

	do_action( 'bp_follow_screen_followers' );

	if ( isset( $_GET['new'] ) )
		bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->follow->id, 'new_follow' );

	bp_core_load_template( 'members/single/followers' );
}

/**
 * Catches any visits to the "Following (X)" tab on a users profile.
 *
 * @uses bp_core_load_template() Loads a template file.
 */
function bp_follow_screen_following() {
	do_action( 'bp_follow_screen_following' );

	bp_core_load_template( 'members/single/following' );
}

/**
 * Catches any visits to the "Activity > Following" tab on a users profile.
 *
 * @uses bp_core_load_template() Loads a template file.
 */
function bp_follow_screen_activity_following() {
	bp_update_is_item_admin( is_super_admin(), 'activity' );
	do_action( 'bp_activity_screen_following' );
	bp_core_load_template( apply_filters( 'bp_activity_template_following', 'members/single/home' ) );
}

/** TEMPLATE LOADER ************************************************/

/**
 * Filter the template location so that templates can be stored in the plugin folder, but
 * overridden by templates of the same name and sub folder location in the theme.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_load_template_filter( $found_template, $templates ) {
	global $bp;

	/**
	 * Only filter the template location when we're on the follow component pages.
	 */
	if ( !bp_is_current_component( $bp->follow->followers->slug ) && !bp_is_current_component( $bp->follow->following->slug ) )
		return $found_template;

	foreach ( (array) $templates as $template ) {
		if ( file_exists( STYLESHEETPATH . '/' . $template ) )
			$filtered_templates[] = STYLESHEETPATH . '/' . $template;
		elseif ( is_child_theme() && file_exists( TEMPLATEPATH . '/' . $template ) )
			$filtered_templates[] = TEMPLATEPATH . '/' . $template;
		else
			$filtered_templates[] = dirname( __FILE__ ) . '/templates/' . $template;
	}

	$found_template = $filtered_templates[0];

	return apply_filters( 'bp_follow_load_template_filter', $found_template );
}
add_filter( 'bp_located_template', 'bp_follow_load_template_filter', 10, 2 );

