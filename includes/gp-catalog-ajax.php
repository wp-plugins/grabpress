<?php
	// Get total # of providers
	$providers_total = count( $list_providers );

	// If total # of providers is equal to the # of providers in array or array
	// is empty
	if ( ( $providers_total == count( $providers ) ) || in_array( '', $providers ) ) {
		// Update provider text to all providers
		$provider_text = 'All Providers';

		// Set providers to empty string
		$providers = '';
	} else { // Not all providers
		// Update provider text to # of # selected
		$provider_text = count( $providers ) . ' of ' . $providers_total . ' selected';
	}

	// Get total # of channels
	$channels_total = count( $list_channels );

	// If total # of providers is equal to the # of providers in array or array
	// is empty
	if ( ( $channels_total == count( $channels ) ) || in_array( '', $channels ) ) {
		// Update channel text to all providers
		$channel_text = "All Video Categories";
	} else {
		// Update channel text to # of # selected
		$channel_text = count( $channels ) . ' of ' . $channels_total . ' selected';
	}

	// Try to fetch player data from API
	try {
		// Get connector ID from API
		$id = Grabpress_API::get_connector_id();

		// Get player data and ID from API
		$player_json = Grabpress_API::call( 'GET',  '/connectors/' . $id . '/?api_key=' . Grabpress::$api_key );
		$player_data = json_decode( $player_json, true );
		$player_id = isset( $player_data['connector']['ctp_embed_id'] ) ? $player_data['connector']['ctp_embed_id'] : '';
	} catch ( Exception $e ) {
		// Log exception error message
		Grabpress::log( 'API call exception: ' . $e->getMessage() );
	}

	// Get keywords if they exist
	$keywords = isset( $form['keywords'] ) ? $form['keywords'] : ''
