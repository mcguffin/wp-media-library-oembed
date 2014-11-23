(function($,window){
	var media       = wp.media, mediaFrame,
		selectBindHandlers = media.view.MediaFrame.Select.prototype.bindHandlers,
		postBindHandlers = media.view.MediaFrame.Post.prototype.bindHandlers,
		l10n, oembedContent;


	l10n = media.view.l10n = typeof _wpMediaViewsL10n === 'undefined' ? {} : _wpMediaViewsL10n;
	l10n = _.extend(l10n,medialiboembed_l10n);
	// override media input methods.
	//AttachmentCompat
	media.view.MediaFrame.Select.prototype.browseRouter = function( view ) {
		var set_data = {};
		set_data.upload = {
			text:     l10n.uploadFilesTitle,
			priority: 20
		};
		set_data.browse = {
			text:     l10n.mediaLibraryTitle,
			priority: 30
		};
		set_data.oembed = {
			text:     l10n.external,
			priority: 45
		};
		view.set(set_data);
	};
	
	
	media.view.MediaFrame.Select.prototype.bindHandlers = function() {
		// parent handlers
		selectBindHandlers.apply( this, arguments );

		// add recorder create handler.
		this.on( 'content:create:oembed', this.contentCreateOembed, this );
		this.on( 'content:render', this.contentRender, this );
		this.on( 'content:render:oembed', this.contentRenderOembed, this );
		this.on( 'content:uploaded:oembed', this.oembedUploaded, this );
		
		mediaFrame = this;
	};
	media.view.MediaFrame.Select.prototype.contentCreateOembed = function( content ){
		var state = this.state();
		this.$el.removeClass('hide-toolbar');
		oembedContent = content.view = new media.view.oembed({controller:this});
	}
	media.view.MediaFrame.Select.prototype.contentRender = function( content ) {
	}
	media.view.MediaFrame.Select.prototype.contentRenderOembed = function( content ){
		this._current = oembedContent;
	}
	media.view.MediaFrame.Select.prototype.oembedUploaded = function( result ){
		// show element library
		if ( result.success ) {
			mediaFrame.content.mode('browse');
		}
	}

	media.view.MediaFrame.Post.prototype.oembedUploaded = function( result ){
	}
	
	media.view.oembed = media.View.extend({
		tagName:   'div',
		className: 'oembed',
		controller:null,
		_content : null,
		_submit : null,
//		_pasteboard : null,
		
		initialize: function() {
			_.defaults( this.options, {
			});
			var self = this;
			// build UI
			this._content = $('<div class="oembed-ui"><label class="embed-url"><span>'+l10n.media_url+'</span><input class="alignment" data-setting="oembed-url" type="text" /></label></div>')
				.appendTo(this.$el)
				.on('click','.insert-oembed-media',function(event) {
					self.submit();
				}).on('focus keyup','input[data-setting="oembed-url"]',function(){
					if ( ! self.oembed_url() ) 
						self._submit.attr('disabled','disabled');
					else 
						self._submit.removeAttr('disabled');
					self.error(false);
				});
			if ( !! l10n.provider_restriction_note ) {
				$('<p class="description">'+l10n.provider_restriction_note+'</p>').appendTo(this._content);
			}
			this._errors = $('<div class="upload-errors oembed-errors"></div>')
				.appendTo(this._content);
			this._submit = $('<button disabled class="insert-oembed-media button-primary"><span class="dashicons dashicons-admin-media"></span>'+l10n.add_media+'</button>')
				.appendTo(this._content);
		},
		error : function( message ) {
			if ( !!message )
				this._errors.append('<div class="oembed-error upload-error">'+message+'</div>').css('display','none').fadeIn(600);
			else 
				$('.oembed-errors *')
					.fadeOut(300,function(){ 
						$(this).each( function() { 
							$(this).remove(); 
						});
					});
		},
		oembed_url : function() {
			return this._content.find('[data-setting="oembed-url"]').val();
		},
		submit : function(){
			var self = this;
			var file = {};
			var attributes = {
				file:      file,
				uploading: true,
				date:      new Date(),
				filename:  'oembed-content',
				menuOrder: 0,
				uploadedTo: wp.media.model.settings.post.id,
				type : 'oembed',
				subtype : '',
				loaded : 0,
				size : 100,
				percent : 0
			};
			file.attachment = wp.media.model.Attachment.create( attributes );
			wp.Uploader.queue.add(file.attachment);

			var ajax_request = {
				url : l10n.settings.ajaxurl,
				type : 'post',
				dataType : 'json',
				data : {
					action : 'oembed_media',
					post_id : wp.media.model.settings.post.id,//$('#post_ID').val(),
					oembed_url : self.oembed_url(),
					_ajax_nonce : l10n.settings.oembed_ajax_nonce
				},
				success : function( result , httpStatus , xhr ) {
					mediaFrame.uploader.uploader.uploader.trigger('FileUploaded', file, {
						response : xhr.responseText,
						status : httpStatus
					});
					if ( result.success ) {
						mediaFrame.trigger('content:uploaded:oembed',result);
					} else {
						// handle error
						self.error( result.data.message );
					}
				},
				error : function(result,b,c){
					self.error( l10n.generic_error_message );
				}
			};
			$.ajax( ajax_request );
		
		}
	});
})(jQuery,window);
