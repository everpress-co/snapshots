<?php
/**
 * Shared helpers for Snapshots.
 *
 * @package Snapshots
 */

namespace EverPress\Snapshots;

/**
 * Returns a Snapshots setting from a constant, option, or default.
 *
 * @since 1.0.0
 *
 * @param string $option Setting key without the snapshots_ prefix.
 * @return mixed Setting value.
 */
function snapshots_option( $option ) {

	$constant = 'SNAPSHOTS_' . strtoupper( $option );
	$return   = null;

	// Constants take precedence over stored options.
	if ( defined( $constant ) ) {
		$return = constant( $constant );
	} else {

		$settings = require __DIR__ . '/set.php';
		$default  = isset( $settings[ $option ]['default'] ) ? $settings[ $option ]['default'] : null;
		$return   = get_option( 'snapshots_' . $option, $default );
	}

	/**
	 * Filter the value of a Snapshots option.
	 *
	 * @param mixed $return The value of the Snapshots option.
	 */
	return apply_filters( 'snapshots_' . $option, $return );
}

/**
 * Loads the settings schema.
 *
 * @since 1.0.0
 *
 * @return array<string, array<string, mixed>> Settings definitions.
 */
function snapshots_get_settings() {
	return require __DIR__ . '/set.php';
}

/**
 * Builds a process PATH the web server can use to find php, wp, and studio.
 *
 * The putenv() function does not expand $PATH. Local's PHP-FPM PATH is often
 * only the Ghostscript directory, so a literal "$PATH:..." value hides php.
 * Directories are joined here. A component whose name is the characters
 * $PATH is dropped.
 *
 * @since [Unreleased]
 *
 * @return void
 */
function snapshots_prepare_path() {
	static $prepared = false;

	if ( $prepared ) {
		return;
	}

	$prepared = true;

	// putenv() may be disabled while exec() remains available. Configured CLI
	// directories are still probed directly in snapshots_resolve_binary().
	if ( ! function_exists( 'putenv' ) ) {
		return;
	}

	$dirs = array();

	$php_path = snapshots_option( 'php_path' );
	if ( is_string( $php_path ) && '' !== $php_path ) {
		foreach ( explode( PATH_SEPARATOR, $php_path ) as $dir ) {
			$dir = trim( $dir );
			if ( '' !== $dir ) {
				$dirs[] = $dir;
			}
		}
	}

	$cli_path = snapshots_option( 'cli_path' );
	if ( is_string( $cli_path ) && '' !== $cli_path ) {
		$dirs[] = untrailingslashit( $cli_path );
	}

	// Studio installs `studio` in ~/.local/bin. The web server PATH does not
	// include that directory, so the admin-bar backup cannot export the database.
	$studio_home_bin = snapshots_studio_home_bin();
	if ( '' !== $studio_home_bin ) {
		$dirs[] = $studio_home_bin;
	}

	if ( '/' === DIRECTORY_SEPARATOR ) {
		$dirs[] = '/usr/local/bin';
		$dirs[] = '/usr/bin';
		$dirs[] = '/bin';
	}

	$current = getenv( 'PATH' );
	if ( is_string( $current ) && '' !== $current ) {
		foreach ( explode( PATH_SEPARATOR, $current ) as $dir ) {
			$dir = trim( $dir );
			if ( '' === $dir || '$PATH' === $dir ) {
				continue;
			}
			$dirs[] = $dir;
		}
	}

	$unique = array();
	foreach ( $dirs as $dir ) {
		if ( ! in_array( $dir, $unique, true ) ) {
			$unique[] = $dir;
		}
	}

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Child processes need the repaired PATH.
	putenv( 'PATH=' . implode( PATH_SEPARATOR, $unique ) );
}

/**
 * Resolves php, wp, or studio.
 *
 * The wp and studio binaries are resolved from SNAPSHOTS_CLI_PATH first.
 * The studio binary is also resolved from ~/.local/bin. Those probes do
 * not use putenv(). Anything still missing is looked up with a shell
 * command after PATH is repaired.
 * The lookup runs in a child process. Studio's site-directory file access
 * hides paths outside the site from PHP itself, so this does not use
 * file_exists() or which.
 *
 * @since [Unreleased]
 *
 * @param string $name Binary name: php, wp, or studio.
 * @return string Absolute path, or an empty string when it is not found.
 */
