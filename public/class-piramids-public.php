<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The piramid public-facing functionality of the plugin.
 *
 * @link       https://webmuehle.at
 * @since      1.5.0
 *
 * @package    Courtres
 * @subpackage Courtres/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Courtres
 * @subpackage Courtres/public
 * @author     Webmühle e.U. <office@webmuehle.at>
 */
class Piramids_Public extends Courtres_Entity_Piramid {


	/**
	 * The ID of this plugin.
	 *
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The version of assets of this plugin.
	 *
	 * @var      string    $version    The current version of this plugin.
	 */
	private $assets_version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version           The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name    = $plugin_name;
		$this->version        = $version;
		$this->assets_version = $version . '.12';
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 */
	public function enqueue_styles() {
	}


	/**
	 * Register the JavaScript for the public-facing side of the site.
	 */
	public function enqueue_scripts() {
	}


	/**
	 * Add the courtpiramid shortcode: [courtpyramid id="#id#" courts="1,2..."]
	 *
	 * @param  array $atts    [description]
	 */
	public function public_shortcode_courtpyramid( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'id'     => false,
				'courts' => false,
			),
			$atts,
			'courtpyramid'
		);

		$piramid = array();
		if ( $atts['id'] ) {
			$piramid_id = $atts['id'];
			$piramid    = Courtres_Entity_Piramid::get_by_id( $piramid_id );
			if ( $piramid ) {
				$piramid['players'] = Courtres_Entity_Piramids_Players::get_by_piramid_id( $piramid_id );
				$atts['piramid']    = $piramid;
			}
		}

		if ( ! $atts['courts'] ) {
			$atts['courts'] = Courtres_Public::getCourts();
		}

		// check rigths for authorized wp user
		$player_user                   = false;
		$can_create_challenge          = false; // user can create the challenge
		$needs_authorize_as_challenged = false; // to accept the challenge by direct link (from email) you need autorized as challenged user
		$user_can_accept               = false; // user can accept the challenge
		$is_accepted                   = false; // succesfully_accepted by direct link (from email)
		$accepting_challenge           = false; // the challenge for accept by direct link (from email)

		if ( is_user_logged_in() && current_user_can( 'place_reservation' ) ) {
			$player_user          = wp_get_current_user();
			$can_create_challenge = true;
		}
		$the_player = false;
		if ( $player_user ) {
			$found_key  = array_search( $player_user->ID, array_column( $piramid['players'], 'player_id' ) );
			$the_player = $found_key == false ? false : $piramid['players'][ $found_key ];
		}
		$atts['player_user'] = $player_user;
		$atts['the_player']  = $the_player;

		// accept the challenge by params redirected from email link
		$query_challenge = get_query_var( 'cr-challenge' );
		if ( ! $query_challenge ) {
			$query_challenge = get_query_var( 'challenge' );
		}
		$query_action = get_query_var( 'cr-action' );
		if ( ! $query_action ) {
			$query_action = get_query_var( 'action' );
		}
		$query_challenge = absint( $query_challenge );

		if ( $query_challenge && 'accept' === $query_action ) {
			$challenges_class    = Courtres_Entity_Challenges::get_instance( $query_challenge );
			$accepting_challenge = $challenges_class->get_full_data();
			$is_challenged_user  = $player_user && $accepting_challenge && (int) $player_user->ID === (int) $accepting_challenge['challenged_id'];
			$token               = isset( $_GET['cr_accept_token'] ) ? sanitize_text_field( wp_unslash( $_GET['cr_accept_token'] ) ) : '';
			$expected_token      = ( $accepting_challenge && ! empty( $accepting_challenge['challenged_id'] ) )
				? Courtres_Entity_Challenges::accept_link_token( $query_challenge, $accepting_challenge['challenged_id'] )
				: '';
			$token_ok            = ( '' !== $expected_token && '' !== $token && hash_equals( $expected_token, $token ) );

			if ( $is_challenged_user && $token_ok && isset( $accepting_challenge['status'] ) && 'created' === $accepting_challenge['status'] ) {
				$res         = $challenges_class->set_accepted();
				$is_accepted = (bool) $res;
			} elseif ( $accepting_challenge && isset( $accepting_challenge['status'] ) && 'created' === $accepting_challenge['status'] && ! $is_challenged_user ) {
				$needs_authorize_as_challenged = true;
			}

			$user_can_accept = (bool) $is_challenged_user && $accepting_challenge && isset( $accepting_challenge['status'] ) && 'created' === $accepting_challenge['status'] && ! $is_accepted;
		} else {
			$user_can_accept = $player_user ? Courtres_Entity_Challenges::user_can_accept( $player_user->ID ) : false;
		}

		// When the challenge is not accepted by the challenged player within 24 hours, the challenge is automatically deleted
		$lifetime_ts = $piramid['lifetime_ts'];
		Courtres_Entity_Challenges::delete_created_expired( $lifetime_ts );
		Courtres_Entity_Challenges::delete_accepted_expired( $lifetime_ts );

		// Set status "played" to challenges with status "scheduled" and current timestamp > challenge["end_ts"]
		Courtres_Entity_Challenges::set_played();

		ob_start();
		include 'partials/' . $this->plugin_name . '-public-piramid.php';

		wp_enqueue_style( $this->plugin_name . 'datepicker', plugin_dir_url( __FILE__ ) . 'css/piramid-public.css', array(), $this->assets_version, 'all' );

		wp_add_inline_style( $this->plugin_name . 'inline_piramid', plugin_dir_url( __FILE__ ) . 'css/inline_css.php' );

		courtres_register_flatpickr_assets(
			'admin/js/courtres-flatpickr.js',
			$this->assets_version
		);
		courtres_enqueue_flatpickr_assets();

		wp_enqueue_script(
			$this->plugin_name . 'piramid',
			plugin_dir_url( __FILE__ ) . 'js/piramid-public.js',
			array(
				'jquery',
			),
			$this->assets_version,
			true
		);
		wp_localize_script(
			$this->plugin_name . 'piramid',
			$this->plugin_name . '_params_pir',
			array(
				'ajax_url'                      => admin_url( 'admin-ajax.php' ),
				'court_nonce'                   => wp_create_nonce( 'courtres_get_court' ),
				'user_can_accept'               => $user_can_accept,
				'needs_authorize_as_challenged' => $needs_authorize_as_challenged,
				'login_href'                    => wp_login_url( add_query_arg( $_GET ) ),
				'is_accepted'                   => $is_accepted,
				'accepting_challenge'           => $accepting_challenge,
				'trans'                         => array(
					'Challenge someone'           => __( 'Challenge someone', 'court-reservation' ),
					'Accepting the challenge'     => __( 'Accepting the challenge', 'court-reservation' ),
					'To comfirm the challenge you need to login as' => __( 'To comfirm the challenge you need to login as', 'court-reservation' ),
					'login'                       => __( 'login', 'court-reservation' ),
					'You have been challenged'    => __( 'You have been challenged', 'court-reservation' ),
					'You have been challenged by' => __( 'You have been challenged by', 'court-reservation' ),
					'Accept the challenge?'       => __( 'Accept the challenge?', 'court-reservation' ),
					'The challenge accepted'      => __( 'The challenge accepted', 'court-reservation' ),
					'The challenge'               => __( 'The challenge', 'court-reservation' ),
					'was succeffully acepted!'    => __( 'was succeffully acepted!', 'court-reservation' ),
					'Game Date is required'       => __( 'Game Date is required', 'court-reservation' ),
					'Game Time is required'       => __( 'Game Time is required', 'court-reservation' ),
					'Court is required'           => __( 'Court is required', 'court-reservation' ),
					'Winner is required'          => __( 'Winner is required', 'court-reservation' ),
					'Delete Challenge?'           => __( 'Delete Challenge?', 'court-reservation' ),
				),
			)
		);

		return ob_get_clean();
	}



	/**
	 * Add the courtchallenges shortcode: [courtchallenges piramid_id="id" statuses="played, closed"]
	 *
	 * @param  array $atts    [description]
	 */
	public function public_shortcode_courtchallenges( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'      => __( 'Challenges', 'court-reservation' ),
				'piramid_id' => false,
				'statuses'   => false,
			),
			$atts,
			'courtchallenges'
		);

		if ( $atts['piramid_id'] && $atts['statuses'] ) {
			$atts['piramid'] = Courtres_Entity_Piramid::get_by_id( $atts['piramid_id'] );
			$statuses_str    = sanitize_text_field( str_replace( ' ', '', $atts['statuses'] ) );
			if ( $statuses_str ) {
				$atts['challenges'] = Courtres_Entity_Challenges::get_by_statuses( $atts['piramid_id'], explode( ',', $statuses_str ) );
			}
		}

		ob_start();
		include 'partials/' . $this->plugin_name . '-public-challenges.php';
		return ob_get_clean();
	}


	/**
	 * JSON error for a challenge AJAX handler.
	 *
	 * @param array  $response Response payload.
	 * @param string $message  Error message.
	 */
	private function challenge_ajax_error( $response, $message ) {
		$response['errors'][] = $message;
		echo wp_json_encode( $response );
		wp_die();
	}

	/**
	 * Nonce and capability check for challenge actions that change stored data.
	 *
	 * @param array  $response     Response payload.
	 * @param string $nonce_field  POST field name.
	 * @param string $nonce_action Nonce action.
	 */
	private function authorize_challenge_request( $response, $nonce_field, $nonce_action ) {
		$nonce = isset( $_POST[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ) : '';
		if ( empty( $_POST ) || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			$this->challenge_ajax_error( $response, __( 'Error checking security code', 'court-reservation' ) );
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'place_reservation' ) ) {
			$this->challenge_ajax_error( $response, __( 'No permission.', 'court-reservation' ) );
		}
	}

	/**
	 * Load a challenge and confirm the current user may act on it.
	 *
	 * @param int    $challenge_id Challenge id.
	 * @param array  $response     Response payload.
	 * @param string $mode         challenged|participant.
	 * @return array{0:Courtres_Entity_Challenges,1:array}
	 */
	private function load_challenge_for_user( $challenge_id, $response, $mode ) {
		$challenges_class = Courtres_Entity_Challenges::get_instance( absint( $challenge_id ) );
		$challenge        = $challenges_class->get_db_data();
		if ( empty( $challenge['id'] ) ) {
			$this->challenge_ajax_error( $response, __( 'Challenge id is not received', 'court-reservation' ) );
		}

		$uid     = get_current_user_id();
		$allowed = false;
		if ( 'challenged' === $mode ) {
			$allowed = ( (int) $uid === (int) $challenge['challenged_id'] );
		} else {
			$allowed = ( (int) $uid === (int) $challenge['challenger_id'] || (int) $uid === (int) $challenge['challenged_id'] );
		}
		if ( ! $allowed ) {
			$this->challenge_ajax_error( $response, __( 'No permission.', 'court-reservation' ) );
		}

		return array( $challenges_class, $challenge );
	}

	/**
	 * Keep pyramid links on this site.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function sanitize_piramid_url( $url ) {
		$url       = esc_url_raw( $url );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$link_host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $link_host || ! $home_host || strtolower( $link_host ) !== strtolower( (string) $home_host ) ) {
			return home_url();
		}
		return $url;
	}

	/**
	 * Create challenge from piramid-public.js
	 * Called by ajax
	 *
	 * @return
	 */
	function create_challenge() {
		$response = array(
			'errors'  => array(),
			'success' => false,
		);
		$this->authorize_challenge_request( $response, 'create_challenge_nonce', 'create_challenge' );

		$piramid_id    = isset( $_POST['piramid_id'] ) ? absint( $_POST['piramid_id'] ) : 0;
		$challenger_id = get_current_user_id();
		$challenged_id = isset( $_POST['challenged_id'] ) ? absint( $_POST['challenged_id'] ) : 0;
		$piramid_url   = isset( $_POST['piramid_url'] ) ? $this->sanitize_piramid_url( wp_unslash( $_POST['piramid_url'] ) ) : home_url();
		if ( ! $piramid_id || ! $challenger_id || ! $challenged_id || $challenger_id === $challenged_id ) {
			$this->challenge_ajax_error( $response, __( 'Not all required data received', 'court-reservation' ) );
		}

		$pyramid_players = Courtres_Entity_Piramids_Players::get_by_piramid_id( $piramid_id );
		$player_ids      = array();
		if ( is_array( $pyramid_players ) ) {
			$player_ids = array_map( 'intval', wp_list_pluck( $pyramid_players, 'player_id' ) );
		}
		if ( ! in_array( $challenger_id, $player_ids, true ) || ! in_array( $challenged_id, $player_ids, true ) ) {
			$this->challenge_ajax_error( $response, __( 'No permission.', 'court-reservation' ) );
		}
		$challenger_wpuser = get_user_by( 'ID', $challenger_id );
		$challenged_wpuser = get_user_by( 'ID', $challenged_id );
		if ( ! $challenger_wpuser || ! $challenged_wpuser ) {
			$this->challenge_ajax_error( $response, __( 'Not all required data received', 'court-reservation' ) );
		}
		$name = $challenger_wpuser->display_name . ' ' . __( 'Challenge', 'court-reservation' ) . ' ' . $challenged_wpuser->display_name;

		$args = array(
			'name'             => $name,
			'piramid_id'       => $piramid_id,
			'challenger_id'    => $challenger_id,
			'challenged_id'    => $challenged_id,
			'piramid_url'      => $piramid_url,
			'_wp_http_referer' => isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : '',
		);
		$res  = Courtres_Entity_Challenges::create( $args );

		// Player you challenge is already challenged by another player
		if ( $res == -1 ) {
			$response['errors'][] = __( 'Player you challenge is already challenged by another player', 'court-reservation' );
			echo wp_json_encode( $response );
			wp_die();
		}

		// Player has challenges as challenger
		if ( $res == -2 ) {
			$response['errors'][] = __( 'Challenging more than one person is not allowed', 'court-reservation' );
			echo wp_json_encode( $response );
			wp_die();
		}

		// You cannot challenge [#player_name#] yet. The cool-down phase is set to [#number_days#] days.
		if ( is_array( $res ) && isset( $res['closed_challenges'] ) ) {
			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );

			$closed_challenges = $res['closed_challenges'][0];
			$piramid           = Courtres_Entity_Piramid::get_by_id( $piramid_id );

			$lock_expired_ts   = strtotime( $closed_challenges['closed_dt'] ) + $piramid['locktime_ts'];
			$lock_expired_text = date_i18n( $date_format, $lock_expired_ts ) . ', ' . date_i18n( $time_format, $lock_expired_ts );

			$response['errors'][] = __( 'You cannot challenge', 'court-reservation' ) . ' ' . $closed_challenges['challenged']['wp_user']->display_name . __( ' yet. The cool-down phase will be expired at', 'court-reservation' ) . ' ' . $lock_expired_text;
			echo wp_json_encode( $response );
			wp_die();
		}

		if ( $res ) {
			$response['success']      = true;
			$response['challenge_id'] = $res;
		} else {
			$response['errors'][] = __( 'Error inserting into db', 'vaa' );
		}

		echo wp_json_encode( $response );
		wp_die();
	}

	/**
	 * Accepting challenge from piramid-public.js
	 * Called by ajax
	 */
	function accept_challenge() {
		$response = array(
			'errors'  => array(),
			'success' => false,
		);
		$this->authorize_challenge_request( $response, 'accept_nonce', 'accept_nonce' );

		$challenge_id = isset( $_POST['challenge_id'] ) ? absint( $_POST['challenge_id'] ) : 0;
		if ( ! $challenge_id ) {
			$this->challenge_ajax_error( $response, __( 'Challenge id is not received', 'court-reservation' ) );
		}

		list( $challenges_class, $challenge ) = $this->load_challenge_for_user( $challenge_id, $response, 'challenged' );
		if ( empty( $challenge['status'] ) || 'created' !== $challenge['status'] ) {
			$this->challenge_ajax_error( $response, __( 'Error accepting the challenge', 'court-reservation' ) );
		}

		$res = $challenges_class->set_accepted();
		if ( ! $res ) {
			$this->challenge_ajax_error( $response, __( 'Error accepting the challenge', 'court-reservation' ) );
		}

		$response['success'] = true;
		echo wp_json_encode( $response );
		wp_die();
	}


	/**
	 * Accepting the challenge by direct email link
	 *
	 * @param int $challenge_id in query vars
	 * @return
	 */
	function accept_challenge_by_email_link() {
		$params = array(
			'challenge' => get_query_var( 'cr-challenge' ),
			'action'    => get_query_var( 'cr-action' ),
		);
		if ( $params['challenge'] && $params['action'] ) {
			$challenges_class = Courtres_Entity_Challenges::get_instance( $params['challenge'] );
			$challenge        = $challenges_class->get_db_data();
			if ( $challenge ) {
				// $post = Courtres_Entity_Piramid::get_post_with_shortcode($challenge["piramid_id"]);
				// if($post){
				// wp_redirect( get_permalink($post->ID) . "?" .  http_build_query($params) );
				// }
				return;
			} else {
				add_action( 'wp_footer', array( $this, 'show_no_challenges_alert' ), 30 );
			}
		}
		return;
	}

	/*
	* Called in the footer if was apllied the email link to expired (and deleted) challenge
	*/
	function show_no_challenges_alert() {
		echo '<script>alert("The challenge is expired or has been deleted.")</script>';
	}


	/**
	 * Scheduling challenge from piramid-public.js
	 * Called by ajax
	 *
	 * @return
	 */
	function schedule_challenge() {
		$response = array(
			'errors'  => array(),
			'success' => false,
		);
		$res      = false;

		$this->authorize_challenge_request( $response, 'schedule_challenge_nonce', 'schedule_challenge' );

		$challenge_id = isset( $_POST['challenge_id'] ) ? absint( $_POST['challenge_id'] ) : 0;
		$court_id     = isset( $_POST['cr_game']['court_id'] ) ? absint( $_POST['cr_game']['court_id'] ) : 0;

		$start_ts = false;
		if ( isset( $_POST['cr_game']['date'], $_POST['cr_game']['time']['h'], $_POST['cr_game']['time']['m'] ) && $_POST['cr_game']['date'] !== '' && $_POST['cr_game']['time']['h'] !== '' && $_POST['cr_game']['time']['m'] !== '' ) {
			$game_date = sanitize_text_field( wp_unslash( $_POST['cr_game']['date'] ) );
			$game_hour = absint( $_POST['cr_game']['time']['h'] );
			$game_min  = absint( $_POST['cr_game']['time']['m'] );
			$datetime  = sprintf( '%s %02d:%02d', $game_date, $game_hour, $game_min );
			$start_ts = strtotime( $datetime );
		}
		if ( ! $challenge_id ) {
			$this->challenge_ajax_error( $response, __( 'Challenge id is not received', 'court-reservation' ) );
		}
		list( $challenges_class, $challenge ) = $this->load_challenge_for_user( $challenge_id, $response, 'participant' );
		if ( empty( $challenge['status'] ) || 'accepted' !== $challenge['status'] ) {
			$this->challenge_ajax_error( $response, __( 'Error updating the challenge', 'court-reservation' ) );
		}
		if ( ! $start_ts ) {
			$response['errors'][] = __( 'Start of game date and time is not received', 'court-reservation' );
			echo wp_json_encode( $response );
			wp_die();
		}
		$start_ar    = array(
			'h' => isset( $game_hour ) ? $game_hour : 0,
			'm' => isset( $game_min ) ? $game_min : 0,
		);
		$pyramid     = Courtres_Entity_Piramid::get_by_id( absint( $challenge['piramid_id'] ) );
		if ( is_array( $pyramid ) && ! empty( $pyramid['duration_ts'] ) ) {
			$duration_ts = absint( $pyramid['duration_ts'] );
		} else {
			$duration_ts = isset( $_POST['duration_ts'] ) ? min( 8 * HOUR_IN_SECONDS, absint( $_POST['duration_ts'] ) ) : 0;
		}
		$end_ts      = $start_ts + $duration_ts;
		$end_h_dec   = $start_ar['h'] + $start_ar['m'] / 60 + $duration_ts / 3600;

		// compare start and end of game with the opening hours of the court
		$courtres_public = new Courtres_Public( $this->plugin_name, $this->version );
		$court           = $courtres_public->getCourtByID( $court_id );
		if ( ! $court ) {
			$this->challenge_ajax_error( $response, __( 'Court id is not received', 'court-reservation' ) );
		}
		if ( $start_ar['h'] < $court->open ) {
			$response['errors'][] = __( 'The start of the game cannot be ealier than the opening hours of the court', 'court-reservation' );
			echo wp_json_encode( $response );
			wp_die();
		}
		if ( $end_h_dec > $court->close ) {
			$response['errors'][] = __( 'The end of the game cannot be later than the closing times of the court', 'court-reservation' );
			echo wp_json_encode( $response );
			wp_die();
		}

		$challenger_user = get_user_by( 'id', (int) $challenge['challenger_id'] );
		$challenged_user = get_user_by( 'id', (int) $challenge['challenged_id'] );
		$challenger_name = ( $challenger_user && ! empty( $challenger_user->display_name ) ) ? $challenger_user->display_name : 'Player 1';
		$challenged_name = ( $challenged_user && ! empty( $challenged_user->display_name ) ) ? $challenged_user->display_name : 'Player 2';

		// first: create event to make a court reservation
		$courtres_admin = new Courtres_Admin( $this->plugin_name, $this->version );
		$result         = $courtres_admin->create_event(
			array(
				'name'       => __( 'Challenge', 'court-reservation' ) . ': ' . $challenger_name . ' vs. ' . $challenged_name, // "Challenge " . $challenge_id,
				'court_id'   => $court_id,
				'event_date' => isset( $game_date ) ? $game_date : '',
				'start'      => array(
					'h' => $start_ar['h'],
					'm' => $start_ar['m'],
				),
				'end'        => array(
					'h' => (int) date_i18n( 'H', $end_ts ),
					'm' => (int) date_i18n( 'i', $end_ts ),
				),
				'check_all'  => false,
				'type'       => 'challenge',
			)
		);

		if ( $result['errors'] ) {
			if ( key_exists( 'overlaps', $result['errors'] ) ) {
				$result['errors'][] = __( 'The court is already reserved at that time. Please find another time', 'court-reservation' );
				unset( $result['errors']['overlaps'] );
			}
			$response['errors'] = array_merge( $response['errors'], $result['errors'] );
			echo wp_json_encode( $response );
			wp_die();
		}

		// second: update challenge data
		$args                = array(
			'id'       => $challenge_id,
			'court_id' => $court_id,
			'event_id' => $result['success']['event_id'],
			'start_ts' => $start_ts,
			'end_ts'   => $end_ts,
			'status'   => 'scheduled',
		);
		$res              = $challenges_class->update(
			array(
				'data'         => $args,
				'where'        => array( 'id' => $challenge_id ),
				'format'       => array( '%d', '%d', '%d', '%d', '%d', '%s' ),
				'where_format' => array( '%d' ),
			)
		);

		if ( $res ) {
			$response['success'] = true;
		} else {
			$response['errors'][] = __( 'Error updating the challenge', 'vaa' );
		}

		echo wp_json_encode( $response );
		wp_die();
	}


	/**
	 * Deleting challenge from piramid-public.js
	 * Called by ajax
	 */
	function delete_challenge() {
		$response = array(
			'errors'  => array(),
			'success' => false,
		);

		$this->authorize_challenge_request( $response, 'delete_nonce', 'delete_nonce' );

		$challenge_id = isset( $_POST['challenge_id'] ) ? absint( $_POST['challenge_id'] ) : 0;
		if ( ! $challenge_id ) {
			$this->challenge_ajax_error( $response, __( 'Challenge id is not received', 'court-reservation' ) );
		}

		list( $challenges_class, $challenge ) = $this->load_challenge_for_user( $challenge_id, $response, 'participant' );
		$status = isset( $challenge['status'] ) ? $challenge['status'] : '';
		if ( ! in_array( $status, array( 'accepted', 'scheduled' ), true ) ) {
			$this->challenge_ajax_error( $response, __( 'Error deleting the challenge', 'court-reservation' ) );
		}

		$event_id = ( 'scheduled' === $status ) ? absint( $challenges_class->get_event_id() ) : 0;

		$res = $challenges_class->delete_by_id();
		if ( ! $res ) {
			$this->challenge_ajax_error( $response, __( 'Error deleting the challenge', 'court-reservation' ) );
		}

		// to delete linked event for scheduled challenges
		if ( $event_id ) {
			global $wpdb;
			$deleted = $wpdb->delete( $this->getTable( 'events' ), array( 'id' => $event_id ), array( '%d' ) );
			if ( ! $deleted ) {
				$this->challenge_ajax_error( $response, __( 'No one challenge event deleted', 'court-reservation' ) );
			}
		}

		$response['success'] = true;
		echo wp_json_encode( $response );
		wp_die();
	}


	/**
	 * Entering challenge result from piramid-public.js
	 * Called by ajax
	 *
	 * @return
	 */
	function enter_challenge_result() {
		$response = array(
			'errors'  => array(),
			'success' => false,
		);
		$res      = false;

		$this->authorize_challenge_request( $response, 'enter_results_nonce', 'enter_results' );
		$challenge_id = isset( $_POST['challenge_id'] ) ? absint( $_POST['challenge_id'] ) : 0;
		if ( ! $challenge_id ) {
			$this->challenge_ajax_error( $response, __( 'Challenge id is not received', 'court-reservation' ) );
		}

		list( $challenges_class, $challenge ) = $this->load_challenge_for_user( $challenge_id, $response, 'participant' );
		if ( empty( $challenge['status'] ) || 'played' !== $challenge['status'] ) {
			$this->challenge_ajax_error( $response, __( 'Error enter the challenge results', 'court-reservation' ) );
		}

		$winner_id = isset( $_POST['cr_results']['winner'] ) ? absint( $_POST['cr_results']['winner'] ) : 0;
		$allowed_winners = array( (int) $challenge['challenger_id'], (int) $challenge['challenged_id'] );
		if ( ! $winner_id || ! in_array( $winner_id, $allowed_winners, true ) ) {
			$this->challenge_ajax_error( $response, __( 'Winner is undefined', 'court-reservation' ) );
		}

		$results_str = false;
		if ( isset( $_POST['cr_results']['sets'] ) && is_array( $_POST['cr_results']['sets'] ) ) {
			$results_str_ = array();
			foreach ( $_POST['cr_results']['sets'] as $result_key => $result_san ) {
				$key = absint( $result_key );
				if ( is_array( $result_san ) ) {
					$clean = array();
					foreach ( $result_san as $player_id => $points ) {
						$player_id = absint( $player_id );
						if ( ! in_array( $player_id, $allowed_winners, true ) ) {
							continue;
						}
						$clean[ $player_id ] = sanitize_text_field( wp_unslash( $points ) );
					}
					$results_str_[ $key ] = $clean;
				} else {
					$results_str_[ $key ] = sanitize_text_field( wp_unslash( $result_san ) );
				}
			}
			$results_str = $results_str_ ? serialize( $results_str_ ) : false;
		}

		if ( ! $results_str ) {
			$this->challenge_ajax_error( $response, __( 'Games result is undefined', 'court-reservation' ) );
		}

		// update challenge data
		$args = array(
			'id'        => $challenge_id,
			'winner_id' => $winner_id,
			'results'   => $results_str,
			'status'    => 'closed',
			'closed_dt' => date_i18n( 'Y-m-d H:i:s' ),
		);

		$res = $challenges_class->update(
			array(
				'data'         => $args,
				'where'        => array( 'id' => $challenge_id ),
				'format'       => array( '%d', '%d', '%s', '%s', '%s' ),
				'where_format' => array( '%d' ),
			)
		);

		if ( $winner_id === (int) $challenge['challenger_id'] ) {
			// re-order the piramid
			Courtres_Entity_Piramids_Players::reorder( $challenge['piramid_id'], $challenge['challenged_id'], $challenge['challenger_id'] );
		}

		if ( $res ) {
			$response['success'] = true;
		} else {
			$response['errors'][] = __( 'Error enter the challenge results', 'vaa' );
		}

		echo wp_json_encode( $response );
		wp_die();
	}

	// 2021-03-14, astoian - if allow to show more the one court, if someone created directly in DB
	public function isCourtUltimate() {
		 // false - premium or higher, true - plan name exactly
		if ( ! cr_fs()->is_plan_or_trial( 'ultimate', false ) ) {
			return false;
		}
		return true;
	}

}
