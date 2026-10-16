<?php
/**
 * Admin list table for snapshots on the Tools screen.
 *
 * @package Snapshots
 */

namespace EverPress\Snapshots;

/**
 * WP_List_Table implementation for managing snapshots.
 *
 * @since [Unreleased]
 */
class Table extends \WP_List_Table {

	/**
	 * Configures list table arguments for the Tools screen.
	 *
	 * @since [Unreleased]
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'snapshot',
				'plural'   => 'snapshots',
				'ajax'     => false,

				/*
				 * Must match Tools submenu screen id (tools_page_{$menu_slug}).
				 */
				'screen'   => 'tools_page_snapshots',
			)
		);
	}

	/**
	 * Bulk actions dropdown (WordPress list table convention).
	 *
	 * @since [Unreleased]
	 *
	 * @return array<string, string> Action slug to label map.
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'snapshots' ),
		);
	}

	/**
	 * Resolves the active bulk action from top or bottom dropdowns.
	 *
	 * @since [Unreleased]
	 *
	 * @return string|false Action slug or false when none selected.
	 */
	public function current_action() {
		if ( isset( $_REQUEST['filter_action'] ) && ! empty( $_REQUEST['filter_action'] ) ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce checked in snapshots_tools_handle_bulk_request() before process_bulk_action().
		if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			return sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		}

		if ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			return sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return false;
	}

	/**
	 * Deletes selected snapshots and redirects with a status query arg.
	 *
	 * @since [Unreleased]
	 *
	 * @return void
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		if ( 'delete' !== $action ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified with check_admin_referer() in process_bulk_action().
		$ids = isset( $_REQUEST['snapshot'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST['snapshot'] ) ) : array();
		if ( empty( $ids ) ) {
			return;
		}

		$deleted = 0;
		$failed  = 0;
		$plugin  = Plugin::get_instance();

		foreach ( $ids as $id_raw ) {
			$id = sanitize_text_field( wp_unslash( $id_raw ) );
			if ( '' === $id ) {
				continue;
			}
			$result = $plugin->delete( $id );
			if ( is_wp_error( $result ) ) {
				++$failed;
			} else {
				++$deleted;
			}
		}

		if ( ! $deleted && ! $failed ) {
			return;
		}

		$sendback = admin_url( 'tools.php?page=snapshots' );
		$sendback = remove_query_arg(
			array(
				'paged',
				'snapshots_bulk_deleted',
				'snapshots_bulk_failed',
			),
			$sendback
		);

		if ( $deleted > 0 ) {
			$sendback = add_query_arg( 'snapshots_bulk_deleted', $deleted, $sendback );
		}
		if ( $failed > 0 ) {
			$sendback = add_query_arg( 'snapshots_bulk_failed', $failed, $sendback );
		}

		wp_safe_redirect( $sendback );
		exit;
	}

	/**
	 * Defines columns shown in the list table.
	 *
	 * @since [Unreleased]
	 *
	 * @return array<string, string> Column slug to heading map.
	 */
	public function get_columns() {
		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'name'      => __( 'Name', 'snapshots' ),
			'created'   => __( 'Created', 'snapshots' ),
			'last_used' => __( 'Last used', 'snapshots' ),
			'location'  => __( 'Location', 'snapshots' ),
		);

		return $columns;
	}

	/**
	 * Renders the checkbox column for a row.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Checkbox HTML.
	 */
	public function column_cb( $item ) {

		return sprintf(
			'<input id="cb-select-%s" type="checkbox" name="snapshot[]" value="%s" />',
			esc_attr( $item['id'] ),
			esc_attr( $item['id'] )
		);
	}

	/**
	 * Renders the created timestamp column.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Human-readable created time.
	 */
	public function column_created( $item ) {

		$timestamp = ( $item['created'] );

		if ( $timestamp > time() - DAY_IN_SECONDS ) {
			/* translators: %s: Human-readable time difference. */
			$human_time = sprintf( __( '%s ago', 'snapshots' ), human_time_diff( $timestamp ) );
		} else {
			$human_time = wp_date( 'Y-m-d H:i', $timestamp );
		}

		return $human_time;
	}

	/**
	 * Renders the last-used timestamp column.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Human-readable last-used time or an em dash.
	 */
	public function column_last_used( $item ) {

		$timestamp = isset( $item['last_used'] ) ? (int) $item['last_used'] : 0;

		if ( $timestamp < 1 ) {
			return '—';
		}

		if ( $timestamp > time() - DAY_IN_SECONDS ) {
			/* translators: %s: Human-readable time difference. */
			return sprintf( __( '%s ago', 'snapshots' ), human_time_diff( $timestamp ) );
		}

		return wp_date( 'Y-m-d H:i', $timestamp );
	}

	/**
	 * Renders the snapshot name with row actions.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Name cell HTML.
	 */
	public function column_name( $item ) {

		$actions = array(
			'restore' => sprintf( '<a href="%s">%s</a>', esc_url( $item['restore'] ), __( 'Restore', 'snapshots' ) ),
			'delete'  => sprintf( '<a href="%s">%s</a>', esc_url( $item['delete'] ), __( 'Delete', 'snapshots' ) ),
		);

		return sprintf( '<strong>%s</strong>', esc_html( $item['name'] ) ) . $this->row_actions( $actions );
	}

	/**
	 * Renders a default column value when no dedicated handler exists.
	 *
	 * @since [Unreleased]
	 *
	 * @param array<string, mixed> $item        Row data.
	 * @param string               $column_name Column slug.
	 * @return string Cell contents.
	 */
	public function column_default( $item, $column_name ) {

		if ( isset( $item[ $column_name ] ) ) {
			return esc_html( (string) $item[ $column_name ] );
		}

		return 'xx';
	}

	/**
	 * Builds row data from registered snapshots.
	 *
	 * @since [Unreleased]
	 *
	 * @return array<int, array<string, mixed>> List table rows.
	 */
	public function table_data() {

		$data  = array();
		$snaps = Plugin::get_instance()->get_snapshots();

		foreach ( $snaps as $id => $snap ) {

			$data[] = array(
				'id'        => $id,
				'name'      => $snap->name,
				'created'   => $snap->created,
				'last_used' => $snap->last_used,
				'location'  => $snap->location,
				'restore'   => $snap->restore,
				'delete'    => $snap->delete,
			);
		}

		return $data;
	}

	/**
	 * Loads columns, pagination, and items for display.
	 *
	 * @since [Unreleased]
	 *
	 * @return void
	 */
	public function prepare_items() {

		$columns     = $this->get_columns();
		$hidden      = array();
		$sortable    = $this->get_sortable_columns();
		$data        = $this->table_data();
		$per_page    = 50;
		$total_items = count( $data );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		/*
		 * set_pagination_args() must run before get_pagenum() so totals exist for clamping
		 * and invalid ?paged stays out of array_slice().
		 */
		$current_page          = $this->get_pagenum();
		$data                  = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;
	}
}
