<?php

/**
 * Fired during plugin uninstall
 *
 * @link       https://webmuehle.at
 * @since      1.1.2
 *
 * @package    Courtres
 * @subpackage Courtres/includes
 */

/**
 * Fired during plugin uninstall.
 *
 * This class defines all code necessary to run during the plugin's uninstall.
 *
 * @since      1.1.2
 * @package    Courtres
 * @subpackage Courtres/includes
 * @author     Webmühle e.U. <office@webmuehle.at>
 */
class Courtres_Uninstaller {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.1.2
	 */
	public static function uninstall() {
		remove_role( 'player' );
		remove_role( 'guest_player' );

		$role = get_role( 'administrator' );
		$role->remove_cap( 'place_reservation', true );

		global $wpdb;
		$tables = array(
			$wpdb->prefix . 'courtres_settings',
			$wpdb->prefix . 'courtres_reservations',
			$wpdb->prefix . 'courtres_event_attachments',
			$wpdb->prefix . 'courtres_events',
			$wpdb->prefix . 'courtres_courts',
		);
		foreach ( $tables as $table_name ) {
			if ( version_compare( get_bloginfo( 'version' ), '6.2', '>=' ) ) {
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
			} else {
				$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table_name ) . '`' );
			}
		}
	}
}
