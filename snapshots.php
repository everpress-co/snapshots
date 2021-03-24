<?php
/**
Plugin Name:     Snapshots
Plugin URI:
Description:     Quickly Create SnapShots of your development sites and restore them with a click.
Author:          EverPress
Author URI:      https://about.me/xaver
Text Domain:     snapshots
Version:         2.3
 */

define( 'SNAPSHOTS_FILE', __FILE__ );


if ( ! defined( 'SNAPSHOTS_FOLDER' ) ) {
	define( 'SNAPSHOTS_FOLDER', WP_CONTENT_DIR . '/.snapshots' );
}

if ( ! defined( 'SNAPSHOTS_CLI_ALLOW_ROOT' ) ) {
	define( 'SNAPSHOTS_CLI_ALLOW_ROOT', false );
}

if ( ! defined( 'SNAPSHOTS_CLI_PATH' ) ) {
	define( 'SNAPSHOTS_CLI_PATH', '/usr/local/bin' );
}

if ( ! defined( 'SNAPSHOTS_MAX_SHOTS' ) ) {
	define( 'SNAPSHOTS_MAX_SHOTS', 2 );
}

if ( ! defined( 'SNAPSHOTS_SAVE_FILES' ) ) {
	define( 'SNAPSHOTS_SAVE_FILES', true );
}

if ( ! defined( 'SNAPSHOTS_SAVE_LOCATION' ) ) {
	define( 'SNAPSHOTS_SAVE_LOCATION', true );
}

require_once dirname( __FILE__ ) . '/common.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) :

	require_once dirname( __FILE__ ) . '/cli.php';

	WP_CLI::add_command( 'snapshot', 'Snapshots' );

else :

	require_once dirname( __FILE__ ) . '/plugin.php';

	new Snapshots_Plugin();

endif;
