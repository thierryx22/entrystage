<?php
/**
 * Opens an entry in its form for editing (or as the starting point of a copy) and saves the
 * changes back into the same entry.
 *
 * How it works:
 *  - the list links to the form page with estg_action, estg_entry and a nonce;
 *  - the form is filled with the entry values through the native Gravity Forms dynamic
 *    population ("gform_field_value");
 *  - the same arguments travel with the form as hidden inputs ("gform_form_tag");
 *  - on submit the request is checked again ("gform_validation") and Gravity Forms is told to
 *    update the existing entry instead of creating a new one ("gform_entry_id_pre_save_lead");
 *  - files that were not replaced and administrative fields keep their value
 *    ("gform_save_field_value").
 *
 * A copy ("duplicate") only fills the form: the submission creates a new entry.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editor {

	/** @var array Context per form id: array( 'action', 'entry', 'return' ) or false. */
	private static $context = array();

	/** @var array Field values to give to Gravity Forms per form: name => value. */
	private static $values = array();

	/** @var array Original entry of an edit in progress, per form id. */
	private static $original = array();

	/**
	 * Registers the Gravity Forms hooks.
	 */
	public static function hooks() {
		add_filter( 'gform_pre_render', array( __CLASS__, 'pre_render' ), 20, 3 );
		add_filter( 'gform_field_value', array( __CLASS__, 'field_value' ), 20, 3 );
		add_filter( 'gform_form_tag', array( __CLASS__, 'form_tag' ), 20, 2 );
		add_filter( 'gform_pre_validation', array( __CLASS__, 'pre_validation' ), 20 );
		add_filter( 'gform_validation', array( __CLASS__, 'validation' ), 5 );
		add_filter( 'gform_entry_id_pre_save_lead', array( __CLASS__, 'entry_id' ), 20, 2 );
		add_filter( 'gform_save_field_value', array( __CLASS__, 'keep_value' ), 20, 5 );
		add_filter( 'gform_disable_notification', array( __CLASS__, 'disable_notification' ), 20, 4 );
		add_filter( 'gform_confirmation', array( __CLASS__, 'confirmation' ), 20, 4 );
		add_action( 'gform_after_submission', array( __CLASS__, 'after_submission' ), 20, 2 );
	}

	/**
	 * Edit/duplicate context of a form for the current request, or null.
	 *
	 * Read from the posted hidden inputs on submission, otherwise from the link.
	 *
	 * @param int $form_id Form id.
	 * @return array|null
	 */
	public static function context( $form_id ) {
		$form_id = (int) $form_id;
		if ( isset( self::$context[ $form_id ] ) ) {
			return self::$context[ $form_id ] ? self::$context[ $form_id ] : null;
		}
		self::$context[ $form_id ] = false;

		// phpcs:disable WordPress.Security.NonceVerification -- the nonce is the "estg_nonce" value checked below.
		$source = isset( $_POST['estg_action'] ) ? $_POST : $_GET;
		if ( ! isset( $source['estg_action'], $source['estg_entry'], $source['estg_nonce'] ) ) {
			return null;
		}
		$action   = sanitize_key( wp_unslash( $source['estg_action'] ) );
		$entry_id = absint( wp_unslash( $source['estg_entry'] ) );
		$nonce    = sanitize_text_field( wp_unslash( $source['estg_nonce'] ) );
		$return   = isset( $source['estg_return'] ) ? (string) wp_unslash( $source['estg_return'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by safe_return().
		// phpcs:enable

		if ( ! in_array( $action, array( 'edit', 'duplicate' ), true ) || ! $entry_id ) {
			return null;
		}
		if ( ! wp_verify_nonce( $nonce, Urls::nonce_action( $action, $entry_id ) ) ) {
			return null;
		}
		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || (int) $entry['form_id'] !== $form_id ) {
			return null;
		}
		$form = \GFAPI::get_form( $form_id );
		if ( ! $form || ! Access::can( $action, $entry, Settings::get( $form ) ) ) {
			return null;
		}
		self::$context[ $form_id ] = array(
			'action' => $action,
			'entry'  => $entry,
			'nonce'  => $nonce,
			'return' => Urls::safe_return( $return, '' ),
		);
		return self::$context[ $form_id ];
	}

	/**
	 * The current request carries edit arguments for this form (valid or not).
	 *
	 * @param int $form_id Form id.
	 * @return bool
	 */
	private static function is_attempt( $form_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only.
		return isset( $_POST['estg_action'], $_POST['estg_entry'] ) && isset( $_POST['gform_submit'] ) && absint( $_POST['gform_submit'] ) === (int) $form_id;
	}

	/**
	 * Fills the form with the entry values.
	 *
	 * @param array $form         Form object.
	 * @param bool  $ajax         Ajax form.
	 * @param array $field_values Field values passed to gravity_form().
	 * @return array
	 */
	public static function pre_render( $form, $ajax = false, $field_values = array() ) {
		if ( ! is_array( $form ) || empty( $form['id'] ) ) {
			return $form;
		}
		$context = self::context( $form['id'] );
		if ( ! $context ) {
			return $form;
		}
		$entry  = $context['entry'];
		$values = array();
		foreach ( $form['fields'] as $field ) {
			if ( in_array( $field->type, array( 'html', 'section', 'page', 'captcha', 'password', 'fileupload', 'signature' ), true ) ) {
				if ( 'edit' === $context['action'] && in_array( $field->type, array( 'fileupload', 'signature' ), true ) ) {
					self::describe_current_file( $field, $entry );
				}
				continue;
			}
			$inputs = $field->get_entry_inputs();
			if ( is_array( $inputs ) && ! in_array( $field->type, array( 'checkbox' ), true ) && 'checkbox' !== $field->get_input_type() ) {
				// Multi-input fields (name, address, product...): one value per input.
				foreach ( $field->inputs as $i => $input ) {
					$value = rgar( $entry, (string) $input['id'] );
					if ( '' === (string) $value ) {
						continue;
					}
					$name = self::input_name( $field, $i );
					$values[ $name ] = $value;
				}
				$field->allowsPrepopulate = true;
				continue;
			}
			$value = self::single_value( $field, $entry );
			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}
			$name                     = self::field_name( $field );
			$values[ $name ]          = $value;
			$field->allowsPrepopulate = true;
		}
		/**
		 * Filters the values used to fill the form (name => value).
		 *
		 * @param array  $values  Values.
		 * @param array  $entry   Entry.
		 * @param array  $form    Form object.
		 * @param string $action  edit | duplicate.
		 */
		self::$values[ (int) $form['id'] ] = (array) apply_filters( 'estg_prefill_values', $values, $entry, $form, $context['action'] );
		return $form;
	}

	/**
	 * Value of a single-input field as Gravity Forms expects it for dynamic population.
	 *
	 * @param \GF_Field $field Field.
	 * @param array     $entry Entry.
	 * @return mixed
	 */
	private static function single_value( $field, $entry ) {
		$type = $field->get_input_type();
		if ( 'checkbox' === $type ) {
			$selected = array();
			foreach ( (array) $field->inputs as $input ) {
				$v = rgar( $entry, (string) $input['id'] );
				if ( '' !== (string) $v ) {
					$selected[] = $v;
				}
			}
			return $selected;
		}
		$value = rgar( $entry, (string) $field->id );
		if ( 'multiselect' === $type && method_exists( $field, 'to_array' ) ) {
			return $field->to_array( $value );
		}
		if ( 'list' === $type ) {
			$rows = maybe_unserialize( $value );
			return is_array( $rows ) ? $rows : '';
		}
		if ( 'date' === $type && 'datepicker' === rgobj( $field, 'dateType' ) && '' !== (string) $value ) {
			$display = \GFCommon::date_display( $value, 'ymd_dash', rgobj( $field, 'dateFormat' ) ? rgobj( $field, 'dateFormat' ) : 'mdy' );
			return $display ? $display : $value;
		}
		if ( in_array( $type, array( 'select', 'radio' ), true ) && ! empty( $field->enablePrice ) && false !== strpos( (string) $value, '|' ) ) {
			// Product options are stored as "value|price".
			$value = substr( $value, 0, strpos( $value, '|' ) );
		}
		return $value;
	}

	/**
	 * Population name of a field (keeps the one set in the form if any).
	 *
	 * @param \GF_Field $field Field.
	 * @return string
	 */
	private static function field_name( $field ) {
		if ( '' === trim( (string) rgobj( $field, 'inputName' ) ) ) {
			$field->inputName = 'estg_' . $field->id;
		}
		return $field->inputName;
	}

	/**
	 * Population name of one input of a field.
	 *
	 * @param \GF_Field $field Field.
	 * @param int       $i     Input index.
	 * @return string
	 */
	private static function input_name( $field, $i ) {
		$inputs = $field->inputs;
		if ( '' === trim( (string) rgar( $inputs[ $i ], 'name' ) ) ) {
			$inputs[ $i ]['name'] = 'estg_' . str_replace( '.', '_', (string) $inputs[ $i ]['id'] );
			$field->inputs        = $inputs;
		}
		return $field->inputs[ $i ]['name'];
	}

	/**
	 * Shows the files already attached under an upload field.
	 *
	 * @param \GF_Field $field Field.
	 * @param array     $entry Entry.
	 */
	private static function describe_current_file( $field, $entry ) {
		$value = rgar( $entry, (string) $field->id );
		if ( '' === (string) $value ) {
			return;
		}
		$files = json_decode( $value, true );
		$files = is_array( $files ) ? $files : array( $value );
		$names = array();
		foreach ( $files as $url ) {
			$names[] = esc_html( wp_basename( (string) strtok( (string) $url, '?' ) ) );
		}
		$note               = '<span class="estg-current-file">' . esc_html__( 'Current file (kept if you add nothing):', 'entrystage' ) . ' ' . implode( ', ', $names ) . '</span>';
		$field->description = trim( rgobj( $field, 'description' ) . ' ' . $note );
	}

	/**
	 * Gives Gravity Forms the entry values.
	 *
	 * @param mixed     $value Current value.
	 * @param \GF_Field $field Field.
	 * @param string    $name  Population name.
	 * @return mixed
	 */
	public static function field_value( $value, $field, $name ) {
		if ( ! is_object( $field ) || empty( $field->formId ) ) {
			return $value;
		}
		$form_id = (int) $field->formId;
		if ( isset( self::$values[ $form_id ] ) && array_key_exists( (string) $name, self::$values[ $form_id ] ) ) {
			return self::$values[ $form_id ][ (string) $name ];
		}
		return $value;
	}

	/**
	 * Adds the hidden inputs and a banner to the form.
	 *
	 * @param string $tag  Form tag.
	 * @param array  $form Form object.
	 * @return string
	 */
	public static function form_tag( $tag, $form ) {
		$context = self::context( rgar( $form, 'id' ) );
		if ( ! $context ) {
			return $tag;
		}
		$entry_id = (int) $context['entry']['id'];
		$tag     .= '<input type="hidden" name="estg_action" value="' . esc_attr( $context['action'] ) . '">';
		$tag     .= '<input type="hidden" name="estg_entry" value="' . esc_attr( $entry_id ) . '">';
		$tag     .= '<input type="hidden" name="estg_nonce" value="' . esc_attr( $context['nonce'] ) . '">';
		$tag     .= '<input type="hidden" name="estg_return" value="' . esc_attr( $context['return'] ) . '">';

		$text = 'edit' === $context['action']
			/* translators: %d: entry id */
			? sprintf( __( 'You are editing entry #%d.', 'entrystage' ), $entry_id )
			/* translators: %d: entry id */
			: sprintf( __( 'New entry pre-filled from entry #%d.', 'entrystage' ), $entry_id );
		$banner = '<div class="estg-editing" role="status">' . esc_html( $text );
		if ( '' !== $context['return'] ) {
			$banner .= ' <a href="' . esc_url( $context['return'] ) . '">' . esc_html__( 'Cancel', 'entrystage' ) . '</a>';
		}
		$banner .= '</div>';
		/**
		 * Filters the banner shown above a form opened from the list.
		 *
		 * @param string $banner  HTML.
		 * @param array  $context Context.
		 * @param array  $form    Form object.
		 */
		return $tag . apply_filters( 'estg_editing_banner', $banner, $context, $form );
	}

	/**
	 * While editing, the "no duplicates" rule must ignore the entry being edited.
	 *
	 * @param array $form Form object.
	 * @return array
	 */
	public static function pre_validation( $form ) {
		$context = is_array( $form ) ? self::context( rgar( $form, 'id' ) ) : null;
		if ( ! $context || 'edit' !== $context['action'] ) {
			return $form;
		}
		foreach ( $form['fields'] as $field ) {
			if ( ! empty( $field->noDuplicates ) ) {
				$field->noDuplicates  = false;
				$field->estgUniqueCheck = true;
			}
		}
		return $form;
	}

	/**
	 * Refuses a tampered or expired edit, and checks unique values against the other entries.
	 *
	 * @param array $result Validation result.
	 * @return array
	 */
	public static function validation( $result ) {
		$form = $result['form'];
		if ( ! self::is_attempt( rgar( $form, 'id' ) ) ) {
			return $result;
		}
		$context = self::context( $form['id'] );
		if ( ! $context ) {
			$result['is_valid'] = false;
			add_filter(
				'gform_validation_message_' . (int) $form['id'],
				function () {
					return '<div class="gform_validation_errors estg-error"><h2 class="gform_submission_error">' . esc_html__( 'This link has expired or you are not allowed to edit this entry. Please go back to the list and try again.', 'entrystage' ) . '</h2></div>';
				}
			);
			return $result;
		}
		if ( 'edit' !== $context['action'] ) {
			return $result;
		}
		foreach ( $form['fields'] as $field ) {
			if ( empty( $field->estgUniqueCheck ) || ! empty( $field->failed_validation ) ) {
				continue;
			}
			$value = rgpost( 'input_' . $field->id );
			if ( is_array( $value ) || '' === trim( (string) $value ) ) {
				continue;
			}
			$others = \GFAPI::count_entries(
				(int) $form['id'],
				array(
					'status'        => 'active',
					'field_filters' => array(
						array(
							'key'   => (string) $field->id,
							'value' => trim( (string) $value ),
						),
						array(
							'key'      => 'id',
							'operator' => '<>',
							'value'    => (int) $context['entry']['id'],
						),
					),
				)
			);
			if ( $others > 0 ) {
				$result['is_valid']         = false;
				$field->failed_validation  = true;
				$field->validation_message = '' !== (string) rgobj( $field, 'errorMessage' ) ? rgobj( $field, 'errorMessage' ) : __( 'This value is already used by another entry.', 'entrystage' );
			}
		}
		$result['form'] = $form;
		return $result;
	}

	/**
	 * Saves into the edited entry instead of creating a new one.
	 *
	 * @param int|null $entry_id Entry id (null for a new entry).
	 * @param array    $form     Form object.
	 * @return int|null
	 */
	public static function entry_id( $entry_id, $form ) {
		$context = self::is_attempt( rgar( $form, 'id' ) ) ? self::context( $form['id'] ) : null;
		if ( ! $context || 'edit' !== $context['action'] ) {
			return $entry_id;
		}
		self::$original[ (int) $form['id'] ] = $context['entry'];
		return (int) $context['entry']['id'];
	}

	/**
	 * Keeps the value of files that were not replaced and of administrative fields.
	 *
	 * @param mixed     $value    Value to save.
	 * @param array     $entry    Entry being saved.
	 * @param \GF_Field $field    Field.
	 * @param array     $form     Form object.
	 * @param string    $input_id Input id.
	 * @return mixed
	 */
	public static function keep_value( $value, $entry, $field, $form, $input_id = '' ) {
		$form_id = (int) rgar( $form, 'id' );
		if ( empty( self::$original[ $form_id ] ) || ! is_object( $field ) ) {
			return $value;
		}
		$original = self::$original[ $form_id ];
		$key      = '' !== (string) $input_id ? (string) $input_id : (string) $field->id;
		$old      = rgar( $original, $key );
		$keep     = 'administrative' === rgobj( $field, 'visibility' ) || ! empty( rgobj( $field, 'adminOnly' ) );
		$settings = Settings::get( $form );
		if ( ! $keep && in_array( $field->get_input_type(), array( 'fileupload', 'signature' ), true ) ) {
			$empty = '' === (string) $value || '[]' === (string) $value;
			$keep  = $empty && '' !== (string) $old;
		}
		return $keep ? $old : $value;
	}

	/**
	 * Skips notifications after an edit when so configured.
	 *
	 * @param bool  $disabled     Disabled.
	 * @param array $notification Notification.
	 * @param array $form         Form object.
	 * @param array $entry        Entry.
	 * @return bool
	 */
	public static function disable_notification( $disabled, $notification, $form, $entry ) {
		$form_id = (int) rgar( $form, 'id' );
		if ( empty( self::$original[ $form_id ] ) ) {
			return $disabled;
		}
		return 'skip' === Settings::get( $form )['edit_notifications'] ? true : $disabled;
	}

	/**
	 * Goes back to the list after an edit or a copy.
	 *
	 * @param mixed $confirmation Confirmation.
	 * @param array $form         Form object.
	 * @param array $entry        Entry.
	 * @param bool  $ajax         Ajax.
	 * @return mixed
	 */
	public static function confirmation( $confirmation, $form, $entry, $ajax = false ) {
		$context = self::is_attempt( rgar( $form, 'id' ) ) ? self::context( $form['id'] ) : null;
		if ( ! $context || '' === $context['return'] ) {
			return $confirmation;
		}
		if ( 'list' !== Settings::get( $form )['after_edit'] ) {
			return $confirmation;
		}
		$code = 'edit' === $context['action'] ? 'updated' : 'duplicated';
		$url  = add_query_arg(
			array(
				'estg_f'   => (int) $form['id'],
				'estg_msg' => $code,
				'estg_n'   => 1,
			),
			remove_query_arg( array( 'estg_msg', 'estg_n', 'estg_view' ), $context['return'] )
		);
		return array( 'redirect' => esc_url_raw( $url ) );
	}

	/**
	 * Adds a note and fires "estg_entry_updated" after an edit.
	 *
	 * @param array $entry Saved entry.
	 * @param array $form  Form object.
	 */
	public static function after_submission( $entry, $form ) {
		$form_id = (int) rgar( $form, 'id' );
		if ( empty( self::$original[ $form_id ] ) ) {
			return;
		}
		$user = wp_get_current_user();
		\GFAPI::add_note( (int) $entry['id'], (int) $user->ID, $user->display_name, __( 'Entry edited from the entry list.', 'entrystage' ), 'entrystage', 'success' );
		/**
		 * Fires after an entry was edited from the list.
		 *
		 * @param array $entry    Saved entry.
		 * @param array $original Entry before the edit.
		 * @param array $form     Form object.
		 */
		do_action( 'estg_entry_updated', $entry, self::$original[ $form_id ], $form );
	}
}
