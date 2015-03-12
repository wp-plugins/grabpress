<!--[if IE]>
 <style type="text/css">
	 .reveal-modal-bg {
			 background: transparent;
			 filter: progid:DXImageTransform.Microsoft.gradient(startColorstr=#70000000,endColorstr=#70000000);
			 zoom: 1;
		}
	</style>
<![endif]-->

<style>
	.update-nag { 
		position: absolute;
		z-index: 10000;
		opacity: 0.8;
	}
</style>

<?php date_default_timezone_set( 'America/New_York' ); ?>
<form method="post" id="form-dashboard">
	<input type="hidden" name="environment" value="<?php echo esc_attr( Grabpress::$environment ); ?>" id ="environment" />
	<input type="hidden" name="embed_id" value="<?php echo esc_attr( $embed_id ); ?>" id ="embed_id" />
	<div class="wrap" >
		<div id="t">
			<div id="b">
				<div class="container-fluid" style="max-width: 1432px;">
					<!-- WATCHLIST -->
					<div class="row-fluid watchlist-wrap">
						<div class="span4 watchlist">
							<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'images/logo-light.png' ); ?>" alt="Logo" />
							<div class="tabbable">
								<ul class="nav nav-tabs">
									<li class="active">
										<a href="#watchlist-tab1" data-toggle="tab">Watchlist</a>
									</li>
								</ul>
								<div class="tab-content">
									<div class="tab-pane active" id="watchlist-tab1">
										<div class="accordion" id="accordion2">
										<?php $i = 1; if ( empty( $watchlist ) ) { ?>
											<div class="accordion-group">
												<div class="accordion-heading">
													<div class="accordion-left"></div>
													<div class="accordion-center"><span class="accordion-toggle">&nbsp;</span></div>
													<div class="accordion-right"></div>
												</div>
												<div id="collapse<?php echo $i;?>" class="accordion-body" style="height:95px;">
													<div class="accordion-inner">
														<span class="accordion-warning">Add a feed to your watch list in the Feed Activity panel</span>
													</div>
												</div>
											</div>
										<?php } else { foreach ( $watchlist as $item ) { ?>
											<div class="accordion-group">
												<div class="accordion-heading">
													<div class="accordion-left"></div>
													<div class="accordion-center">
														<a class="accordion-toggle feed-title" data-guid="v<?php echo $item->video->guid;?>" data-toggle="collapse" data-parent="#accordion2" href="#collapse<?php echo $i;?>"><?php echo $item->video->title;?></a>
													</div>
													<div class="accordion-right"></div>
												</div>
												<div id="collapse<?php echo $i;?>" class="accordion-body collapse in" style="<?php echo esc_attr( 1 == $i ? '': 'display:none;' ); ?>">
													<div class="accordion-inner"></div>
												</div>
											</div>
										<?php $i++; } } ?>
									</div>
								</div>
							</div>
						</div>
					</div>
					<!--End of left accordion panels-->
					<!--RIGHT PANEL -->			
					<div class="span8 right-pane" id="right-panel">
						<div class="row-fluid">
							<div class="span4">
								<div class="row-fluid">
									<!-- MESSAGES -->
									<div class="span12 messages">
										<div class="tabbable panel" id="messages-tabs">
											<ul class="nav nav-tabs">
												<li><a style="cursor: pointer;" class="button-tab-messages">Messages</a></li>
												<?php if ( ! empty($user_messages) ) { ?>
													<li><a style="cursor: pointer;" class="button-tab-Alerts">Alerts</a></li>
												<?php } ?>
											</ul>
											<div class="tab-pane active nano" id="messages-tab1">
												<div class="content">
													<?php 
													     if(isset($broadcasts) and is_array($broadcasts)) {
													     	foreach ( $broadcasts as $msg ) { ?>
														<p>
															<?php echo html_entity_decode( $msg->message->body, ENT_QUOTES, 'UTF-8' ); ?>
														</p>
													<?php } }?>
												</div>
											</div>
											<?php if ( ! empty($user_messages) ) { ?>
												<div class="tab-pane active nano" id="messages-tab2">
													<div class="content">
														<?php if ( ! empty( $user_messages ) ) {
															foreach( $user_messages as $user_message ) { ?>
															<p id="<?php echo esc_attr( $user_message['id'] ); ?>">
																<?php echo html_entity_decode( $user_message['body'], ENT_QUOTES, 'UTF-8' ); ?>
															</p>
														<?php } } ?>
													</div>
												</div>
											<?php } ?>
										</div>
									</div>
								</div>
								<div class="row-fluid">
									<!-- FORTUNE COOKIE -->
									<div class="span12 welcome">
										<div class="panel">
											<div class="tab-content">
												<div class="tab-pane active noscroll" id="messages-tab1">
													<div class="content">
													<?php
														$num_feeds = count( $feeds );
														if ( 'account-unlinked' == $publisher_status && Grabpress::check_permissions_for( 'grab-press-general-setting' ) ) {
																$create = isset( $_REQUEST['page'], $_REQUEST['action'] ) && 'account' == $_REQUEST['page'] && 'create' == $_REQUEST['action'] ? 'Create' : '<a href="admin.php?page=grab-press-general-setting&action=create">Create</a>';
																$link =  isset( $_REQUEST['page'], $_REQUEST[ 'action']) && 'account' == $_REQUEST['page'] && 'default' == $_REQUEST['action'] ? 'link an existing' : '<a href="admin.php?page=grab-press-general-setting&action=default">link an existing</a>';
															echo 'Want to earn money?' . $create . ' or ' . $link . ' Grab Publisher account.';
														} else if ( 0 == $num_feeds && Grabpress::check_permissions_for( 'gp-video-feeds' ) ) {
																$admin = get_admin_url();
																$admin_page = $admin . 'admin.php?page=gp-video-feeds';
																$here = '<a href="' . $admin_page . '">here</a>';
																echo 'Thank you for activating GrabPress. Try creating your first Video Feed ' . $here . ".";
														} else {
															$p = count( $fortune_cookies );
															$p--;
															$r = rand( 0, $p );
															echo html_entity_decode( $fortune_cookies[ $r ]->message->body );
														} ?>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<!-- PLAYLISTS -->
							<div class="feeds" id="playlists">
								<?php
									$num_feeds = is_array( $feeds ) ? count( $feeds ) : 0;
									$admin = get_admin_url();
									$admin_account_page = $admin . 'options-general.php?page=grab-press-general-setting';
									if ( Grabpress::check_permissions_for( 'gp-account' ) ) {
								?>
									<div id="btn-account-settings">
										<div class="accordion-left">&nbsp;</div>
										<div class="accordion-center">
											<a href="#" class="big-link" data-reveal-id="AccoutDetails_Modal" data-animation="fade">Account Settings</a>
										</div>
										<div class="accordion-right">&nbsp;</div>
									</div>
								<?php } ?>
								<div id="publisher-account-status" value="Publisher Account Status" class="<?php echo esc_attr( $publisher_status ); ?>"></div>
								<div class="panel nano">
									<div class="content">
									<h3>Feed Activity (Latest Video Feeds)</h3>
									<table class="table table-hover">
										<thead>
											<tr>
												<th>Feed Name</th>
												<th>Last Updated</th>
												<th>New Videos</th>
												<th>Watchlist</th>
												<th>&nbsp;</th>
											</tr>
										</thead>
										<tbody>
										<?php
										if ($num_feeds >= 1 ) {
											for ( $n = 0; $n < $num_feeds; $n++ ) {
												$feed = $feeds[ $n ]->playlist;
												$feedId = $feed->id;
												$rowColor = ( $n % 2 ) == 1 ? 'odd' : 'even';
												$updated_at = new DateTime($feed->updated_at);
										?>
											<tr id="tr-<?php echo esc_attr( $feedId ); ?>" class="<?php echo esc_attr( $rowColor ); ?>">
												<td><?php echo urldecode( $feed->name ); ?></td>
												<td><?php echo esc_html( $updated_at->format('m/d/Y') ); ?></td>
												<?php
													$feed_health_value = $feed->count_new_videos;
												?>
												<td class="<?php //echo esc_attr( $feed_health ); ?>"><?php echo $feed_health_value; ?></td>
												<td class="watch">
													<?php
														if ( 1 == $feed->is_watchlist ) {
															echo '<input type="button" value="0" class="watchlist-check watch-on" id="watchlist-check-' . $feedId . '" >';
														} else {
															echo '<input type="button" value="1" class="watchlist-check watch-off" id="watchlist-check-'. $feedId . '" >';
														}
													?>
												</td>
												<td>
													<a href="admin.php?page=gp-video-feeds&action=edit-feed&feed_id=<?php echo esc_attr( $feedId ); ?>" id="btn-update-<?php echo esc_attr( $feedId ); ?>" class="big-link">
														Edit
													</a>
												<i class="icon-pencil"></i></td>
											</tr>
										<?php } }?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
					<div clas="row-fluid">
						<div class="span12 faq">
							<!-- RESOURCES -->
							<div class="tabbable panel" id="resources">
								<ul class="nav nav-tabs">
									<li class="active"><a href="#faq-tab1" data-toggle="tab">Resources</a></li>
								</ul>
								<div class="tab-content">
									<div class="tab-pane active" id="faq-tab1">
										<p> Read more about GrabMedia and our Grabpress and Video Feed technology:</p>
										<?php foreach ( $resources as $msg ) { ?>
											<p><?php echo html_entity_decode( $msg->message->body, ENT_QUOTES, 'UTF-8' ); ?></p>
										<?php }?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</form>












