<?php
/**
 * Query-shape checks for entity list, count, and delete helpers.
 *
 * Run: php tests/entity-queries.php
 */

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'ARRAY_N', 'ARRAY_N' );
	define( 'OBJECT', 'OBJECT' );
	define( 'OBJECT_K', 'OBJECT_K' );
}

function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

function wp_parse_args( $args, $defaults = array() ) {
	if ( ! is_array( $args ) ) {
		$args = array();
	}
	return array_merge( $defaults, $args );
}

function wp_list_pluck( $list, $field ) {
	$out = array();
	foreach ( (array) $list as $item ) {
		if ( is_array( $item ) && isset( $item[ $field ] ) ) {
			$out[] = $item[ $field ];
		} elseif ( is_object( $item ) && isset( $item->$field ) ) {
			$out[] = $item->$field;
		}
	}
	return $out;
}

function get_users( $args = array() ) {
	return array();
}

function current_time( $type ) {
	return 1700000000;
}

class EntityQueryCapture {
	public $prefix       = 'wp_';
	public $queries      = array();
	public $result_queue = array();

	public function prepare( $query, $args = array() ) {
		$values = ( is_array( $args ) && func_num_args() === 2 ) ? $args : array_slice( func_get_args(), 1 );
		$query  = str_replace( array( "'%s'", '"%s"' ), '%s', $query );
		$query  = preg_replace( '|(?<!%)%s|', "'%s'", $query );
		foreach ( $values as &$value ) {
			if ( is_float( $value ) ) {
				continue;
			}
			$value = addslashes( (string) $value );
		}
		unset( $value );
		$prepared = vsprintf( $query, $values );
		if ( ! is_string( $prepared ) ) {
			throw new RuntimeException( 'prepare failed for ' . $query );
		}
		return $prepared;
	}

	public function get_results( $query, $output = OBJECT ) {
		$this->queries[] = $query;
		if ( $this->result_queue ) {
			return array_shift( $this->result_queue );
		}
		return array();
	}

	public function get_row( $query ) {
		$this->queries[] = $query;
		return (object) array( 'cnt' => 0 );
	}

	public function query( $query ) {
		$this->queries[] = $query;
		return 1;
	}

	public function reset() {
		$this->queries      = array();
		$this->result_queue = array();
	}
}

$wpdb = new EntityQueryCapture();

require dirname( __DIR__ ) . '/includes/entity/base.php';
require dirname( __DIR__ ) . '/includes/entity/piramid.php';
require dirname( __DIR__ ) . '/includes/entity/piramids-players.php';
require dirname( __DIR__ ) . '/includes/entity/challenges.php';

$failures = 0;

function check( $label, $condition, $detail = '' ) {
	global $failures;
	if ( $condition ) {
		echo "ok  $label\n";
		return;
	}
	$failures++;
	echo "FAIL $label" . ( $detail ? " — $detail" : '' ) . "\n";
}

function assert_text_absent( $label, $sql, $text ) {
	check( $label . ' drops extra text', false === strpos( $sql, $text ), $sql );
}

$extra_id = '0 not-a-number';

$wpdb->reset();
Courtres_Entity_Challenges::get_by_id( $extra_id );
$select = $wpdb->queries[0];
assert_text_absent( 'get_by_id extra text', $select, 'not-a-number' );
check( 'get_by_id extra text binds 0', (bool) preg_match( '/\bID\s*=\s*0\b/', $select ), $select );
check( 'get_by_id extra text keeps limit', (bool) preg_match( '/LIMIT\s+1000\s+OFFSET\s+0/', $select ), $select );

$wpdb->reset();
Courtres_Entity_Challenges::get_by_id( 42 );
$select = $wpdb->queries[0];
check( 'get_by_id 42', (bool) preg_match( '/\bID\s*=\s*42\b/', $select ), $select );
check( 'get_by_id 42 count', (bool) preg_match( '/COUNT\(\*\)/', $wpdb->queries[1] ), $wpdb->queries[1] );

$wpdb->reset();
Courtres_Entity_Challenges::get_list(
	array(
		'where' => array(
			'conditions' => array( 'ID = ' . $extra_id ),
		),
	)
);
assert_text_absent( 'legacy concatenated id', $wpdb->queries[0], 'not-a-number' );
check( 'legacy concatenated id matches nothing', false !== strpos( $wpdb->queries[0], '1 = 0' ), $wpdb->queries[0] );

$wpdb->reset();
Courtres_Entity_Piramids_Players::get_by_piramid_id( 7 );
$select = $wpdb->queries[0];
check( 'players by pyramid id', (bool) preg_match( '/`piramid_id`\s*=\s*7\b/', $select ) && (bool) preg_match( '/`is_active`\s*=\s*1\b/', $select ), $select );
check( 'players sort', false !== strpos( $select, 'sort ASC' ), $select );

$wpdb->reset();
Courtres_Entity_Piramids_Players::get_list(
	array(
		'where' => array(
			'conditions' => array( '`piramid_id` = ' . $extra_id, '`is_active` = 1' ),
		),
	)
);
assert_text_absent( 'legacy player id', $wpdb->queries[0], 'not-a-number' );

