<?php

namespace EverPress\Snapshots;

class Table extends \WP_List_Table {


	public function get_columns() {
		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'name'     => __( 'Name', 'snapshots' ),
			'created'  => __( 'Created', 'snapshots' ),
			'location' => __( 'Location', 'snapshots' ),
			'actions'  => __( 'Actions', 'snapshots' ),
		);

		return $columns;
	}

	public function column_cb( $item ) {

		return sprintf(
			'<input id="cb-select-%s" type="checkbox" name="snapshot[]" value="%s" />',
			$item['id'],
			$item['id']
		);
	}

	public function column_actions( $item ) {

		$actions = array(
			'restore' => sprintf( '<a href="?page=%s&action=%s&snapshot=%s">%s</a>', $_REQUEST['page'], 'restore', $item['id'], __( 'Restore', 'snapshots' ) ),
			'delete'  => sprintf( '<a href="?page=%s&action=%s&snapshot=%s">%s</a>', $_REQUEST['page'], 'delete', $item['id'], __( 'Delete', 'snapshots' ) ),
		);

		return $this->row_actions( $actions );
	}

	public function column_created( $item ) {

		$timestamp = strtotime( $item['created'] );

		if ( $timestamp > time() - DAY_IN_SECONDS * 2 ) {
			$human_time = sprintf( __( '%s ago', 'snapshots' ), human_time_diff( $timestamp ) );
		} else {
			$human_time = wp_date( 'Y-m-d H:i:s', $timestamp );
		}

		return $human_time;
	}

	public function column_default( $item, $column_name ) {

		if ( isset( $item[ $column_name ] ) ) {
			return $item[ $column_name ];
		}

		return 'xx';
	}

	public function table_data() {

		$snaps = Plugin::get_instance()->get_snapshots();

		foreach ( $snaps as $id => $snap ) {

			$data[] = array(
				'id'       => $id,
				'name'     => $snap->name,
				'created'  => wp_date( 'Y-m-d H:i:s', $snap->created ),
				'location' => $snap->location,
			);
		}

		return $data;
	}


	public function prepare_items() {
		$columns     = $this->get_columns();
		$hidden      = array();
		$sortable    = $this->get_sortable_columns();
		$data        = $this->table_data();
		$perPage     = 50;
		$currentPage = $this->get_pagenum();
		$totalItems  = count( $data );
		$this->set_pagination_args(
			array(
				'total_items' => $totalItems,
				'per_page'    => $perPage,
			)
		);
		$data                  = array_slice( $data, ( ( $currentPage - 1 ) * $perPage ), $perPage );
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;
	}
}
