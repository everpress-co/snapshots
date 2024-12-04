<?php

namespace EverPress\Snapshots;

/**
 * Syncs a local
 *
 * @when after_wp_load
 *
 * @author xaver
 */
class CLI_Command extends \WP_CLI_Command {

	private $name;

	/**
	 * Saves a SnapShot of your current database and content folder
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of your SnapShot.
	 *
	 * [--files]
	 * : Include the content folder as zip file.
	 *
	 * ## EXAMPLES
	 *
	 *     Create a SnapShot with custom name:
	 *        wp snapshot backup "My SnapShot"
	 *     Run snapshot with all files:
	 *        wp snapshot backup "My SnapShot" --files
	 *     Run snapshot with redirection to the settings page after restore:
	 *        wp snapshot backup "My SnapShot" --location="wp-admin/options-general.php"
	 *
	 * ## USAGE
	 *
	 * @subcommand backup
	 * @synopsis [<name>] [--files] [--location=<location>]
	 */
	public function backup( $args, $assoc_args ) {

		do_action( 'snapshots_before_backup', $args, $assoc_args );

		if ( $this->snapshots_create( $args, $assoc_args ) ) {
			do_action( 'snapshots_before_backup', $args, $assoc_args );
			\WP_CLI::success( 'Snapshot saved!' );
		} else {
			\WP_CLI::error( 'Snapshot not saved!' );
		}
	}

	/**
	 * Restores a SnapShot of your current database and content folder
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of your SnapShot.
	 *   *
	 * ## EXAMPLES
	 *
	 *     Restores the latest SnapShot with a given name:
	 *        wp snapshot restore "My SnapShot"
	 *     Restores the latest SnapShot with an id:
	 *        wp snapshot restore my-snapshot
	 *     Restores SnapShot with an id and timestamp:
	 *        wp snapshot restore my-snapshot_1587039434
	 *
	 * ## USAGE
	 *
	 * @subcommand restore
	 * @synopsis [<name>]
	 */
	public function restore( $args, $assoc_args ) {

		do_action( 'snapshots_before_restore', $args, $assoc_args );

		if ( $this->snapshots_restore( $args, $assoc_args ) ) {
			do_action( 'snapshots_before_restore', $args, $assoc_args );

			\WP_CLI::success( 'Snapshot restored!' );

		} else {
			\WP_CLI::error( 'Snapshot not restored!' );
		}
	}

	/**
	 * Deletes a SnapShot of your current database and content folder
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of your SnapShot.
	 *   *
	 * ## EXAMPLES
	 *
	 *     Restores the latest SnapShot with a given name:
	 *        wp snapshot delete "My SnapShot"
	 *     Restores the latest SnapShot with an id:
	 *        wp snapshot delete my-snapshot
	 *     Restores SnapShot with an id and timestamp:
	 *        wp snapshot delete my-snapshot_1587039434
	 *
	 * ## USAGE
	 *
	 * @subcommand delete
	 * @synopsis [<name>]
	 */
	public function delete( $args, $assoc_args ) {

		do_action( 'snapshots_before_delete', $args, $assoc_args );

		if ( $this->snapshots_delete( $args, $assoc_args ) ) {
			do_action( 'snapshots_before_delete', $args, $assoc_args );

			\WP_CLI::success( 'Snapshot deleted!' );

		} else {
			\WP_CLI::error( 'Snapshot not deleted!' );
		}
	}

