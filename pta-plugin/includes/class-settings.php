<?php
/**
 * Settings Page Class
 *
 * @package PTA_Plugin
 */

namespace PTA_Plugin;

// Direct access protection
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 * Handles plugin settings page
 */
class Settings {
	/**
	 * Settings page slug
	 */
	const MENU_SLUG = 'pta-settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
		
		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . PTA_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
		
		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'PTA 設定', 'pta-plugin' ),
			__( 'PTA', 'pta-plugin' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// Basic settings
		register_setting( 'pta_basic_settings', 'pta_city_name', array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'XXXX市'
		) );
		
		register_setting( 'pta_basic_settings', 'pta_blocks', array(
			'type' => 'array',
			'sanitize_callback' => array( $this, 'sanitize_blocks' ),
			'default' => array()
		) );
		
		// Translation settings
		register_setting( 'pta_translation_settings', 'pta_translation_provider', array(
			'type' => 'string',
			'sanitize_callback' => array( $this, 'sanitize_translation_provider' ),
			'default' => 'mymemory'
		) );
		
		register_setting( 'pta_translation_settings', 'pta_mymemory_api_key', array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => ''
		) );
		
		register_setting( 'pta_translation_settings', 'pta_ascii_fallback', array(
			'type' => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default' => true
		) );
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get active tab
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'basic';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?page=<?php echo esc_attr( self::MENU_SLUG ); ?>&tab=basic" 
				   class="nav-tab <?php echo $active_tab === 'basic' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( '基本設定', 'pta-plugin' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( self::MENU_SLUG ); ?>&tab=translation" 
				   class="nav-tab <?php echo $active_tab === 'translation' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( '翻訳 API', 'pta-plugin' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( self::MENU_SLUG ); ?>&tab=roles" 
				   class="nav-tab <?php echo $active_tab === 'roles' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'ロール/権限', 'pta-plugin' ); ?>
				</a>
			</h2>
			
			<?php
			switch ( $active_tab ) {
				case 'translation':
					$this->render_translation_tab();
					break;
				case 'roles':
					$this->render_roles_tab();
					break;
				default:
					$this->render_basic_tab();
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render basic settings tab
	 */
	private function render_basic_tab() {
		$city_name = get_option( 'pta_city_name', 'XXXX市' );
		$blocks = get_option( 'pta_blocks', array() );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'pta_basic_settings' ); ?>
			
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="pta_city_name"><?php esc_html_e( '市名', 'pta-plugin' ); ?></label>
					</th>
					<td>
						<input type="text" id="pta_city_name" name="pta_city_name" 
						       value="<?php echo esc_attr( $city_name ); ?>" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'PTA が活動する市の名前を入力してください。', 'pta-plugin' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'ブロック一覧', 'pta-plugin' ); ?>
					</th>
					<td>
						<div id="pta-blocks-container">
							<?php
							if ( empty( $blocks ) ) {
								$blocks = array( '' ); // Show at least one empty field
							}
							foreach ( $blocks as $index => $block ) :
							?>
								<div class="pta-block-field">
									<input type="text" name="pta_blocks[]" 
									       value="<?php echo esc_attr( $block ); ?>" 
									       class="regular-text" 
									       placeholder="<?php esc_attr_e( '例: ward-1', 'pta-plugin' ); ?>" />
									<button type="button" class="button pta-remove-block">
										<?php esc_html_e( '削除', 'pta-plugin' ); ?>
									</button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button pta-add-block">
							<?php esc_html_e( 'ブロックを追加', 'pta-plugin' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( '区連（ブロック）のスラッグを入力してください。URLやファイルパスで使用されます。', 'pta-plugin' ); ?>
						</p>
					</td>
				</tr>
			</table>
			
			<?php submit_button(); ?>
		</form>
		
		<script>
		jQuery(document).ready(function($) {
			// Add block field
			$('.pta-add-block').on('click', function() {
				var template = '<div class="pta-block-field">' +
					'<input type="text" name="pta_blocks[]" value="" class="regular-text" placeholder="<?php echo esc_js( __( '例: ward-1', 'pta-plugin' ) ); ?>" />' +
					' <button type="button" class="button pta-remove-block"><?php echo esc_js( __( '削除', 'pta-plugin' ) ); ?></button>' +
					'</div>';
				$('#pta-blocks-container').append(template);
			});
			
			// Remove block field
			$(document).on('click', '.pta-remove-block', function() {
				$(this).closest('.pta-block-field').remove();
			});
		});
		</script>
		<style>
		.pta-block-field {
			margin-bottom: 10px;
		}
		.pta-block-field input {
			margin-right: 10px;
		}
		</style>
		<?php
	}

	/**
	 * Render translation settings tab
	 */
	private function render_translation_tab() {
		$provider = get_option( 'pta_translation_provider', 'mymemory' );
		$api_key = get_option( 'pta_mymemory_api_key', '' );
		$ascii_fallback = get_option( 'pta_ascii_fallback', true );
		$providers = Slug_Generator::get_translation_providers();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'pta_translation_settings' ); ?>
			
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="pta_translation_provider"><?php esc_html_e( '翻訳プロバイダー', 'pta-plugin' ); ?></label>
					</th>
					<td>
						<select id="pta_translation_provider" name="pta_translation_provider">
							<?php foreach ( $providers as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $provider, $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'スラッグ生成に使用する翻訳サービスを選択してください。', 'pta-plugin' ); ?>
						</p>
					</td>
				</tr>
				<tr class="pta-mymemory-settings" <?php echo $provider !== 'mymemory' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">
						<label for="pta_mymemory_api_key"><?php esc_html_e( 'MyMemory API キー', 'pta-plugin' ); ?></label>
					</th>
					<td>
						<input type="text" id="pta_mymemory_api_key" name="pta_mymemory_api_key" 
						       value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
						<p class="description">
							<?php 
							printf(
								/* translators: %s: MyMemory API URL */
								esc_html__( 'MyMemory API キーを入力してください。%s から取得できます。', 'pta-plugin' ),
								'<a href="https://mymemory.translated.net/doc/keygen.php" target="_blank">https://mymemory.translated.net/doc/keygen.php</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'ASCII フォールバック', 'pta-plugin' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="pta_ascii_fallback" value="1" <?php checked( $ascii_fallback ); ?> />
							<?php esc_html_e( '翻訳に失敗した場合、ローマ字変換を使用する', 'pta-plugin' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'API エラーや制限に達した場合の代替処理を有効にします。', 'pta-plugin' ); ?>
						</p>
					</td>
				</tr>
			</table>
			
			<?php submit_button(); ?>
		</form>
		
		<script>
		jQuery(document).ready(function($) {
			$('#pta_translation_provider').on('change', function() {
				if ($(this).val() === 'mymemory') {
					$('.pta-mymemory-settings').show();
				} else {
					$('.pta-mymemory-settings').hide();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render roles tab
	 */
	private function render_roles_tab() {
		$roles = wp_roles();
		$pta_roles = array();
		
		// Get PTA roles
		foreach ( $roles->roles as $role_key => $role_data ) {
			if ( strpos( $role_key, 'pta_' ) === 0 ) {
				$pta_roles[ $role_key ] = $role_data;
			}
		}
		?>
		<div class="pta-roles-info">
			<h2><?php esc_html_e( 'PTA カスタムロール一覧', 'pta-plugin' ); ?></h2>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ロール名', 'pta-plugin' ); ?></th>
						<th><?php esc_html_e( '表示名', 'pta-plugin' ); ?></th>
						<th><?php esc_html_e( '権限レベル', 'pta-plugin' ); ?></th>
						<th><?php esc_html_e( '説明', 'pta-plugin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pta_roles as $role_key => $role_data ) : ?>
						<tr>
							<td><code><?php echo esc_html( $role_key ); ?></code></td>
							<td><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></td>
							<td><?php echo esc_html( $this->get_role_level_description( $role_key ) ); ?></td>
							<td><?php echo esc_html( $this->get_role_description( $role_key ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<h3><?php esc_html_e( '権限の説明', 'pta-plugin' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'システム管理者・市協議会役員', 'pta-plugin' ); ?></strong>: <?php esc_html_e( '全てのコンテンツに対する完全なアクセス権限', 'pta-plugin' ); ?></li>
				<li><strong><?php esc_html_e( '区連協議会役員', 'pta-plugin' ); ?></strong>: <?php esc_html_e( '所属する区連のコンテンツのみ編集可能', 'pta-plugin' ); ?></li>
				<li><strong><?php esc_html_e( 'その他の役職', 'pta-plugin' ); ?></strong>: <?php esc_html_e( '閲覧権限のみ（非公開ページも閲覧可能）', 'pta-plugin' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Get role level description
	 */
	private function get_role_level_description( $role_key ) {
		$levels = array(
			'pta_sys_admin' => __( '管理者', 'pta-plugin' ),
			'pta_city_officer' => __( '編集者', 'pta-plugin' ),
			'pta_block_officer' => __( '編集者（制限付き）', 'pta-plugin' ),
			'pta_city_executive' => __( '閲覧者', 'pta-plugin' ),
			'pta_city_director' => __( '閲覧者', 'pta-plugin' ),
			'pta_project_committee' => __( '閲覧者', 'pta-plugin' ),
			'pta_pr_committee' => __( '閲覧者', 'pta-plugin' ),
			'pta_school_officer' => __( '閲覧者', 'pta-plugin' ),
		);
		
		return isset( $levels[ $role_key ] ) ? $levels[ $role_key ] : __( '不明', 'pta-plugin' );
	}

	/**
	 * Get role description
	 */
	private function get_role_description( $role_key ) {
		$descriptions = array(
			'pta_sys_admin' => __( 'システム全体の管理権限', 'pta-plugin' ),
			'pta_city_officer' => __( '市協議会本部の役員', 'pta-plugin' ),
			'pta_block_officer' => __( '区連単位でのコンテンツ管理', 'pta-plugin' ),
			'pta_city_executive' => __( '市協議会常任理事', 'pta-plugin' ),
			'pta_city_director' => __( '市協議会理事', 'pta-plugin' ),
			'pta_project_committee' => __( 'プロジェクト委員会メンバー', 'pta-plugin' ),
			'pta_pr_committee' => __( '広報委員会メンバー', 'pta-plugin' ),
			'pta_school_officer' => __( '各学校のPTA役員', 'pta-plugin' ),
		);
		
		return isset( $descriptions[ $role_key ] ) ? $descriptions[ $role_key ] : '';
	}

	/**
	 * Sanitize blocks array
	 */
	public function sanitize_blocks( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			return array();
		}
		
		// Remove empty values and sanitize
		$sanitized = array();
		foreach ( $blocks as $block ) {
			$block = sanitize_title( $block );
			if ( ! empty( $block ) ) {
				$sanitized[] = $block;
			}
		}
		
		return array_unique( $sanitized );
	}

	/**
	 * Sanitize translation provider
	 */
	public function sanitize_translation_provider( $provider ) {
		$valid_providers = array_keys( Slug_Generator::get_translation_providers() );
		
		if ( in_array( $provider, $valid_providers, true ) ) {
			return $provider;
		}
		
		return 'mymemory';
	}

	/**
	 * Add settings link to plugins page
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=' . self::MENU_SLUG ),
			__( '設定', 'pta-plugin' )
		);
		
		array_unshift( $links, $settings_link );
		
		return $links;
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on our settings page
		if ( 'settings_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}
		
		// jQuery is already loaded in admin
	}
}