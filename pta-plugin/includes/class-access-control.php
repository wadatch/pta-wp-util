<?php
/**
 * Access Control Class
 *
 * @package PTA_Plugin
 */

namespace PTA_Plugin;

// Direct access protection
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Access_Control
 * Handles content access restrictions based on user blocks
 */
class Access_Control {
	/**
	 * Roles instance
	 */
	private $roles;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->roles = new Roles();
		
		// Hook into pre_get_posts for filtering content
		add_action( 'pre_get_posts', array( $this, 'filter_posts_by_block' ), 10 );
		
		// Hook into capability checks
		add_filter( 'user_has_cap', array( $this, 'filter_capabilities' ), 10, 4 );
		
		// Filter media library
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_by_block' ) );
		
		// Add block info to permalinks structure consideration
		add_filter( 'post_link', array( $this, 'check_post_access' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'check_post_access' ), 10, 2 );
		
		// Prevent direct access to restricted content
		add_action( 'template_redirect', array( $this, 'restrict_direct_access' ) );
	}

	/**
	 * Filter posts in admin and frontend based on user's block
	 */
	public function filter_posts_by_block( $query ) {
		// Skip if not main query or if user is PTA admin
		if ( ! $query->is_main_query() || $this->roles->is_pta_admin() ) {
			return;
		}

		// Only filter in admin and for logged-in users
		if ( ! is_admin() || ! is_user_logged_in() ) {
			return;
		}

		// Get current user's block
		$user_block = $this->roles->get_user_block();
		
		// If user is block officer, filter content
		if ( $this->roles->is_block_officer() && $user_block ) {
			// Only show posts/pages from user's block
			$meta_query = $query->get( 'meta_query' ) ?: array();
			
			// Add custom filtering based on post path
			add_filter( 'posts_where', array( $this, 'filter_posts_where' ), 10, 2 );
			
			// Store user block for use in filter
			$this->current_filter_block = $user_block;
		}
	}

	/**
	 * Filter posts WHERE clause to check for block in path
	 */
	public function filter_posts_where( $where, $query ) {
		global $wpdb;
		
		if ( isset( $this->current_filter_block ) ) {
			$block = $this->current_filter_block;
			
			// Add condition to check if post slug or parent path contains the block
			$where .= $wpdb->prepare(
				" AND (
					{$wpdb->posts}.post_name LIKE %s 
					OR {$wpdb->posts}.guid LIKE %s
					OR {$wpdb->posts}.ID IN (
						SELECT ID FROM {$wpdb->posts} AS p2
						WHERE p2.post_type IN ('post', 'page')
						AND p2.post_status != 'auto-draft'
						AND p2.post_name LIKE %s
					)
				)",
				'%' . $block . '%',
				'%/' . $block . '/%',
				$block . '%'
			);
			
			// Clean up
			unset( $this->current_filter_block );
			remove_filter( 'posts_where', array( $this, 'filter_posts_where' ), 10 );
		}
		
		return $where;
	}

	/**
	 * Filter media library based on user's block
	 */
	public function filter_media_by_block( $args ) {
		// Skip if user is PTA admin
		if ( $this->roles->is_pta_admin() ) {
			return $args;
		}

		// Get current user's block
		$user_block = $this->roles->get_user_block();
		
		// If user is block officer, filter media
		if ( $this->roles->is_block_officer() && $user_block ) {
			// Add meta query to filter media by block
			$args['meta_query'] = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key' => '_wp_attached_file',
					'value' => '/' . $user_block . '/',
					'compare' => 'LIKE'
				),
				array(
					'key' => 'pta_block',
					'value' => $user_block,
					'compare' => '='
				)
			);
		}
		
		return $args;
	}

	/**
	 * Filter user capabilities based on content block
	 */
	public function filter_capabilities( $allcaps, $caps, $args, $user ) {
		// Skip if no specific capability is being checked
		if ( empty( $args[0] ) ) {
			return $allcaps;
		}

		// Skip if user is PTA admin
		if ( $this->roles->is_pta_admin( $user->ID ) ) {
			return $allcaps;
		}

		// Check if this is an edit capability
		$edit_caps = array( 'edit_post', 'edit_page', 'delete_post', 'delete_page', 'publish_post', 'publish_page' );
		
		if ( in_array( $args[0], $edit_caps ) && isset( $args[2] ) ) {
			$post_id = $args[2];
			$post = get_post( $post_id );
			
			if ( $post ) {
				// Check if user can edit this specific post
				if ( ! $this->user_can_edit_post( $user->ID, $post ) ) {
					// Remove the capability
					foreach ( $caps as $cap ) {
						$allcaps[ $cap ] = false;
					}
				}
			}
		}
		
		return $allcaps;
	}

	/**
	 * Check if user can edit a specific post
	 */
	private function user_can_edit_post( $user_id, $post ) {
		// PTA admins can edit everything
		if ( $this->roles->is_pta_admin( $user_id ) ) {
			return true;
		}

		// Get user's block
		$user_block = $this->roles->get_user_block( $user_id );
		
		// If user is not a block officer or has no block, can't edit
		if ( ! $this->roles->is_block_officer( $user_id ) || ! $user_block ) {
			return false;
		}

		// Check if post belongs to user's block
		return $this->post_belongs_to_block( $post, $user_block );
	}

	/**
	 * Check if post belongs to a specific block
	 */
	private function post_belongs_to_block( $post, $block ) {
		// Check post slug
		if ( strpos( $post->post_name, $block ) !== false ) {
			return true;
		}

		// Check post path (for hierarchical post types)
		$permalink = get_permalink( $post );
		if ( strpos( $permalink, '/' . $block . '/' ) !== false ) {
			return true;
		}

		// Check parent pages
		if ( $post->post_parent ) {
			$parent = get_post( $post->post_parent );
			if ( $parent ) {
				return $this->post_belongs_to_block( $parent, $block );
			}
		}

		// Check post meta
		$post_block = get_post_meta( $post->ID, 'pta_block', true );
		if ( $post_block === $block ) {
			return true;
		}

		return false;
	}

	/**
	 * Check post access (for filtering purposes)
	 */
	public function check_post_access( $permalink, $post ) {
		// This is just for checking, not modifying
		return $permalink;
	}

	/**
	 * Restrict direct access to content
	 */
	public function restrict_direct_access() {
		// Only check for single posts/pages
		if ( ! is_singular() ) {
			return;
		}

		// Skip if user is not logged in (let WP handle it)
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Skip if user is PTA admin
		if ( $this->roles->is_pta_admin() ) {
			return;
		}

		global $post;
		
		// Get user's block
		$user_block = $this->roles->get_user_block();
		
		// For block officers, check if they can access this content
		if ( $this->roles->is_block_officer() && $user_block ) {
			if ( ! $this->post_belongs_to_block( $post, $user_block ) ) {
				// User can only read, not edit
				// Remove admin bar edit link
				add_filter( 'show_admin_bar', '__return_false' );
				
				// Remove edit post link
				add_filter( 'edit_post_link', '__return_empty_string' );
			}
		}
	}

	/**
	 * Get block from post path
	 */
	public function get_block_from_path( $path ) {
		$blocks = get_option( 'pta_blocks', array() );
		
		foreach ( $blocks as $block ) {
			if ( strpos( $path, '/' . $block . '/' ) !== false ) {
				return $block;
			}
		}
		
		return null;
	}

	/**
	 * Add block meta to post on save
	 */
	public function add_block_meta_on_save( $post_id ) {
		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Try to determine block from permalink
		$permalink = get_permalink( $post_id );
		$block = $this->get_block_from_path( $permalink );
		
		if ( $block ) {
			update_post_meta( $post_id, 'pta_block', $block );
		}
	}
}