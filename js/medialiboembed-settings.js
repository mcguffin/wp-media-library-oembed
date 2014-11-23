console.log(1232131);
(function($){
	$(document).on('change','#oembed-restrict-providers',function(){
		if ($(this).is(':checked')) {
			$('.oembed-provider-select').slideDown(300);
		} else {
			$('.oembed-provider-select').slideUp(300);
		}
	});
})(jQuery);