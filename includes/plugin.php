<?php

namespace EverPress\Snapshots;

class Plugin {

	private static $instance = null;

	public function __construct() {

		register_activation_hook( SNAPSHOTS_FILE, array( &$this, 'on_activate' ) );
		register_deactivation_hook( SNAPSHOTS_FILE, array( &$this, 'on_deactivate' ) );

		add_action( 'init', array( $this, 'actions' ) );
		add_action( 'init', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_bar_menu', array( $this, 'toolbar_snapshots' ), 20 );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SNAPSHOTS_FILE ), array( $this, 'apd_settings_link' ) );
		add_action( 'admin_notices', array( $this, 'snapshots_tools_admin_notices' ) );
		add_action( 'update_option_snapshots_list_order', array( $this, 'clear_snapshot_list_cache' ) );
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new Plugin();
		}

		return self::$instance;
	}

	public function apd_settings_link( array $links ) {
		$url = admin_url( 'options-general.php?page=snapshots-settings' );

		$links = array( 'settings' => '<a href="' . esc_url( $url ) . '">' . __( 'Settings', 'snapshots' ) . '</a>' )
		+ array( 'snapshots' => '<a href="' . esc_url( $url ) . '">' . __( 'Snapshots', 'snapshots' ) . '</a>' )
		+ $links;

		return $links;
	}

	public function admin_menu() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$hook = add_management_page(
			'Snapshots',
			'Snapshots',
			'manage_options',
			'snapshots',
			array( $this, 'render' ),
			9999
		);