function snapshots_resolve_binary( $name ) {
	$allowed = array( 'php', 'wp', 'studio' );
	if ( ! in_array( $name, $allowed, true ) ) {
		return '';
	}

	$configured = snapshots_resolve_configured_binary( $name );
	if ( '' !== $configured ) {
		return $configured;
	}

	snapshots_prepare_path();

	$lookup = ( '/' === DIRECTORY_SEPARATOR ) ? 'command -v ' : 'where ';
	$output = array();
	$code   = 0;

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Binary lookup cannot use file_exists() under open_basedir.
	exec( $lookup . escapeshellarg( $name ), $output, $code );

	if ( 0 !== $code || empty( $output[0] ) || ! is_string( $output[0] ) ) {
		return '';
	}

	$path = trim( $output[0] );
	if ( '' === $path || false === strpos( $path, DIRECTORY_SEPARATOR ) ) {
		return '';
	}

	return $path;
}

/**
 * Builds a shell command that searches one directory for a binary.
 *
 * POSIX shells accept a one-command PATH assignment, and command -v is a
 * shell builtin. cmd.exe does not apply that assignment. On Windows the
 * command sets PATH for one statement and calls System32\where.exe by
 * absolute path, because replacing PATH would hide where.exe.
 *
 * @since [Unreleased]
 *
 * @param string $name Binary name.
 * @param string $dir  Directory to search.
 * @return string Command string, or an empty string when the directory cannot be probed.
 */
function snapshots_binary_probe_command( $name, $dir ) {
	if ( '/' === DIRECTORY_SEPARATOR ) {
		return 'PATH=' . escapeshellarg( $dir ) . ' command -v ' . escapeshellarg( $name );
	}

	$dir = rtrim( $dir, '/\\' );
	// A trailing slash on a drive root would become "C:" and search the wrong place.
	if ( '' === $dir || 1 === preg_match( '/^[A-Za-z]:$/', $dir ) ) {
		return '';
	}

	// cmd.exe expands % and !, and a quote would close the set "PATH=..." value.
	if ( false !== strpbrk( $dir, "%!\"\r\n" ) ) {
		return '';
	}

	$windows = getenv( 'SystemRoot' );
	if ( ! is_string( $windows ) || '' === trim( $windows ) ) {
		$windows = getenv( 'WINDIR' );
	}
	if ( ! is_string( $windows ) || '' === trim( $windows ) || false !== strpbrk( $windows, "%!\"\r\n" ) ) {
		return '';
	}

	$where = untrailingslashit( $windows ) . '\\System32\\where.exe';

	return 'set "PATH=' . $dir . '" && ' . escapeshellarg( $where ) . ' ' . escapeshellarg( $name );
}

/**
 * Locates one binary in a single directory via the shell.
 *
 * PATH for the child process is only this directory, so a later system
 * binary is not chosen. The result is cached for the request.
 *
 * @since [Unreleased]
 *
 * @param string $name Binary name.
 * @param string $dir  Directory to search.
 * @return string Absolute path, or an empty string when it is not found.
 */
function snapshots_resolve_binary_in_dir( $name, $dir ) {
	static $cache = array();

	$allowed = array( 'php', 'wp', 'studio' );
	if ( ! in_array( $name, $allowed, true ) || '' === $dir ) {
		return '';
	}

	$cache_key = $name . "\n" . $dir;
	if ( array_key_exists( $cache_key, $cache ) ) {
		return $cache[ $cache_key ];
	}

	$command = snapshots_binary_probe_command( $name, $dir );
	if ( '' === $command ) {
		$cache[ $cache_key ] = '';
		return '';
	}

	$output = array();
	$code   = 0;

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Binary lookup cannot use file_exists() under open_basedir.
	exec( $command, $output, $code );

	$found = '';
	if ( 0 === $code && ! empty( $output[0] ) && is_string( $output[0] ) ) {
		$found = trim( $output[0] );
		if ( '' === $found || false === strpos( $found, DIRECTORY_SEPARATOR ) ) {
			$found = '';
		}
	}

	$cache[ $cache_key ] = $found;
	return $found;
}