?>
<div id="gp-catalog-container">
	<form method="post" id="form-catalog-page">
		<div class="wrap" >
			<input type="hidden" name="player_id" value="<?php echo esc_attr( $player_id ); ?>"  id="player_id" />
			<input type="hidden" name="environment" value="<?php echo esc_attr( Grabpress::$environment ); ?>"  id="environment" />
			<fieldset id="preview-feed">
				<legend>
					<?php
						// If action is get preview
						if ( 'gp_get_preview' == $form['action'] ) {
							// Legend is preview feed
							echo 'Preview Feed';
						} else if ( isset( $form['display'] ) && 'Tab' == $form['display'] ) { // If display is set to tab
							// Legend is search criteria
							echo 'Search Criteria';
						} else { // Not either of the above
							// Legend is insert video
							echo 'Insert Video';
						}
					?>
				</legend>
				<?php if ( 'gp_get_preview' != $form['action'] ) { ?>
					<div class="label-tile-one-column">
						<span class="preview-text-catalog"><b>Keywords:</b> <input name="keywords" id="keywords" type="text" value="<?php echo $keywords; ?>" maxlength="255" /></span>
						<a href="#" id="help">help</a>
					</div>
					<div class="label-tile">
						<div class="tile-left">
							<input type="hidden" name="channels_total" value="<?php echo esc_attr( $channels_total ); ?>" id="channels_total" />
							<span class="preview-text-catalog"><b>Grab Video Categories:</b></span>
						</div>
						<div class="tile-right">
							<select name="channels[]" id="channel-select-preview" class="channel-select multiselect" multiple="multiple" style="width:500px" onchange="GrabPressCatalog.doValidation( 1 ); ">
								<?php
									// Loop through each channel record
									foreach ( $list_channels as $record ) {
										// Get channel info from record category info
										$channel = $record->category;
										$name = $channel->name;
										$id = $channel->id;
										$selected = ( ( is_array( $channels ) ) && ( in_array( $name, $channels ) ) ) ? 'selected="selected"': '';
										// Output channel option HTML
										echo '<option value = "' . $name . '" ' . $selected . ' >' . $name . '</option>';
									}
								?>
							</select>
						</div>
					</div>
					<div class="label-tile">
						<div class="tile-left">
							<input type="hidden" name="providers_total" value="<?php echo esc_attr( $providers_total ); ?>" class="providers_total" id="providers_total" />
							<span class="preview-text-catalog"><b>Providers: </b></span>
						</div>
						<div class="tile-right">
							<select name="providers[]" id="provider-select-preview" class="multiselect" multiple="multiple" style="<?php esc_attr( Grabpress::outline_invalid() ); ?>" onchange="GrabPressCatalog.doValidation( 1 )" >
								<?php
									// Loop through each provider record
									foreach ( $list_providers as $record_provider ) {
										// Get provider info from record
										$provider = $record_provider->provider;
										$provider_name = $provider->name;
										$provider_id = $provider->id;
										$provider_selected = ((isset($providers)) && (is_array($providers)) && ( in_array( $provider_id, $providers ))) ? 'selected="selected"' : '';
										// Output provider option HTML
										echo '<option value = "' . $provider_id . '" ' . $provider_selected . '>' . $provider_name . '</option>';
									}
								?>
							</select>
						</div>
					</div>
					<div class="clear"></div>
					<div class="label-tile">
						<div class="tile-left">
							<span class="preview-text-catalog"><b>Date Range: </b></span>
						</div>
						<div class="tile-right">
							From<input type="text" value="<?php echo esc_attr( $created_after = isset($form['created_after']) ? $form['created_after'] : '' ); ?>" maxlength="8" id="created_after" name="created_after" class="datepicker" readonly="readonly" />
							To<input type="text" value="<?php echo esc_attr( $created_before = isset( $form['created_before'] ) ? $form['created_before'] : '' ); ?>" maxlength="8" id="created_before" name="created_before" class="datepicker" readonly="readonly" />
						</div>
					</div>
					<div class="label-tile">
						<div class="tile-left">
							<input type="button" value="Clear Dates " id="clearDates" style="float:left" >
						</div>
						<div class="tile-right">
							<a href="#" id="clear-search" onclick="return false;" >clear search</a>
							<input type="submit" value="Search " class="update-search" id="update-search" >
						</div>
					</div>
					<div class="clear"></div>
					<div class="label-tile-one-column">
						Sort by:
						<?php
							$created_checked = ( $form['sort_by'] != 'relevance' ) ? 'checked="checked"': '';
							$relevance_checked = ( $form['sort_by'] == 'relevance' ) ? 'checked="checked"': '';
						?>
						<input type="radio" class="sort_by" name="sort_by" value="created_at" <?php echo $created_checked;?> /> Date
						<input type="radio" class="sort_by" name="sort_by" value="relevance" <?php echo $relevance_checked; ?> /> Relevance
							<?php
								if ( isset( $form['display'] ) && 'Tab' == $form['display'] ) {
									if( ! empty( $list_feeds['results'] ) && Grabpress::check_permissions_for( 'gp-autoposter' ) ) {
							?>
								<input type="button" id="btn-create-feed" class="button-primary" value="<?php esc_attr( _e( 'Create Feed' ) ); ?>" />
								<?php } ?>
							<?php } ?>
						</div>
				<?php } else { ?>
					<input name="keywords" id="keywords" type="hidden" value="<?php echo $keywords; ?>" />
				<?php } ?>
				<?php if($empty == "false"){ ?>
					<div class="label-tile-one-column" >
						<input type="hidden" id="feed_count" value="<?php echo esc_attr( ( $list_feeds['total_count'] > 400 ) ? '400' : $list_feeds['total_count'] ); ?>" name="feed_count" />
						<input type="hidden" id="page" value="0" name="page" />
					</div>
				<?php } ?>
			<?php
				if( ! empty($list_feeds['results'] ) ) {
					foreach ( $list_feeds['results'] as $key => $result ) {
			?>
				<div data-id="<?php echo $result['video']['video_product_id']; ?>" class="result-tile" <?php if ( 0 == $key && $form['action'] == 'gp_get_preview' ) echo 'style="border-top:none;"'; ?> >
					<div class="tile-left">
						<img src="<?php echo $result['video']['media_assets'][0]['url']; ?>" height="72" width="123" onclick="grabModal.play('<?php echo esc_js( $result['video']['guid'] ); ?>')" alt="Video preview" />
					</div>
					<div class="tile-right">
						<h2 class="video-title">
							<?php echo html_entity_decode( $result['video']['title'], ENT_QUOTES, 'UTF-8' ); ?>
						</h2>
						<p class="video-summary">
							<?php echo html_entity_decode( $result['video']['summary'], ENT_QUOTES, 'UTF-8' ); ?>
						</p>
						<p class="video_date">
							<?php $date = new DateTime( $result['video']['created_at'] );
							$stamp = $date->format('m/d/Y') ?>
							<span><?php echo esc_html( $stamp ); ?>&nbsp;&nbsp;</span> <span><?php echo esc_html( Grabpress_API::time_format_mm_ss( $result['video']['duration'] ) );?>&nbsp;&nbsp;</span> <span>SOURCE: <?php echo esc_html( $result["video"]["provider"]["name"] ); ?></span>
							<?php if ( 'gp_get_catalog' == $form['action'] ) {
								if ( isset( $form['display'] ) && 'Tab' == $form['display'] ) {
									if( Grabpress::check_permissions_for( 'single-post' ) ) { ?>
											<input type="button" class="button-primary btn-create-feed-single" value="<?php _e( 'Create Post' ) ?>" id="btn-create-feed-single-<?php echo $result['video']['id']; ?>" />
											<input type="button" class="button-primary" onclick="grabModal.play('<?php echo $result["video"]["guid"]; ?>')" value="Watch Video" /></p>
						<?php }
								} else { ?>
									<input type="button" class="insert_into_post" value="<?php _e( 'Insert into Post' ) ?>" id="btn-create-feed-single-<?php echo $result['video']['id']; ?>" />
									<input type="button" class="update-search" onclick="grabModal.play('<?php echo $result['video']['guid']; ?>')" value="Watch Video" /></p>
					<?php }
							} else { ?>
									<input type="button" class="update-search" onclick="grabModal.play('<?php echo $result['video']['guid']; ?>')" value="Watch Video" /></p>
				<?php } ?>
					</div>
				</div>
							<?php
								}
							} else if ( 'gp_get_preview' == $form['action'] ) {
							?>
								<h1>It appears we do not have any content matching your search criteria. Please modify your settings until you see the kind of videos you want in your feed</h1>
				<?php } ?>
			</fieldset>
		</div>
	</form>
	<script type="text/javascript">
		// Create jQuery $ scope
		(function($){

			<?php
				// If action is get catalog
				if ( 'gp_get_catalog' == $form['action'] ) {
					// If display is tab
					if ( isset( $form['display'] ) && 'Tab' == $form['display'] ) {
			?>
				// Once window is fully loaded, including graphics
				$(window).load(function () {
					//Define vars
					var action = $( '#action-catalog' );

					// Run validation on form
					GrabPressCatalog.doValidation();

					// Update action to catalog search
					action.val( 'catalog-search' );
				});

				// DOM ready
				$(function() {
					// Load catalog search form in tab
					GrabPressCatalog.tabSearchForm( 'gp_get_catalog' );
				});

			<?php } else { ?>
				(function(global, $) {
					// Get and store Thickbox position globally
					global.backup_tb_position = tb_position;
					global.tb_position = GrabPressCatalog.getTBPosition;
				})(window, jQuery);

				// DOM ready
				$(function() {
					// Submit search
					GrabPressCatalog.postSearchForm();
				});
			<?php } ?>
		<?php } else if ( 'gp_get_preview' == $form['action'] ) { ?>
			// DOM ready
			$(function() {
				// Generate preview search form
				GrabPressCatalog.previewSearchForm();
			});
			<?php }  ?>
			// DOM ready
			$(function() {
				// Run validation and initialize form
				GrabPressCatalog.doValidation( 1 );
				GrabPressCatalog.initSearchForm();
			});

		})(jQuery); // End jQuery $ scaope
	</script>
</div>