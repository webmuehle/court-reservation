'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const assert = require( 'assert' );

const root = path.join( __dirname, '..' );
const hooks = fs.readFileSync( path.join( root, 'includes/class-courtres.php' ), 'utf8' );
const admin = fs.readFileSync( path.join( root, 'admin/class-courtres-admin.php' ), 'utf8' );
const piramid = fs.readFileSync( path.join( root, 'public/class-piramids-public.php' ), 'utf8' );
const publicPhp = fs.readFileSync( path.join( root, 'public/class-courtres-public.php' ), 'utf8' );
const adminJs = fs.readFileSync( path.join( root, 'admin/js/courtres-admin.js' ), 'utf8' );
const piramidJs = fs.readFileSync( path.join( root, 'public/js/piramid-public.js' ), 'utf8' );
const publicJs = fs.readFileSync( path.join( root, 'public/js/courtres-public.js' ), 'utf8' );
const header = fs.readFileSync( path.join( root, 'courtres.php' ), 'utf8' );
const readme = fs.readFileSync( path.join( root, 'readme.txt' ), 'utf8' );
const notices = fs.readFileSync( path.join( root, 'includes/class-courtres-notices.php' ), 'utf8' );
const challengesView = fs.readFileSync( path.join( root, 'public/partials/courtres-public-challenges.php' ), 'utf8' );
const pyramidView = fs.readFileSync( path.join( root, 'public/partials/courtres-public-piramid.php' ), 'utf8' );
const players = fs.readFileSync( path.join( root, 'includes/entity/piramids-players.php' ), 'utf8' );

const stateChangingNopriv = [
	'edit_reservation_type',
	'create_challenge',
	'accept_challenge',
	'schedule_challenge',
	'delete_challenge',
	'enter_challenge_result',
	'get_court',
	'download_csv',
	'withdraw_challenge',
];

stateChangingNopriv.forEach( function ( action ) {
	assert.doesNotMatch(
		hooks,
		new RegExp( 'wp_ajax_nopriv_' + action + '\\b' ),
		action + ' must not use a public ajax hook'
	);
} );

assert.doesNotMatch(
	hooks,
	/admin_post_nopriv_get_players_select_options/,
	'Player list must not use a public admin-post hook'
);

assert.match( admin, /function edit_reservation_type\(\) \{[\s\S]*current_user_can\(\s*'manage_options'\s*\)/ );
assert.match( admin, /check_ajax_referer\(\s*'courtres_edit_reservation_type',\s*'nonce'\s*\)/ );
assert.match( admin, /function download_csv\(\) \{[\s\S]*current_user_can\(\s*'manage_options'\s*\)/ );
assert.match( admin, /wp_verify_nonce\(\s*\$nonce,\s*'export_expired'\s*\)/ );
assert.match( admin, /function get_players_select_options\(\) \{[\s\S]*current_user_can\(\s*'place_reservation'\s*\)/ );
assert.match( admin, /check_ajax_referer\(\s*'courtres_players_select',\s*'players_nonce'\s*\)/ );
assert.match( admin, /reservation_type_nonce/ );

assert.match( piramid, /authorize_challenge_request\(\s*\$response,\s*'delete_nonce',\s*'delete_nonce'\s*\)/ );
assert.match( piramid, /authorize_challenge_request\(\s*\$response,\s*'accept_nonce',\s*'accept_nonce'\s*\)/ );
assert.match( piramid, /authorize_challenge_request\(\s*\$response,\s*'create_challenge_nonce',\s*'create_challenge'\s*\)/ );
assert.match( piramid, /authorize_challenge_request\(\s*\$response,\s*'schedule_challenge_nonce',\s*'schedule_challenge'\s*\)/ );
assert.match( piramid, /authorize_challenge_request\(\s*\$response,\s*'enter_results_nonce',\s*'enter_results'\s*\)/ );
assert.match( piramid, /current_user_can\(\s*'place_reservation'\s*\)/ );
assert.match( piramid, /accept_link_token/ );
assert.match( piramid, /authorize_challenge_request\(\s*\$response,\s*'withdraw_nonce',\s*'withdraw_nonce'\s*\)/ );
assert.match( piramid, /load_challenge_for_user\(\s*\$challenge_id,\s*\$response,\s*'challenger'\s*\)/ );
assert.match( piramid, /'created' !== \$challenge\['status'\]/ );
assert.match( piramid, /challengeable_player_ids/ );
assert.match( piramid, /You cannot challenge this player\./ );
assert.match( hooks, /wp_ajax_withdraw_challenge/ );
assert.match( hooks, /after_challenge_withdrawn/ );
assert.match( notices, /function after_challenge_withdrawn/ );
assert.match( notices, /has withdrawn the challenge\./ );
assert.match( challengesView, /data-withdraw_nonce/ );
assert.match( challengesView, /esc_html__\(\s*'Withdraw',\s*'court-reservation'\s*\)/ );
assert.match( pyramidView, /challengeable_player_ids/ );
assert.match( players, /function challengeable_player_ids/ );
assert.match( piramidJs, /withdraw_nonce/ );
assert.match( piramidJs, /"action": "withdraw_challenge"/ );

assert.match( publicPhp, /check_ajax_referer\(\s*'courtres_get_court',\s*'court_nonce'\s*\)/ );
assert.match( publicPhp, /players_nonce/ );

assert.match( adminJs, /reservation_type_nonce/ );
assert.match( piramidJs, /court_nonce/ );
assert.match( piramidJs, /accept_nonce/ );
assert.match( piramidJs, /delete_nonce/ );
assert.match( publicJs, /players_nonce/ );

assert.match( header, /Version:\s+1\.12\.3/ );
assert.match( header, /define\(\s*'Court_Reservation',\s*'1\.12\.3'\s*\)/ );
assert.match( readme, /Stable tag:\s*1\.12\.3/ );
assert.match( readme, /Security hardening of AJAX handlers/ );

console.log( 'ajax handler checks ok' );
