<?php
/**
 * Helper Functions
 *
 * @package PTA_Plugin
 */

namespace PTA_Plugin;

// Direct access protection
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get PTA plugin instance
 *
 * @return PTA_Plugin
 */
function pta_plugin() {
	return PTA_Plugin::get_instance();
}

/**
 * Check if current user is PTA admin
 *
 * @param int $user_id Optional. User ID to check. Default current user.
 * @return bool
 */
function pta_is_admin( $user_id = null ) {
	$roles = pta_plugin()->get_roles();
	return $roles ? $roles->is_pta_admin( $user_id ) : false;
}

/**
 * Check if current user is block officer
 *
 * @param int $user_id Optional. User ID to check. Default current user.
 * @return bool
 */
function pta_is_block_officer( $user_id = null ) {
	$roles = pta_plugin()->get_roles();
	return $roles ? $roles->is_block_officer( $user_id ) : false;
}

/**
 * Get user's assigned block
 *
 * @param int $user_id Optional. User ID to check. Default current user.
 * @return string
 */
function pta_get_user_block( $user_id = null ) {
	$roles = pta_plugin()->get_roles();
	return $roles ? $roles->get_user_block( $user_id ) : '';
}

/**
 * Get all configured blocks
 *
 * @return array
 */
function pta_get_blocks() {
	return get_option( 'pta_blocks', array() );
}

/**
 * Get city name
 *
 * @return string
 */
function pta_get_city_name() {
	return get_option( 'pta_city_name', 'XXXX市' );
}

/**
 * Check if a post belongs to a specific block
 *
 * @param int|WP_Post $post Post ID or post object.
 * @param string      $block Block slug.
 * @return bool
 */
function pta_post_belongs_to_block( $post, $block ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}

	$access_control = pta_plugin()->get_access_control();
	if ( ! $access_control ) {
		return false;
	}

	// Use reflection to access private method
	$reflection = new \ReflectionClass( $access_control );
	$method = $reflection->getMethod( 'post_belongs_to_block' );
	$method->setAccessible( true );

	return $method->invoke( $access_control, $post, $block );
}

/**
 * Get block from URL path
 *
 * @param string $path URL path.
 * @return string|null
 */
function pta_get_block_from_path( $path ) {
	$access_control = pta_plugin()->get_access_control();
	return $access_control ? $access_control->get_block_from_path( $path ) : null;
}

/**
 * Generate slug from title
 *
 * @param string $title Post title.
 * @return string
 */
function pta_generate_slug( $title ) {
	$slug_generator = pta_plugin()->get_slug_generator();
	if ( ! $slug_generator ) {
		return sanitize_title( $title );
	}

	// Use reflection to access private method
	$reflection = new \ReflectionClass( $slug_generator );
	$method = $reflection->getMethod( 'generate_slug_from_title' );
	$method->setAccessible( true );

	return $method->invoke( $slug_generator, $title );
}

/**
 * Clear translation cache
 */
function pta_clear_translation_cache() {
	$slug_generator = pta_plugin()->get_slug_generator();
	if ( $slug_generator ) {
		$slug_generator->clear_translation_cache();
	}
}

/**
 * Check if user can access content
 *
 * @param int|WP_Post $post    Post ID or post object.
 * @param int         $user_id Optional. User ID. Default current user.
 * @return bool
 */
function pta_user_can_access( $post, $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	// Not logged in users follow WordPress defaults
	if ( ! $user_id ) {
		return false;
	}

	// PTA admins can access everything
	if ( pta_is_admin( $user_id ) ) {
		return true;
	}

	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}

	$user_block = pta_get_user_block( $user_id );

	// Block officers can access their block's content
	if ( pta_is_block_officer( $user_id ) && $user_block ) {
		return pta_post_belongs_to_block( $post, $user_block );
	}

	// Other users can read but not edit
	return $post->post_status === 'publish' || current_user_can( 'read_private_posts' );
}

/**
 * Get available translation providers
 *
 * @return array
 */
function pta_get_translation_providers() {
	return Slug_Generator::get_translation_providers();
}

/**
 * Log plugin activity (for debugging)
 *
 * @param string $message Log message.
 * @param string $level   Log level (error, warning, info, debug).
 */
function pta_log( $message, $level = 'info' ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( '[PTA Plugin] [%s] %s', strtoupper( $level ), $message ) );
	}
}

/**
 * Get plugin version
 *
 * @return string
 */
function pta_get_version() {
	return PTA_PLUGIN_VERSION;
}

/**
 * Get plugin directory path
 *
 * @param string $path Optional. Path to append.
 * @return string
 */
function pta_get_plugin_dir( $path = '' ) {
	return PTA_PLUGIN_DIR . ltrim( $path, '/' );
}

/**
 * Get plugin URL
 *
 * @param string $path Optional. Path to append.
 * @return string
 */
function pta_get_plugin_url( $path = '' ) {
	return PTA_PLUGIN_URL . ltrim( $path, '/' );
}

/**
 * Format block name for display
 *
 * @param string $block Block slug.
 * @return string
 */
function pta_format_block_name( $block ) {
	// Convert slug to readable format
	$formatted = str_replace( array( '-', '_' ), ' ', $block );
	$formatted = ucwords( $formatted );
	
	return $formatted;
}

/**
 * Get posts by block
 *
 * @param string $block   Block slug.
 * @param array  $args    Optional. Additional query arguments.
 * @return WP_Post[]
 */
function pta_get_posts_by_block( $block, $args = array() ) {
	$default_args = array(
		'post_type' => array( 'post', 'page' ),
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_query' => array(
			array(
				'key' => 'pta_block',
				'value' => $block,
				'compare' => '='
			)
		)
	);

	$args = wp_parse_args( $args, $default_args );
	
	return get_posts( $args );
}

/**
 * Check if block exists
 *
 * @param string $block Block slug to check.
 * @return bool
 */
function pta_block_exists( $block ) {
	$blocks = pta_get_blocks();
	return in_array( $block, $blocks, true );
}

/**
 * Sanitize block slug
 *
 * @param string $block Block slug.
 * @return string
 */
function pta_sanitize_block( $block ) {
	return sanitize_title( $block );
}

/**
 * Get role display name
 *
 * @param string $role_slug Role slug.
 * @return string
 */
function pta_get_role_display_name( $role_slug ) {
	$role = get_role( $role_slug );
	
	if ( ! $role ) {
		return $role_slug;
	}

	$wp_roles = wp_roles();
	$role_names = $wp_roles->role_names;
	
	return isset( $role_names[ $role_slug ] ) ? translate_user_role( $role_names[ $role_slug ] ) : $role_slug;
}

/**
 * Check if current request is for admin area
 *
 * @return bool
 */
function pta_is_admin_request() {
	return is_admin() && ! wp_doing_ajax() && ! wp_doing_cron();
}

/**
 * Get current page URL
 *
 * @return string
 */
function pta_get_current_url() {
	global $wp;
	return home_url( $wp->request );
}