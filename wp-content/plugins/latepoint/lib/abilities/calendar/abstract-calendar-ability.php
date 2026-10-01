<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractCalendarAbility extends LatePointAbstractAbility {

	/**
	 * @param array $args
	 * @return \LatePoint\Misc\BookingRequest|\WP_Error
	 */
	protected function build_booking_request( array $args ) {
		// not absint, zero means ANY service to OsConnectorHelper and a negative would be flipped into a valid id
		$service_id = (int) ( $args['service_id'] ?? 0 );
		if ( $service_id <= 0 ) {
			return new WP_Error( 'invalid_service_id', __( 'A valid service ID is required.', 'latepoint' ), [ 'status' => 400 ] );
		}

		// abilities api validates that date is a string, but not its format
		$requested_date = sanitize_text_field( $args['date'] ?? '' );
		$date_object    = OsWpDateTime::os_createFromFormat( 'Y-m-d', $requested_date );
		if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $requested_date ) {
			return new WP_Error( 'invalid_date', __( 'Date has to be in Y-m-d format.', 'latepoint' ), [ 'status' => 400 ] );
		}

		$booking              = new OsBookingModel();
		$booking->service_id  = $service_id;
		$booking->agent_id    = empty( $args['agent_id'] ) ? LATEPOINT_ANY_AGENT : absint( $args['agent_id'] );
		$booking->location_id = empty( $args['location_id'] ) ? LATEPOINT_ANY_LOCATION : absint( $args['location_id'] );
		$booking->start_date  = $requested_date;
		$booking->start_time  = absint( $args['start_time'] ?? 0 );
		// stays zero when not requested, get_total_duration() then falls back to the service duration
		$booking->duration = absint( $args['duration'] ?? 0 );
		$booking->set_buffers();
		$booking->calculate_end_date_and_time();

		return \LatePoint\Misc\BookingRequest::create_from_booking_model( $booking );
	}

	/**
	 * Blocked periods are only generated from today onwards, the booking form excludes past days by
	 * clamping its date range to "now" in OsCalendarHelper::generate_single_month().
	 *
	 * @param \LatePoint\Misc\BookingRequest $booking_request
	 * @return bool
	 */
	protected function is_requested_day_in_the_past( \LatePoint\Misc\BookingRequest $booking_request ): bool {
		return $booking_request->start_date < OsTimeHelper::now_datetime_object()->format( 'Y-m-d' );
	}

	protected function serialize_off_period( OsOffPeriodModel $p ): array {
		return [
			'id'         => (int) $p->id,
			'name'       => $p->summary ?? '',
			'start_date' => $p->start_date ?? '',
			'end_date'   => $p->end_date ?? '',
			'agent_id'   => (int) $p->agent_id,
		];
	}

	protected function serialize_work_period( OsWorkPeriodModel $w ): array {
		return [
			'id'         => (int) $w->id,
			'weekday'    => (int) $w->week_day,
			'start_time' => (int) $w->start_time,
			'end_time'   => (int) $w->end_time,
			'agent_id'   => (int) $w->agent_id,
		];
	}

	protected function off_period_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'         => [ 'type' => 'integer' ],
				'name'       => [ 'type' => 'string' ],
				'start_date' => [ 'type' => 'string' ],
				'end_date'   => [ 'type' => 'string' ],
				'agent_id'   => [ 'type' => 'integer' ],
			],
		];
	}
}
