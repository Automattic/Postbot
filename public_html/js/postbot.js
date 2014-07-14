var uploader;
var needs_saving = false;

var Postbot_Error = function() {
	var errors = [];
	var api = {};

	api.add = function( error_message ) {
		errors[errors.length] = error_message;
		show();
		return this;
	};

	api.reset = function() {
		errors = [];
		$( '#message' ).hide();
		return this;
	};

	var show = function() {
		var message = '';

		if ( errors.length == 1 )
			message = sanitize_string( errors[0] );
		else {
			message = '<ul>';

			for ( var pos = 0; pos < errors.length; pos++ ) {
				message += '<li>' + sanitize_string( errors[pos] ) + '</li>';
			}

			message += '</ul>';
		}

		$( '#message' ).html( message ).show();
	};

	return api;
};

var postbot_error = Postbot_Error();

function set_tab_order() {
	$( '.schedule-item' ).each( function( pos, item ) {
		$( item ).find( 'input, textarea' ).each( function( pos2, input ) {
			$( input ).attr( 'tabindex', ( pos * 3 ) + pos2 + 1 );
		} );
	} );
}

function update_body_class() {
	var count = $( '.schedule-item' ).length;
	var klass = 'media-items-multiple';

	if ( count === 0 )
		klass = 'media-items-none';
	else if ( count === 1 )
		klass = 'media-items-single';

	$( 'ul.schedule-list.sortable' ).sortable( count > 1 ? 'enable' : 'disable' );
	$( 'body' ).removeClass( 'media-items-multiple media-items-none media-items-single' ).addClass( klass );
}

function update_uploader_button() {
	if ( $( '#upload-help' ).is( ':visible' ) )
		uploader.setOption( 'browse_button', 'upload-help' );
	else
		uploader.setOption( 'browse_button', 'pick-files' );
}

function update_schedule_times( date_string, time_string, schedule, button_text ) {
	$( '#schedule-pick-date' ).text( date_string );
	$( '#schedule-pick-time' ).text( time_string );
	$( '#schedule-submit-button' ).val( button_text );

	for ( var loop = 0; loop < schedule.length; loop++ ) {
		$( '.schedule-item:nth-child(' + ( loop + 1 ) + ') .schedule-time' ).html( sanitize_string( schedule[loop].day ) + '<br/>' + sanitize_string( schedule[loop].date ) );
	}
}

