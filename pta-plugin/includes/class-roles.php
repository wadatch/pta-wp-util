<?php
/**
 * Roles Management Class
 *
 * @package PTA_Plugin
 */

namespace PTA_Plugin;

// Direct access protection
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Roles
 * Handles custom roles and capabilities management
 */
class Roles {
	/**
	 * Custom roles configuration
	 */
	private $custom_roles = array(
		'pta_sys_admin' => array(
			'display_name' => 'PTA システム管理者',
			'capabilities' => 'administrator'
		),
		'pta_city_officer' => array(
			'display_name' => 'PTA 市協議会役員',
			'capabilities' => 'editor'
		),
		'pta_city_executive' => array(
			'display_name' => 'PTA 市協議会常任理事',
			'capabilities' => 'author_with_private'
		),
		'pta_city_director' => array(
			'display_name' => 'PTA 市協議会理事',
			'capabilities' => 'author_with_private'
		),
		'pta_project_committee' => array(
			'display_name' => 'PTA プロジェクト委員',
			'capabilities' => 'author_with_private'
		),
		'pta_pr_committee' => array(
			'display_name' => 'PTA 広報委員',
			'capabilities' => 'author_with_private'
		),
		'pta_block_officer' => array(
			'display_name' => 'PTA 区連協議会役員',
			'capabilities' => 'editor_limited'
		),
		'pta_school_officer' => array(
			'display_name' => 'PTA 単位PTA役員',
			'capabilities' => 'subscriber_with_private'
		)
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook for role registration
		add_action( 'init', array( $this, 'register_roles' ), 11 );
		
		// Hook for user registration
		add_action( 'user_register', array( $this, 'set_default_user_block' ), 10 );
		
		// Add custom user profile fields
		add_action( 'show_user_profile', array( $this, 'add_user_block_field' ) );
		add_action( 'edit_user_profile', array( $this, 'add_user_block_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_block_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_block_field' ) );
	}

	/**
	 * Register custom roles
	 */
	public function register_roles() {
		// Only add roles if they don't exist
		foreach ( $this->custom_roles as $role_name => $role_data ) {
			if ( ! get_role( $role_name ) ) {
				$this->add_role( $role_name, $role_data );
			}
		}
	}

	/**
	 * Add a single role
	 */
	private function add_role( $role_name, $role_data ) {
		$capabilities = $this->get_capabilities_by_type( $role_data['capabilities'] );
		
		add_role(
			$role_name,
			__( $role_data['display_name'], 'pta-plugin' ),
			$capabilities
		);
	}

	/**
	 * Get capabilities based on type
	 */
	private function get_capabilities_by_type( $type ) {
		switch ( $type ) {
			case 'administrator':
				// Get all administrator capabilities
				$admin_role = get_role( 'administrator' );
				return $admin_role ? $admin_role->capabilities : array();
				
			case 'editor':
				// Get editor capabilities
				$editor_role = get_role( 'editor' );
				return $editor_role ? $editor_role->capabilities : array();
				
			case 'editor_limited':
				// Editor capabilities for block officers (will be limited by access control)
				$editor_role = get_role( 'editor' );
				return $editor_role ? $editor_role->capabilities : array();
				
			case 'author_with_private':
				// Author capabilities plus read private pages
				$author_role = get_role( 'author' );
				$capabilities = $author_role ? $author_role->capabilities : array();
				$capabilities['read_private_pages'] = true;
				$capabilities['read_private_posts'] = true;
				return $capabilities;
				
			case 'subscriber_with_private':
				// Subscriber capabilities plus read private pages
				$subscriber_role = get_role( 'subscriber' );
				$capabilities = $subscriber_role ? $subscriber_role->capabilities : array();
				$capabilities['read_private_pages'] = true;
				$capabilities['read_private_posts'] = true;
				return $capabilities;
				
			default:
				return array( 'read' => true );
		}
	}

	/**
	 * Add all custom roles
	 */
	public function add_roles() {
		foreach ( $this->custom_roles as $role_name => $role_data ) {
			$this->add_role( $role_name, $role_data );
		}
	}

	/**
	 * Add custom capabilities
	 */
	public function add_capabilities() {
		// Add custom capabilities to roles if needed
		// Currently using WordPress default capabilities
	}

	/**
	 * Remove all custom roles
	 */
	public function remove_roles() {
		foreach ( $this->custom_roles as $role_name => $role_data ) {
			remove_role( $role_name );
		}
	}

	/**
	 * Set default user block on registration
	 */
	public function set_default_user_block( $user_id ) {
		// Set empty block by default
		update_user_meta( $user_id, 'pta_block', '' );
	}

	/**
	 * Add user block field to profile
	 */
	public function add_user_block_field( $user ) {
		// Check if current user can edit users
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$blocks = get_option( 'pta_blocks', array() );
		$user_block = get_user_meta( $user->ID, 'pta_block', true );
		?>
		<h3><?php esc_html_e( 'PTA 区連設定', 'pta-plugin' ); ?></h3>
		<table class="form-table">
			<tr>
				<th>
					<label for="pta_block"><?php esc_html_e( '所属区連', 'pta-plugin' ); ?></label>
				</th>
				<td>
					<select name="pta_block" id="pta_block">
						<option value=""><?php esc_html_e( '-- 選択してください --', 'pta-plugin' ); ?></option>
						<?php foreach ( $blocks as $block ) : ?>
							<option value="<?php echo esc_attr( $block ); ?>" <?php selected( $user_block, $block ); ?>>
								<?php echo esc_html( $block ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'ユーザーが所属する区連を選択してください。', 'pta-plugin' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save user block field
	 */
	public function save_user_block_field( $user_id ) {
		// Check if current user can edit users
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
			return;
		}

		// Save block
		if ( isset( $_POST['pta_block'] ) ) {
			$block = sanitize_text_field( $_POST['pta_block'] );
			update_user_meta( $user_id, 'pta_block', $block );
		}
	}

	/**
	 * Check if user has PTA admin role
	 */
	public function is_pta_admin( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		return in_array( 'pta_sys_admin', $user->roles ) || in_array( 'pta_city_officer', $user->roles );
	}

	/**
	 * Check if user is block officer
	 */
	public function is_block_officer( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		return in_array( 'pta_block_officer', $user->roles );
	}

	/**
	 * Get user's block
	 */
	public function get_user_block( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		return get_user_meta( $user_id, 'pta_block', true );
	}
}