	/**
	 * Sync to restore site
	 *
	 * @synopsis [<name>] [--format=<format>] [--limit=<limit>]
	 */
	/**
	 * Restores a SnapShot of your current database and content folder
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of your SnapShot.
	 *
	 * [--format]
	 * : Format of output. Allow values ‘table’, ‘json’, ‘csv’, ‘yaml’, ‘ids’, ‘count’
	 *
	 * [--limit]
	 * : maximum of entries returned
	 *
	 * ## USAGE
	 *
	 * @subcommand list
	 * @synopsis [<name>] [--format=<format>] [--limit=<limit>]
	 */
	public function list( $args, $assoc_args ) {

		if ( array_key_exists( 0, $args ) ) {
			$files = $this->get_snapshot_files( $args[0] );
		} else {
			$files = $this->get_snapshot_files();
		}

		if ( empty( $files ) ) {
			\WP_CLI::log( 'No files found!' );
			return;
		}

		$data = array();

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$limit  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', count( $files ) );

		foreach ( $files as $i => $file ) {
			if ( $i >= $limit ) {
				break;
			}

			$manifest = json_decode( file_get_contents( $file . 'manifest.json' ) );

			$name     = $manifest->name;
			$filename = pathinfo( $file, PATHINFO_FILENAME );
			$data[]   = array(
				'name'    => $name,
				'file'    => $filename,
				'created' => wp_date( 'Y-m-d H:i:s', filemtime( $file ) ),
				'past'    => human_time_diff( filemtime( $file ) ) . ' ago',
			);
		}

		\WP_CLI\Utils\format_items( $format, $data, array_keys( $data[0] ) );
	}


	private function check() {
		if ( ! file_exists( snapshots_option( 'folder' ) ) ) {
			return wp_mkdir_p( snapshots_option( 'folder' ) );
		}

		return true;
	}


	private function get_snapshot_files( $name = null ) {

		if ( ! is_dir( snapshots_option( 'folder' ) ) ) {
			return array();
		}

		$backups = list_files( snapshots_option( 'folder' ), 1 );
		$backups = preg_grep( '/([a-z-]+)_(\d+)\/$/', $backups );
		if ( ! is_null( $name ) ) {
			$backups = preg_grep( '/' . preg_quote( $name ) . '_(\d+)\/$/', $backups );
		}
		rsort( $backups );

		usort(
			$backups,
			function ( $a, $b ) {
				return filemtime( $a . 'manifest.json' ) < filemtime( $b . 'manifest.json' );
			}
		);

		return $backups;
	}


	private function snapshots_create( $args, $assoc_args ) {

		if ( ! $this->check() ) {
			exit;
		}

		$name          = $this->get_name( $args );
		$timestamp     = time();
		$snapshot_name = sanitize_title( $name ) . '_' . $timestamp;
		$folder        = trailingslashit( snapshots_option( 'folder' ) ) . $snapshot_name;

		if ( ! is_dir( $folder ) ) {
			wp_mkdir_p( $folder );
		}

		$location = $folder . '/dump.sql';

		$manifest = array(
			'name'    => $name,
			'created' => $timestamp,
		);

		$this->command( 'db export ' . $location );

		if ( ! file_exists( $location ) ) {
			\WP_CLI::error( sprintf( 'No snapshots found for %s', $snapshot_name ) );
		}

		if ( $files = \WP_CLI\Utils\get_flag_value( $assoc_args, 'files', false ) ) {
			$upload_dir = wp_upload_dir();
			$basedir    = $upload_dir['basedir'];
			$zipfile    = $folder . '/data.zip';
			$this->zip( $basedir, $zipfile );
			if ( ! file_exists( $zipfile ) ) {
				\WP_CLI::error( sprintf( 'No able to save zip file %s', $zipfile ) );
			}
		}
		if ( $location = \WP_CLI\Utils\get_flag_value( $assoc_args, 'location', false ) ) {
			$manifest['location'] = $location;
		}
		$manifestfile = $folder . '/manifest.json';

		file_put_contents( $manifestfile, json_encode( $manifest ) );
		if ( ! file_exists( $manifestfile ) ) {
			\WP_CLI::error( sprintf( 'No able to save manifest file %s', $manifestfile ) );
		}

		$this->destroy_snapshots( $snapshot_name );

		set_transient( 'snapshot_current', $name );

		return true;
	}


