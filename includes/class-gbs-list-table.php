<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GBS_List_Table extends WP_List_Table {

	private $data = [];

	public function __construct( $data ) {
		parent::__construct([
			'singular' => 'lead',
			'plural'   => 'leads',
			'ajax'     => false
		]);
		$this->data = $data;
	}

	public function get_columns() {
		return [
			'cb'          => '<input type="checkbox" />',
			'name'        => 'Name',
			'category'    => 'Category',
			'website'     => 'Website',
			'phone'       => 'Phone',
			'rating'      => 'Rating',
			'reviews'     => '# Reviews',
			'address'     => 'Address',
			'about'       => 'About',
		];
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="lead[]" value="%s" />', $item['id'] );
	}

	public function column_name( $item ) {
		return esc_html( $item['name'] );
	}

	public function column_website( $item ) {
		if ( ! empty( $item['website'] ) ) {
			return sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $item['website'] ), esc_html( $item['website'] ) );
		}
		return '';
	}

	public function column_about( $item ) {
		return wp_trim_words( $item['about'], 15, '…' );
	}

	public function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] ?? '' );
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = [
			'name' => ['name', true],
			'rating' => ['rating', true],
			'reviews' => ['reviews', true],
		];

		$this->_column_headers = [$columns, $hidden, $sortable];

		usort( $this->data, function( $a, $b ) {
			$orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'name';
			$order   = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
			$result  = strcmp( $a[$orderby], $b[$orderby] );
			return ($order === 'asc') ? $result : -$result;
		});

		$this->items = $this->data;
	}

	public function get_bulk_actions() {
		return [
			'delete' => 'Delete'
		];
	}

	public function process_bulk_action() {
		if ( 'delete' === $this->current_action() && !empty($_POST['lead']) ) {
			GBS_DB::delete_leads( $_POST['lead'] );
			echo '<div class="updated"><p>Deleted selected leads.</p></div>';
		}
	}
}