/**
 * Resolves wp or studio without putenv().
 *
 * SNAPSHOTS_CLI_PATH is checked first. studio is then checked in ~/.local/bin.
 * A host can allow exec() while disabling putenv(), and the repaired PATH is
 * never published in that case.
 *
 * @since [Unreleased]
 *
 * @param string $name Binary name.
 * @return string Absolute path, or an empty string when it is not found.
 */
function snapshots_resolve_configured_binary( $name ) {
	if ( in_array( $name, array( 'wp', 'studio' ), true ) ) {
		$cli_path = snapshots_option( 'cli_path' );
		if ( is_string( $cli_path ) && '' !== trim( $cli_path ) ) {
			$found = snapshots_resolve_binary_in_dir( $name, untrailingslashit( trim( $cli_path ) ) );
			if ( '' !== $found ) {
				return $found;
			}
		}
	}

	if ( 'studio' !== $name ) {
		return '';
	}

	$home = snapshots_studio_home_bin();
	if ( '' === $home ) {
		return '';
	}

	return snapshots_resolve_binary_in_dir( 'studio', $home );
}

/**
 * Returns ~/.local/bin when that directory contains the studio command.
 *
 * The lookup is a shell probe. Studio's site-directory file access hides
 * paths outside the site from PHP, so file_exists() cannot see this directory.
 * An empty result leaves PATH unchanged. SNAPSHOTS_CLI_PATH is still checked
 * ahead of this directory when studio is installed somewhere else. The same
 * directory is probed directly, so discovery does not depend on putenv().
 *
 * @since [Unreleased]
 *
 * @return string Absolute directory, or an empty string when studio is not there.
 */
function snapshots_studio_home_bin() {
	$home = getenv( 'HOME' );
	if ( ! is_string( $home ) || '' === trim( $home ) ) {
		$home = getenv( 'USERPROFILE' );
	}
	if ( ! is_string( $home ) || '' === trim( $home ) ) {
		return '';
	}

	$dir = untrailingslashit( $home ) . '/.local/bin';
	if ( '' === snapshots_resolve_binary_in_dir( 'studio', $dir ) ) {
		return '';
	}

	return $dir;
}

/**
 * Closes the current database connection.
 *
 * The admin request keeps Studio's SQLite file open for the whole request.
 * Restore has to release that connection before another process writes the
 * snapshot, or the import waits on a lock held by the request that is waiting
 * on the import.
 *
 * @since [Unreleased]
 *
 * @return void
 */
function snapshots_release_database() {
	global $wpdb;

	if ( is_object( $wpdb ) && method_exists( $wpdb, 'close' ) ) {
		$wpdb->close();
	}
}

/**
 * Reopens the database after snapshots_release_database().
 *
 * The SQLite driver marks the connection unusable until db_connect() runs.
 *
 * @since [Unreleased]
 *
 * @return void
 */
function snapshots_reconnect_database() {
	global $wpdb;

	if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'db_connect' ) ) {
		return;
	}

	if ( ! empty( $wpdb->dbh ) ) {
		return;
	}

	$wpdb->db_connect();
}

/**
 * Absolute path to Studio's bundled wp sqlite command file.
 *
 * The file sits next to the studio binary, outside the site. `studio wp` can
 * read it. The web request cannot, because Studio limits PHP to the site
 * directory.
 *
 * @since [Unreleased]
 *
 * @return string Absolute path, or an empty string when it cannot be resolved.
 */
function snapshots_studio_sqlite_command_file() {
	$studio = snapshots_resolve_binary( 'studio' );
	if ( '' === $studio ) {
		return '';
	}

	$resolved = snapshots_resolve_path( $studio );
	if ( '' === $resolved ) {
		return '';
	}

	$file = dirname( $resolved, 2 ) . '/cli/wp-files/sqlite-command/command.php';
	if ( ! is_file( $file ) ) {
		return '';
	}

	return $file;
}