var decode_entities = ( function() {
	var element = document.createElement( 'div' );

	function decodeHTMLEntities( str ) {
		if ( str && typeof str === 'string' ) {
			str = str.replace( /<script[^>]*>([\S\s]*?)<\/script>/gmi, '' );
			str = str.replace( /<\/?\w(?:[^"'>]|"[^"]*"|'[^']*')*>/gmi, '' );
			element.innerHTML = str;
			str = element.textContent;
			element.textContent = '';
		}

		return str;
	}

	return decodeHTMLEntities;
} )();

function sanitize_string( str ) {
	return $( '<div/>' ).text( str ).html();
}

function slow_publish_schedule( pos ) {
	$( 'form' ).ajaxSubmit( {
		data: {
			media_id: $( 'li.schedule-item:nth-child(' + pos + ')' ).data( 'media-id' ),
			pos: ( pos - 1 )
		},
		dataType: 'json',
		success: function( result ) {
			if ( result.redirect ) {
				window.location = result.redirect;
				return;
			}
			else if ( result.error ) {
				$( '#schedule-progress' ).modal( 'hide' );

				postbot_error.add( result.error );

				disable_form_elements( false );
				return;
			}

			$.each( result, function( pos, item ) {
				show_published_item( item );
			} );

			if ( $( 'li.schedule-item:nth-child(' + ( pos + 1 ) + ')' ).length > 0 )
				slow_publish_schedule( pos + 1 );
			else {
				$( '#uploader' ).hide();

				setTimeout( function() {
					window.location = postbot.pending_url;
				}, 2000 );
			}
		}
	} );
}

function show_published_item( item ) {
	$( '#schedule-progress ul' ).append( '<li><a target="_blank" href="' + item.url + '">' + sanitize_string( decode_entities( item.title ) ) + '</a></li>' );
}

function disable_form_elements( status ) {
	$( 'input[type=submit]' ).prop( 'disabled', status );
	$( '#pick-files' ).prop( 'disabled', status );
}

function schedule_changed() {
	var data = {
		action:  'postbot_get_dates',
		total:   $( '.schedule-item' ).length,
		nonce:   postbot.nonce
	};

	if ( $( 'input[name=schedule_date]' ).length > 0 ) {
		data.date           = $( 'input[name=schedule_date]' ).val();
		data.hour           = parseInt( $( 'input[name=schedule_time_hour]').val(), 10 );
		data.minute         = parseInt( $( 'input[name=schedule_time_minute]').val(), 10 );
		data.interval       = parseInt( $( 'select[name=schedule_interval]' ).val(), 10 );
		data.ignore_weekend = $( 'input[name=ignore_weekend]' ).is( ':checked' ) ? 1 : 0;
	}

	$.post( postbot.ajax_url, data, function( response ) {
		if ( response.error ) {
			postbot_error.add( response.error );
			disable_form_elements( false );
		}
		else {
			update_schedule_times( response.text, response.time, response.dates, decode_entities( response.button ) );

			postbot.body_text_1   = response.body_text_1;
			postbot.body_text_2   = response.body_text_2;
			postbot.schedule_text = response.schedule_text;
			postbot.scheduling_text = response.scheduling_text;
		}
	}, 'json' );
}

function show_pending_upload( upload, file ) {
	var preloader = new mOxie.Image();
	var item_id = '#media-item-' + sanitize_string( upload.id );

	$( 'ul.schedule-list' ).append( upload.html.replace( /"schedule-item"/, '"schedule-item pending-upload"') );

	preloader.onload = function() {
		var width = Math.min( postbot.thumbnail_size * 2, preloader.width );
		var retina_width = Math.min( width / 2, preloader.width );

		preloader.downsize( width, width, true );
		$( item_id ).find( '.schedule-thumb img' ).prop( 'src', preloader.getAsDataURL() ).attr( 'width', retina_width );
	};

	preloader.load( file.getSource() );
}

function remap_uploaded_item( old_id, new_id, nonce, proper_thumb ) {
	var old = $( '#media-item-' + old_id );

	old.removeClass( 'pending-upload' );
	old.data( 'media-id', new_id );
	old.attr( 'id', 'media-item-' + new_id );
	old.find( '.schedule-thumb img' ).removeClass( 'pending-upload' );
	old.find( 'a.schedule-remove' ).data( 'nonce', nonce );

	old.find( 'label' ).each( function( pos, item ) {
		var existing = $( item ).attr( 'for' );
		var replace = existing.replace( old_id, new_id );
		var input = $( '#' + existing );

		$( item ).attr( 'for', replace );
		input.attr( 'id', replace ).attr( 'name', input.attr( 'name' ).replace( old_id, new_id ) );
	} );

	// Replace thumbnail
	var thumb = new Image();
	thumb.onload = function() {
		old.find( '.schedule-thumb img' ).attr( 'src', proper_thumb );
	};

	thumb.src = proper_thumb;
}

function setup_uploader() {
	uploader = new plupload.Uploader({
		runtimes : 'html5,html4',

		browse_button : 'upload-help',

		url : 'uploader.php',
		drop_element: document.getElementById( 'upload-help-drop' ),

		filters : {
			max_file_size : postbot.max_upload + 'mb',
			mime_types: [
				{
					title : postbot.upload_prompt,
					extensions : 'jpg,gif,png'
				},
			]
		},

		multipart_params: {
			nonce: postbot.nonce
		},

		file_data_name: 'media'
	});

	uploader.bind( 'Init', function() {
		if ( uploader.features.dragdrop ) {
			$( 'body' ).on( 'dragover', function( e ) {
				e.preventDefault();

				$( 'body' ).addClass( 'dragging' );
			} );

			$( '#upload-help-drop' ).on( 'drop', function( e ) {
				e.preventDefault();

				$( 'body' ).removeClass( 'dragging' );
			} );

			$( '#upload-help-drop' ).on( 'dragleave', function( e ) {
				e.preventDefault();

				if ( window.event.pageX === 0 || window.event.pageY === 0 ) {
					$( 'body' ).removeClass( 'dragging' );
					return false;
				}
			} );
		}

		update_body_class();
	} );

	uploader.init();

	uploader.bind( 'UploadProgress', function( up, file ) {
		var progress = $( '#media-item-' + file.id + ' .progress' );

		progress.find( '.progress-bar' ).css( 'width', file.percent + '%' );
		progress.find( 'span:first' ).text( file.percent + '%' );
		progress.find( 'span:last' ).text( plupload.formatSize( file.size ) );
	} );

	uploader.bind( 'FilesAdded', function( up, files ) {
		var data = {
			action: 'postbot_uploading',
			nonce:  postbot.nonce,
			files:  []
		};

		if ( $( 'input[name=schedule_date]' ).length > 0 ) {
			data.date           = $( 'input[name=schedule_date]' ).val();
			data.hour           = parseInt( $( 'input[name=schedule_time_hour]').val(), 10 );
			data.minute         = parseInt( $( 'input[name=schedule_time_minute]').val(), 10 );
			data.interval       = parseInt( $( 'select[name=schedule_interval]' ).val(), 10 );
			data.ignore_weekend = $( 'input[name=ignore_weekend]' ).is( ':checked' ) ? 1 : 0;
		}

		disable_form_elements( true );
		postbot_error.reset();

		plupload.each( files, function( file ) {
			data.files[data.files.length] = {
				size:     file.size,
				filename: file.name,
				id:       file.id,
			};
		} );

		$( 'body' ).addClass( 'uploading' );

		$.post( postbot.ajax_url, data, function( response ) {
			if ( response.error ) {
				postbot_error.add( response.error );
				disable_form_elements( false );
				return;
			}

			$( '#schedule-submit-button' ).val( decode_entities( response.button ) );

			for ( var pos = 0; pos < response.files.length; pos++ ) {
				show_pending_upload( response.files[pos], files[pos] );
			}

			up.start();
			update_body_class();
			set_tab_order();
			uploader.refresh();
		}, 'json' );
	} );

	uploader.bind( 'UploadComplete', function( uploader, files ) {
		// Remove any files that failed
		$( '.schedule-list .pending-upload' ).remove();

		$( 'body' ).removeClass( 'uploading' );

		update_uploader_button();
		schedule_changed();
		disable_form_elements( false );
	} );

	uploader.bind( 'FileUploaded', function( uploader, file, response ) {
		$( '#media-item-' + file.id ).find( '.progress' ).remove();

		if ( response.status == 200 ) {
			response = $.parseJSON( response.response );

			if ( response.error ) {
				$( '#media-item-' + file.id ).remove();
				postbot_error.add( file.name + ' - ' + response.error );

				if ( !response.continue_uploading ) {
					uploader.stop();
					uploader.trigger( 'UploadComplete', uploader, uploader.files );
				}
			}
			else
				remap_uploaded_item( file.id, response.id, response.nonce, response.img );

			update_body_class();
		}
	} );

	uploader.bind( 'Error', function( uploader, error ) {
		var error_message = error.message;

		if ( error.status )
			error_message += ' ' + error.status;

		// Remove upload & show error
		postbot_error.add( error_message );
		uploader.stop();
	} );
}

function auto_save() {
	if ( needs_saving ) {
		$( 'form' ).ajaxSubmit( {
			data: {
				action: 'postbot_autosave',
				nonce: postbot.nonce
			},
			dataType: 'json',
			success: function( result ) {
				postbot_error.reset();

				if ( result.error )
					postbot_error.add( result.error );
			}
		} );

		needs_saving = false;
	}
}

jQuery( document ).ready( function($) {
	if ( $( '#upload-help').length > 0 )
		setup_uploader();
	else {
		$( document ).bind( 'drop dragover', function( e ) {
			e.preventDefault();
		} );
	}

	$( 'a.swap-blog' ).on( 'click', function( e ) {
		$( 'input[name=schedule_on_blog]' ).val( $( this ).data( 'blog-id' ) );
		$( '#dropdown-toggle span' ).text( $( this ).data( 'blog-name' ) );
		$( '#dropdown-toggle img' ).attr( 'src', $( this ).data( 'blavatar' ) );

		$( 'li.dropdown-blog' ).removeClass( 'selected' );
		$( this ).parent( 'li' ).addClass( 'selected' );

		$( this ).closest( 'li.dropdown' ).removeClass( 'open' );

		var data = {
			action: 'postbot_set_blog',
			nonce: $( this ).data( 'nonce' ),
			blog_id: $( this ).data( 'blog-id' )
		};

		$.post( postbot.ajax_url, data, function( response ) {
			if ( response.error ) {
				disable_form_elements( false );
				return postbot_error.add( response.error );
			}

			$( 'input[type=submit]' ).val( decode_entities( response.button ) );
			schedule_changed();
		}, 'json' );

		e.preventDefault();
	} );

	$( 'input[name=schedule_date]' ).datepicker( {
		dateFormat: 'yy-mm-dd',
		minDate: 0,
		maxDate: 90,
		onClose: function() {
			schedule_changed();
		}
	} );

	$( 'select.schedule-monitor' ).on( 'change', schedule_changed );

	$( '#schedule-pick-date' ).on( 'click', function( e ) {
		$( 'input[name=schedule_date]' ).datepicker( 'show' );
		e.preventDefault();
	} );

	$( 'ul.schedule-list.sortable' ).sortable( {
		containment: 'form',
		axis: 'y',
		cursor: 'move',
		placeholder: 'sortable-placeholder',
		update: function() {
			needs_saving = true;
			schedule_changed();
			set_tab_order();
		}
	} );

	$( 'input[name=ignore_weekend]' ).on( 'click', function() {
		postbot_error.reset();
		schedule_changed();
	} );

	$( 'input[type=submit]' ).on( 'click', function( e ) {
		postbot_error.reset();
		$( '#confirm-schedule .body-text-1' ).html( postbot.body_text_1 );
		$( '#confirm-schedule .body-text-2' ).html( postbot.body_text_2 );
		$( '#schedule-confirmed' ).html( postbot.schedule_text );
		$( '#confirm-schedule' ).modal();
		e.preventDefault();
	} );

	$( '#schedule-confirmed' ).on( 'click', function( e ) {
		$( '#confirm-schedule' ).modal( 'hide' );

		disable_form_elements( true );

		$( '#schedule-progress span' ).text( postbot.scheduling_text );
		$( '#schedule-progress' ).modal( {
			keyboard: false,
			backdrop: 'static'
		} );

		$( 'input[name=schedule_nonce]' ).val( $( '#blog-' + $( 'input[name=schedule_on_blog]' ).val() ).data( 'nonce' ) );

		slow_publish_schedule( 1 );

		e.preventDefault();
	} );

	$( '#schedule-pick-time' ).on( 'click', function( e ) {
		$( '#pick-time' ).toggle();
		postbot_error.reset();
		e.preventDefault();
	} );

	$( '#pick-time button' ).on( 'click', function( e ) {
		$( '#pick-time' ).toggle();
		postbot_error.reset();
		schedule_changed();
		e.preventDefault();
	} );

	$( 'body' ).on( 'click', 'a.schedule-remove', function( e ) {
		var media_id = $( this ).closest( 'li' ).data( 'media-id' );
		var data = {
			action: 'postbot_delete',
			nonce: $( this ).data( 'nonce' ),
			media_id: media_id
		};
		var item_to_delete = $( this ).closest( 'li' ).detach();

		postbot_error.reset();
		item_to_delete.hide();
		set_tab_order();

		$.post( postbot.ajax_url, data, function( response ) {
			if ( response.error ) {
				$( '.schedule-list' ).append( item_to_delete );
				schedule_changed();
				update_body_class();
				set_tab_order();
				return postbot_error.add( response.error );
			}
		}, 'json' );

		schedule_changed();
		update_body_class();
		e.preventDefault();
	} );

	$( '#upload-help' ).on( 'click', function( e ) {
		postbot_error.reset();
		$( '#pick-files' ).click();
		e.preventDefault();
	} );

	$( 'a.schedule-delete-pending' ).on( 'click', function() {
		if ( confirm( postbot.are_you_sure ) ) {
			// Remove from list
			$( this ).closest( 'li' ).remove();

			// Tell server
			var data = {
				action:  'postbot_delete_pending',
				id:      $( this ).data( 'id' ),
				nonce:   $( this ).data( 'nonce' )
			};

			$.post( postbot.ajax_url, data, function() {
				if ( $( 'ul.schedule-list li' ).length === 0 )
					document.location = postbot.scheduler_url;
			} );
		}

		return false;
	} );

	$( '.schedule-list' ).on( 'change', '.schedule-item input,.schedule-item textarea', function() {
		needs_saving = true;
	} );

	$( '#responsive-menu-button' ).on( 'click', function( a ) {
		a.preventDefault();
		$( '.navbar-left' ).toggleClass( 'dropped' );
	} );

	setInterval( auto_save, 5000 );
	set_tab_order();

	if ( typeof(auto_publish) != 'undefined' && auto_publish )
		$( '#schedule-confirmed' ).click();
} );
