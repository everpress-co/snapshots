<?php

class Snapshots_Plugin {


	public function __construct() {

		register_activation_hook( SNAPSHOTS_FILE, array( &$this, 'on_activate' ) );
		register_deactivation_hook( SNAPSHOTS_FILE, array( &$this, 'on_deactivate' ) );

		add_action( 'admin_bar_menu', array( $this, 'toolbar_snapshots' ), 20 );
		add_action( 'init', array( $this, 'actions' ) );
	}

	public function actions() {

		if ( array_key_exists( 'snapshot_restore', $_GET ) ) {
			if ( current_user_can( 'manage_options' ) ) {

				$redirect = $this->restore( $_GET['snapshot_restore'] );

				$this->login_user( wp_get_current_user() );

				wp_redirect( $redirect );
				exit;
			}
		}

		if ( array_key_exists( 'snaphot_create', $_GET ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				$files    = snapshots_option( 'save_files' );
				$location = snapshots_option( 'save_location' );
				$this->backup( $_GET['snaphot_create'], $files, $location );
				wp_redirect( remove_query_arg( 'snaphot_create' ) );
				exit;
			}
		}
	}


	public function toolbar_snapshots( $wp_admin_bar ) {

		$files = $this->get_snaps();
		$count = count( $files );

		$title = $count ? $count : esc_html__( 'Click here to create your first Snapshot!', 'snapshots' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'snapshots',
				'title' => '<span class="ab-icon dashicons dashicons-backup" style="margin-top:2px"></span> ' . $title,
				'href'  => add_query_arg( array( 'snaphot_create' => '1' ) ),
				'meta'  => array(
					'onclick' => 'var snapshotsname = prompt("' . esc_attr__( 'Please name your Snapshot.', 'snapshots' ) . '", "' . esc_attr( get_option( 'blogname', 'snapshots' ) ) . '"); this.href=this.href.replace(\"snaphot_create=1\", \"snaphot_create="+encodeURIComponent(snapshotsname)); return !!snapshotsname;',
				),
			)
		);

		if ( $count ) {
			foreach ( $files as $i => $file ) {
				$data = json_decode( file_get_contents( $file ) );
				$wp_admin_bar->add_node(
					array(
						'id'     => 'snapshot-' . $i,
						'title'  => '<span title="' . esc_attr( sprintf( '%s ago', human_time_diff( $data->created ) ) ) . '" style="display:inline-block;width:120px;overflow:hidden; text-overflow:ellipsis;">' . esc_html( $data->name ) . '</span> <span style="display:inline-block;overflow:hidden;">' . wp_date( 'Y-m-d H:i', $data->created ) . '</span>',
						'href'   => add_query_arg( array( 'snapshot_restore' => basename( $file ) ) ),
						'parent' => 'snapshots',
						'meta'   => array(
							'html'    => '',
							'onclick' => 'return confirm(\"' . sprintf(
								esc_attr__( 'Restore this Backup from %s?' ),
								wp_date( 'Y-m-d H:i', $data->created )
							) . '\");',
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

		$files = glob( snapshots_option( 'folder' ) . '/*.json' );
		$list  = array();
		usort(
			$files,
			function( $a, $b ) {
				return filemtime( $a ) < filemtime( $b );
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

	private function command( $cmd, $echo = false ) {
		$cmd = 'cd ' . SNAPSHOTS_CLI_PATH . '; wp ' . $cmd;
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
