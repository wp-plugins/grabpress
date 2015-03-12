<?php

// Define class only if not defined already
if (!class_exists('Grabpress_API')) {

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
        static function call($method, $resource, $data = array(), $auth = false) {
            // Create empty array to holding request args
            $args = array();

            // Convert data array to JSON
            $json = json_encode($data, true);

            // Get API location for current ENV
            $apiLocation = self::get_location($auth);

            // Build request URL
            if (Grabpress::grabpress_is_secure()) {
                $request_url = 'https://' . $apiLocation . $resource;
            } else {
                $request_url = 'http://' . $apiLocation . $resource;
            }

            // If user auth credentials provided
            // Set additional options and params based on call method
            switch ($method) {
                case 'GET':
                    $params = '';
                    $params .= strstr($resource, '?') ? '&' : '?';
                    $params .= http_build_query($data);
                    $request_url .= $params;
                    $args['timeout'] = 10;
                    // Call API using WP HTTP API
                    Grabpress::log('GET ' . $request_url);
                    try {

                        $response = wp_remote_get($request_url, $args);
                 
                    } catch (Exception $e) { // If request unsuccessful
                        // Log custom fatal error
                        Grabpress::abort('API call error: ' . $e->getMessage());
                    }
                    break;
                case 'POST';
                    $args['body'] = $json;
                    $args['method'] = 'POST';
                    $args['headers']['content-type'] = 'application/json';
                    // Call API using WP HTTP API
                    Grabpress::log('POST ' . $request_url);
                    try {
                        $response = wp_remote_request($request_url, $args);
                    } catch (Exception $e) { // If request unsuccessful
                        // Log custom fatal error
                        Grabpress::abort('API call error: ' . $e->getMessage());
                    }
                    break;
                case 'PUT';
                    // Convert JSON to associative array
                    /* $data = $json; */
                    $args['method'] = 'PUT';
                    $args['body'] = $json;
                    $args['headers']['content-type'] = 'application/json';
                    // Call API using WP HTTP API
                    Grabpress::log('PUT ' . $request_url);
                    try {
                        $response = wp_remote_request($request_url, $args);
                    } catch (Exception $e) { // If request unsuccessful
                        // Log custom fatal error
                        Grabpress::abort('API call error: ' . $e->getMessage());
                    }
                    break;
                case 'DELETE';
                    $args['method'] = 'DELETE';

                    // Call API using WP HTTP API
                    Grabpress::log('DELETE ' . $request_url);
                    try {
                        $response = wp_remote_request($request_url, $args);
                    } catch (Exception $e) { // If request unsuccessful
                        // Log custom fatal error
                        Grabpress::abort('API call error: ' . $e->getMessage());
                    }
                    break;
                case "POST_CURL":
                    $data = json_decode($json, true);
                    $args['method'] = 'POST';
                    $args['body'] = $data;
                    // Call API using WP HTTP API
                    try {
                        $response = wp_remote_request($request_url, $args);
                    } catch (Exception $e) {
                        // If request unsuccessful handle the error message
                    }
                case "PUT_CURL":

                    $data = json_decode($json, true);
                    $args['method'] = 'PUT';
                    $args['body'] = $data;
                    // Call API using WP HTTP API
                    try {

                        $response = wp_remote_request($request_url, $args);
                    } catch (Exception $e) {
                        //If request unsuccessful, handle the error message
                    }
                    break;
            }

            if (isset($response) && !is_wp_error($response)) {
                $code = $response['response']['code'];
                $status = $code = $response['headers']['status'];
                $body = $response['body'];

                // If HTTP code is not 400 series
                if ($code < 400) {
                    // Return response from HTTP request
                    return $body;
                } else {
                    return $response['response']['message'];
                }
            }
        }

        /**
         * Creates a connection between WP user and backend
         * @return boolean User successfully created/updated
         */
        static function create_connection() {
            // Get/generate user data
            $user_url = get_site_url();
            $user_nicename = Grabpress::$grabpress_user;
            $user_login = $user_nicename;
            $url_array = explode('/', $user_url);
            $email_host = substr($url_array[2], 4, 13);
            $email_dir = isset($url_array[3]) ? $url_array[3] : '';
            $user_email = md5(uniqid(rand(), TRUE)) . '@grab.press';
            $display_name = 'GrabPress';
            $nickname = 'GrabPress';
            $first_name = 'Grab';
            $last_name = 'Press';

            // Build post data array
            $post_data = array(
                'user' => array(
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $user_email,
                    'password' => 'grabpress'
                ),
            );
            //http://auth.grabnetworks.com
            // Fetch user data from backend
            try {
                $user_json = self::call('POST', '/accounts/-1/users/create_with_payment_details?rkey=bbd608b4067f25888a43e63582fd096708d443da', $post_data, true);
                $user_data = json_decode($user_json);
                // Get API key, user id and account type id from user data
                $api_key = $user_data->user->tokens[0]->access_key;
                $user_id = $user_data->user->tokens[0]->user_id;
                $account_id = $user_data->user->account_id;
   

            } catch (Exception $ex) {
                
            }
            // IF API key exists
            if ($api_key) {
                // Store API key in WPDB
                update_option('grabpress_account_id', $account_id);
                update_option('grabpress_key', $api_key);
                update_option('grabpress_user_id', $user_id);
                Grabpress::$api_key = get_option('grabpress_key');
                Grabpress::$user_id = get_option('grabpress_user_id');
                //return the user
                return $user_data->user;
            }


            // User successfully created/updated
            return true;
        }


        /**
         * Creates necessary embeds for the user
         * @param $auto_play string either 0 or 1
         * @param $video_id integer the video or content id
         * @return boolean embeds successfully created
         */
        static function create_embed($auto_play, $video_id) {
            $is_vip = Grabpress::is_wp_dot_com();
            GrabPress::log ( $video_id );
            Grabpress::$api_key = get_option ( 'grabpress_key' );
            Grabpress::$user_id = get_option ( 'grabpress_user_id' );
            $embed_data = array (
                    'embed' => array (
                            'content_id' => $video_id,
                            'content_type' => 'video',
                            'owner_id' => Grabpress::$user_id,
                            'label' => 'Default GrabPress Player (CTP)',
                            'ad_server_id' => 5,
                            'ad_key' => 'voxant',
                            'ad_rules_url' => 'http://player.grabnetworks.com/ads/rules.json',
                            'auto_play' => $auto_play,
                            'width' => 640,
                            'height' => 360,
                            'skin_url' => 'swf/OSMFSkin.swf',
                            'companion_id' => 'sidebar-top-ads',
                            'companion_type' => 'floating',
                            'javascript_namespace' => 'grabPlayerJS',
                            'javascript_event_handler' => 'playerEventHandler',
                            'ad_tier_name' => 'CMP',
                            'ad_tier_id' => 2,
                            'product_type_id' => $is_vip ? GP_VIP_PRODUCT_TYPE_ID : GP_PRODUCT_TYPE_ID 
                    ) 
            );
                      
            try {
                $embed_json = self::call ( 'POST', '/embeds?api_key=' . GrabPress::$api_key, $embed_data, false );
            } catch ( Exception $e ) {
                // Log exception error message
                Grabpress::message ( 'API call exception: ' . $e->getMessage () );
            }
            Grabpress::log ( "Specific embed ID is". $embed_json . " and the video ID is". $video_id);
            // Embeds successfully created
            return json_decode($embed_json); 
        }


		
		/**
		 * Creates necessary embeds for the user
		 *
		 * @return boolean embeds successfully created
		 */
		static function create_embeds() {
			Grabpress::log ( "Entered create_embeds" );
			Grabpress::$api_key = get_option ( 'grabpress_key' );
			Grabpress::$user_id = get_option ( 'grabpress_user_id' );
			$video_id = self::get_latest_video ();
			GrabPress::log ( $video_id );
            $is_vip = Grabpress::is_wp_dot_com();
			$ctp_embed_data = array (
					'embed' => array (
							'content_id' => $video_id,
							'content_type' => 'video',
							'owner_id' => Grabpress::$user_id,
							'label' => 'Default GrabPress Player (CTP)',
							'ad_server_id' => 5,
							'ad_key' => 'voxant',
							'ad_rules_url' => 'http://player.grabnetworks.com/ads/rules.json',
							'auto_play' => 'false',
							'width' => 600,
							'hieght' => 450,
							'skin_url' => 'swf/OSMFSkin.swf',
							'companion_id' => 'sidebar-top-ads',
							'companion_type' => 'floating',
							'javascript_namespace' => 'grabPlayerJS',
							'javascript_event_handler' => 'playerEventHandler',
							'ad_tier_name' => 'CMP',
							'ad_tier_id' => 2,
							'product_type_id' => $is_vip ? GP_VIP_PRODUCT_TYPE_ID : GP_PRODUCT_TYPE_ID 
					) 
			);
			$ap_embed_data = array (
					'embed' => array (
							'content_id' => $video_id,
							'content_type' => 'video',
							'owner_id' => Grabpress::$user_id,
							'label' => 'Default GrabPress Player (AP)',
							'ad_server_id' => 5,
							'ad_key' => 'voxant',
							'ad_rules_url' => 'http://player.grabnetworks.com/ads/rules.json',
							'auto_play' => 'true',
							'width' => 600,
							'hieght' => 450,
							'skin_url' => 'swf/OSMFSkin.swf',
							'companion_id' => 'sidebar-top-ads',
							'companion_type' => 'floating',
							'javascript_namespace' => 'grabPlayerJS',
							'javascript_event_handler' => 'playerEventHandler',
							'ad_tier_name' => 'CMP',
							'ad_tier_id' => 2,
							'product_type_id' => $is_vip ? GP_VIP_PRODUCT_TYPE_ID : GP_PRODUCT_TYPE_ID 
					)
			);			
			try {
				$embed_json = self::call ( 'POST', '/embeds?api_key=' . GrabPress::$api_key, $ctp_embed_data, false );
			} catch ( Exception $e ) {
				// Log exception error message
				Grabpress::message ( 'API call exception: ' . $e->getMessage () );
			}
			try {
				$embed_json = self::call ( 'POST', '/embeds?api_key=' . GrabPress::$api_key, $ap_embed_data, false );
			} catch ( Exception $e ) {
				// Log exception error message
				Grabpress::message ( 'API call exception: ' . $e->getMessage () );
			}
			// Embeds successfully created
			return true;
		}
		
		/**
		 * Get the latest video from the catalog
		 * used for creating the embed
		 */		
		static function get_latest_video() {
			$video_data = self::call ( 'GET', '/catalogs/1/videos?limit=1', array (), false );
			$result = json_decode ( $video_data, true );
			return $result [0] ["video"] ["id"];
		}
		
		/**
		 *  Get an embed_id
		 *  @params Boolean (true = autoplay embed ; false = click-to-play embed)
		 */
		static function get_embed_id($ap = false) {
			Grabpress::$api_key = get_option ( 'grabpress_key' );
			Grabpress::$user_id = get_option ( 'grabpress_user_id' );
			$embeds_data = self::call ( 'GET', '/embeds/?api_key=' . GrabPress::$api_key, array (), false );

			$result = json_decode ( $embeds_data );
			$result_count = count ( $result );
			for($i = 0; $i < $result_count; $i ++) {
				$e_id = $result [$i]->embed->id;
				$type = $result [$i]->embed->auto_play;
				if ($type === $ap) {
					return $e_id;
				}
			}
		}
		
        /**
         * Wrapper to soft delete a single user.
         * @param api_key integer, the api key.
         * @param user_id integer, the user id.
         * @return deleted user object
         */
        static function delete_grabpress_user($api_key, $user_id) {

            $result = array();

            if ('' != $api_key && strlen($user_id) > 1) {

                try {

                    $validate_json = self::call('DELETE', '/accounts/-1/users/' . $user_id . '?api_key=' . $api_key, array(), true);

                    $result = json_decode($validate_json);

                    if ($result->user) {
                        update_option('grabpress_key', '');
                        update_option('grabpress_user_id', '');
                    }
                } catch (Exception $e) {
                    // Log exception error message
                    Grabpress::message('API call exception: ' . $e->getMessage());
                }

                return $result;
            }
        }

        /**
         * Creates GrabPress video feed
         * @param  array $params Associative array containing params
         */
        static function create_feed($params) {

            // If valid API key
            if (self::validate_key()) {
                // Get channels from params
                $channels = $params['channels'];

                // Convert channels string to array
                $channels_list = implode(',', $channels);

                // Get total # of channels chosen by the user
                $channels_count = count($channels_list);

                // Get total # of channels from catalog
                $channels_total = $params['channels_total'];

                // If chosen channels is equal to channels in catalog
                if ($channels_count == $channels_total) {
                    // Set channel list to empty string
                    $channels_list = '';
                }

                // URL encode name
                $name = $params['name'];

                // Get providers from params
                $providers = $params['providers'];

                // Convert providers array to string
                $providers_list = implode(',', $providers);

                // Get total # of providers chosen by user
                $providers_count = count($providers);

                // Get total # of providers from catalog
                $providers_total = $params['providers_total'];

                // If chosen providers is equal to providers in catalog
                if ($providers_count == $providers_total) {
                    // Set provider list to empty string
                    $providers_list = '';
                }

                // Get category list from params
                $category_list = $params['category'];

                // Get total # of categories
                $category_count = count($category_list);

                // Create empty categories array
                $categories = array();

                // If category list is an array
                if (is_array($category_list)) {
                    // Loop through category list
                    foreach ($category_list as $cat) {
                        // Get category name from WPDB
                        $categories[] = get_cat_name($cat);
                    }
                } else { // If not an array
                    // Set feed as uncategorized
                    $categories[] = 'Uncategorized';
                }

                // Generate channel URL to call
                $search_parameters = array(
                    'keywords_and' => $params['keywords_and'],
                    'keywords_not' => $params['keywords_not'],
                    'keywords_or' => $params['keywords_or'],
                    'keywords_phrase' => $params['keywords_phrase'],
                    'providers' => $providers,
                    'categories' => $channels_list,
                );
/*                // Get submission template ID
                $submission_template_id = self::get_shortcode_template_id();*/

                // Build post dat array
                $post_data = array(
                    'playlist' => array(
                        /*   'submission_template_id' => "$submission_template_id", */
                        'description' => $name,
                        'name' => $name,
                        'refresh_interval' => 86400,
                        'search_parameters' => json_encode($search_parameters)
                    ),
                );
                // Send feed data to backend and listen for response
                try {
                    $created_feed = self::call('POST', '/playlists?api_key=' . GrabPress::$api_key, $post_data, false);
                    $playlist_feed = json_decode($created_feed);
                    //Refresh It.
                    $refresher = self::call('GET', '/playlists/'. $playlist_feed->playlist->id .'/refresh?api_key=' . GrabPress::$api_key, $post_data, false);

                } catch (\Exception $e) {
                    Grabpress::$message = $e->getMessage();
                }


                if (is_object($playlist_feed->playlist)) {
                    // If feed is active
                    // Output message to admin dashboard
                    Grabpress::$message = 'Congratulations! Your video feed:' . $playlist_feed->playlist->name . 'was successfully created';
                } else { // Feed inactive
                    // Output support message to admin dashboard
                    Grabpress::$message = 'Something went wrong grabbing your feed. Please <a href = "https://getsatisfaction.com/grabmedia" target="_blank">contact Grab support</a>\n' . $response_data;
                }
            } else { // No valid key
                // Output message regarding invalid key to admin dashboard
                Grabpress::$message = 'Your API key is no longer valid. Please <a href = "https://getsatisfaction.com/grabmedia" target="_blank">contact Grab support.</a>';
            }
        }

        /**
         * Edit feed on backend
         * @param  array $request Associative array containing request data
         */
        static function edit_feed( $request ) {
            // Get feed ID from request

            $channels = $request['channels'];

            // Convert channels string to array
            $channels_list = implode(',', $channels);
            $feed_id = intval($request['feed_id']);
            // Generate channel URL to call
                $search_parameters = array(
                    'keywords_and' => $request['keywords_and'],
                    'keywords_not' => $request['keywords_not'],
                    'keywords_or' => $request['keywords_or'],
                    'keywords_phrase' => $request['keywords_phrase'],
                    'providers' =>  $request['providers'],
                    'categories' => $request['channels']
                );
            
            // Build PUT data array
                $put_data = array(
                    'playlist' => array(
                        /*   'submission_template_id' => "$submission_template_id", */
                        'description' => $request['name'],
                        'refresh_interval' => 86400,
                        'name' => $request['name'],
                        'search_parameters' => json_encode($search_parameters)
                    ),
                );
            // Send feed data to backend and listen for response
                try {
                    $updated_feed = self::call('PUT', '/playlists/'. $feed_id .'?'.'api_key=' . GrabPress::$api_key, $put_data, false);
                    $updated_feed_response = json_decode($updated_feed);
                    return $updated_feed_response;

                } catch (\Exception $e) {
                    Grabpress::$message = $e->getMessage();
                }

        }

        /**
         * Determine if provider has opted out
         * @param  object $provider JSON object containing provider info
         * @return boolean           Provider opted out
         */
        static function filter_out_providers($provider) {
            return !$provider->provider->opt_out;
        }


        /**
         * Fetches channels from local 'cache' or backend
         * @return object JSON object containing channels data
         */
        static function get_channels() {
            Grabpress::log();
            // Try fetching channels
            try {
                // If channels 'cached' locally
                if (isset(Grabpress::$channels)) {
                    // Return 'cached' version
                    return Grabpress::$channels;
                }

                // Fetch channels from backend
                if (Grabpress::grabpress_is_secure()) {
                    $json_channel = self::get_json('https://catalog.' . Grabpress::$environment . '.com/catalogs/1/categories');
                } else {
                    $json_channel = self::get_json('http://catalog.' . Grabpress::$environment . '.com/catalogs/1/categories');
                }
                $channels_list = json_decode($json_channel);

                // If channels exist
                if ($channels_list) {
                    // Sort channels alphabetically
                    uasort($channels_list, array('Grabpress_API', 'sort_channels'));

                    // 'Cache' channels locally
                    Grabpress::$channels = $channels_list;
                }
            } catch (Exception $e) { // If fetch fails
                // Create empty array
                $channels_list = array();
            }

            // Returned groomed channels list
            return $channels_list;
        }

        /**
         * Returns last entry of jetpack_log array
         */
        static function get_jetpack_blog_id() {

            $jetpack_log = get_option('jetpack_log', false);
            $jetpack_blog_id = null;
            $jetpack_blog_id = $jetpack_log[count($jetpack_log) - 1]['blog_id'];

            if (is_int($jetpack_blog_id)) {
                return $jetpack_blog_id;
            } else {
                return get_current_blog_id();
            }
        }

        /**
         * Returns a token if a particular api key it´s associated with WP OAuth.
         * @param  string apikey, Grabpress api key.
         * @return string the token in case of the existence or null otherwise.
         */
        static function get_oauth_token_for_client($apikey = null) {

            $oauth_token = self::call('GET', '/oauth/?api_key=' . $apikey);
            $oauth_token = json_decode($oauth_token);
            return isset($oauth_token->token) ? $oauth_token->token : null;
        }

        /**
         * Fetch all videos for a given playlist
         * @param  string   $video_feed_id playlist id
         * @return object   JSON object containing all videos
         */
        static function get_all_videos_from_playlist($video_feed_id) {
            try {
                $all_videos_data = self::call('GET', '/playlists/' . $video_feed_id . '/videos?api_key=' . Grabpress::$api_key, array('can_edit' => 'true'), false);
                return json_decode($all_videos_data);
            } catch (\Exception $e) {
                Grabpress::$message = $e->getMessage();
            }
            return false;
        }

        /**
         * Get feed from backend by ID
         * @param  string   $video_feed_id playlist id
         * @return object   JSON object containing feed data
         */
        static function get_video_feed_by_id($video_feed_id) {
            try {
                $video_feed_data = self::call('GET', '/playlists/' . $video_feed_id . '?api_key=' . Grabpress::$api_key, array('can_edit' => 'true'), false);
                return json_decode($video_feed_data);
            } catch (\Exception $e) {
                Grabpress::$message = $e->getMessage();
            }
            return false;
        }

        /**
         * Updates a feeds by id
         * @param  integer, $feed_id
         * @param  array, $form_params user request with new feed info
         * @return object, STD object containing feeds data
         */
        static function update_video_feed_by_id($feed_id = null, $form_params = null) {

            // Get the feed
            $feed_object = self::get_video_feed_by_id($feed_id);
            $parts = json_decode($feed_object->playlist->search_parameters);

            //These objects attributes,  will be updated according a form submitted by the user with new params
            $parts->keywords_and = "Awesome TeAm";


            $post_data = array(
                'playlist' => array(
                    'description' => 'An awesome team',
                    'refresh_interval' => 86400,
                    'name' => 'Super super',
                    'search_parameters' => json_encode($parts)
                ),
            );
            // Try to update a single playlist
            try {
                $updated_feed = self::call('PUT', '/playlists/' . $feed_id . '?api_key=' . GrabPress::$api_key, $post_data, false);
                return json_decode($updated_feed);
            } catch (\Exception $e) {
                Grabpress::$message = $e->getMessage();
            }

            return false;
        }

        /**
         * Deletes a playlist based on a playlist id
         * @param  string $video_feed_id   Playlist Id
         */
        static function delete_video_feed_by_id($video_feed_id) {

            try {
                $validate_json = self::call('DELETE', '/playlists/' . $video_feed_id . '?api_key=' . Grabpress::$api_key, array('can_edit' => 'true'), false);
                $result = json_decode($validate_json);
            } catch (\Exception $e) {
                Grabpress::$message = $e->getMessage();
            }
        }

        /**
         * Fetches feeds from backend
         * @return object JSON object containing feeds data
         */
        static function get_video_feeds() {
            // Get feeds from backend using key
            try {
                //fetch all video feeds
                $video_feeds_data = self::call('GET', '/playlists?api_key=' . Grabpress::$api_key, array('can_edit' => 'true'), false);
                return json_decode($video_feeds_data);
            } catch (\Exception $e) {
                Grabpress::$message = $e->getMessage();
            }
            return false;
        }

        /**
         * Fetch # of submissions from last submission for a given feed
         * @param  object $feed JSON object containing feed data
         * @return integer       Number of submissions from last submission
         */
        static function get_items_from_last_submission($video_feed_id) {
            // Fetch submissions from backend
            try {
                //Fetch video feed object to see if there are more items
                // Convert response to JSON object
                $submissions = array();
            } catch (Exception $e) {
                
            }
            // Start count at 0
            $count = 0;

            // Return # of items from last submission
            return $count;
        }

        /**
         * Fetches JSON from a provided URL using WP HTTP API
         * @param  string $url              URL to fetch JSON from
         * @return string                   JSON response or error message
         */
        static function get_json($url) {
            Grabpress::log();
            // Set request args
            $args = array(
                'timeout' => 5,
                'headers' => array(
                    'Content-type: application/json\r\n',
                ),
            );

            // Fetch JSON via WP HTTP request
            try {
                $response = wp_remote_get($url, $args);
            } catch (Exception $e) {
                
            }
            // Check status of active HTTP request, last HTTP code received
            $status = wp_remote_retrieve_response_code($response);

            // If HTTP code is not 400 series
            if ($response && $status < 400 && !is_wp_error($response)) {
                // Return response from HTTP request
                return $response['body'];
            } else { // Else is a 404 or like error
                // Generate custom exception error
                throw new Exception('API get_json error with status = ' . $status . ' and response =' . json_encode($response));
            }
        }

        /**
         * Get the base URL of the API based on environment
         * @return string Base URL location of the API
         */
        static function get_location($auth = false) {


            if ($auth) {
                // If production environment
                if (PRODUCTION_ENV == Grabpress::$environment) {
                    // Set production URL
                    $apiLocation = 'auth.' . PRODUCTION_ENV . '.com';
                } else { // If development environment
                    // Set development URL
                    $apiLocation = 'auth.' . DEVELOPMENT_ENV . '.com';
                }
            } else {

                // If production environment
                if (PRODUCTION_ENV == Grabpress::$environment) {
                    // Set production URL
                    $apiLocation = 'catalog.' . PRODUCTION_ENV . '.com';
                } else { // If development environment
                    // Set development URL
                    $apiLocation = 'catalog.' . DEVELOPMENT_ENV . '.com';
                }
            }
            return $apiLocation;
        }


        /**
         * Get PHP config settings from php.ini
         * @return string Serialized PHP config settings
         */
        static function get_php_conf() {
            // Build PHP config array using PHP settings
            $php_conf = array(
                'display_errors' => ini_get('display_errors'),
                'magic_quotes_gpc' => ini_get('magic_quotes_gpc'),
                'magic_quotes_runtime' => ini_get('magic_quotes_runtime'),
                'magic_quotes_sybase' => ini_get('magic_quotes_sybase'),
                'log_errors' => ini_get('log_errors'),
                'error_log' => ini_get('error_log'),
                'error_reporting' => ini_get('error_reporting'),
            );

            // Return serialized PHP config settings
            return serialize($php_conf);
        }

        /**
         * Fetch stored player settings from API
         * @return array Custom player settings
         */
        static function get_player_settings() {
            // Set default settings
            $defaults = array(
                'width' => 600,
                'height' => 270,
                'ratio' => '16:9',
            );
            // If GrabPress player settings not stored locally yet, then it set the width, height and ratio
            if (!Grabpress::$player_settings) {
                Grabpress::$player_settings = array(
                    'width' => $defaults['width'],
                    'height' => $defaults['height'],
                    'ratio' => $defaults['ratio'],
                );
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
            $defaults = array(
                'width' => 600,
                'height' => 270,
                'ratio' => '16:9',
            );

            // Return array with defaults set in lieu of custom settings
            return array_merge($defaults, $settings);
        }

        /**
         * Get URL for largest preview image found in MRSS feed
         * @param  object $mrss XML object containing video feed data
         * @return string       Preview image URL
         */
        static function get_preview_url($mrss) {
            // Get thumb widths
            $first_thumb_width = intval($mrss->channel->item->mediagroup->mediathumbnail[0]->attributes()->width);
            $second_thumb_width = intval($mrss->channel->item->mediagroup->mediathumbnail[1]->attributes()->width);

            // Get thumb URLs
            $first_thumb_url = $mrss->channel->item->mediagroup->mediathumbnail[0]->attributes()->url;
            $second_thumb_url = $mrss->channel->item->mediagroup->mediathumbnail[1]->attributes()->url;

            // If first thumbnail width larger than second
            if ($first_thumb_width > $second_thumb_width) {
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
            Grabpress::log();
            // Try using fetching providers
            try {
                // If 'cached' version of providers stored locally
                if (isset(Grabpress::$providers)) {
                    // Return cached version
                    return Grabpress::$providers;
                }

                // Fetch providers from backend
                try {
                    if (Grabpress::grabpress_is_secure()) {
                        $json_provider = self::get_json('https://catalog.' . Grabpress::$environment . '.com/catalogs/1/providers?limit=-1&api_key=' . GrabPress::$api_key . '');
                    } else {
                        $json_provider = self::get_json('http://catalog.' . Grabpress::$environment . '.com/catalogs/1/providers?limit=-1&api_key=' . GrabPress::$api_key . '');
                    }
                    $providers_list = json_decode($json_provider);
                } catch (Exception $e) {
                    
                }
                if (isset($providers_list)) {
                    // If providers exist
                    if ($providers_list) {
                        // Filter out opted out providers
                        $providers_list = array_filter($providers_list, array('Grabpress_API', 'filter_out_providers'));

                        // Sort providers alphabetically
                        uasort($providers_list, array('Grabpress_API', 'sort_providers'));

                        // 'Cache' providers locally
                        Grabpress::$providers = $providers_list;
                    }
                }
            } catch (Exception $e) { // Fetch fails
                // Create empty array
                $providers_list = array();
            }

            // Return groomed providers list
            return isset($providers_list) ? $providers_list : array();
        }

        /**
         * Get shortcode template ID
         * @return string Shortcode template ID
         */
        static function get_shortcode_template_id() {
            // If local template ID exists
            if (Grabpress::$shortcode_submission_template_id) {
                // Return it
                return Grabpress::$shortcode_submission_template_id;
            }

            // Fetch submission templates from API
            try {
                $submission_templates_json = self::call('GET', '/submission_templates/default');
                $submission_templates = json_decode($submission_templates_json);
            } catch (Exception $e) {
                
            }
            $template_count = count($submission_templates);

            // Loop through each
            for ($i = 0; $i < $template_count; $i++) {
                // Get submission template for current iteration
                $submission_template = $submission_templates[$i]->submission_template;

                // If template name is 'ShortCode Template'
                if ('ShortCode Template' == $submission_template->name) {
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
                $account_id = get_option('grabpress_account_id', '-1');
                //Try to fetch current user by apikey and user id
                $user_json = self::call('GET', '/accounts/'. $account_id .'/users/' . Grabpress::$user_id . '?api_key=' . Grabpress::$api_key, array(), true);
                // Convert to PHP JSON object
                $user_data = json_decode($user_json);
      

            // Return user data
            return $user_data;
        }

        /**
         * Fetch MRSS for a video by ID
         * @param  string $video_id ID of video to be fetched
         * @return object           XML (MRSS) object containing video feed
         */
        static function get_video_mrss($video_id) {
            // Build request URL
            if (Grabpress::grabpress_is_secure()) {
                $request_url = 'https://catalog.' . Grabpress::$environment . '.com/catalogs/1/videos/' . $video_id . '.mrss';
            } else {
                $request_url = 'http://catalog.' . Grabpress::$environment . '.com/catalogs/1/videos/' . $video_id . '.mrss';
            }

            // Temp fix for redirecting to the created post
            // Added so automated tests work correctly
            // remove as needed, but make sure automated tests
            // continue to work (CatalogTests.test_catalog_2_create_post_from_catalog_search)
            if (!isset($args)) {
                $args = array();
            }
            // End temp fix
            // Make request
            try {
                $response = wp_remote_get($request_url, $args);
                // Strip out headers and get just the body of the request
                $xml = wp_remote_retrieve_body($response);
            } catch (Exception $e) {
                
            }
            // Get status of HTTP request
            $status = wp_remote_retrieve_response_code($response);

            // If XML exists and status ok
            if ($xml && $status < 400 && !is_wp_error($response)) {
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
                $xmlString = str_replace($search, $replace, $xml);

                // Return XML object
                return simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
            } else {
                // 400 error exists
                // Throw custom exception error
                throw new Exception('Catalog API error with status = ' . $status . ' and response:' . $response);
            }
        }

        /**
         * Fetch watchlist from local 'cache' or backend
         * @return object JSON object containing watchlist data
         */
        static function get_watchlist() {
            // If watch list 'cached' locally
            if (isset(Grabpress::$watchlist)) {
                // Return 'cached' version
                return Grabpress::$watchlist;
            }

            // Create empty array to hold watched data
            $watched = array();

            // Try fetching feeds_data
            try {
                // Fetch feeds
                $playlists = self::get_video_feeds();
                if (is_array($playlists)) {
                    // Loop through all feeds
                    foreach ($playlists as $playlist) {
                        // If feed has watchlist
                        if ($playlist->playlist->is_watchlist == true) {
                            // Fetch feed
                            $str_get = '/playlists/' . $playlist->playlist->id . '/videos?limit=10&api_key=' . Grabpress::$api_key;
                            $videos_json = self::call('GET', $str_get);
                            $videos = json_decode($videos_json);
                            // Grab results
                            $watched = $videos;
                        }
                    }
                }
            } catch (Exception $e) {
                // Output error message to admin dashboard
                Grabpress::$error = 'There was an error connecting to the API! Please try again later!';
            }

            // Sort watched chronologically
            uasort($watched, array('Grabpress_API', 'sort_watchlist'));

            // Return groomed watched
            return $watched;
        }

        /**
         * Compares channel names to see which one comes first for alphabetically
         * @param  object $channel1 JSON object containing channel one data
         * @param  object $channel2 JSON object containing channel two data
         * @return int               Result of comparison, < 0, >0 or 0
         */
        static function sort_channels($channel1, $channel2) {
            return strcasecmp($channel1->category->name, $channel2->category->name);
        }

        /**
         * Compares provider names to see which one comes first for alphabetically
         * @param  object $provider1 JSON object containing provider one data
         * @param  object $provider2 JSON object containing provider two data
         * @return int               Result of comparison, < 0, >0 or 0
         */
        static function sort_providers($provider1, $provider2) {
            return strcasecmp($provider1->provider->name, $provider2->provider->name);
        }

        /**
         * Sort watch list chronologically by creation date
         * @param  object $watchlist1 JSON object containing watchlist one data
         * @param  object $watchlist2 JSON object containing watchlist two data
         * @return boolean             First watchlist was created before second
         */
        static function sort_watchlist($watchlist1, $watchlist2) {
            $watchlist1_time = new DateTime($watchlist1->video->updated_at);
            $watchlist2_time = new DateTime($watchlist2->video->updated_at);
            return $watchlist1_time->format('YmdHis') < $watchlist2_time->format('YmdHis');
        }

        /*
         * Input: time in miliseconds
         * Return: time in format MM:SS ,If duration is greater than 1 hour, the MM:SS format is still use
         */

        static function time_format_mm_ss($ms) {
            // Calculate seconds
            $seconds = $ms / 1000;

            // Calculate minutes
            $mins = intval($seconds / 60);

            // Calculate total # of full seconds
            $secs = $seconds % 60;

            // If seconds only 1 characer
            if (1 === strlen($secs)) {
                // Prepend 0
                $secs = '0' . $secs;
            }

            // If minutes only 1 character
            if (1 === strlen($mins)) {
                // Prepend 0
                $mins = '0' . $mins;
            }

            // Return formatted MM:SS
            return $mins . ':' . $secs;
        }

        /**
         * Validates and API key
         * @return boolean Returns true if valid key exists or was created, or
         * false it not
         */
        static function validate_key() {
            // Get GrabPress API key
            $api_key = get_option('grabpress_key');
            $user_id = get_option('grabpress_user_id');
            // If API key exists

            if ('' != $api_key && strlen($user_id) > 1) {
                // Try to validate API key
                try {

                    // Get validation response
                    $validate_json = self::call('GET', '/accounts/-1/users/' . $user_id . '?api_key=' . $api_key, array(), true);

                    $validate_data = json_decode($validate_json);

                    if (is_null($validate_data)) {
                        Grabpress::$error = "There was an error connecting with Grabpress!" . PHP_EOL;
                        //We are in .ORG
                        if (!Grabpress::is_wp_dot_com()) {
                            $text = sprintf(__(' Your API key is no longer valid. <a href="%s" target="%s">Please contact GrabPress support</a>.'), esc_url('https://wordpress.org/support/plugin/grabpress'), esc_attr('_blank'));
                        } else {
                            //We are in .COM    
                            $text = sprintf(__(' Your API key is no longer valid. <a href="%s" target="%s">Please contact GrabPress support</a>.'), esc_url('mailto:support@grab-media.com'), esc_attr('_blank'));
                        }
                        Grabpress::$error .= $text;
                    }

                    // If a validation error exists
                    if (isset($validate_data->error)) {
                    	self::create_connection();
                    	self::create_embeds();
                    }
                    return true;
                } catch (Exception $e) {
                    // Log exception error message
                    Grabpress::log('API call exception: ' . $e->getMessage());
                }
            } else {
                // Try creating a connection
                try {
                    // Create connection
                    self::create_connection();
                    self::create_embeds();
                } catch (Exception $e) {
                    // If creation fails
                    // Output error message to admin dashboard
                    Grabpress::$error = "There was an error connecting to the API! Please try again later!";
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
        static function watchlist_activity($feeds) {
            // If feeds
            if ($feeds) {
                // Loop through all feeds
                foreach ($feeds as $feed) {

                    //FAKE!
                    $feed->playlist->posts_per_update = 1;

                    // Fetch items from last submission
                    $submissions = 1; //self::get_items_from_last_submission($feed);

                    // Get health of feed based on # of submissions divided by posts per
                    // update
                    $feed->playlist->feed_health = $submissions / $feed->playlist->posts_per_update;

                    // Set feed submissions to # of submissions from last submission
                    $feed->playlist->submissions = $submissions;
                    $feed->playlist->count_new_videos = Grabpress_API::get_dashboard_count_new_videos($feed->playlist->id);
                }
            }

            // Return updated feeds
            return $feeds;
        }

        /**
        * Get all messages for the dashboard right panel
        * @return A composibe array with (messages, pills)
        **/
        static function get_all_dashboard_messages() {

                $api_key = Grabpress::$api_key;

                // Fetch broadcast data
                $str_get = '/messages?api_key=' . $api_key . '&message_type_id=1';
                $broadcasts_json = self::call('GET', $str_get);
                
                // Fetch pills data
                $str_get = '/messages?api_key=' . $api_key . '&message_type_id=2';
                $fortune_cookies_json = self::call('GET', $str_get);

                // Fetch resources data
                $str_get = '/messages?api_key=' . $api_key . '&message_type_id=3';
                $resources_json = self::call('GET', $str_get);

                //User messages
                $str_get = '/user_messages?api_key=' . $api_key;
                $user_message_ids_json = self::call('GET', $str_get);

                // Convert data to JSON objects
                $broadcasts = json_decode($broadcasts_json);
                $fortune_cookies = json_decode($fortune_cookies_json);
                $resources = json_decode($resources_json);
                $user_message_ids = json_decode($user_message_ids_json);

                //For each message id, fetch message body...
                $user_messages = array();
                foreach($user_message_ids as $user_message_id) {
                    $message_id = $user_message_id->user_message->message_id;
                    $str_get = '/messages/' . $message_id . '?api_key=' . $api_key;
                    $user_message_json = self::call('GET', $str_get);
                    $user_message = json_decode($user_message_json);
                    $body = $user_message->message->body;
                    $user_messages[] = array( 'id'=> $message_id , 
                                              'body'=> $body 
                    );
                }

                //Return data in a composite array
                $all_dashboard_messages = array( 
                    'broadcasts' => $broadcasts,
                    'fortune_cookies' => $fortune_cookies,
                    'resources' => $resources,
                    'user_messages' => $user_messages
                );
                return $all_dashboard_messages;
        }

        //
        static function get_dashboard_count_new_videos($playlist) {
            //Interval 1 day
            $str_interval = 'P1D';
            $api_key = Grabpress::$api_key;
            //
            $date = new DateTime();
            $date->sub(new DateInterval($str_interval));
            $since_date = $date->format('Y-m-d\Th:m');
            //
            $str_get = '/playlists/' . $playlist . '/playlist_items?api_key=' . $api_key . '&created_after=' . $since_date;
            $playlist_items_json = self::call('GET', $str_get);
            $playlist_items = json_decode($playlist_items_json);
            $count_new_videos = count($playlist_items);
            return $count_new_videos;
        }

        

    }

}
