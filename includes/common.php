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
 * Resolves php, wp, or studio with command -v after PATH is repaired.
 *
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
 * Locates one binary in a single directory via the shell.
 *
 * @since [Unreleased]
 *
 * @param string $name Binary name.
 * @param string $dir  Directory to search.
 * @return string Absolute path, or an empty string when it is not found.
 */
function snapshots_resolve_binary_in_dir( $name, $dir ) {
	$allowed = array( 'php', 'wp', 'studio' );
	if ( ! in_array( $name, $allowed, true ) || '' === $dir ) {
		return '';
	}

	$lookup = ( '/' === DIRECTORY_SEPARATOR ) ? 'command -v ' : 'where ';
	$output = array();
	$code   = 0;

	// PATH is only this directory so a later system php is not chosen.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Binary lookup cannot use file_exists() under open_basedir.
	exec( 'PATH=' . escapeshellarg( $dir ) . ' ' . $lookup . escapeshellarg( $name ), $output, $code );

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
