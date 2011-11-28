<?php
/**
 * Backwards compatibililty functions for BP 1.2.x.
 *
 * @author r-a-y
 * @package BP-Follow
 * @subpackage Backpat
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** BACKPAT FUNCTIONS ************************************************/

if ( !function_exists( 'bp_actions' ) ) :
function bp_actions() {
	do_action( 'bp_actions' );
}
add_action( 'wp', 'bp_actions', 3  );
endif;

if ( !function_exists( 'bp_is_current_component' ) ) :
function bp_is_current_component( $slug ) {
	global $bp;

	if ( $bp->current_component == $slug )
		return true;

	return false;
}
endif;

if ( !function_exists( 'bp_is_current_action' ) ) :
function bp_is_current_action( $action = '' ) {
	global $bp;

	if ( $action == $bp->current_action )
		return true;

	return false;
}
endif;

if ( !function_exists( 'bp_is_user' ) ) :
function bp_is_user() {
	return bp_is_member();
}
endif;

if ( !function_exists( 'bp_get_user_meta_key' ) ) :
function bp_get_user_meta_key( $key = false ) {
	return apply_filters( 'bp_get_user_meta_key', $key );
}
endif;

if ( !function_exists( 'bp_get_user_meta' ) ) :
function bp_get_user_meta( $user_id, $meta_key, $single = false ) {
	return get_user_meta( $user_id, bp_get_user_meta_key( $meta_key ), $single );
}
endif;

if ( !function_exists( 'bp_update_user_meta' ) ) :
function bp_update_user_meta( $user_id, $meta_key, $meta_value, $prev_value = '' ) {
	return update_user_meta( $user_id, bp_get_user_meta_key( $meta_key ), $meta_value, $prev_value );
}
endif;

if ( !function_exists( 'bp_core_delete_notifications_by_type' ) ) :
function bp_core_delete_notifications_by_type( $user_id, $component_name, $component_action ) {
	return BP_Core_Notification::delete_for_user_by_type( $user_id, $component_name, $component_action );
}
endif;

?>