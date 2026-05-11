<?php

namespace EverPress\Snapshots;

class Table extends \WP_List_Table {

	/**
	 * Constructor.
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
	 * @return array<string, string>
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'snapshots' ),
		);
	}

	/**
	 * Top bulk dropdown uses `action`; bottom uses `action2`. The parent method only considers `action`.
	 *
	 * @return string|false
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
	 * Handle bulk actions when Apply is clicked (run from Plugin::snapshots_tools_handle_bulk_request).
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		if ( 'delete' !== $action ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified with check_admin_referer() in process_bulk_action().
		$ids = isset( $_REQUEST['snapshot'] ) ? (array) wp_unslash( $_REQUEST['snapshot'] ) : array();
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

	public function column_cb( $item ) {

		return sprintf(
			'<input id="cb-select-%s" type="checkbox" name="snapshot[]" value="%s" />',
			esc_attr( $item['id'] ),
			esc_attr( $item['id'] )
		);
	}


	public function column_created( $item ) {

		$timestamp = ( $item['created'] );

		if ( $timestamp > time() - DAY_IN_SECONDS ) {
			$human_time = sprintf( __( '%s ago', 'snapshots' ), human_time_diff( $timestamp ) );
		} else {
			$human_time = wp_date( 'Y-m-d H:i', $timestamp );
		}

		return $human_time;
	}

	public function column_last_used( $item ) {

		$timestamp = isset( $item['last_used'] ) ? (int) $item['last_used'] : 0;

		if ( $timestamp < 1 ) {
			return '—';
		}

		if ( $timestamp > time() - DAY_IN_SECONDS ) {
			return sprintf( __( '%s ago', 'snapshots' ), human_time_diff( $timestamp ) );
		}

		return wp_date( 'Y-m-d H:i', $timestamp );
	}

	public function column_name( $item ) {

		$actions = array(
			'restore' => sprintf( '<a href="%s">%s</a>', esc_url( $item['restore'] ), __( 'Restore', 'snapshots' ) ),
			'delete'  => sprintf( '<a href="%s">%s</a>', esc_url( $item['delete'] ), __( 'Delete', 'snapshots' ) ),
		);

		return sprintf( '<strong>%s</strong>', $item['name'] ) . $this->row_actions( $actions );
	}

	public function column_default( $item, $column_name ) {

		if ( isset( $item[ $column_name ] ) ) {
			return $item[ $column_name ];
		}

		return 'xx';
	}

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


	public function prepare_items() {

		$columns    = $this->get_columns();
		$hidden     = array();
		$sortable   = $this->get_sortable_columns();
		$data       = $this->table_data();
		$perPage    = 50;
		$totalItems = count( $data );
		$this->set_pagination_args(
			array(
				'total_items' => $totalItems,
				'per_page'    => $perPage,
			)
		);
		/*
		 * set_pagination_args() must run before get_pagenum() so totals exist for clamping
		 * and invalid ?paged stays out of array_slice().
		 */
		$currentPage           = $this->get_pagenum();
		$data                  = array_slice( $data, ( ( $currentPage - 1 ) * $perPage ), $perPage );
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;
	}
}
