/**
 * Plugin Name: GrabPress
 * Plugin URI: http://www.grab-media.com/publisher/grabpress
 * Description: Configure Grab's AutoPoster software to deliver fresh video
 * direct to your Blog. Link a Grab Media Publisher account to get paid!
 * Version: 2.3.4.1-06252013
 * Author: Grab Media
 * Author URI: http://www.grab-media.com
 * License: GPL2
 */

/**
 * Copyright 2012 Grab Networks Holdings, Inc.
 * (email: licensing@grab-media.com)
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Avoid jQuery conflicts with other plugins
(function($) {

	// Load GrabPress plugin language pack to TinyMCE
	tinymce.PluginManager.requireLangPack( 'blist' );

	// Create blist plugin
	tinymce.create( 'tinymce.plugins.blist', {
		/**
		 * Initializes the plugin after it is created
		 * @param  {tinymce.Editor} ed  Editor instance that the plugin is
		 * initialized in.
		 * @param  {String} url Absolute URL to where the plugin is located
		 */
		init: function( ed, url ) {
			// Register blcss button
			ed.addButton( 'blcss', {
				// Config button
				title: 'Insert Video',
				image: url + '/images/icons/g2.png',
				onclick: function() {
					// Define vars
					var body = $( 'body' ),
							postNumber = document.getElementById( 'post_ID' ).value,
							content = tinymce.activeEditor.getContent(),
							form = ''
					;

					// Build HTML for GrabPress form
					form = [
						'<form id="GrabPressForm" action="admin.php?page=catalog" method="post">\n',
						'	<input type="hidden" name="post_id" value="' + postNumber + '"\n',
						'	<input type="hidden" name="post_content2" value="' + content + '"\n',
						'</form>'
					].join( '\n' );

					// Append form HTML to body
					body.append( form );

					// Submit form
					$( '#GrabPressForm' ).submit();
				}
			});
		},

		/**
		 * Creates control instances based in the incoming name. This method is
		 * normally not needed since the addButton method of the tinymce.Editor
		 * class is an easier way to add buttons, but you sometimes need to
		 * create more complex controls like listboxes, split buttons etc. This
		 * method can be used to create those.
		 *
		 * @param {String} n Name of the control to create.
		 * @param {tinymce.ControlManager} cm Control manager to use in order to
		 * create new control.
		 * @return {tinymce.ui.Control} New control instance or null if no control
		 * was created.
		 */
		createControl: function( n, cm ){
			return null;
		}
	});

	// Register plugin
	tinymce.PluginManager.add( 'blist', tinymce.plugins.blist );

})(jQuery); // End $ scope