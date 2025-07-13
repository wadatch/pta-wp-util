<?php
/**
 * Plugin Name: PTA Group Manager & Auto-Slug
 * Plugin URI: https://example.com/pta-plugin
 * Description: 区連（ブロック）単位のコンテンツ編集権限管理と投稿／固定ページ保存時のASCII・英訳スラッグ自動生成
 * Version: 1.0.0
 * Author: PTA Development Team
 * Author URI: https://example.com
 * Text Domain: pta-plugin
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace PTA_Plugin;

// Direct access protection
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'PTA_PLUGIN_VERSION', '1.0.0' );
define( 'PTA_PLUGIN_FILE', __FILE__ );
define( 'PTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PTA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PTA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class PTA_Plugin {
	/**
	 * Instance
	 *
	 * @var PTA_Plugin
	 */
	private static $instance = null;

	/**
	 * Plugin components
	 */
	private $roles = null;
	private $access_control = null;
	private $slug_generator = null;
	private $settings = null;

	/**
	 * Get plugin instance
	 *
	 * @return PTA_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		// Load helper functions
		require_once PTA_PLUGIN_DIR . 'includes/helpers.php';

		// Load classes
		require_once PTA_PLUGIN_DIR . 'includes/class-roles.php';
		require_once PTA_PLUGIN_DIR . 'includes/class-access-control.php';
		require_once PTA_PLUGIN_DIR . 'includes/class-slug-generator.php';
		require_once PTA_PLUGIN_DIR . 'includes/class-settings.php';
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Load text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Initialize components
		add_action( 'init', array( $this, 'init_components' ), 5 );

		// Activation/Deactivation hooks
		register_activation_hook( PTA_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( PTA_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'pta-plugin',
			false,
			dirname( PTA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		// Initialize roles
		$this->roles = new Roles();

		// Initialize access control
		$this->access_control = new Access_Control();

		// Initialize slug generator
		$this->slug_generator = new Slug_Generator();

		// Initialize settings (admin only)
		if ( is_admin() ) {
			$this->settings = new Settings();
		}
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Initialize roles on activation
		require_once PTA_PLUGIN_DIR . 'includes/class-roles.php';
		$roles = new Roles();
		$roles->add_roles();
		$roles->add_capabilities();

		// Set default options
		$this->set_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Remove roles and capabilities
		require_once PTA_PLUGIN_DIR . 'includes/class-roles.php';
		$roles = new Roles();
		$roles->remove_roles();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options
	 */
	private function set_default_options() {
		// Default city name
		if ( ! get_option( 'pta_city_name' ) ) {
			update_option( 'pta_city_name', 'XXXX市' );
		}

		// Default blocks
		if ( ! get_option( 'pta_blocks' ) ) {
			$default_blocks = array(
				'ward-1',
				'ward-2',
				'ward-3',
				'ward-4',
				'ward-5',
				'ward-6',
				'ward-7',
				'ward-8',
				'ward-9',
				'ward-10'
			);
			update_option( 'pta_blocks', $default_blocks );
		}

		// Translation settings
		if ( ! get_option( 'pta_translation_provider' ) ) {
			update_option( 'pta_translation_provider', 'mymemory' );
		}

		if ( ! get_option( 'pta_ascii_fallback' ) ) {
			update_option( 'pta_ascii_fallback', true );
		}
	}

	/**
	 * Get plugin components
	 */
	public function get_roles() {
		return $this->roles;
	}

	public function get_access_control() {
		return $this->access_control;
	}

	public function get_slug_generator() {
		return $this->slug_generator;
	}

	public function get_settings() {
		return $this->settings;
	}
}

// Initialize plugin
function pta_plugin() {
	return PTA_Plugin::get_instance();
}

// Start the plugin
pta_plugin();