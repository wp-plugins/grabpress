<?php

// Define class only if not defined already
if ( ! class_exists( 'Grabpress_API' ) ) {
	/**
	 * Grabpress_API is a class that handles communication with the GrabPress backend.
	 *
	 * @author Grab Media
	 */
	class Grabpress_API {
		/**
		 * Makes call to API using WP HTTP API
		 * @param  string  $method   Method of call being made
		 * @param  string  $resource API end point to call
		 * @param  array   $data     Associative array containing request data
		 * @param  boolean $auth     Does the call require auth credentials
		 * @return string            Response from API
		 */
		static function call( $method, $resource, $data = array(), $auth = false ) {
			// Log caller debug info
			Grabpress::log();

			// Create empty array to holding request args
			$args = array();

			// Convert data array to JSON
			$json = json_encode( $data );

			// Get API location for current ENV
			$apiLocation = self::get_location();

			// Build request URL
			$request_url = 'http://' . $apiLocation . $resource;

			// If user auth credentials provided
			if ( isset( $auth, $data['user'], $data['pass'] ) ) {
				// Add to request headers
				$args['headers'] = array(
					'Authorization' => 'Basic ' . base64_encode( $data['user'] . ':' . $data['pass'] ),
				);
			}

			// Set additional options and params based on call method
			switch ( $method ) {
				case 'GET':
					$params = '';
					$params .= strstr( $resource, '?' ) ? '&' : '?';
					$params .= http_build_query( $data );
					$request_url .= $params;
					$args['timeout'] = 10;

					// Call API using WP HTTP API
					try {
						$response = wp_remote_get( $request_url, $args );
					} catch ( Exception $e ) { // If request unsuccessful
						// Log custom fatal error
						Grabpress::abort( 'API call error: ' . $e->getMessage() );
					}
					break;
				case 'POST';
					// Convert JSON to associative array
					$data = json_decode( $json, true );
					$args['body'] = $data;

					// Call API using WP HTTP API
					try {
						$response = wp_remote_post( $request_url, $args );
					} catch ( Exception $e ) { // If request unsuccessful
						// Log custom fatal error
						Grabpress::abort( 'API call error: ' . $e->getMessage() );
					}
					break;
				case 'PUT';
					// Convert JSON to associative array
					$data = json_decode( $json, true );
					$args['method'] = 'PUT';
					$args['body'] = $data;

					// Call API using WP HTTP API
					try {
						$response = wp_remote_post( $request_url, $args );
					} catch ( Exception $e ) { // If request unsuccessful
						// Log custom fatal error
						Grabpress::abort( 'API call error: ' . $e->getMessage() );
					}
					break;
				case 'DELETE';
					$args['method'] = 'DELETE';

					// Call API using WP HTTP API
					try {
						$response = wp_remote_request( $request_url, $args );
					} catch ( Exception $e ) { // If request unsuccessful
						// Log custom fatal error
						Grabpress::abort( 'API call error: ' . $e->getMessage() );
					}
					break;

				case "POST_CURL":
				case "PUT_CURL":
					// Start the curl setup
					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, $request_url );
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
					//curl_setopt( $ch, CURLOPT_VERBOSE, true );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
						'Content-type: application/json'
					) );

					// If user auth credentials provided
					if( isset($auth) && isset($data['user']) && isset($data['pass'])){
						curl_setopt($ch, CURLOPT_USERPWD, $data['user'] . ":" . $data['pass']);
					}

					if( $method == "POST_CURL" ) {
						curl_setopt( $ch, CURLOPT_POST, true );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $json );
					} else {
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $json );
					}

					try {
						$response = curl_exec( $ch );
					} catch (Exception $e) {
						GrabPress::abort( 'API call error: '.$e->getMessage());
					}
					$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
					curl_close( $ch );
					if ($response && ($status < 400 || $status == 404)) {//should check for http status code less than 400 too
						GrabPress::log( 'status = ' . $status . ', response =' . $response );
						return $response;
					} else {
						throw new Exception('API call error with status = ' . $status . ' and response =' . $response);
					}
					break;
			}

			// Debug only!!! Next few lines
			// echo 'RESPONSE: ';
			// var_dump( $response );
			// echo '<br /><br />';

			// Check status of active HTTP request, last HTTP code received
			$status = wp_remote_retrieve_response_code( $response );

			// If HTTP code is not 400 series
			if ( $response && $status < 400 && ! is_wp_error( $response ) ) {
				// Log status
				Grabpress::log( 'status = ' . $status . ', response =' . json_encode( $response ) );

				// Return response from HTTP request
				return $response['body'];
			} else { // Else is a 404 or like error
				// Generate custom exception error
				throw new Exception( 'API call error with status = ' . $status . ' and response =' . json_encode( $response ) );
			}
		}

		/**
		 * Creates a connection between WP user and backend
		 * @return boolean User successfully created/updated
		 */
		static function create_connection() {
			// Log caller debug info
			Grabpress::log();

			// Get/generate user data
			$user_url      = get_site_url();
			$user_nicename = Grabpress::$grabpress_user;
			$user_login    = $user_nicename;
			$url_array     = explode( '/', $user_url );
			$email_host    =  substr( $url_array[ 2 ], 4, 13 );
			$email_dir     = isset( $url_array[ 3 ] ) ? $url_array[ 3 ] : '';
			$user_email    = md5( uniqid( rand(), TRUE ) ) . '@grab.press';
			$display_name  = 'GrabPress';
			$nickname      = 'GrabPress';
			$first_name    = 'Grab';
			$last_name     = 'Press';

			// Build post data array
			$post_data = array(
				'user' => array(
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'email'      => $user_email,
				),
			);

			// Fetch user data from backend
			$user_json = self::call( 'POST', '/user', $post_data );
			$user_data = json_decode( $user_json );

			// Get API key from user data
			$api_key = $user_data->user->access_key;

			// IF API key exists
			if ( $api_key ) {
				// Store API key in WPDB
				update_option( 'grabpress_key', $api_key );
			}

			// Get API key from WPDB
			Grabpress::$api_key = get_option( 'grabpress_key' );

			// Add additional info to user
			$description = 'Bringing you the best media on the Web.';
			$role = 'editor'; // Minimum for auto-publish (author)

			// Get current logged in user
			$grab_user = Grabpress::get_user_by('login');

			// If user exists
			if ( isset( $user_data ) ) {
				// Create message to be outputted
				$msg = 'User Exists (' . $user_login . '): ' . $user_data->user->id;
			}

			if ( $grab_user !== false ) {
				// Build user data array
				$user = array(
					'ID'            => $grab_user->id,
					'user_login'    => $user_login,
					'user_nicename' => $user_nicename,
					'user_url'      => $user_url,
					'user_email'    => $user_email,
					'display_name'  => $display_name,
					'user_pass'     => Grabpress::$api_key,
					'nickname'      => $nickname,
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'description'   => $description,
					'role'          => $role,
				);

				// Update user info in WPDB and get user ID for user changed
				// Has to use wp_update_user instead of wp_insert_user because wp_inset_user does not encrypt password as of wp3.7.1
				$user_id = wp_update_user( $user );

			} else { // User doesnt exist, store password with new data
				// Build user data array
				$user = array(
					'user_login'    => $user_login,
					'user_nicename' => $user_nicename,
					'user_url'      => $user_url,
					'user_email'    => $user_email,
					'display_name'  => $display_name,
					'user_pass'     => Grabpress::$api_key ,
					'nickname'      => $nickname,
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'description'   => $description,
					'role'          => $role,
				);

				// Insert user info in WPDB and get user ID for user changed
				$user_id = wp_insert_user( $user );
			}

			// If no user ID returned
			if ( ! isset( $user_id ) ) {
				// Log custom fatal error
				Grabpress::abort( 'Error creating user.' );
			}

			// User successfully created/updated
			return true;
		}

		/**
		 * Creates GrabPress feed
		 * @param  array $params Associative array containing params
		 */
		static function create_feed( $params ) {
			// Log caller debug info
			Grabpress::log();

			// If valid API key
			if ( self::validate_key() ) {
				// Get channels from params
				$channels = $params[ 'channels' ];

				// Convert channels string to array
				$channels_list = implode( ',', $channels );

				// Get total # of channels chosen by the user
				$channels_count = count( $channels_list );

				// Get total # of channels from catalog
				$channels_total = $params['channels_total'];

				// If chosen channels is equal to channels in catalog
				if ( $channels_count == $channels_total ) {
					// Set channel list to empty string
					$channels_list = '';
				}

				// URL encode name
				$name = rawurlencode( $params[ 'name' ] );

				// Get providers from params
				$providers = $params['providers'];

				// Convert providers string to array
				$providers_list = implode( ',', $providers );

				// Get total # of providers chosen by user
				$providers_count = count( $providers );

				// Get total # of providers from catalog
				$providers_total = $params['providers_total'];

				// If chosen providers is equal to providers in catalog
				if ( $providers_count == $providers_total ) {
					// Set provider list to empty string
					$providers_list = '';
				}

				// Generate channel URL to call
				$url = Grabpress::generate_catalog_url( array(
					'keywords_and'    => $params['keywords_and'],
					'keywords_not'    => $params['keywords_not'],
					'keywords_or'     => $params['keywords_or'],
					'keywords_phrase' => $params['keywords_phrase'],
					'providers'       => $providers_list,
					'categories'      => $channels_list,
				) );

				// Get connector ID
				$connector_id = self::get_connector_id();

				// Get category list from params
				$category_list = $params[ 'category' ];

				// Get total # of categories
				$category_count = count( $category_list );

				// Create empty categories array
				$categories = array();

				// If category list is an array
				if ( is_array( $category_list ) ) {
					// Loop through category list
					foreach ( $category_list as $cat ) {
						// Get category name from WPDB
						$categories[] = get_cat_name( $cat );
					}
				} else { // If not an array
					// Set feed as uncategorized
					$categories[] = 'Uncategorized';
				}

				// Get schedule from params
				$schedule = $params['schedule'];

				// If click to play is true
				if ( '1' == $params['click_to_play'] ) {
					$auto_play = '1'; // Set autoplay to true
				} else {
					// Set autoplay to false
					$auto_play = '0';
				}

				// Get author ID from params
				$author_id = (int) $params['author'];

				// Fetch feeds
				$feeds = self::get_feeds();

				// Get total # of feeds
				$num_feeds = count( $feeds );

				// If no feeds
				if ( 0 == $num_feeds ) {
					// Set watchlist to true
					$watchlist = 1;
				} else {
					// Set watchlist to false
					$watchlist = 0;
				}

				// Get submission template ID
				$submission_template_id = self::get_shortcode_template_id();

				// Build post dat array
				$post_data = array(
					'feed' => array(
						'submission_template_id' => "$submission_template_id",
						'name'                   => $name,
						'posts_per_update'       => $params['limit'],
						'url'                    => $url,
						'update_frequency'       => $params['schedule'],
						'auto_play'              => $auto_play,
						'watchlist'              => $watchlist,
						'exclude_tags'           => stripslashes( $params['exclude_tags'] ),
						'include_tags'           => stripslashes( $params['include_tags'] ),
						'custom_options'         => array(
							'category'  => $categories,
							'publish'   => (bool) ( $params['publish'] ),
							'author_id' => $author_id
						),
					),
				);

				// Send feed data to backend and listen for response
				$response_json = self::call( 'POST_CURL', '/connectors/' . $connector_id . '/feeds/?api_key=' . Grabpress::$api_key, $post_data );
				$response_data = $response_json;

				// If feed is active
				if ( true == $response_data->feed->active ) {
					// Output message to admin dashboard
					Grabpress::$feed_message = 'Grab yourself a coffee. Your videos are on the way!';
				} else { // Feed inactive
					// Output support message to admin dashboard
					Grabpress::$feed_message = 'Something went wrong grabbing your feed. Please <a href = "https://getsatisfaction.com/grabmedia" target="_blank">contact Grab support</a>\n' . $response_data;
				}
			} else { // No valid key
				// Output message regarding invalid key to admin dashboard
				Grabpress::$feed_message = 'Your API key is no longer valid. Please <a href = "https://getsatisfaction.com/grabmedia" target="_blank">contact Grab support.</a>';
			}
		}

		/**
		 * Delete feed from backed by ID
		 * @param  string $feed_id ID of feed to be deleted
		 */
		static function delete_feed( $feed_id ) {
			// Get connector ID
			$connector_id = self::get_connector_id();

			// Delete feed from backend by ID
			self::call( 'DELETE', '/connectors/' . $connector_id . '/feeds/' . $feed_id . '?api_key=' . Grabpress::$api_key, $feed_id );
		}

		/**
		 * Determine if provider has opted out
		 * @param  object $provider JSON object containing provider info
		 * @return boolean           Provider opted out
		 */
		static function filter_out_providers( $provider ) {
			return ! $provider->provider->opt_out;
		}

		/**
		 * Edit feed on backend
		 * @param  array $request Associative array containing request data
		 */
		static function edit_feed( $request ) {
			// Get feed ID from request
			$feed_id = $request['feed_id'];

			// Get name from request
			$name = htmlspecialchars( $request['name'] );

			// Get requested channels
			$channels = $request[ 'channels' ];

			// Convert channels string to array
			$channels_list = implode( ',', $channels );

			// Get total # of providers requested by user
			$channels_count = count( $channels );

			// Get total # of providers returned from catalog
			$channels_total = $request['channels_total'];

			// If total channels requested equals total returned from catalog
			if ( $channels_count == $channels_total ) {
				// Set channel_list to empty string
				$channels_list = '';
			}

			// Get requested providers
			$providers = $request['providers'];

			// Convert providers string to array
			$providers_list = implode( ',', $providers );

			// Get total # of requested providers
			$providers_count = count( $providers );

			// Get total # of providers returned from catalog
			$providers_total = $request['providers_total'];

			// If total providers requested equals total returned from catalog
			if ( $providers_count == $providers_total ) {
				// Set providers_list to empty string
				$providers_list = '';
			}

			// Build request data array
			$request_data = array(
				'keywords_and'    => $request['keywords_and'],
				'keywords_not'    => $request['keywords_not'],
				'keywords_or'     => $request['keywords_or'],
				'keywords_phrase' => $request['keywords_phrase'],
				'providers'       => $providers_list,
				'categories'      => $channels_list,
			);

			// Generate request URL based on request data
			$request_url = Grabpress::generate_catalog_url( $request_data );

			// Get connector ID
			$connector_id = self::get_connector_id();

			// Get active status from request
			$active = (bool) $request['active'];

			// Get category list from request
			$category_list = $request[ 'category' ];

			// Get total # of categories requested
			$category_count = count( $category_list );

			// Create empty array to hold categories
			$categories = array();

			// If category list is an array
			if ( is_array( $category_list ) ) {
				// Loop through category list
				foreach ( $category_list as $cat ) {
					// Push category names into categories array
					$categories[] = get_cat_name( $cat );
				}
			} else { // Not an array
				// Set as uncategorized
				$categories[] = 'Uncategorized';
			}

			// If click to play
			if ( '1' == $request['click_to_play'] ) { // Defaults to false
				// Set autoplay = true
				$auto_play = '1';
			} else {
				// Set autoplay = false
				$auto_play = '0';
			}

			// Get author ID from request
			$author_id = (int) $request['author'];

			$post_data = array(
				'feed' => array(
					'active'           => $active,
					'name'             => $name,
					'posts_per_update' => $request['limit'],
					'url'              => $request_url,
					'update_frequency' => $request['schedule'],
					'auto_play'        => $auto_play,
					'exclude_tags'     => stripslashes( $request['exclude_tags'] ),
					'include_tags'     => stripslashes( $request['include_tags'] ),
					'custom_options'   => array(
						'category'  => $categories,
						'publish'   => (bool) ( $request['publish'] ),
						'author_id' => $author_id,
					),
				),
			);

			// Make call to update feed
			$response = self::call( 'PUT_CURL', '/connectors/' . $connector_id . '/feeds/' . $feed_id . '?api_key=' . Grabpress::$api_key, $post_data );
		}

		/**
		 * Fetches channels from local 'cache' or backend
		 * @return object JSON object containing channels data
		 */
		static function get_channels() {
			// Try fetching channels
			try {
				// If channels 'cached' locally
				if ( isset( Grabpress::$channels ) ) {
					// Return 'cached' version
					return Grabpress::$channels;
				}

				// Fetch channels from backend
				$json_channel = self::get_json( 'http://catalog.' . Grabpress::$environment . '.com/catalogs/1/categories' );
				$channels_list = json_decode( $json_channel );

				// If channels exist
				if ( $channels_list ) {
					// Sort channels alphabetically
					uasort( $channels_list, array( 'Grabpress_API', 'sort_channels' ) );

					// 'Cache' channels locally
					Grabpress::$channels = $channels_list;
				}
			} catch ( Exception $e ) { // If fetch fails
				// Create empty array
				$channels_list = array();

				// Log exception error message
				Grabpress::log( 'API call exception: ' .$e->getMessage() );
			}

			// Returned groomed channels list
			return $channels_list;
		}

		/**
		 * Fetch connector data from locally stored connector or API
		 * @return array|boolean Returns either connector data or false
		 */
		static function get_connector() {
			// Log caller debug info
			Grabpress::log();

			// If locally stored connector
			if ( Grabpress::$connector ) {
				// Return it
				return Grabpress::$connector;
			}

			// If valid key
			if ( self::validate_key() ) {
				// Build XML-RPC URL
				$rpc_url = get_bloginfo( 'url' ) . '/xmlrpc.php';

				// Fetch connectors data from API
				$connectors_json =  self::call( 'GET', '/connectors?api_key=' . Grabpress::$api_key );
				$connectors_data = json_decode( $connectors_json );

				// Get array size of connectors data
				$connectors_count = count( $connectors_data );

				// Loop through each item in the connectors data array
				for ( $i = 0; $i < $connectors_count; $i++ ) {
					// Get connector from current iteration
					$connector = $connectors_data[ $i ]->connector;

					// If connector's destination address matches the XML-RPC URL
					if ( $connector->destination_address == $rpc_url ) {
						// Get connector ID
						$connector_id = $connector->id;

						// Store connector locally
						Grabpress::$connector = $connector;
					}
				}

				// If connector ID is not set
				if ( ! isset( $connector_id ) ) {
					// Create connector
					$connector_types_json = self::call( 'GET', '/connector_types?api_key=' . Grabpress::$api_key );

					// Convert response to PHP JSON object
					$connector_types = json_decode( $connector_types_json );

					// Get total # of connector types
					$types_count = count( $connector_types );

					// Set safe default for connector type id
					$connector_type_id = null;

					// Loop through each type
					for ( $i = 0; $i < $types_count; $i++ ) {
						// Get connector type
						$connector_type = $connector_types[$i]->connector_type;
						// If name is wordpress
						if ( 'wordpress' == $connector_type->name ) {
							// Get connector type ID
							$connector_type_id = $connector_type->id;
						}
					}

					// If connector type ID is not set
					if ( ! $connector_type_id ) {
						// Log custom fatal error
						Grabpress::abort( 'Error retrieving Autoposter id for connector name "wordpress"' );
					}

					// Reference global blog ID
					global $blog_id;

					// Build connector post array
					$connector_post = array(
						'connector' => array(
							'connector_type_id'   => $connector_type_id,
							'destination_name'    => get_bloginfo( 'name' ),
							'destination_address' => $rpc_url,
							'username'            =>'grabpress',
							'password'            => Grabpress::$api_key,
							'custom_options'      => array(
								'blog_id' => $blog_id
							),
						),
					);

					// Fetch connector data from API
					$connector_json = self::call( 'POST',  '/connectors?api_key='.Grabpress::$api_key, $connector_post );
					$connector_data = json_decode( $connector_json );

					// Store connector locally
					Grabpress::$connector = $connector_data->connector;
				}

				// Return locally stored connector
				return Grabpress::$connector;
			} else { // No valid key
				// Output message to admin dashboard
				Grabpress::$feed_message = 'Your API key is no longer valid. Please <a href = "https://getsatisfaction.com/grabmedia" target="_blank">contact Grab support.</a>';

				// Return false response
				return false;
			}
		}

		/**
		 * Get connector ID If exists
		 * @return string|array Connector ID or connector array
		 */
		static function get_connector_id() {
			// Fetch connector
			$connector = self::get_connector();

			// If connector exists
			if ( $connector ) {
				// Return connector ID
				return self::get_connector()->id;
			} else { // If not
				// Return connector itself
				return $connector;
			}
		}

		/**
		 * Get feed from backend by ID
		 * @param  string $feed_id ID of feed to fetch
		 * @return object          JSON object containing feed data
		 */
		static function get_feed( $feed_id ) {
			// Log caller debug info
			Grabpress::log();

			// If valid key
			if ( self::validate_key() ) {
				// Get connector ID
				$connector_id = self::get_connector_id();

				// Fetch feed from backend by ID
				$feed_json = self::call( 'GET', '/connectors/' . $connector_id . '/feeds/' . $feed_id . '?api_key=' . Grabpress::$api_key );
				$feed_data = json_decode( $feed_json );

				// Return JSON feed data
				return $feed_data;
			} else { // No valid key
				// Log custom fatal error
				Grabpress::abort( 'No valid key' );
			}
		}

		/**
		 * Fetches feeds from backend
		 * @return object JSON object containing feeds data
		 */
		static function get_feeds() {
			// Log caller debug info
			Grabpress::log();

			// If valid key
			if ( self::validate_key() ) {
				// Get connector ID
				$connector_id = self::get_connector_id();

				// Get feeds from backend using key
				$feeds_json = self::call( 'GET', '/connectors/' . $connector_id . '/feeds?api_key=' . Grabpress::$api_key );
				$feeds_data = json_decode( $feeds_json );

				// Return JSON feeds data
				return $feeds_data;
			} else { // No valid key
				// Log custom fatal error
				Grabpress::abort( 'No valid key' );
			}
		}

		/**
		 * Fetch # of submissions from last submission for a given feed
		 * @param  object $feed JSON object containing feed data
		 * @return integer       Number of submissions from last submission
		 */
		static function get_items_from_last_submission( $feed ) {
			// Fetch submissions from backend
			$submissions = self::call( 'GET', '/connectors/' . self::get_connector_id() . '/feeds/' . $feed->feed->id . '/submissions?api_key=' . Grabpress::$api_key );

			// Convert response to JSON object
			$submissions = json_decode( $submissions );

			// Start count at 0
			$count = 0;

			// If submissions exist
			if ( count( $submissions ) ) {
				// Loop through each submission
				foreach ( $submissions as $sub ) {
					// Set creation date
					$last_submission = new DateTime( $submissions[0]->submission->created_at );

					// If submission date more recent than last submission date
					if ( new DateTime( $sub->submission->created_at ) > $last_submission->modify( '- ' . $feed->feed->update_frequency . ' seconds' )
					) {
						// Increment count
						$count++;
					}
				}
			}

			// Return # of items from last submission
			return $count;
		}

		/**
		 * Fetches JSON from a provided URL using WP HTTP API
		 * @param  string $url              URL to fetch JSON from
		 * @return string                   JSON response or error message
		 */
		static function get_json( $url ) {
			// Log caller debug info
			Grabpress::log();

			// Set request args
			$args = array(
				'timeout' => 5,
				'headers' => array(
					'Content-type: application/json\r\n',
				),
			);

			// Try fetching JSON from URL
			try {
				// Fetch JSON via WP HTTP request
				$response = wp_remote_get( $url, $args );
			} catch ( Exception $e ) { // If fetch unsuccessful
				// Log exception error message
		 		Grabpress::abort( 'API get_json error: ' . $e->getMessage() );
 			}

 			// Check status of active HTTP request, last HTTP code received
			$status = wp_remote_retrieve_response_code( $response );

			// If HTTP code is not 400 series
			if ( $response && $status < 400 && ! is_wp_error( $response ) ) {
				// Return response from HTTP request
				return $response['body'];
			} else { // Else is a 404 or like error
				// Generate custom exception error
				throw new Exception( 'API get_json error with status = '. $status . ' and response =' . json_encode( $response ) );
			}
		}

		/**
		 * Get the base URL of the API based on environment
		 * @return string Base URL location of the API
		 */
		static function get_location() {
			// If production environment
			if ( PRODUCTION_ENV == Grabpress::$environment ) {
				// Set production URL
				$apiLocation = 'autoposter.' . PRODUCTION_ENV . '.com';
			} else { // If development environment
				// Set development URL
				$apiLocation = 'autoposter.' . DEVELOPMENT_ENV . '.com';
			}

			// Return the API base URL
			return $apiLocation;
		}

		/**
		 * Get PHP config settings from php.ini
		 * @return string Serialized PHP config settings
		 */
		static function get_php_conf() {
			// Build PHP config array using PHP settings
			$php_conf = array (
				'display_errors'       => ini_get( 'display_errors' ),
				'magic_quotes_gpc'     => ini_get( 'magic_quotes_gpc' ),
				'magic_quotes_runtime' => ini_get( 'magic_quotes_runtime' ),
				'magic_quotes_sybase'  => ini_get( 'magic_quotes_sybase' ),
				'log_errors'           => ini_get( 'log_errors' ),
				'error_log'            => ini_get( 'error_log' ),
				'error_reporting'      => ini_get( 'error_reporting' ),
			);

			// Return serialized PHP config settings
			return serialize( $php_conf );
		}

		/**
		 * Fetch stored player settings from API
		 * @return array Custom player settings
		 */
		static function get_player_settings() {
			// If GrabPress player settings not stored locally yet
			if ( ! Grabpress::$player_settings ) {
				// Fetch settings from API
				$settings_json =  self::call( 'GET',  '/connectors/' .self::get_connector_id() . '/player_settings?api_key=' .Grabpress::$api_key );
				$settings = json_decode( $settings_json );

				// If no settings stored in API
				if ( empty( $settings ) || ( isset( $settings->error ) && 404 == $settings->error->status_code ) ) {
					// Set value as empty array
					Grabpress::$player_settings = array();
				} else { // Else if settings exist
					// Build settings into array format
					Grabpress::$player_settings = array(
						'width'  => $settings->player_setting->width,
						'height' => $settings->player_setting->height,
						'ratio'  => $settings->player_setting->ratio,
					);
				}
			}

			// Return player settings
			return Grabpress::$player_settings;
		}

		/**
		 * Get array of player settings using custom settings and/or defaults
		 * @return array Player settings
		 */
		static function get_player_settings_for_embed() {
			// Fetch player settings from API
			$settings = self::get_player_settings();

			// Set default settings
			$defaults = array(
				'width' => 600,
				'height'=> 270,
				'ratio' => '16:9',
			);

			// Return array with defaults set in lieu of custom settings
			return array_merge( $defaults, $settings );
		}

		/**
		 * Get URL for largest preview image found in MRSS feed
		 * @param  object $mrss XML object containing video feed data
		 * @return string       Preview image URL
		 */
		static function get_preview_url( $mrss ) {
			// Log caller debug info
			Grabpress::log();

			// Get thumb widths
			$first_thumb_width = intval( $mrss->channel->item->mediagroup->mediathumbnail[0]->attributes()->width );
			$second_thumb_width = intval( $mrss->channel->item->mediagroup->mediathumbnail[1]->attributes()->width );

			// Get thumb URLs
			$first_thumb_url = $mrss->channel->item->mediagroup->mediathumbnail[0]->attributes()->url;
			$second_thumb_url = $mrss->channel->item->mediagroup->mediathumbnail[1]->attributes()->url;

			// If first thumbnail width larger than second
			if ( $first_thumb_width > $second_thumb_width ) {
				// Return first thumb URL
				return $first_thumb_url;
			} else { // Second thumb wider
				// Return second thumb URL
				return $second_thumb_url;
			}
		}

		/**
		 * Gets providers from local cache or backend
		 * @return object JSON object containing providers data
		 */
		static function get_providers() {
			// Try using fetching providers
			try{
				// If 'cached' version of providers stored locally
				if ( isset( Grabpress::$providers ) ) {
					// Return cached version
					return Grabpress::$providers;
				}

				// Fetch providers from backend
				$json_provider = self::get_json( 'http://catalog.' . Grabpress::$environment . '.com/catalogs/1/providers?limit=-1&api_key='.GrabPress::$api_key.'' );
				$providers_list = json_decode( $json_provider );

				// If providers exist
				if ( $providers_list ) {
					// Filter out opted out providers
					$providers_list = array_filter( $providers_list, array( 'Grabpress_API', 'filter_out_providers' ) );

					// Sort providers alphabetically
					uasort( $providers_list, array( 'Grabpress_API', 'sort_providers' ) );

					// 'Cache' providers locally
					Grabpress::$providers = $providers_list;
				}
			} catch ( Exception $e ) { // Fetch fails
				// Create empty array
				$providers_list = array();

				// Log exception error message
				Grabpress::log( 'API call exception: ' . $e->getMessage() );
			}

			// Return groomed providers list
			return $providers_list;
		}

		/**
		 * Get shortcode template ID
		 * @return string Shortcode template ID
		 */
		static function get_shortcode_template_id() {
			// Log caller debug info
			Grabpress::log();

			// If local template ID exists
			if ( Grabpress::$shortcode_submission_template_id ) {
				// Return it
				return Grabpress::$shortcode_submission_template_id;
			}

			// Fetch submission templates from API
			$submission_templates_json = self::call( 'GET', '/submission_templates/default' );
			$submission_templates = json_decode( $submission_templates_json );

			$template_count = count( $submission_templates );

			// Loop through each
			for ( $i = 0; $i < $template_count; $i++ ) {
				// Get submission template for current iteration
				$submission_template = $submission_templates[ $i ]->submission_template;

				// If template name is 'ShortCode Template'
				if ( 'ShortCode Template' == $submission_template->name ) {
					// Set as local template ID
					Grabpress::$shortcode_submission_template_id = $submission_template->id;
				}
			}

			// Return local template ID
			return Grabpress::$shortcode_submission_template_id;
		}

		/**
		 * Fetched user data from API
		 * @return string User data in JSON formatted string
		 */
		static function get_user() {
			// Log caller debug info
			Grabpress::log();

			// Get connector ID
			$id = self::get_connector_id();

			// Get user data in JSON format from API
			$user_json = self::call( 'GET',  '/connectors/' . $id . '/user?api_key=' . Grabpress::$api_key );

			// Convert to PHP JSON object
			$user_data = json_decode( $user_json );

			// Return user data
			return $user_data;
		}

		/**
		 * Fetch MRSS for a video by ID
		 * @param  string $video_id ID of video to be fetched
		 * @return object           XML (MRSS) object containing video feed
		 */
		static function get_video_mrss( $video_id ) {
			// Build request URL
			$request_url = 'http://catalog.' . Grabpress::$environment . '.com/catalogs/1/videos/' . $video_id . '.mrss';

			// Try fetching XML via WP HTTP request
			try {
				// Temp fix for redirecting to the created post
				// Added so automated tests work correctly
				// remove as needed, but make sure automated tests
				// continue to work (CatalogTests.test_catalog_2_create_post_from_catalog_search)
				if ( !isset ($args)){
					$args = array();
				}
				// End temp fix

				// Make request
				$response = wp_remote_get( $request_url, $args );

				// Strip out headers and get just the body of the request
				$xml = wp_remote_retrieve_body( $response );
			} catch ( Exception $e ) { // Fetch fails
				// Log custom fatal error
				Grabpress::abort( 'Catalog API exception: ' . $e->getMessage() );
			}

			// Get status of HTTP request
			$status = wp_remote_retrieve_response_code( $response );

			// If XML exists and status ok
			if ( $xml && $status < 400 && ! is_wp_error( $response ) ) {
				// Build search array
				$search = array(
					'grab:',
					'media:',
					'type="flash"',
				);

				// Build replace array
				$replace = array(
					'grab',
					'media',
					''
				);

				// Replace specified strings in XML
				$xmlString = str_replace( $search, $replace, $xml );

				// Return XML object
				return  simplexml_load_string( $xmlString, 'SimpleXMLElement', LIBXML_NOCDATA );
			} else { // 400 error exists
				// Throw custom exception error
				throw new Exception( 'Catalog API error with status = ' . $status . ' and response:' . $response );
			}
		}

		/**
		 * Fetch watchlist from local 'cache' or backend
		 * @return object JSON object containing watchlist data
		 */
		static function get_watchlist() {
			// If watch list 'cached' locally
			if ( isset( Grabpress::$watchlist ) ) {
				// Return 'cached' version
				return Grabpress::$watchlist;
			}

			// Create empty array to hold watched data
			$watched = array();

			// Try fetching feeds_data
			try {
				// Fetch feeds
				$feeds = self::get_feeds();

				// Loop through all feeds
				foreach ( $feeds as $feed ) {
					// If feed has watchlist
					if ( $feed->feed->watchlist == true ) {
						// Fetch feed
						$json = json_decode( self::get_json( $feed->feed->url ) );

						// Grab results
						$watched = $json->results;
					}
				}
			} catch ( Exception $e ) {
				// Output error message to admin dashboard
				Grabpress::$error = 'There was an error connecting to the API! Please try again later!';

				// Log exception error message
				Grabpress::log( 'API call exception: ' . $e->getMessage() );
			}

			// Sort watched chronologically
			uasort( $watched, array( 'Grabpress_API', 'sort_watchlist' ) );

			// Return groomed watched
			return $watched;
		}

		/**
		 * Compares channel names to see which one comes first for alphabetically
		 * @param  object $channel1 JSON object containing channel one data
		 * @param  object $channel2 JSON object containing channel two data
		 * @return int               Result of comparison, < 0, >0 or 0
		 */
		static function sort_channels( $channel1, $channel2 ) {
			return strcasecmp( $channel1->category->name, $channel2->category->name );
		}

		/**
		 * Compares provider names to see which one comes first for alphabetically
		 * @param  object $provider1 JSON object containing provider one data
		 * @param  object $provider2 JSON object containing provider two data
		 * @return int               Result of comparison, < 0, >0 or 0
		 */
		static function sort_providers( $provider1, $provider2 ) {
			return strcasecmp( $provider1->provider->name, $provider2->provider->name );
		}

		/**
		 * Sort watch list chronologically by creation date
		 * @param  object $watchlist1 JSON object containing watchlist one data
		 * @param  object $watchlist2 JSON object containing watchlist two data
		 * @return boolean             First watchlist was created before second
		 */
		static function sort_watchlist( $watchlist1, $watchlist2 ) {
			$watchlist1_time = new DateTime( $watchlist1->video->created_at );
			$watchlist2_time = new DateTime( $watchlist2->video->created_at );
			return $watchlist1_time->format( 'YmdHis' ) < $watchlist2_time->format( 'YmdHis' );
		}

		/*
		 * Input: time in miliseconds
		 * Return: time in format MM:SS ,If duration is greater than 1 hour, the MM:SS format is still use
		 */
		static function time_format_mm_ss( $ms ) {
			// Calculate seconds
			$seconds = $ms / 1000;

			// Calculate minutes
			$mins = intval( $seconds / 60 );

			// Calculate total # of full seconds
			$secs = $seconds % 60 ;

			// If seconds only 1 characer
			if ( 1 === strlen( $secs ) ) {
				// Prepend 0
				$secs = '0' . $secs;
			}

			// If minutes only 1 character
			if ( 1 === strlen( $mins ) ) {
				// Prepend 0
				$mins = '0' . $mins;
			}

			// Return formatted MM:SS
			return  $mins .':' . $secs;
		}

		/**
		 * Validates and API key
		 * @return boolean Returns true if valid key exists or was created, or
		 * false it not
		 */
		static function validate_key() {
			// Log caller debug info
			Grabpress::log();

			// Get GrabPress API key
			$api_key = get_option( 'grabpress_key' );

			// If API key exists
			if ( '' != $api_key ) {
				// Try to validate API key
				try {
					// Get validation response
					$validate_json = self::call( 'GET', '/user/validate?api_key=' . $api_key );
					$validate_data = $validate_json;

					// If a validation error exists
					if ( isset( $validate_data->error ) ) {
						// Create connection
						return self::create_connection();
					} else { // If no error
						// Store API key locally
						Grabpress::$api_key = $api_key;

						// Valid key
						return true;
					}
				} catch ( Exception $e ) {
					// Output error message to admin dashboard
					Grabpress::$error = "There was an error connecting to the API! Please try again later!";

					// Log exception error message
					Grabpress::log( 'API call exception: ' . $e->getMessage() );
				}
			} else { // If no API key
				// Try creating a connection
				try {
					// Create connection
					return self::create_connection();
				} catch ( Exception $e ) { // If creation fails
					// Output error message to admin dashboard
					Grabpress::$error = "There was an error connecting to the API! Please try again later!";

					// Log exception error message
					Grabpress::log( 'API call exception: ' . $e->getMessage() );
				}
			}

			// Invalid key
			return false;
		}

		/**
		 * Update feeds with watchlist activity
		 * @param  object $feeds JSON object containing feeds
		 * @return object        JSON object containing updated feeds
		 */
		static function watchlist_activity( $feeds ) {
			// If feeds
			if ( $feeds ) {
				// Loop through all feeds
				foreach ( $feeds as $feed ) {
					// Fetch items from last submission
					$submissions = self::get_items_from_last_submission( $feed );

					// Get health of feed based on # of submissions divided by posts per
					// update
					$feed->feed->feed_health = $submissions / $feed->feed->posts_per_update;

					// Set feed submissions to # of submissions from last submission
					$feed->feed->submissions = $submissions;
				}
			}

			// Return updated feeds
			return $feeds;
		}
	}
}
