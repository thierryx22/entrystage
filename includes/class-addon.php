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
	 * style of the lists: full (the plugin look), minimal (layout only) or none.
	 * Sites that had unticked "Use the plugin stylesheet" get "none".
	 *
	 * @return string full | minimal | none.
	 */
	public function style_mode() {
		$mode = (string) $this->get_plugin_setting( 'style' );
		if ( in_array( $mode, array( 'full', 'minimal', 'none' ), true ) ) {
			return $mode;
		}
		return '0' === (string) $this->get_plugin_setting( 'load_css' ) ? 'none' : 'full';
	}

	/**
	 * Colour settings of the full style: setting => CSS variable.
	 *
	 * @return array
	 */
	public static function color_settings() {
		return array(
			'accent'      => array( '--estg-accent', '#2563eb', __( 'Main colour', 'entrystage' ), __( 'Buttons, links, icons, current page.', 'entrystage' ) ),
			'color_border' => array( '--estg-border', '#e5e7eb', __( 'Lines', 'entrystage' ), __( 'Borders of the table, of the buttons and of the fields.', 'entrystage' ) ),
			'color_alt'   => array( '--estg-bg-alt', '#f9fafb', __( 'Background of every other row', 'entrystage' ), __( 'Also the background of the search box.', 'entrystage' ) ),
			'color_muted' => array( '--estg-muted', '#6b7280', __( 'Secondary text', 'entrystage' ), __( 'Number of entries, labels of the fields.', 'entrystage' ) ),
			'color_danger' => array( '--estg-danger', '#b91c1c', __( 'Archive and errors', 'entrystage' ), __( 'Archive button on hover, error messages.', 'entrystage' ) ),
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
		$css = '';
		foreach ( self::color_settings() as $name => $color ) {
			$value = sanitize_hex_color( (string) $this->get_plugin_setting( $name ) );
			if ( $value && strtolower( $value ) !== $color[1] ) {
				$css .= $color[0] . ':' . $value . ';';
			}
		}
		$radius = (string) $this->get_plugin_setting( 'radius' );
		if ( '' !== $radius && is_numeric( $radius ) ) {
			$css .= '--estg-radius:' . max( 0, min( 24, (int) $radius ) ) . 'px;';
		}
		if ( '' !== $css ) {
			wp_add_inline_style( 'estg-front', '.estg,.estg-space{' . $css . '}' );
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
				'description' => '<p>' . esc_html__( 'These settings apply to every list of the site. The colours and the rounding are only used by the EntryStage style. For finer changes, see "Look and feel" in the documentation (CSS variables and classes).', 'entrystage' ) . '</p>',
				'fields'      => array_merge(
					array(
						array(
							'name'          => 'style',
							'type'          => 'radio',
							'label'         => __( 'Style of the lists', 'entrystage' ),
							'default_value' => $this->style_mode(),
							'choices'       => array(
								array(
									'value' => 'full',
									'label' => __( 'EntryStage: the look of the plugin, with the colours below', 'entrystage' ),
								),
								array(
									'value' => 'minimal',
									'label' => __( 'Minimal: layout only (columns, cards on phones, widths); colours, buttons and fonts come from your theme', 'entrystage' ),
								),
								array(
									'value' => 'none',
									'label' => __( 'None: no stylesheet; you style the list entirely from your theme', 'entrystage' ),
								),
							),
						),
					),
					$this->color_fields(),
					array(
						array(
							'name'        => 'radius',
							'type'        => 'text',
							'input_type'  => 'number',
							'label'       => __( 'Corner rounding (pixels, 0 to 24)', 'entrystage' ),
							'class'       => 'small',
							'placeholder' => '6',
						),
					)
				),
			),
		);
	}

	/**
	 * Colour pickers of the full style.
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
				'default_value' => $color[1],
				'placeholder'   => $color[1],
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
		foreach ( array_keys( self::color_settings() ) as $name ) {
			$value             = sanitize_hex_color( trim( (string) rgar( $settings, $name ) ) );
			$settings[ $name ] = $value ? $value : '';
		}
		$settings['style'] = in_array( rgar( $settings, 'style' ), array( 'full', 'minimal', 'none' ), true ) ? $settings['style'] : 'full';
		$settings['load_css'] = 'none' === $settings['style'] ? '0' : '1';   // Kept for older code reading it.
		$radius             = trim( (string) rgar( $settings, 'radius' ) );
		$settings['radius'] = is_numeric( $radius ) ? (string) max( 0, min( 24, (int) $radius ) ) : '';
		parent::update_plugin_settings( $settings );
	}

}
