<?php
/**
 * Plugin upgrade routines run after a version update.
 *
 * @package Snapshots
 */

namespace EverPress\Snapshots;

/**
 * Runs incremental upgrade callbacks when the plugin is updated.
 *
 * @since 2.7.0
 */
class Upgrade {

	/**
	 * Singleton instance.
	 *
	 * @since 2.7.0
	 *
	 * @var Upgrade|null
	 */
	private static $instance = null;

	/**
	 * Plugin version before the in-progress update.
	 *
	 * @since 2.7.0
	 *
	 * @var string|null
	 */
	private $from;

	/**
	 * Plugin version after the in-progress update.
	 *
	 * @since 2.7.0
	 *
	 * @var string|null
	 */
	private $to;

	/**
	 * Registers upgrade hooks.
	 *
	 * @since 2.7.0
	 */
	private function __construct() {

		add_filter( 'upgrader_pre_install', array( $this, 'upgrader_pre_install' ), 10, 2 );
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @since 2.7.0
	 *
	 * @return Upgrade
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Upgrade();
		}

		return self::$instance;
	}

	/**
	 * Captures the installed version before this plugin is upgraded.
	 *
	 * @since 2.7.0
	 *
	 * @param bool|WP_Error $res        Installation result from the upgrader.
	 * @param array         $hook_extra Extra arguments passed to the upgrader hook.
	 * @return bool|WP_Error Unchanged installation result.
	 */
	public function upgrader_pre_install( $res, $hook_extra ) {

		// Store the current version of the plugin before the update kicks in.
		if ( $res && isset( $hook_extra['plugin'] ) ) {
			if ( plugin_basename( SNAPSHOTS_FILE ) === $hook_extra['plugin'] ) {
				$plugin_data = get_plugin_data( SNAPSHOTS_FILE, false, false );
				$this->from  = $plugin_data['Version'];
				add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );
			}
		}
		return $res;
	}

	/**
	 * Runs version-specific upgrade callbacks after a successful install.
	 *
	 * @since 2.7.0
	 *
	 * @param bool|WP_Error $res        Installation result from the upgrader.
	 * @param array         $hook_extra Extra arguments passed to the upgrader hook.
	 * @param array         $result     Installation result details.
	 * @return bool|WP_Error Unchanged installation result.
	 */
	public function upgrader_post_install( $res, $hook_extra, $result ) {

		// On success run the delta updates.
		if ( $res && isset( $hook_extra['plugin'] ) ) {
			if ( plugin_basename( SNAPSHOTS_FILE ) === $hook_extra['plugin'] ) {
				$plugin_data = get_plugin_data( SNAPSHOTS_FILE, false, false );
				$this->to    = $plugin_data['Version'];

				$this->run();

			}
		}

		return $res;
	}

	/**
	 * Invokes private update_* methods whose version falls between from and to.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	private function run() {
		$class   = new \ReflectionClass( $this );
		$methods = $class->getMethods( \ReflectionMethod::IS_PRIVATE );

		$updates = wp_list_pluck( $methods, 'name' );
		$updates = preg_grep( '/^update_([\d_+])/', $updates );

		foreach ( $updates as $update_method ) {

			// Get version number from method.
			$version = str_replace( 'update_', '', $update_method );
			$version = str_replace( '_', '.', $version );

			// Call method only if needed.
			if ( version_compare( $version, $this->from, '>' ) && version_compare( $version, $this->to, '<=' ) ) {
				call_user_func( array( $this, $update_method ) );
			}
		}
	}

	/**
	 * Placeholder upgrade routine for version 0.1.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	private function update_0_1() {
		error_log( print_r( __FUNCTION__, true ) );
	}
}