$wpdb->reset();
Courtres_Entity_Challenges::get_by_statuses( 4, array( 'played', 'closed' ) );
$select = $wpdb->queries[0];
check( 'statuses bind pyramid', (bool) preg_match( '/`piramid_id`\s*=\s*4\b/', $select ), $select );
check( 'statuses bind played', false !== strpos( $select, "'played'" ), $select );
check( 'statuses bind closed', false !== strpos( $select, "'closed'" ), $select );
check( 'statuses use OR', false !== stripos( $select, ' OR ' ), $select );

$wpdb->reset();
Courtres_Entity_Challenges::get_by_statuses( 4, array( "o'brien" ) );
$select = $wpdb->queries[0];
assert_text_absent( 'status text', $select, "o'brien" );
check( 'status text stays quoted', false !== strpos( $select, "'o\\'brien'" ), $select );

$wpdb->reset();
Courtres_Entity_Challenges::get_list(
	array(
		'sort'  => 'end_ts unexpected',
		'limit' => '3 extra',
	)
);
$select = $wpdb->queries[0];
check( 'sort fallback', false !== strpos( $select, '`id` DESC' ), $select );
check( 'limit cast', (bool) preg_match( '/LIMIT\s+3\s+OFFSET\s+0/', $select ), $select );
assert_text_absent( 'sort and limit', $select, 'unexpected' );

$wpdb->reset();
Courtres_Entity_Challenges::user_can_accept( 15 );
$count = $wpdb->queries[0];
check( 'user_can_accept id', (bool) preg_match( '/challenged_id`?\s*=\s*15\b/', $count ), $count );
check( 'user_can_accept status', false !== strpos( $count, "'created'" ), $count );
check( 'user_can_accept active', (bool) preg_match( '/is_active`?\s*=\s*1\b/', $count ), $count );

$wpdb->reset();
Courtres_Entity_Challenges::is_player_already_challenger( 2, 9 );
check( 'challenger count', (bool) preg_match( '/`piramid_id`\s*=\s*2\b/', $wpdb->queries[0] ) && (bool) preg_match( '/`challenger_id`\s*=\s*9\b/', $wpdb->queries[0] ), $wpdb->queries[0] );

$wpdb->reset();
Courtres_Entity_Challenges::delete_created_expired( 86400 );
$delete = $wpdb->queries[0];
check( 'delete created status', false !== strpos( $delete, "'created'" ), $delete );
check( 'delete created interval', false !== strpos( $delete, 'INTERVAL 86400 SECOND' ), $delete );
assert_text_absent( 'delete created', $delete, 'not-a-number' );

$wpdb->reset();
Courtres_Entity_Challenges::delete( array( 'id' => "5'x", 'status' => 'created' ), 'AND' );
$delete = $wpdb->queries[0];
assert_text_absent( 'delete key values', $delete, "5'x" );
check( 'delete key values stay quoted', false !== strpos( $delete, "'5\\'x'" ), $delete );
check( 'delete key status', false !== strpos( $delete, "'created'" ), $delete );

$wpdb->reset();
$challenge = Courtres_Entity_Challenges::get_instance( $extra_id );
check( 'instance id cast', 0 === $challenge->get_id(), (string) $challenge->get_id() );
assert_text_absent( 'instance lookup', $wpdb->queries[0], 'not-a-number' );

$wpdb->reset();
$wpdb->result_queue = array(
	array(
		array(
			'id'            => 8,
			'piramid_id'    => 4,
			'challenger_id' => 10,
			'challenged_id' => 11,
			'winner_id'     => 0,
		),
	),
);
Courtres_Entity_Challenges::get_challenges();
$player_query = $wpdb->queries[2];
check( 'player in list', (bool) preg_match( '/`player_id`\s+IN\s*\(\s*10\s*,\s*11\s*\)/', $player_query ), $player_query );
check( 'player pyramid id', (bool) preg_match( '/`piramid_id`\s*=\s*4\b/', $player_query ), $player_query );

$wpdb->reset();
$wpdb->result_queue = array(
	array(
		array(
			'id'          => 3,
			'locktime_ts' => 3600,
			'name'        => 'Pyramid',
		),
	),
);
Courtres_Entity_Challenges::get_closed_challenges_for_player( 3, 10, 12 );
$closed = '';
foreach ( $wpdb->queries as $query ) {
	if ( false !== strpos( $query, 'closed_dt' ) ) {
		$closed = $query;
	}
}
check( 'closed lock interval', false !== strpos( $closed, 'INTERVAL 3600 SECOND' ), $closed );
check( 'closed challenger', (bool) preg_match( '/`challenger_id`\s*=\s*10\b/', $closed ), $closed );
check( 'closed challenged', (bool) preg_match( '/`challenged_id`\s*=\s*12\b/', $closed ), $closed );
assert_text_absent( 'closed challenges', $closed, 'not-a-number' );

$wpdb->reset();
Courtres_Entity_Piramids_Players::count(
	array(
		'conditions' => array(
			'`piramid_id` = 5',
			'`player_id` = 6',
			'`sort` = 2',
		),
	)
);
$count = $wpdb->queries[0];
check( 'legacy count still filters', (bool) preg_match( '/`piramid_id`\s*=\s*5\b/', $count ) && (bool) preg_match( '/`player_id`\s*=\s*6\b/', $count ) && (bool) preg_match( '/`sort`\s*=\s*2\b/', $count ), $count );

if ( $failures ) {
	echo "\n$failures check(s) failed\n";
	exit( 1 );
}

echo "\nentity query checks ok\n";
exit( 0 );
