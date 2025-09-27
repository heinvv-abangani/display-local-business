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
			'logo'        => 'Logo',
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

	public function column_logo( $item ) {
		if ( ! empty( $item['logo'] ) ) {
			return sprintf( '<img src="%s" style="width: 200px; height: auto; max-height: 100px; object-fit: cover;" alt="Logo" />', esc_url( $item['logo'] ) );
		}
		return '<span style="color: #999;">No logo</span>';
	}

	public function column_name( $item ) {
		return esc_html( $item['name'] );
	}

	public function column_website( $item ) {
		if ( ! empty( $item['website'] ) ) {
			$display_url = strlen( $item['website'] ) > 30 ? substr( $item['website'], 0, 30 ) . '...' : $item['website'];
			return sprintf( '<a href="%s" target="_blank" title="%s">%s</a>', esc_url( $item['website'] ), esc_attr( $item['website'] ), esc_html( $display_url ) );
		}
		return '<span style="color: #999;">No website</span>';
	}

	public function column_phone( $item ) {
		if ( ! empty( $item['phone'] ) ) {
			// Clean phone number for tel: link (remove spaces, dashes, etc.)
			$clean_phone = preg_replace( '/[^+\d]/', '', $item['phone'] );
			return sprintf( '<a href="tel:%s" title="Call %s">%s</a>', esc_attr( $clean_phone ), esc_attr( $item['phone'] ), esc_html( $item['phone'] ) );
		}
		return '<span style="color: #999;">No phone</span>';
	}

	public function column_about( $item ) {
		return wp_trim_words( $item['about'], 15, '…' );
	}

	public function display() {
		// Add some CSS for better table display
		echo '<style>
			.wp-list-table td.column-logo { width: 220px; }
			.wp-list-table td.column-name { width: 200px; font-weight: bold; }
			.wp-list-table td.column-website { width: 150px; }
			.wp-list-table td.column-phone { width: 150px; }
			.wp-list-table td.column-rating { width: 80px; text-align: center; }
			.wp-list-table td.column-reviews { width: 80px; text-align: center; }
			.wp-list-table td.column-category { width: 120px; }
			.wp-list-table td.column-about { width: 200px; }
			.wp-list-table img { border-radius: 4px; }
		</style>';
		parent::display();
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