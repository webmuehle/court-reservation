<?php
/**
 * Freemius stub for WordPress.org build.
 * Replaces Freemius SDK when submitting to WordPress.org (custom updaters not permitted).
 * Premium build uses full Freemius SDK; this stub is for the free .org version only.
 *
 * @package Courtres
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Courtres_Freemius_Stub {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function is_plan( $plan ) {
		return false;
	}

	public function is_plan_or_trial( $plan, $exact = false ) {
		return false;
	}

	public function set_basename( $bool, $path ) {
	}

	public function add_action( $hook, $callback ) {
	}
}
