<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://webmuehle.at
 * @since      1.0.3
 *
 * @package    Courtres
 * @subpackage Courtres/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.3
 * @package    Courtres
 * @subpackage Courtres/includes
 * @author     Webmühle e.U. <office@webmuehle.at>
 */
class Courtres_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.3
	 */
	public function load_plugin_textdomain() {
		$domain_path = dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/';

		load_plugin_textdomain(
			'court-reservation',
			false,
			$domain_path
		);

		// Some strings use the "courtres" text domain; reuse the same translation files.
		add_filter(
			'load_textdomain_mofile',
			function ( $mofile, $domain ) {
				if ( 'courtres' === $domain ) {
					$mofile = str_replace( 'courtres-', 'court-reservation-', $mofile );
				}
				return $mofile;
			},
			10,
			2
		);

		load_plugin_textdomain(
			'courtres',
			false,
			$domain_path
		);
	}



}
