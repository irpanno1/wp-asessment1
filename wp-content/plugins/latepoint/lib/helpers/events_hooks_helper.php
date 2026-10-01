<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/**
 * Central wiring for the Events feature. Gated by the "Enable Events Functionality" toggle
 * (settings[enable_events_functionality], General Settings) — see init_hooks().
 * Hooks: wizard step, cart/order pipeline, shortcodes, notifications.
 * Admin menu is registered statically in menu_helper.php.
 */
class OsEventsHooksHelper {

	public static $step_code         = 'booking__event';
	public static $tickets_step_code = 'booking__event_tickets';

	// ─── Bootstrap ────────────────────────────────────────────────────────

	public static function init_hooks(): void {
		// ── Front-end shortcodes ──────────────────────────────────────────
		// Always registered so a saved [latepoint_event]/[latepoint_events_list] tag never renders
		// as raw text on a page — each callback checks the "Enable Events Functionality" toggle
		// itself and degrades gracefully when it's off.
		add_action( 'init', [ __CLASS__, 'register_shortcodes' ] );

		if ( ! OsSettingsHelper::is_on( 'enable_events_functionality' ) ) {
			return;
		}

		// ── Wizard step registration ──────────────────────────────────────
		add_filter( 'latepoint_get_step_codes_with_rules', [ __CLASS__, 'add_event_step' ], 10, 2 );
		add_filter( 'latepoint_step_show_next_btn_rules', [ __CLASS__, 'add_step_next_btn_rules' ], 10, 2 );
		add_filter( 'latepoint_should_step_be_skipped', [ __CLASS__, 'should_step_be_skipped' ], 10, 5 );
		add_action( 'latepoint_load_step', [ __CLASS__, 'load_event_step' ], 10, 3 );
		add_action( 'latepoint_process_step', [ __CLASS__, 'require_tickets_before_leaving_event_steps' ], 8, 3 );
		add_action( 'latepoint_process_step', [ __CLASS__, 'process_event_step' ], 10, 3 );
		add_filter( 'latepoint_settings_for_step_codes', [ __CLASS__, 'add_step_settings' ] );
		add_filter( 'latepoint_step_labels_by_step_codes', [ __CLASS__, 'add_step_label' ] );
		add_filter( 'latepoint_svg_for_step_code', [ __CLASS__, 'add_event_step_svg' ], 10, 2 );
		add_action( 'latepoint_remove_preset_steps', [ __CLASS__, 'handle_event_presets' ], 10, 4 );
		add_action( 'latepoint_event_deleted', [ __CLASS__, 'delete_registrations_for_event' ] );
		// Both event steps run automatically whenever a visitor is registering for an event — they are
		// not admin-configurable, so keep them out of the Booking Form settings step-text dropdown and
		// the "Change Order" reorder modal.
		add_filter( 'latepoint_steps_for_select', [ __CLASS__, 'remove_event_steps_from_select' ] );
		add_filter( 'latepoint_step_codes_for_order_modal', [ __CLASS__, 'remove_event_steps_from_order_modal' ] );

		// ── Presets: inject selected_event_id from request ───────────────
		add_filter( 'latepoint_set_presets', [ __CLASS__, 'inject_event_preset' ], 10, 2 );
		// ── Persist event preset across wizard steps (in the form params area) ──
		add_action( 'latepoint_booking_form_params_presets_after', [ __CLASS__, 'render_event_preset_field' ], 10, 5 );

		// ── Cart/order item pipeline ──────────────────────────────────────
		add_filter( 'latepoint_cart_item_full_amount_to_charge', [ __CLASS__, 'cart_item_full_amount' ], 10, 2 );
		add_filter( 'latepoint_cart_item_deposit_amount_to_charge', [ __CLASS__, 'cart_item_deposit_amount' ], 10, 2 );
		add_filter( 'latepoint_order_item_full_amount_to_charge', [ __CLASS__, 'order_item_full_amount' ], 10, 2 );
		add_filter( 'latepoint_cart_item_get_item_display_name', [ __CLASS__, 'cart_item_display_name' ], 10, 2 );
		add_filter( 'latepoint_cart_item_get_image_url', [ __CLASS__, 'cart_item_image_url' ], 10, 2 );
		add_filter( 'latepoint_cart_item_original_object', [ __CLASS__, 'cart_item_original_object' ], 10, 2 );
		add_filter( 'latepoint_order_item_original_object', [ __CLASS__, 'order_item_original_object' ], 10, 2 );
		add_filter( 'latepoint_cart_price_breakdown_rows', [ __CLASS__, 'add_event_to_price_breakdown_rows' ], 10, 3 );
		add_action( 'latepoint_cart_summary_items', [ __CLASS__, 'render_event_cart_summary_items' ], 10, 3 );
		add_action( 'latepoint_order_summary_items', [ __CLASS__, 'render_event_order_summary_items' ] );
		add_action( 'latepoint_order_items_list_admin', [ __CLASS__, 'render_event_order_items_admin' ] );
		add_filter( 'latepoint_order_confirmation_message_title', [ __CLASS__, 'event_confirmation_message_title' ], 10, 2 );

		// ── Post-payment: create event_registration record ────────────────
		add_action( 'latepoint_order_created', [ __CLASS__, 'create_registrations_from_order' ] );
		// ── Keep registrations in sync with their order: release capacity when the order is
		// cancelled (get_booked_capacity() already excludes cancelled registrations — this is what
		// actually makes the exclusion apply), and keep payment_status current after a pay-later
		// order is paid. Refunds don't need their own listener — the admin cancels the order after
		// refunding, which already triggers this same sync.
		add_action( 'latepoint_order_updated', [ __CLASS__, 'sync_registrations_with_order' ], 20 );

		// ── Notifications (Processes engine) ──────────────────────────────
		add_filter( 'latepoint_process_event_types', [ __CLASS__, 'add_process_event_types' ] );
		add_filter( 'latepoint_process_event_names', [ __CLASS__, 'add_process_event_names' ] );
		add_filter( 'latepoint_load_templates_for_action_type', [ __CLASS__, 'add_event_templates' ], 10, 3 );
		// Priority 20 — after Pro's own registration-time handlers (ticket types at 5, e-ticket
		// issue/void at 10), so a job that runs synchronously (no time offset) sees fully-issued
		// tickets instead of racing ahead of them.
		add_action( 'latepoint_event_registration_created', [ __CLASS__, 'handle_event_registration_created_for_processes' ], 20 );
		add_action( 'latepoint_event_registration_cancelled', [ __CLASS__, 'handle_event_registration_cancelled_for_processes' ], 20 );
		// These four answer the generic Processes engine's own questions about the event -
		// without them create_jobs_for_process() can't compute a job's event_time_utc and
		// silently never creates one, regardless of Pro. Pro adds e-ticket-specific vars
		// (QR image, ticket URL) on top of these, but never owns anything this depends on.
		add_filter( 'latepoint_get_object_model_type_for_process', [ __CLASS__, 'set_object_model_type_for_process' ], 10, 3 );
		add_filter( 'latepoint_get_event_time_utc_for_process', [ __CLASS__, 'set_event_time_utc_for_process' ], 10, 3 );
		add_filter( 'latepoint_prepare_replacement_vars_from_data_objects', [ __CLASS__, 'prepare_replacement_vars' ], 10, 3 );
		add_filter( 'latepoint_replace_all_vars_in_template', [ __CLASS__, 'replace_vars_in_template' ], 10, 3 );
		add_action( 'latepoint_available_vars_after', [ __CLASS__, 'output_available_vars' ] );
	}

