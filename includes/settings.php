<?php

namespace EverPress\Snapshots;

class Settings {

	private static $instance = null;


	private function __construct() {

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new Settings();
		}

		return self::$instance;
	}

	public function admin_init() {

		add_settings_section(
			'snapshots_section',
			__( 'Settings Section', 'snapshots-page-plugin' ),
			function () {
				echo '<p>' . esc_html__( 'Options are stored with the snapshots by default. So if you restore an old snapshot with different settings the current one will be lost.', 'snapshots' ) . '</p>';
			},
			'snapshots-page'
		);

		$settings = require_once __DIR__ . '/set.php';

		foreach ( $settings as $key => $setting ) {
			$id = 'snapshots_' . $key;
			register_setting(
				'snapshots',
				$id,
				array(
					'type'              => $setting['type'] ?? 'string',
					'sanitize_callback' => function ( $value ) use ( $setting, $id ) {

						if ( $setting['type'] === 'number' ) {
							return (int) $value;
						} elseif ( $setting['type'] === 'string' && $value === '' ) {
							return;
						}

						return $value;
					},
					'label'             => $setting['name'],
					'default'           => $setting['default'],
					'description'       => $setting['description'],
				)
			);
			add_settings_field(
				$id,
				$setting['name'],
				function () use ( $id, $key, $setting ) {
					$value        = snapshots_option( $key, $setting['default'] );
					$is_undefined = $value === null;
					$is_default   = $value === $setting['default'];
					$const        = strtoupper( $id );
					$placeholder  = $setting['default'];
					$is_const     = defined( $const );
					if ( $is_const ) {
						$placeholder = constant( $const );
					}
					switch ( $setting['type'] ) {
						case 'boolean':
							echo '<label><input type="hidden" name="' . esc_attr( $id ) . '" value="0"><input type="checkbox" name="' . esc_attr( $id ) . '" value="1" ' . checked( $value, true, false ) . ' ' . ( $is_const ? 'disabled' : '' ) . '> ' . esc_html( $setting['description'] ) . '</label>';

							break;
						case 'number':
							echo '<input type="number" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="small-text" placeholder="' . esc_attr( $placeholder ) . '" ' . ( $is_const ? 'readonly' : '' ) . '>';
							break;
						default:
							echo '<input type="text" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( $placeholder ) . '" ' . ( $is_const ? 'readonly' : '' ) . '>';
							break;
					}
					if ( $is_const ) {
						echo ' <span class="description">' . sprintf( esc_html__( 'Defined in %s constant', 'snapshots' ), '<code>' . $const . '</code>' ) . '</span>';
					}
					if ( $setting['description'] ) {
						echo '<p class="description">' . esc_html( $setting['description'] ) . '</p>';
					}
				},
				'snapshots-page',
				'snapshots_section'
			);
		}
	}

	public function admin_menu() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			'Snapshots2',
			'Snapshots2',
			'manage_options',
			'snapshots2',
			array( $this, 'render_setting' ),
			'dashicons-backup',
			99
		);
	}


	public function render_setting() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Snapshots Settings', 'snapshots' ) . '</h1>';

		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'snapshots' );
			do_settings_sections( 'snapshots-page' );
			submit_button();
			?>
		</form>
		<?

		echo '</div>';
	}

	public function render_snapshots_page() {
		?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Simple Settings Page', 'snapshots-page-plugin' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'snapshots' );
			do_settings_sections( 'snapshots-page' );
			submit_button();
			?>
		</form>
	</div>
		<?php
	}
}
