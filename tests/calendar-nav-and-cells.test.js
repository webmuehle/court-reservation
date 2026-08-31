'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const assert = require( 'assert' );

const root = path.join( __dirname, '..' );
const publicJs = fs.readFileSync( path.join( root, 'public/js/courtres-public.js' ), 'utf8' );
const flatpickrJs = fs.readFileSync( path.join( root, 'admin/js/courtres-flatpickr.js' ), 'utf8' );
const publicCss = fs.readFileSync( path.join( root, 'public/css/courtres-public.css' ), 'utf8' );
const publicPhp = fs.readFileSync( path.join( root, 'public/class-courtres-public.php' ), 'utf8' );
const functionsPhp = fs.readFileSync( path.join( root, 'functions.php' ), 'utf8' );

function fail( msg ) {
	throw new Error( msg );
}

// --- Compact table cells ---
assert.doesNotMatch(
	publicCss,
	/table\.table\.reservations td\s*\{[^}]*height:\s*6em/,
	'Reservation cells must not be forced to 6em'
);
assert.match(
	publicCss,
	/table\.table\.reservations td\s*\{[^}]*height:\s*auto/,
	'Reservation cells should size to their content'
);
assert.match(
	publicPhp,
	/table\.table\.reservations th, table\.table\.reservations td \{[\s\S]*height: auto;/,
	'Cell-height setting must not force table height: 100% / square rows'
);
assert.doesNotMatch(
	publicPhp,
	/function option_ui_table_cell_height\(\) \{[\s\S]{0,800}height: 100%;/,
	'option_ui_table_cell_height must not stretch the table to 100% height'
);

// --- Next/prev keep the current page ---
assert.match(
	publicJs,
	/var \$step = courtresReadNavigatorStep\( id \);/,
	'> and < must start from the currently viewed date, not from today'
);
assert.match(
	publicJs,
	/function courtresReadNavigatorStep\( courtId \) \{[\s\S]*data-from-day/,
	'Current page is stored on the reservation table'
);
assert.match(
	publicJs,
	/courtresSyncDateInput\( id, \$step \)/,
	'Date input stays in sync after arrow navigation'
);

function nextStep( current, increment, innerWidth ) {
	if ( innerWidth > 900 ) {
		return current + increment;
	}
	return current + 1;
}
function prevStep( current, increment ) {
	const next = current - increment;
	return next < 0 ? 0 : next;
}

const daysPerPage = 3;
let step = 0;
step = nextStep( step, daysPerPage, 1200 );
assert.strictEqual( step, 3, 'first > click advances one page' );
step = nextStep( step, daysPerPage, 1200 );
assert.strictEqual( step, 6, 'second > click advances past 6 days' );
step = nextStep( step, daysPerPage, 1200 );
assert.strictEqual( step, 9, 'third > click continues into later dates' );
step = prevStep( step, daysPerPage );
assert.strictEqual( step, 6, '< returns one page' );
step = prevStep( 2, daysPerPage );
assert.strictEqual( step, 0, '< does not go before today' );

function ymdFromStep( todayYmd, stepOffset ) {
	const todayMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec( todayYmd );
	const d = new Date( Date.UTC( +todayMatch[1], +todayMatch[2] - 1, +todayMatch[3] ) );
	d.setUTCDate( d.getUTCDate() + ( parseInt( stepOffset, 10 ) || 0 ) );
	return (
		d.getUTCFullYear() +
		'-' +
		String( d.getUTCMonth() + 1 ).padStart( 2, '0' ) +
		'-' +
		String( d.getUTCDate() ).padStart( 2, '0' )
	);
}
assert.strictEqual( ymdFromStep( '2026-08-31', 0 ), '2026-08-31' );
assert.strictEqual( ymdFromStep( '2026-08-31', 6 ), '2026-09-06' );
assert.strictEqual( ymdFromStep( '2026-08-31', 9 ), '2026-09-09' );

// --- Flatpickr follows WordPress locale instead of always German ---
assert.match( flatpickrJs, /function resolveLocaleCode\(/ );
assert.match( flatpickrJs, /function getLocale\(/ );
assert.doesNotMatch(
	flatpickrJs,
	/function getLocale\(\) \{[\s\S]{0,200}return 'de';/,
	'Flatpickr must not be hardcoded to German'
);
assert.match( functionsPhp, /courtres_flatpickr/ );
assert.match( functionsPhp, /firstDayOfWeek/ );

function resolveLocaleCode( loc ) {
	return String( loc ).toLowerCase().replace( '_', '-' ).split( '-' )[0];
}
function pickLocale( lang, l10ns ) {
	if ( lang && l10ns[lang] ) {
		return 'localized:' + lang;
	}
	return 'default';
}
assert.strictEqual( resolveLocaleCode( 'en_US' ), 'en' );
assert.strictEqual( resolveLocaleCode( 'de_AT' ), 'de' );
assert.strictEqual( pickLocale( 'en', { de: {} } ), 'default' );
assert.strictEqual( pickLocale( 'de', { de: {} } ), 'localized:de' );

const tables = [
	'public/partials/courtres-public-table.php',
	'public/partials/courtres-public-display.php',
	'public/partials/courtres-public-table-full-view.php',
	'public/partials/courtres-public-display-full-view.php',
];
tables.forEach( function ( file ) {
	const src = fs.readFileSync( path.join( root, file ), 'utf8' );
	assert.match( src, /data-from-day=/, file + ' must expose the current page' );
} );

console.log( 'calendar-nav-and-cells.test.js: all assertions passed' );
