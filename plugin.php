<?php

class Snapshots_Plugin {


	public function __construct() {

		register_activation_hook( SNAPSHOTS_FILE, array( &$this, 'on_activate' ) );
		register_deactivation_hook( SNAPSHOTS_FILE, array( &$this, 'on_deactivate' ) );

		add_action( 'init', array( $this, 'actions' ) );
		add_action( 'init', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_bar_menu', array( $this, 'toolbar_snapshots' ), 20 );

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
			$this->backup( $_GET['snaphot_create'], $files, $location );
			wp_redirect( remove_query_arg( 'snaphot_create' ) );
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

		$title = $count ? $count : esc_html__( 'Click here to create your first Snapshot!', 'snapshots' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'snapshots',
				'title' => '<span class="ab-icon dashicons dashicons-backup" style="margin-top:2px"></span> ' . $title,
				'href'  => add_query_arg( 'snaphot_create', '1' ),

			)
		);

		if ( $count ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'snapshot-search',
					'title'  => '<span class="search-snapshot"><input type="search" placeholder="' . esc_attr__( 'Search Snapshots...', 'snapshots' ) . '"></span>',
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
		$files = preg_grep( '/([a-z-]+)_(\d+)\/$/', $files );
		usort(
			$files,
			function( $a, $b ) {
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
			$command .= ' --location="' . remove_query_arg( 'snaphot_create' ) . '"';
		}
		$this->command( $command );
	}


	public function restore( $name ) {
		$name    = is_null( $name ) ? '' : ' ' . basename( $name );
		$command = 'snapshot restore' . $name;
		$result  = $this->command( $command );

		if ( $redirect = array_values( preg_grep( '/^Redirect to: (.*?)/', $result ) ) ) {
			$redirect = trim( str_replace( 'Redirect to:', '', $redirect[0] ) );
			return home_url( $redirect );
		}

		return remove_query_arg( 'snapshot_restore' );

	}

	public function delete( $name ) {
		$name    = is_null( $name ) ? '' : ' ' . basename( $name );
		$command = 'snapshot delete' . $name;
		$result  = $this->command( $command );

		return $result;

	}

	private function command( $cmd, $echo = false ) {
		$cmd = trailingslashit( SNAPSHOTS_CLI_PATH ) . 'wp ' . $cmd;
		if ( snapshots_option( 'allow_root' ) ) {
			$cmd .= ' --allow-root';
		}
		$cmd .= ' --path=\'' . ABSPATH . '\'';
		exec( $cmd, $output );
		if ( $echo ) {
			echo $output;
		}
		return $output;
	}


	private function login_user( $user ) {

		if ( ! isset( $user->ID ) ) {
			return false;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		return $user;
	}

	private function is_exec() {
		return @exec( 'echo EXEC IS WORKING' ) == 'EXEC IS WORKING';
	}

	public function on_activate() {

		if ( ! $this->is_exec() ) {
			$heading = esc_html__( 'Snapshots requires the "exec" method!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the %s is installed and working on your server.', 'snapshosts' ), '<a href="https://www.php.net/manual/en/function.exec.php" rel="noopener noreferrer" target="_blank">exec command</a>' );

			die( '<div style="font-family:sans-serif;"><strong>' . $heading . '</strong><p>' . $body . '</p></div>' );
		}

		if ( ! $this->command( 'cli version' ) ) {
			$heading = esc_html__( 'Snapshots requires WP-CLI!', 'snapshosts' );
			$body    = sprintf( esc_html__( 'Please make sure the command line interface %1$s is installed and working on your server. Read the official guide %2$s.', 'snapshosts' ), '<a href="https://wp-cli.org/#installing" rel="noopener noreferrer" target="_blank">WP-CLI</a>', '<a href="https://wp-cli.org/" rel="noopener noreferrer" target="_blank">' . esc_html__( 'here', 'snapshosts' ) . '</a>' );
			die( '<div style="font-family:sans-serif;"><strong>' . $heading . '</strong><p>' . $body . '</p></div>' );
		}

	}

	public function on_deactivate() {

	}

}
