<?php
/**
 * Slug Generator Class
 *
 * @package PTA_Plugin
 */

namespace PTA_Plugin;

// Direct access protection
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Slug_Generator
 * Handles automatic ASCII/translated slug generation
 */
class Slug_Generator {
	/**
	 * Translation providers
	 */
	const PROVIDER_MYMEMORY = 'mymemory';
	const PROVIDER_DEEPL = 'deepl';
	const PROVIDER_NONE = 'none';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into post data before save
		add_filter( 'wp_insert_post_data', array( $this, 'generate_slug_on_insert' ), 10, 2 );
		
		// Hook into post save for updates
		add_action( 'save_post', array( $this, 'regenerate_slug_on_update' ), 10, 2 );
	}

	/**
	 * Generate slug on new post insert
	 */
	public function generate_slug_on_insert( $data, $postarr ) {
		// Only process for posts and pages
		if ( ! in_array( $data['post_type'], array( 'post', 'page' ), true ) ) {
			return $data;
		}

		// Skip if not a new post or if slug already exists
		if ( ! empty( $data['post_name'] ) || $data['post_status'] === 'auto-draft' ) {
			return $data;
		}

		// Generate slug from title
		if ( ! empty( $data['post_title'] ) ) {
			$generated_slug = $this->generate_slug_from_title( $data['post_title'] );
			
			if ( $generated_slug ) {
				// Ensure slug is unique
				$data['post_name'] = $this->ensure_unique_slug( $generated_slug, $postarr['ID'] ?? 0 );
			}
		}

		return $data;
	}

	/**
	 * Regenerate slug on post update if emptied
	 */
	public function regenerate_slug_on_update( $post_id, $post ) {
		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions and auto-drafts
		if ( wp_is_post_revision( $post_id ) || $post->post_status === 'auto-draft' ) {
			return;
		}

		// Only process for posts and pages
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		// Check if slug was emptied (user cleared it)
		if ( empty( $post->post_name ) && ! empty( $post->post_title ) ) {
			// Generate new slug
			$generated_slug = $this->generate_slug_from_title( $post->post_title );
			
			if ( $generated_slug ) {
				// Ensure unique and update
				$unique_slug = $this->ensure_unique_slug( $generated_slug, $post_id );
				
				// Update post slug
				wp_update_post( array(
					'ID' => $post_id,
					'post_name' => $unique_slug
				) );
			}
		}
	}

	/**
	 * Generate slug from title
	 */
	private function generate_slug_from_title( $title ) {
		// Convert problematic characters for database compatibility
		$safe_title = Charset_Converter::prepare_for_database( $title );
		
		// Get translation provider
		$provider = get_option( 'pta_translation_provider', self::PROVIDER_MYMEMORY );
		
		// Translate title based on provider
		$translated = '';
		
		switch ( $provider ) {
			case self::PROVIDER_MYMEMORY:
				$translated = $this->translate_with_mymemory( $safe_title );
				break;
				
			case self::PROVIDER_DEEPL:
				$translated = $this->translate_with_deepl( $safe_title );
				break;
				
			case self::PROVIDER_NONE:
				// No translation, use original
				$translated = $safe_title;
				break;
		}

		// If translation failed and fallback is enabled
		if ( empty( $translated ) && get_option( 'pta_ascii_fallback', true ) ) {
			$translated = $this->romanize_text( $safe_title );
		}

		// Sanitize to create slug
		if ( ! empty( $translated ) ) {
			$slug = sanitize_title( $translated );
			// Convert back for final slug if needed
			return Charset_Converter::prepare_for_display( $slug );
		}

		// Final fallback to original title
		$fallback_slug = sanitize_title( $safe_title );
		return Charset_Converter::prepare_for_display( $fallback_slug );
	}

	/**
	 * Translate using MyMemory API
	 */
	private function translate_with_mymemory( $text ) {
		// Check if we have cached translation
		$cache_key = 'pta_translation_' . md5( $text );
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// Prepare API request
		$api_key = get_option( 'pta_mymemory_api_key', '' );
		$url = 'https://api.mymemory.translated.net/get';
		
		$params = array(
			'q' => $text,
			'langpair' => 'ja|en',
		);
		
		if ( ! empty( $api_key ) ) {
			$params['key'] = $api_key;
		}
		
		$response = wp_remote_get( add_query_arg( $params, $url ), array(
			'timeout' => 10,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( isset( $data['responseData']['translatedText'] ) ) {
			$translated = $data['responseData']['translatedText'];
			
			// Cache for 24 hours
			set_transient( $cache_key, $translated, DAY_IN_SECONDS );
			
			return $translated;
		}

		return '';
	}

	/**
	 * Translate using DeepL API (placeholder)
	 */
	private function translate_with_deepl( $text ) {
		// DeepL implementation would go here
		// For now, return empty to trigger fallback
		return '';
	}

	/**
	 * Romanize Japanese text
	 */
	private function romanize_text( $text ) {
		// Basic romanization using remove_accents
		// This will handle some characters but not Japanese specifically
		$romanized = remove_accents( $text );
		
		// Additional Japanese romanization could be implemented here
		// For now, we'll use WordPress's built-in function
		
		return $romanized;
	}

	/**
	 * Ensure slug is unique
	 */
	private function ensure_unique_slug( $slug, $post_id = 0 ) {
		$original_slug = $slug;
		$suffix = 2;
		
		// Check if slug exists
		while ( $this->slug_exists( $slug, $post_id ) ) {
			$slug = $original_slug . '-' . $suffix;
			$suffix++;
		}
		
		return $slug;
	}

	/**
	 * Check if slug already exists
	 */
	private function slug_exists( $slug, $exclude_id = 0 ) {
		global $wpdb;
		
		$query = $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND ID != %d AND post_status != 'trash' LIMIT 1",
			$slug,
			$exclude_id
		);
		
		return (bool) $wpdb->get_var( $query );
	}

	/**
	 * Clear translation cache
	 */
	public function clear_translation_cache() {
		global $wpdb;
		
		// Delete all transients with our prefix
		$wpdb->query(
			"DELETE FROM $wpdb->options 
			WHERE option_name LIKE '_transient_pta_translation_%' 
			OR option_name LIKE '_transient_timeout_pta_translation_%'"
		);
	}

	/**
	 * Get available translation providers
	 */
	public static function get_translation_providers() {
		return array(
			self::PROVIDER_MYMEMORY => __( 'MyMemory Translation', 'pta-plugin' ),
			self::PROVIDER_DEEPL => __( 'DeepL Translation', 'pta-plugin' ),
			self::PROVIDER_NONE => __( 'No Translation (Romanization only)', 'pta-plugin' ),
		);
	}
}