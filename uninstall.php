<?php
// If uninstall is not initiated from within WordPress then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	wp_die('You are not allowed to do this');
	exit();
}
 
// Set option name string
$gp_key_option = 'grabpress_key';
$gp_user_id_option = 'grabpress_user_id';
$grabpress_login = 'grabpress';

// Handle single site uninstall
if ( ! is_multisite() ) {
	// Remove GrabPress API key from WPDB
	delete_option( $gp_key_option );
	delete_option( $gp_user_id_option );
	// Check if 'grabpress' user exists in WPDB and delete it if it does
	delete_user_by_login( $grabpress_login );
} else { // Handle WP Multisite uninstall
	// Access WPDB global
	global $wpdb;
	// Get all blog IDs from WP Multisite
	$blog_ids = $wpdb->get_col(
		'SELECT blog_id FROM $wpdb->blogs'
	);
	// Get blog ID of blog initiating the uninstall
	$original_blog_id = get_current_blog_id();
	// Loop through all blogs within the WP Multisite and remove the GrabPress
	// API key and 'grabpress' user from WPDB
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		delete_site_option( $gp_key_option );
		delete_site_option( $gp_user_id_option );
		delete_user_by_login( $grabpress_login );
	}
	// Switch back to original initiating blog
	switch_to_blog( $original_blog_id );
}

/**
 * Check if a user exists by login name and delete it if it does
 */
function delete_user_by_login( $login ) {
	// Check if 'grabpress' user exists in WPDB
	$user = get_user_by( 'login', $login );
	// If it exists
	if ( $user ) {
		// Delete it
		wp_delete_user( $user->id );
	}

}
?>