( function ( $ ) {
	'use strict';

	function getLocale() {
		if ( typeof flatpickr !== 'undefined' && flatpickr.l10ns && flatpickr.l10ns.de ) {
			return flatpickr.l10ns.de;
		}
		return 'de';
	}

	function jqueryFormatToFlatpickr( format ) {
		if ( ! format ) {
			return 'Y-m-d';
		}
		return String( format )
			.replace( /yyyy/g, 'Y' )
			.replace( /dd/g, 'd' )
			.replace( /mm/g, 'm' )
			.replace( /yy/g, 'Y' );
	}

	function resolveDateFormat( input ) {
		return jqueryFormatToFlatpickr(
			input.getAttribute( 'data-flatpickr-format' ) ||
				input.getAttribute( 'data-eventdateformat' ) ||
				input.getAttribute( 'data-date_format' ) ||
				'Y-m-d'
		);
	}

	function isHiddenReservationPanel( input ) {
		var wrap = input.closest( '.cr-calendar-expanded' );
		if ( wrap ) {
			return wrap.classList.contains( 'cr-calendar-expanded--collapsed' );
		}
		var panel = input.closest( '[id^="drugi_kal_"]' );
		if ( ! panel ) {
			return false;
		}
		return window.getComputedStyle( panel ).display === 'none';
	}

	function ensureCalendarOverlay( instance, visibleInput ) {
		var cal = instance && instance.calendarContainer;
		if ( ! cal ) {
			return;
		}
		if ( cal.parentNode !== document.body ) {
			document.body.appendChild( cal );
		}
		cal.classList.remove( 'inline', 'static' );
		cal.style.position = 'fixed';
		cal.style.zIndex = '100002';
		cal.style.margin = '0';
		cal.style.right = 'auto';
		cal.style.bottom = 'auto';
		if ( ! cal.classList.contains( 'open' ) ) {
			cal.style.display = 'none';
			return;
		}
		cal.style.display = 'block';
		positionCalendarNearInput( cal, visibleInput || instance._crVisibleInput );
	}

	function positionCalendarNearInput( cal, visibleInput ) {
		if ( ! cal || ! visibleInput ) {
			return;
		}
		var rect = visibleInput.getBoundingClientRect();
		var calWidth = cal.offsetWidth || 308;
		var calHeight = cal.offsetHeight || 320;
		var top = rect.bottom + 6;
		var left = rect.left;

		if ( top + calHeight > window.innerHeight - 8 ) {
			top = Math.max( 8, rect.top - calHeight - 6 );
		}
		if ( left + calWidth > window.innerWidth - 8 ) {
			left = Math.max( 8, window.innerWidth - calWidth - 8 );
		}
		if ( left < 8 ) {
			left = 8;
		}

		cal.style.top = top + 'px';
		cal.style.left = left + 'px';
	}

	function reservationOverlayHooks( visibleInput ) {
		return {
			onReady: function ( selectedDates, dateStr, instance ) {
				instance._crVisibleInput = visibleInput;
				ensureCalendarOverlay( instance, visibleInput );
			},
			onOpen: function ( selectedDates, dateStr, instance ) {
				ensureCalendarOverlay( instance, visibleInput );
				if ( typeof instance._positionCalendar === 'function' ) {
					instance._positionCalendar();
				}
				ensureCalendarOverlay( instance, visibleInput );
			},
			onClose: function ( selectedDates, dateStr, instance ) {
				if ( instance.calendarContainer ) {
					instance.calendarContainer.style.display = 'none';
				}
			},
		};
	}

	function baseOptions() {
		return {
			locale: getLocale(),
			allowInput: false,
			static: false,
			appendTo: document.body,
			disableMobile: false,
			clickOpens: false,
		};
	}

	function getReservationAnchor( courtId ) {
		var anchorId = 'cr-fp-anchor-' + courtId;
		var anchor = document.getElementById( anchorId );
		if ( anchor ) {
			return anchor;
		}
		anchor = document.createElement( 'input' );
		anchor.type = 'text';
		anchor.id = anchorId;
		anchor.setAttribute( 'aria-hidden', 'true' );
		anchor.setAttribute( 'tabindex', '-1' );
		anchor.className = 'cr-flatpickr-anchor';
		document.body.appendChild( anchor );
		return anchor;
	}

	function destroyInput( input ) {
		if ( ! input ) {
			return;
		}
		if ( input._crFpAnchor && input._crFpAnchor._flatpickr ) {
			var anchorFp = input._crFpAnchor._flatpickr;
			var anchorCal = anchorFp.calendarContainer;
			anchorFp.destroy();
			if ( anchorCal && anchorCal.parentNode ) {
				anchorCal.parentNode.removeChild( anchorCal );
			}
		}
		if ( input._crFpAnchor && input._crFpAnchor.parentNode ) {
			input._crFpAnchor.parentNode.removeChild( input._crFpAnchor );
		}
		if ( input._flatpickr ) {
			var fp = input._flatpickr;
			var cal = fp.calendarContainer;
			fp.destroy();
			if ( cal && cal.parentNode ) {
				cal.parentNode.removeChild( cal );
			}
		}
		input.removeAttribute( 'data-cr-fp-init' );
		delete input._crFpAnchor;
		delete input._flatpickr;
		var wrap = input.closest( '.cr-calendar-picker' );
		if ( wrap ) {
			wrap.removeAttribute( 'data-cr-fp-wrap' );
		}
	}

	window.courtresDestroyFlatpickr = function ( root ) {
		var scope = root || document;
		scope.querySelectorAll( 'input.cr-reservation-date-input' ).forEach( function ( input ) {
			destroyInput( input );
		} );
		scope.querySelectorAll( 'input' ).forEach( function ( input ) {
			if ( input.classList.contains( 'cr-reservation-date-input' ) ) {
				return;
			}
			if ( input._flatpickr || input.getAttribute( 'data-cr-fp-init' ) ) {
				destroyInput( input );
			}
		} );
	};

	window.courtresCleanupFlatpickrCalendars = function ( root ) {
		var active = new Set();
		var scope = root || document;
		scope.querySelectorAll( 'input' ).forEach( function ( input ) {
			var fp = input._flatpickr;
			if ( fp && fp.calendarContainer ) {
				active.add( fp.calendarContainer );
			}
			if ( input._crFpAnchor && input._crFpAnchor._flatpickr && input._crFpAnchor._flatpickr.calendarContainer ) {
				active.add( input._crFpAnchor._flatpickr.calendarContainer );
			}
		} );
		document.querySelectorAll( 'body > .flatpickr-calendar' ).forEach( function ( cal ) {
			if ( ! active.has( cal ) && cal.parentNode ) {
				cal.parentNode.removeChild( cal );
			}
		} );
	};

	function todayYmd() {
		if ( typeof courtres_params !== 'undefined' && courtres_params.today_ymd ) {
			return courtres_params.today_ymd;
		}
		var d = new Date();
		return (
			d.getFullYear() +
			'-' +
			String( d.getMonth() + 1 ).padStart( 2, '0' ) +
			'-' +
			String( d.getDate() ).padStart( 2, '0' )
		);
	}

	function parseYmd( str ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( str );
		if ( ! m ) {
			return null;
		}
		return Date.UTC( +m[1], +m[2] - 1, +m[3] );
	}

	function dayOffsetFromToday( ymd ) {
		var sel = parseYmd( ymd );
		var today = parseYmd( todayYmd() );
		if ( sel === null || today === null ) {
			return 0;
		}
		return Math.round( ( sel - today ) / 86400000 );
	}

	function bindReservationOpen( visibleInput, fp ) {
		var wrap = visibleInput.closest( '.cr-calendar-picker' );
		if ( ! wrap || wrap.getAttribute( 'data-cr-fp-wrap' ) ) {
			return;
		}
		wrap.setAttribute( 'data-cr-fp-wrap', '1' );
		var openPicker = function ( e ) {
			if ( e ) {
				e.preventDefault();
			}
			fp.open();
		};
		visibleInput.addEventListener( 'click', openPicker );
		visibleInput.addEventListener( 'focus', openPicker );
		wrap.addEventListener(
			'click',
			function ( e ) {
				if ( e.target.closest( '.cr-calendar-dismiss' ) ) {
					e.stopPropagation();
					return;
				}
				openPicker( e );
			}
		);
	}

	function initMultipleInput( input ) {
		var initial = [];
		if ( input.value ) {
			initial = input.value.split( ',' ).map( function ( d ) {
				return d.trim();
			} ).filter( Boolean );
		}

		flatpickr(
			input,
			Object.assign( {}, baseOptions(), {
				mode: 'multiple',
				dateFormat: 'Y-m-d',
				clickOpens: true,
				defaultDate: initial.length ? initial : null,
			} )
		);
		input.setAttribute( 'data-cr-fp-init', '1' );
	}

	function initReservationInput( visibleInput ) {
		var courtId = visibleInput.getAttribute( 'data-court-id' ) || '';
		var initial =
			visibleInput.value && visibleInput.value !== 'YYYY-MM-DD' ? visibleInput.value : null;
		var anchor = getReservationAnchor( courtId );

		var fp = flatpickr(
			anchor,
			Object.assign( {}, baseOptions(), reservationOverlayHooks( visibleInput ), {
				dateFormat: 'Y-m-d',
				disableMobile: true,
				closeOnSelect: true,
				onChange: function ( selectedDates, dateStr ) {
					visibleInput.value = dateStr || '';
					if ( ! dateStr || ! courtId ) {
						return;
					}
					if ( typeof window.courtresNavigateToStep === 'function' ) {
						window.courtresNavigateToStep(
							courtId,
							dayOffsetFromToday( dateStr )
						);
					}
				},
			} )
		);

		if ( initial ) {
			fp.setDate( initial, false );
			anchor.value = initial;
		}

		visibleInput._crFpAnchor = anchor;
		visibleInput._flatpickr = fp;
		visibleInput.setAttribute( 'data-cr-fp-init', '1' );
		bindReservationOpen( visibleInput, fp );
	}

	function initSingleInput( input ) {
		var initial = input.value ? input.value : null;
		var fp = flatpickr(
			input,
			Object.assign( {}, baseOptions(), {
				dateFormat: resolveDateFormat( input ),
				clickOpens: true,
			} )
		);

		if ( initial ) {
			fp.setDate( initial, false );
		}

		input.setAttribute( 'data-cr-fp-init', '1' );
	}

	function shouldInit( input ) {
		if ( input.getAttribute( 'data-cr-fp-init' ) ) {
			return false;
		}
		if ( input.classList.contains( 'cr-flatpickr-anchor' ) ) {
			return false;
		}
		if ( input.classList.contains( 'cr-reservation-date-input' ) ) {
			return true;
		}
		if (
			input.id === 'courtres-event-dates' ||
			input.classList.contains( 'courtres-event-dates' )
		) {
			return true;
		}
		if ( input.classList.contains( 'datepicker' ) || input.classList.contains( 'cr-flatpickr' ) ) {
			return true;
		}
		return false;
	}

	window.courtresInitFlatpickr = function ( root ) {
		if ( typeof flatpickr === 'undefined' ) {
			return;
		}

		var scope = root || document;
		var inputs = scope.querySelectorAll( 'input' );

		inputs.forEach( function ( input ) {
			if ( ! shouldInit( input ) ) {
				return;
			}
			if (
				input.classList.contains( 'cr-reservation-date-input' ) &&
				isHiddenReservationPanel( input )
			) {
				destroyInput( input );
				return;
			}

			destroyInput( input );

			if (
				input.id === 'courtres-event-dates' ||
				input.classList.contains( 'courtres-event-dates' ) ||
				input.getAttribute( 'data-flatpickr-mode' ) === 'multiple'
			) {
				initMultipleInput( input );
				return;
			}

			if ( input.classList.contains( 'cr-reservation-date-input' ) ) {
				initReservationInput( input );
				return;
			}

			initSingleInput( input );
		} );
	};

	window.courtresInitReservationDatepickers = window.courtresInitFlatpickr;

	function readCourtId( el ) {
		return el.getAttribute( 'data-court-id' ) || $( el ).data( 'courtId' ) || '';
	}

	$( document ).ready( function () {
		window.courtresInitFlatpickr();
		$( 'form[name="kalendar"]' ).on( 'submit', function ( e ) {
			e.preventDefault();
		} );
		$( document ).on( 'click', '.cr-calendar-open', function ( e ) {
			e.preventDefault();
			var courtId = readCourtId( this );
			if ( ! courtId || typeof window.courtresExpandCalendarPanel !== 'function' ) {
				return;
			}
			window.courtresExpandCalendarPanel( courtId );
			window.setTimeout( function () {
				var root = document.getElementById( 'drugi_kal_' + courtId );
				window.courtresInitFlatpickr( root || document );
			}, 50 );
		} );
		$( document ).on( 'click', '.cr-calendar-dismiss', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			var courtId = readCourtId( this );
			if ( ! courtId || typeof window.courtresCollapseCalendarPanel !== 'function' ) {
				return;
			}
			var visibleInput = document.querySelector(
				'#cr-datum-' + courtId + ', #drugi_kal_' + courtId + ' .cr-reservation-date-input'
			);
			if ( visibleInput && visibleInput._flatpickr && visibleInput._flatpickr.isOpen ) {
				visibleInput._flatpickr.close();
			}
			window.courtresCollapseCalendarPanel( courtId );
		} );
		$( window ).on( 'resize scroll', function () {
			document.querySelectorAll( 'input.cr-reservation-date-input[data-cr-fp-init]' ).forEach( function ( input ) {
				var fp = input._flatpickr;
				if ( fp && fp.isOpen && fp.calendarContainer ) {
					ensureCalendarOverlay( fp, input );
				}
			} );
		} );
	} );
}( jQuery ) );
