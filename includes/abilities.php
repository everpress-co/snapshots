<?php
/**
 * WordPress Abilities API registration for SnapShots.
 *
 * Soft-depends on WordPress 6.9+ (Abilities API). Older installs skip registration.
 *
 * @package Snapshots
 */

namespace EverPress\Snapshots;

/**
 * Registers list, backup, restore, and delete abilities.
 *
 * @since [Unreleased]
 */
class Abilities {

	/**
	 * Singleton instance.
	 *
	 * @since [Unreleased]
	 *
	 * @var Abilities|null
	 */
	private static $instance = null;

	/**
	 * Hooks category and ability registration when the Abilities API exists.
	 *
	 * @since [Unreleased]
	 */
	private function __construct() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @since [Unreleased]
	 *
	 * @return Abilities
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the SnapShots ability category.
	 *
	 * @since [Unreleased]
	 *
	 * @return void
	 */
	public function register_categories() {
		wp_register_ability_category(
			'snapshots',
			array(
				'label'       => __( 'SnapShots', 'snapshots' ),
				'description' => __( 'Site snapshots for development rollbacks.', 'snapshots' ),
			)
		);
	}

	/**
	 * Registers list, backup, restore, and delete abilities.
	 *
	 * @since [Unreleased]
	 *
	 * @return void
	 */
	public function register_abilities() {
		wp_register_ability(
			'snapshots/list',
			array(
				'label'               => __( 'List snapshots', 'snapshots' ),
				'description'         => __(
					'Lists saved SnapShots on this site. Each item includes name (display label), file (folder id such as before-task_1587039434), created, and past. Use the exact file value with snapshots/restore or snapshots/delete. Prefer this before creating or restoring a snapshot.',
					'snapshots'
				),
				'category'            => 'snapshots',
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => (object) array(),
					'properties'           => array(
						'name' => array(
							'type'        => 'string',
							'description' => __( 'Optional name or slug prefix to filter the list.', 'snapshots' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'    => array(
								'type'        => 'string',
								'description' => __( 'Display name of the snapshot.', 'snapshots' ),
							),
							'file'    => array(
								'type'        => 'string',
								'description' => __( 'Folder id for restore and delete.', 'snapshots' ),
							),
							'created' => array(
								'type'        => 'string',
								'description' => __( 'Created datetime (site timezone).', 'snapshots' ),
							),
							'past'    => array(
								'type'        => 'string',
								'description' => __( 'Relative time since creation.', 'snapshots' ),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_list' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'snapshots/backup',
			array(
				'label'               => __( 'Create snapshot', 'snapshots' ),
				'description'         => __(
					'Creates a named development snapshot of the database and optionally the uploads folder. Use a clear name tied to the task (for example Before experiment). Does not change live site content, but may permanently delete older snapshots that share the same name once the configured max_shots retention limit is exceeded. After success, prefer the returned file id for a later restore.',
					'snapshots'
				),
				'category'            => 'snapshots',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'     => array(
							'type'        => 'string',
							'description' => __( 'Human-readable snapshot name. Must not begin with a hyphen.', 'snapshots' ),
							'minLength'   => 1,
						),
						'files'    => array(
							'type'        => 'boolean',
							'description' => __( 'Include the uploads directory. Default true.', 'snapshots' ),
							'default'     => true,
						),
						'location' => array(
							'type'        => 'string',
							'description' => __( 'Optional return path or URL stored in the snapshot manifest.', 'snapshots' ),
						),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array(
							'type' => 'boolean',
						),
						'file'    => array(
							'type'        => 'string',
							'description' => __( 'Folder id of the new snapshot.', 'snapshots' ),
						),
						'message' => array(
							'type' => 'string',
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_backup' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'snapshots/restore',
			array(
				'label'               => __( 'Restore snapshot', 'snapshots' ),
				'description'         => __(
					'Restores a snapshot by folder id (file from snapshots/list) or slug. Prefer the exact file id. Overwrites the database and, when the snapshot included files, the uploads folder. Does not restore plugins, themes, or WordPress core. Only restore a snapshot you created for the current task, or when the user explicitly asks.',
					'snapshots'
				),
				'category'            => 'snapshots',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'        => 'string',
							'description' => __( 'Snapshot folder id (preferred) or name slug from snapshots/list.', 'snapshots' ),
							'minLength'   => 1,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array(
							'type' => 'boolean',
						),
						'redirect' => array(
							'type'        => 'string',
							'description' => __( 'Suggested URL after restore, when available.', 'snapshots' ),
						),
						'message'  => array(
							'type' => 'string',
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_restore' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'snapshots/delete',
			array(
				'label'               => __( 'Delete snapshot', 'snapshots' ),
				'description'         => __(
					'Permanently deletes a snapshot on this server by folder id (file from snapshots/list) or slug. Only delete when the user asks. Deletion cannot be undone.',
					'snapshots'
				),
				'category'            => 'snapshots',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'        => 'string',
							'description' => __( 'Snapshot folder id (preferred) or name slug from snapshots/list.', 'snapshots' ),
							'minLength'   => 1,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array(
							'type' => 'boolean',
						),
						'message' => array(
							'type' => 'string',
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_delete' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	/**
	 * Permission check shared by all SnapShots abilities.
	 *
	 * @since [Unreleased]
	 *
	 * @param mixed $input Ability input (unused).
	 * @return bool
	 */
	public function can_manage( $input = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return current_user_can( 'manage_options' );
	}

	/**
	 * Lists snapshots in the CLI-shaped field set.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed>|null $input Optional filter input.
	 * @return array<int, array{name:string,file:string,created:string,past:string}>
	 */
	public function execute_list( $input = null ) {
		if ( is_object( $input ) ) {
			$input = (array) $input;
		}
		$input  = is_array( $input ) ? $input : array();
		$filter = '';
		if ( isset( $input['name'] ) && is_string( $input['name'] ) && '' !== $input['name'] ) {
			$filter = sanitize_title( $input['name'] );
			if ( '' === $filter ) {
				// Fall back to a safe alphanumeric prefix when sanitize_title empties the value.
				$filter = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $input['name'] ) );
			}
		}

		return Plugin::get_instance()->list_snapshot_rows( $filter );
	}

	/**
	 * Creates a snapshot.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array{success:bool,file?:string,message:string}|\WP_Error
	 */
	public function execute_backup( $input = null ) {
		if ( is_object( $input ) ) {
			$input = (array) $input;
		}
		$input = is_array( $input ) ? $input : array();

		// Gotcha: property-level schema defaults are not injected into $input.
		if ( ! isset( $input['name'] ) || ! is_string( $input['name'] ) || '' === $input['name'] ) {
			return new \WP_Error( 'snapshots_missing_name', __( 'A snapshot name is required.', 'snapshots' ) );
		}

		$name = sanitize_text_field( $input['name'] );
		if ( '' === $name ) {
			return new \WP_Error( 'snapshots_missing_name', __( 'A snapshot name is required.', 'snapshots' ) );
		}

		$files = true;
		if ( array_key_exists( 'files', $input ) && null !== $input['files'] ) {
			$files = (bool) $input['files'];
		}

		$location = null;
		if ( isset( $input['location'] ) && is_string( $input['location'] ) && '' !== $input['location'] ) {
			$location = sanitize_text_field( $input['location'] );
		}

		return Plugin::get_instance()->backup( $name, $files, $location );
	}

	/**
	 * Restores a snapshot.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array{success:bool,redirect?:string,message:string}|\WP_Error
	 */
	public function execute_restore( $input = null ) {
		if ( is_object( $input ) ) {
			$input = (array) $input;
		}
		$input = is_array( $input ) ? $input : array();

		if ( ! isset( $input['id'] ) || ! is_string( $input['id'] ) || '' === $input['id'] ) {
			return new \WP_Error( 'snapshots_missing_id', __( 'A snapshot id is required.', 'snapshots' ) );
		}

		$id = sanitize_text_field( $input['id'] );
		if ( '' === $id ) {
			return new \WP_Error( 'snapshots_missing_id', __( 'A snapshot id is required.', 'snapshots' ) );
		}

		$result = Plugin::get_instance()->restore( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'  => true,
			'redirect' => is_string( $result ) ? $result : '',
			'message'  => __( 'Snapshot restored.', 'snapshots' ),
		);
	}

	/**
	 * Deletes a snapshot.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array{success:bool,message:string}|\WP_Error
	 */
	public function execute_delete( $input = null ) {
		if ( is_object( $input ) ) {
			$input = (array) $input;
		}
		$input = is_array( $input ) ? $input : array();

		if ( ! isset( $input['id'] ) || ! is_string( $input['id'] ) || '' === $input['id'] ) {
			return new \WP_Error( 'snapshots_missing_id', __( 'A snapshot id is required.', 'snapshots' ) );
		}

		$id = sanitize_text_field( $input['id'] );
		if ( '' === $id ) {
			return new \WP_Error( 'snapshots_missing_id', __( 'A snapshot id is required.', 'snapshots' ) );
		}

		$result = Plugin::get_instance()->delete( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'message' => __( 'Snapshot deleted.', 'snapshots' ),
		);
	}
}
