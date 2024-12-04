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
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new Plugin();
		}

		return self::$instance;
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

		wp_enqueue_script( 'snapshots-script', plugin_dir_url( SNAPSHOTS_FILE ) . 'assets/script.js', array(), false, true );
		wp_enqueue_style( 'snapshots-style', plugin_dir_url( SNAPSHOTS_FILE ) . 'assets/style.css', array() );

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

		$snapshots = $this->get_snaps();
		$count     = count( $snapshots );
		$last      = get_transient( 'snapshot_current' );

		$title = $count ? $count : esc_html__( 'Click here to create your first Snapshot!', 'snapshots' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'snapshots',
				'title' => '<span class="ab-icon dashicons dashicons-backup" style="margin-top:2px"></span>' . $title . '<span class="snapshot-extra-title" title="">' . $last . '</span>',
				'href'  => add_query_arg( 'snaphot_create', '1' ),

			)
		);

		if ( $count ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'snapshot-search',
					'title'  => '<span class="search-snapshot"><label for="snapshost_search">' . esc_attr__( 'Search SnapShots...', 'snapshots' ) . '</label><input id="snapshost_search" type="search" placeholder="' . esc_attr__( 'Search SnapShots...', 'snapshots' ) . '"></span>',
					'parent' => 'snapshots',
				)
			);

			foreach ( $snapshots as $i => $snapshot ) {
				$file = trailingslashit( $snapshot ) . 'manifest.json';
				if ( ! file_exists( $file ) ) {
					$data = array(
						'name'    => esc_html__( 'SNAPSHOT IS BROKEN!', 'snapshots' ),
						'created' => (int) explode( '_', basename( $snapshot ) )[1],
					);
				} else {
					$data = json_decode( file_get_contents( $file ), true );
				}
				$wp_admin_bar->add_node(
					array(
						'id'     => 'snapshot-' . $i,
						'title'  => '<span class="restore-snapshot" title="' . sprintf( esc_attr__( 'created %s ago', 'snapshots' ), human_time_diff( $data['created'] ) ) . ' - ' . wp_date( 'Y-m-d H:i', $data['created'] ) . '" data-date="' . wp_date( 'Y-m-d H:i', $data['created'] ) . '">' . esc_html( $data['name'] ) . '</span>',
						'href'   => add_query_arg( array( 'snapshot_restore' => basename( $snapshot ) ) ),
						'parent' => 'snapshots',
						'meta'   => array(
							'rel'  => $data['name'] . ' ' . strtolower( $data['name'] ),
							'html' => '<div><a class="delete-snapshot" title="' . esc_attr( sprintf( 'delete %s', $data['name'] ) ) . '" data-name="' . esc_attr( $data['name'] ) . '" data-date="' . wp_date( 'Y-m-d H:i', $data['created'] ) . '" href="' . add_query_arg( array( 'snapshot_delete' => basename( $snapshot ) ) ) . '">&times;</a></div>',

						),
					)
				);
			}
		}
	}


	public function get_snaps() {

		if ( ! is_dir( snapshots_option( 'folder' ) ) ) {
			return array();
		}

		if ( ! function_exists( 'list_files' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$files = list_files( snapshots_option( 'folder' ), 1 );
		$files = preg_grep( '/([a-z0-9-]+)_(\d+)\/$/', $files );
		usort(
			$files,
			function ( $a, $b ) {
				$a = (int) explode( '_', basename( $a ) )[1];
				$b = (int) explode( '_', basename( $b ) )[1];
				return $b <=> $a;
			}
		);
		return $files;
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
		$this->command( $command );
	}


	public function restore( $name ) {
		$name = is_null( $name ) ? '' : basename( $name );

		// disable cron
		define( 'DISABLE_WP_CRON', true );

		$this->check_environment();

		$command = trim( 'snapshot restore ' . $name );
		$result  = $this->command( $command );

		do_action( 'snapshot_restored', $name, $result );

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

		return $result;
	}

	private function exec( $rawcmd ) {

		$this->may_add_php_path();

		exec( $rawcmd, $output, $error );

		if ( $error ) {
			return new \WP_Error( $error, implode( "\n", $output ), array( 'command' => $rawcmd ) );
		}

		return $output;
	}

	private function command( $cmd, $echo = false, $output = ARRAY_A ) {
		$cmd = trailingslashit( snapshots_option( 'cli_path' ) ) . 'wp ' . $cmd;
		if ( snapshots_option( 'allow_root' ) ) {
			$cmd .= ' --allow-root';
		}
		$cmd .= ' --debug=false'; // prevent error outputs
		$cmd .= ' --path=\'' . ABSPATH . '\' 2>&1';

		$result = $this->exec( $cmd );

		if ( is_wp_error( $result ) ) {
			$data    = $result->get_error_data();
			$code    = $result->get_error_code();
			$body    = $result->get_error_message();
			$heading = esc_html__( 'SnapShots Error', 'snapshosts' );
			$body    = '<h2>' . $heading . '</h2><pre>[' . $code . '] ' . $result->get_error_message() . '</pre><h3>Command</h3><pre>' . '<pre>' . $data['command'] . '</pre>';
			$this->error( $body, $heading, array( 'command' => 'wp cli version' ) );

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


	private function may_add_php_path() {

		static $added = false;

		if ( $added ) {
			return;
		}

		$php_path = snapshots_option( 'php_path' );
		if ( ! $php_path ) {
			return;
		}

		if ( ! $php_path ) {
			return;
		}

		$added = true;

		if ( function_exists( 'putenv' ) ) {
			putenv( 'PATH=$PATH:' . $php_path );
		} else {
			$cmd = 'export PATH=$PATH:' . $php_path;
			$this->exec( $cmd );
		}

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

		if ( ! $this->is_exec() ) {
			$heading = esc_html__( 'SnapShots requires the "exec" method!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the %s is installed and working on your server.', 'snapshosts' ), '<a href="https://www.php.net/manual/en/function.exec.php" rel="noopener noreferrer" target="_blank">exec command</a>' );
			$this->error( $body, $heading );
		}

		if ( is_wp_error( $this->exec( 'which php' ) ) ) {
			$heading = esc_html__( 'SnapShots requires the php in your PATH environment!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the PHP binaries can be found in your PATH by defining the folder in %s constant.', 'snapshosts' ), '<code>SNAPSHOTS_PHP_PATH</code>' );
			$this->error( $body, $heading, array( 'command' => 'which php' ) );
		}

		if ( ! $this->command( 'cli version' ) ) {
			$heading = esc_html__( 'SnapShots requires WP-CLI!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the command line interface %1$s is installed and working on your server. Read the official guide %2$s.', 'snapshosts' ), '<a href="https://wp-cli.org/#installing" rel="noopener noreferrer" target="_blank">WP-CLI</a>', '<a href="https://wp-cli.org/" rel="noopener noreferrer" target="_blank">' . esc_html__( 'here', 'snapshosts' ) . '</a>' );
			$this->error( $body, $heading, array( 'command' => 'wp cli version' ) );
		}

		$this->check_environment();
	}

	public function on_deactivate() {
	}


	private function check_environment() {

		// get the home URL to make sure we are in the correct database
		$command = trim( 'option get home' );

		$result = $this->command( $command, false, false );

		$result = wp_parse_url( $result, PHP_URL_HOST );

		$home = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( $result !== $home ) {

			// this is in place if the cron URL triggers this process
			if ( ! isset( $_GET['snapshot_redirect'] ) ) {
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

	public function error( $body, $heading = null, $extra = null ) {

		$output = '';

		if ( is_wp_error( $body ) ) {
			$code = $body->get_error_code();
			$data = $body->get_error_data();
			$body = $body->get_error_message();
		}

		if ( ! is_null( $heading ) ) {
			$output .= sprintf( '<h2><code>[SnapShots]</code> %s</h2>', $heading );
		}
		$output .= sprintf( '<p>%s</p>', $body );
		$output .= '<p>Please check the FAQ section <a href="https://wordpress.org/plugins/snapshots/#faq" target="_blank">here</a> for more information or open a new support topic on the WordPress Repository <a href="https://wordpress.org/support/plugin/snapshots/" target="_blank">here</a>.</p>';

		$args = array(
			'back_link' => true,
		);

		if ( is_array( $extra ) ) {
			$args = array_merge( $args, $extra );
		}

		wp_die( $output, $heading, $args );
	}
}
