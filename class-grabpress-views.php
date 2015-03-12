<?php

// Define class only if not defined already
if (!class_exists('Grabpress_Views')) {

    /**
     * Grabpress_Views is a class that handles the rendering of all of the views
     * required by the GrabPress plugin.
     *
     * @author Grab Media
     */
    class Grabpress_Views {

        // Static Properties
        static $list_channels = array();
        static $list_providers = array();

        /**
         * Generates catalog URL to call based on request, and calls API populating data into Grapbress catalog view
         * @param  array $request Associative array containing catalog request data
         */
        static function catalog_management($request) {

            // Set default request params
            $defaults = array(
                'sort_by' => 'created_at',
                'providers' => array(),
                'channels' => array(),
                'page_no' => 1,
            );

            // Merge default request params with those provided
            $request = array_merge($defaults, $request);

            // If keywords provided
            if (isset($request['keywords'])) {
                // Parse advanced search string
                $adv_search_params = Grabpress::parse_adv_search_string(isset($request['keywords']) ? $request['keywords'] : '' );

                // If created before date provided
                if (isset($request['created_before']) && !empty($request['created_before'])) {
                    // Format date to YYYYMMDD, i.e. 20010101
                    $created_before = self::get_formatted_date($request['created_before']);

                    // Add formatted date to advanced search params
                    $adv_search_params['created_before'] = $created_before;
                }

                // If created after date provided
                if (isset($request['created_after']) && !empty($request['created_after'])) {
                    // Format date to YYYYMMDD, i.e. 20010101
                    $created_after = self::get_formatted_date($request['created_after']);

                    // Add formatted date to advanced search params
                    $adv_search_params['created_after'] = $created_after;
                }

                // Try fetching data from API
                try {
                    // If requested providers is less than all providers returned by the API
                    if (count($request['providers']) < count(Grabpress_API::get_providers())) {
                        // If providers is an array, convert to string and add to advanced search params, else add empty string value
                        $adv_search_params['providers'] = is_array($request['providers']) ? implode($request['providers'], ',') : '';
                    }

                    // If requested channels is less than all channels returned by the API
                    if (count($request['channels']) < count(Grabpress_API::get_channels())) {
                        // If channels is an array, convert to string and add to advanced search params, else add string value of request channels directly
                        $adv_search_params['categories'] = is_array($request['channels']) ? implode($request['channels'], ',') : $request['channels'];
                    }

                    // Add sort_by and page to advanced search params
                    $adv_search_params['sort_by'] = $request['sort_by'];
                    $adv_search_params['page'] = $request['page_no'];

                    // Get catalog URL based on advanced search params
                    $url_catalog = Grabpress::generate_catalog_url($adv_search_params);

                    // Get JSON for preview of feed
                    $json_preview = Grabpress_API::get_json($url_catalog);
                    // Convert preview JSON to associative array
                    $list_feeds = json_decode($json_preview, true);

                    // If results is empty
                    if (empty($list_feeds['results'])) {
                        // Output error message to admin dashboard
                        Grabpress::$error = 'It appears we do not have any content matching your search criteria. Please modify your settings until you see the kind of videos you want in your feed';
                    } else { // Else if results exist
                        // Wrap keywords with bold and italic tags
                        // TODO: Ewwwwww at <b> and <i> tags, look to replace with CSS
                        $list_feeds['results'] = self::emphasize_keywords($adv_search_params, $list_feeds['results']);
                    }
                } catch (Exception $e) { // If fetch unsuccessful
                    // Log exception message
                    Grabpress::log('API call exception: ' . $e->getMessage());
                }

                // Fetch channels and providers from API
                self::get_channels_and_providers();
            } else { // If no keywords provided
                // Set results as empty array
                $list_feeds['results'] = array();
            }

            // Fetch channels and providers from API
            self::get_channels_and_providers();

            // Build template data array
            $request_data = array(
                'form' => $request,
                'list_channels' => Grabpress_API::get_channels(),
                'list_providers' => Grabpress_API::get_providers(),
                'list_feeds' => $list_feeds,
                'providers' => $request['providers'],
                'channels' => $request['channels'],
            );

            // Render GrabPress catalog template
            print Grabpress::render('includes/gp-catalog-template.php', $request_data);
        }

        /**
         * Creates a registered user account
         * @param  array $params Associative array holding user data required to register a new account
         */
        static function create_user($params) {
            // Get payment info if valid Paypal ID was provided else set as empty
            $payment = isset($params['paypal_id']) ? 'paypal' : '';

            // Build user data array
            $user_data = array(
                'user' => array(
                    'email' => trim($params['email']),
                    'password' => $params['password'],
                    'first_name' => $params['first_name'],
                    'last_name' => $params['last_name'],
                    'payment_detail' => array(
                        'payee' => $params['first_name'] . ' ' . $params['last_name'],
                        'company' => $params['company'],
                        'address1' => $params['address1'],
                        'address2' => $params['address2'],
                        'city' => $params['city'],
                        'state' => $params['state'],
                        'zip' => $params['zip'],
                        'country_id' => 214,
                        'preferred_payment_type' => 'Paypal',
                        'phone_number' => $params['phone_number'],
                        'paypal_id' => $params['paypal_id'],
                    ),
                ),
            );


            // Try to create new user account
            try {
                $result_json = '';
                // Call API and retrieve resulting JSON response
                $result_json = Grabpress_API::call('POST', '/accounts/-1/users/create_with_payment_details?rkey=bbd608b4067f25888a43e63582fd096708d443da', $user_data, true);
                // Parse JSON response
                $result_data = json_decode($result_json);

                Grabpress::$user_id = $result_data->user->tokens[0]->user_id;
                Grabpress::$api_key = $result_data->user->tokens[0]->access_key;
                update_option('grabpress_key', $result_data->user->tokens[0]->access_key);
                update_option('grabpress_user_id', $result_data->user->tokens[0]->user_id);

            } catch (Exception $e) { // If API call is unsuccessful
                // Log exception message
                Grabpress::log('API call exception: ' . $e->getMessage());
            }

              
            // If results has user
            if (isset($result_data->user)) {
                // Set action to 'link-user'
                $params['action'] = 'link-user';

                return $result_data->user;
                // Link account
                /* Grabpress_Views::link_account ( $params ); */
            } else { // Else no user present
                // Output already registered message to admin dashboard
                Grabpress::$error = ( isset($result_data) ) ? 'We already have a registered user with the email address ' . $params['email'] . '. If you would like to update your account information, please login to the <a href="http://www.grab-media.com/publisherAdmin/">Grab Publisher Dashboard</a>, or contact our <a href="http://www.grab-media.com/support/">support</a> if you need assistance.' : '';

                // Set action to 'create'
                $params['action'] = 'create';
            }
        }

        /**
         * Renders dashboard view and all of its components
         * @param  array $request Associative array containing request data
         */
        static function dashboard_management($request) {
            Grabpress::log();
            // Try fetching dashboard data from API
            try {
                //Get all dashboard messages
                $all_dashboard_messages = Grabpress_API::get_all_dashboard_messages();
                $broadcasts = $all_dashboard_messages['broadcasts'];
                $fortune_cookies = $all_dashboard_messages['fortune_cookies'];
                $resources = $all_dashboard_messages['resources'];
                $user_messages = $all_dashboard_messages['user_messages'];

                // Fetch watchlist and feeds data from API
                $watchlist = Grabpress_API::get_watchlist();
                $feeds = Grabpress_API::get_video_feeds();
                $feeds = Grabpress_API::watchlist_activity($feeds);

                // Get total # of feeds
                $num_feeds = count($feeds);

                // Fetch user data from API
                $user = Grabpress_API::get_user();
               
                if(isset($user)) {
                    $parts = explode("@", $user->user->email);
                    $domain = $parts['1'];

                    if ( $domain === "grab.press" ) {
                        $linked = 'account-unlinked';
                    } else {
                        $linked = 'account-linked';
                    }
                } 
                
                $publisher_status = $linked;

                // Fetch embed ID
                $embed_id = Grabpress_API::get_embed_id(false);
                //488 - Fetch the embed id here

                // Get providers from API
                $list_providers = Grabpress_API::get_providers();
            
            } catch (Exception $e) { // If fetching dashboard data unsuccessful
                // Create empty arrays for dashboard data
                $broadcasts = $fortune_cookies = $resources = $feeds = $watchlist = $list_providers = $alerts = array();
                // Set publisher status to unlinked
                $publisher_status = 'account-unlinked';
                // Set embed ID to empty sring
                $embed_id = '';
                // Log exception error message
                Grabpress::log('API call exception: ' . $e->getMessage());
            }

            // Build template data array
            $template_data = array(
                'broadcasts' => $broadcasts,
                'fortune_cookies' => $fortune_cookies,
                'resources' => $resources,
                'user_messages' => $user_messages,
                'feeds' => $feeds,
                'watchlist' => array_splice($watchlist, 0, 10), // Get first ten only
                'embed_id' => $embed_id,
                'publisher_status' => $publisher_status,
                'list_providers' => $list_providers,
                'user' => $user,
            );

            // Render dashboard view
            print Grabpress::render('includes/gp-dashboard.php', $template_data);
        }

        /**
         * Deletes feed based on a provided feed ID or renders feed management view if delete fails
         * @param  array $params Associative array containing params data
         */
        static function delete_feed($params) {
            // Try deleting feed
            try {
                // Delete feed based on ID provided via params
                Grabpress_API::delete_video_feed_by_id($params['feed_id']);
                Grabpress_Views::feed_management($params);
            } catch (Exception $e) { // If deletion unsuccessful
                // Render feed management view
                Grabpress_Views::feed_management();
            }
        }

        /**
         * Creates feed using API key, selected channels and selected providers
         * @param  array $params Associative array containing params data
         */
        static function do_create_feed($params) {
            // If valid API key and channels and providers not empty
            if (Grabpress_API::validate_key() && !empty($params['providers'])) {
                // Try fetching feed from API
                try {
                    // Fetch feed from API using params
                    Grabpress_API::create_feed($params);

                    // Render feed created successfully view
                    Grabpress_Views::feed_creation_success($params);
                } catch (Exception $e) { // If fetch unsuccessful
                    // Log exception error message
                    Grabpress::log('API call exception: ' . $e->getMessage());
                }
            } else { // If no valid key and/or channels is empty and/or providers is empty
                // Set as invalid
                Grabpress::$invalid = true;

                // Render feed management view
                Grabpress_Views::feed_management($params);
            }
        }

        /**
         * Store the edits made to the feed and display success message
         * @param  array $params An associative array holding all of the params
         * required for storing the edits
         */
        static function do_edit_feed($params) {
            try {
                Grabpress_API::edit_feed($params);
                self::feed_update_success($params);
            } catch (Exception $e) {
                Grabpress::log('API call exception: ' . $e->getMessage());
            }
        }

        /**
         * Renders edit feed template based on the params provided
         * @param  array $params An associative array holding all of the params
         * required for rendering the edit feed template
         */
        static function edit_feed( $params ) {
            // Log caller debug info
            Grabpress::log();

            // Fetch channel and provider info from API
            self::get_channels_and_providers();
            // Get total # of channels and providers
            $channels_total = count(self::$list_channels);
            $providers_total = count(self::$list_providers);

            // If valid API key exists
            if (Grabpress_API::validate_key()) {
                // Try fetching feed from API
                try {
                    //Feed object to edit by
                    $feed = Grabpress_API::get_video_feed_by_id($params['feed_id']);    
                    $search_params = json_decode($feed->playlist->search_parameters);               
                    $providers_total = isset($search_params->providers) && is_array($search_params->providers) ? count($search_params->providers) : 0;
                    
                } catch (Exception $e) { // Catch exception if fetch is unsuccessful
                    // Log exception message
                    Grabpress::log('API call exception: ' . $e->getMessage());
                }

                // Convert queried providers and channels strings to arrays
                // $queried_providers = isset($query['providers']) ? explode(',', $query['providers']) : '';
                // $queried_channels = isset($query['channels']) ? explode(',', $query['channels']) : '';

              
                //Reset form  and push data in array with feed data
                if (isset($params['action']) && $params['action'] == 'edit-feed' ) {
                    $template_data = array(
                        'form' => array(
                            'referer' => 'edit',
                            'action' => 'modify',
                            'feed_id' => $feed->playlist->id,
                            'name' => $feed->playlist->name,
                            'keywords_and' => isset($search_params->keywords_and) ? $search_params->keywords_and : '',
                            'keywords_not' => isset($search_params->keywords_not) ? $search_params->keywords_not : '',
                            'keywords_or' => isset($search_params->keywords_or) ? $search_params->keywords_or : '',
                            'keywords_phrase' => isset($search_params->keywords_phrase) ? $search_params->keywords_phrase : '',
                            'channels' => isset($search_params->categories) ? $search_params->categories : '',
                            'providers' => isset($search_params->providers) ? $search_params->providers : '',
                            'providers_total' => $providers_total,                            
                        ),
                            'list_providers' => self::$list_providers,
                            'providers_total' => $providers_total,
                            'list_channels' => self::$list_channels,
                            'channels_total' => $channels_total,
                           
                    );

                    // Render edit feed template using template data
                    print Grabpress::render('includes/gp-feed-template.php', $template_data);
                } else {
                    // If params 'channels' and 'providers' were provided and are not empty
                    if (isset($params['channels'], $params['providers']) && !empty($params['channels']) && !empty($params['providers'])) {

                        // Build template data array
                        $template_data = array(
                            'form' => array(
                                'action' => 'modify',
                                'feed_id' => $params['feed_id'],
                                'name' => $params['name'],
                                'channels' => $params['channels'],
                                'keywords_and' => $params['keywords_and'],
                                'keywords_not' => $params['keywords_not'],
                                'keywords_or' => $query['keywords'],
                                'keywords_phrase' => $query['keywords_phrase'],
                                'limit' => $params['limit'],
                                'schedule' => $params['schedule'],
                                'active' => $params['active'],
                                'publish' => $params['publish'],
                                'click_to_play' => $params['click_to_play'],
                                'author' => $params['author'],
                                'providers' => $params['providers'],
                                'category' => $params['category'],
                                'exclude_tags' => $feed->feed->exclude_tags,
                                'include_tags' => $feed->feed->include_tags,
                            ),
                            'list_providers' => self::$list_providers,
                            'providers_total' => $providers_total,
                            'list_channels' => self::$list_channels,
                            'channels_total' => $channels_total,
                            'blogusers' => $wp_users,
                        );

                        // Render edit feed template using template data
                        print Grabpress::render('includes/gp-feed-template.php', $template_data);
                    } else { // If no channel and/or provider info provided
                        // Create empty categories array
                        $categories = array();

                        // If custom options category is an array
                        if (isset($feed->feed->custom_options->category) && is_array($feed->feed->custom_options->category)) {
                            // Loop through the array
                            foreach ($feed->feed->custom_options->category as $category) {
                                // Push category IDs into categories array
                                $categories[] = get_cat_id($category);
                            }
                        }

                        // Build template data array
                        $template_data = array(
                            'form' => array(
                                'referer' => 'edit',
                                'action' => 'modify',
                                'feed_id' => $feed->feed->id,
                                'name' => $feed->feed->name,
                                'channels' => $query['categories'],
                                'keywords_and' => $query['keywords_and'],
                                'keywords_not' => $query['keywords_not'],
                                'keywords_or' => $query['keywords'],
                                'keywords_phrase' => $query['keywords_phrase'],
                                'limit' => $feed->feed->posts_per_update,
                                'schedule' => $feed->feed->update_frequency,
                                'active' => $feed->feed->active,
                                'publish' => $feed->feed->custom_options->publish,
                                'click_to_play' => $feed->feed->auto_play,
                                'author' => $feed->feed->custom_options->author_id,
                                'providers' => $queried_providers,
                                'category' => $categories,
                                'exclude_tags' => $feed->feed->exclude_tags,
                                'include_tags' => $feed->feed->include_tags,
                            ),
                            'list_providers' => self::$list_providers,
                            'providers_total' => $providers_total,
                            'list_channels' => self::$list_channels,
                            'channels_total' => $channels_total,
                            'blogusers' => $wp_users,
                            'localhost' => false
                        );

                        // Render edit feed template using template data
                        print Grabpress::render('includes/gp-feed-template.php', $template_data);
                    }
                }
            }
        }

        /**
         * Loops through each video summary in results and emphasizes keywords
         * @param  array $params  Associative array with params data
         * @param  array $results PHP JSON object containing preview HTML
         * @return array          PHP JSON object containing preview HTML with keywords emphasized
         */
        static function emphasize_keywords($params, $results) {
            // Parse keywords from params
            $keywords = self::get_keywords_from_params($params);

            // Loop through results
            foreach ($results as $key => $result) {
                // Add emphasis to keywords in video summary
                $results[$key]['video']['summary'] = self::emphasize_result_keywords($keywords, $result['video']['summary']);
            }

            // Return updated results with keywords emphasized
            return $results;
        }

        /**
         * Wraps all keyword with the HTML <strong> tag
         * @param  array $keywords Array containing all keywords
         * @param  string $result   Video summary
         * @return string           Video summary with emphasized keywords
         */
        static function emphasize_result_keywords($keywords, $result) {
            // Loop through keywords
            foreach ($keywords as $keyword) {
                // Build RegEx for keyword
                $regex = '/\\b' . $keyword . '/ui';

                // Strip the result down to just the keyword
                $replace_keywords = substr($result, stripos($result, $keyword), strlen($keyword));

                // Wrap keywords in <strong> HTML tags
                // TODO: Add emphasis via CSS, not HTML
                $replace_keywords = '<strong>' . $replace_keywords . '</strong>';

                // Replace keywords with emphasize version in result
                $result = preg_replace($regex, $replace_keywords, $result);
            }

            // Return result with emphasized keywords
            return $result;
        }

        /**
         * Renders feed created successfully view
         * @param  array $params Associative array containing params data
         */
        static function feed_creation_success($params) {
            // Render feed created successfully view
            print Grabpress::render('includes/gp-feed-created-template.php', array('request' => $params));
        }

        /**
         * Renders feed management view
         * @param  array $params Associative array holding params data
         */
        static function feed_management($params) {


            // Fetch channels and providers from API
            self::get_channels_and_providers();

            // Get total number of channels and providers
            $providers_total = count(self::$list_providers);
            $channels_total = count(self::$list_channels);

            $video_feeds = Grabpress_API::get_video_feeds();

            // Build template data array
            $template_data = array(
                'form' => $params,
                'list_providers' => self::$list_providers,
                'providers_total' => $providers_total,
                'list_channels' => self::$list_channels,
                'channels_total' => $channels_total,
                'video_feeds' => $video_feeds,
            );


            // Render feed management view
            print Grabpress::render('includes/gp-feed-template.php', $template_data);
        }

        /**
         * [feed_name_unique_callback description]
         */
        static function feed_name_unique_callback() {
            // Set duplicated name default value to false
            $duplicated_name = 'false';

            // Get feed name and ID from request
            $name = $_REQUEST['name'];
            $id = $_REQUEST['id'];

            // Try fetching feeds from API
            try {
                // Fetch feeds from API
                $feeds = Grabpress_API::get_video_feeds();

                // Get total # of feeds
                $num_feeds = count($feeds);

                // Loop through each feed
                foreach ($feeds as $record_feed) {
                    // If feed name is is the same as request feed name and feed ID is different
                    if ($record_feed->feed->name == $name && $record_feed->feed->id != $id) {
                        // Set duplicated name to true
                        $duplicated_name = 'true';

                        // Break out of loop
                        break;
                    }
                }
            } catch (Exception $e) { // If fetch is unsuccessful
                // Render feed management view
                Grabpress_Views::feed_management();
            }

            // Output duplicated name
            echo $duplicated_name;

            die(); // this is required to return a proper result
        }

        /**
         * Renders feed created successfully view
         * @param  array $params Associative array containing params data
         */
        static function feed_update_success($params) {
            // Render feed created successfully view
            print Grabpress::render('includes/gp-feed-updated-template.php', array('request' => $params));
        }

        static function render_general_settings($request) {
            
             //Try to get current linked user.
            $user = Grabpress_API::get_user();
            if ($user != null ) {
                $email = isset($user->user->email) ? $user->user->email : $user->email;
                $parts = explode('@', $email);
                $domain = $parts['1'];
                if($domain === 'grab.press'){
                    $linked = false;    
                } else {
                    $linked = true;
                }
           } else {
                $linked = false;
           }

            //Handle link user action.
            if (isset($request['action']) && $request['action'] == 'link-user') {

                $user = Grabpress_Views::link_account($request);

                //Update User information
                if (isset($user->active)) {
                    $linked = true; 
                    update_option('grabpress_key', $user->tokens[0]->access_key);
                    update_option('grabpress_user_id', $user->tokens[0]->user_id);
                    Grabpress::$user_id = $user->tokens[0]->user_id;
                    Grabpress::$api_key = $user->tokens[0]->access_key;
                } else {
                    $linked = false; 
                    Grabpress::show_message();
                }
                
            } else if (isset($request['action']) && $request['action'] == 'create-user') {
                //We Try to create a new user
                $user = self::create_user($request);
                if ($user->active) {
                      $linked = true; 
                }
               

            } else if ( isset($request['action']) && $request['action'] == 'unlink') {
                //Handle unlink action
                $linked = false;
                self::unlink_account();
            } else {

            }
    
            // Build template data array
            $request_data = array(
                'request' => $request,
                'has_access' => Grabpress::check_permissions_for('gp-account'),
                'linked' => $linked,
                'user' => $user
            );

            // Render account template
            print Grabpress::render('includes/gp-general-settings.php', array('request' => $request_data));
        }
        
        /**
         * Gets catalog callback from URL requested
         */
        static function get_catalog_callback() {
            // Strip slashes recursively on request
            $request = Grabpress::strip_deep($_REQUEST);

            // Set default request params
            $defaults = array(
                'providers' => array(),
                'channels' => array(),
                'sort_by' => 'created_at',
                'empty' => 'true',
                'page' => 1,
            );

            // Merge default request params with those provided
            $request = array_merge($defaults, $request);

            // If request empty as determined by empty boolean
            if ($request['empty'] == 'true') {
                // Set results to empty array
                $list_feeds['results'] = array();

                // Set empty as true
                $empty = 'true';
            } else { // Else not empty
                // Parse advanced search string for keywords, else assign empty string
                $adv_search_params = Grabpress::parse_adv_search_string(isset($request['keywords']) ? $request['keywords'] : '' );

                // Get date range params
                $adv_search_params['created_before'] = self::get_created_before_date($request);
                $adv_search_params['created_after'] = self::get_created_after_date($request);

                // Try fetching data from API
                try {
                    // If requested providers is less than all providers returned by the API
                    if (count($request['providers']) < count(Grabpress_API::get_providers())) {
                        // If providers is an array, convert to string and add to advanced search params, else add empty string value
                        $adv_search_params['providers'] = is_array($request['providers']) ? implode($request['providers'], ',') : '';
                    }

                    // If requested channels is less than all channels returned by the API
                    if (count($request['channels']) < count(Grabpress_API::get_channels())) {
                        // If channels is an array, convert to string and add to advanced search params, else add string value of request channels directly
                        $adv_search_params['categories'] = is_array($request['channels']) ? implode($request['channels'], ',') : $request['channels'];
                    }

                    // Add sort_by and page to advanced search params
                    $adv_search_params['sort_by'] = $request['sort_by'];
                    $adv_search_params['page'] = $request['page'];

                    // Get catalog URL based on advanced search params
                    $url_catalog = Grabpress::generate_catalog_url($adv_search_params);

                    // Get JSON for preview of feed
                    $json_preview = Grabpress_API::get_json($url_catalog);

                    // Convert preview JSON to associative array
                    $list_feeds = json_decode($json_preview, true);

                    // If results is empty
                    if (empty($list_feeds['results'])) {
                        // Output error message to admin dashboard
                        Grabpress::$error = 'It appears we do not have any content matching your search criteria. Please modify your settings until you see the kind of videos you want in your feed';
                    } else { // Else if results exist
                        // Wrap keywords with bold and italic tags
                        // TODO: Ewwwwww at <b> and <i> tags, look to replace with CSS
                        $list_feeds['results'] = self::emphasize_keywords($adv_search_params, $list_feeds['results']);
                    }

                    $empty = "false";
                } catch (Exception $e) { // If fetch unsuccessful
                    // Log exception message
                    Grabpress::log('API call exception: ' . $e->getMessage());
                }

                // Fetch channels and providers from API
                self::get_channels_and_providers();
            }

            // Fetch channelsand providers from API
            self::get_channels_and_providers();

            // Build template data array
            $request_data = array(
                'form' => $request,
                'list_channels' => self::$list_channels,
                'list_providers' => self::$list_providers,
                'list_feeds' => $list_feeds,
                'providers' => $request['providers'],
                'channels' => $request['channels'],
                'empty' => $empty,
            );

            // Render GrabPress catalog AJAX
            print Grabpress::render('includes/gp-catalog-ajax.php', $request_data);

            // Terminate
            die();
        }

        /**
         * Fetch the lists of channels and providers from the API
         */
        static function get_channels_and_providers() {
            // Reset values
            self::$list_channels = array();
            self::$list_providers = array();

            // Try fetching channel and provider info from API
            try {
                // If fetch successful store lists in arrays
                self::$list_channels = Grabpress_API::get_channels();
                self::$list_providers = Grabpress_API::get_providers();
            } catch (Exception $e) { // Catch exception if fetch is unsuccessful
                // Log exception message
                Grabpress::log('API call exception: ' . $e->getMessage());
            }
        }

        /**
         * Checks for a created before date and formats if exists
         * @param  array $request Associative array containing request data
         * @return string         Created before date in YYYYMMDD format, i.e. 20010101
         */
        static function get_created_before_date($request) {
            // If created before date exists
            if (isset($request['created_before']) && !empty($request['created_before'])) {
                // Format date to YYYYMMDD, i.e. 20010101
                $created_before = self::get_formatted_date($request['created_before']);

                // Return formatted date
                return $created_before;
            }

            // Return empty string
            return '';
        }

        /**
         * Checks for a created after date and formats if exists
         * @param  array $request Associative array containing request data
         * @return string         Created after date in YYYYMMDD format, i.e. 20010101
         */
        static function get_created_after_date($request) {
            // If created before date exists
            if (isset($request['created_after']) && !empty($request['created_after'])) {
                // Format date to YYYYMMDD, i.e. 20010101
                $created_after = self::get_formatted_date($request['created_after']);

                // Return formatted date
                return $created_after;
            }

            // Return empty string
            return '';
        }

        /**
         * Takes a request date value and converts to YYYYMMDD format
         * @param  string $request_date Timestamp
         * @return string               Formatted date, YYYYMMDD
         */
        static function get_formatted_date($request_date) {
            // Create representation of datetime from created after value
            $datetime = new DateTime($request_date);

            // Format date to YYYYMMDD, i.e. 20010101
            $formatted_date = $datetime->format('Ymd');

            return $formatted_date;
        }

        /**
         * Parses and compiles all keywords from keywords, keywords_and, keywords_or
         * @param  array $params Associative array containing params
         * @return array         An array containing a compiled list of keywords
         */
        static function get_keywords_from_params($params) {
            // Create empty keywords array
            $keywords = array();

            // If keyword phrase provided and not empty
            if (isset($params['keywords_phrase']) && !empty($params['keywords_phrase'])) {
                // Remove any character besides Latin, digit, hyphen, single quote and space from keyword phrase and push trimmed version into keywords array
                array_push($keywords, preg_replace('/[^\p{Latin}0-9-\' ]/u', '', trim($params['keywords_phrase'])));
            }

            // If keywords_and provided and not empty
            if (isset($params['keywords_and']) && !empty($params['keywords_and'])) {
                // Strip whitespace from keywords_and
                $keys = trim($params['keywords_and']);

                // Convert keywords_and into an array delimited by a single space
                $keywords_and = explode(' ', $keys);

                // Loop through each keywords_and
                foreach ($keywords_and as $key => $value) {
                    // Remove any character besides Latin, digit, hyphen, single quote and space from keyword_and value
                    $keywords_and[$key] = preg_replace('/[^\p{Latin}0-9\' ]/u', '', trim($value));

                    // If value is empty
                    if (empty($keywords_and[$key])) {
                        // Remove key from array
                        unset($keywords_and[$key]);

                        // Continue to next key in the loop
                        continue;
                    }
                }

                // If keywords_and array isn't empty merge it with keywords array
                $keywords = (!empty($keywords_and) ) ? array_merge($keywords, $keywords_and) : $keywords;
            }

            // If keywords_or provided and not empty
            if (isset($params['keywords_or']) && !empty($params['keywords_or'])) {
                // Strip whitespace from keywords_or
                $keys = trim($params['keywords_or']);

                // Convert keywords_or into an array delimited by a single space
                $keywords_or = explode(' ', $keys);

                // Loop through each keywords_or
                foreach ($keywords_or as $key => $value) {
                    // Remove any character besides Latin, digit, hyphen, single quote and space from keyword_or value
                    $keywords_or[$key] = preg_replace('/[^\p{Latin}0-9\']/u', '', trim($value));

                    // If value is empty
                    if (empty($keywords_or[$key])) {
                        // Remove key from array
                        unset($keywords_or[$key]);

                        // Continue to next key in the loop
                        continue;
                    }
                }

                // If keywords_or array isn't empty merge it with keywords array
                $keywords = (!empty($keywords_or) ) ? array_merge($keywords, $keywords_or) : $keywords;
            }

            // Return array of compiled keywords
            return $keywords;
        }

        /**
         * [get_preview_callback description]
         */
        static function get_preview_callback() {
            // Get preview videos for request
            Grabpress_Views::grabpress_preview_videos($_REQUEST);

            // Terminate
            die();
        }

        /**
         * Fetches GrabPress preview videos for feed preview
         * @param  array $request Associative array containing request data
         */
        static function grabpress_preview_videos($request) {
            // Log caller debug info
            Grabpress::log();

            // Setup default params
            $defaults = array(
                'sort_by' => 'created_at',
                'providers' => array(),
                'channels' => array(),
                'page' => 1,
            );

            // Merge default params with request params
            $request = array_merge($defaults, $request);

            // Fetch channels and providers from API
            self::get_channels_and_providers();

            // If keywords requested
            if (isset($request['keywords'])) {
                // Parse keywords from the advanced search string
                $adv_search_params = Grabpress::parse_adv_search_string(isset($request['keywords']) ? $request['keywords'] : '' );
            } else if (isset($request['feed_id'])) { // Else if feed ID provided
                // Try to fetch feed from API
                $feed = Grabpress_API::get_video_feed_by_id($request['feed_id']);
                // Create empty array to hold query data
                $query = array();
                // Convert query string to an array
                //parse_str(parse_url($feed->feed->url, PHP_URL_QUERY), $query);
                // Create advanced search params from query array
                //$adv_search_params = $query;
                /////////////////////////////////
                $search_parameters = $feed->playlist->search_parameters;
                $search_parameters = json_decode($search_parameters);
                $adv_search_params = array( 'keywords_and' => $search_parameters->keywords_and,
                                            'keywords_not' => $search_parameters->keywords_not,
                                            'keywords_or' => $search_parameters->keywords_or,
                                            'keywords_phrase' => $search_parameters->keywords_phrase,
                                            'keyword_exact_phrase' => $search_parameters->keywords_phrase,
                                            );
                // Add keywords to request data
                $request['keywords'] = Grabpress::generate_adv_search_string($adv_search_params);

                // Convert providers and channels strings to arrays and add to request data
                // $request['providers'] = explode(',', $query['providers']);
                // $request['channels'] = explode(',', $query['categories']);

                /////////////////////////////////
                if(isset($search_parameters->providers)) {
                    $request['providers'] =  $search_parameters->providers;
                } else {
                    $request['providers'] = array();
                }
 
                if(is_string($request['providers'])) {
                    $request['providers'] = explode( ',', $request['providers']);
                } 

                if(isset($search_parameters->categories)) {
                    $request['channels'] = $search_parameters->categories;
                } else {
                    $request['channels'] = array();
                }
                if(is_string($request['channels'])) {
                    $request['channels'] = explode( ',', $request['channels']);
                } 

            } else {
                // Create advanced search params from request
                $adv_search_params = $request;

                // Add keywords to request data
                $request['keywords'] = Grabpress::generate_adv_search_string($adv_search_params);

                // TODO: Figure out if this is needed, can't see where it is used
                // $keywords_emphasize['keywords_and'] = $request['keywords_and'];
                // $keywords_emphasize['keywords_or'] = $request['keywords_or'];
                // $keywords_emphasize['keywords_phrase'] = $request['keywords_phrase'];
            }

            // Get date range params
            $adv_search_params['created_before'] = self::get_created_before_date($request);
            $adv_search_params['created_after'] = self::get_created_after_date($request);

            // If number of requested providers is less than number of providers returned from the API
            if (count($request['providers']) < count(self::$list_providers)) {
                // If providers is an array convert to string and add to advanced search params, else set value as empty string
                $adv_search_params['providers'] = is_array($request['providers']) ? implode($request['providers'], ',') : '';
            } else { // If number is greater than or equal to
                // Remove providers from advanced search params
                unset($adv_search_params['providers']);
            }

            // If number of requested channels is less than number of providers returned from the API
            if (count($request['channels']) < count(self::$list_channels)) {
                // If channels is an array convert to string and add to advanced search params, else set value as request channels string
                $adv_search_params['categories'] = is_array($request['channels']) ? implode($request['channels'], ',') : $request['channels'];
            } else { // If number is greater than or equal to
                // Remove categories and channels from advanced search params
                unset($adv_search_params['categories']);
                unset($adv_search_params['channels']);
            }

            // Add request page to advanced search params
            $adv_search_params["page"] = $request["page"];

            // Generate catalog URL from advanced search params
            $url_catalog = Grabpress::generate_catalog_url($adv_search_params);

            // Try fetching catalog preview JSON
            try {
                // Get JSON from catalog URL
                $json_preview = Grabpress_API::get_json($url_catalog);

                // Convert JSON string to PHP JSON object
                $list_feeds = json_decode($json_preview, true);

                // If results are empty
                if (empty($list_feeds['results'])) {
                    // Output error message to admin dashboard
                    Grabpress::$error = 'It appears we do not have any content matching your search criteria. Please modify your settings until you see the kind of videos you want in your feed';
                } else { // Else results exist
                    // Emphasize all keywords in results
                    $list_feeds['results'] = Grabpress_Views::emphasize_keywords($adv_search_params, $list_feeds['results']);
                }
            } catch (Exception $e) { // Else if fetch is unsuccessful
                // Create empty feeds array
                $list_feeds = array();

                // Log exception message
                Grabpress::log('API call exception: ' . $e->getMessage());
            }

            // Build request data array
            $request_data = array(
                'form' => $request,
                'list_channels' => self::$list_channels,
                'list_providers' => self::$list_providers,
                'list_feeds' => $list_feeds,
                'providers' => $request['providers'],
                'channels' => $request['channels'],
                'empty' => 'false',
            );

            // Render catalog preview videos
            print Grabpress::render('includes/gp-catalog-ajax.php', $request_data);

            // Terminate
            die();
        }

        /**
         * [insert_video_callback description]
         */
        static function insert_video_callback() {
            // If GrabPress is not permitted to edit single posts
            if (!Grabpress::check_permissions_for('single-post')) {
                // Abort and display permissions message
                Grabpress::abort('Insufficient Permissions');
            }

            // Get video ID and format from request
            $video_id = $_POST['video_id'];
            $format = $_POST['format'];
            $width = $_POST['width'];
            $height = $_POST['height'];
            $ap = isset($_POST['auto_play']) ? $_POST['auto_play'] : '0'; 
           
        
            // Try fetching video info from API
            try {

                // Get MRSS for video
                $objXml = Grabpress_API::get_video_mrss($video_id);

                // Get preview image URL for video
                $img_url = Grabpress_API::get_preview_url($objXml);

                // Loop through each item in video MRSS
                foreach ($objXml->channel->item as $item) {
                    // If post is the format
                    if ('post' == $format) {
                        
                        // Build HTML to embed in post
                        $html = '<div id="grabpreview">';
                        $html .= '	<p><img src="' . $img_url . '" /></p>';
                        $html .= '</div><!-- #grabpreview -->';
                        $html .= '<p>' . $item->description . '</p>';
                        $html .= '<!--more-->';
                        $html .= '<div style="width:'.$width.'px" id="grabembed">';
                        $html .= '	[grabpress_video video_id="'. $video_id .'"  auto_play="'.$ap.'" width="'.$width.'" height="'. $height.'"  guid="' . $item->guid . '"]';
                        $html .= '	<p>Thanks for checking us out. Please take a look at the rest of our videos and articles.</p><br />';
                        $html .= '	<p><img src="' . $item->grabprovider->attributes()->logo . '"" /></p>';
                        $html .= '<p>To stay in the loop, bookmark <a href="/">our homepage</a>.</p>';
                        $html .= '</div><!-- #grabembed -->';
                        // TODO: Inline CSS makes me cry, it's bad
                        $html .= '<style>';
                        $html .= '	div#grabpreview {';
                        $html .= '		display:none !important';
                        $html .= '	}';
                        $html .= '</style>';

                        // Build post data array
                        $post_data = array(
                            'post_content' => $html,
                            'post_title' => 'VIDEO: ' . esc_html($item->title),
                            'post_type' => 'post',
                            'post_status' => 'draft',
                            'tags_input' => esc_html($item->mediagroup->mediakeywords),
                        );

                        // Insert post to WPDB and get its ID
                        $post_id = wp_insert_post($post_data);

                        $upload_dir = wp_upload_dir();
                        $image_url = $img_url;
                        $image_data = file_get_contents($image_url);

                        //Get image URL
                        $filename = basename($image_url);

                        if (validate_file($filename)) {//sanitize file path
                            GrabPress::error(" invalid filename " . $filename);
                        }

                        if (wp_mkdir_p($upload_dir['path']))
                            $file = $upload_dir['path'] . '/' . $filename;
                        else
                            $file = $upload_dir['basedir'] . '/' . $filename;
                        file_put_contents($file, $image_data);

                        $wp_filetype = wp_check_filetype($filename, null);
                        $attachment = array(
                            'guid' => sanitize_file_name($filename),
                            'guid' => "endworld",
                            'post_mime_type' => $wp_filetype['type'],
                            'post_title' => sanitize_file_name($filename),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        );

                        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
                        include_once(ABSPATH . 'wp-admin/includes/image.php');

                        //Set feature image for post
                        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
                        wp_update_attachment_metadata($attach_id, $attach_data);

                        // Build redirect data array
                        $redirect_info = array(
                            'status' => 'redirect',
                            'url' => 'post.php?post=' . $post_id . '&action=edit',
                        );

                        // Output JSON string containing redirect info
                        echo json_encode($redirect_info);
                    } else if ('embed' == $format) { // If embed format
                        //488 - Removed - Get embed id
                        $ctp_embed_id = Grabpress_API::get_embed_id(false); //'2320719';
                        // Build ok status array
                        $ok_status = array(
                            'status' => 'ok',
                            'content' => '<div id="grabDiv' . $ctp_embed_id . '">[grabpress_video guid="' . $item->guid . '"]</div>',
                        );

                        // Output JSON string containing ok status
                        echo json_encode($ok_status);
                    }
                }
            } catch (Exception $e) { // If video info fetch unsuccessful
                // Render feed management view
                Grabpress_Views::feed_management();
            }

            die(); // this is required to return a proper result
        }

        /**
         * Connects GrabPress with Grab account and stores connector
         * @param  array $params Associative array containing account information
         */
        static function link_account($params) {

           $account_id = get_option('grabpress_account_id', '-1');
         
            // If email and password params are provided
            if (isset($params['email']) && isset($params['password']) ) {
                // Build credentials array
                $credentials = array(
                    'username' => isset($params['email']) ? $params['email'] : '',
                    'password' => isset($params['password']) ? $params['password'] : ''
                );

                // Try to check if a user exist in backend by email and password
                try {


                    // Fetch JSON string from API


                    $user_json = Grabpress_API::call('GET', '/accounts/'. $account_id .'/users/?email=' . $credentials["username"] . '&password=' . $credentials["password"], array(), true);
                  

                    $user_data = json_decode($user_json);
            
                    // If user exists
                    if (isset($user_data->user)) {

                        //Store account id, we need it to make the proper service call.
                        update_option('grabpress_account_id', $user_data->user->account_id);        

                        Grabpress::$user_id = $user_data->user->tokens[0]->user_id;
                        Grabpress::$api_key = $user_data->user->tokens[0]->access_key;
                        update_option('grabpress_key', $user_data->user->tokens[0]->access_key);
                        update_option('grabpress_user_id', $user_data->user->tokens[0]->user_id);
                    
                        // Get user from user data
                        $user = $user_data->user;
                        //Return the user
                        return $user;
                        // Output messages to admin dashboard
                        /* Grabpress::plugin_messages (); */
                    } else {
                        // Display error message in admin dashboard
                        Grabpress::$error = 'No user with the supplied email and password combination exists in our system. Please try again.';
                    }

                    // Set action to default
                    $params['action'] = 'default';
                } catch (Exception $e) { // If fetch is unsuccessful
                    // Log exception message
                    Grabpress::log('API call exception: ' . $e->getMessage());
                }
            } else { // If credentials are not provided
                // Log custom abort message
                Grabpress::abort('Attempt to link user with incomplete form data.');
                return;
            }
        }

        /**
         * Renders feed with prefilled data and keywords
         * @param  array $params An associative array holding all of the params
         * required for prefilling the feed
         */
        static function prefill_feed($params) {
            // Log caller debug info
            Grabpress::log();

            // If valid API key exists
            if (Grabpress_API::validate_key()) {
                self::get_channels_and_providers();
            }

            // Get total # of channels and providers
            $channels_total = count(self::$list_channels);
            $providers_total = count(self::$list_providers);

            // Get WordPress users
            $wp_users = get_users();

            // Parse advanced search string
            $keywords = Grabpress::parse_adv_search_string(isset($params['keywords']) ? $params['keywords'] : '' );

            // Build template data array
            $template_data = array(
                'form' => array(
                    'referer' => 'create',
                    'action' => 'update',
                    'channels' => $params['channels'],
                    'keywords_and' => $keywords['keywords_and'],
                    'keywords_not' => $keywords['keywords_not'],
                    'keywords_or' => $keywords['keywords_or'],
                    'keywords_phrase' => $keywords['keywords_phrase'],
                    'providers' => $params['providers'],
                    'publish' => $params['publish'],
                    'click_to_play' => $params['click_to_play'],
                    'category' => '',
                ),
                'list_providers' => self::$list_providers,
                'providers_total' => $providers_total,
                'list_channels' => self::$list_channels,
                'channels_total' => $channels_total,
                'blogusers' => $wp_users
            );

            // Render edit feed template using template data
            print Grabpress::render('includes/gp-feed-template.php', $template_data);
        }

        /**
         * Handles template settings
         * @param  array $request Associative array containing request data
         */
        static function template_management($request) {
            // Set default params
            $defaults = array(
                'width' => 480,
                'ratio' => 'widescreen',
                'playback' => 'auto',
                'action' => 'new',
            );

            // If action provided and not default
            if (isset($request['action']) && $request['action'] != 'default') {
                // Determine aspect ratio
                if ('widescreen' == $request['ratio']) {
                    $ratio = '16:9';
                } else {
                    $ratio = '4:3';
                }

                // Get width from request
                $width = isset($request['width']) ? $request['width'] : $defaults['width'];

                // If widescreen 16:9
                if ('16:9' == $ratio) {
                    // Determine height based on 16:9
                    $height = (int) ( ( $width / 16 ) * 9 );
                } else { // If normal aspect ratio 4:3
                    // Determine height based on 4:3
                    $height = (int) ( ( $width / 4 ) * 3 );
                }

                // Try making API call
                try {
                    // Build player settings array
                    $player_settings = array(
                        'player_setting' => array(
                            'ratio' => $ratio,
                            'width' => $width,
                            'height' => $height,
                        ),
                    );

                    // Try to update player template via API call
                    // 488 - Removed - PUT/POST connectors player_settings $player_settings
                } catch (Exception $e) { // If unsuccessful API call
                    Grabpress::log('API call exception: ' . $e->getMessage());
                }

                // Render template modified view
                print Grabpress::render('includes/gp-template-modified.php');
            } else { // Else action doesn't exist or is default
                // Use default settings
                $settings = $defaults;

                // Try fetching stored player settings
                try {
                    // Fetch stored player settings
                    $player = Grabpress_API::get_player_settings();

                    // If player settings retrieved
                    if ($player) {
                        // Set dimensions and ratio according to retrieved settings
                        $settings['width'] = $player['width'];
                        $settings['height'] = $player['height'];
                        $settings['ratio'] = '16:9' == $player['ratio'] ? 'widescreen' : 'standard';

                        // If widescreen
                        if ('widescreen' == $settings['ratio']) {
                            // TODO: Is this necessary? Shouldn't player height exist already from get_player_settings()?
                            // $player_height = (int)($player['width']/16)*9;
                            // Update selected aspect settings
                            $settings['widescreen_selected'] = true;
                            $settings['standard_selected'] = false;
                        } else { // Else normal screen
                            // TODO: Is this necessary? Shouldn't player height exist already from get_player_settings()?
                            // $player_height = (int)($player['width']/4)*3;
                            // Update selected aspect settings
                            $settings['widescreen_selected'] = false;
                            $settings['standard_selected'] = true;
                        }

                        // TODO: Is this necessary? Shouldn't player height exist already from get_player_settings()?
                        // $settings['height'] = $player_height;
                        // Set action as edit
                        $settings['action'] = 'edit';
                    }

                    // If widescreen_selected and standard_selected are not set
                    if (!isset($settings['widescreen_selected'], $settings['standard_selected'])) {
                        // Set default values
                        $settings['widescreen_selected'] = true;
                        $settings['standard_selected'] = false;
                    }
                } catch (Exception $e) { // If fetch unsuccessful
                    // Log exception error message
                    Grabpress::log('API call exception: ' . $e->getMessage());

                    // Set default values
                    $settings['widescreen_selected'] = true;
                    $settings['standard_selected'] = false;
                }

                // Render template view
                print Grabpress::render('includes/gp-template.php', array(
                            'form' => $settings
                ));
            }
        }

        /**
         * [toggle_feed_callback description]
         * @param  array $request Associative array containing request data
         */
        static function toggle_feed_callback($request) {
            // Reference global for WordPress DB
            global $wpdb;

            // Get ID and active status from HTTP request in the form of an integer
            $feed_id = intval($_REQUEST['feed_id']);
            $active = intval($_REQUEST['active']);

            // Build post data array
            $post_data = array(
                'feed' => array(
                    'active' => $active,
                ),
            );

            // Try PUT
            try {
                //488 - This should be replaced by some code that activates/deactivates a feed_id
                // 488 - Removed - PUT connectors feeds $post_data
                // Get feeds from API
                $feeds = Grabpress_API::get_video_feeds();

                // Get total # of feeds
                $num_feeds = count($feeds);

                // Set default value of 0 active feeds
                $active_feeds = 0;

                // Loop through all feeds
                for ($i = 0; $i < $num_feeds; $i++) {
                    // Get feed from current iteration
                    $feed = $feeds[$i];

                    // If active feeds is greater than 0
                    // TODO: Is active a boolean? If so change the way we check for it
                    if ($feed->feed->active > 0) {
                        // Increment active feeds
                        $active_feeds++;
                    }
                }
            } catch (Exception $e) { // If PUT unsuccessful
                // Log exception error message
                Grabpress::log('API call exception: ' . $e->getMessage());
            }

            // Output active feeds against total number of feeds, '#-#'
            echo $active_feeds . '-' . $num_feeds;

            die(); // this is required to return a proper result
        }

        /**
         * [toggle_watchlist_callback description]
         */
        static function toggle_watchlist_callback() {
            // Reference global WPDB
            global $wpdb;

            // Get feed ID and watch list in integer format
            $feed_id = intval($_REQUEST['feed_id']);
            $watchlist = intval($_REQUEST['watchlist']);
            // Try PUT
            try {
                // Make PUT call
                $post_data = array(
                    'playlist' => array(
                        'is_watchlist' => ($watchlist == 1 ? true : false),
                    ),
                );
                $str = '/playlists/' . $feed_id . '?api_key=' . GrabPress::$api_key;
                $updated_feed = Grabpress_API::call('PUT', $str, $post_data, false);
                $response = json_decode($updated_feed);
                //Create new response object
                $response = new stdClass();
                // Add properties from GrabPress to response object
                $response->environment = Grabpress::$environment;
                // 488 -  Removed - embed_id
                $response->embed_id = Grabpress_API::get_embed_id(false);
                $response->results = array_splice(Grabpress_API::get_watchlist(), 0, 10); // First 10 only
                // Output response as JSON string
                echo json_encode($response);
            } catch (Exception $e) { // If PUT unsuccessful
                // Render feed management view
                Grabpress_Views::feed_management();
            }
            die(); // this is required to return a proper result
        }

        /**
         * Unlinks account
         * @param  array $params Associative array holding account information
         */
        static function unlink_account() {

            // If confirmed
           
          
                // Try to unlink account - PUT to backend
                try {
          
                    $unlink_user =  Grabpress_API::create_connection();
            
                    Grabpress_API::create_embeds();

                } catch (Exception $e) { // If PUT is unsuccessful
                    // Log exception message
                    Grabpress::log('API call exception: ' . $e->getMessage());
                }
                // Set action to default
                $params['action'] = 'unlink';

                return $unlink_user;
                
            

            
        }


    }

}