<div id="AccoutDetails_Modal" class="reveal-modal">
	<p>Account Details</p>
	<div class="infoBox">
		<?php
			$linked = isset( $user->user->email );
			if( $linked ) {  ?>
		<p>Linked Account Email Address: <br />
				 <?php  echo esc_html( $user->user->email ) ;
			} else {
		?>
			<p>This installation is not linked to a Publisher account.<br />
			Linking GrabPress to your account allows us to keep track of the video ads displayed with your Grab content and make sure you get paid.</p>
		<?php } ?>
		</p>
		<p>API Key: <br /><?php echo esc_html( get_option( 'grabpress_key' ) ); ?><input type="hidden" value="<?php echo esc_attr( get_option( 'grabpress_key' ) ); ?>" id="fe_text" /></p>
	</div>
	<?php if ( Grabpress::check_permissions_for( 'gp-account' ) ) { ?>
		<div class="btn-modal-box">
			<div class="accordion-left">&nbsp;</div>
			<div class="accordion-center"><a href="<?php echo esc_url( $admin_account_page ); ?>" >Account Settings</a></div>
			<div class="accordion-right">&nbsp;</div>
		</div>
	<?php } ?>
	<div class="btn-modal-box" id="d_clip_button" data-clipboard-target="fe_text" data-clipboard-text="Default clipboard text from attribute">
			<div class="accordion-left">&nbsp;</div>
			<div class="accordion-center"><a href="#">Copy API Key</a></div>
			<div class="accordion-right">&nbsp;</div>
	</div>
	<div class="btn-modal-box">
			<div class="accordion-left">&nbsp;</div>
			<div class="accordion-center"><a class="close-reveal-modal" href="#">Back to Dashboard</a></div>
			<div class="accordion-right">&nbsp;</div>
	</div>