/**
 * Resolves a filesystem path, including a symlink outside the site.
 *
 * PHP realpath() is tried first. When Studio's site-directory file access
 * hides the target, a PHP subprocess resolves it instead.
 *
 * @since [Unreleased]
 *
 * @param string $path Path to resolve.
 * @return string Absolute path, or an empty string.
 */
function snapshots_resolve_path( $path ) {
	if ( ! is_string( $path ) || '' === $path ) {
		return '';
	}

	$resolved = realpath( $path );
	if ( is_string( $resolved ) && '' !== $resolved ) {
		return $resolved;
	}

	$php = snapshots_resolve_binary( 'php' );
	if ( '' === $php && is_string( PHP_BINARY ) ) {
		$php = PHP_BINARY;
	}
	if ( '' === $php ) {
		return '';
	}

	$output = array();
	$code   = 0;

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- realpath() cannot see outside the site under open_basedir.
	exec( escapeshellarg( $php ) . ' -r ' . escapeshellarg( 'echo realpath($argv[1]);' ) . ' -- ' . escapeshellarg( $path ), $output, $code );

	if ( 0 !== $code || empty( $output[0] ) || ! is_string( $output[0] ) ) {
		return '';
	}

	$found = trim( $output[0] );
	if ( '' === $found || false === strpos( $found, DIRECTORY_SEPARATOR ) ) {
		return '';
	}

	return $found;
}

/**
 * Returns the PHP binary used to launch WP-CLI.
 *
 * Configured directories in SNAPSHOTS_PHP_PATH are tried first. PHP_BINARY
 * is the fallback when those directories do not contain php.
 *
 * @since [Unreleased]
 *
 * @return string Absolute php path, or an empty string when none is found.
 */
function snapshots_php_binary() {
	snapshots_prepare_path();

	$configured = snapshots_option( 'php_path' );
	if ( is_string( $configured ) && '' !== trim( $configured ) ) {
		foreach ( explode( PATH_SEPARATOR, $configured ) as $dir ) {
			$found = snapshots_resolve_binary_in_dir( 'php', trim( $dir ) );
			if ( '' !== $found ) {
				return $found;
			}
		}

		if ( defined( 'PHP_BINARY' ) && is_string( PHP_BINARY ) && '' !== PHP_BINARY ) {
			return PHP_BINARY;
		}

		return '';
	}

	$resolved = snapshots_resolve_binary( 'php' );
	if ( '' !== $resolved ) {
		return $resolved;
	}

	if ( defined( 'PHP_BINARY' ) && is_string( PHP_BINARY ) && '' !== PHP_BINARY ) {
		return PHP_BINARY;
	}

	return '';
}

/**
 * Whether this WordPress directory is a WordPress Studio site.
 *
 * A zero exit from `studio status` is the signal, including a Studio site
 * that uses MySQL. Installing the studio binary on a machine is not enough.
 *
 * @since [Unreleased]
 *
 * @return bool True when Studio manages this site.
 */
function snapshots_is_studio_site() {
	static $is_studio = null;

	if ( null !== $is_studio ) {
		return $is_studio;
	}

	$studio = snapshots_resolve_binary( 'studio' );
	if ( '' === $studio || ! defined( 'ABSPATH' ) ) {
		$is_studio = false;
		return false;
	}

	$output = array();
	$code   = 0;
	$cmd    = escapeshellarg( $studio ) . ' status --path=' . escapeshellarg( ABSPATH );
	if ( '/' === DIRECTORY_SEPARATOR ) {
		$cmd .= ' 2>/dev/null';
	}

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Studio site detection is a child process.
	exec( $cmd, $output, $code );

	$is_studio = ( 0 === $code );
	return $is_studio;
}

/**
 * Whether the site database is Studio's SQLite file.
 *
 * @since [Unreleased]
 *
 * @return bool True when wp-content/database/.ht.sqlite exists.
 */
function snapshots_is_sqlite() {
	return file_exists( WP_CONTENT_DIR . '/database/.ht.sqlite' );
}

/**
 * Shell prefix that runs WP-CLI for this site.
 *
 * Studio sites use `studio wp`. Other sites use the absolute php binary
 * and the wp script, so the wp shebang does not have to find php.
 *
 * @since [Unreleased]
 *
 * @return string Escaped command prefix, or an empty string when neither launcher is available.
 */
