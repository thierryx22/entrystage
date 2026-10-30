<?php
/**
 * "EntryStage list" block for the block editor: the same list as the shortcode, set from the block
 * sidebar, with a live preview drawn by the server.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Block {

	const NAME = 'entrystage/list';

	/**
	 * Registers the block (called on "init" by the add-on).
	 */
	public static function hooks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script(
			'estg-block',
			ENTRYSTAGE_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
			Addon::asset_version( 'assets/js/block.js' ),
			true
		);
		register_block_type(
			self::NAME,
			array(
				'api_version'     => 2,
				'title'           => __( 'EntryStage list', 'entrystage' ),
				'description'     => __( 'Entries of a Gravity Forms form, as set in the EntryStage tab of the form.', 'entrystage' ),
				'category'        => 'widgets',
				'icon'            => 'list-view',
				'keywords'        => array( 'gravity forms', 'entries', 'list' ),
				'editor_script'   => 'estg-block',
				'supports'        => array(
					'html'      => false,
					'className' => true,
					'align'     => array( 'wide', 'full' ),
				),
				'attributes'      => array(
					'form'    => array(
						'type'    => 'number',
						'default' => 0,
					),
					'user'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'perPage' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'page'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'forms'   => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'number' ),
						'default' => array(),
					),
					'multi'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'align'   => array(
						'type' => 'string',
					),
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'editor_style' ) );
	}

	/**
	 * Forms and texts used by the block sidebar.
	 */
	public static function editor_data() {
		$forms = array();
		foreach ( (array) \GFAPI::get_forms( true, false, 'title' ) as $form ) {
			$settings = Settings::get( $form );
			$forms[]  = array(
				'id'      => (int) $form['id'],
				'title'   => (string) $form['title'],
				'enabled' => ! empty( $settings['enabled'] ),
			);
		}
		wp_localize_script(
			'estg-block',
			'estgBlock',
			array(
				'forms' => $forms,
				'i18n'  => array(
					'title'       => __( 'EntryStage list', 'entrystage' ),
					'form'        => __( 'Form', 'entrystage' ),
					'choose'      => __( 'Choose a form', 'entrystage' ),
					'off'         => __( '(list not enabled)', 'entrystage' ),
					'offHelp'     => __( 'The list of this form is not enabled: tick "List enabled" in the form settings, EntryStage tab.', 'entrystage' ),
					'none'        => __( 'No Gravity Forms form yet.', 'entrystage' ),
					'entries'     => __( 'Entries shown', 'entrystage' ),
					'asSet'       => __( 'As set in the form (Access step)', 'entrystage' ),
					'current'     => __( 'Only the entries of the person logged in', 'entrystage' ),
					'oneUser'     => __( 'Only the entries of one user', 'entrystage' ),
					'userId'      => __( 'User ID', 'entrystage' ),
					'perPage'     => __( 'Rows per page', 'entrystage' ),
					'perPageHelp' => __( 'Empty: as set in the form.', 'entrystage' ),
					'page'        => __( 'Page of the form (for Edit and Duplicate)', 'entrystage' ),
					'pageHelp'    => __( 'Page number, path or address of a page showing the form. Empty: the page chosen in the form settings.', 'entrystage' ),
					'settings'    => __( 'List settings', 'entrystage' ),
					'more'        => __( 'Columns, buttons, look: form > Settings > EntryStage.', 'entrystage' ),
					'multi'       => __( 'Several forms, in tabs', 'entrystage' ),
					'multiHelp'   => __( 'A personal space: one tab per form, with its number of entries.', 'entrystage' ),
					'tabs'        => __( 'Forms shown in tabs', 'entrystage' ),
					'chooseMany'  => __( 'Tick the forms to show in tabs.', 'entrystage' ),
				),
			)
		);
	}

	/**
	 * List stylesheet and script inside the editor, so that the preview looks like the site
	 * (cards when the column is narrow).
	 */
	public static function editor_style() {
		if ( is_admin() ) {
			Addon::get_instance()->enqueue_front_assets();
		}
	}

	/**
	 * Front-end output: the shortcode with the attributes of the block.
	 *
	 * @param array $attrs Block attributes.
	 * @return string
	 */
	public static function render( $attrs ) {
		$atts = array( 'form' => absint( rgar( $attrs, 'form' ) ) );
		$forms = array_filter( array_map( 'absint', (array) rgar( $attrs, 'forms' ) ) );
		if ( $forms ) {
			$atts = array( 'forms' => implode( ',', $forms ) );
		}
		if ( empty( $atts['form'] ) && empty( $atts['forms'] ) ) {
			return '';
		}
		$user = (string) rgar( $attrs, 'user' );
		if ( 'current' === $user || ( '' !== $user && ctype_digit( $user ) ) ) {
			$atts['user'] = $user;
		}
		if ( absint( rgar( $attrs, 'perPage' ) ) ) {
			$atts['per_page'] = absint( $attrs['perPage'] );
		}
		if ( empty( $atts['forms'] ) && '' !== trim( (string) rgar( $attrs, 'page' ) ) ) {
			$atts['page'] = sanitize_text_field( (string) $attrs['page'] );
		}
		$html = Renderer::shortcode( $atts );
		if ( '' === $html ) {
			return '';
		}
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : 'class="wp-block-entrystage-list"';
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}
}
