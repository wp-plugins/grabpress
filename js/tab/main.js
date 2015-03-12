jQuery(document).ready(function($){


	var tabItems = $('.cd-tabs-navigation a'),
	tabContentWrapper = $('.cd-tabs-content');

	tabItems.on('click', function(event){
		event.preventDefault();
		var selectedItem = $(this);
		if( !selectedItem.hasClass('selected') ) {
			var selectedTab = selectedItem.data('content'),
			selectedContent = tabContentWrapper.find('li[data-content="'+selectedTab+'"]'),
			slectedContentHeight = selectedContent.innerHeight();
			
			tabItems.removeClass('selected');
			selectedItem.addClass('selected');
			selectedContent.addClass('selected').siblings('li').removeClass('selected');
			//animate tabContentWrapper height when content changes 
			tabContentWrapper.animate({
				'height': slectedContentHeight
			}, 200);
		}
	});

	//hide the .cd-tabs::after element when tabbed navigation has scrolled to the end (mobile version)
	checkScrolling($('.cd-tabs nav'));
	$(window).on('resize', function(){
		checkScrolling($('.cd-tabs nav'));
		tabContentWrapper.css('height', 'auto');
	});
	$('.cd-tabs nav').on('scroll', function(){ 
		checkScrolling($(this));
	});

	function checkScrolling(tabs){
		var totalTabWidth = parseInt(tabs.children('.cd-tabs-navigation').width()),
		tabsViewport = parseInt(tabs.width());
		if( tabs.scrollLeft() >= totalTabWidth - tabsViewport) {
			tabs.parent('.cd-tabs').addClass('is-ended');
		} else {
			tabs.parent('.cd-tabs').removeClass('is-ended');
		}
	}

	var closePreviews = $( '.close-preview' ),
	env = 'production',
	modalID = '1720202',
	self = this;

	if ( ! window.grabModal ) {
		try {
					// Create it with defaults
					window.grabModal = new com.grabnetworks.Modal({
						id: modalID,
						tgt: env,
						width: 800,
						height: 450
					});
				} catch( err ) {
					// Do nothing
				}
			}


		GrabPressCatalog.publishVideo();

//When the user clicks on manage feed, then we clean up the right inputs
$('.manage-feed').click( function () {
	$("#form-create-feed :input")
	.not(':input[type=button], :input[type=hidden], :input[type=submit], :input[multiple=multiple]')
	.val('');
});

$('.manage-feeds-body-row').hover( function() {

	$(this).find('a.manage-feeds-link-preview').addClass('manage-feeds-link-preview-hover');
	$(this).find('a.manage-feeds-link-edit').addClass('manage-feeds-link-edit-hover');
	$(this).find('a.manage-feeds-link-delete').addClass('manage-feeds-link-delete-hover');

},
function() {

	$(this).find('a.manage-feeds-link-preview').removeClass('manage-feeds-link-preview-hover');
	$(this).find('a.manage-feeds-link-edit').removeClass('manage-feeds-link-edit-hover');
	$(this).find('a.manage-feeds-link-delete').removeClass('manage-feeds-link-delete-hover');

});




});