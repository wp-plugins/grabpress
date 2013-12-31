<?php
// If uninstall is not initiated from within WordPress then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Set option name string
$option_name = 'grabpress_key';

// Handle single site uninstall
if ( ! is_multisite() ) {
	// Remove GrabPress API key from WPDB
	delete_option( $option_name );
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
	// API key from WPDB
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		delete_site_option( $option_name );
	}
	// Switch back to original initiating blog
	switch_to_blog( $original_blog_id );
}
?>