</div>
<div class="dashgear grabgear">
     <?php echo '<img src="' . plugin_dir_url( __FILE__ ) . 'images/grabgear.gif" alt="Grab">'; ?>
</div>












<script type="text/javascript">

	// Create jQuery $ scope
	(function($){

		// Define vars
		var clip, location,
				clipBtn = $( '#d_clip_button' )
		;

		// Setup location object
		location = {
			moviePath: '<?php echo Grabpress::grabpress_plugin_url(); ?>/js/ZeroClipboard.swf'
		};

		// Create new ZeroClipboard instance
		clip = new ZeroClipboard( clipBtn, location );

		// On complete
		clip.on( 'complete', function ( client, args ) {
			// Popup alert message
			debugstr( 'Copied text to clipboard: ' + args.text );
		});

		/**
		 * Creates an alert message with provided text
		 * @param  {String} text Message to display in the alert popup
		 */
		function debugstr( text ) {
			alert( text );
		}

		$('.button-tab-messages').click(function() {
			$('#messages-tab1').show();
			$('#messages-tab2').hide();
		});

		$('.button-tab-Alerts').click(function() {
			$('#messages-tab2').show();
			$('#messages-tab1').hide();
		});

		$( document ).ready( function() {
			$('#messages-tab1').show();
			$('#messages-tab2').hide();
		} )

		$( window ).resize(function() {
			var viewportWidth = $(window).width();
			if(viewportWidth<=1200) {
				//$( '.tabbable' ).hide();
  				$( "#playlists" ).addClass('span8');
  				
  			} else  {
  				$( "#playlists" ).removeClass('span8');
  			}
  			if(viewportWidth<=1175) {
  				$( ".watchlist" ).css('display','none');
  				$( "#right-panel").css('position','relative');
  				$( "#right-panel").css('margin-top','70px');
  			} else {
  				$( ".watchlist" ).css('display','');
  				$( "#right-panel").css('position','');
  				$( "#right-panel").css('margin-top','');	
  			}
		});


	})(jQuery); // End jQuery $ scope

</script>