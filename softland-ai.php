<?php
/**
 * Plugin Name: Softland AI
 * Plugin URI: https://softland.app/
 * Description: Floating AI assistant widget for WordPress with DeepSeek integration, multilingual UI support, contextual answers, and admin API settings.
 * Version: 1.1.3
 * Author: Softland
 * Author URI: https://softland.app/
 * Text Domain: softland-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Softland_AI_Plugin {
	const OPTION_KEY = 'softland_ai_settings';
	const NONCE_KEY  = 'softland_ai_nonce';
	const CACHE_KEY  = 'softland_ai_store_context_v1';

	/**
	 * Boot plugin.
	 */
	public static function init() {
		$instance = new self();
		$instance->hooks();
	}

	/**
	 * Register hooks.
	 */
	private function hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
		add_shortcode( 'softland_ai_widget', array( $this, 'shortcode_widget' ) );
		add_action( 'wp_ajax_softland_ai_chat', array( $this, 'ajax_chat' ) );
		add_action( 'wp_ajax_nopriv_softland_ai_chat', array( $this, 'ajax_chat' ) );
		add_action( 'save_post', array( $this, 'flush_store_context_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_store_context_cache' ) );
		add_action( 'created_term', array( $this, 'flush_store_context_cache' ) );
		add_action( 'edited_term', array( $this, 'flush_store_context_cache' ) );
		add_action( 'delete_term', array( $this, 'flush_store_context_cache' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'flush_store_context_cache' ), 10, 0 );
	}

	/**
	 * Settings defaults.
	 *
	 * @return array<string,mixed>
	 */
	private function defaults() {
		return array(
			'enabled'             => '1',
			'api_key'             => '',
			'base_url'            => 'https://api.deepseek.com',
			'model'               => 'deepseek-chat',
			'widget_label'        => 'سوفت لاند AI',
			'launcher_text'       => '',
			'store_profile'       => 'سوفت لاند متجر ووردبريس/ووكومرس متخصص بقطع الكمبيوتر، التجميعات الجاهزة، صفحة جمع جهازك، الشاشات، الملحقات، الإكسسوارات، وورك ستيشن، وعروض التقسيط داخل السعودية. خلّ الرد يوجّه العميل بسرعة لأقرب منتج أو تصنيف أو صفحة تفيده داخل المتجر.',
			'answer_style'        => 'خل الرد باللهجة السعودية ويكون قصير وواضح وعملي. اذكر المنتج أو التصنيف أو الصفحة المناسبة أول، وبعدها اقترح خطوة بسيطة مثل: افتح الرابط، شف هالفئة، أو استخدم البحث.',
			'featured_categories' => "التجميعات\nمكونات الـPC\nالشاشات\nالملحقات\nالاكسسوارات\nورك ستيشن",
			'important_pages'     => "shop\nالمتجر\nجمع جهازك\nعناوين الفروع\nabout-softland\nالشروط الأحكام",
			'quick_prompts'       => "أبغى تجميعة ألعاب قوية\nأبحث عن كرت شاشة مناسب\nوين صفحة جمع جهازك؟\nكيف أعرف الشحن والضمان؟\nهل عندكم تقسيط؟",
			'max_products'        => 6,
			'max_categories'      => 6,
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array<string,mixed>
	 */
	private function settings() {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $this->defaults() );
	}

	/**
	 * Check RTL language.
	 */
	private function is_rtl_lang() {
		return is_rtl() || in_array( determine_locale(), array( 'ar', 'ar_AR' ), true );
	}

	/**
	 * Plugin URL helper.
	 */
	private function plugin_url( $path = '' ) {
		return plugin_dir_url( __FILE__ ) . ltrim( $path, '/' );
	}

	/**
	 * Plugin path helper.
	 */
	private function plugin_path( $path = '' ) {
		return plugin_dir_path( __FILE__ ) . ltrim( $path, '/\\' );
	}

	/**
	 * Register settings page.
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Softland AI', 'softland-ai' ),
			__( 'Softland AI', 'softland-ai' ),
			'manage_options',
			'softland-ai',
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			58
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'softland_ai_group',
			self::OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'softland_ai_api',
			__( 'ربط الذكاء', 'softland-ai' ),
			function () {
				echo '<p>' . esc_html__( 'من هنا تضبط بيانات الـ API والموديل اللي يستخدمه مساعد سوفت لاند.', 'softland-ai' ) . '</p>';
			},
			'softland-ai'
		);

		add_settings_field( 'enabled', __( 'تفعيل الودجت', 'softland-ai' ), array( $this, 'field_enabled' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'api_key', __( 'مفتاح الـ API', 'softland-ai' ), array( $this, 'field_api_key' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'base_url', __( 'رابط الـ API', 'softland-ai' ), array( $this, 'field_base_url' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'model', __( 'اسم الموديل', 'softland-ai' ), array( $this, 'field_model' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'widget_label', __( 'اسم المساعد', 'softland-ai' ), array( $this, 'field_widget_label' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'launcher_text', __( 'نص الزر', 'softland-ai' ), array( $this, 'field_launcher_text' ), 'softland-ai', 'softland_ai_api' );

		add_settings_section(
			'softland_ai_store',
			__( 'ذكاء المتجر', 'softland-ai' ),
			function () {
				echo '<p>' . esc_html__( 'هنا تعلم المساعد كيف يرد وش أهم أقسام وصفحات سوفت لاند بالنسبة لك.', 'softland-ai' ) . '</p>';
			},
			'softland-ai'
		);

		add_settings_field( 'store_profile', __( 'وصف المتجر', 'softland-ai' ), array( $this, 'field_store_profile' ), 'softland-ai', 'softland_ai_store' );
		add_settings_field( 'answer_style', __( 'أسلوب الرد', 'softland-ai' ), array( $this, 'field_answer_style' ), 'softland-ai', 'softland_ai_store' );
		add_settings_field( 'featured_categories', __( 'الأقسام المهمة', 'softland-ai' ), array( $this, 'field_featured_categories' ), 'softland-ai', 'softland_ai_store' );
		add_settings_field( 'important_pages', __( 'الصفحات المهمة', 'softland-ai' ), array( $this, 'field_important_pages' ), 'softland-ai', 'softland_ai_store' );
		add_settings_field( 'quick_prompts', __( 'الأسئلة السريعة', 'softland-ai' ), array( $this, 'field_quick_prompts' ), 'softland-ai', 'softland_ai_store' );
		add_settings_field( 'max_products', __( 'عدد المنتجات في السياق', 'softland-ai' ), array( $this, 'field_max_products' ), 'softland-ai', 'softland_ai_store' );
		add_settings_field( 'max_categories', __( 'عدد الأقسام في السياق', 'softland-ai' ), array( $this, 'field_max_categories' ), 'softland-ai', 'softland_ai_store' );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ) {
		$defaults = $this->defaults();
		$input    = is_array( $input ) ? $input : array();

		return array(
			'enabled'             => empty( $input['enabled'] ) ? '0' : '1',
			'api_key'             => sanitize_text_field( $input['api_key'] ?? $defaults['api_key'] ),
			'base_url'            => untrailingslashit( esc_url_raw( $input['base_url'] ?? $defaults['base_url'] ) ),
			'model'               => sanitize_text_field( $input['model'] ?? $defaults['model'] ),
			'widget_label'        => sanitize_text_field( $input['widget_label'] ?? $defaults['widget_label'] ),
			'launcher_text'       => sanitize_text_field( $input['launcher_text'] ?? $defaults['launcher_text'] ),
			'store_profile'       => sanitize_textarea_field( $input['store_profile'] ?? $defaults['store_profile'] ),
			'answer_style'        => sanitize_textarea_field( $input['answer_style'] ?? $defaults['answer_style'] ),
			'featured_categories' => sanitize_textarea_field( $input['featured_categories'] ?? $defaults['featured_categories'] ),
			'important_pages'     => sanitize_textarea_field( $input['important_pages'] ?? $defaults['important_pages'] ),
			'quick_prompts'       => sanitize_textarea_field( $input['quick_prompts'] ?? $defaults['quick_prompts'] ),
			'max_products'        => max( 2, min( 12, absint( $input['max_products'] ?? $defaults['max_products'] ) ) ),
			'max_categories'      => max( 2, min( 12, absint( $input['max_categories'] ?? $defaults['max_categories'] ) ) ),
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$settings = $this->settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Softland AI', 'softland-ai' ); ?></h1>
			<p><?php esc_html_e( 'مساعد ذكي لمتجر سوفت لاند يساعد العميل يوصل بسرعة للمنتجات، الأقسام، وروابط الخدمة المهمة.', 'softland-ai' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'softland_ai_group' ); ?>
				<?php do_settings_sections( 'softland-ai' ); ?>
				<?php submit_button(); ?>
			</form>
			<hr>
			<p><strong><?php esc_html_e( 'حالة مفتاح الـ API:', 'softland-ai' ); ?></strong> <?php echo ! empty( $settings['api_key'] ) ? esc_html__( 'المفتاح محفوظ', 'softland-ai' ) : esc_html__( 'إلى الآن ما انحفظ مفتاح', 'softland-ai' ); ?></p>
			<p><strong><?php esc_html_e( 'ملاحظة:', 'softland-ai' ); ?></strong> <?php esc_html_e( 'الإعدادات الافتراضية مضبوطة أصلًا على طبيعة سوفت لاند: قطع كمبيوتر، تجميعات، إكسسوارات، تقسيط، وصفحات الفروع والخدمة.', 'softland-ai' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Field renderers.
	 */
	public function field_enabled() {
		$settings = $this->settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>>
			<?php esc_html_e( 'فعّل الودجت العائم في واجهة الموقع', 'softland-ai' ); ?>
		</label>
		<?php
	}

	public function field_api_key() {
		$settings = $this->settings();
		?>
		<input class="regular-text" type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="new-password">
		<p class="description"><?php esc_html_e( 'ينحفظ داخل السيرفر فقط وما يطلع للمتصفح أبدًا.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_base_url() {
		$settings = $this->settings();
		?>
		<input class="regular-text" type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[base_url]" value="<?php echo esc_attr( $settings['base_url'] ); ?>">
		<p class="description"><?php esc_html_e( 'مثال: https://api.deepseek.com', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_model() {
		$settings = $this->settings();
		?>
		<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( $settings['model'] ); ?>">
		<p class="description"><?php esc_html_e( 'مثال: deepseek-chat', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_widget_label() {
		$settings = $this->settings();
		?>
		<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_label]" value="<?php echo esc_attr( $settings['widget_label'] ); ?>">
		<?php
	}

	public function field_launcher_text() {
		$settings = $this->settings();
		?>
		<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[launcher_text]" value="<?php echo esc_attr( $settings['launcher_text'] ); ?>">
		<p class="description"><?php esc_html_e( 'إذا خليته فاضي بيستخدم النص السعودي الافتراضي تلقائي.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_store_profile() {
		$settings = $this->settings();
		?>
		<textarea class="large-text" rows="5" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[store_profile]"><?php echo esc_textarea( $settings['store_profile'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'اكتب وصف مختصر وواضح عن سوفت لاند عشان المساعد يفهم النشاط ويعرف وش يقترح.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_answer_style() {
		$settings = $this->settings();
		?>
		<textarea class="large-text" rows="4" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[answer_style]"><?php echo esc_textarea( $settings['answer_style'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'حدد لهجة الرد وطريقته بشكل واضح.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_featured_categories() {
		$settings = $this->settings();
		?>
		<textarea class="large-text code" rows="6" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[featured_categories]"><?php echo esc_textarea( $settings['featured_categories'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'كل سطر فيه اسم قسم. هالأقسام يعطيها المساعد أولوية أعلى.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_important_pages() {
		$settings = $this->settings();
		?>
		<textarea class="large-text code" rows="6" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[important_pages]"><?php echo esc_textarea( $settings['important_pages'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'كل سطر فيه عنوان صفحة أو slug، مثل: shop أو جمع جهازك أو عناوين الفروع.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_quick_prompts() {
		$settings = $this->settings();
		?>
		<textarea class="large-text code" rows="6" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quick_prompts]"><?php echo esc_textarea( $settings['quick_prompts'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'كل سطر فيه سؤال سريع يظهر للمستخدم كبداية.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_max_products() {
		$settings = $this->settings();
		?>
		<input class="small-text" type="number" min="2" max="12" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_products]" value="<?php echo esc_attr( (string) $settings['max_products'] ); ?>">
		<p class="description"><?php esc_html_e( 'إذا زاد العدد بيصير السياق أوسع، بس ممكن يبطئ الرد شوي.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_max_categories() {
		$settings = $this->settings();
		?>
		<input class="small-text" type="number" min="2" max="12" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_categories]" value="<?php echo esc_attr( (string) $settings['max_categories'] ); ?>">
		<p class="description"><?php esc_html_e( 'يخلي سياق الأقسام مختصر ومفيد.', 'softland-ai' ); ?></p>
		<?php
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		$settings = $this->settings();
		if ( is_admin() || '1' !== $settings['enabled'] ) {
			return;
		}

		wp_enqueue_style(
			'softland-ai-frontend',
			$this->plugin_url( 'assets/css/frontend-v2.css' ),
			array(),
			file_exists( $this->plugin_path( 'assets/css/frontend-v2.css' ) ) ? filemtime( $this->plugin_path( 'assets/css/frontend-v2.css' ) ) : '1.1.3'
		);

		wp_enqueue_script(
			'softland-ai-frontend',
			$this->plugin_url( 'assets/js/frontend.js' ),
			array(),
			file_exists( $this->plugin_path( 'assets/js/frontend.js' ) ) ? filemtime( $this->plugin_path( 'assets/js/frontend.js' ) ) : '1.1.3',
			true
		);

		$rtl = $this->is_rtl_lang();

		wp_localize_script(
			'softland-ai-frontend',
			'softlandAi',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( self::NONCE_KEY ),
				'action'             => 'softland_ai_chat',
				'pageId'             => get_queried_object_id(),
				'pageTitle'          => wp_get_document_title(),
				'pageUrl'            => home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) ),
				'pageType'           => get_post_type() ?: '',
				'isRtl'              => $rtl,
				'isAdmin'            => current_user_can( 'manage_options' ),
				'widgetLabel'        => $settings['widget_label'],
				'launcherText'       => $settings['launcher_text'] ? $settings['launcher_text'] : 'اسأل سوفت لاند AI',
				'thinking'           => 'لحظة شوي، أجهز لك أفضل اقتراح...',
				'empty'              => 'اكتب سؤالك أول.',
				'error'              => 'حالياً ما قدرت أرد، جرّب بعد شوي.',
				'userLabel'          => 'أنت',
				'botLabel'           => $settings['widget_label'],
				'heading'            => 'دور على القطعة أو الصفحة المناسبة',
				'subheading'         => 'اسأل عن التجميعات، قطع الـ PC، الشاشات، التقسيط، الشحن، الضمان، أو صفحات الفروع.',
				'placeholder'        => 'مثال: أبغى تجميعة ألعاب قوية أو وين صفحة جمع جهازك؟',
				'disclaimer'         => 'الذكاء الاصطناعي ممكن يغلط أحيان، فراجع تفاصيل المنتج أو الصفحة قبل الشراء.',
				'initialSuggestions' => $this->initial_suggestions(),
			)
		);
	}

	/**
	 * Initial prompts.
	 *
	 * @return string[]
	 */
	private function initial_suggestions() {
		$settings = $this->settings();
		$custom   = $this->multiline_values( $settings['quick_prompts'] );

		if ( ! empty( $custom ) ) {
			return array_slice( $custom, 0, 5 );
		}

		return array(
			'أبغى تجميعة ألعاب قوية',
			'أبي شاشة مناسبة للقيمنق',
			'أقدر أجمع جهازي بنفسي؟',
		);
	}

	/**
	 * Render widget automatically.
	 */
	public function render_widget() {
		$settings = $this->settings();
		if ( is_admin() || '1' !== $settings['enabled'] ) {
			return;
		}

		echo $this->widget_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Shortcode output.
	 */
	public function shortcode_widget() {
		return $this->widget_markup();
	}

	/**
	 * Widget HTML.
	 */
	private function widget_markup() {
		$settings = $this->settings();
		$rtl      = $this->is_rtl_lang();

		ob_start();
		?>
		<div class="softland-ai" data-softland-ai dir="<?php echo esc_attr( $rtl ? 'rtl' : 'ltr' ); ?>">
			<button class="softland-ai__launcher" type="button" data-softland-ai-launcher aria-expanded="false" aria-controls="softland-ai-panel">
				<span class="softland-ai__spark" aria-hidden="true">✦</span>
				<span><?php echo esc_html( $settings['launcher_text'] ? $settings['launcher_text'] : 'اسأل سوفت لاند AI' ); ?></span>
			</button>

			<section class="softland-ai__panel" id="softland-ai-panel" data-softland-ai-panel hidden aria-hidden="true">
				<div class="softland-ai__card">
					<div class="softland-ai__head">
						<div>
							<strong><?php echo esc_html( $settings['widget_label'] ); ?></strong>
							<p><?php echo esc_html( 'اسأل عن المنتجات، التجميعات، التقسيط، الشحن، الضمان، أو أي صفحة مهمة داخل المتجر.' ); ?></p>
						</div>
						<button class="softland-ai__close" type="button" data-softland-ai-close aria-label="<?php echo esc_attr( 'إغلاق' ); ?>">×</button>
					</div>

					<div class="softland-ai__body">
						<div class="softland-ai__intro" data-softland-ai-intro>
							<span class="softland-ai__hero-icon" aria-hidden="true">✦</span>
							<h3><?php echo esc_html( 'ابدأ بسؤال سريع' ); ?></h3>
						</div>

						<div class="softland-ai__chips" data-softland-ai-chips></div>
						<div class="softland-ai__messages" data-softland-ai-messages></div>
						<div class="softland-ai__status" data-softland-ai-status aria-live="polite"></div>
					</div>

					<form class="softland-ai__composer" data-softland-ai-form>
						<label class="screen-reader-text" for="softland-ai-input"><?php echo esc_html( 'سؤال المساعد' ); ?></label>
						<textarea id="softland-ai-input" name="message" rows="3" data-softland-ai-input placeholder="<?php echo esc_attr( 'مثال: أبي كرت شاشة أو أبغى صفحة جمع جهازك' ); ?>"></textarea>
						<button class="softland-ai__submit" type="submit" data-softland-ai-submit aria-label="<?php echo esc_attr( 'إرسال' ); ?>">→</button>
					</form>

					<p class="softland-ai__disclaimer"><?php echo esc_html( 'الذكاء الاصطناعي ممكن يغلط أحيان، فراجع تفاصيل المنتج أو الصفحة قبل ما تعتمد قرارك.' ); ?></p>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX chat handler.
	 */
	public function ajax_chat() {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		if ( '' === $message ) {
			wp_send_json_error(
				array(
					'message' => 'اكتب سؤالك أول.',
				),
				400
			);
		}

		$history = $this->sanitize_history( $_POST['history'] ?? '[]' );
		$page_id = absint( $_POST['page_id'] ?? 0 );
		$page    = array(
			'title' => sanitize_text_field( wp_unslash( $_POST['page_title'] ?? '' ) ),
			'url'   => esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) ),
			'type'  => sanitize_key( wp_unslash( $_POST['page_type'] ?? '' ) ),
		);

		wp_send_json_success( $this->build_assistant_response( $message, $history, $page_id, $page ) );
	}

	/**
	 * Build assistant response.
	 *
	 * @param string $message User message.
	 * @param array  $history History.
	 * @param int    $page_id Current page ID.
	 * @param array  $page Client page context.
	 * @return array<string,mixed>
	 */
	private function build_assistant_response( $message, $history, $page_id, $page ) {
		$settings    = $this->settings();
		$diagnostics = array(
			'has_api_key' => '' !== $settings['api_key'],
			'base_url'    => $settings['base_url'],
			'model'       => $settings['model'],
		);

		$search_link = $this->build_search_link( $message );
		$context     = $this->collect_site_context( $page_id, $page );

		$system_prompt = implode(
			"\n",
			array(
				'You are Softland AI, an ecommerce shopping assistant for Softland.',
				'Always reply in Saudi Arabic dialect, even if the user writes in another language, unless the user explicitly asks you to switch language.',
				'Use only the provided store context and never invent stock, pricing, shipping, or warranty details.',
				'Prefer the most relevant product, category, or page link inside the site.',
				'Keep the answer short, helpful, action-oriented, and natural in Saudi dialect.',
				'If the question is outside the available context, be honest and guide the user to the closest page in Saudi dialect.',
				'Return only valid JSON with keys: answer, suggestions, links.',
				'links must be an array of objects with label and url.',
				'Store profile: ' . $settings['store_profile'],
				'Reply style: ' . $settings['answer_style'],
			)
		);

		$user_prompt = wp_json_encode(
			array(
				'question'     => $message,
				'history'      => $history,
				'search_link'  => $search_link,
				'site_context' => $context,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$content = $this->deepseek_request(
			array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			)
		);

		if ( is_wp_error( $content ) ) {
			$diagnostics['request_error'] = $this->error_details( $content );
			error_log( 'Softland AI fallback: ' . wp_json_encode( $diagnostics ) );
			return $this->fallback_response( $message, $search_link, $page, $page_id, $diagnostics );
		}

		$parsed = $this->extract_json( $content );
		if ( ! is_array( $parsed ) || empty( $parsed['answer'] ) ) {
			$diagnostics['parse_error'] = 'invalid_or_empty_json';
			error_log( 'Softland AI parse fallback: ' . wp_json_encode( $diagnostics ) );
			return $this->fallback_response( $message, $search_link, $page, $page_id, $diagnostics );
		}

		$links = array();
		if ( ! empty( $parsed['links'] ) && is_array( $parsed['links'] ) ) {
			foreach ( $parsed['links'] as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}
				$links[] = array(
					'label' => sanitize_text_field( $link['label'] ?? '' ),
					'url'   => esc_url_raw( $link['url'] ?? '' ),
				);
			}
		}

		if ( ! empty( $search_link ) ) {
			$links[] = $search_link;
		}

		if ( $page_id && get_permalink( $page_id ) ) {
			$links[] = array(
				'label' => 'الصفحة الحالية',
				'url'   => get_permalink( $page_id ),
			);
		} elseif ( ! empty( $page['url'] ) ) {
			$links[] = array(
				'label' => 'الصفحة الحالية',
				'url'   => $page['url'],
			);
		}

		$suggestions = array();
		if ( ! empty( $parsed['suggestions'] ) && is_array( $parsed['suggestions'] ) ) {
			foreach ( array_slice( $parsed['suggestions'], 0, 4 ) as $suggestion ) {
				$suggestion = sanitize_text_field( (string) $suggestion );
				if ( '' !== $suggestion ) {
					$suggestions[] = $suggestion;
				}
			}
		}

		if ( empty( $suggestions ) ) {
			$suggestions = $this->initial_suggestions();
		}

		return array(
			'answer'      => sanitize_textarea_field( (string) $parsed['answer'] ),
			'suggestions' => array_values( array_unique( $suggestions ) ),
			'links'       => $this->unique_links( $links ),
			'source'      => 'ai',
			'diagnostics' => $diagnostics,
		);
	}

	/**
	 * Fallback response.
	 */
	private function fallback_response( $message, $search_link, $page, $page_id, $diagnostics ) {
		$links = $this->important_links();

		if ( ! empty( $search_link ) ) {
			$links[] = $search_link;
		}

		if ( $page_id && get_permalink( $page_id ) ) {
			$links[] = array(
				'label' => 'الصفحة الحالية',
				'url'   => get_permalink( $page_id ),
			);
		} elseif ( ! empty( $page['url'] ) ) {
			$links[] = array(
				'label' => 'الصفحة الحالية',
				'url'   => $page['url'],
			);
		}

		$message_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $message ) : strtolower( $message );
		$mentions_shipping = false !== strpos( $message_lower, 'شحن' ) || false !== strpos( $message_lower, 'shipping' ) || false !== strpos( $message_lower, 'ضمان' ) || false !== strpos( $message_lower, 'warranty' );
		$mentions_installment = false !== strpos( $message_lower, 'تقسيط' ) || false !== strpos( $message_lower, 'tabby' ) || false !== strpos( $message_lower, 'tamara' ) || false !== strpos( $message_lower, 'installment' );

		$answer = 'أقدر أساعدك توصل بسرعة للمنتجات، الأقسام، وصفحات المتجر المهمة داخل سوفت لاند. اذكر اسم القطعة أو استخدامك مثل تجميعة ألعاب، كرت شاشة، شاشة، شحن، أو تقسيط وبوجّهك لأقرب نتيجة.';

		if ( $mentions_shipping ) {
			$answer = 'إذا سؤالك عن الشحن أو الضمان، افتح صفحة الشروط أو الفروع أو ادخل على صفحة المنتج نفسه عشان تشوف التفاصيل النهائية.';
		} elseif ( $mentions_installment ) {
			$answer = 'إذا تبي تقسيط، ابدأ من صفحات العروض أو المتجر أو المنتجات المناسبة، وبعدها راجع الشروط النهائية داخل صفحة المنتج أو صفحة الشروط.';
		}

		return array(
			'answer'      => $answer,
			'suggestions' => $this->initial_suggestions(),
			'links'       => $this->unique_links( $links ),
			'source'      => 'fallback',
			'diagnostics' => $diagnostics,
		);
	}

	/**
	 * DeepSeek request.
	 */
	private function deepseek_request( $messages ) {
		$settings = $this->settings();
		$api_key  = trim( (string) $settings['api_key'] );
		$base_url = trim( (string) $settings['base_url'] );
		$model    = trim( (string) $settings['model'] );

		if ( '' === $api_key ) {
			return new WP_Error( 'softland_ai_missing_key', 'DeepSeek API key is not configured.' );
		}

		$payload = array(
			'model'       => $model ? $model : 'deepseek-chat',
			'messages'    => $messages,
			'temperature' => 0.15,
			'max_tokens'  => 520,
		);

		$endpoints = array_unique(
			array(
				untrailingslashit( $base_url ) . '/chat/completions',
				untrailingslashit( $base_url ) . '/v1/chat/completions',
			)
		);

		$last_error = null;

		foreach ( $endpoints as $endpoint ) {
			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 18,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
						'Accept'        => 'application/json',
					),
					'body'    => wp_json_encode( $payload ),
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				continue;
			}

			$code     = (int) wp_remote_retrieve_response_code( $response );
			$raw_body = wp_remote_retrieve_body( $response );
			$body     = json_decode( $raw_body, true );

			if ( $code < 200 || $code >= 300 ) {
				$last_error = new WP_Error(
					'softland_ai_http_error',
					is_array( $body ) ? ( $body['error']['message'] ?? $body['message'] ?? 'DeepSeek API request failed.' ) : 'DeepSeek API request failed.',
					array(
						'status'   => $code,
						'endpoint' => $endpoint,
					)
				);
				continue;
			}

			$content = $body['choices'][0]['message']['content'] ?? '';
			if ( is_array( $content ) ) {
				$content = wp_json_encode( $content );
			}

			if ( ! is_string( $content ) || '' === trim( $content ) ) {
				$last_error = new WP_Error(
					'softland_ai_empty_response',
					'DeepSeek returned an empty response.',
					array(
						'endpoint' => $endpoint,
					)
				);
				continue;
			}

			return $content;
		}

		return $last_error ?: new WP_Error( 'softland_ai_request_failed', 'DeepSeek request failed.' );
	}

	/**
	 * Extract JSON from model response.
	 */
	private function extract_json( $content ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return null;
		}

		$clean   = preg_replace( '/^```(?:json)?|```$/mi', '', $content );
		$clean   = trim( (string) $clean );
		$decoded = json_decode( $clean, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		if ( preg_match( '/\{.*\}/s', $clean, $matches ) ) {
			$decoded = json_decode( $matches[0], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * Sanitize conversation history.
	 */
	private function sanitize_history( $raw ) {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$history = array();
		foreach ( array_slice( $raw, -6 ) as $message ) {
			$role    = sanitize_key( $message['role'] ?? '' );
			$content = sanitize_textarea_field( $message['content'] ?? '' );
			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) || '' === $content ) {
				continue;
			}

			$history[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}

		return $history;
	}

	/**
	 * Get error details for diagnostics.
	 */
	private function error_details( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return array();
		}

		$data = $error->get_error_data();
		return array_filter(
			array(
				'code'     => $error->get_error_code(),
				'message'  => $error->get_error_message(),
				'status'   => is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0,
				'endpoint' => is_array( $data ) ? (string) ( $data['endpoint'] ?? '' ) : '',
			)
		);
	}

	/**
	 * Unique links.
	 */
	private function unique_links( $links ) {
		$seen   = array();
		$unique = array();

		foreach ( $links as $link ) {
			$label = sanitize_text_field( $link['label'] ?? '' );
			$url   = esc_url_raw( $link['url'] ?? '' );
			if ( '' === $label || '' === $url ) {
				continue;
			}

			$key = md5( $label . '|' . $url );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return array_slice( $unique, 0, 5 );
	}

	/**
	 * Important links.
	 */
	private function important_links() {
		$links = array(
			array(
				'label' => 'الرئيسية',
				'url'   => home_url( '/' ),
			),
		);

		$shop_url = $this->get_shop_url();
		if ( $shop_url ) {
			$links[] = array(
				'label' => 'المتجر',
				'url'   => $shop_url,
			);
		}

		$links = array_merge( $links, $this->configured_page_links() );

		foreach ( $this->featured_category_links() as $category_link ) {
			$links[] = $category_link;
		}

		return $this->unique_links( $links );
	}

	/**
	 * Collect site context for the assistant.
	 */
	private function collect_site_context( $page_id, $page ) {
		$context = array(
			'site'          => array(
				'name'        => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
				'home_url'    => home_url( '/' ),
				'language'    => determine_locale(),
			),
			'store_profile' => $this->settings()['store_profile'],
			'current_page'  => array_filter(
				array(
					'title' => $page_id ? get_the_title( $page_id ) : ( $page['title'] ?? '' ),
					'url'   => $page_id ? get_permalink( $page_id ) : ( $page['url'] ?? '' ),
					'type'  => $page_id ? get_post_type( $page_id ) : ( $page['type'] ?? '' ),
					'text'  => $page_id ? wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $page_id ) ), 32 ) : '',
				)
			),
			'important_links' => $this->important_links(),
			'store'           => $this->get_cached_store_context(),
			'content'         => array(
				'pages' => $this->collect_posts( 'page', 4 ),
				'posts' => $this->collect_posts( 'post', 3 ),
			),
		);

		return $context;
	}

	/**
	 * Get cached store context.
	 */
	private function get_cached_store_context() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$context = $this->build_store_context();
		set_transient( self::CACHE_KEY, $context, 10 * MINUTE_IN_SECONDS );

		return $context;
	}

	/**
	 * Flush cached context.
	 */
	public function flush_store_context_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Build store context.
	 */
	private function build_store_context() {
		$settings = $this->settings();

		return array(
			'platform'            => class_exists( 'WooCommerce' ) ? 'woocommerce' : 'wordpress',
			'featured_categories' => $this->collect_product_categories(
				(int) $settings['max_categories'],
				$this->multiline_values( $settings['featured_categories'] )
			),
			'recent_products'     => $this->collect_products( (int) $settings['max_products'] ),
			'featured_products'   => $this->collect_products( 4, array( 'featured' => true ) ),
			'core_pages'          => $this->core_store_pages(),
			'configured_pages'    => $this->configured_page_links(),
		);
	}

	/**
	 * Collect posts for context.
	 */
	private function collect_posts( $post_type, $limit ) {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'title'   => get_the_title( $post->ID ),
				'url'     => get_permalink( $post->ID ),
				'type'    => $post->post_type,
				'excerpt' => wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post->ID ) ), 18 ),
			);
		}

		wp_reset_postdata();
		return $items;
	}

	/**
	 * Collect WooCommerce products.
	 */
	private function collect_products( $limit, $args = array() ) {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}

		$query_args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		);

		if ( ! empty( $args['featured'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'featured',
				),
			);
		}

		$query = new WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$product_terms = get_the_terms( $post->ID, 'product_cat' );
			$categories    = array();

			if ( ! is_wp_error( $product_terms ) && is_array( $product_terms ) ) {
				foreach ( array_slice( $product_terms, 0, 3 ) as $term ) {
					$categories[] = $term->name;
				}
			}

			$items[] = array_filter(
				array(
					'title'      => get_the_title( $post->ID ),
					'url'        => get_permalink( $post->ID ),
					'type'       => 'product',
					'excerpt'    => wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post->ID ) ), 22 ),
					'categories' => $categories,
				)
			);
		}

		wp_reset_postdata();
		return $items;
	}

	/**
	 * Collect product categories.
	 */
	private function collect_product_categories( $limit, $preferred_names = array() ) {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 30,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$preferred = array();
		$others    = array();

		foreach ( $terms as $term ) {
			$item = array(
				'name'  => $term->name,
				'url'   => get_term_link( $term ),
				'slug'  => $term->slug,
				'count' => (int) $term->count,
			);

			if ( is_wp_error( $item['url'] ) ) {
				continue;
			}

			$matched = false;
			foreach ( $preferred_names as $preferred_name ) {
				if ( $this->contains_text( $term->name, $preferred_name ) || $this->contains_text( $term->slug, $preferred_name ) ) {
					$matched = true;
					break;
				}
			}

			if ( $matched ) {
				$preferred[] = $item;
			} else {
				$others[] = $item;
			}
		}

		return array_slice( array_merge( $preferred, $others ), 0, $limit );
	}

	/**
	 * Build a focused search link when possible.
	 */
	private function build_search_link( $message ) {
		$message = sanitize_text_field( $message );
		$shop_url = $this->get_shop_url();

		if ( $shop_url ) {
			return array(
				'label' => 'نتائج البحث في المتجر',
				'url'   => add_query_arg( 's', rawurlencode( $message ), $shop_url ),
			);
		}

		return array(
			'label' => 'نتائج البحث',
			'url'   => add_query_arg( 's', rawurlencode( $message ), home_url( '/' ) ),
		);
	}

	/**
	 * Get core WooCommerce pages.
	 */
	private function core_store_pages() {
		$pages = array();
		$labels = array(
			'shop'      => 'المتجر',
			'cart'      => 'السلة',
			'checkout'  => 'الدفع',
			'myaccount' => 'حسابي',
		);

		foreach ( $labels as $key => $label ) {
			$url = '';

			if ( function_exists( 'wc_get_page_permalink' ) ) {
				$url = wc_get_page_permalink( $key );
			}

			if ( ! $url && 'shop' === $key ) {
				$url = $this->get_shop_url();
			}

			if ( $url ) {
				$pages[] = array(
					'label' => $label,
					'url'   => $url,
				);
			}
		}

		return $pages;
	}

	/**
	 * Build page links from configured titles/slugs.
	 */
	private function configured_page_links() {
		$values = $this->multiline_values( $this->settings()['important_pages'] );
		$links  = array();

		foreach ( $values as $value ) {
			$page = get_page_by_path( sanitize_title( $value ), OBJECT, 'page' );

			if ( ! $page ) {
				$page = $this->find_page_by_title_like( $value );
			}

			if ( ! $page instanceof WP_Post ) {
				continue;
			}

			$links[] = array(
				'label' => get_the_title( $page->ID ),
				'url'   => get_permalink( $page->ID ),
			);
		}

		return $this->unique_links( array_merge( $this->core_store_pages(), $links ) );
	}

	/**
	 * Build links for featured categories.
	 */
	private function featured_category_links() {
		$links = array();

		foreach ( $this->collect_product_categories( 4, $this->multiline_values( $this->settings()['featured_categories'] ) ) as $category ) {
			$links[] = array(
				'label' => $category['name'],
				'url'   => $category['url'],
			);
		}

		return $links;
	}

	/**
	 * Find page by title fragment.
	 */
	private function find_page_by_title_like( $title ) {
		$query = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				's'                      => $title,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$post = ! empty( $query->posts[0] ) ? $query->posts[0] : null;
		wp_reset_postdata();

		return $post;
	}

	/**
	 * Get shop URL.
	 */
	private function get_shop_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );
			if ( $shop ) {
				return $shop;
			}
		}

		if ( post_type_exists( 'product' ) ) {
			$archive = get_post_type_archive_link( 'product' );
			if ( $archive ) {
				return $archive;
			}
		}

		return '';
	}

	/**
	 * Convert textarea lines to values.
	 *
	 * @param string $text Multiline text.
	 * @return string[]
	 */
	private function multiline_values( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$lines = is_array( $lines ) ? $lines : array();
		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines );

		return array_values( array_unique( $lines ) );
	}

	/**
	 * Safe contains helper.
	 */
	private function contains_text( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = trim( (string) $needle );

		if ( '' === $haystack || '' === $needle ) {
			return false;
		}

		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle );
		}

		return false !== stripos( $haystack, $needle );
	}
}

Softland_AI_Plugin::init();