		add_action(
			sprintf( 'load-%s', $hook ),
			array( $this, 'snapshots_tools_handle_bulk_request' )
		);
	}

	/**
	 * Process list-table bulk actions before admin-header sends output (fixes wp_safe_redirect notices).
	 */
	public function snapshots_tools_handle_bulk_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once __DIR__ . '/table.php';

		$table = new Table();
		$table->process_bulk_action();
	}


	/**
	 * Admin notices for the Tools → Snapshots screen (bulk delete feedback).
	 */
	public function snapshots_tools_admin_notices() {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query arguments.
		if ( ! isset( $_GET['page'] ) || 'snapshots' !== $_GET['page'] ) {
			return;
		}

		global $pagenow;

		if ( 'tools.php' !== $pagenow ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'tools_page_snapshots' !== $screen->id ) {
			return;
		}

		if ( isset( $_GET['snapshots_bulk_deleted'] ) ) {
			$deleted_count = absint( $_GET['snapshots_bulk_deleted'] );
			if ( $deleted_count > 0 ) {
				$message = sprintf(
					/* translators: %d: number of snapshots deleted */
					_n(
						'%d snapshot permanently deleted.',
						'%d snapshots permanently deleted.',
						$deleted_count,
						'snapshots'
					),
					$deleted_count
				);
				if ( function_exists( 'wp_admin_notice' ) ) {
					wp_admin_notice(
						$message,
						array(
							'id'          => 'snapshots-bulk-deleted',
							'type'        => 'success',
							'dismissible' => true,
						)
					);
				} else {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						esc_html( $message )
					);
				}
			}
		}

		if ( isset( $_GET['snapshots_bulk_failed'] ) ) {
			$failed_count = absint( $_GET['snapshots_bulk_failed'] );
			if ( $failed_count > 0 ) {
				$message = sprintf(
					/* translators: %d: number of snapshots that could not be deleted */
					_n(
						'Could not delete %d snapshot. Check SnapShots logs or CLI output.',
						'Could not delete %d snapshots. Check SnapShots logs or CLI output.',
						$failed_count,
						'snapshots'
					),
					$failed_count
				);
				if ( function_exists( 'wp_admin_notice' ) ) {
					wp_admin_notice(
						$message,
						array(
							'id'          => 'snapshots-bulk-failed',
							'type'        => 'warning',
							'dismissible' => true,
						)
					);
				} else {
					printf(
						'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
						esc_html( $message )
					);
				}
			}
		}
	}


	public function render() {

		echo '<div class="wrap"><h1>' . esc_html__( 'Snapshots', 'snapshots' ) . '</h1>';

		snapshots_get_nav( 'snapshots' );

		require_once __DIR__ . '/table.php';

		echo '<form class="snapshots-list-table-form" method="post" action="' . esc_url( admin_url( 'tools.php?page=snapshots' ) ) . '">';

		$table = new Table();
		$table->prepare_items();
		$table->display();

		echo '</form>';

		add_action( 'admin_footer_text', array( $this, 'admin_footer_text' ) );

		echo '</div>';
	}

	public function admin_footer_text( $default ) {
		/* Translators: %1$s: Plugin Name, %2$s: Rating URL */
		return sprintf( esc_html__( 'If you like %1$s please leave a %2$s rating. Thanks in advance!', 'snapshots' ), '<strong>SnapShots</strong>', '<a href="https://wordpress.org/support/view/plugin-reviews/snapshots?filter=5#new-post" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a>' );
	}

	public function actions() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( array_key_exists( 'snapshot_restore', $_GET ) ) {
			$redirect = $this->restore( $_GET['snapshot_restore'] );
			$this->login_user( wp_get_current_user() );
			wp_redirect( $redirect );
			exit;
		}

		if ( array_key_exists( 'snaphot_create', $_GET ) ) {
			$files    = snapshots_option( 'save_files' );
			$location = snapshots_option( 'save_location' );
			if ( array_key_exists( 'snapshot_location', $_GET ) ) {
				$redirect_to = htmlspecialchars_decode( $_GET['snapshot_location'] );
			} else {
				$redirect_to = remove_query_arg( 'snaphot_create' );
			}
			$this->backup( $_GET['snaphot_create'], $files, $location );

			wp_redirect( $redirect_to );
			exit;
		}

		if ( array_key_exists( 'snapshot_delete', $_GET ) ) {
			$this->delete( $_GET['snapshot_delete'] );
			wp_redirect( remove_query_arg( 'snapshot_delete' ) );
			exit;
		}
	}

	public function enqueue_scripts() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script( 'snapshots-script', plugin_dir_url( SNAPSHOTS_FILE ) . 'build/script.js', array(), false, true );
		wp_enqueue_style( 'snapshots-style', plugin_dir_url( SNAPSHOTS_FILE ) . 'build/style.css', array() );

		wp_localize_script(
			'snapshots-script',
			'snapshots',
			array(
				'prompt'    => esc_attr__( 'Create a new Snapshot. Please define a name:', 'snapshots' ),
				'restore'   => esc_attr__( 'Restore this Backup from %s?', 'snapshots' ),
				'delete'    => esc_attr__( 'Delete snapshot %1$s from %2$s?', 'snapshots' ),
				'currently' => esc_attr__( 'You are currently on the %s snapshot', 'snapshots' ),
				'blogname'  => get_option( 'blogname', 'snapshots' ),
			)
		);
	}


	public function toolbar_snapshots( $wp_admin_bar ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$snapshots = $this->get_snapshots();
		$count     = count( $snapshots );
		$last      = get_transient( 'snapshot_current' );
		if ( isset( $snapshots[ $last ] ) ) {
			$last = $snapshots[ $last ]->name;
		} else {
			$last = '';
		}

		$title = $count ? $count : esc_html__( 'Click here to create your first Snapshot!', 'snapshots' );

		// the main menu entry
		$wp_admin_bar->add_node(
			array(
				'id'    => 'snapshots',
				'title' => '<span class="ab-icon dashicons dashicons-backup" style="margin-top:2px"></span>' . esc_html( $title ) . '<span class="snapshot-extra-title" title="">' . esc_html( $last ) . '</span>',
				'href'  => add_query_arg( 'snaphot_create', '1' ),

			)
		);

		if ( ! $count ) {
			return;
		}

		// search and setings button
		$wp_admin_bar->add_node(
			array(
				'id'     => 'snapshot-search',
				'title'  => '<span class="search-snapshot"><label for="snapshost_search">' . esc_attr__( 'Search SnapShots...', 'snapshots' ) . '</label><input id="snapshost_search" type="search" placeholder="' . esc_attr__( 'Search SnapShots...', 'snapshots' ) . '"></span>',
				'parent' => 'snapshots',
				'meta'   => array(
					'html' => '<div><a title="' . esc_attr__( 'Settings', 'snapshots' ) . '" href="' . esc_url( admin_url( 'options-general.php?page=snapshots-settings' ) ) . '"><span class="dashicons dashicons-admin-settings"></span></a></div>',

				),
			)
		);

		$current_string = __( 'current', 'snapshots' );

		foreach ( $snapshots as $id => $data ) {

			$wp_admin_bar->add_node(
				array(
					'id'     => 'snapshot-' . $id,
					'title'  => '<span class="restore-snapshot" data-status="' . esc_attr( $current_string ) . '" title="' . sprintf( esc_attr__( 'created %s ago', 'snapshots' ), human_time_diff( $data->created ) ) . ' - ' . wp_date( 'Y-m-d H:i', $data->created ) . '" data-date="' . wp_date( 'Y-m-d H:i', $data->created ) . '">' . esc_html( $data->name ) . '</span>',
					'href'   => esc_url( $data->restore ),
					'parent' => 'snapshots',
					'meta'   => array(
						'rel'   => $data->name . ' ' . strtolower( $data->name ),
						'class' => $data->current ? 'current' : '',
						'html'  => '<div><a class="delete-snapshot" title="' . esc_attr( sprintf( 'delete %s', $data->name ) ) . '" data-name="' . esc_attr( $data->name ) . '" data-date="' . wp_date( 'Y-m-d H:i', $data->created ) . '" href="' . esc_url( $data->delete ) . '">&times;</a></div>',

					),
				)
			);
		}
	}


	/**
	 * Transient key for the snapshot list cache.
	 *
	 * @param string|false $snapshot_current_id Value of snapshot_current transient.
	 * @param string       $order               `last_used` or `created`.
	 * @return string
	 */
	private function snapshot_list_cache_key( $snapshot_current_id, $order ) {
		$suffix = false !== $snapshot_current_id ? (string) $snapshot_current_id : '';
		return 'snapshot_list_' . $suffix . '_' . $order;
	}

	/**
	 * Snapshot list sort mode: manifest last_used vs folder created time.
	 *
	 * @return string `last_used`|`created`
	 */
	private function get_list_order() {
		$order = snapshots_option( 'list_order' );
		if ( 'created' !== $order && 'last_used' !== $order ) {
			$order = 'last_used';
		}
		return apply_filters( 'snapshots_list_order', $order );
	}


	public function get_snapshots() {

		$last      = get_transient( 'snapshot_current' );
		$order     = $this->get_list_order();
		$cache_key = $this->snapshot_list_cache_key( $last, $order );

		// add caching for the snapshots to improve performance
		if ( false === ( $snapshots = get_transient( $cache_key ) ) ) {

			// raw snaps by reading files
			$snaps = $this->get_snaps();

			$snapshots = array();

			foreach ( $snaps as $i => $snapshot ) {
				$file = trailingslashit( $snapshot ) . 'manifest.json';
				$id   = basename( $snapshot );
				// move current to the top
				// if ( $last == $id ) {
				// add last one to the begingin of the array
				// $return = array( $id => array() ) + $return;
				// }
				$created_ts       = (int) explode( '_', $id )[1];
				$snapshots[ $id ] = (object) array(
					'name'      => $id,
					'created'   => $created_ts,
					'last_used' => 0,
					'current'   => $last == $id,
					'broken'    => false,
					'restore'   => add_query_arg( array( 'snapshot_restore' => $id ) ),
					'delete'    => add_query_arg( array( 'snapshot_delete' => $id ) ),

				);
				if ( ! file_exists( $file ) ) {
					$snapshots[ $id ]->broken = true;
				} else {
					$data             = json_decode( file_get_contents( $file ) );
					$snapshots[ $id ] = (object) wp_parse_args( $data, (array) $snapshots[ $id ] );
				}
			}

			set_transient( $cache_key, $snapshots, DAY_IN_SECONDS );
		}

		return (array) $snapshots;
	}


	private function get_snaps() {

		if ( ! is_dir( snapshots_option( 'folder' ) ) ) {
			return array();
		}

		if ( ! function_exists( 'list_files' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$files = list_files( snapshots_option( 'folder' ), 1 );
		$files = preg_grep( '/([a-z0-9-]+)_(\d+)\/$/', $files );
		$order = $this->get_list_order();
		usort(
			$files,
			function ( $a, $b ) use ( $order ) {
				$ma = $this->get_snapshot_sort_meta( $a );
				$mb = $this->get_snapshot_sort_meta( $b );

				if ( 'created' === $order ) {
					if ( $ma['created'] !== $mb['created'] ) {
						return $mb['created'] <=> $ma['created'];
					}
					return $mb['primary'] <=> $ma['primary'];
				}

				if ( $ma['primary'] !== $mb['primary'] ) {
					return $mb['primary'] <=> $ma['primary'];
				}
				return $mb['created'] <=> $ma['created'];
			}
		);
		return $files;
	}

	/**
	 * Sort keys: newest activity first — last_used from manifest when set, else created (folder id).
	 *
	 * @param string $snapshot_dir Absolute path to snapshot directory (with trailing slash).
	 * @return array{primary: int, created: int}
	 */
	private function get_snapshot_sort_meta( $snapshot_dir ) {

		$id         = basename( trailingslashit( $snapshot_dir ) );
		$parts      = explode( '_', $id );
		$created_ts = (int) end( $parts );

		$manifest_file = trailingslashit( $snapshot_dir ) . 'manifest.json';
		$last_used     = 0;
		if ( is_file( $manifest_file ) ) {
			$decoded = json_decode( (string) file_get_contents( $manifest_file ), true );
			if ( is_array( $decoded ) && isset( $decoded['last_used'] ) ) {
				$last_used = (int) $decoded['last_used'];
			}
		}

		$primary = $last_used > 0 ? $last_used : $created_ts;

		return array(
			'primary' => $primary,
			'created' => $created_ts,
		);
	}


	private function clear_cache() {

		$last = get_transient( 'snapshot_current' );
		delete_transient( 'snapshot_list_' . $last );
		foreach ( array( 'last_used', 'created' ) as $order ) {
			delete_transient( $this->snapshot_list_cache_key( $last, $order ) );
		}
	}

	/**
	 * Public hook target: flush snapshot list transients (e.g. after settings change).
	 */
	public function clear_snapshot_list_cache() {
		$this->clear_cache();
	}


	public function backup( $name = null, $files = true, $location = null ) {
		$command = 'snapshot backup "' . esc_attr( $name ) . '"';
		if ( $files ) {
			$command .= ' --files';
		}
		if ( $location ) {
			if ( array_key_exists( 'snapshot_location', $_GET ) ) {
				$location = htmlspecialchars_decode( $_GET['snapshot_location'] );
			} else {
				$location = remove_query_arg( 'snaphot_create' );
			}
			$command .= ' --location="' . $location . '"';
		}

		$exclude_tables = snapshots_option( 'exclude_tables' );
		if ( ! empty( $exclude_tables ) ) {
			$command .= ' --exclude_tables="' . implode( ',', (array) $exclude_tables ) . '"';
		}

		$this->command( $command );
		$this->clear_cache();
	}


	public function restore( $name ) {
		$name = is_null( $name ) ? '' : basename( $name );

		// disable cron
		define( 'DISABLE_WP_CRON', true );

		$this->check_environment();

		$command = trim( 'snapshot restore ' . $name );
		$result  = $this->command( $command );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		do_action( 'snapshot_restored', $name, $result );

		$this->clear_cache();

		if ( $redirect = array_values( preg_grep( '/^Redirect to: (.*?)/', $result ) ) ) {
			$redirect = trim( str_replace( 'Redirect to:', '', $redirect[0] ) );
			return home_url( $redirect );
		}

		return remove_query_arg( 'snapshot_restore' );
	}

	public function delete( $name ) {
		$name    = is_null( $name ) ? '' : basename( $name );
		$command = trim( 'snapshot delete ' . $name );
		$result  = $this->command( $command );
		$this->clear_cache();

		return $result;
	}

	public function exec( $rawcmd ) {
		$this->may_add_paths();

		$output      = array();
		$result_code = 0;

		exec( $rawcmd, $output, $result_code );

		if ( $result_code ) {

			// add error output to the command
			$rawcmd .= ' 2>&1';

			exec( $rawcmd, $output, $result_code );

			return new \WP_Error( $result_code, implode( "\n", $output ), array( 'command' => $rawcmd ) );

			switch ( $result_code ) {

				case 1:
					$output = array( 'General error occurred. Command syntax or execution failed.' );
					break;

				case 2:
					$output = array( 'Misuse of shell builtins or invalid arguments.' );
					break;

				case 126:
					$output = array( 'Permission issue: Command invoked cannot execute.' );
					break;

				case 127:
					$output = array( 'Command not found. Please ensure the command exists and is in the PATH.' );
					break;

				case 128:
					$output = array( 'Invalid exit argument.' );
					break;

				default:
					if ( $result_code > 128 ) {
						$output = array( 'Process terminated by signal ' . ( $result_code - 128 ) );
					} else {
						$output = array( "Unknown error. Return code: $result_code" );
					}
					break;
			}
			return new \WP_Error( $result_code, implode( "\n", $output ), array( 'command' => $rawcmd ) );
		}

		// remove lines starting with "Deprecated"
		$output = preg_grep( '/^Deprecated/', $output, PREG_GREP_INVERT );
		$output = preg_grep( '/^PHP Deprecated/', $output, PREG_GREP_INVERT );
		// remove empty lines
		$output = array_filter( $output );

		return array_values( $output );
	}

	private function command( $cmd, $echo = false, $output = ARRAY_A ) {
		$cmd = trailingslashit( snapshots_option( 'cli_path' ) ) . 'wp ' . $cmd;
		if ( snapshots_option( 'cli_allow_root' ) ) {
			$cmd .= ' --allow-root';
		}

		$home_url = home_url();

		// $cmd .= ' --debug=false'; // prevent error outputs
		// $cmd .= ' --exec="echo phpversion();"'; // prevent color outputs
		$cmd .= ' --path=\'' . ABSPATH . '\'';
		// $cmd .= ' --quiet';
		// $cmd .= ' 2> /dev/null';

		$cmd .= ' --url=' . $home_url;

		// $cmd .= ' --require=' . __DIR__ . '/require.php';

		$result = $this->exec( $cmd );

		if ( $echo && is_wp_error( $result ) ) {
			$data    = $result->get_error_data();
			$code    = $result->get_error_code();
			$body    = $result->get_error_message();
			$heading = esc_html__( 'SnapShots Error', 'snapshosts' );
			$body    = '<h2>' . $heading . '</h2><pre>[' . $code . '] ' . $result->get_error_message() . '</pre><h3>Command</h3><pre>' . '<pre>' . $data['command'] . '</pre>';
			$this->error( $body, $heading, array( 'command' => 'wp cli version' ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $output !== ARRAY_A ) {
			$result = $result[0];
		}

		if ( $echo ) {
			echo $result;
		}
		return $result;
	}


	private function login_user( $user ) {

		if ( ! isset( $user->ID ) ) {
			return false;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		return $user;
	}


	private function may_add_paths() {

		static $added = false;

		if ( $added ) {
			return;
		}

		$php_path = snapshots_option( 'php_path' );
		if ( $php_path ) {
			if ( function_exists( 'putenv' ) ) {
				putenv( 'PATH=$PATH:' . $php_path );
			} else {
				$cmd = 'export PATH=$PATH:' . $php_path;
				$this->exec( $cmd );
			}
		}
		$added = true;

		return true;
	}

	private function is_exec() {

		$result = $this->exec( 'echo EXEC IS WORKING' );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		if ( is_array( $result ) ) {
			$result = $result[0];
		}
		return $result == 'EXEC IS WORKING';
	}

	public function on_activate() {

		$this->check_environment();
	}

	public function on_deactivate() {
	}


	public function check_environment( $return_on_error = false ) {

		if ( ! $this->is_exec() ) {
			$heading = esc_html__( 'SnapShots requires the "exec" method!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the %s is installed and working on your server.', 'snapshosts' ), '<a href="https://www.php.net/manual/en/function.exec.php" rel="noopener noreferrer" target="_blank">exec command</a>' );
			$this->error( $body, $heading );
		}

		$php_path = $this->exec( 'which php' );

		if ( is_wp_error( $php_path ) ) {
			$heading = esc_html__( 'SnapShots requires the php in your PATH environment!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the PHP binaries can be found in your PATH by defining the folder in %s constant.', 'snapshosts' ), '<code>SNAPSHOTS_PHP_PATH</code>' );
			$body   .= '<p>Run following command in your terminal to get the path: <br><code>which php</code></p>';

			$this->error( $body, $heading, array( 'command' => 'which php' ) );
		}

		$cli_version = $this->command( 'cli version' );

		if ( is_wp_error( $cli_version ) ) {
			$heading = esc_html__( 'SnapShots requires WP-CLI!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the command line interface %1$s is installed and working on your server. Read the official guide %2$s.', 'snapshosts' ), '<a href="https://wp-cli.org/#installing" rel="noopener noreferrer" target="_blank">WP-CLI</a>', '<a href="https://wp-cli.org/" rel="noopener noreferrer" target="_blank">' . esc_html__( 'here', 'snapshosts' ) . '</a>' );
			$this->error( $body, $heading, array( 'command' => 'wp cli version' ) );
		}

		$db_check = $this->command( 'db check' );

		if ( is_wp_error( $db_check ) ) {
			$heading = esc_html__( 'SnapShots cannot talk to your Database!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the command line interface is setup correct. Run this command in your terminal to check: %s', 'snapshosts' ), '<br><code>' . esc_html( implode( ' ', $db_check->get_error_data() ) ) . '</code>' );
			$body   .= '<p>Following error was returned:<br><code>' . $db_check->get_error_message() . '</code></p>';
			$body   .= '<p>If you are running MySQL via a socked try to add the path to the socket to the <code>DB_HOST</code> constant in your <code>wp-config.php</code> file like:</p>';
			$body   .= '<p><code>define( \'DB_HOST\', \'localhost:[ABSOLUTE_PATH_TO_YOUR_SOCKET]/mysql/mysqld.sock\' );</code></p>';
			$this->error( $body, $heading, array( 'command' => 'wp option get home' ) );

		} else {
			// get the home URL to make sure we are in the correct database
			$home_url = $this->command( 'option get home', false, false );

			$result = wp_parse_url( $home_url, PHP_URL_HOST );

			$home = wp_parse_url( home_url(), PHP_URL_HOST );

			return;
			if ( $result !== $home ) {

				// this is in place if the cron URL triggers this process
				if ( ! headers_sent() && ! isset( $_GET['snapshot_redirect'] ) ) {
					$redirect = add_query_arg( 'snapshot_redirect', 1 );
					error_log( 'redirect ' . print_r( $redirect, true ) );
					wp_redirect( $redirect );
					exit;
				}

				$heading = esc_html__( 'Your Home URLs do not match!', 'snapshosts' );
				$body    = sprintf( esc_html__( 'The `home_url()` (%1$s) returns a different result than the WP CLI command `wp option get home` (%2$s).', 'snapshosts' ), $home, $result );

				$this->error( $body, $heading, array( 'command' => $command ) );

			}
		}
	}

	public function error( $body, $heading = null, $extra = null ) {

		$output = '';

		if ( is_wp_error( $body ) ) {
			$code = $body->get_error_code();
			$data = $body->get_error_data();
			$body = $body->get_error_message();
		}

		if ( ! is_null( $heading ) ) {
			$output .= sprintf( '<h2>%s</h2>', $heading );
		}
		$output .= sprintf( '<p>%s</p>', $body );
		$output .= '<p>Please check the FAQ section <a href="https://wordpress.org/plugins/snapshots/#faq" target="_blank">here</a> for more information or open a new support topic on the WordPress Repository <a href="https://wordpress.org/support/plugin/snapshots/" target="_blank">here</a>.</p>';

		$args = array(
			'back_link' => true,
		);

		if ( is_array( $extra ) ) {
			$args = array_merge( $args, $extra );
		}

		wp_admin_notice(
			$output,
			array(
				'type'               => 'error',
				'additional_classes' => array( 'inline' ),
			)
		);

		// wp_die( $output, $heading, $args );
	}
}
