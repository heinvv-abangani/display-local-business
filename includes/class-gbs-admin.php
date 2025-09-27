<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GBS_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_menu() {
		add_menu_page(
			'Business Scraper',
			'Business Scraper',
			'manage_options',
			'gbs-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-store',
			30
		);

		add_submenu_page(
			'gbs-dashboard',
			'Saved Leads',
			'Saved Leads',
			'manage_options',
			'gbs-leads',
			[ $this, 'render_leads' ]
		);

		add_submenu_page(
			'gbs-dashboard',
			'Settings',
			'Settings',
			'manage_options',
			'gbs-settings',
			[ $this, 'render_settings' ]
		);
	}

	public function register_settings() {
		register_setting( 'gbs_settings_group', 'gbs_google_api_key' );
	}

	public function render_dashboard() {
		echo '<div class="wrap">';
		echo '<h1>Google Business Scraper</h1>';

		$api_key = get_option( 'gbs_google_api_key' );

		if ( empty( $api_key ) ) {
			echo '<p>Please set your API key in <a href="' . admin_url( 'admin.php?page=gbs-settings' ) . '">Settings</a>.</p>';
			echo '</div>';
			return;
		}

		// Test API Key button
		echo '<form method="post" style="margin-bottom: 20px;">';
		submit_button( 'Test API Key', 'secondary', 'gbs_test_api', false );
		echo '</form>';

		if ( isset( $_POST['gbs_test_api'] ) ) {
			$api = new GBS_API( $api_key );
			$test_result = $api->test_api_key();
			
			if ( isset( $test_result['success'] ) ) {
				echo '<div class="notice notice-success"><p><strong>✓ ' . esc_html( $test_result['success'] ) . '</strong></p></div>';
			} else {
				echo '<div class="notice notice-error"><p><strong>✗ API Test Failed:</strong> ' . esc_html( $test_result['error'] ) . '</p></div>';
				
				if ( strpos( $test_result['error'], 'referer restrictions' ) !== false ) {
					echo '<div class="notice notice-warning"><p><strong>🔧 SOLUTION NEEDED:</strong><br>';
					echo '• Google Places API does NOT work with HTTP referer restrictions<br>';
					echo '• Go to Google Cloud Console > APIs & Services > Credentials<br>';
					echo '• Edit your API key and change "Application restrictions" to:<br>';
					echo '&nbsp;&nbsp;→ <strong>"IP addresses"</strong> (add your server IP)<br>';
					echo '&nbsp;&nbsp;→ OR <strong>"None"</strong> (less secure but works for testing)<br>';
					echo '• Keep "API restrictions" to limit to Places API only</p></div>';
				} else {
					echo '<div class="notice notice-info"><p><strong>Common API Key Issues:</strong><br>';
					echo '• API key is invalid or expired<br>';
					echo '• Places API is not enabled in Google Cloud Console<br>';
					echo '• API key restrictions are too strict<br>';
					echo '• Billing is not set up in Google Cloud Console</p></div>';
				}
			}
		}

		echo '<div class="notice notice-info"><p><strong>Instructions:</strong><br>';
		echo '• Location: Enter coordinates like "52.3676,4.9041" (Amsterdam) or "40.7128,-74.0060" (NYC)<br>';
		echo '• Radius: Distance in meters (e.g., 1000 for 1km, 5000 for 5km)<br>';
		echo '• Type: Business category like "restaurant", "dentist", "store", etc.<br>';
		echo '• Debug logs are written to your WordPress debug.log file</p></div>';

		echo '<form method="post">';
		echo '<input type="text" name="gbs_location" placeholder="Location (lat,lng) - e.g. 52.3676,4.9041" style="width:300px" value="' . esc_attr( $_POST['gbs_location'] ?? '' ) . '" />';
		echo '<input type="text" name="gbs_radius" placeholder="Radius (meters) - e.g. 5000" style="width:150px" value="' . esc_attr( $_POST['gbs_radius'] ?? '' ) . '" />';
		echo '<input type="text" name="gbs_type" placeholder="Business type - e.g. restaurant" style="width:200px" value="' . esc_attr( $_POST['gbs_type'] ?? '' ) . '" />';
		submit_button( 'Search Businesses', 'primary', 'gbs_search' );
		echo '</form>';

		if ( isset( $_POST['gbs_search'] ) ) {
			$location = sanitize_text_field( $_POST['gbs_location'] );
			$radius   = absint( $_POST['gbs_radius'] );
			$type     = sanitize_text_field( $_POST['gbs_type'] );

			// Input validation
			$errors = [];
			if ( empty( $location ) ) {
				$errors[] = 'Location is required';
			} elseif ( ! preg_match( '/^-?\d+\.?\d*,-?\d+\.?\d*$/', $location ) ) {
				$errors[] = 'Location must be in format "lat,lng" (e.g., "52.3676,4.9041")';
			}
			if ( $radius < 1 || $radius > 50000 ) {
				$errors[] = 'Radius must be between 1 and 50000 meters';
			}
			if ( empty( $type ) ) {
				$errors[] = 'Business type is required';
			}

			if ( ! empty( $errors ) ) {
				echo '<div class="notice notice-error"><p><strong>Validation Errors:</strong><br>';
				foreach ( $errors as $error ) {
					echo '• ' . esc_html( $error ) . '<br>';
				}
				echo '</p></div>';
			} else {
				// Debug output
				echo '<div class="notice notice-info"><p><strong>Debug Info:</strong><br>';
				echo 'Location: ' . esc_html( $location ) . '<br>';
				echo 'Radius: ' . esc_html( $radius ) . '<br>';
				echo 'Type: ' . esc_html( $type ) . '<br>';
				echo 'API Key (first 10 chars): ' . esc_html( substr( $api_key, 0, 10 ) ) . '...<br>';
				echo '</p></div>';

				$api = new GBS_API( $api_key );
				$results = $api->search_places( $location, $radius, $type );

				// Check for errors in results
				if ( isset( $results['error'] ) ) {
					echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . esc_html( $results['error'] ) . '</p></div>';
					echo '<p><strong>Troubleshooting tips:</strong></p>';
					echo '<ul>';
					echo '<li>Check that your Google API key is valid</li>';
					echo '<li>Ensure Places API is enabled in Google Cloud Console</li>';
					echo '<li>Verify location format is "lat,lng" (e.g., "52.3676,4.9041")</li>';
					echo '<li>Try a larger radius (e.g., 5000 meters)</li>';
					echo '<li>Check WordPress debug.log for detailed error messages</li>';
					echo '</ul>';
				} elseif ( ! empty( $results ) ) {
					echo '<h2>Results</h2><form method="post">';
					echo '<table class="widefat"><thead><tr>';
					echo '<th>Save</th><th>Name</th><th>Address</th><th>Rating</th><th>Reviews</th>';
					echo '</tr></thead><tbody>';

					foreach ( $results as $place ) {
						echo '<tr>';
						echo '<td><input type="checkbox" name="gbs_save[]" value="' . esc_attr( $place['place_id'] ) . '"></td>';
						echo '<td>' . esc_html( $place['name'] ) . '</td>';
						echo '<td>' . esc_html( $place['address'] ) . '</td>';
						echo '<td>' . esc_html( $place['rating'] ) . '</td>';
						echo '<td>' . esc_html( $place['reviews'] ) . '</td>';
						echo '</tr>';

						foreach ( $place as $k => $v ) {
							echo '<input type="hidden" name="gbs_data[' . esc_attr( $place['place_id'] ) . '][' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '">';
						}
					}

					echo '</tbody></table>';
					submit_button( 'Save Selected to DB', 'secondary', 'gbs_save_btn' );
					echo '</form>';
				} else {
					echo '<div class="notice notice-warning"><p>No businesses found. Try adjusting your search parameters.</p></div>';
				}
			}
		}

		if ( isset( $_POST['gbs_save_btn'], $_POST['gbs_save'], $_POST['gbs_data'] ) ) {
			$save_ids = (array) $_POST['gbs_save'];
			$data = $_POST['gbs_data'];

			$api = new GBS_API( get_option( 'gbs_google_api_key' ) );

			foreach ( $save_ids as $id ) {
				$details = $api->get_place_details( $id );

				if ( empty( $details ) ) {
					$details = $data[$id];
				} else {
					$details['rating']  = $details['rating'] ?: ( $data[$id]['rating'] ?? '' );
					$details['reviews'] = $details['reviews'] ?: ( $data[$id]['reviews'] ?? '' );
				}

				GBS_DB::insert_lead( $details );
			}

			echo '<div class="updated"><p>Saved ' . count( $save_ids ) . ' leads to database with details.</p></div>';
		}

		echo '</div>';
	}

	public function render_leads() {
		$filter_category = !empty($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
		$filter_rating   = !empty($_GET['filter_rating']) ? floatval($_GET['filter_rating']) : 0;
		$filter_reviews  = !empty($_GET['filter_reviews']) ? intval($_GET['filter_reviews']) : 0;

		$filters = [];
		if ( $filter_category ) $filters['category'] = $filter_category;
		if ( $filter_rating ) $filters['rating'] = $filter_rating;
		if ( $filter_reviews ) $filters['reviews'] = $filter_reviews;

		$leads = GBS_DB::get_leads( 100, $filters );
		$categories = GBS_DB::get_categories();

		echo '<div class="wrap"><h1>Saved Leads</h1>';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="gbs-leads" />';
		
		echo '<select name="filter_category"><option value="">All Categories</option>';
		foreach ( $categories as $cat ) {
			$selected = ($filter_category === $cat) ? 'selected' : '';
			echo "<option value='" . esc_attr($cat) . "' $selected>" . esc_html($cat) . "</option>";
		}
		echo '</select> ';

		echo 'Min Rating: <input type="number" step="0.1" name="filter_rating" value="' . esc_attr($filter_rating) . '" style="width:60px;" /> ';
		echo 'Min Reviews: <input type="number" name="filter_reviews" value="' . esc_attr($filter_reviews) . '" style="width:60px;" /> ';

		echo '<input type="submit" class="button" value="Filter" />';
		echo '</form><br/>';

		if ( empty( $leads ) ) {
			echo '<p>No leads saved yet.</p></div>';
			return;
		}

		echo '<form method="post">';
		$list_table = new GBS_List_Table( $leads );
		$list_table->process_bulk_action();
		$list_table->prepare_items();
		$list_table->display();
		echo '</form></div>';
	}

	public function render_settings() {
		echo '<div class="wrap">';
		echo '<h1>Google Business Scraper Settings</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'gbs_settings_group' );
		do_settings_sections( 'gbs_settings_group' );
		echo '<table class="form-table">';
		echo '<tr valign="top">';
		echo '<th scope="row">Google API Key</th>';
		echo '<td><input type="text" name="gbs_google_api_key" value="' . esc_attr( get_option( 'gbs_google_api_key' ) ) . '" style="width:400px" /></td>';
		echo '</tr>';
		echo '</table>';
		submit_button();
		echo '</form>';
		echo '</div>';
	}
}