	private function snapshots_restore( $args, $assoc_args ) {

		if ( ! $this->check() ) {
			exit;
		}

		$snapshot_name = $this->get_name( $args );
		$backup_dir    = false;

		if ( $restore_file = $this->get_most_recent_file( $snapshot_name, 'dump.sql' ) ) {
			$location = $restore_file;
		} else {
			\WP_CLI::error( sprintf( 'No snapshots found for %s', $snapshot_name ) );
		}

		$manifest = $this->get_most_recent_file( $snapshot_name, 'manifest.json' );

		$zip = $this->get_most_recent_file( $snapshot_name, 'data.zip' );

		if ( file_exists( $zip ) ) {

			$upload_dir = wp_upload_dir();
			$backup_dir = $upload_dir['basedir'] . '.' . time();

			if ( $unzip = $this->unzip( $zip, $backup_dir ) ) {
			} else {
				\WP_CLI::error( sprintf( 'Not able to extract uploads directory for %s', $snapshot_name ) );
			}

			$this->delete_folder( $upload_dir['basedir'] );

			// rename old directory (back it up)
			if ( ! rename( $backup_dir, $upload_dir['basedir'] ) ) {
				\WP_CLI::error( sprintf( 'Could not backup upload folder for %s', $snapshot_name ) );
			}
		}

		$this->command( 'db import ' . $location . ' --skip-optimization' );

		$sql_data = file_get_contents( $location );

		// drop all tables who do not belong to this import
		if ( preg_match_all( '/-- Table structure for table `(.*?)`/', $sql_data, $matches ) ) {
			$tables = $matches[1];

			$all_tables = $this->command( 'db tables --all-tables-with-prefix' );
			$to_remove  = array_filter( array_diff( $all_tables, $tables ) );
			if ( ! empty( $to_remove ) ) {
				global $wpdb;
				foreach ( $to_remove as $t ) {
					$wpdb->query( 'DROP TABLE IF EXISTS `' . $t . '`;' );
				}
			}
		}

		// maybe replace the URL if the current one doesn't match the one from the SQL file
		if ( preg_match( "/'home','(https?:\/\/([^']+)?)'/", $sql_data, $match ) ) {
			$sql_home_url = $match[1];
			$home_url     = get_option( 'home' );

			if ( $home_url != $sql_home_url ) {
				$this->command( 'search-replace ' . $sql_home_url . ' ' . $home_url );
			}
		}

		if ( ! function_exists( 'wp_upgrade' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		wp_upgrade();

		if ( file_exists( $manifest ) ) {
			$manifest = json_decode( file_get_contents( $manifest ) );
			if ( isset( $manifest->location ) ) {
				\WP_CLI::line( 'Redirect to: ' . $manifest->location );
			}
			if ( isset( $manifest->name ) ) {
				// don't use 'set_transient' here to prevent race conditions
				global $wpdb;
				$query = $wpdb->prepare( "INSERT INTO {$wpdb->options} SET option_name = '_transient_snapshot_current', option_value = '%s', autoload = 'yes' ON DUPLICATE KEY UPDATE option_value = '%s'", $manifest->name, $manifest->name );
				$this->command( sprintf( 'db query "%s"', $query ) );
			}
		}

		return true;
	}


	private function command( $command, $return = true, $exit_error = false ) {
		$options = array(
			'return'     => true,
			// 'parse'      => 'json',
			'launch'     => false,
			'exit_error' => $exit_error,
		);

		$result = \WP_CLI::runcommand( $command, $options );

		$result = trim( $result );

		if ( strpos( $result, "\n" ) !== false ) {
			$result = explode( "\n", $result );
		}

		if ( $return ) {
			return $result;
		}

		echo $result;
	}


	private function snapshots_delete( $args, $assoc_args ) {

		$snapshot_name = $this->get_name( $args );

		if ( $restore_file = $this->get_most_recent_file( $snapshot_name, 'dump.sql' ) ) {
			$location = dirname( $restore_file );
		} else {
			\WP_CLI::error( sprintf( 'No snapshots found for %s', $snapshot_name ) );
		}

		if ( ! $this->delete_folder( $location ) ) {
			\WP_CLI::error( sprintf( 'No able delete folder %s', $location ) );
		}

		return true;
	}

	private function zip( $folder, $destination = null ) {

		if ( class_exists( 'ZipArchive', false ) && apply_filters( 'unzip_file_use_ziparchive', true ) ) {
			return $this->zip_archive( $folder, $destination );
		}

		return $this->zip_pclzip( $folder, $destination );
	}

	private function zip_archive( $folder, $destination = null ) {
		// Get real path for our folder
		$rootPath = realpath( $folder );

		$zipfile = ! is_null( $destination ) ? $destination : trailingslashit( $rootPath ) . basename( $folder ) . '.zip';

		$zip = new \ZipArchive();
		$zip->open( $zipfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

		$files = list_files( $rootPath, 999 );
		$count = 0;

		foreach ( $files as $file ) {

			$relativePath = substr( $file, strlen( $rootPath ) + 1 );
			if ( is_dir( $file ) ) {
				$zip->addEmptyDir( $relativePath );
				continue;
			}
			$zip->addFile( $file, $relativePath );
			++$count;
		}

		$zip->close();
		return true;
	}

	private function zip_pclzip( $folder, $destination = null ) {
		// Get real path for our folder
		$rootPath = realpath( $folder );

		$zipfile = ! is_null( $destination ) ? $destination : trailingslashit( $rootPath ) . basename( $folder ) . '.zip';

		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		$zip = new \PclZip( $zipfile );

		$files = list_files( $rootPath, 999 );
		$count = 0;

		return $zip->create( $files, '', $rootPath );
	}

	private function unzip( $zipfile, $destination ) {
		WP_Filesystem();
		return unzip_file( $zipfile, $destination );
	}

	private function delete_folder( $target, $recursive = true ) {
		global $wp_filesystem;
		WP_Filesystem();

		$type = is_dir( $target ) ? 'd' : 'f';

		return $wp_filesystem->delete( $target, $recursive, $type );
	}

	private function get_most_recent_file( $backup_name, $extension ) {

		if ( false !== strpos( $backup_name, '_' ) ) {
			return trailingslashit( snapshots_option( 'folder' ) ) . $backup_name . '/' . $extension;
		}

		$backups = $this->get_snaps( $backup_name, true );
		return isset( $backups[0] ) ? trailingslashit( $backups[0] ) . $extension : null;
	}

	public function get_snaps( $name = null, $order = false ) {

		if ( ! is_dir( snapshots_option( 'folder' ) ) ) {
			return array();
		}

		if ( ! function_exists( 'list_files' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
		}

		$files = list_files( snapshots_option( 'folder' ), 1 );
		if ( ! is_null( $name ) ) {
			$files = preg_grep( '/' . preg_quote( $name ) . '_(\d+)\/$/', $files );
		} else {
			$files = preg_grep( '/([a-z-]+)_(\d+)\/$/', $files );
		}
		if ( $order ) {
			usort(
				$files,
				function ( $a, $b ) {
					return filemtime( $b . 'manifest.json' ) - filemtime( $a . 'manifest.json' );
				}
			);
		}
		return $files;
	}

	private function get_name( $args ) {

		if ( array_key_exists( 0, $args ) ) {
			return preg_replace( '/\.(json|sql|zip)$/', '', $args[0] );
		}

		return $this->get_default_name();
	}

	private function get_default_name() {
		return sanitize_title( get_option( 'blogname', '' ) );
	}


	private function destroy_snapshots( $name ) {

		if ( false !== strpos( $name, '_' ) ) {
			$name = explode( '_', $name );
			$name = $name[0];
		}

		$backups = $this->get_snaps( $name, true );

		$skipped = 0;
		foreach ( $backups as $backup ) {
			if ( $skipped >= snapshots_option( 'max_shots' ) ) {
				$this->delete_folder( $backup );
			}
			++$skipped;
		}
	}
}
