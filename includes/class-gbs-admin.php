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

		// Test API Key button and table check
		echo '<form method="post" style="margin-bottom: 20px;">';
		submit_button( 'Test API Key', 'secondary', 'gbs_test_api', false );
		echo ' ';
		submit_button( 'Check Database Table', 'secondary', 'gbs_check_table', false );
		echo ' ';
		submit_button( 'Fix Database Structure', 'secondary', 'gbs_fix_table', false );
		echo ' ';
		submit_button( 'Clear Cache', 'secondary', 'gbs_clear_cache', false );
		echo '</form>';

		// Check database table
		if ( isset( $_POST['gbs_check_table'] ) ) {
			$table_exists = GBS_DB::table_exists();
			if ( $table_exists ) {
				echo '<div class="notice notice-success"><p>✓ Database table exists: ' . GBS_DB::table_name() . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>✗ Database table missing. Attempting to create...</p></div>';
				GBS_DB::create_table();
				$table_exists_after = GBS_DB::table_exists();
				if ( $table_exists_after ) {
					echo '<div class="notice notice-success"><p>✓ Database table created successfully!</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>✗ Failed to create database table. Check WordPress debug.log.</p></div>';
				}
			}
		}

		// Fix database structure
		if ( isset( $_POST['gbs_fix_table'] ) ) {
			echo '<div class="notice notice-info"><p>Updating database structure to handle longer URLs...</p></div>';
			GBS_DB::update_table_structure();
			echo '<div class="notice notice-success"><p>✓ Database structure updated! Logo and website fields can now store longer URLs.</p></div>';
		}

		// Clear cache
		if ( isset( $_POST['gbs_clear_cache'] ) ) {
			global $wpdb;
			$deleted = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gbs_%' OR option_name LIKE '_transient_timeout_gbs_%'" );
			echo '<div class="notice notice-success"><p>✓ Cache cleared! Deleted ' . $deleted . ' cached items. Next searches will fetch fresh data from Google.</p></div>';
		}

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
		echo '• <strong>Location:</strong> Type city name for autocomplete (e.g., "Amsterdam", "New York") or enter coordinates manually<br>';
		echo '• <strong>Radius:</strong> Select from dropdown (1km-50km) or choose "Custom" for specific meters<br>';
		echo '• <strong>Type:</strong> Business category like "restaurant", "dentist", "store", etc.<br>';
		echo '• <strong>💡 Tip:</strong> Search URLs are shareable - copy the URL after searching to save/share your search<br>';
		echo '• <strong>⚡ Caching:</strong> Results are cached for 1 hour, place details for 24 hours to reduce API calls<br>';
		echo '• Debug logs are written to your WordPress debug.log file</p></div>';

		echo '<form method="get" id="gbs-search-form">';
		echo '<input type="hidden" name="page" value="gbs-dashboard" />';
		echo '<div style="margin-bottom: 10px;">';
		echo '<input type="text" id="gbs-location-autocomplete" placeholder="Type city name (e.g. Amsterdam, New York)" style="width:300px" />';
		echo '<input type="hidden" name="gbs_location" id="gbs_location" value="' . esc_attr( $_GET['gbs_location'] ?? '' ) . '" />';
		echo '<br><small style="color: #666;">Or enter coordinates manually: <input type="text" id="gbs-manual-coords" placeholder="52.3676,4.9041" style="width:150px; margin-top: 5px;" value="' . esc_attr( $_GET['gbs_location'] ?? '' ) . '" /></small>';
		echo '</div>';
		echo '<select name="gbs_radius" id="gbs-radius-select" style="width:120px">';
		$current_radius = $_GET['gbs_radius'] ?? '5000';
		$radius_options = [
			'1000'  => '1 km',
			'2000'  => '2 km', 
			'5000'  => '5 km',
			'10000' => '10 km',
			'20000' => '20 km',
			'50000' => '50 km',
			'custom' => 'Custom'
		];
		
		foreach ( $radius_options as $value => $label ) {
			$selected = ( $current_radius == $value || ( $value == 'custom' && ! array_key_exists( $current_radius, $radius_options ) ) ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		
		// Custom radius input (hidden by default)
		$is_custom = ! array_key_exists( $current_radius, $radius_options );
		echo '<input type="text" id="gbs-custom-radius" placeholder="Custom radius (meters)" style="width:150px; margin-left:5px;' . ( $is_custom ? '' : ' display:none;' ) . '" value="' . ( $is_custom ? esc_attr( $current_radius ) : '' ) . '" />';
		
		echo '<input type="text" name="gbs_type" placeholder="Business type - e.g. restaurant" style="width:200px; margin-left:5px;" value="' . esc_attr( $_GET['gbs_type'] ?? '' ) . '" />';
		submit_button( 'Search Businesses', 'primary', 'gbs_search' );
		echo '</form>';

		if ( isset( $_GET['gbs_search'] ) ) {
			$location = sanitize_text_field( $_GET['gbs_location'] );
			$radius   = absint( $_GET['gbs_radius'] );
			$type     = sanitize_text_field( $_GET['gbs_type'] );

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

				// Fetch detailed information for each place to show logos, websites, phone numbers
				if ( ! isset( $results['error'] ) && ! empty( $results ) ) {
					echo '<div class="notice notice-info"><p>⏳ Fetching detailed information (logos, websites, phone numbers) for ' . count( $results ) . ' businesses...</p></div>';
					
					$detailed_count = 0;
					foreach ( $results as $index => $place ) {
						if ( ! empty( $place['place_id'] ) ) {
							echo '<div class="notice notice-info"><p>📍 Processing: ' . esc_html( $place['name'] ) . '</p></div>';
							
							$details = $api->get_place_details( $place['place_id'] );
							
							if ( ! empty( $details ) && ! isset( $details['error'] ) ) {
								// Merge basic data with detailed data, keeping non-empty values
								$results[$index] = array_merge( $place, array_filter( $details, function($value) {
									return !empty($value);
								} ) );
								$detailed_count++;
								
								// Show what we found
								$found_items = [];
								if ( ! empty( $details['logo'] ) ) $found_items[] = 'logo';
								if ( ! empty( $details['website'] ) ) $found_items[] = 'website';
								if ( ! empty( $details['phone'] ) ) $found_items[] = 'phone';
								
								if ( ! empty( $found_items ) ) {
									echo '<div class="notice notice-success"><p>✓ Found: ' . implode( ', ', $found_items ) . '</p></div>';
								}
							} else {
								echo '<div class="notice notice-warning"><p>⚠️ Could not fetch details for ' . esc_html( $place['name'] ) . '</p></div>';
							}
						}
					}
					
					echo '<div class="notice notice-success"><p>✅ Complete! Enhanced ' . $detailed_count . ' of ' . count( $results ) . ' businesses with detailed information.</p></div>';
				}

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
					echo '<style>
						.gbs-results-table td.column-logo { width: 220px; }
						.gbs-results-table td.column-name { width: 200px; font-weight: bold; }
						.gbs-results-table td.column-website { width: 150px; }
						.gbs-results-table td.column-phone { width: 150px; }
						.gbs-results-table td.column-rating { width: 80px; text-align: center; }
						.gbs-results-table td.column-reviews { width: 80px; text-align: center; }
						.gbs-results-table td.column-category { width: 120px; }
						.gbs-results-table img { border-radius: 4px; max-height: 80px; object-fit: cover; }
					</style>';
					echo '<table class="widefat gbs-results-table"><thead><tr>';
					echo '<th style="width:50px;">Save</th><th style="width:220px;">Logo</th><th style="width:200px;">Name</th><th style="width:120px;">Category</th><th style="width:150px;">Website</th><th style="width:150px;">Phone</th><th style="width:80px;">Rating</th><th style="width:80px;">Reviews</th><th>Address</th>';
					echo '</tr></thead><tbody>';

					foreach ( $results as $place ) {
						echo '<tr>';
						echo '<td><input type="checkbox" name="gbs_save[]" value="' . esc_attr( $place['place_id'] ) . '"></td>';
						
						// Logo column
						echo '<td class="column-logo">';
						if ( ! empty( $place['logo'] ) ) {
							echo '<img src="' . esc_url( $place['logo'] ) . '" style="width: 200px; height: auto;" alt="Logo" />';
						} else {
							echo '<span style="color: #999;">No logo</span>';
						}
						echo '</td>';
						
						// Name column
						echo '<td class="column-name">' . esc_html( $place['name'] ) . '</td>';
						
						// Category column
						echo '<td class="column-category">' . esc_html( $place['category'] ) . '</td>';
						
						// Website column
						echo '<td class="column-website">';
						if ( ! empty( $place['website'] ) ) {
							$display_url = strlen( $place['website'] ) > 25 ? substr( $place['website'], 0, 25 ) . '...' : $place['website'];
							echo '<a href="' . esc_url( $place['website'] ) . '" target="_blank" title="' . esc_attr( $place['website'] ) . '">' . esc_html( $display_url ) . '</a>';
						} else {
							echo '<span style="color: #999;">No website</span>';
						}
						echo '</td>';
						
						// Phone column
						echo '<td class="column-phone">';
						if ( ! empty( $place['phone'] ) ) {
							$clean_phone = preg_replace( '/[^+\d]/', '', $place['phone'] );
							echo '<a href="tel:' . esc_attr( $clean_phone ) . '" title="Call ' . esc_attr( $place['phone'] ) . '">' . esc_html( $place['phone'] ) . '</a>';
						} else {
							echo '<span style="color: #999;">No phone</span>';
						}
						echo '</td>';
						
						// Rating column
						echo '<td class="column-rating">' . esc_html( $place['rating'] ?: 'N/A' ) . '</td>';
						
						// Reviews column  
						echo '<td class="column-reviews">' . esc_html( $place['reviews'] ?: '0' ) . '</td>';
						
						// Address column
						echo '<td>' . esc_html( $place['address'] ) . '</td>';
						
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

		if ( isset( $_POST['gbs_save_btn'] ) ) {
			echo '<div class="notice notice-info"><p><strong>Save Debug:</strong><br>';
			echo 'Save button clicked: YES<br>';
			echo 'gbs_save isset: ' . ( isset( $_POST['gbs_save'] ) ? 'YES' : 'NO' ) . '<br>';
			echo 'gbs_data isset: ' . ( isset( $_POST['gbs_data'] ) ? 'YES' : 'NO' ) . '<br>';
			
			if ( isset( $_POST['gbs_save'] ) ) {
				echo 'Selected IDs: ' . count( $_POST['gbs_save'] ) . '<br>';
			}
			echo '</p></div>';

			if ( isset( $_POST['gbs_save'], $_POST['gbs_data'] ) ) {
				$save_ids = (array) $_POST['gbs_save'];
				$data = $_POST['gbs_data'];

				echo '<div class="notice notice-info"><p><strong>Processing Save:</strong><br>';
				echo 'Number of items to save: ' . count( $save_ids ) . '<br>';
				echo '</p></div>';

				$api = new GBS_API( get_option( 'gbs_google_api_key' ) );
				$saved_count = 0;

				foreach ( $save_ids as $id ) {
					echo '<div class="notice notice-info"><p>Processing place ID: ' . esc_html( $id ) . '</p></div>';
					
					$details = $api->get_place_details( $id );

					if ( empty( $details ) || isset( $details['error'] ) ) {
						echo '<div class="notice notice-warning"><p>Using basic data for place ID: ' . esc_html( $id ) . '</p></div>';
						$details = $data[$id] ?? [];
					} else {
						echo '<div class="notice notice-success"><p>Got detailed data for: ' . esc_html( $details['name'] ?? $id ) . '</p></div>';
						$details['rating']  = $details['rating'] ?: ( $data[$id]['rating'] ?? '' );
						$details['reviews'] = $details['reviews'] ?: ( $data[$id]['reviews'] ?? '' );
					}

					if ( ! empty( $details ) ) {
						echo '<div class="notice notice-info"><p><strong>Data to save:</strong><br>';
						echo 'Name: ' . esc_html( $details['name'] ?? 'N/A' ) . '<br>';
						echo 'Place ID: ' . esc_html( $details['place_id'] ?? 'N/A' ) . '<br>';
						echo 'Address: ' . esc_html( $details['address'] ?? 'N/A' ) . '<br>';
						echo '</p></div>';

						global $wpdb;
						$table_exists_before = GBS_DB::table_exists();
						echo '<div class="notice notice-info"><p>Table exists before save: ' . ( $table_exists_before ? 'YES' : 'NO' ) . '</p></div>';

						$result = GBS_DB::insert_lead( $details );
						
						// Show database error if any
						if ( $wpdb->last_error ) {
							echo '<div class="notice notice-error"><p><strong>Database Error:</strong> ' . esc_html( $wpdb->last_error ) . '</p></div>';
							echo '<div class="notice notice-info"><p><strong>Last Query:</strong> ' . esc_html( $wpdb->last_query ) . '</p></div>';
						}

						if ( $result !== false ) {
							$saved_count++;
							echo '<div class="notice notice-success"><p>✓ Saved: ' . esc_html( $details['name'] ?? $id ) . ' (Affected rows: ' . $wpdb->rows_affected . ')</p></div>';
						} else {
							echo '<div class="notice notice-error"><p>✗ Failed to save: ' . esc_html( $details['name'] ?? $id ) . '</p></div>';
						}
					} else {
						echo '<div class="notice notice-error"><p>✗ No data to save for place ID: ' . esc_html( $id ) . '</p></div>';
					}
				}

				echo '<div class="updated"><p><strong>Save Complete:</strong> Saved ' . $saved_count . ' of ' . count( $save_ids ) . ' leads to database.</p></div>';
			} else {
				echo '<div class="notice notice-error"><p><strong>Save Error:</strong> No items selected or missing form data.</p></div>';
			}
		}

		// Add Google Maps JavaScript API and autocomplete
		$api_key = get_option( 'gbs_google_api_key' );
		if ( ! empty( $api_key ) ) {
			echo '<script>
			function initAutocomplete() {
				const autocomplete = new google.maps.places.Autocomplete(
					document.getElementById("gbs-location-autocomplete"),
					{
						types: ["(cities)"],
						fields: ["place_id", "geometry", "name", "formatted_address"]
					}
				);

				autocomplete.addListener("place_changed", function() {
					const place = autocomplete.getPlace();
					
					if (!place.geometry) {
						console.log("No details available for: " + place.name);
						return;
					}

					// Get the coordinates
					const lat = place.geometry.location.lat();
					const lng = place.geometry.location.lng();
					const coordinates = lat + "," + lng;
					
					// Update the hidden field and manual input
					document.getElementById("gbs_location").value = coordinates;
					document.getElementById("gbs-manual-coords").value = coordinates;
					
					console.log("Selected location:", place.formatted_address, coordinates);
				});

				// Handle manual coordinate input
				document.getElementById("gbs-manual-coords").addEventListener("input", function() {
					document.getElementById("gbs_location").value = this.value;
					document.getElementById("gbs-location-autocomplete").value = "";
				});

				// Set initial value if coordinates are present
				const currentCoords = document.getElementById("gbs_location").value;
				if (currentCoords && currentCoords.includes(",")) {
					document.getElementById("gbs-manual-coords").value = currentCoords;
				}

				// Handle radius dropdown
				const radiusSelect = document.getElementById("gbs-radius-select");
				const customRadiusInput = document.getElementById("gbs-custom-radius");

				radiusSelect.addEventListener("change", function() {
					if (this.value === "custom") {
						customRadiusInput.style.display = "inline-block";
						customRadiusInput.focus();
					} else {
						customRadiusInput.style.display = "none";
					}
				});

				// Handle form submission to use correct radius value
				document.getElementById("gbs-search-form").addEventListener("submit", function(e) {
					const radiusSelect = document.getElementById("gbs-radius-select");
					const customRadiusInput = document.getElementById("gbs-custom-radius");
					
					if (radiusSelect.value === "custom" && customRadiusInput.value) {
						// Create a hidden input with the custom radius value
						const hiddenInput = document.createElement("input");
						hiddenInput.type = "hidden";
						hiddenInput.name = "gbs_radius";
						hiddenInput.value = customRadiusInput.value;
						this.appendChild(hiddenInput);
						
						// Disable the select to prevent it from being submitted
						radiusSelect.disabled = true;
					}
				});
			}

			function loadGoogleMapsScript() {
				if (typeof google !== "undefined" && google.maps && google.maps.places) {
					initAutocomplete();
					return;
				}
				
				const script = document.createElement("script");
				script.src = "https://maps.googleapis.com/maps/api/js?key=' . esc_js( $api_key ) . '&libraries=places&callback=initAutocomplete";
				script.async = true;
				script.defer = true;
				document.head.appendChild(script);
			}

			// Load when DOM is ready
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", loadGoogleMapsScript);
			} else {
				loadGoogleMapsScript();
			}
			</script>';
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