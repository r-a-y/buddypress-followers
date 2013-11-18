<?php
/**
 * Backwards compatibililty functions for < BP 1.7.
 *
 * @author r-a-y
 * @package BP-Follow
 * @subpackage Backpat
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** 1.7 compat *****************************************************/

if ( ! function_exists( 'bp_get_template_part' ) ) :
function bp_get_template_part( $slug, $name = null ) {

	// Execute code for this part
	do_action( 'get_template_part_' . $slug, $slug, $name );

	// Setup possible parts
	$templates = array();
	if ( isset( $name ) )
		$templates[] = $slug . '-' . $name . '.php';
	$templates[] = $slug . '.php';

	// Allow template parts to be filtered
	$templates = apply_filters( 'bp_get_template_part', $templates, $slug, $name );

	// Return the part that is found
	return bp_locate_template( $templates, true, false );
}
endif;

if ( ! function_exists( 'bp_locate_template' ) ) :
function bp_locate_template( $template_names, $load = false, $require_once = true ) {

	// No file found yet
	$located            = false;
	$template_locations = bp_get_template_stack();

	// Try to find a template file
	foreach ( (array) $template_names as $template_name ) {

		// Continue if template is empty
		if ( empty( $template_name ) )
			continue;

		// Trim off any slashes from the template name
		$template_name  = ltrim( $template_name, '/' );

		// Loop through template stack
		foreach ( (array) $template_locations as $template_location ) {

			// Continue if $template_location is empty
			if ( empty( $template_location ) )
				continue;

			// Check child theme first
			if ( file_exists( trailingslashit( $template_location ) . $template_name ) ) {
				$located = trailingslashit( $template_location ) . $template_name;
				break 2;
			}
		}
	}

	// Maybe load the template if one was located
	if ( ( true == $load ) && !empty( $located ) )
		load_template( $located, $require_once );

	return $located;
}
endif;

if ( ! function_exists( 'bp_get_template_stack' ) ) :
function bp_get_template_stack() {
	global $wp_filter, $merged_filters, $wp_current_filter;

	// Setup some default variables
	$tag  = 'bp_template_stack';
	$args = $stack = array();

	// Add 'bp_template_stack' to the current filter array
	$wp_current_filter[] = $tag;

	// Sort
	if ( ! isset( $merged_filters[ $tag ] ) ) {
		ksort( $wp_filter[$tag] );
		$merged_filters[ $tag ] = true;
	}

	// Ensure we're always at the beginning of the filter array
	reset( $wp_filter[ $tag ] );

	// Loop through 'bp_template_stack' filters, and call callback functions
	do {
		foreach( (array) current( $wp_filter[$tag] ) as $the_ ) {
			if ( ! is_null( $the_['function'] ) ) {
				$args[1] = $stack;
				$stack[] = call_user_func_array( $the_['function'], array_slice( $args, 1, (int) $the_['accepted_args'] ) );
			}
		}
	} while ( next( $wp_filter[$tag] ) !== false );

	// Remove 'bp_template_stack' from the current filter array
	array_pop( $wp_current_filter );

	// Remove empties and duplicates
	$stack = array_unique( array_filter( $stack ) );

	return (array) apply_filters( 'bp_get_template_stack', $stack ) ;
}
endif;

if ( ! function_exists( 'bp_register_template_stack' ) ) :
function bp_register_template_stack( $location_callback = '', $priority = 10 ) {

	// Bail if no location, or function does not exist
	if ( empty( $location_callback ) || ! function_exists( $location_callback ) )
		return false;

	// Add location callback to template stack
	return add_filter( 'bp_template_stack', $location_callback, (int) $priority );
}
endif;

if ( ! function_exists( 'bp_deregister_template_stack' ) ) :
function bp_deregister_template_stack( $location_callback = '', $priority = 10 ) {

	// Bail if no location, or function does not exist
	if ( empty( $location_callback ) || ! function_exists( $location_callback ) )
		return false;

	// Add location callback to template stack
	return remove_filter( 'bp_template_stack', $location_callback, (int) $priority );
}
endif;

if ( ! function_exists( 'bp_get_template_locations' ) ) :
function bp_get_template_locations( $templates = array() ) {
	$locations = array(
		'buddypress',
		'community',
		''
	);
	return apply_filters( 'bp_get_template_locations', $locations, $templates );
}
endif;

if ( ! function_exists( 'bp_add_template_stack_locations' ) ) :
/**
 * @since 1.2
 */
function bp_add_template_stack_locations( $stacks = array() ) {
	$retval = array();

	// Get alternate locations
	$locations = bp_get_template_locations();

	// Loop through locations and stacks and combine
	foreach ( (array) $stacks as $stack )
		foreach ( (array) $locations as $custom_location )
			$retval[] = untrailingslashit( trailingslashit( $stack ) . $custom_location );

	return apply_filters( 'bp_add_template_stack_locations', array_unique( $retval ), $stacks );
}
endif;

// HOOKS ////////////////////////////////////////////////////////////

// Filter BuddyPress template locations
if ( ! has_filter( 'bp_get_template_stack', 'bp_add_template_stack_locations' ) ) {
	add_filter( 'bp_get_template_stack', 'bp_add_template_stack_locations' );
}

// Register template stacks
bp_register_template_stack( 'get_stylesheet_directory', 10 );
bp_register_template_stack( 'get_template_directory',   12 );
