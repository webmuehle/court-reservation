<?php
/**
 * Rank rule used by the pyramid buttons, checked without WordPress.
 */

class Courtres_Entity_Base {}

require __DIR__ . '/../includes/entity/piramids-players.php';

/**
 * Same condition the pyramid template used before the shared helper.
 *
 * @param array $players       Players in display order.
 * @param int   $challenger_id Challenger user id.
 * @return int[]
 */
function courtres_legacy_challengeable_ids( array $players, $challenger_id ) {
	$the_player = null;
	foreach ( $players as $player ) {
		if ( (int) $player['player_id'] === (int) $challenger_id ) {
			$the_player = $player;
			break;
		}
	}
	if ( ! $the_player ) {
		return array();
	}

	$ids     = array();
	$counter = 0;
	$row_len = 1;
	foreach ( $players as $player ) {
		$enabled = $player['player_id'] != $challenger_id
			&& $player['sort'] < $the_player['sort']
			&& $player['sort'] >= $the_player['sort'] - $row_len;
		if ( $enabled ) {
			$ids[] = (int) $player['player_id'];
		}
		$counter++;
		if ( $counter == $row_len ) {
			$counter = 0;
			$row_len++;
		}
	}
	return $ids;
}

/**
 * @param int $count Player count.
 * @return array
 */
function courtres_sample_players( $count ) {
	$players = array();
	for ( $sort = 0; $sort < $count; $sort++ ) {
		$players[] = array(
			'player_id' => (string) ( $sort + 1 ),
			'sort'      => (string) $sort,
		);
	}
	return $players;
}

$failures = 0;
$players  = courtres_sample_players( 10 );

$expected = array(
	0 => array(),
	1 => array( 1 ),
	2 => array( 2 ),
	6 => array( 4, 5, 6 ),
	7 => array( 5, 6, 7 ),
	8 => array( 6, 7, 8 ),
);

foreach ( $expected as $sort => $ids ) {
	$got = Courtres_Entity_Piramids_Players::challengeable_player_ids( $players, $sort + 1 );
	if ( $got !== $ids ) {
		fwrite( STDERR, "sort {$sort} expected " . json_encode( $ids ) . ' got ' . json_encode( $got ) . PHP_EOL );
		$failures++;
	}
}

if ( Courtres_Entity_Piramids_Players::challengeable_player_ids( $players, 99 ) !== array() ) {
	fwrite( STDERR, "unknown challenger should have no targets" . PHP_EOL );
	$failures++;
}

for ( $count = 1; $count <= 15; $count++ ) {
	$sample = courtres_sample_players( $count );
	foreach ( $sample as $player ) {
		$legacy = courtres_legacy_challengeable_ids( $sample, $player['player_id'] );
		$got    = Courtres_Entity_Piramids_Players::challengeable_player_ids( $sample, $player['player_id'] );
		if ( $got !== $legacy ) {
			fwrite( STDERR, "mismatch for {$count} players, challenger {$player['player_id']}" . PHP_EOL );
			$failures++;
		}
	}
}

if ( $failures ) {
	exit( 1 );
}

echo "pyramid rank checks ok\n";