	// ─── Shortcodes ───────────────────────────────────────────────────────

	public static function register_shortcodes(): void {
		add_shortcode( 'latepoint_events_list', [ __CLASS__, 'shortcode_events_list' ] );
		// Only 2 shortcodes for now — [latepoint_event] (single-event detail page) is commented
		// out, not removed, so it can be re-enabled later. shortcode_event_detail() below and
		// its view (front/events/_event_detail.php) are left intact but unreferenced.
		// add_shortcode( 'latepoint_event', [ __CLASS__, 'shortcode_event_detail' ] );
		add_shortcode( 'latepoint_event_button', [ __CLASS__, 'shortcode_event_button' ] );
	}

	public static function shortcode_events_list( array $atts ): string {
		if ( ! OsSettingsHelper::is_on( 'enable_events_functionality' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			[
				'category_id'     => 0,
				'limit'           => 20,
				'image_mode'      => 'thumbnail',
				'show_seats_left' => 'yes',
			],
			$atts,
			'latepoint_events_list'
		);

		$image_mode      = in_array( $atts['image_mode'], [ 'thumbnail', 'date', 'none' ], true ) ? $atts['image_mode'] : 'thumbnail';
		$show_seats_left = ( $atts['show_seats_left'] === 'yes' );

		$args   = [
			'category_id' => absint( $atts['category_id'] ),
			'limit'       => absint( $atts['limit'] ),
		];
		$events = OsEventsManagerHelper::get_published_events( $args );

		// Batch-load booked capacity for the whole list instead of letting each card's
		// is_sold_out() call run its own SUM query.
		$event_ids  = array_map( function ( $event ) { return $event->id; }, $events );
		$capacities = OsEventModel::get_booked_capacities_for_event_ids( $event_ids );
		foreach ( $events as $event ) {
			$event->booked_capacity = $capacities[ $event->id ] ?? 0;
		}

		ob_start();
		include LATEPOINT_VIEWS_ABSPATH . 'front/events/_events_list.php';

		return ob_get_clean();
	}

	// [latepoint_event] is commented out for now (see register_shortcodes() above) — kept intact
	// here, and front/events/_event_detail.php is left untouched, so this can be re-enabled by
	// just uncommenting both.
	// public static function shortcode_event_detail( array $atts ): string {
	// if ( ! OsSettingsHelper::is_on( 'enable_events_functionality' ) ) {
	// return '<p>' . esc_html__( 'Event not found.', 'latepoint' ) . '</p>';
	// }
	//
	// $atts = shortcode_atts(
	// [
	// 'id' => 0,
	// ],
	// $atts,
	// 'latepoint_event'
	// );
	//
	// $event_id = absint( $atts['id'] );
	// $event    = new OsEventModel( $event_id );
	//
	// if ( ! $event->id || $event->status !== LATEPOINT_EVENT_STATUS_PUBLISHED ) {
	// return '<p>' . esc_html__( 'Event not found.', 'latepoint' ) . '</p>';
	// }
	//
	// ob_start();
	// include LATEPOINT_VIEWS_ABSPATH . 'front/events/_event_detail.php';
	//
	// return ob_get_clean();
	// }

	/**
	 * Renders a standalone Register button for a single event — same presentational attributes
	 * as [latepoint_book_button], so it's usable anywhere without the full list/detail markup.
	 */
	public static function shortcode_event_button( array $atts ): string {
		if ( ! OsSettingsHelper::is_on( 'enable_events_functionality' ) ) {
			return '<p>' . esc_html__( 'Event not found.', 'latepoint' ) . '</p>';
		}

		$atts = shortcode_atts(
			[
				'event_id'            => 0,
				'caption'             => __( 'Register', 'latepoint' ),
				'is_inherit'          => false,
				'align'               => false,
				'bg_color'            => false,
				'text_color'          => false,
				'font_size'           => false,
				'border'              => false,
				'border_radius'       => false,
				'margin'              => false,
				'padding'             => false,
				'css'                 => false,
				'classname'           => false,
				'btn_classes'         => false,
				'btn_wrapper_classes' => false,
			],
			$atts,
			'latepoint_event_button'
		);

		$event = new OsEventModel( absint( $atts['event_id'] ) );

		if ( ! $event->id || $event->status !== LATEPOINT_EVENT_STATUS_PUBLISHED ) {
			return '<p>' . esc_html__( 'Event not found.', 'latepoint' ) . '</p>';
		}

		$btn_wrapper_classes   = [];
		$btn_wrapper_classes[] = $atts['btn_wrapper_classes'] ?: 'latepoint-event-button-wrapper';
		if ( $atts['align'] ) {
			$btn_wrapper_classes[] = "latepoint-event-button-align-{$atts['align']}";
		}
		if ( $atts['classname'] ) {
			$btn_wrapper_classes[] = $atts['classname'];
		}

		$btn_classes = [];
		if ( $atts['btn_classes'] ) {
			$btn_classes[] = $atts['btn_classes'];
		}

		$styles = [];
		// If not inherit - show button styles. Only the active "Register" state is customizable —
		// the sold-out/closed states are fixed informational messages, same as the list/detail pages.
		if ( ! $atts['is_inherit'] ) {
			if ( $atts['bg_color'] ) {
				$styles[] = 'background-color: ' . esc_attr( $atts['bg_color'] );
			}
			if ( $atts['text_color'] ) {
				$styles[] = 'color: ' . esc_attr( $atts['text_color'] );
			}
			if ( $atts['font_size'] ) {
				$styles[] = 'font-size: ' . esc_attr( $atts['font_size'] );
			}
			if ( $atts['border'] ) {
				$styles[] = 'border: ' . esc_attr( $atts['border'] );
			}
			if ( $atts['border_radius'] ) {
				$styles[] = 'border-radius: ' . esc_attr( $atts['border_radius'] );
			}
			if ( $atts['margin'] ) {
				$styles[] = 'margin: ' . esc_attr( $atts['margin'] );
			}
			if ( $atts['padding'] ) {
				$styles[] = 'padding: ' . esc_attr( $atts['padding'] );
			}
			if ( $atts['css'] ) {
				$styles[] = $atts['css'];
			}
		}
		$style_attr = ! empty( $styles ) ? ' style="' . esc_attr( implode( '; ', $styles ) ) . '"' : '';

		if ( $event->is_sold_out() ) {
			$button = '<button type="button" class="latepoint-event-sold-out latepoint-btn latepoint-btn-secondary ' . esc_attr( implode( ' ', $btn_classes ) ) . '" disabled>'
				. esc_html__( 'Sold Out', 'latepoint' ) . '</button>';
		} elseif ( $event->is_registration_open() ) {
			$button = '<button type="button" class="latepoint-event-register-btn latepoint-btn latepoint-btn-primary ' . esc_attr( implode( ' ', $btn_classes ) ) . '"'
				. ' data-event-id="' . esc_attr( (string) $event->id ) . '"' . $style_attr . '>'
				. esc_html( $atts['caption'] ) . '</button>';
		} else {
			$button = '<button type="button" class="latepoint-event-unavailable latepoint-btn latepoint-btn-secondary ' . esc_attr( implode( ' ', $btn_classes ) ) . '" disabled>'
				. esc_html__( 'Registration Closed', 'latepoint' ) . '</button>';
		}

		return '<div class="' . esc_attr( implode( ' ', $btn_wrapper_classes ) ) . '">' . $button . '</div>';
	}

	// ─── Wizard step registration ─────────────────────────────────────────

	public static function add_event_step( array $steps ): array {
		// booking__event is a booking sub-step; booking sub-steps naturally precede customer.
		// No sibling ordering needed — all other booking__* steps are removed in event mode.
		$steps[ self::$step_code ] = [];
		// booking__event_tickets follows the info step — "after" rules match the bare sibling
		// name ('event'), not the prefixed code, per OsStepsHelper::insert_step_recursive().
		$steps[ self::$tickets_step_code ] = [ 'after' => 'event' ];

		return $steps;
	}

	public static function add_step_next_btn_rules( array $rules ): array {
		$rules[ self::$step_code ]         = true;
		$rules[ self::$tickets_step_code ] = true;

		return $rules;
	}

	public static function add_step_settings( array $settings ): array {
		$settings[ self::$step_code ]         = [
			'side_panel_heading'     => __( 'Event Information', 'latepoint' ),
			'side_panel_description' => __( 'Review the event details before you register', 'latepoint' ),
			'main_panel_heading'     => __( 'Event Information', 'latepoint' ),
		];
		$settings[ self::$tickets_step_code ] = [
			'side_panel_heading'     => __( 'Ticket Selection', 'latepoint' ),
			'side_panel_description' => __( 'Please select the number of tickets you\'d like to reserve', 'latepoint' ),
			'main_panel_heading'     => __( 'Select Your Tickets', 'latepoint' ),
		];

		return $settings;
	}

	public static function add_step_label( array $labels ): array {
		$labels[ self::$step_code ]         = __( 'Event', 'latepoint' );
		$labels[ self::$tickets_step_code ] = __( 'Tickets', 'latepoint' );

		return $labels;
	}

	/**
	 * Returns a ticket-style inline SVG for the event wizard step side panel.
	 * Hooked on `latepoint_svg_for_step_code` so that the event step gets a
	 * matching line-art illustration like the built-in booking steps.
	 *
	 * @param string $svg       SVG string produced by the core switch (empty for unknown codes).
	 * @param string $step_code The step code being rendered.
	 *
	 * @return string
	 */
	public static function add_event_step_svg( string $svg, string $step_code ): string {
		if ( ! in_array( $step_code, [ self::$step_code, self::$tickets_step_code ], true ) ) {
			return $svg;
		}
		// Ticket icon: outer frame (evenodd with inner hollow) + dashed separator + body text lines.
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">'
			. '<path class="latepoint-step-svg-highlight" fill-rule="evenodd" d="'
			. 'M 13,20 L 60,20 A 5,5 0 0 1 65,25 L 65,48 A 5,5 0 0 1 60,53 L 13,53 A 5,5 0 0 1 8,48 L 8,25 A 5,5 0 0 1 13,20 Z '
			. 'M 13,22 L 60,22 A 3,3 0 0 1 63,25 L 63,48 A 3,3 0 0 1 60,51 L 13,51 A 3,3 0 0 1 10,48 L 10,25 A 3,3 0 0 1 13,22 Z '
			. 'M 22,24 L 24,24 L 24,28 L 22,28 Z '
			. 'M 22,30 L 24,30 L 24,34 L 22,34 Z '
			. 'M 22,36 L 24,36 L 24,40 L 22,40 Z '
			. 'M 22,42 L 24,42 L 24,46 L 22,46 Z '
			. 'M 22,48 L 24,48 L 24,51 L 22,51 Z'
			. '"/>'
			. '<path class="latepoint-step-svg-highlight" d="'
			. 'M 28,29 L 57,29 L 57,31 L 28,31 Z '
			. 'M 28,36 L 51,36 L 51,38 L 28,38 Z '
			. 'M 28,43 L 55,43 L 55,45 L 28,45 Z'
			. '"/>'
			. '</svg>';
	}

	/**
	 * Inject selected_event_id from the request into presets so the step system
	 * knows we are in event mode across all subsequent wizard steps.
	 *
	 * Event mode is entered ONLY when the request explicitly carries selected_event_id
	 * (set by the event Register button or round-tripped via the persistent hidden field
	 * emitted by render_event_preset_field()). No global-session fallback — that leaked
	 * event mode into normal appointment bookings.
	 */
	public static function inject_event_preset( array $presets, array $raw_presets ): array {
		$event_id = (int) ( $raw_presets['selected_event_id'] ?? 0 );
		if ( $event_id ) {
			$presets['selected_event_id'] = $event_id;
			$presets['booking_intent']    = 'event';
		}

		return $presets;
	}

	/**
	 * Render persistent hidden fields for event presets in the booking-form params area.
	 *
	 * Hooked to latepoint_booking_form_params_presets_after, which fires inside
	 * lib/views/steps/partials/_booking_form_params.php — the persistent .latepoint-presets
	 * div that is re-serialized by the client on every step request. This ensures
	 * selected_event_id round-trips through the entire wizard (event → customer → payment)
	 * without relying on the global transient session.
	 *
	 * @param OsBookingModel $booking
	 * @param array          $restrictions
	 * @param array          $presets
	 * @param string         $current_step_code
	 * @param string         $add_string_to_id
	 */
	public static function render_event_preset_field( $booking, $restrictions, $presets, $current_step_code, $add_string_to_id ): void {
		$event_id = (int) ( OsStepsHelper::$presets['selected_event_id'] ?? 0 );
		if ( $event_id ) {
			echo OsFormHelper::hidden_field(
				'presets[selected_event_id]',
				$event_id,
				[
					'skip_id' => true,
					'class'   => 'clear_for_new_item',
				]
			);
			echo OsFormHelper::hidden_field(
				'presets[booking_intent]',
				'event',
				[
					'skip_id' => true,
					'class'   => 'clear_for_new_item',
				]
			);
		}
	}

	/**
	 * Deletes all registrations for an event once it's gone, so cancelled-order cleanup, CSV
	 * exports, and capacity sums never see rows pointing at a nonexistent event.
	 */
	public static function delete_registrations_for_event( OsEventModel $event ): void {
		( new OsEventRegistrationModel() )->delete_where( [ 'event_id' => $event->id ] );
	}

	/**
	 * Remove booking-specific steps when in event mode.
	 */
	public static function handle_event_presets( array $presets, OsCartItemModel $active_cart_item, OsBookingModel $booking, OsCartModel $cart ): void {
		if ( empty( $presets['selected_event_id'] ) ) {
			return;
		}

		// A previous, abandoned registration attempt for a DIFFERENT event can still be sitting in
		// the cart (cookie-based, so it survives closing the wizard popup) — reconcile it here, on
		// every wizard request (fires on the very first steps__start too, via
		// remove_restricted_and_skippable_steps()), so the summary panel/cart-count badge/price
		// breakdown never show stale data from the very first screen after opening. Re-entering the
		// SAME event is a no-op, so an in-progress ticket-type selection is preserved (same
		// guarantee as back-navigation).
		$selected_event_id = (int) $presets['selected_event_id'];
		foreach ( $cart->get_items() as $existing_item ) {
			if ( $existing_item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				continue;
			}
			$existing_data = OsEventsManagerHelper::decode_item_data( $existing_item->item_data );
			if ( (int) $existing_data['event_id'] !== $selected_event_id ) {
				$cart->remove_item( $existing_item );
			}
		}

		// Remove all standard booking sub-steps; keep booking__event (our own step).
		OsStepsHelper::remove_step_by_name( 'booking__locations' );
		OsStepsHelper::remove_step_by_name( 'booking__services' );
		OsStepsHelper::remove_step_by_name( 'booking__agents' );
		OsStepsHelper::remove_step_by_name( 'booking__datepicker' );
		OsStepsHelper::remove_step_by_name( 'booking__group_bookings' );
		OsStepsHelper::remove_step_by_name( 'booking__service_extras' );
	}

	/**
	 * Remove both event steps from the Booking Form settings step-text dropdown — their text is fixed
	 * (see add_step_settings()), not admin-configurable.
	 */
	public static function remove_event_steps_from_select( array $steps_with_labels ): array {
		unset( $steps_with_labels[ self::$step_code ], $steps_with_labels[ self::$tickets_step_code ] );

		return $steps_with_labels;
	}

	/**
	 * Remove both event steps from the "Change Order" reorder modal — they always run automatically in
	 * event mode (in place of the normal booking sub-steps), so reordering them makes no sense.
	 */
	public static function remove_event_steps_from_order_modal( array $step_codes ): array {
		return array_values( array_diff( $step_codes, [ self::$step_code, self::$tickets_step_code ] ) );
	}

	/**
	 * Skip both event steps when not in event mode; skip booking steps when in event mode.
	 */
	public static function should_step_be_skipped( bool $skip, string $step_code, OsCartModel $cart, OsCartItemModel $cart_item, OsBookingModel $booking ): bool {
		$event_id    = (int) ( OsStepsHelper::$presets['selected_event_id'] ?? 0 );
		$our_steps   = [ self::$step_code, self::$tickets_step_code ];
		$is_our_step = in_array( $step_code, $our_steps, true );

		if ( $is_our_step ) {
			// Show event steps only in event mode.
			if ( ! $event_id ) {
				return true;
			}
			// Skip the ticket selector when this event has a fixed quantity of 1 per registration —
			// unless an addon (e.g. Pro's Ticket Types) still needs this step for its own selector,
			// so that setting can't be used to silently bypass per-type selection/pricing.
			if ( $step_code === self::$tickets_step_code ) {
				$event = new OsEventModel( $event_id );
				if ( ! $event->id || $event->should_show_ticket_selector() ) {
					return false;
				}

				return ! (bool) apply_filters( 'latepoint_event_tickets_step_selector_html', '', $event );
			}

			return false;
		}

		// Skip all other booking__* steps in event mode
		if ( $event_id && 0 === strpos( $step_code, 'booking__' ) ) {
			return true;
		}

		return $skip;
	}

	/**
	 * Render the event wizard steps (info + tickets).
	 */
	public static function load_event_step( string $step_code, string $format = 'json', array $params = [] ): void {
		if ( ! in_array( $step_code, [ self::$step_code, self::$tickets_step_code ], true ) ) {
			return;
		}

		$event_id = (int) ( OsStepsHelper::$presets['selected_event_id'] ?? 0 );
		$event    = $event_id ? new OsEventModel( $event_id ) : null;
		$view     = $step_code === self::$tickets_step_code ? '_step_event_tickets' : '_step_event';

		$controller                            = new OsEventsManagerController();
		$controller->vars['event']             = $event;
		$controller->vars['current_step_code'] = $step_code;
		$controller->vars['presets']           = OsStepsHelper::$presets;
		$controller->set_layout( 'none' );
		$controller->set_return_format( $format );
		$controller->format_render(
			$view,
			[],
			[
				// Forwards any pending hidden-field patches (e.g. active_cart_item[id], set by
				// process_event_step() when the info step just created the cart item) to the
				// client — same as OsStepsHelper::load_step() does for standard steps
				// (steps_helper.php:613). Without this, the id never round-trips to the
				// tickets step and its submission creates a second, duplicate cart item.
				'fields_to_update' => OsStepsHelper::$fields_to_update,
				'step_code'        => $step_code,
				'show_next_btn'    => OsStepsHelper::can_step_show_next_btn( $step_code ),
				'show_prev_btn'    => OsStepsHelper::can_step_show_prev_btn( $step_code ),
				'is_first_step'    => OsStepsHelper::is_first_step( $step_code ),
				'is_last_step'     => OsStepsHelper::is_last_step( $step_code ),
				'is_pre_last_step' => OsStepsHelper::is_pre_last_step( $step_code ),
			]
		);
	}

	/**
	 * Resolves the final ticket quantity for an event step/quantity-update request and validates
	 * it (registration window open, capacity available). Shared by process_event_step() and
	 * OsEventsManagerController::update_ticket_quantity() — the two previously duplicated this
	 * filter pair and validation with a drifted copy (the controller's copy was missing the
	 * is_registration_open() check this method now covers for both).
	 *
	 * @return array{quantity: int, error: ?string, is_capacity_error: bool} `is_capacity_error`
	 *         lets a step-based caller decide whether to redirect back to the tickets step.
	 */
	public static function resolve_and_validate_event_quantity( OsEventModel $event, array $params, int $base_quantity ): array {
		/**
		 * Total ticket quantity for this step, before validation.
		 *
		 * Lets an addon that sells multiple named ticket types on one registration (e.g. Pro's
		 * Ticket Types) substitute the sum across its own type-quantity fields for the plain
		 * `event_quantity` field this step normally reads.
		 *
		 * @param {int}          $quantity Quantity read from `event_quantity`.
		 * @param {OsEventModel} $event    The event being registered for.
		 * @param {array}        $params   Raw request params for the current step.
		 * @returns {int} The filtered quantity.
		 *
		 * @since 5.7.0
		 * @hook latepoint_event_step_quantity
		 *
		 */
		$quantity = (int) apply_filters( 'latepoint_event_step_quantity', $base_quantity, $event, $params );

		if ( ! $event->is_registration_open() ) {
			return [
				'quantity'          => $quantity,
				'error'             => __( 'Registration for this event is not open.', 'latepoint' ),
				'is_capacity_error' => false,
			];
		}

		/**
		 * Whether to skip this step's own event-level capacity check.
		 *
		 * An addon that tracks capacity per ticket type (e.g. Pro's Ticket Types) validates
		 * capacity itself, earlier in `latepoint_process_step`, and can return true here to
		 * avoid a redundant/looser check against the event's total capacity alone.
		 *
		 * @param {bool}         $skip   Whether to skip the check. Default false.
		 * @param {OsEventModel} $event  The event being registered for.
		 * @param {array}        $params Raw request params for the current step.
		 * @returns {bool} The filtered skip flag.
		 *
		 * @since 5.7.0
		 * @hook latepoint_event_skip_capacity_check
		 *
		 */
		$skip_capacity_check = apply_filters( 'latepoint_event_skip_capacity_check', false, $event, $params );

		if ( ! $skip_capacity_check && $event->has_capacity_limit() && $event->get_available_capacity() < $quantity ) {
			return [
				'quantity'          => $quantity,
				'error'             => sprintf(
					/* translators: %d: available capacity */
					__( 'Only %d spot(s) available for this event.', 'latepoint' ),
					$event->get_available_capacity()
				),
				'is_capacity_error' => true,
			];
		}

		return [
			'quantity'          => $quantity,
			'error'             => null,
			'is_capacity_error' => false,
		];
	}

	/**
	 * Blocks forward navigation out of the event steps while nothing is selected.
	 * validate_types_step() (Pro) only fires while the tickets step itself is being submitted,
	 * and prev/specific step navigation skips latepoint_process_step entirely — so a 0-ticket
	 * cart item created on the info step could otherwise ride through to a confirmed,
	 * registration-less $0 order. is_bookable() is the authoritative gate that actually blocks
	 * the order; this just stops the customer earlier, at the tickets step, instead of at the
	 * final confirm click.
	 */
	public static function require_tickets_before_leaving_event_steps( string $step_code, OsBookingModel $booking, array $params ): void {
		// Both event steps legitimately run with an empty selection — the info step creates the
		// 0-quantity item, and the tickets step is where the selection is actually made.
		if ( in_array( $step_code, [ self::$step_code, self::$tickets_step_code ], true ) ) {
			return;
		}
		if ( ! OsStepsHelper::$cart_object ) {
			return;
		}
		foreach ( OsStepsHelper::$cart_object->get_items() as $cart_item ) {
			if ( $cart_item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				continue;
			}
			if ( OsEventsManagerHelper::resolve_item_quantity( $cart_item->item_data ) < 1 ) {
				self::send_step_error( __( 'Please select at least one ticket.', 'latepoint' ), self::$tickets_step_code );
			}
		}
	}

	/**
	 * Process the event wizard steps: validate capacity, build cart item.
	 * Runs on both the info and tickets steps — event_quantity is only present in
	 * $params once the tickets step has been submitted, so it defaults to 1 while
	 * the info step is processed (still creating the cart item, so the summary
	 * sidebar reflects the event right away).
	 */
	public static function process_event_step( string $step_code, OsBookingModel $booking, array $params ): void {
		if ( ! in_array( $step_code, [ self::$step_code, self::$tickets_step_code ], true ) ) {
			return;
		}

		$event_id = (int) ( OsStepsHelper::$presets['selected_event_id'] ?? 0 );
		$quantity = max( 1, absint( $params['event_quantity'] ?? 1 ) );

		if ( ! $event_id ) {
			self::send_step_error( __( 'No event selected.', 'latepoint' ) );

			return;
		}

		$event = new OsEventModel( $event_id );
		if ( ! $event->id ) {
			self::send_step_error( __( 'Event not found.', 'latepoint' ) );

			return;
		}

		$result = self::resolve_and_validate_event_quantity( $event, $params, $quantity );
		if ( $result['error'] ) {
			self::send_step_error( $result['error'], $result['is_capacity_error'] ? self::$tickets_step_code : '' );

			return;
		}
		$quantity = $result['quantity'];

		// Reuse the existing cart item on back-navigation so we update it in place instead of
		// inserting a duplicate row (which inflated the price on every pass). Only call
		// add_item() when this truly is a new item — mirrors the canonical booking flow
		// in OsStepsHelper::add_current_item_to_cart() (steps_helper.php:1644-1704).
		$cart_item = OsStepsHelper::$active_cart_item;
		$is_new    = ( ! $cart_item || $cart_item->is_new_record() );
		if ( $is_new ) {
			$cart_item = new OsCartItemModel();
		}

		$cart_item->variant   = LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION;
		$cart_item->item_data = wp_json_encode( OsEventsManagerHelper::build_cart_item_data( $event, $quantity, $params ) );

		if ( $is_new ) {
			// A visitor can only have one event registration in the cart at a time. The cart persists
			// across separate wizard opens (OsCartsHelper::get_or_create_cart() reuses the same cart via
			// a cookie), so an earlier abandoned registration can still be sitting here — clear it
			// before adding this one, so the summary never shows more than one event at once.
			foreach ( OsStepsHelper::$cart_object->get_items() as $existing_item ) {
				if ( $existing_item->variant === LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
					OsStepsHelper::$cart_object->remove_item( $existing_item );
				}
			}

			// New item: add_item() persists the cart if needed, sets cart_id, then saves.
			if ( ! OsStepsHelper::$cart_object->add_item( $cart_item ) ) {
				return;
			}
		} else {
			// Existing item: update the DB row in place, then refresh the cart's in-memory
			// items list so the total recomputes from the new quantity without appending a
			// duplicate line (same refresh pattern as OsCartModel::remove_item(), cart_model.php:500).
			//
			// set_active_cart_item_object() only populates id on the model; cart_id is never
			// loaded from the DB there, so we must set it explicitly before save() to prevent
			// the UPDATE from setting cart_id = NULL (which would detach the item from the cart).
			$cart_item->cart_id = OsStepsHelper::$cart_object->id;
			$cart_item->save();
			OsStepsHelper::$cart_object->items = OsCartsHelper::get_items_for_cart_id( OsStepsHelper::$cart_object->id );
			OsStepsHelper::$cart_object->calculate_prices();
		}

		OsStepsHelper::$active_cart_item = $cart_item;
		// Round-trip the id through the persistent hidden field (active_cart_item[id]) so that
		// back-navigation reuses this exact cart item instead of creating a new one each time.
		OsStepsHelper::$fields_to_update['active_cart_item[id]'] = $cart_item->id;
		// Keep preset alive across subsequent steps.
		OsStepsHelper::$presets['selected_event_id'] = $event_id;
		OsStepsHelper::$presets['booking_intent']    = 'event';
	}

	/**
	 * Update the quantity on the current active cart item in place, without advancing the wizard —
	 * used by the live +/- ticket-quantity preview on the tickets step (see update_ticket_quantity()
	 * in OsEventsManagerController). Mirrors the "existing item" branch in process_event_step() above.
	 */
	public static function update_active_cart_item_quantity( OsEventModel $event, int $quantity, array $params = [] ): void {
		$active = OsStepsHelper::$active_cart_item;
		if ( ! $active || $active->is_new_record() ) {
			// Info step hasn't created the cart item yet — nothing to update; the eventual
			// "Next" submission will create it with the correct quantity anyway.
			return;
		}

		// set_active_cart_item_object() (steps_helper.php) builds $active from only the client-
		// supplied id — cart_id is never loaded from the DB there, so re-fetch the real row rather
		// than trusting the in-memory object for the ownership check below (same reason
		// process_event_step() explicitly re-sets cart_id itself before its own save()).
		$cart_item = new OsCartItemModel( $active->id );
		if ( $cart_item->is_new_record() || (int) $cart_item->cart_id !== (int) OsStepsHelper::$cart_object->id ) {
			return;
		}

		$cart_item->variant   = LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION;
		$cart_item->item_data = wp_json_encode( OsEventsManagerHelper::build_cart_item_data( $event, $quantity, $params ) );
		$cart_item->save();
		OsStepsHelper::$cart_object->items = OsCartsHelper::get_items_for_cart_id( OsStepsHelper::$cart_object->id );
		OsStepsHelper::$cart_object->calculate_prices();
		OsStepsHelper::$active_cart_item = $cart_item;
	}

	/**
	 * Send a step-processing error as JSON and stop execution.
	 *
	 * A `latepoint_process_step` listener's return value is discarded by
	 * OsStepsHelper::process_step() (unlike a core process_step_* method, which can return
	 * WP_Error), so this is the only way to surface a validation failure to the wizard —
	 * mirrors the response shape OsStepsHelper::process_step() itself sends on WP_Error.
	 */
	private static function send_step_error( string $message, string $send_to_step = '' ): void {
		wp_send_json(
			[
				'status'           => LATEPOINT_STATUS_ERROR,
				'message'          => $message,
				'send_to_step'     => $send_to_step,
				'fields_to_update' => OsStepsHelper::$fields_to_update,
			]
		);
	}

	// ─── Cart / order pipeline hooks ──────────────────────────────────────

	public static function cart_item_full_amount( float $amount, OsCartItemModel $item ): float {
		if ( $item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
			return $amount;
		}

		return OsEventsManagerHelper::calculate_item_amount( $item->item_data );
	}

	public static function cart_item_deposit_amount( float $amount, OsCartItemModel $item ): float {
		if ( $item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
			return $amount;
		}

		// Events: no deposit — pay full amount always
		return OsEventsManagerHelper::calculate_item_amount( $item->item_data );
	}

	public static function order_item_full_amount( float $amount, OsOrderItemModel $item ): float {
		if ( $item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
			return $amount;
		}

		return OsEventsManagerHelper::calculate_item_amount( $item->item_data );
	}

	public static function cart_item_display_name( string $name, OsCartItemModel $item ): string {
		if ( $item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
			return $name;
		}

		$data = OsEventsManagerHelper::decode_item_data( $item->item_data );

		return $data['event_name'] ?: __( 'Event Registration', 'latepoint' );
	}

	public static function cart_item_image_url( string $url, OsCartItemModel $item ): string {
		if ( $item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
			return $url;
		}

		$data  = OsEventsManagerHelper::decode_item_data( $item->item_data );
		$event = $data['event_id'] ? new OsEventModel( $data['event_id'] ) : null;

		return $event && $event->id ? $event->get_featured_image_url( 'thumbnail' ) : $url;
	}

	public static function cart_item_original_object( $obj, OsCartItemModel $item ) {
		if ( $item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
			return $obj;
		}

		$data = OsEventsManagerHelper::decode_item_data( $item->item_data );

		return $data['event_id'] ? new OsEventModel( $data['event_id'] ) : $obj;
	}

	public static function order_item_original_object( $obj, OsOrderItemModel $item ) {
		if ( $item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
			return $obj;
		}

		$data = OsEventsManagerHelper::decode_item_data( $item->item_data );

		return $data['event_id'] ? new OsEventModel( $data['event_id'] ) : $obj;
	}

	/**
	 * Render event registration items inside the cart summary panel / verify step summary.
	 * Hooked to 'latepoint_cart_summary_items' so the event name + ticket count appear
	 * alongside bookings and bundles in the summary (fixes missing event line on summary).
	 *
	 * @param OsCartModel $cart            Current cart.
	 * @param string      $output_target   'summary_panel' or 'step_verify'.
	 * @param string      $current_step_code Current wizard step.
	 */
	public static function render_event_cart_summary_items( OsCartModel $cart, string $output_target, string $current_step_code ): void {
		foreach ( $cart->get_items() as $cart_item ) {
			if ( $cart_item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				continue;
			}
			$data = OsEventsManagerHelper::decode_item_data( $cart_item->item_data );
			// A per-type-selection addon (e.g. Pro's Ticket Types) always writes this key once
			// active for the event, even as an empty array — so its presence alone (regardless of
			// content) is what tells "0 is a real, valid quantity" apart from "flat event, default
			// to 1", without this file needing to know Pro's classes exist.
			$uses_ticket_types = array_key_exists( 'ticket_types', $data );
			$quantity          = OsEventsManagerHelper::resolve_item_quantity( $cart_item->item_data );
			$pending           = $uses_ticket_types && $quantity < 1;
			$name              = $cart_item->get_item_display_name();
			// Not calculate_item_amount() directly — that ignores the latepoint_cart_item_full_amount_to_charge
			// filter chain (e.g. Pro's Ticket Types override), so this line would show a flat-price
			// total that doesn't match what full_amount_to_charge() actually charges.
			$amount = $pending ? 0.0 : $cart_item->full_amount_to_charge();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			include LATEPOINT_VIEWS_ABSPATH . 'events_manager/_cart_summary_item.php';
		}
	}

	/**
	 * Render event registration items inside the post-checkout order summary (confirmation screen,
	 * customer cabinet, agent view, print). Hooked to 'latepoint_order_summary_items'.
	 *
	 * @param OsOrderModel $order Current order.
	 */
	public static function render_event_order_summary_items( OsOrderModel $order ): void {
		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				continue;
			}
			$data = OsEventsManagerHelper::decode_item_data( $order_item->item_data );
			// See the note in render_event_cart_summary_items() above.
			$uses_ticket_types = array_key_exists( 'ticket_types', $data );
			$quantity          = OsEventsManagerHelper::resolve_item_quantity( $order_item->item_data );
			$pending           = $uses_ticket_types && $quantity < 1;
			$name              = ! empty( $data['event_name'] ) ? $data['event_name'] : __( 'Event', 'latepoint' );
			$amount            = $order_item->full_amount_to_charge();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			include LATEPOINT_VIEWS_ABSPATH . 'events_manager/_cart_summary_item.php';
		}
	}

	/**
	 * Render event registration items inside the admin order detail editor.
	 * Hooked to 'latepoint_order_items_list_admin'.
	 *
	 * @param OsOrderModel $order Current order.
	 */
	public static function render_event_order_items_admin( OsOrderModel $order ): void {
		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				continue;
			}
			$data = OsEventsManagerHelper::decode_item_data( $order_item->item_data );
			// See the note in render_event_cart_summary_items() above.
			$uses_ticket_types = array_key_exists( 'ticket_types', $data );
			$quantity          = $uses_ticket_types ? max( 0, (int) $data['quantity'] ) : max( 1, (int) $data['quantity'] );
			$name              = ! empty( $data['event_name'] ) ? $data['event_name'] : __( 'Event', 'latepoint' );
			$amount            = $order_item->full_amount_to_charge();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			include LATEPOINT_VIEWS_ABSPATH . 'events_manager/_order_item_admin.php';
		}
	}

	/**
	 * Add the event line item to the checkout price-breakdown panel.
	 */
	public static function add_event_to_price_breakdown_rows( array $rows, OsCartModel $cart, array $rows_to_hide ): array {
		foreach ( $cart->get_items() as $item ) {
			if ( $item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				continue;
			}

			$data = OsEventsManagerHelper::decode_item_data( $item->item_data );
			// See the note in render_event_cart_summary_items() above.
			$amount   = $item->full_amount_to_charge();
			$label    = $data['event_name'] ?: __( 'Event', 'latepoint' );
			$quantity = (int) $data['quantity'];
			$event    = $data['event_id'] ? new OsEventModel( (int) $data['event_id'] ) : null;

			$note = '';
			// The "(qty × unit price)" note only makes sense for a single flat price — an addon
			// breakdown (e.g. Pro's Ticket Types) has its own per-type prices, so skip it there.
			if ( $quantity > 1 && isset( $data['event_price'] ) && empty( $data['ticket_types'] ) && $event && $event->should_multiply_price_by_tickets() ) {
				$note = '(' . $quantity . ' × ' . OsMoneyHelper::format_price( $data['event_price'], true, false ) . ')';
			}

			$rows[] = [
				'type'    => 'before_subtotal',
				'label'   => $label,
				'note'    => $note,
				'value'   => OsMoneyHelper::format_price( $amount, true, false ),
				'raw'     => $amount,
				'classes' => 'event-registration-row',
			];
		}

		return $rows;
	}

	// ─── Post-payment: create registrations ───────────────────────────────

	public static function create_registrations_from_order( OsOrderModel $order ): void {
		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				continue;
			}

			$data     = OsEventsManagerHelper::decode_item_data( $order_item->item_data );
			$event_id = (int) $data['event_id'];
			// A per-type-selection addon (e.g. Pro's Ticket Types) always writes this key once
			// active for the event, even as an empty array — so its presence alone (regardless of
			// content) is what tells "0 is a real, valid quantity" apart from "flat event, default
			// to 1". Same rule as render_event_cart_summary_items() above. Writing a registration
			// anyway for a still-pending (quantity 0) ticket-types item created a free,
			// capacity-consuming, ticket-issuing phantom registration for zero selected tickets.
			$uses_ticket_types = array_key_exists( 'ticket_types', $data );
			$quantity          = OsEventsManagerHelper::resolve_item_quantity( $order_item->item_data );

			if ( ! $event_id || ( $uses_ticket_types && $quantity < 1 ) ) {
				continue;
			}

			// Re-check capacity to prevent oversell under concurrency
			$event = new OsEventModel( $event_id );
			if ( $event->has_capacity_limit() && $event->get_available_capacity() < $quantity ) {
				error_log( sprintf( 'LatePoint: event registration oversold for event #%d on order #%d (requested %d, available %d) — no registration created.', $event_id, $order->id, $quantity, $event->get_available_capacity() ) );
				/**
				 * Fires when a paid order's event registration can't be created because the event
				 * sold out between checkout and payment. The customer was charged but got no
				 * registration — nothing else surfaces this; hook it to notify an admin or trigger
				 * a refund.
				 *
				 * @since 5.7.0
				 * @hook latepoint_event_registration_oversold
				 *
				 * @param {OsOrderModel} $order    The paid order.
				 * @param {int}          $event_id The oversold event.
				 * @param {int}          $quantity Tickets requested by this order item.
				 */
				do_action( 'latepoint_event_registration_oversold', $order, $event_id, $quantity );
				continue;
			}

			$reg                 = new OsEventRegistrationModel();
			$reg->event_id       = $event_id;
			$reg->customer_id    = $order->customer_id;
			$reg->order_id       = $order->id;
			$reg->order_item_id  = $order_item->id;
			$reg->quantity       = $quantity;
			$reg->status         = LATEPOINT_EVENT_REGISTRATION_STATUS_CONFIRMED;
			$reg->payment_status = $order->payment_status;

			if ( $reg->save() ) {
				/**
				 * Fires after a paid order creates an event registration.
				 *
				 * @param {OsEventRegistrationModel} $reg The registration that was created.
				 *
				 * @since 5.7.0
				 * @hook latepoint_event_registration_created
				 */
				do_action( 'latepoint_event_registration_created', $reg );
			}
		}
	}

	// ─── Order status/payment sync ────────────────────────────────────────

	/**
	 * Keeps each order's event_registration items in sync whenever the order is saved:
	 * cancelling the order cancels its registrations (which is what releases capacity, since
	 * OsEventModel::get_booked_capacity() already excludes cancelled registrations), and any
	 * payment_status change on the order (pay-later order gets paid, a refund adjusts it, etc.)
	 * is copied onto the registration instead of the one-time snapshot taken at creation time.
	 *
	 * @since 5.7.0
	 */
	public static function sync_registrations_with_order( OsOrderModel $order ): void {
		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item->variant !== LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				continue;
			}

			$reg = ( new OsEventRegistrationModel() )->where( [ 'order_item_id' => $order_item->id ] )->set_limit( 1 )->get_results_as_models();
			if ( ! $reg ) {
				continue;
			}

			if ( LATEPOINT_ORDER_STATUS_CANCELLED === $order->status && ! $reg->is_cancelled() ) {
				$old_status  = $reg->status;
				$reg->status = LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED;
				if ( $reg->save() ) {
					do_action( 'latepoint_event_registration_cancelled', $reg, $old_status );
				}
			}

			if ( $order->payment_status !== $reg->payment_status ) {
				$reg->payment_status = $order->payment_status;
				$reg->save();
			}
		}
	}

	// ─── Notification (Processes engine) hooks ────────────────────────────

	public static function add_process_event_types( array $types ): array {
		$types[] = 'event_registration_created';
		$types[] = 'event_registration_cancelled';

		return $types;
	}

	public static function add_process_event_names( array $names ): array {
		$names['event_registration_created']   = __( 'Event Registration Created', 'latepoint' );
		$names['event_registration_cancelled'] = __( 'Event Registration Cancelled', 'latepoint' );

		return $names;
	}

	public static function handle_event_registration_created_for_processes( OsEventRegistrationModel $reg ): void {
		$objects   = [];
		$objects[] = [
			'model'       => 'event_registration',
			'id'          => $reg->id,
			'model_ready' => $reg,
		];
		OsProcessJobsHelper::create_jobs_for_event( 'event_registration_created', $objects );
	}

	public static function handle_event_registration_cancelled_for_processes( OsEventRegistrationModel $reg ): void {
		$objects   = [];
		$objects[] = [
			'model'       => 'event_registration',
			'id'          => $reg->id,
			'model_ready' => $reg,
		];
		OsProcessJobsHelper::create_jobs_for_event( 'event_registration_cancelled', $objects );
	}

	public static function set_object_model_type_for_process( ?string $object_model_type, OsProcessModel $process, array $objects ): ?string {
		if ( in_array( $process->event_type, [ 'event_registration_created', 'event_registration_cancelled' ], true ) ) {
			$object_model_type = 'event_registration';
		}

		return $object_model_type;
	}

	public static function set_event_time_utc_for_process( ?OsWpDateTime $event_time_utc, OsProcessModel $process, array $objects ): ?OsWpDateTime {
		if ( ! in_array( $process->event_type, [ 'event_registration_created', 'event_registration_cancelled' ], true ) ) {
			return $event_time_utc;
		}

		try {
			$reg             = $objects[0]['model_ready'] ?? new OsEventRegistrationModel( $objects[0]['id'] );
			$source_datetime = 'event_registration_created' === $process->event_type ? $reg->created_at : $reg->updated_at;
			$event_time_utc  = new OsWpDateTime( $source_datetime, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error creating event time for event_registration process', 'process_jobs_error', $e->getMessage() );
		}

		return $event_time_utc;
	}

	/**
	 * Deliberately does NOT populate $vars['order'], even though the registration has an
	 * order_id: OsReplacerHelper::replace_order_vars() unconditionally calls
	 * OsOrdersHelper::generate_order_items_html(), which assumes every order item is a booking
	 * and fatals (undefined OsEventModel::get_nice_start_datetime()) on an event_registration
	 * item — a pre-existing bug, out of scope here. {{order_*}} tokens simply aren't available
	 * on event-registration templates; nothing in this feature's own templates needs them.
	 */
	public static function prepare_replacement_vars( array $vars, array $data_objects, array $other_vars ): array {
		foreach ( $data_objects as $data_object ) {
			if ( 'event_registration' !== $data_object['model'] ) {
				continue;
			}
			$reg                        = $data_object['model_ready'] ?? new OsEventRegistrationModel( $data_object['id'] );
			$vars['event_registration'] = $reg;
			if ( empty( $vars['customer'] ) ) {
				$vars['customer'] = $reg->get_customer();
			}
		}

		return $vars;
	}

	/**
	 * Base event-registration template vars. The E-Ticket-specific {{event_tickets_qr}}/
	 * {{event_tickets_url}} vars are Pro's own — OsFeatureEventTicketsHelper::replace_vars_in_template()
	 * runs after this (default priority on both) and fills those in when E-Tickets is enabled.
	 */
	public static function replace_vars_in_template( string $text, array $vars, string $original_text ): string {
		if ( empty( $vars['event_registration'] ) ) {
			return $text;
		}

		$reg   = $vars['event_registration'];
		$event = $reg->get_event();

		$needles      = [
			'{{event_name}}',
			'{{event_start_date}}',
			'{{event_start_time}}',
			'{{event_location_name}}',
			'{{event_organizer_name}}',
			'{{registration_code}}',
			'{{ticket_count}}',
		];
		$replacements = [
			$event ? esc_html( $event->name ) : '',
			$event ? $event->get_formatted_start_date() : '',
			$event ? $event->get_formatted_start_time() : '',
			$event ? esc_html( $event->get_display_location_name() ) : '',
			$event ? esc_html( $event->get_display_organizer_name() ) : '',
			esc_html( $reg->registration_code ),
			(string) (int) $reg->quantity,
		];

		return str_replace( $needles, $replacements, $text );
	}

	public static function output_available_vars(): void {
		?>
		<div class="available-vars-block">
			<h4><?php esc_html_e( 'Event', 'latepoint' ); ?></h4>
			<ul>
				<li><span class="var-label"><?php esc_html_e( 'Event Name', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{event_name}}</span></li>
				<li><span class="var-label"><?php esc_html_e( 'Event Start Date', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{event_start_date}}</span></li>
				<li><span class="var-label"><?php esc_html_e( 'Event Start Time', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{event_start_time}}</span></li>
				<li><span class="var-label"><?php esc_html_e( 'Event Location', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{event_location_name}}</span></li>
				<li><span class="var-label"><?php esc_html_e( 'Organizer', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{event_organizer_name}}</span></li>
				<li><span class="var-label"><?php esc_html_e( 'Registration Code', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{registration_code}}</span></li>
				<li><span class="var-label"><?php esc_html_e( 'Ticket Count', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{ticket_count}}</span></li>
				<?php do_action( 'latepoint_available_vars_event' ); ?>
			</ul>
		</div>
		<?php
	}

	public static function add_event_templates( array $templates, string $action_type, $wp_filesystem ): array {
		if ( $action_type !== 'send_email' ) {
			return $templates;
		}

		$templates[] = [
			'id'           => 'event_registration_created__to_customer',
			'to_user_type' => 'customer',
			'name'         => __( 'Event Registration Confirmation', 'latepoint' ),
			'to_email'     => '{{customer_full_name}} <{{customer_email}}>',
			'subject'      => __( 'Event Registration Confirmation', 'latepoint' ),
			'content'      => OsEmailHelper::get_email_layout( $wp_filesystem->get_contents( LATEPOINT_VIEWS_ABSPATH . 'mailers/customer/event_registration_created.html' ) ),
		];
		$templates[] = [
			'id'           => 'event_registration_cancelled__to_customer',
			'to_user_type' => 'customer',
			'name'         => __( 'Event Registration Cancelled', 'latepoint' ),
			'to_email'     => '{{customer_full_name}} <{{customer_email}}>',
			'subject'      => __( 'Event Registration Cancelled', 'latepoint' ),
			'content'      => OsEmailHelper::get_email_layout( $wp_filesystem->get_contents( LATEPOINT_VIEWS_ABSPATH . 'mailers/customer/event_registration_cancelled.html' ) ),
		];

		return $templates;
	}

	/**
	 * Returns "Registration Confirmed" as the confirmation banner title for event orders,
	 * but only when the admin has not customized the step setting.
	 *
	 * @param string       $title  Current title value.
	 * @param OsOrderModel $order  Current order.
	 *
	 * @return string
	 */
	public static function event_confirmation_message_title( string $title, OsOrderModel $order ): string {
		if ( ! self::order_contains_event( $order ) ) {
			return $title;
		}

		return esc_html__( 'Registration Confirmed', 'latepoint' );
	}

	/**
	 * Returns true when the given order contains at least one event_registration item.
	 *
	 * @param OsOrderModel $order Order to inspect.
	 *
	 * @return bool
	 */
	private static function order_contains_event( OsOrderModel $order ): bool {
		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item->variant === LATEPOINT_ITEM_VARIANT_EVENT_REGISTRATION ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Default "Ticket Types" upgrade teaser on the event edit form, shown when Pro isn't active.
	 */
	public static function output_ticket_types_upsell( OsEventModel $event ): void {
		?>
		<div class="white-box section-anchor" id="stickySectionTicketTypes">
			<div class="white-box-header">
				<div class="os-form-sub-header"><h3><?php esc_html_e( 'Ticket Types', 'latepoint' ); ?></h3></div>
			</div>
			<div class="white-box-content">
				<?php
				$description = __( 'Sell tiered tickets such as Early Bird, Regular, or VIP for this event, each with its own price and capacity.', 'latepoint' );
				include LATEPOINT_ABSPATH . 'lib/views/shared/pro_feature.php';
				?>
			</div>
		</div>
		<?php
	}
}