function snapshots_wp_invocation() {
	snapshots_prepare_path();

	if ( snapshots_is_studio_site() ) {
		$studio = snapshots_resolve_binary( 'studio' );
		if ( '' === $studio ) {
			return '';
		}
		return escapeshellarg( $studio ) . ' wp';
	}

	$php = snapshots_php_binary();
	$wp  = snapshots_resolve_binary( 'wp' );
	if ( '' === $php || '' === $wp ) {
		return '';
	}

	return escapeshellarg( $php ) . ' ' . escapeshellarg( $wp );
}

/**
 * Turns a return address into a site path without snapshot action arguments.
 *
 * The admin-bar create link adds `snaphot_create` and `snapshots_nonce` to the
 * current screen. Those arguments are not the screen to reopen after restore.
 *
 * @since [Unreleased]
 *
 * @param string $url Absolute URL or path, including an optional query and fragment.
 * @return string Path, query, and fragment, or an empty string when nothing remains.
 */
function snapshots_return_path( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$fragment = wp_parse_url( $url, PHP_URL_FRAGMENT );
	$cleaned  = remove_query_arg(
		array(
			'snaphot_create',
			'snapshots_nonce',
			'snapshot_location',
			'snapshot_restore',
			'snapshot_delete',
			'snapshot_redirect',
		),
		$url
	);

	$parts = wp_parse_url( $cleaned );
	if ( ! is_array( $parts ) ) {
		return '';
	}

	$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
	if ( '' === $path ) {
		$path = '/';
	}
	if ( ! empty( $parts['query'] ) ) {
		$path .= '?' . $parts['query'];
	}
	if ( is_string( $fragment ) && '' !== $fragment && false === strpos( $path, '#' ) ) {
		$path .= '#' . $fragment;
	}

	return $path;
}

/**
 * Renders admin navigation tabs for Snapshots screens.
 *
 * @since 1.0.0
 *
 * @param string $current_tab Active tab slug.
 * @return void
 */
function snapshots_get_nav( $current_tab = 'snapshots' ) {

	?>
	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'tools.php?page=snapshots' ) ); ?>" class="nav-tab<?php echo 'snapshots' === $current_tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Snapshots', 'snapshots' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=snapshots-settings' ) ); ?>" class="nav-tab<?php echo 'settings' === $current_tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'snapshots' ); ?></a>
	</nav>

	<?php
}

/**
 * Writes access-protection files into the snapshot storage folder.
 *
 * The folder lives inside the web root by default and holds full database
 * dumps; these files keep that data from being served by Apache or exposed
 * through directory indexes. Idempotent: existing files are left untouched,
 * and nginx setups still need a server-level dot-directory deny rule.
 *
 * @since 3.0.0
 *
 * @param string $folder Absolute path to the snapshot storage folder.
 * @return void
 */
function snapshots_harden_folder( $folder ) {
	if ( ! is_dir( $folder ) ) {
		return;
	}

	$htaccess = trailingslashit( $folder ) . '.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		// Apache 2.4 (mod_authz_core) with a 2.2 fallback.
		$rules  = "# SnapShots: deny web access to snapshot data.\n";
		$rules .= "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n";
		$rules .= "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
		file_put_contents( $htaccess, $rules );
	}

	$index = trailingslashit( $folder ) . 'index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}
}

/**
 * Returns the snapshot name used when a create request has no name.
 *
 * Uses the display name of the current snapshot when that snapshot is still
 * on disk. Otherwise uses the site title. A missing site title becomes
 * "snapshots".
 *
 * @since [Unreleased]
 *
 * @return string Snapshot name.
 */
