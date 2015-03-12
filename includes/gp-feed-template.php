<?php
$is_edit = (isset($_GET['action']) && 'edit-feed' == $_GET['action'] );
$action = isset($_GET['action']) ? $_GET['action'] : 'create-feed'; 
$ref = isset($_GET['ref']) ? $_GET['ref'] : 'create-feed';  
$feed_id = isset( $_GET['feed_id'] ) ? intval($_GET['feed_id']) : ''; 
$manage_feeds_playlist = Grabpress_API::get_video_feeds();
?>
<div class="wrap">
    <img src="<?php echo esc_url ( plugin_dir_url ( __FILE__ ) . 'images/logo-dark.png' ); ?>" alt="Logo" />
    <h2>Video Feeds: Create custom feeds and select videos for posts</h2>
    <fieldset id="create-form" class="<?php echo esc_attr ( $is_edit ? 'edit-mode' : ''  ) ?>">
        <?php
        $rpc_url = get_bloginfo ( 'url' ) . '/xmlrpc.php';
        ?>
        <form method="post" id="form-create-feed">
            <?php
            if (  $is_edit && isset ( $_GET['feed_id'] )  ) {
                ?>
                <input type="hidden"  name="feed_id" value="<?php echo $feed_id; ?>" />
                <?php } ?>
                
                <?php
                if ( isset ( $form['referer'] ) ) {
                    $referer = ($form['referer'] == 'edit') ? 'edit' : 'create';
                } else {
                    $referer = 'create';
                }
                if ( $is_edit ) {
                    $value = ( 'modify' == $form['action'] ) ? 'modify' : 'update';
                } else {
                    $value = 'update';
                }
                ?>
                <input type="hidden"  name="referer" value="<?php echo esc_attr ( $referer ); ?>" />
                <input type="hidden"  name="action" value="<?php echo esc_attr ( $value ); ?>" />
                <input type="hidden"  name="feed_id" value="<?php echo esc_attr ( isset ( $_GET['feed_id'] ) ? $_GET['feed_id'] : ''  ); ?>" />


                <!-- Formulario create -->
                <div class="cd-tabs">
                    <nav>
                        <ul class="cd-tabs-navigation">
                            <li><a data-content="create-feed" class="<?php echo $ref == 'create-feed' ? 'selected' : '';?> create-feed" href="#0"><span>create feed</span></a></li>
                            <li><a data-content="manage-feed" class="<?php echo $ref == 'manage-feed' ? 'selected' : '';?> manage-feed" href="#0"><span>manage feeds</span></a></li>
                            <li><a data-content="select-videos" class="<?php echo $ref == 'select-videos' ? 'selected': '';?> select-videos" href="#0"><span>select videos</span></a></li>
                        </ul> <!-- cd-tabs-navigation -->
                    </nav>

                    <ul class="cd-tabs-content">
                        <li data-content="create-feed" class="<?php echo $ref == 'create-feed' ? 'selected' : '';?> ">
                           <table class="grabpress-table-shadow form-table grabpress-table">
                              <tr>
                                <td style ="padding: 0 0;" ><h4 class="title_feeds">Search Criteria</h4></td>
                            </tr>

                            <tr valign="bottom" class="well_feeds" >
                                <th scope="row">Feed Name</th> 
                                <td class="feed-name">
                                    <?php $feed_date = date ( 'YmdHis' ); ?>
                                    <input type="hidden" name="feed_date" value="<?php echo esc_attr ( $feed_date = isset ( $form['feed_date'] ) ? $form['feed_date'] : $feed_date ); ?>" id="feed_date" />
                                    <?php $name = isset ( $form['name'] ) ? urldecode ( $form['name'] ) : $feed_date; ?>
                                    <input type="text" name="name" id="name" class="ui-autocomplete-input" value="<?php echo esc_attr ( $name ); ?>" maxlength="14" />
                                    <span class="description">A unique name of 6-14 characters</span>
                                </td>
                            </tr>
                            <tr valign="bottom" class="well_feeds">
                                <th scope="row">Grab Video Categories</th>
                                <td class="feed-categories">
                                    <input type="hidden" name="channels_total" value="<?php echo $channels_total; ?>" id="channels_total" />
                                    <select  style="<?php esc_attr ( Grabpress::outline_invalid () ); ?>" name="channels[]" id="channel-select" class="channel-select multiselect" multiple="multiple" style="width:500px" >
                                        <?php
                                        if ( !array_key_exists ( 'channels', $form ) ) {
                                            $form['channels'] = array();
                                        }

                                        if ( is_array ( $form['channels'] ) ) {
                                            $channels = $form['channels'];
                                        } else {
                                            $channels = explode ( ',', rawurldecode ( $form['channels'] ) );
                                        }


                            // In the edit mode, video categories may return nothing if all options are selected
                                        $selectedAllVideoCategories = false;
                                        if ( $is_edit && isset ( $form['channels'] ) && count ( $form['channels'] ) == 1 && empty ( $form['channels'][0] ) ) {
                                            $selectedAllVideoCategories = true;
                                        }

                                        foreach ( $list_channels as $record ) {
                                            $channel = $record->category;
                                            $name = $channel->name;
                                            $id = $channel->id;

                                            if ( $is_edit && $selectedAllVideoCategories == false ) {
                                                $selected = ( in_array ( $name, $channels ) ) ? 'selected="selected"' : '';
                                            } else {
                                                $selected = 'selected="selected"';
                                            }
                                            echo '<option value = "' . $name . '" ' . $selected . '>' . $name . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <span class="description">Add or remove specific video categories from this feed</span>
                                </td>
                            </tr>
                            <tr valign="bottom" class="well_feeds">
                                <th scope="row">Keywords or</th>
                                <td>
                                    <input type="text" name="keywords_or" id="keywords_or" class="ui-autocomplete-input" value="<?php echo esc_attr ( $form['keywords_and'] ); ?>" maxlength="255" />
                                    <span class="description">Include any of these keywords</span>
                                </td>
                            </tr>
                            <tr valign="bottom" class="well_feeds">
                                <th scope="row">Keywords and</th>
                                <td>
                                    <input type="text" name="keywords_and" id="keywords_and" class="ui-autocomplete-input" value="<?php echo esc_attr ( $form['keywords_or'] ); ?>" maxlength="255" />
                                    <span class="description">Require all these keywords</span>
                                </td>
                            </tr>
                            <tr valign="bottom" class="well_feeds">
                                <th scope="row">Keywords exclude</th>
                                <td>
                                    <input type="text" name="keywords_not" id="keywords_not" value="<?php echo esc_attr ( $form['keywords_not'] ); ?>" maxlength="255" />
                                    <span class="description">Exclude these keywords</span>
                                </td>
                            </tr>
                            <tr valign="bottom" class="well_feeds">
                                <th scope="row">Keywords Exact phrase</th>
                                <td>
                                    <input type="text" name="keywords_phrase" id="keywords_phrase" class="ui-autocomplete-input" value="<?php echo esc_attr ( $form['keywords_phrase'] ); ?>" maxlength="255" />
                                    <span class="description">Require this exact phrase</span>
                                </td>
                            </tr>
                            <tr valign="bottom" class="well_feeds">
                                <th scope="row">Content Providers</th>
                                <td class="feed-providers">
                                    <input type="hidden" name="providers_total" value="<?php echo esc_attr ( $providers_total ); ?>" class="providers_total" id="providers_total" />
                                    <select name="providers[]" id="provider-select" class="multiselect" multiple="multiple" style="<?php esc_attr ( Grabpress::outline_invalid () ); ?>" onchange="GrabPressVideoFeed.doValidation()">
                                        <?php
                            // In the edit mode, content providers return nothing if all options are selected
                                        $selectedAllProvides = false;
                                        if ( $is_edit && isset ( $form['providers'] ) && count ( $form['providers'] ) == 1 && empty ( $form['providers'][0] ) ) {
                                            $selectedAllProvides = true;
                                        }

                                        foreach ( $list_providers as $record_provider ) {
                                            $provider = $record_provider->provider;
                                            $provider_name = $provider->name;
                                            $provider_id = $provider->id;
                                            if ( $is_edit && $selectedAllProvides == false ) {
                                                $provider_selected = ( in_array ( $provider_id, $form['providers'] ) ) ? 'selected="selected"' : '';
                                            } else {
                                                $provider_selected = 'selected="selected"';
                                            }
                                            echo '<option ' . $provider_selected . ' value = "' . $provider_id . '">' . $provider_name . '</option>\n';
                                        }
                                        ?>
                                    </select>
                                    <span class="description">Add or remove specific content providers from this feed</span>
                                </td>
                            </tr>
                            <tr valign="bottom" class="well_feeds create-feed-btns">
                                <td colspan="2" class="button-tip">
                                 <?php $click = ( $is_edit ) ? 'onclick="GrabPressVideoFeed.validateKeywords(\'update\');"' : 'onclick="GrabPressVideoFeed.validateKeywords();"' ?>
                                 <input type="button" class="button-primary" value="<?php esc_attr ( $is_edit ? _e ( 'Save Changes' ) : _e ( 'Create Feed' )  ); ?>" id="btn-create-feed" <?php echo $click; ?>  />
                                 <?php if (!$is_edit ) : ?>
                                 <input type="button" onclick="GrabPressVideoFeed.previewVideos()" class="button-primary" value="<?php esc_attr ( $is_edit ? _e ( 'Preview Changes' ) : _e ( 'Preview Feed' )  ); ?>" id="btn-preview-feed" />
                             <?php endif; ?>

                             <a id="reset-form" href="#">reset form</a>                    
                         </td>
                     </tr>

                     <tr valign="bottom" class="well_feeds">
                        <td class="button-tip" colspan="2">

                        </td>
                    </tr>

                </form>
            </fieldset>
        </table>       
        <table class="grabpress-metadata">         
            <tr valign="bottom">
                <th scope="row">Plugin Version</th>
                <td><?php echo esc_html ( Grabpress::$version ); ?></td>
            </tr>
            <tr valign="bottom">
                <th scope="row">API Key</th> 
                <td><?php echo esc_html ( get_option ( 'grabpress_key' ) ); ?></td>
            </tr>
        </table>
    </li>



    <li data-content="manage-feed" class="<?php echo $ref == 'manage-feed' ? 'selected' : '';?>">
        <!-- place holder for manage feeds -->              
        <div class="manage-feeds-container" >
            <h1 class="manage-feeds-page-title">Manage feeds</h1>
            <div class="manage-feeds-title-row">
                <div class="manage-feeds-title-col">Name</div>
                <div class="manage-feeds-title-col">Categories</div>
                <div class="manage-feeds-title-col">Keywords Any</div>
                <div class="manage-feeds-title-col">Keywords All</div>
                <div class="manage-feeds-title-col">Keywords Exact Phrase</div>
                <div class="manage-feeds-title-col">Keywords Exclude</div>
                <div class="manage-feeds-title-col">Content Providers</div>
                <div class="manage-feeds-title-col width-large">Last Video Added</div>
                <div class="manage-feeds-title-col">Preview</div>
                <div class="manage-feeds-title-col">Edit</div>
                <div class="manage-feeds-title-col">Delete</div>
            </div>
            <?php

            foreach($manage_feeds_playlist as $playlist_item) {

                        //Name
                $playist_name = $playlist_item->playlist->name;
                        //Playlist Id
                $playlist_id = $playlist_item->playlist->id;
                        //Decode search parameters
                $search_parameters = json_decode($playlist_item->playlist->search_parameters);

                if(isset($search_parameters->categories)) {
                    $categories_count = isset($search_parameters->categories) && is_string($search_parameters->categories) ? count(explode(",", $search_parameters->categories)) : count($search_parameters->categories);
                } else {
                    $categories_count = 0;
                }
                $keywords_and = isset($search_parameters->keywords_and) ? $search_parameters->keywords_and : '';
                $keywords_not = isset($search_parameters->keywords_not) ? $search_parameters->keywords_not: '';
                $keywords_or = isset($search_parameters->keywords_or) ? $search_parameters->keywords_or: '';
                $keywords_phrase = isset($search_parameters->keywords_phrase) ? $search_parameters->keywords_phrase : '';

                        //Providers
                $providers_count = isset($search_parameters->providers) && is_array($search_parameters->providers) ? count($search_parameters->providers) : 0;
                $updated_at = isset($playlist_item->playlist->updated_at) ? new DateTime($playlist_item->playlist->updated_at) : '';
                $updated_at = $updated_at->format('Y-m-d h:m');
                ?>
                <div class="manage-feeds-body-row">
                    <div class="manage-feeds-body-col"><?php echo (strlen($playist_name) > 9 ) ?  substr(esc_attr($playist_name), 0, 9).'..' : esc_attr($playist_name); ?></div>
                    <div class="manage-feeds-body-col"><?php echo esc_attr($categories_count) . ' selected'; ?></div>
                    <div class="manage-feeds-body-col"><?php echo esc_attr($keywords_or); ?></div>
                    <div class="manage-feeds-body-col"><?php echo esc_attr($keywords_and); ?></div>
                    <div class="manage-feeds-body-col"><?php echo esc_attr($keywords_phrase); ?></div>
                    <div class="manage-feeds-body-col"><?php echo esc_attr($keywords_not); ?></div>
                    <div class="manage-feeds-body-col"><?php echo esc_attr($providers_count) . ' selected'; ?></div>
                    <div class="manage-feeds-body-col"><?php echo esc_attr($updated_at); ?></div>
                    <div class="manage-feeds-body-col">
                        <a class="manage-feeds-link-preview btn-preview-feed" 
                        data-id="<?php echo $playlist_id; ?>" 
                        data-playlist-id="<?php echo $playlist_id; ?>" 
                        data-show-watch="true"
                        >Preview</a></div>
                        <div class="manage-feeds-body-col">
                            <a class="manage-feeds-link-edit" data-playlist-id="<?php echo $playlist_id; ?>" >Edit</a></div>
                            <div class="manage-feeds-body-col">
                                <a class="manage-feeds-link-delete" data-playlist-id="<?php echo $playlist_id; ?>" 
                                    data-playlist-name="<?php echo esc_attr($playist_name); ?>">
                                    Delete</a></div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>


                    </li>

                    <li data-content="select-videos" class="<?php echo $ref == 'select-videos' ? 'selected': '';?>">
                      <div class="select-videos-from-dropdown">
                          <p style="color:#000">Select one of your feeds from below to select new videos to post.</p>
                      </div>  
                      <select  style="<?php esc_attr ( Grabpress::outline_invalid () ); ?>" name="my-video-feeds[]" id="my-video-feeds" class="channel-select ui-widget ui-state-default ui-corner-all"  style="width:500px" >
                        <?php

                        $my_video_feeds = Grabpress_API::get_video_feeds();

                        foreach ( $my_video_feeds as $key => $my_video_feed ) {
                            $video_feed_name = $my_video_feed->playlist->name;
                            $video_feed_id = intval($my_video_feed->playlist->id);

                            $option_selected = (isset($_GET['feed_id']) && intval($_GET['feed_id']) === $video_feed_id) ? 'selected="selected"' : "";

                            echo '<option '. $option_selected .' id="'. $key .'"" value = "' . $video_feed_id . '" >' . $video_feed_name . '</option>';
                        }
                        ?>
                    </select>
                    <?php

                    if ( isset($_GET['feed_id']) && $_GET['action'] === 'view_videos' ) {

                        $video_feed_collection = Grabpress_API::get_all_videos_from_playlist( $feed_id );

                        foreach ($video_feed_collection as $key => $result) {

                            ?>
                            <div class="gp-video-list-container">
                                <div data-id="<?php echo esc_attr( $result->video->id ); ?>" class="result-tile" id="video-<?php echo esc_attr(  $result->video->id ); ?>">
                                    <div class="tile-left">
                                        <img src="<?php echo esc_attr( $result->video->media_assets[0]->url ); ?>" height="72px" width="123px" onclick="grabModal.play( '<?php echo esc_js( $result->video->guid ); ?>' )">
                                    </div>
                                    <div class="tile-right">
                                        <h2 class="video-title" id="video-title-<?php echo esc_attr( $result->video->id ); ?>">
                                            <?php echo html_entity_decode( Grabpress::getExcerpt ($result->video->title, 0 , 50), ENT_QUOTES, 'UTF-8' ); ?>
                                        </h2>
                                        <p class="video-summary">
                                            <?php echo html_entity_decode( Grabpress::getExcerpt($result->video->summary, 0, 190), ENT_QUOTES, 'UTF-8' );?>
                                        </p>
                                        <div class="video_date">
                                            <?php $date = new DateTime( $result->video->created_at );
                                            $stamp = $date->format( 'm/d/Y' ) ?>

                                            <span class="gp-video-feed-source"><?php echo esc_html( $result->video->provider->name ); ?></span>
                                            <span class="gp-video-feed-metadata"><?php echo esc_html( $stamp ); ?>&nbsp;&nbsp;<span>
                                                <span class="gp-video-feed-metadata"><?php echo esc_html( Grabpress_API::time_format_mm_ss( $result->video->duration ) ); ?></span>

                                            </div>
                                            <div class="gb-video-feeds-button">
                                                <?php if ( Grabpress::check_permissions_for('single-post') ) { ?>
                                                <input type="button" class="button-primary btn-create-feed-single"  value="<?php esc_attr( _e( 'Create Post' ) ) ?>" id="btn-create-feed-single-<?php echo esc_attr( $result->video->id ); ?>" />
                                                <?php } ?>
                                                <input type="button" class="button-primary gp-create-post-watch-video" onclick="grabModal.play('<?php echo esc_js( $result->video->guid ); ?>')" value="Watch Video" />
                                            </div>
                                        </div>
                                    </div> 
                                </div> 
                                <?php

                            }

                        } 
                        ?>
                        <?php include_once "gp-player-customization.php"; ?>

                        <script type="text/javascript">

//Change dropdown event
jQuery(document).ready(function($){
    //main URL
    var host = "<?php  echo site_url();  ?>";

    $('.select-videos').click( function() {

        var video_feed_id = $("#my-video-feeds").find('option').first().attr('value'); 
        var location = host + '/wp-admin/admin.php?page=gp-video-feeds&action=view_videos&feed_id='+ video_feed_id + '&ref=select-videos';
        window.location = location;

    });


    $('#my-video-feeds').change( function(){
        var video_feed_id = $(this).attr('value');
        var location = host + '/wp-admin/admin.php?page=gp-video-feeds&action=view_videos&feed_id='+ video_feed_id + '&ref=select-videos';
        window.location = location;



    });

    function manage_feeds_link_preview(playlist_id) {
        $('.dialog_preview_feed').dialog({
            width: 600,
            height: 400,
            autoOpen: false  
        });
        $('.dialog_preview_feed').dialog('open');
    }

    function manage_feeds_link_edit(playlist_id) {

        var location =  host + "/wp-admin/admin.php?page=gp-video-feeds&action=edit-feed&feed_id=" + playlist_id;
        window.location = location;

    }

    function manage_feeds_link_delete(playlist_id, playlist_name) {
        var confirmDelete;

        confirmDelete = confirm( 'Are you sure you want to delete the video feed "' + playlist_name + '"?' );
            // If user confirms they want to delete the feed
            if ( confirmDelete ) {
                var location = host + "/wp-admin/admin.php?page=gp-video-feeds&action=delete&feed_id=" + playlist_id + "&ref=manage-feed";
                window.location = location;
            } else { // User does not confirm
                // Prevent default browser behavior
                return false;
            }
        }
        
        $('.manage-feeds-link-edit').click( function() {
            var playlist_id;
            playlist_id = $(this).data('playlist-id');
            manage_feeds_link_edit(playlist_id);
        });

        $('.manage-feeds-link-delete').click( function() {
            var playlist_id;
            playlist_id = $(this).data('playlist-id');
            playlist_name = $(this).data('playlist-name');
            manage_feeds_link_delete(playlist_id, playlist_name);
        });

    });
</script>

</li>



</ul> <!-- cd-tabs-content -->
</div> <!-- cd-tabs -->

</div> 
