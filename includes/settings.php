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
			'',
			function () {

				$result = Plugin::get_instance()->exec( 'which php' );
				if ( is_wp_error( $result ) ) {
					wp_admin_notice(
						'Not able to use PHP: ' . $result->get_error_message(),
						array(
							'type'               => 'error',
							'additional_classes' => array( 'inline' ),
						)
					);
				}
				wp_admin_notice(
					esc_html__( 'Options are global! They will not change if you reload a SnapShot.', 'snapshots' ),
					array(
						'type'               => 'info',
						'additional_classes' => array( 'inline' ),
					)
				);
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
					'type'              => $setting['type'],
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
					$type         = $setting['type'];
					$is_undefined = $value === null;
					$is_default   = $value === $setting['default'];
					$const        = strtoupper( $id );
					$placeholder  = $setting['default'];
					$is_const     = defined( $const );
					if ( $is_const ) {
						$placeholder = constant( $const );
					}
					if ( $is_const ) {
						echo ' <div class="description">' . sprintf( esc_html__( 'Defined in %s constant', 'snapshots' ), '<code>' . $const . '</code>' ) . '</div>';
					} else {
						$output_value = $type === 'boolean' ? ( $value ? 'true' : 'false' ) : ( $type === 'number' ? $value : '"' . $value . '"' );
						echo ' <div class="description">' . sprintf( esc_html__( 'Define as: %s', 'snapshots' ), '<code>define("' . $const . '", ' . esc_attr( $output_value ) . ');</code>' ) . '</div>';
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
					if ( $setting['description'] && $setting['type'] !== 'boolean' ) {
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

		add_options_page(
			'Snapshots',
			'Snapshots',
			'manage_options',
			'snapshots-settings',
			array( $this, 'render' ),
			99
		);
	}



	public function render() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Snapshots Settings', 'snapshots' ) . '</h1>';
		snapshots_get_nav( 'settings' );

		Plugin::get_instance()->check_environment();

		?>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'snapshots' );
			do_settings_sections( 'snapshots-page' );
			submit_button();
			?>

		</form>
		<?php

		add_action( 'admin_footer_text', array( $this, 'admin_footer_text' ) );
		echo '</div>';
	}

	public function admin_footer_text( $default ) {
		/* Translators: %1$s: Plugin Name, %2$s: Rating URL */
		return sprintf( esc_html__( 'If you like %1$s please leave a %2$s rating. Thanks in advance!', 'snapshots' ), '<strong>SnapShots</strong>', '<a href="https://wordpress.org/support/view/plugin-reviews/snapshots?filter=5#new-post" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a>' );
	}
}
