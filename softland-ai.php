<?php
/**
 * Plugin Name: Softland AI
 * Plugin URI: https://softland.app/
 * Description: Floating AI assistant widget for WordPress with DeepSeek integration, multilingual UI support, contextual answers, and admin API settings.
 * Version: 1.0.0
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
	}

	/**
	 * Settings defaults.
	 *
	 * @return array<string,mixed>
	 */
	private function defaults() {
		return array(
			'enabled'       => '1',
			'api_key'       => '',
			'base_url'      => 'https://api.deepseek.com',
			'model'         => 'deepseek-chat',
			'widget_label'  => 'Softland AI',
			'launcher_text' => '',
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
			__( 'DeepSeek Settings', 'softland-ai' ),
			function () {
				echo '<p>' . esc_html__( 'Configure DeepSeek API access for the floating AI widget.', 'softland-ai' ) . '</p>';
			},
			'softland-ai'
		);

		add_settings_field( 'enabled', __( 'Enable Widget', 'softland-ai' ), array( $this, 'field_enabled' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'api_key', __( 'DeepSeek API Key', 'softland-ai' ), array( $this, 'field_api_key' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'base_url', __( 'DeepSeek Base URL', 'softland-ai' ), array( $this, 'field_base_url' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'model', __( 'DeepSeek Model', 'softland-ai' ), array( $this, 'field_model' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'widget_label', __( 'Widget Label', 'softland-ai' ), array( $this, 'field_widget_label' ), 'softland-ai', 'softland_ai_api' );
		add_settings_field( 'launcher_text', __( 'Launcher Text', 'softland-ai' ), array( $this, 'field_launcher_text' ), 'softland-ai', 'softland_ai_api' );
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
			'enabled'       => empty( $input['enabled'] ) ? '0' : '1',
			'api_key'       => sanitize_text_field( $input['api_key'] ?? $defaults['api_key'] ),
			'base_url'      => untrailingslashit( esc_url_raw( $input['base_url'] ?? $defaults['base_url'] ) ),
			'model'         => sanitize_text_field( $input['model'] ?? $defaults['model'] ),
			'widget_label'  => sanitize_text_field( $input['widget_label'] ?? $defaults['widget_label'] ),
			'launcher_text' => sanitize_text_field( $input['launcher_text'] ?? $defaults['launcher_text'] ),
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
			<p><?php esc_html_e( 'Floating AI assistant with DeepSeek integration for WordPress.', 'softland-ai' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'softland_ai_group' ); ?>
				<?php do_settings_sections( 'softland-ai' ); ?>
				<?php submit_button(); ?>
			</form>
			<hr>
			<p><strong><?php esc_html_e( 'Saved API key status:', 'softland-ai' ); ?></strong> <?php echo ! empty( $settings['api_key'] ) ? esc_html__( 'API key saved', 'softland-ai' ) : esc_html__( 'No API key saved yet', 'softland-ai' ); ?></p>
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
			<?php esc_html_e( 'Render the floating widget on the frontend', 'softland-ai' ); ?>
		</label>
		<?php
	}

	public function field_api_key() {
		$settings = $this->settings();
		?>
		<input class="regular-text" type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="new-password">
		<p class="description"><?php esc_html_e( 'Paste your DeepSeek secret key here. It is only used server-side.', 'softland-ai' ); ?></p>
		<?php
	}

	public function field_base_url() {
		$settings = $this->settings();
		?>
		<input class="regular-text" type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[base_url]" value="<?php echo esc_attr( $settings['base_url'] ); ?>">
		<?php
	}

	public function field_model() {
		$settings = $this->settings();
		?>
		<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( $settings['model'] ); ?>">
		<p class="description"><?php esc_html_e( 'Example: deepseek-chat', 'softland-ai' ); ?></p>
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
		<p class="description"><?php esc_html_e( 'Leave empty to auto-switch between Arabic and English defaults.', 'softland-ai' ); ?></p>
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
			$this->plugin_url( 'assets/css/frontend.css' ),
			array(),
			file_exists( $this->plugin_path( 'assets/css/frontend.css' ) ) ? filemtime( $this->plugin_path( 'assets/css/frontend.css' ) ) : '1.0.0'
		);

		wp_enqueue_script(
			'softland-ai-frontend',
			$this->plugin_url( 'assets/js/frontend.js' ),
			array(),
			file_exists( $this->plugin_path( 'assets/js/frontend.js' ) ) ? filemtime( $this->plugin_path( 'assets/js/frontend.js' ) ) : '1.0.0',
			true
		);

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
				'isRtl'              => $this->is_rtl_lang(),
				'isAdmin'            => current_user_can( 'manage_options' ),
				'widgetLabel'        => $settings['widget_label'],
				'launcherText'       => $settings['launcher_text'] ? $settings['launcher_text'] : ( $this->is_rtl_lang() ? 'استخدم AI' : 'Use AI' ),
				'thinking'           => $this->is_rtl_lang() ? 'جارٍ تجهيز الرد...' : 'Preparing the answer...',
				'empty'              => $this->is_rtl_lang() ? 'اكتب سؤالك أولاً.' : 'Write your question first.',
				'error'              => $this->is_rtl_lang() ? 'تعذر الرد الآن. حاول مرة أخرى.' : 'Unable to answer right now. Please try again.',
				'userLabel'          => $this->is_rtl_lang() ? 'أنت' : 'You',
				'botLabel'           => $settings['widget_label'],
				'heading'            => $this->is_rtl_lang() ? 'ابدأ بطرح سؤالك' : 'Start asking questions',
				'subheading'         => $this->is_rtl_lang() ? 'المساعد يفهم محتوى الموقع ويقترح صفحات وروابط مناسبة.' : 'The assistant understands your site and suggests relevant pages and links.',
				'placeholder'        => $this->is_rtl_lang() ? 'مثال: أريد شقة للبيع أو أريد صفحة التسعير أو كيف أضيف عقاري؟' : 'Example: I need an apartment for sale, pricing page, or how to add my property?',
				'disclaimer'         => $this->is_rtl_lang() ? 'قد يخطئ الذكاء الصناعي أحيانًا، راجع التفاصيل النهائية داخل الصفحة المقترحة.' : 'AI may occasionally make mistakes, so verify final details on the suggested page.',
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
		if ( $this->is_rtl_lang() ) {
			return array(
				'ما أفضل المناطق للاستثمار؟',
				'أرني أحدث الصفحات أو العقارات',
				'كيف أضيف عقاري على الموقع؟',
			);
		}

		return array(
			'What are the best areas for investment?',
			'Show me the newest properties or pages',
			'How do I add my property to the site?',
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
		$rtl = $this->is_rtl_lang();

		ob_start();
		?>
		<div class="softland-ai" data-softland-ai dir="<?php echo esc_attr( $rtl ? 'rtl' : 'ltr' ); ?>">
			<button class="softland-ai__launcher" type="button" data-softland-ai-launcher aria-expanded="false" aria-controls="softland-ai-panel">
				<span class="softland-ai__spark" aria-hidden="true">✦</span>
				<span><?php echo esc_html( $this->settings()['launcher_text'] ? $this->settings()['launcher_text'] : ( $rtl ? 'استخدم AI' : 'Use AI' ) ); ?></span>
			</button>

			<section class="softland-ai__panel" id="softland-ai-panel" data-softland-ai-panel hidden aria-hidden="true">
				<div class="softland-ai__card">
					<div class="softland-ai__head">
						<div>
							<strong><?php echo esc_html( $this->settings()['widget_label'] ); ?></strong>
							<p><?php echo esc_html( $rtl ? 'اسأل عن العقارات، الصفحات، الخدمات، أو أي محتوى داخل الموقع.' : 'Ask about properties, pages, services, or any content inside the site.' ); ?></p>
						</div>
						<button class="softland-ai__close" type="button" data-softland-ai-close aria-label="<?php echo esc_attr( $rtl ? 'إغلاق' : 'Close' ); ?>">×</button>
					</div>

					<div class="softland-ai__body">
						<div class="softland-ai__intro" data-softland-ai-intro>
							<span class="softland-ai__hero-icon" aria-hidden="true">✦</span>
							<h3><?php echo esc_html( $rtl ? 'ابدأ بطرح سؤالك' : 'Start asking questions' ); ?></h3>
							<p><?php echo esc_html( $rtl ? 'المساعد يفهم الموقع بالكامل ويقترح إجابات وروابط وصفحات مناسبة.' : 'The assistant understands the whole site and suggests answers, links, and relevant pages.' ); ?></p>
						</div>

						<div class="softland-ai__chips" data-softland-ai-chips></div>
						<div class="softland-ai__messages" data-softland-ai-messages></div>
						<div class="softland-ai__status" data-softland-ai-status aria-live="polite"></div>
					</div>

					<form class="softland-ai__composer" data-softland-ai-form>
						<label class="screen-reader-text" for="softland-ai-input"><?php echo esc_html( $rtl ? 'سؤال المساعد' : 'Assistant prompt' ); ?></label>
						<textarea id="softland-ai-input" name="message" rows="3" data-softland-ai-input placeholder="<?php echo esc_attr( $rtl ? 'مثال: ما أفضل المناطق للاستثمار؟' : 'Example: What are the best areas for investment?' ); ?>"></textarea>
						<button class="softland-ai__submit" type="submit" data-softland-ai-submit aria-label="<?php echo esc_attr( $rtl ? 'إرسال' : 'Send' ); ?>">→</button>
					</form>

					<p class="softland-ai__disclaimer"><?php echo esc_html( $rtl ? 'قد يخطئ الذكاء الصناعي أحيانًا، لذلك راجع التفاصيل النهائية داخل الصفحة المقترحة.' : 'AI may occasionally make mistakes, so verify final details on the suggested page.' ); ?></p>
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
					'message' => $this->is_rtl_lang() ? 'اكتب سؤالك أولاً.' : 'Write your question first.',
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
		$diagnostics = array(
			'has_api_key' => '' !== $this->settings()['api_key'],
			'base_url'    => $this->settings()['base_url'],
			'model'       => $this->settings()['model'],
		);

		$search_link = $this->build_search_link( $message );
		$context     = $this->collect_site_context( $page_id, $page );

		$system_prompt = 'You are Softland AI, a concise website concierge. Reply in the same language as the user. Use only the given site context. Return only valid JSON with: answer, suggestions, links. Each link item must contain label and url. Keep the answer short and useful.';
		$user_prompt   = wp_json_encode(
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
				'label' => $this->is_rtl_lang() ? 'الصفحة الحالية' : 'Current page',
				'url'   => get_permalink( $page_id ),
			);
		} elseif ( ! empty( $page['url'] ) ) {
			$links[] = array(
				'label' => $this->is_rtl_lang() ? 'الصفحة الحالية' : 'Current page',
				'url'   => $page['url'],
			);
		}

		$suggestions = array();
		if ( ! empty( $parsed['suggestions'] ) && is_array( $parsed['suggestions'] ) ) {
			foreach ( array_slice( $parsed['suggestions'], 0, 3 ) as $suggestion ) {
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
				'label' => $this->is_rtl_lang() ? 'الصفحة الحالية' : 'Current page',
				'url'   => get_permalink( $page_id ),
			);
		} elseif ( ! empty( $page['url'] ) ) {
			$links[] = array(
				'label' => $this->is_rtl_lang() ? 'الصفحة الحالية' : 'Current page',
				'url'   => $page['url'],
			);
		}

		return array(
			'answer'      => $this->is_rtl_lang()
				? 'أستطيع مساعدتك في استكشاف محتوى الموقع والصفحات المهمة. جرّب سؤالًا عن منطقة، صفحة، خدمة، أو نوع عقار وسأوجّهك لأقرب النتائج والروابط المناسبة.'
				: 'I can help you explore the site and important pages. Ask about an area, page, service, or property type and I will point you to the most relevant results and links.',
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
			'temperature' => 0.2,
			'max_tokens'  => 650,
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
					'timeout' => 25,
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

		return array_slice( $unique, 0, 4 );
	}

	/**
	 * Important links.
	 */
	private function important_links() {
		$links = array(
			array(
				'label' => $this->is_rtl_lang() ? 'الرئيسية' : 'Home',
				'url'   => home_url( '/' ),
			),
		);

		if ( get_post_type_archive_link( 'property' ) ) {
			$links[] = array(
				'label' => $this->is_rtl_lang() ? 'كل العقارات' : 'All properties',
				'url'   => get_post_type_archive_link( 'property' ),
			);
		}

		if ( get_post_type_archive_link( 'area' ) ) {
			$links[] = array(
				'label' => $this->is_rtl_lang() ? 'المناطق' : 'Areas',
				'url'   => get_post_type_archive_link( 'area' ),
			);
		}

		$pages = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
				'number'      => 3,
			)
		);

		foreach ( $pages as $page ) {
			$links[] = array(
				'label' => get_the_title( $page->ID ),
				'url'   => get_permalink( $page->ID ),
			);
		}

		return $links;
	}

	/**
	 * Collect site context for the assistant.
	 */
	private function collect_site_context( $page_id, $page ) {
		$context = array(
			'site' => array(
				'name'        => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
				'home_url'    => home_url( '/' ),
				'language'    => determine_locale(),
			),
			'current_page' => array_filter(
				array(
					'title' => $page_id ? get_the_title( $page_id ) : ( $page['title'] ?? '' ),
					'url'   => $page_id ? get_permalink( $page_id ) : ( $page['url'] ?? '' ),
					'type'  => $page_id ? get_post_type( $page_id ) : ( $page['type'] ?? '' ),
					'text'  => $page_id ? wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $page_id ) ), 30 ) : '',
				)
			),
			'important_links' => $this->important_links(),
			'content'         => array(
				'pages'      => $this->collect_posts( 'page', 4 ),
				'posts'      => $this->collect_posts( 'post', 4 ),
				'properties' => post_type_exists( 'property' ) ? $this->collect_posts( 'property', 5 ) : array(),
				'areas'      => post_type_exists( 'area' ) ? $this->collect_posts( 'area', 4 ) : array(),
				'agencies'   => post_type_exists( 'agency' ) ? $this->collect_posts( 'agency', 3 ) : array(),
				'agents'     => post_type_exists( 'agent' ) ? $this->collect_posts( 'agent', 3 ) : array(),
			),
		);

		return $context;
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
	 * Build a focused search link when possible.
	 */
	private function build_search_link( $message ) {
		$message = sanitize_text_field( $message );
		$property_archive = get_post_type_archive_link( 'property' );

		if ( $property_archive ) {
			return array(
				'label' => $this->is_rtl_lang() ? 'نتائج البحث الذكي' : 'Smart search results',
				'url'   => add_query_arg( 's', rawurlencode( $message ), $property_archive ),
			);
		}

		return array(
			'label' => $this->is_rtl_lang() ? 'نتائج البحث' : 'Search results',
			'url'   => add_query_arg( 's', rawurlencode( $message ), home_url( '/' ) ),
		);
	}
}

Softland_AI_Plugin::init();
