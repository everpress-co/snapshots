<?php
/**
 * Plugin Name:     Snapshots
 * Plugin URI:
 * Description:     Quickly Create SnapShots of your development sites and restore them with a click.
 * Author:          EverPress
 * Author URI:      https://xaver.dev
 * Text Domain:     snapshots
 * Version:         3.0.0
 *
 * @package Snapshots
 */

namespace EverPress\Snapshots;

use WP_CLI;

if ( version_compare( PHP_VERSION, '7.0' ) < 0 ) {
	return;
}

define( 'SNAPSHOTS_FILE', __FILE__ );

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/settings.php';

Settings::get_instance();

if ( defined( 'WP_CLI' ) && WP_CLI ) :

	require_once __DIR__ . '/includes/cli.php';

	WP_CLI::add_command( 'snapshot', 'EverPress\Snapshots\CLI_Command' );

else :

	require_once __DIR__ . '/includes/plugin.php';
	require_once __DIR__ . '/includes/upgrade.php';

	Plugin::get_instance();
	Upgrade::get_instance();

endif;
