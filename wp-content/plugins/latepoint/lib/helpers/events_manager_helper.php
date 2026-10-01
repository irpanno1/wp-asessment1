<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/**
 * Utility helper for Events — thin wrappers around common queries.
 */
class OsEventsManagerHelper {

	// ─── Queries ──────────────────────────────────────────────────────────

	public static function get_published_events( array $args = [] ): array {
		$query = ( new OsEventModel() )->should_be_published();

		if ( ! empty( $args['category_id'] ) ) {
			$query->where( [ 'category_id' => (int) $args['category_id'] ] );
		}

		$results = $query->order_by( 'start_datetime_utc asc' )->get_results_as_models();

		// Normalize: get_results_as_models() returns a single model (not an array) when limit == 1.
		if ( ! is_array( $results ) ) {
			$results = $results ? [ $results ] : [];
		}

		if ( ! empty( $args['limit'] ) ) {
			$results = array_slice( $results, 0, (int) $args['limit'] );
		}

		return $results;
	}

	// ─── Date-range tiles (admin form) ─────────────────────────────────────

	/**
	 * Render an event's saved OsEventDateModel rows as tiles, plus a trailing
	 * "Add" tile — the same tile/lightbox pattern as
	 * OsWorkPeriodsHelper::generate_days_with_custom_schedule() (Agent "Custom
	 * schedule"), reusing its .custom-day-work-period(.is-range)/.add-custom-day-w
	 * markup and CSS verbatim so a saved range looks and behaves exactly like a
	 * custom scheduled day/date-range elsewhere in the admin.
	 */
	public static function generate_event_date_range_tiles( int $event_id ): string {
		$event   = new OsEventModel( $event_id );
		$dates   = $event->get_dates();
		$tz_name = OsTimeHelper::get_wp_timezone_name();

		$html = '<div class="custom-day-work-periods">';

		foreach ( $dates as $date_range ) {
			// Genuinely stored as UTC — parse as UTC, then render in the site's timezone, same
			// convention as OsEventDateModel::get_formatted_date_range()/get_formatted_time_range().
			$start_date = OsTimeHelper::date_from_db( $date_range->start_datetime_utc, false, $tz_name );
			$end_date   = OsTimeHelper::date_from_db( $date_range->end_datetime_utc, false, $tz_name );
			$is_range   = $start_date->format( 'Y-m-d' ) !== $end_date->format( 'Y-m-d' );

			$edit_params   = OsUtilHelper::build_os_params(
				[
					'event_id'      => $event_id,
					'date_range_id' => $date_range->id,
				]
			);
			$remove_params = OsUtilHelper::build_os_params(
				[ 'id' => $date_range->id ],
				'remove_event_date_range_' . $date_range->id
			);

			$html .= '<div class="custom-day-work-period' . ( $is_range ? ' is-range' : '' ) . '">';
			$html .= '<a href="#" title="' . esc_attr__( 'Edit Date Range', 'latepoint' ) . '" class="edit-custom-day"'
				. ' data-os-action="' . esc_attr( OsRouterHelper::build_route_name( 'events_manager', 'event_date_range_form' ) ) . '"'
				. ' data-os-output-target="lightbox"'
				. ' data-os-lightbox-classes="width-700"'
				. ' data-os-after-call="latepoint_init_custom_day_schedule"'
				. ' data-os-params="' . esc_attr( $edit_params ) . '"><i class="latepoint-icon latepoint-icon-edit-3"></i></a>';
			$html .= '<a href="#" title="' . esc_attr__( 'Remove Date Range', 'latepoint' ) . '" class="remove-custom-day"'
				. ' data-os-pass-this="yes"'
				. ' data-os-after-call="latepoint_custom_day_removed"'
				. ' data-os-action="' . esc_attr( OsRouterHelper::build_route_name( 'events_manager', 'remove_event_date_range' ) ) . '"'
				. ' data-os-params="' . esc_attr( $remove_params ) . '"'
				. ' data-os-prompt="' . esc_attr__( 'Are you sure you want to remove this date range?', 'latepoint' ) . '"><i class="latepoint-icon latepoint-icon-trash-2"></i></a>';
			$html .= '<div class="custom-day-work-period-i">';
			if ( $is_range ) {
				$html .= '<div class="custom-day-number">' . esc_html( $start_date->format( 'd' ) . ' - ' . $end_date->format( 'd' ) ) . '</div>';
				if ( $start_date->format( 'n' ) !== $end_date->format( 'n' ) ) {
					$html .= '<div class="custom-day-month">' . esc_html( OsUtilHelper::get_month_name_by_number( (int) $start_date->format( 'n' ) ) . ' - ' . OsUtilHelper::get_month_name_by_number( (int) $end_date->format( 'n' ) ) ) . '</div>';
				} else {
					$html .= '<div class="custom-day-month">' . esc_html( OsUtilHelper::get_month_name_by_number( (int) $start_date->format( 'n' ) ) ) . '</div>';
				}
			} else {
				$html .= '<div class="custom-day-number">' . esc_html( $start_date->format( 'd' ) ) . '</div>';
				$html .= '<div class="custom-day-month">' . esc_html( OsUtilHelper::get_month_name_by_number( (int) $start_date->format( 'n' ) ) ) . '</div>';
			}
			$html .= '</div>';
			$html .= '<div class="custom-day-periods"><div class="custom-day-period">' . esc_html( $date_range->get_formatted_time_range() ) . '</div></div>';
			$html .= '</div>';
		}

		$add_params = OsUtilHelper::build_os_params( [ 'event_id' => $event_id ] );
		$html      .= '<a class="add-custom-day-w"'
			. ' data-os-action="' . esc_attr( OsRouterHelper::build_route_name( 'events_manager', 'event_date_range_form' ) ) . '"'
			. ' data-os-output-target="lightbox"'
			. ' data-os-lightbox-classes="width-700"'
			. ' data-os-after-call="latepoint_init_custom_day_schedule"'
			. ' data-os-params="' . esc_attr( $add_params ) . '">
                <div class="add-custom-day-i">
                  <div class="add-day-graphic-w"><div class="add-day-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div></div><div class="add-day-label">' . esc_html__( 'Add Date Range', 'latepoint' ) . '</div>
                </div>
              </a>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Convert a local wall-clock date + minutes-from-midnight (as entered by the admin, in the
	 * site's configured timezone) into a genuine UTC datetime string for storage. Same recipe as
	 * OsBookingModel::get_start_datetime() / OsOffPeriodModel::get_start_datetime() use for their
	 * own *_utc columns — anchor midnight local, add minutes, then shift to UTC.
	 */
	public static function utc_datetime_from_local( string $local_date, int $minutes ): string {
		$dt = new OsWpDateTime( $local_date . ' 00:00:00', OsTimeHelper::get_wp_timezone() );
		if ( $minutes > 0 ) {
			$dt->modify( '+' . $minutes . ' minutes' );
		}
		$dt->setTimezone( new DateTimeZone( 'UTC' ) );

		return $dt->format( LATEPOINT_DATETIME_DB_FORMAT );
	}

	/**
	 * Convert a genuine UTC datetime string back into its local wall-clock date + minutes-from-
	 * midnight, for repopulating admin form fields (date picker + time picker) on edit.
	 *
	 * @return array{date:string,minutes:int}
	 */
	public static function local_date_and_minutes_from_utc( string $utc_datetime ): array {
		$dt = OsTimeHelper::date_from_db( $utc_datetime, false, OsTimeHelper::get_wp_timezone_name() );

		return [
			'date'    => $dt->format( 'Y-m-d' ),
			'minutes' => OsTimeHelper::convert_datetime_to_minutes( $dt ),
		];
	}

	// ─── Cart item helpers ────────────────────────────────────────────────

	/**
	 * Build the item_data payload for an event_registration cart item.
	 *
	 * Both call sites that write this payload (initial add-to-cart and the live quantity
	 * update) go through here, so a filtered addon (e.g. Pro's Ticket Types) can attach extra
	 * keys — such as a per-type breakdown — in exactly one place instead of two.
	 *
	 * @param OsEventModel $event    The event being registered for.
	 * @param int          $quantity Number of tickets.
	 * @param array        $params   Raw request params for the current step, if any.
	 * @return array
	 */
	public static function build_cart_item_data( OsEventModel $event, int $quantity, array $params = [] ): array {
		/**
		 * Item data stored on an event_registration cart/order item.
		 *
		 * @param {array}        $item_data Base payload (event_id, quantity, event_name, event_price).
		 * @param {OsEventModel} $event     The event being registered for.
		 * @param {array}        $params    Raw request params for the current step.
		 * @returns {array} The filtered item data.
		 *
		 * @since 5.7.0
		 * @hook latepoint_event_cart_item_data
		 *
		 */
		return apply_filters(
			'latepoint_event_cart_item_data',
			[
				'event_id'    => $event->id,
				'quantity'    => $quantity,
				'event_name'  => $event->name,
				'event_price' => (float) $event->price,
			],
			$event,
			$params
		);
	}

	/**
	 * Decode item_data for an event_registration cart/order item.
	 *
	 * Always includes event_id/quantity/event_name (defaulted below), but addons (e.g. Pro's
	 * Ticket Types) can contribute additional keys via the latepoint_event_cart_item_data filter —
	 * intentionally typed as a plain map rather than a fixed shape so callers checking for those
	 * addon-contributed keys (e.g. array_key_exists('ticket_types', ...)) aren't flagged as
	 * always-false by static analysis.
	 *
	 * @param string $item_data JSON string.
	 * @return array<string, mixed>
	 */
	public static function decode_item_data( string $item_data ): array {
		$defaults = [
			'event_id'   => 0,
			'quantity'   => 1,
			'event_name' => '',
		];
		$data     = json_decode( $item_data, true );

		return is_array( $data ) ? array_merge( $defaults, $data ) : $defaults;
	}

	/**
	 * Real ticket count for an event_registration cart/order item. A per-type-selection addon
	 * (e.g. Pro's Ticket Types) always writes the `ticket_types` key once active for the event,
	 * even as an empty array — its presence alone is what tells "0 is a real quantity, nothing
	 * picked yet" apart from "flat event, default to 1".
	 */
	public static function resolve_item_quantity( string $item_data ): int {
		$data = self::decode_item_data( $item_data );

		return array_key_exists( 'ticket_types', $data )
			? max( 0, (int) $data['quantity'] )
			: max( 1, (int) $data['quantity'] );
	}

	/**
	 * Calculate total charge for an event_registration cart/order item.
	 */
	public static function calculate_item_amount( string $item_data ): float {
		$data     = self::decode_item_data( $item_data );
		$event_id = (int) $data['event_id'];
		$quantity = max( 1, (int) $data['quantity'] );

		if ( ! $event_id ) {
			return 0.0;
		}

		$event = new OsEventModel( $event_id );
		if ( ! $event->id ) {
			return 0.0;
		}

		return $event->should_multiply_price_by_tickets() ? (float) $event->price * $quantity : (float) $event->price;
	}
}