function snapshots_default_name() {
	$current = get_transient( 'snapshot_current' );
	if ( is_string( $current ) && '' !== $current ) {
		$current = basename( $current );
		if ( 1 === preg_match( '/^[a-z0-9_-]+$/', $current ) ) {
			$manifest = trailingslashit( snapshots_option( 'folder' ) ) . $current . '/manifest.json';
			if ( is_file( $manifest ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local manifest, not a remote URL.
				$data = json_decode( (string) file_get_contents( $manifest ), true );
				if ( is_array( $data ) && isset( $data['name'] ) && is_string( $data['name'] ) && '' !== trim( $data['name'] ) ) {
					return $data['name'];
				}
			}
		}
	}

	$title = get_option( 'blogname', 'snapshots' );
	if ( ! is_string( $title ) || '' === trim( $title ) ) {
		return 'snapshots';
	}

	return $title;
}

if ( ! function_exists( 'wp_date' ) ) {

	// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- WordPress core polyfill for older sites.

	/**
	 * Polyfill for wp_date() on WordPress versions below 5.3.
	 *
	 * @since 1.0.0
	 *
	 * @param string            $format    PHP date format.
	 * @param int|null          $timestamp Optional. Unix timestamp.
	 * @param DateTimeZone|null $timezone  Optional. Timezone.
	 * @return string|false Formatted date string, or false on failure.
	 */
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		global $wp_locale;

		if ( null === $timestamp ) {
			$timestamp = time();
		} elseif ( ! is_numeric( $timestamp ) ) {
			return false;
		}

		if ( ! $timezone ) {
			$timezone = wp_timezone();
		}

		$datetime = date_create( '@' . $timestamp );
		$datetime->setTimezone( $timezone );

		if ( empty( $wp_locale->month ) || empty( $wp_locale->weekday ) ) {
			$date = $datetime->format( $format );
		} else {
			// We need to unpack shorthand `r` format because it has parts that might be localized.
			$format = preg_replace( '/(?<!\\\\)r/', DATE_RFC2822, $format );

			$new_format    = '';
			$format_length = strlen( $format );
			$month         = $wp_locale->get_month( $datetime->format( 'm' ) );
			$weekday       = $wp_locale->get_weekday( $datetime->format( 'w' ) );

			for ( $i = 0; $i < $format_length; $i++ ) {
				switch ( $format[ $i ] ) {
					case 'D':
						$new_format .= addcslashes( $wp_locale->get_weekday_abbrev( $weekday ), '\\A..Za..z' );
						break;
					case 'F':
						$new_format .= addcslashes( $month, '\\A..Za..z' );
						break;
					case 'l':
						$new_format .= addcslashes( $weekday, '\\A..Za..z' );
						break;
					case 'M':
						$new_format .= addcslashes( $wp_locale->get_month_abbrev( $month ), '\\A..Za..z' );
						break;
					case 'a':
						$new_format .= addcslashes( $wp_locale->get_meridiem( $datetime->format( 'a' ) ), '\\A..Za..z' );
						break;
					case 'A':
						$new_format .= addcslashes( $wp_locale->get_meridiem( $datetime->format( 'A' ) ), '\\A..Za..z' );
						break;
					case '\\':
						$new_format .= $format[ $i ];

						// If character follows a slash, we add it without translating.
						if ( $i < $format_length ) {
							$new_format .= $format[ ++$i ];
						}
						break;
					default:
						$new_format .= $format[ $i ];
						break;
				}
			}

			$date = $datetime->format( $new_format );
			$date = wp_maybe_decline_date( $date, $format );
		}

		return $date;
	}

	/**
	 * Polyfill for wp_timezone() on WordPress versions below 5.3.
	 *
	 * @since 1.0.0
	 *
	 * @return DateTimeZone Site timezone.
	 */
	function wp_timezone() {
		return new DateTimeZone( wp_timezone_string() );
	}

	/**
	 * Polyfill for wp_timezone_string() on WordPress versions below 5.3.
	 *
	 * @since 1.0.0
	 *
	 * @return string PHP timezone identifier or offset.
	 */
	function wp_timezone_string() {
		$timezone_string = get_option( 'timezone_string' );

		if ( $timezone_string ) {
			return $timezone_string;
		}

		$offset  = (float) get_option( 'gmt_offset' );
		$hours   = (int) $offset;
		$minutes = ( $offset - $hours );

		$sign      = ( $offset < 0 ) ? '-' : '+';
		$abs_hour  = abs( $hours );
		$abs_mins  = abs( $minutes * 60 );
		$tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

		return $tz_offset;
	}

	// phpcs:enable
}
