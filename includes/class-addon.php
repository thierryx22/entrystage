<?php
/**
 * Gravity Forms add-on: settings tab of each form (the workshop, see class-atelier.php), plugin
 * settings (appearance, license), shortcode and front-end assets.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Addon extends \GFAddOn {

	protected $_version                  = ENTRYSTAGE_VERSION;
	protected $_min_gravityforms_version = ENTRYSTAGE_MIN_GF_VERSION;
	protected $_slug                     = Settings::SLUG;
	protected $_path                     = 'entrystage/entrystage.php';
	protected $_full_path                = ENTRYSTAGE_FILE;
	protected $_title                    = 'EntryStage';
	protected $_short_title              = 'EntryStage';
	protected $_capabilities_settings_page = 'gravityforms_edit_settings';
	protected $_capabilities_form_settings = 'gravityforms_edit_forms';
	protected $_capabilities_uninstall     = 'gravityforms_uninstall';

	/** @var Addon|null */
	private static $instance = null;

	/**
	 * Single instance.
	 *
	 * @return Addon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Front-end and shared hooks.
	 */
	public function init() {
		parent::init();
		add_shortcode( 'entrystage', array( Renderer::class, 'shortcode' ) );
		Block::hooks();
		add_action( 'template_redirect', array( Actions::class, 'maybe_handle' ), 5 );
		add_action( 'gform_after_save_form', array( Settings::class, 'flush' ) );
		Editor::hooks();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_color_picker' ) );
	}

	/**
	 * Styles of the lists: name => label. "minimal" (layout only) and "none" (no stylesheet) come after them.
	 *
	 * @return array
	 */
	public static function skins() {
		$skins = array(
			'editorial' => __( 'Editorial template: no frame, headings in small capitals, a strong rule on top of the table, underlined tabs and fields', 'entrystage' ),
		);
		return $skins;
	}

	/**
	 * Style of the lists: one of skins(), minimal (layout only) or none.
	 * "full" (the single style of the first versions) is now "editorial"; sites that had unticked
	 * "Use the plugin stylesheet" get "none".
	 *
	 * @return string
	 */
	public function style_mode() {
		$mode = (string) $this->get_plugin_setting( 'style' );
		if ( array_key_exists( $mode, self::skins() ) || in_array( $mode, array( 'minimal', 'none' ), true ) ) {
			return $mode;
		}
		if ( '' === $mode && '0' === (string) $this->get_plugin_setting( 'load_css' ) ) {
			return 'none';
		}
		return 'editorial';
	}

	/**
	 * Fonts offered for the lists: setting => array( label, CSS font-family or '' ). Fonts already installed on the
	 * visitor's computer only: nothing is loaded from another site.
	 *
	 * @return array
	 */
	public static function fonts() {
		return array(
			''          => array( __( 'Font of the style', 'entrystage' ), '' ),
			'theme'     => array( __( 'Font of the theme', 'entrystage' ), 'inherit' ),
			'system'    => array( __( 'System (sans serif)', 'entrystage' ), 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' ),
			'humanist'  => array( __( 'Humanist (sans serif)', 'entrystage' ), 'Seravek, "Gill Sans Nova", Ubuntu, Calibri, "DejaVu Sans", source-sans-pro, sans-serif' ),
			'geometric' => array( __( 'Geometric (sans serif)', 'entrystage' ), 'Avenir, Montserrat, Corbel, "URW Gothic", source-sans-pro, sans-serif' ),
			'serif'     => array( __( 'Classic serif', 'entrystage' ), 'Charter, "Bitstream Charter", "Sitka Text", Cambria, serif' ),
		);
	}

	/**
	 * Colour settings of the styles: setting => array( CSS variable, colour saved as default by the first
	 * versions (ignored), label, help ). Empty: the colour of the chosen style.
	 *
	 * @return array
	 */
	public static function color_settings() {
		return array(
			'accent'       => array( '--estg-accent', '#2563eb', __( 'Main colour', 'entrystage' ), __( 'Buttons, links, icons, active tab and page. Empty: the colour of the style.', 'entrystage' ) ),
			'color_bg'     => array( '--estg-bg', '', __( 'Background colour', 'entrystage' ), __( 'Background around the list. Empty: none.', 'entrystage' ) ),
			'color_border' => array( '--estg-border', '#e5e7eb', __( 'Lines', 'entrystage' ), __( 'Lines of the table, of the cards and of the fields. Empty: the colour of the style.', 'entrystage' ) ),
		);
	}

	/**
	 * WordPress colour picker on the settings page of the plugin.
	 *
	 * @param string $hook Admin page.
	 */
	public function enqueue_color_picker( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page detection only.
		if ( 'gf_settings' !== rgget( 'page' ) || Settings::SLUG !== rgget( 'subview' ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'estg-colors', ENTRYSTAGE_URL . 'assets/js/colors.js', array( 'wp-color-picker' ), self::asset_version( 'assets/js/colors.js' ), true );
	}

	/**
	 * Front-end CSS and JS, loaded only on pages that display a list or a form opened from it.
	 */
	public function enqueue_front_assets() {
		$this->enqueue_front_style();
		wp_enqueue_script( 'estg-front', ENTRYSTAGE_URL . 'assets/js/front.js', array(), self::asset_version( 'assets/js/front.js' ), true );
	}

	/**
	 * Plugin stylesheet (unless switched off) and the colour chosen in the plugin settings.
	 */
	public function enqueue_front_style() {
		$mode = $this->style_mode();
		if ( 'none' === $mode ) {
			return;
		}
		if ( 'minimal' === $mode ) {
			// Layout only: colours, backgrounds and fonts come from the theme.
			wp_enqueue_style( 'estg-front', ENTRYSTAGE_URL . 'assets/css/front-base.css', array(), self::asset_version( 'assets/css/front-base.css' ) );
			return;
		}
		wp_enqueue_style( 'estg-front', ENTRYSTAGE_URL . 'assets/css/front.css', array(), self::asset_version( 'assets/css/front.css' ) );
		$file = 'assets/css/skin-' . $mode . '.css';
		if ( ! file_exists( ENTRYSTAGE_DIR . $file ) ) {
			$file = 'assets/css/skin-editorial.css';
		}
		wp_enqueue_style( 'estg-skin', ENTRYSTAGE_URL . $file, array( 'estg-front' ), self::asset_version( $file ) );
		// colours and font chosen in the settings, over those of the style
		$css = '';
		foreach ( self::color_settings() as $name => $color ) {
			$value = sanitize_hex_color( (string) $this->get_plugin_setting( $name ) );
			if ( $value && strtolower( $value ) !== $color[1] ) {
				$css .= $color[0] . ':' . $value . ';';
			}
		}
		$fonts = self::fonts();
		$font  = (string) $this->get_plugin_setting( 'font' );
		$rule  = '';
		if ( 'theme' === $font ) {
			$rule = '.estg.estg,.estg-space.estg-space{font-family:inherit}';
		} elseif ( '' !== $font && isset( $fonts[ $font ] ) ) {
			$css .= '--estg-font:' . $fonts[ $font ][1] . ';';
		}
		if ( '' !== $css || '' !== $rule ) {
			wp_add_inline_style( 'estg-skin', ( '' !== $css ? '.estg.estg,.estg-space.estg-space{' . $css . '}' : '' ) . $rule );
		}
	}

	/**
	 * Version of an asset in its URL: the plugin version plus the time the file was last changed, so that
	 * browsers and page caches load the new file after an update, even when the version number is the same.
	 *
	 * @param string $file Path from the plugin folder.
	 * @return string
	 */
	public static function asset_version( $file ) {
		$path = ENTRYSTAGE_DIR . $file;
		return ENTRYSTAGE_VERSION . ( is_file( $path ) ? '.' . filemtime( $path ) : '' );
	}

	/**
	 * Script of the settings workshop (form settings tab of this add-on only).
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'estg_atelier',
				'src'     => ENTRYSTAGE_URL . 'assets/js/atelier.js',
				'version' => self::asset_version( 'assets/js/atelier.js' ),
				'deps'    => array(),
				'strings' => self::atelier_strings(),
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => Settings::SLUG,
					),
				),
			),
		);
		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Texts of the settings workshop script.
	 *
	 * @return array
	 */
	private static function atelier_strings() {
		return array(
			'ready'        => __( 'Ready', 'entrystage' ),
			'check'        => __( 'To check', 'entrystage' ),
			'optional'     => __( 'Optional', 'entrystage' ),
			'copied'       => __( 'Copied', 'entrystage' ),
			'copy'         => __( 'Copy', 'entrystage' ),
			'toColumns'    => __( '+ Column', 'entrystage' ),
			/* translators: %s: field label */
			'addColumn'    => __( 'Add %s to the columns', 'entrystage' ),
			/* translators: %s: column label */
			'moveUp'       => __( 'Move %s up', 'entrystage' ),
			/* translators: %s: column label */
			'moveDown'     => __( 'Move %s down', 'entrystage' ),
			/* translators: %s: column label */
			'remove'       => __( 'Remove %s', 'entrystage' ),
			'colLabel'     => __( 'Label', 'entrystage' ),
			/* translators: %s: example value */
			'example'      => __( 'For example: %s', 'entrystage' ),
			'display'      => __( 'Display', 'entrystage' ),
			'width'        => __( 'Width', 'entrystage' ),
			'auto'         => __( 'auto', 'entrystage' ),
			'sortable'     => __( 'Sortable', 'entrystage' ),
			/* translators: %d: number of columns */
			'count'        => __( 'Columns: %d', 'entrystage' ),
			'allUsed'      => __( 'Every field is in use.', 'entrystage' ),
			'noField'      => __( 'No field matches.', 'entrystage' ),
			'noColumns'    => __( 'No column yet: add fields from the left.', 'entrystage' ),
			'fixedDate'    => __( 'Date of the entry', 'entrystage' ),
			'addDate'      => __( '+ Date column', 'entrystage' ),
			'actions'      => __( 'Actions', 'entrystage' ),
			/* translators: %s: number of fields not drawn */
			'moreFields'   => __( '%s more fields: type a name, a type or a field number to find them.', 'entrystage' ),
			/* translators: %s: column label */
			'moved'        => __( '%s moved.', 'entrystage' ),
			/* translators: %s: column label */
			'added'        => __( '%s added.', 'entrystage' ),
			/* translators: %s: column label */
			'removed'      => __( '%s removed.', 'entrystage' ),
		);
	}

	/**
	 * Styles of the form opened for editing (banner).
	 *
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'  => 'estg-atelier',
				'src'     => ENTRYSTAGE_URL . 'assets/css/atelier.css',
				'version' => self::asset_version( 'assets/css/atelier.css' ),
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => Settings::SLUG,
					),
				),
			),
			array(
				'handle'  => 'estg-form',
				'src'     => ENTRYSTAGE_URL . 'assets/css/front.css',
				'version' => self::asset_version( 'assets/css/front.css' ),
				'enqueue' => array( array( $this, 'is_editing_any_form' ) ),
			),
		);
		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Enqueue condition used by styles().
	 *
	 * @param array $form Form object.
	 * @return bool
	 */
	public function is_editing_any_form( $form ) {
		return 'none' !== $this->style_mode() && is_array( $form ) && ! empty( $form['id'] ) && null !== Editor::context( $form['id'] );
	}

	// ---------------------------------------------------------------------------------------
	// Form settings tab.
	// ---------------------------------------------------------------------------------------

	/**
	 * Icon of the settings tab.
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return 'dashicons-list-view';
	}

	/**
	 * Settings tab of a form: the EntryStage workshop.
	 *
	 * @param array $form Form object.
	 */
	public function form_settings( $form ) {
		Atelier::render( $this, $form );
	}

	/**
	 * Title of the settings tab.
	 *
	 * @return string
	 */
	public function form_settings_page_title() {
		return 'EntryStage';
	}

	/**
	 * Settings are read fresh after a save.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Settings.
	 */
	public function save_form_settings( $form, $settings ) {
		$result = parent::save_form_settings( $form, $settings );
		Settings::flush();
		delete_transient( 'estg_form_page_' . (int) rgar( $form, 'id' ) );
		delete_transient( 'estg_list_page_' . (int) rgar( $form, 'id' ) );
		return $result;
	}

	// ---------------------------------------------------------------------------------------
	// Plugin settings: appearance and license.
	// ---------------------------------------------------------------------------------------

	/**
	 * Plugin settings page.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => __( 'Appearance', 'entrystage' ),
				'description' => '<p>' . esc_html__( 'These settings apply to every list of the site. The colours and the font are used by the styles only (not by Minimal and None); leave a colour empty to keep the one of the style. For finer changes, see "Look and feel" in the documentation (CSS variables and classes).', 'entrystage' ) . '</p>',
				'fields'      => array_merge(
					array(
						array(
							'name'          => 'style',
							'type'          => 'radio',
							'label'         => __( 'Style of the lists', 'entrystage' ),
							'default_value' => $this->style_mode(),
							'choices'       => $this->style_choices(),
						),
						array(
							'name'  => 'style_preview',
							'type'  => 'html',
							'label' => __( 'Preview of the styles', 'entrystage' ),
							'html'  => $this->style_previews(),
						),
					),
					$this->color_fields(),
					array(
						array(
							'name'          => 'font',
							'type'          => 'select',
							'label'         => __( 'Font of the texts', 'entrystage' ),
							'description'   => __( 'Fonts already installed on the computer of the visitor: nothing is loaded from another site.', 'entrystage' ),
							'default_value' => '',
							'choices'       => $this->font_choices(),
						),
					)
				),
			),
		);
	}

	/**
	 * Choices of the style setting: the styles, then Minimal and None.
	 *
	 * @return array
	 */
	private function style_choices() {
		$out = array();
		foreach ( self::skins() as $value => $label ) {
			$out[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		$out[] = array(
			'value' => 'minimal',
			'label' => __( 'Minimal: layout only (columns, cards on phones, widths); colours, buttons and fonts come from your theme', 'entrystage' ),
		);
		$out[] = array(
			'value' => 'none',
			'label' => __( 'None: no stylesheet; you style the list entirely from your theme', 'entrystage' ),
		);
		return $out;
	}

	/**
	 * Preview of each style under the style setting: the list, the board (EntryStage Pro) and a phone.
	 * A click on a preview picks the style (assets/js/colors.js).
	 *
	 * @return string Safe HTML.
	 */
	private function style_previews() {
		$names = array(
			'editorial' => __( 'Editorial template', 'entrystage' ),
		);
		$kinds = array( 'list' => __( 'List', 'entrystage' ) );
		$kinds['mobile'] = __( 'Phone', 'entrystage' );
		$current = $this->style_mode();
		// right after a save, the page is built before the new choice is stored: show the choice just sent
		$posted = sanitize_key( (string) rgpost( '_gform_setting_style' ) );
		if ( '' !== $posted && ( array_key_exists( $posted, self::skins() ) || in_array( $posted, array( 'minimal', 'none' ), true ) ) ) {
			$current = $posted;
		}
		$h       = '<style>.estg-previews{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px;max-width:1100px;width:0;min-width:100%}'
			. '.estg-preview{display:flex;flex-direction:column;gap:8px;padding:10px;border:1px solid #dcdcde;border-radius:10px;background:#fff;cursor:pointer;text-align:left;font:inherit}'
			. '.estg-preview:hover{border-color:#2271b1}.estg-preview.is-current{border-color:#2271b1;box-shadow:0 0 0 2px #2271b1}'
			. '.estg-preview-name{font-weight:600;font-size:14px}.estg-preview-imgs{display:grid;grid-template-columns:1fr 27%;gap:8px;align-items:start}'
			. '.estg-preview figure{margin:0}.estg-preview-mobile{grid-column:2;grid-row:1/span 2}'
			. '.estg-preview img{display:block;width:100%;height:auto;border:1px solid #e5e7eb;border-radius:6px}'
			. '.estg-preview figcaption{margin-top:3px;font-size:11px;color:#646970}</style>';
		$h      .= '<div class="estg-previews">';
		foreach ( $names as $style => $name ) {
			$h .= '<button type="button" class="estg-preview' . ( $style === $current ? ' is-current' : '' ) . '" data-estg-style="' . esc_attr( $style ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: name of a style */ __( 'Choose the style %s', 'entrystage' ), $name ) ) . '">';
			$h .= '<span class="estg-preview-name">' . esc_html( $name ) . '</span><span class="estg-preview-imgs">';
			foreach ( $kinds as $kind => $label ) {
				$h .= '<figure class="estg-preview-' . esc_attr( $kind ) . '"><img src="' . esc_url( ENTRYSTAGE_URL . 'assets/img/styles/' . $style . '-' . $kind . '.png' ) . '" alt="' . esc_attr( $name . ' : ' . $label ) . '" loading="lazy"><figcaption>' . esc_html( $label ) . '</figcaption></figure>';
			}
			$h .= '</span></button>';
		}
		return $h . '</div>';
	}

	/**
	 * Choices of the font setting.
	 *
	 * @return array
	 */
	private function font_choices() {
		$out = array();
		foreach ( self::fonts() as $value => $font ) {
			$out[] = array(
				'value' => $value,
				'label' => $font[0],
			);
		}
		return $out;
	}

	/**
	 * Colour pickers of the styles.
	 *
	 * @return array
	 */
	private function color_fields() {
		$out = array();
		foreach ( self::color_settings() as $name => $color ) {
			$out[] = array(
				'name'          => $name,
				'type'          => 'text',
				'label'         => $color[2],
				'description'   => $color[3],
				'class'         => 'small estg-color-field',
				'default_value' => '',
			);
		}
		return $out;
	}

	/**
	 * Cleans the settings before they are saved (and, in EntryStage Pro, activates the license key).
	 *
	 * @param array $settings Posted settings.
	 */
	public function update_plugin_settings( $settings ) {
		unset( $settings['style_preview'] );
		foreach ( self::color_settings() as $name => $color ) {
			$value             = sanitize_hex_color( trim( (string) rgar( $settings, $name ) ) );
			// a colour equal to the default of the first versions is the colour of the style
			$settings[ $name ] = $value && strtolower( $value ) !== $color[1] ? $value : '';
		}
		$style             = (string) rgar( $settings, 'style' );
		$settings['style'] = array_key_exists( $style, self::skins() ) || in_array( $style, array( 'minimal', 'none' ), true ) ? $style : 'editorial';
		$settings['load_css'] = 'none' === $settings['style'] ? '0' : '1';   // Kept for older code reading it.
		$settings['font']  = array_key_exists( (string) rgar( $settings, 'font' ), self::fonts() ) ? (string) rgar( $settings, 'font' ) : '';
		parent::update_plugin_settings( $settings );
	}

}
