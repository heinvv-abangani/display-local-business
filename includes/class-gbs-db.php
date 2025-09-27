<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GBS_DB {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'gbs_leads';
	}

	public static function create_table() {
		global $wpdb;

		$table = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			place_id varchar(255) NOT NULL,
			name varchar(255) NOT NULL,
			address text,
			rating float,
			reviews int,
			website text,
			phone varchar(50),
			category varchar(255),
			logo text,
			about text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY place_id (place_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );
		
		// Debug: Log table creation
		error_log( 'GBS DB: Table creation result: ' . print_r( $result, true ) );
		
		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
		error_log( 'GBS DB: Table exists check: ' . ( $table_exists ? 'YES' : 'NO' ) );
	}

	public static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
	}

	public static function update_table_structure() {
		global $wpdb;
		$table = self::table_name();
		
		// Update logo column to TEXT
		$wpdb->query( "ALTER TABLE $table MODIFY COLUMN logo TEXT" );
		
		// Update website column to TEXT
		$wpdb->query( "ALTER TABLE $table MODIFY COLUMN website TEXT" );
		
		error_log( 'GBS DB: Updated table structure for longer URLs' );
	}

	public static function insert_lead( $data ) {
		global $wpdb;
		$table = self::table_name();

		// Debug: Log the data being inserted
		error_log( 'GBS DB: Attempting to insert lead: ' . json_encode( $data ) );

		$result = $wpdb->replace( $table, [
			'place_id' => $data['place_id'] ?? '',
			'name'     => $data['name'] ?? '',
			'address'  => $data['address'] ?? '',
			'rating'   => $data['rating'] ?? null,
			'reviews'  => $data['reviews'] ?? null,
			'website'  => $data['website'] ?? '',
			'phone'    => $data['phone'] ?? '',
			'category' => $data['category'] ?? '',
			'logo'     => $data['logo'] ?? '',
			'about'    => $data['about'] ?? '',
		] );

		// Debug: Check for database errors
		if ( $wpdb->last_error ) {
			error_log( 'GBS DB Error: ' . $wpdb->last_error );
			error_log( 'GBS DB Query: ' . $wpdb->last_query );
			return false;
		}

		error_log( 'GBS DB: Successfully inserted/updated lead. Affected rows: ' . $wpdb->rows_affected );
		return $result;
	}

	public static function get_leads( $limit = 50, $filters = [] ) {
		global $wpdb;
		$table = self::table_name();
		
		$where = [];
		if ( ! empty( $filters['category'] ) ) {
			$where[] = $wpdb->prepare( "category = %s", $filters['category'] );
		}
		if ( ! empty( $filters['rating'] ) ) {
			$where[] = $wpdb->prepare( "rating >= %f", $filters['rating'] );
		}
		if ( ! empty( $filters['reviews'] ) ) {
			$where[] = $wpdb->prepare( "reviews >= %d", $filters['reviews'] );
		}

		$sql = "SELECT * FROM $table";
		if ( $where ) {
			$sql .= " WHERE " . implode( " AND ", $where );
		}
		$sql .= " ORDER BY created_at DESC LIMIT $limit";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function get_categories() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_col( "SELECT DISTINCT category FROM $table WHERE category != '' ORDER BY category ASC" );
	}

	public static function delete_leads( $ids ) {
		global $wpdb;
		$table = self::table_name();
		
		if ( ! is_array( $ids ) ) {
			$ids = [ $ids ];
		}

		foreach ( $ids as $id ) {
			$wpdb->delete( $table, [ 'id' => intval( $id ) ] );
		}
	}
}