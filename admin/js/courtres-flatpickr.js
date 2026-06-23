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
		var panel = input.closest( '[id^="drugi_kal_"]' );
		if ( ! panel ) {
			return false;
		}
		return window.getComputedStyle( panel ).display === 'none';
	}

	function ensureCalendarOverlay( instance ) {
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
		if ( ! cal.classList.contains( 'open' ) ) {
			cal.style.display = 'none';
		}
	}

	function overlayHooks() {
		return {
			onReady: function ( selectedDates, dateStr, instance ) {
				ensureCalendarOverlay( instance );
			},
			onOpen: function ( selectedDates, dateStr, instance ) {
				ensureCalendarOverlay( instance );
				if ( instance.calendarContainer ) {
					instance.calendarContainer.style.display = 'block';
				}
				if ( typeof instance._positionCalendar === 'function' ) {
					instance._positionCalendar();
				}
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
			clickOpens: true,
		};
	}

	function destroyInput( input ) {
		if ( ! input || ! input._flatpickr ) {
			return;
		}
		var fp = input._flatpickr;
		var cal = fp.calendarContainer;
		fp.destroy();
		if ( cal && cal.parentNode ) {
			cal.parentNode.removeChild( cal );
		}
		input.removeAttribute( 'data-cr-fp-init' );
		var wrap = input.closest( '.cr-calendar-picker' );
		if ( wrap ) {
			wrap.removeAttribute( 'data-cr-fp-wrap' );
		}
	}

	window.courtresDestroyFlatpickr = function ( root ) {
		var scope = root || document;
		scope.querySelectorAll( 'input' ).forEach( function ( input ) {
			destroyInput( input );
		} );
	};

	window.courtresCleanupFlatpickrCalendars = function ( root ) {
		var active = new Set();
		var scope = root || document;
		scope.querySelectorAll( 'input' ).forEach( function ( input ) {
			if ( input._flatpickr && input._flatpickr.calendarContainer ) {
				active.add( input._flatpickr.calendarContainer );
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

	function bindCalendarWrapper( input, fp ) {
		var wrap = input.closest( '.cr-calendar-picker' );
		if ( ! wrap || wrap.getAttribute( 'data-cr-fp-wrap' ) ) {
			return;
		}
		wrap.setAttribute( 'data-cr-fp-wrap', '1' );
		wrap.addEventListener(
			'click',
			function ( e ) {
				if ( e.target.closest( '.cr-calendar-dismiss' ) ) {
					return;
				}
				if ( e.target === input ) {
					return;
				}
				fp.open();
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
				defaultDate: initial.length ? initial : null,
			} )
		);
		input.setAttribute( 'data-cr-fp-init', '1' );
	}

	function initReservationInput( input ) {
		var courtId = input.getAttribute( 'data-court-id' ) || '';
		var initial =
			input.value && input.value !== 'YYYY-MM-DD' ? input.value : null;

		var fp = flatpickr(
			input,
			Object.assign( {}, baseOptions(), overlayHooks(), {
				dateFormat: 'Y-m-d',
				disableMobile: true,
				closeOnSelect: true,
				onChange: function ( selectedDates, dateStr ) {
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
		}

		input.setAttribute( 'data-cr-fp-init', '1' );
		bindCalendarWrapper( input, fp );
	}

	function initSingleInput( input ) {
		var initial = input.value ? input.value : null;
		var fp = flatpickr(
			input,
			Object.assign( {}, baseOptions(), {
				dateFormat: resolveDateFormat( input ),
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

	// Backward compatibility for reservation calendar AJAX hooks.
	window.courtresInitReservationDatepickers = window.courtresInitFlatpickr;

	$( document ).ready( function () {
		window.courtresInitFlatpickr();
		$( 'form[name="kalendar"]' ).on( 'submit', function ( e ) {
			e.preventDefault();
		} );
		$( document ).on( 'click', '[id^="prvi_kal_"] a.button', function ( e ) {
			if ( $( e.currentTarget ).closest( '[data-navigator]' ).length ) {
				return;
			}
			var navId = $( this ).closest( '[id^="prvi_kal_"]' ).attr( 'id' );
			if ( ! navId ) {
				return;
			}
			var courtId = navId.replace( 'prvi_kal_', '' );
			window.setTimeout( function () {
				var root = document.getElementById( 'drugi_kal_' + courtId );
				window.courtresInitFlatpickr( root || document );
			}, 50 );
		} );
	} );
}( jQuery ) );
