<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GBS_API {
	private $api_key;

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	public function search_places( $location, $radius, $type ) {
		// Create cache key based on search parameters
		$cache_key = 'gbs_search_' . md5( $location . '_' . $radius . '_' . $type );
		
		// Try to get cached results first
		$cached_results = get_transient( $cache_key );
		if ( $cached_results !== false ) {
			error_log( 'GBS: Using cached results for search' );
			return $cached_results;
		}

		$url = add_query_arg(
			[
				'location' => $location,
				'radius'   => $radius,
				'type'     => $type,
				'key'      => $this->api_key,
			],
			'https://maps.googleapis.com/maps/api/place/nearbysearch/json'
		);

		// Debug: Log the API request URL
		error_log( 'GBS API Request URL: ' . $url );

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			error_log( 'GBS API Error: ' . $response->get_error_message() );
			return [ 'error' => 'API request failed: ' . $response->get_error_message() ];
		}

		$body = wp_remote_retrieve_body( $response );
		$response_code = wp_remote_retrieve_response_code( $response );
		
		// Debug: Log response code and body
		error_log( 'GBS API Response Code: ' . $response_code );
		error_log( 'GBS API Response Body: ' . $body );

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( 'GBS JSON Error: ' . json_last_error_msg() );
			return [ 'error' => 'JSON decode error: ' . json_last_error_msg() ];
		}

		if ( isset( $data['error_message'] ) ) {
			$error_details = 'Google API Error: ' . $data['error_message'];
			if ( isset( $data['status'] ) ) {
				$error_details .= ' (Status: ' . $data['status'] . ')';
			}
			error_log( 'GBS Google API Error: ' . $error_details );
			return [ 'error' => $error_details ];
		}

		if ( empty( $data['results'] ) ) {
			error_log( 'GBS: No results found in API response' );
			return [ 'error' => 'No results found. Status: ' . ( $data['status'] ?? 'unknown' ) ];
		}

		$results = [];

		foreach ( $data['results'] as $place ) {
			$results[] = [
				'place_id' => $place['place_id'] ?? '',
				'name'     => $place['name'] ?? '',
				'address'  => $place['vicinity'] ?? '',
				'rating'   => $place['rating'] ?? '',
				'reviews'  => $place['user_ratings_total'] ?? 0,
				'website'  => '',
				'phone'    => '',
				'category' => $place['types'][0] ?? '',
				'logo'     => '',
				'about'    => '',
			];
		}

		error_log( 'GBS: Found ' . count( $results ) . ' results' );
		
		// Cache the results for 1 hour (3600 seconds)
		set_transient( $cache_key, $results, 3600 );
		error_log( 'GBS: Cached search results for 1 hour' );
		
		return $results;
	}

	public function get_place_details( $place_id ) {
		// Create cache key for place details
		$cache_key = 'gbs_place_' . $place_id;
		
		// Try to get cached details first
		$cached_details = get_transient( $cache_key );
		if ( $cached_details !== false ) {
			error_log( 'GBS: Using cached place details for ' . $place_id );
			return $cached_details;
		}

		$fields = implode( ',', [
			'name',
			'website',
			'formatted_phone_number',
			'formatted_address',
			'types',
			'photos',
			'editorial_summary',
			'rating',
			'user_ratings_total',
		] );

		$url = add_query_arg(
			[
				'place_id' => $place_id,
				'fields'   => $fields,
				'key'      => $this->api_key,
			],
			'https://maps.googleapis.com/maps/api/place/details/json'
		);

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['result'] ) ) {
			return [];
		}

		$result = $data['result'];

		// Debug: Log photos information
		error_log( 'GBS: Photos available for ' . $place_id . ': ' . ( isset( $result['photos'] ) ? count( $result['photos'] ) : '0' ) );
		if ( isset( $result['photos'][0]['photo_reference'] ) ) {
			error_log( 'GBS: Photo reference: ' . substr( $result['photos'][0]['photo_reference'], 0, 20 ) . '...' );
		}

		$place_details = [
			'place_id' => $place_id,
			'name'     => $result['name'] ?? '',
			'address'  => $result['formatted_address'] ?? '',
			'rating'   => $result['rating'] ?? '',
			'reviews'  => $result['user_ratings_total'] ?? 0,
			'website'  => $result['website'] ?? '',
			'phone'    => $result['formatted_phone_number'] ?? '',
			'category' => $result['types'][0] ?? '',
			'logo'     => ! empty( $result['photos'][0]['photo_reference'] ) 
				? $this->get_photo_url( $result['photos'][0]['photo_reference'] ) 
				: '',
			'about'    => $result['editorial_summary']['overview'] ?? '',
		];

		// Debug: Log final logo URL
		if ( ! empty( $place_details['logo'] ) ) {
			error_log( 'GBS: Generated logo URL: ' . $place_details['logo'] );
		} else {
			error_log( 'GBS: No logo URL generated for ' . $place_id );
		}

		// Cache place details for 24 hours (86400 seconds)
		set_transient( $cache_key, $place_details, 86400 );
		error_log( 'GBS: Cached place details for ' . $place_id . ' for 24 hours' );

		return $place_details;
	}

	private function get_photo_url( $photo_reference ) {
		return add_query_arg(
			[
				'maxwidth' => 400,
				'photo_reference' => $photo_reference,
				'key' => $this->api_key,
			],
			'https://maps.googleapis.com/maps/api/place/photo'
		);
	}

	public function test_api_key() {
		// Simple test using a known place ID for testing API connectivity
		$url = add_query_arg(
			[
				'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4', // Google Sydney office
				'fields' => 'name',
				'key' => $this->api_key,
			],
			'https://maps.googleapis.com/maps/api/place/details/json'
		);

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return [ 'error' => 'Connection failed: ' . $response->get_error_message() ];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error_message'] ) ) {
			return [ 'error' => $data['error_message'] . ' (Status: ' . $data['status'] . ')' ];
		}

		if ( isset( $data['result']['name'] ) ) {
			return [ 'success' => 'API key is working! Test location: ' . $data['result']['name'] ];
		}

		return [ 'error' => 'Unexpected response format' ];
	}
}