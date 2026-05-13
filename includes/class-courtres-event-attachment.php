<?php
/**
 * Event attachment (Dazuhängen) storage helpers.
 *
 * @package Courtres
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists per-occurrence participant rows for events.
 */
final class Courtres_Event_Attachment {

	/**
	 * Full DB table name including prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'courtres_event_attachments';
	}

	/**
	 * Count participants for one calendar occurrence.
	 *
	 * @param int    $event_id        Event id (courtres_events.id).
	 * @param string $occurrence_date Y-m-d.
	 * @return int
	 */
	public static function count_for_occurrence( $event_id, $occurrence_date ) {
		global $wpdb;
		$t   = self::table_name();
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE event_id = %d AND occurrence_date = %s",
			absint( $event_id ),
			$occurrence_date
		);
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Whether user is attached for this occurrence.
	 *
	 * @param int    $event_id        Event id.
	 * @param string $occurrence_date Y-m-d.
	 * @param int    $user_id         User id.
	 * @return bool
	 */
	public static function is_user_attached( $event_id, $occurrence_date, $user_id ) {
		global $wpdb;
		$t   = self::table_name();
		$sql = $wpdb->prepare(
			"SELECT 1 FROM {$t} WHERE event_id = %d AND occurrence_date = %s AND user_id = %d LIMIT 1",
			absint( $event_id ),
			$occurrence_date,
			absint( $user_id )
		);
		return (bool) $wpdb->get_var( $sql );
	}

	/**
	 * Fetch attachment rows for one occurrence.
	 *
	 * @param int    $event_id        Event id.
	 * @param string $occurrence_date Y-m-d.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_rows( $event_id, $occurrence_date ) {
		global $wpdb;
		$t   = self::table_name();
		$sql = $wpdb->prepare(
			"SELECT user_id, created_at FROM {$t} WHERE event_id = %d AND occurrence_date = %s ORDER BY created_at ASC, id ASC",
			absint( $event_id ),
			$occurrence_date
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete all rows for an event (e.g. when event deleted).
	 *
	 * @param int $event_id Event id.
	 * @return bool
	 */
	public static function delete_all_for_event( $event_id ) {
		global $wpdb;
		$del = $wpdb->delete(
			self::table_name(),
			array( 'event_id' => absint( $event_id ) ),
			array( '%d' )
		);
		return false !== $del;
	}

	/**
	 * Insert participant row.
	 *
	 * @param int    $event_id        Event id.
	 * @param string $occurrence_date Y-m-d.
	 * @param int    $user_id         User id.
	 * @return bool|int False on failure, insert id on success.
	 */
	public static function insert_row( $event_id, $occurrence_date, $user_id ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'event_id'        => absint( $event_id ),
				'occurrence_date' => $occurrence_date,
				'user_id'         => absint( $user_id ),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s' )
		);
		if ( ! $ok ) {
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Remove participant row.
	 *
	 * @param int    $event_id        Event id.
	 * @param string $occurrence_date Y-m-d.
	 * @param int    $user_id         User id.
	 * @return bool
	 */
	public static function delete_row( $event_id, $occurrence_date, $user_id ) {
		global $wpdb;
		$del = $wpdb->delete(
			self::table_name(),
			array(
				'event_id'        => absint( $event_id ),
				'occurrence_date' => $occurrence_date,
				'user_id'         => absint( $user_id ),
			),
			array( '%d', '%s', '%d' )
		);
		return false !== $del && $del > 0;
	}
}
