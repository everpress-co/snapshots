<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}
/**
 * Syncs a local
 *
 * @when after_wp_load
 *
 * @author xaver
 */
class Snapshots extends WP_CLI_Command {

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
			WP_CLI::success( 'Snapshot saved!' );
		} else {
			WP_CLI::error( 'Snapshot not saved!' );
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
	 *        wp snapshot restore my-snapshot.1587039434
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

			WP_CLI::success( 'Snapshot restored!' );

		} else {
			WP_CLI::error( 'Snapshot not restored!' );
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
			WP_CLI::log( 'No files found!' );
			return;
		}

		$data = array();

		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$limit  = WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', count( $files ) );

		foreach ( $files as $i => $file ) {
			if ( $i >= $limit ) {
				break;
			}

			$manifest = json_decode( file_get_contents( $file ) );

			$name     = $manifest->name;
			$filename = pathinfo( $file, PATHINFO_FILENAME );
			$data[]   = array(
				'name'    => $name,
				'file'    => $filename,
				'created' => wp_date( 'Y-m-d H:i:s', filemtime( $file ) ),
				'past'    => human_time_diff( filemtime( $file ) ) . ' ago',
			);
		}

		WP_CLI\Utils\format_items( $format, $data, array_keys( $data[0] ) );
	}


	private function check() {
		if ( ! file_exists( snapshots_option( 'folder' ) ) ) {
			return wp_mkdir_p( snapshots_option( 'folder' ) );
		}

		return true;
	}


	private function get_snapshot_files( $name = '*' ) {

		if ( ! is_dir( snapshots_option( 'folder' ) ) ) {
			return array();
		}

		$files = glob( trailingslashit( snapshots_option( 'folder' ) ) . $name . '.*.json' );
		usort(
			$files,
			function( $a, $b ) {
				return filemtime( $a ) < filemtime( $b );
			}
		);

		return $files;

	}


	private function snapshots_create( $args, $assoc_args ) {

		if ( ! $this->check() ) {
			exit;
		}

		$name          = $this->get_name( $args );
		$timestamp     = time();
		$snapshot_name = sanitize_title( $name ) . '.' . $timestamp;

		$location = trailingslashit( snapshots_option( 'folder' ) ) . $snapshot_name . '.sql';

		$manifest = array(
			'name'    => $name,
			'created' => $timestamp,
		);

		ob_start();
		$db = new DB_Command();
		$db->export( array( $location ), array() );
		$result = explode( "\n", ob_get_clean() );

		if ( ! file_exists( $location ) ) {
			WP_CLI::error( sprintf( 'No snapshots found for %s', $snapshot_name ) );
		}

		if ( $files = WP_CLI\Utils\get_flag_value( $assoc_args, 'files', false ) ) {
			$upload_dir = wp_upload_dir();
			$folder     = $upload_dir['basedir'];
			$zipfile    = trailingslashit( snapshots_option( 'folder' ) ) . $snapshot_name . '.zip';
			$this->zip( $folder, $zipfile );
			if ( ! file_exists( $zipfile ) ) {
				WP_CLI::error( sprintf( 'No able to save zip file %s', $zipfile ) );
			}
		}
		if ( $location = WP_CLI\Utils\get_flag_value( $assoc_args, 'location', false ) ) {
			$manifest['location'] = $location;
		}
		$manifestfile = trailingslashit( snapshots_option( 'folder' ) ) . $snapshot_name . '.json';

		file_put_contents( $manifestfile, json_encode( $manifest ) );
		if ( ! file_exists( $manifestfile ) ) {
			WP_CLI::error( sprintf( 'No able to save manifest file %s', $manifestfile ) );
		}

		$this->destroy_snapshots( $snapshot_name );

		return true;

	}


	private function snapshots_restore( $args, $assoc_args ) {

		if ( ! $this->check() ) {
			exit;
		}
		$snapshot_name = $this->get_name( $args );
		$backup_dir    = false;

		if ( $restore_file = $this->get_most_recent_file( $snapshot_name, 'sql' ) ) {
			$location = trailingslashit( snapshots_option( 'folder' ) ) . $restore_file;
		} else {
			WP_CLI::error( sprintf( 'No snapshots found for %s', $snapshot_name ) );
		}

		$manifest = $this->get_most_recent_file( $snapshot_name, 'json' );

		if ( file_exists( trailingslashit( snapshots_option( 'folder' ) ) . $snapshot_name . '.zip' ) ) {

			$upload_dir = wp_upload_dir();
			$backup_dir = $upload_dir['basedir'] . '.' . time();
			if ( ! rename( $upload_dir['basedir'], $backup_dir ) ) {
				WP_CLI::error( sprintf( 'Could not backup upload folder for %s', $snapshot_name ) );
			}

			if ( $unzip = $this->unzip( trailingslashit( snapshots_option( 'folder' ) ) . $snapshot_name . '.zip', $upload_dir['basedir'] ) ) {

			} else {
				rename( $backup_dir, $upload_dir['basedir'] );
				WP_CLI::error( sprintf( 'Not able to extract uploads directory for %s', $snapshot_name ) );
			}
		}
		ob_start();
		$db = new DB_Command();
		$db->import( array( $location ), array() );
		$result = explode( "\n", ob_get_clean() );

		if ( preg_match_all( '/-- Table structure for table `(.*?)`/', file_get_contents( $location ), $matches ) ) {
			$tables = $matches[1];
			ob_start();
			$db->tables( null, array( 'all-tables-with-prefix' => true ) );
			$all_tables = explode( "\n", ob_get_clean() );
			$to_remove  = array_filter( array_diff( $all_tables, $tables ) );
			if ( ! empty( $to_remove ) ) {
				$db->query( array( 'DROP TABLE IF EXISTS `' . implode( '`; DROP TABLE IF EXISTS `', $to_remove ) . '`;' ), null );
			}
		}
		if ( $backup_dir ) {
			$this->delete( $backup_dir );
		}
		if ( file_exists( trailingslashit( snapshots_option( 'folder' ) ) . $manifest ) ) {
			$manifest = json_decode( file_get_contents( trailingslashit( snapshots_option( 'folder' ) ) . $manifest ) );
			if ( isset( $manifest->location ) ) {
				WP_CLI::line( 'Redirect to: ' . $manifest->location );
			}
		}

		if ( ! function_exists( 'wp_upgrade' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		wp_upgrade();

		return true;

	}

	private function zip( $folder, $destination = null ) {
		// Get real path for our folder
		$rootPath = realpath( $folder );

		$name = ! is_null( $destination ) ? $destination : trailingslashit( $rootPath ) . basename( $folder ) . '.zip';

		$zip = new ZipArchive();
		$zip->open( $name, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		$files = list_files( $rootPath, 999 );
		$count = 0;

		foreach ( $files as $file ) {

			$relativePath = substr( $file, strlen( $rootPath ) + 1 );
			if ( is_dir( $file ) ) {
				$zip->addEmptyDir( $relativePath );
				continue;
			}
			$zip->addFile( $file, $relativePath );
			$count++;
		}

		$zip->close();

	}
	private function unzip( $zipfile, $destination ) {
		WP_Filesystem();
		return unzip_file( $zipfile, $destination );
	}

	private function delete( $target, $recursive = true ) {
		global $wp_filesystem;
		WP_Filesystem();

		$type = is_dir( $target ) ? 'd' : 'f';

		return $wp_filesystem->delete( $target, $recursive, $type );
	}

	private function get_most_recent_file( $backup_name, $extension ) {

		$backupsdir = scandir( snapshots_option( 'folder' ), SCANDIR_SORT_DESCENDING );
		foreach ( $backupsdir as $backup ) {
			if ( strpos( $backup, $backup_name . '.' . $extension ) === 0 ) {
				return $backup;
			} elseif ( preg_match( '/^' . preg_quote( $backup_name ) . '(\.(\d+))?\.' . preg_quote( $extension ) . '/', $backup ) ) {
				return $backup;
			}
		}

		// check for name
		$manifests = glob( trailingslashit( snapshots_option( 'folder' ) ) . '*.json' );

		foreach ( $manifests as $manifest ) {
			$m = json_decode( file_get_contents( $manifest ) );
			if ( $m->name == $backup_name ) {
				$backup = preg_replace( '/\.json$/', '.sql', basename( $manifest ) );
				return $backup;
			}
		}

		return false;
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
		$skipped    = 0;
		$backupsdir = scandir( snapshots_option( 'folder' ), SCANDIR_SORT_DESCENDING );
		$name       = str_replace( strstr( $name, '.' ), '.', $name );
		foreach ( $backupsdir as $backup ) {
			if ( strpos( $backup, $name ) === 0 ) {
				if ( $skipped >= snapshots_option( 'max_shots' ) * 3 ) {
					unlink( trailingslashit( snapshots_option( 'folder' ) ) . $backup );
				}
				$skipped++;
			}
		}
	}

}
