<?php
/**
 * What each Gravity Forms field type can do in the list: whether it can be shown, its display
 * modes, whether it can be sorted or filtered, and how its value is drawn (text, sub-input,
 * file links, thumbnails, signature).
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fields {

	/** Field types that hold no value, or a value that must never be shown. */
	const EXCLUDED = array( 'html', 'section', 'page', 'captcha', 'password', 'creditcard' );

	/** Field types whose stored value cannot be used to sort the list. */
	const NOT_SORTABLE = array( 'fileupload', 'list', 'multiselect', 'checkbox', 'signature', 'consent', 'post_image', 'post_category', 'post_tags', 'post_custom_field' );

	/** Field types whose parts can be shown one at a time. */
	const WITH_PARTS = array( 'name', 'address' );

	/** Picture extensions shown as thumbnails. SVG is left out on purpose. */
	const IMAGE_EXT = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp' );

	/**
	 * The field can be offered in the list.
	 *
	 * @param \GF_Field $field Field.
	 * @return bool
	 */
	public static function is_listable( $field ) {
		return is_object( $field ) && ! in_array( $field->type, self::EXCLUDED, true ) && ! in_array( $field->get_input_type(), self::EXCLUDED, true );
	}

	/**
	 * Listable fields of a form, by id.
	 *
	 * @param array $form Form object.
	 * @return \GF_Field[]
	 */
	public static function listable( $form ) {
		$out = array();
		foreach ( (array) rgar( $form, 'fields' ) as $field ) {
			if ( self::is_listable( $field ) ) {
				$out[ (string) $field->id ] = $field;
			}
		}
		return $out;
	}

	/**
	 * Label of a field, without HTML.
	 *
	 * @param \GF_Field $field Field.
	 * @return string
	 */
	public static function label( $field ) {
		$label = trim( wp_strip_all_tags( (string) \GFCommon::get_label( $field ) ) );
		/* translators: %d: field id */
		return '' !== $label ? $label : sprintf( __( 'Field %d', 'entrystage' ), (int) $field->id );
	}

	/**
	 * Readable name of a field type.
	 *
	 * @param \GF_Field $field Field.
	 * @return string
	 */
	public static function type_label( $field ) {
		$title = method_exists( $field, 'get_form_editor_field_title' ) ? (string) $field->get_form_editor_field_title() : '';
		$title = '' !== $title ? $title : ucfirst( (string) $field->type );
		if ( ! empty( $field->enableCalculation ) || 'calculation' === $field->get_input_type() ) {
			/* translators: %s: field type */
			$title = sprintf( __( '%s (calculated)', 'entrystage' ), $title );
		}
		return wp_strip_all_tags( $title );
	}

	/**
	 * Value of a field as Gravity Forms shows it in the entry detail.
	 *
	 * Calls GF_Field::get_value_entry_detail(), as GFCommon::get_lead_field_display() does (deprecated in
	 * Gravity Forms 3.0). Gravity Forms 2.9.29 and later expect the entry as second parameter; earlier versions
	 * expect the currency.
	 *
	 * @param \GF_Field $field  Field.
	 * @param mixed     $value  Value.
	 * @param array     $entry  Entry.
	 * @param string    $format html | text.
	 * @return string
	 */
	public static function display( $field, $value, $entry, $format = 'html' ) {
		$third = class_exists( 'GFForms' ) && version_compare( (string) \GFForms::$version, '2.9.29', '>=' ) ? (array) $entry : rgar( $entry, 'currency' );
		if ( is_object( $field ) && method_exists( $field, 'get_value_entry_detail' ) ) {
			return (string) $field->get_value_entry_detail( $value, $third, true, $format, 'screen' );
		}
		return (string) \GFCommon::get_lead_field_display( $field, $value, $third, true, $format, 'screen' );
	}

	/**
	 * Display modes of a field: value => label. The first one is the default.
	 *
	 * @param \GF_Field $field Field.
	 * @return array
	 */
	public static function modes( $field ) {
		$type  = $field->get_input_type();
		$modes = array();
		if ( 'fileupload' === $type || 'post_image' === $type ) {
			$modes['name']   = __( 'File name (link)', 'entrystage' );
			$modes['thumb']  = __( 'Thumbnail (pictures)', 'entrystage' );
			$modes['button'] = __( 'Download button', 'entrystage' );
		} elseif ( 'signature' === $type ) {
			$modes['thumb'] = __( 'Thumbnail', 'entrystage' );
		}
		// names that say what is shown (choice label or stored code, whole field or one part).
		$has_choices = is_array( rgobj( $field, 'choices' ) ) && ! empty( $field->choices ) && ! in_array( $type, array( 'product', 'option', 'shipping' ), true );
		if ( $has_choices ) {
			$modes['default'] = __( 'Label of the choice', 'entrystage' );
		} elseif ( in_array( $field->type, self::WITH_PARTS, true ) ) {
			$modes['default'] = __( 'Whole field', 'entrystage' );
		} else {
			$modes['default'] = __( 'Value as entered', 'entrystage' );
		}
		if ( in_array( $field->type, self::WITH_PARTS, true ) && is_array( rgobj( $field, 'inputs' ) ) ) {
			foreach ( $field->inputs as $input ) {
				if ( ! empty( $input['isHidden'] ) ) {
					continue;
				}
				$label = trim( (string) ( ! empty( $input['customLabel'] ) ? $input['customLabel'] : rgar( $input, 'label' ) ) );
				/* translators: %s: part of a field, for example "Last" or "City" */
				$modes[ 'input:' . $input['id'] ] = sprintf( __( 'Only: %s', 'entrystage' ), '' !== $label ? $label : $input['id'] );
			}
		}
		if ( is_array( rgobj( $field, 'choices' ) ) && ! empty( $field->choices ) && ! in_array( $type, array( 'product', 'option', 'shipping' ), true ) ) {
			$modes['raw'] = __( 'Stored value of the choice', 'entrystage' );
		}
		if ( in_array( $type, array( 'textarea', 'post_content', 'post_excerpt' ), true ) ) {
			$modes['excerpt'] = __( 'Beginning of the text', 'entrystage' );
		}
		/**
		 * Filters the display modes offered for a field.
		 *
		 * @param array     $modes value => label.
		 * @param \GF_Field $field Field.
		 */
		return (array) apply_filters( 'estg_field_modes', $modes, $field );
	}

	/**
	 * Default display mode of a field.
	 *
	 * @param \GF_Field $field Field.
	 * @return string
	 */
	public static function default_mode( $field ) {
		$modes = array_keys( self::modes( $field ) );
		return $modes ? (string) $modes[0] : 'default';
	}

	/**
	 * Sort key for a field shown in a given mode, or '' when the list cannot be sorted on it.
	 *
	 * @param \GF_Field $field Field.
	 * @param string    $mode  Display mode.
	 * @return string
	 */
	public static function sort_key( $field, $mode ) {
		if ( 0 === strpos( (string) $mode, 'input:' ) ) {
			return Settings::clean_key( substr( $mode, 6 ) );
		}
		if ( in_array( $field->get_input_type(), self::NOT_SORTABLE, true ) || in_array( $field->type, self::NOT_SORTABLE, true ) ) {
			return '';
		}
		$inputs = $field->get_entry_inputs();
		return is_array( $inputs ) ? '' : (string) $field->id;
	}

	/**
	 * The field can get a drop-down filter (one choice per entry).
	 *
	 * @param \GF_Field $field Field.
	 * @return bool
	 */
	public static function is_filterable( $field ) {
		return in_array( $field->get_input_type(), array( 'select', 'radio' ), true ) && is_array( rgobj( $field, 'choices' ) ) && ! empty( $field->choices );
	}

	/**
	 * Why a column of this field cannot be sorted or filtered (shown in the settings).
	 *
	 * @param \GF_Field $field Field.
	 * @return array array( 'sort' => string, 'filter' => string ), '' when allowed.
	 */
	public static function limits( $field ) {
		$type = $field->get_input_type();
		$sort = '';
		if ( in_array( $type, array( 'fileupload', 'post_image', 'signature' ), true ) ) {
			$sort = __( 'Files cannot be sorted.', 'entrystage' );
		} elseif ( in_array( $type, self::NOT_SORTABLE, true ) ) {
			$sort = __( 'Several values per entry: no sorting.', 'entrystage' );
		} elseif ( is_array( $field->get_entry_inputs() ) ) {
			$sort = in_array( $field->type, self::WITH_PARTS, true ) ? __( 'Choose one part (for example the last name) to sort.', 'entrystage' ) : __( 'Several values per entry: no sorting.', 'entrystage' );
		}
		return array(
			'sort'   => $sort,
			'filter' => self::is_filterable( $field ) ? '' : __( 'Filters exist for drop-downs and radio buttons only.', 'entrystage' ),
		);
	}

	/**
	 * Warning shown in the settings for fields that are normally kept from visitors.
	 *
	 * @param \GF_Field $field Field.
	 * @return string
	 */
	public static function warning( $field ) {
		if ( ! empty( $field->adminOnly ) || 'administrative' === rgobj( $field, 'visibility' ) ) {
			return __( 'Administrative field: everyone who sees the list will see it.', 'entrystage' );
		}
		if ( 'hidden' === rgobj( $field, 'visibility' ) ) {
			return __( 'Hidden field: everyone who sees the list will see it.', 'entrystage' );
		}
		return '';
	}

	/**
	 * Files of an upload field: array( url, download, name, image ).
	 *
	 * @param \GF_Field $field Field.
	 * @param array     $entry Entry.
	 * @param bool      $force Download link instead of display link.
	 * @return array
	 */
	public static function files( $field, $entry, $force = false ) {
		$raw  = (string) rgar( $entry, (string) $field->id );
		$list = array();
		if ( '' === trim( $raw ) ) {
			return $list;
		}
		if ( 'post_image' === $field->get_input_type() ) {
			$parts = explode( '|:|', $raw );
			$urls  = array( $parts[0] );
		} elseif ( ! empty( $field->multipleFiles ) || '[' === substr( ltrim( $raw ), 0, 1 ) ) {
			$urls = json_decode( $raw, true );
			$urls = is_array( $urls ) ? $urls : array();
		} else {
			$urls = array( $raw );
		}
		foreach ( $urls as $url ) {
			$url = is_string( $url ) ? trim( $url ) : '';
			if ( '' === $url ) {
				continue;
			}
			$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
			$name     = rawurldecode( wp_basename( $path ) );
			$ext      = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			$download = $url;
			if ( method_exists( $field, 'get_download_url' ) ) {
				$download = (string) $field->get_download_url( $url, $force );
			}
			$list[] = array(
				'url'      => $url,
				'download' => $download,
				'name'     => '' !== $name ? $name : __( 'File', 'entrystage' ),
				'image'    => in_array( $ext, self::IMAGE_EXT, true ),
			);
		}
		return $list;
	}

	/**
	 * Draws the value of a field for the list, the detail panel or the entry view.
	 *
	 * @param array     $form     Form object.
	 * @param array     $entry    Entry.
	 * @param \GF_Field $field    Field.
	 * @param string    $mode     Display mode.
	 * @param array     $settings Form settings.
	 * @return string Safe HTML.
	 */
	public static function render( $form, $entry, $field, $mode, array $settings ) {
		$modes = self::modes( $field );
		$mode  = isset( $modes[ $mode ] ) ? $mode : self::default_mode( $field );
		$size  = 96 === (int) rgar( $settings, 'thumb_size' ) ? 96 : 48;
		$type  = $field->get_input_type();

		if ( 0 === strpos( $mode, 'input:' ) ) {
			$html = esc_html( (string) rgar( $entry, substr( $mode, 6 ) ) );
		} elseif ( 'raw' === $mode ) {
			$html = esc_html( self::raw_text( $field, $entry ) );
		} elseif ( 'excerpt' === $mode ) {
			$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) rgar( $entry, (string) $field->id ) ) ) );
			$html = esc_html( wp_html_excerpt( $text, 120, '…' ) );
		} elseif ( in_array( $type, array( 'fileupload', 'post_image' ), true ) && 'default' !== $mode ) {
			$html = self::files_html( $field, $entry, $mode, $size );
		} elseif ( 'signature' === $type && 'thumb' === $mode ) {
			$html = self::signature_html( $field, $entry, $size );
		} else {
			$value = \GFFormsModel::get_lead_field_value( $entry, $field );
			$html  = (string) Fields::display( $field, $value, $entry, 'html' );
		}
		$column = array(
			'id'    => (string) $field->id,
			'label' => self::label( $field ),
			'field' => $field,
			'mode'  => $mode,
		);
		/**
		 * Filters the HTML of a value in the list (kept from the first builds; "mode" was added later).
		 *
		 * @param string $html   HTML.
		 * @param array  $column Column: id, label, field, mode.
		 * @param array  $entry  Entry.
		 * @param array  $form   Form object.
		 */
		$html = (string) apply_filters( 'estg_cell_value', $html, $column, $entry, $form );
		return wp_kses_post( $html );
	}

	/**
	 * Plain text of a value (CSV export).
	 *
	 * @param \GF_Field $field Field.
	 * @param array     $entry Entry.
	 * @param string    $mode  Display mode.
	 * @return string
	 */
	public static function text( $field, $entry, $mode ) {
		if ( 0 === strpos( (string) $mode, 'input:' ) ) {
			return (string) rgar( $entry, substr( $mode, 6 ) );
		}
		if ( 'raw' === $mode ) {
			return self::raw_text( $field, $entry );
		}
		if ( in_array( $field->get_input_type(), array( 'fileupload', 'post_image' ), true ) ) {
			return implode( "\n", wp_list_pluck( self::files( $field, $entry ), 'download' ) );
		}
		$value = \GFFormsModel::get_lead_field_value( $entry, $field );
		$text  = Fields::display( $field, $value, $entry, 'text' );
		$text  = is_array( $text ) ? implode( ', ', $text ) : (string) $text;
		$text  = preg_replace( '#<br\s*/?>|</li>|</p>#i', "\n", $text );
		return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Stored value(s) of a choice field, as text.
	 *
	 * @param \GF_Field $field Field.
	 * @param array     $entry Entry.
	 * @return string
	 */
	private static function raw_text( $field, $entry ) {
		$inputs = $field->get_entry_inputs();
		if ( is_array( $inputs ) ) {
			$values = array();
			foreach ( $inputs as $input ) {
				$v = (string) rgar( $entry, (string) $input['id'] );
				if ( '' !== $v ) {
					$values[] = $v;
				}
			}
			return implode( ', ', $values );
		}
		$value = (string) rgar( $entry, (string) $field->id );
		if ( 'multiselect' === $field->get_input_type() ) {
			$list = json_decode( $value, true );
			$list = is_array( $list ) ? $list : explode( ',', $value );
			return implode( ', ', array_map( 'trim', $list ) );
		}
		return $value;
	}

	/**
	 * Links, thumbnails or buttons of the files of an entry.
	 *
	 * @param \GF_Field $field Field.
	 * @param array     $entry Entry.
	 * @param string    $mode  name | thumb | button.
	 * @param int       $size  Thumbnail size in pixels.
	 * @return string
	 */
	private static function files_html( $field, $entry, $mode, $size ) {
		$files = self::files( $field, $entry, 'button' === $mode );
		if ( empty( $files ) ) {
			return '';
		}
		$out   = array();
		$shown = 0;
		foreach ( $files as $file ) {
			/* translators: %s: file name */
			$open = sprintf( __( 'Open %s', 'entrystage' ), $file['name'] );
			if ( 'thumb' === $mode && $file['image'] ) {
				if ( $shown >= 3 ) {
					continue;
				}
				++$shown;
				$out[] = '<a class="estg-thumb" href="' . esc_url( $file['download'] ) . '" target="_blank" rel="noopener" title="' . esc_attr( $file['name'] ) . '">'
					. '<img src="' . esc_url( $file['download'] ) . '" alt="' . esc_attr( $open ) . '" width="' . (int) $size . '" height="' . (int) $size . '" loading="lazy" decoding="async"></a>';
			} elseif ( 'button' === $mode ) {
				$label = 1 === count( $files ) ? __( 'Download', 'entrystage' ) : $file['name'];
				$out[] = '<a class="estg-btn estg-btn-ghost estg-btn-small estg-download" href="' . esc_url( $file['download'] ) . '" title="' . esc_attr( $file['name'] ) . '">' . Renderer::icon( 'download' ) . ' <span>' . esc_html( $label ) . '</span></a>';
			} else {
				$out[] = '<a class="estg-file" href="' . esc_url( $file['download'] ) . '" target="_blank" rel="noopener">' . Renderer::icon( 'file' ) . ' <span>' . esc_html( $file['name'] ) . '</span></a>';
			}
		}
		$images = count( wp_list_filter( $files, array( 'image' => true ) ) );
		if ( 'thumb' === $mode && $images > $shown ) {
			/* translators: %d: number of other pictures */
			$out[] = '<span class="estg-more-files">' . esc_html( sprintf( __( '+%d', 'entrystage' ), $images - $shown ) ) . '</span>';
		}
		return '<span class="estg-files estg-files-' . esc_attr( $mode ) . '">' . implode( ' ', $out ) . '</span>';
	}

	/**
	 * Signature (Gravity Forms Signature add-on) as a small picture.
	 *
	 * @param \GF_Field $field Field.
	 * @param array     $entry Entry.
	 * @param int       $size  Height in pixels.
	 * @return string
	 */
	private static function signature_html( $field, $entry, $size ) {
		$file = (string) rgar( $entry, (string) $field->id );
		if ( '' === $file ) {
			return '';
		}
		$url = '';
		if ( function_exists( 'gf_signature' ) && is_object( gf_signature() ) && method_exists( gf_signature(), 'get_signature_url' ) ) {
			$url = (string) gf_signature()->get_signature_url( $file );
		}
		if ( '' === $url ) {
			$value = \GFFormsModel::get_lead_field_value( $entry, $field );
			return (string) Fields::display( $field, $value, $entry, 'html' );
		}
		return '<img class="estg-signature" src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'Signature', 'entrystage' ) . '" height="' . (int) $size . '" loading="lazy" decoding="async">';
	}

	/**
	 * Data of a field for the settings page.
	 *
	 * @param \GF_Field $field Field.
	 * @return array
	 */
	public static function describe( $field ) {
		$modes  = array();
		foreach ( self::modes( $field ) as $value => $label ) {
			$modes[] = array(
				'value'    => (string) $value,
				'label'    => $label,
				'sortable' => '' !== self::sort_key( $field, $value ),
			);
		}
		$limits = self::limits( $field );
		// an example taken from the first choice, to show the difference between label and stored value.
		$example = array();
		if ( is_array( rgobj( $field, 'choices' ) ) && ! empty( $field->choices ) ) {
			$first = reset( $field->choices );
			$text  = wp_strip_all_tags( (string) rgar( $first, 'text' ) );
			$value = (string) rgar( $first, 'value' );
			if ( '' !== $text && '' !== $value ) {
				$example = array(
					'default' => $text,
					'raw'     => $value,
				);
			}
		}
		return array(
			'example'    => $example,
			'key'        => (string) $field->id,
			'label'      => self::label( $field ),
			'type'       => self::type_label( $field ),
			'modes'      => $modes,
			'filterable' => self::is_filterable( $field ),
			'sortNote'   => $limits['sort'],
			'filterNote' => $limits['filter'],
			'warning'    => self::warning( $field ),
		);
	}
}
