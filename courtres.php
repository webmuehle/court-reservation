<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.webmuehle.at
 * @since             1.0.5
 * @package           Courtres
 *
 * @wordpress-plugin
 * Plugin Name:       Court Reservation
 * Plugin URI:        https://www.courtreservation.io
 * Description:       Reservation system for tennis, squash and badminton
 * Version:           1.12.3
 * Author:            Webmühle e.U.
 * Author URI:        https://www.webmuehle.at
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       courtres
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Auto deactivation of free when premium is active (premium defines cr_fs first)
if ( function_exists( 'cr_fs' ) ) {
	cr_fs()->set_basename( true, __FILE__ );
	return;
}

// Freemius SDK integration (falls back to a stub for WordPress.org builds).
if ( ! function_exists( 'cr_fs' ) ) {
	$cr_freemius_sdk_path = dirname( __FILE__ ) . '/freemius/start.php';
	if ( file_exists( $cr_freemius_sdk_path ) ) {
		require_once $cr_freemius_sdk_path;

		function cr_fs() {
			global $cr_fs;

			if ( ! isset( $cr_fs ) ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
				$cr_fs = fs_dynamic_init(
					array(
						'id'              => '3086',
						'slug'            => 'court-reservation',
						'type'            => 'plugin',
						'public_key'      => 'pk_b5c504d97853f6130b63fd7344155',
						'is_premium'      => false,
						'has_addons'      => false,
						'has_paid_plans'  => true,
						'menu'            => array(
							'slug' => 'courtres',
						),
					)
				);
			}

			return $cr_fs;
		}

		cr_fs();
		do_action( 'cr_fs_loaded' );
	} else {
		require_once dirname( __FILE__ ) . '/includes/class-courtres-freemius-stub.php';
		function cr_fs() {
			return Courtres_Freemius_Stub::instance();
		}
	}
}

/**
 * Currently plugin version.
 * Start at version 1.0.4 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'Court_Reservation', '1.12.3' );

require_once plugin_dir_path( __FILE__ ) . 'functions.php';

/**
 * The core plugin class
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/entity/base.php';

/**
 * The core plugin class to work with piramid table
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/entity/piramid.php';
/**
 * The core plugin class to work with piramids-players relations table
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/entity/piramids-players.php';
/**
 * The core plugin class to work with piramid-challenges relations table
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/entity/challenges.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-courtres-activator.php
 */
if ( ! function_exists( 'activate_courtres' ) ) {
	function activate_courtres() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-courtres-activator.php';
		Courtres_Activator::activate();
	}
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-courtres-deactivator.php
 */
if ( ! function_exists( 'deactivate_courtres' ) ) {
	function deactivate_courtres() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-courtres-deactivator.php';
		Courtres_Deactivator::deactivate();
	}
}

/**
 * The code that runs during plugin unistall.
 * This action is documented in includes/class-courtres-unstaller.php
 */
if ( ! function_exists( 'uninstall_courtres' ) ) {
	function uninstall_courtres() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-courtres-uninstaller.php';
		Courtres_Uninstaller::uninstall();
	}
}

register_activation_hook( __FILE__, 'activate_courtres' );
register_deactivation_hook( __FILE__, 'deactivate_courtres' ); // 23.05.2019, astoian - doesnot remove tables on deactivation
register_uninstall_hook( __FILE__, 'uninstall_courtres' ); // 23.05.2019, astoian - remove all tables on uninstall

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-courtres.php';

/**
 * The core plugin class that is base for public and admin classes
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-courtres-base.php';


/**
 * The core plugin class for notifications by emails
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-courtres-notices.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.5
 */
function run_courtres() {
	$plugin = new Courtres();
	$plugin->run();
}
run_courtres();
