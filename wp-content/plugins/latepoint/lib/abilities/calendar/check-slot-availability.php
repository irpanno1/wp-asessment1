<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCheckSlotAvailability extends LatePointAbstractCalendarAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/check-slot-availability';
		$this->label       = __( 'Check slot availability', 'latepoint' );
		$this->description = __( 'Checks whether a specific time slot can be booked, using the same availability rules as the booking form.', 'latepoint' );
		$this->permission  = '';
		$this->read_only   = true;
	}

	public function check_permission(): bool {
		return true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'service_id'  => [ 'type' => 'integer' ],
				'date'        => [
					'type'   => 'string',
					'format' => 'date',
				],
				'start_time'  => [
					'type'        => 'integer',
					'description' => __( 'Start time (minutes from midnight).', 'latepoint' ),
				],
				'agent_id'    => [ 'type' => 'integer' ],
				'location_id' => [ 'type' => 'integer' ],
			],
			'required'   => [ 'service_id', 'date', 'start_time' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'available' => [ 'type' => 'boolean' ],
				'conflicts' => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
			],
		];
	}

	public function execute( array $args ) {
		$booking_request = $this->build_booking_request( $args );
		if ( is_wp_error( $booking_request ) ) {
			return $booking_request;
		}

		$available = ! $this->is_requested_day_in_the_past( $booking_request )
					&& OsBookingHelper::is_booking_request_available( $booking_request );

		return [
			'available' => $available,
			'conflicts' => $this->get_conflicting_bookings( $booking_request ),
		];
	}

	/**
	 * Appointments that overlap the requested slot.
	 *
	 * @param \LatePoint\Misc\BookingRequest $booking_request
	 * @return array
	 */
	private function get_conflicting_bookings( \LatePoint\Misc\BookingRequest $booking_request ): array {
		// availability is public information, appointment records are not
		if ( ! OsRolesHelper::can_user( 'booking__view' ) ) {
			return [];
		}

		$filter = new \LatePoint\Misc\Filter(
			[
				'date_from'   => $booking_request->start_date,
				'service_id'  => $booking_request->service_id,
				'agent_id'    => $booking_request->agent_id,
				'location_id' => $booking_request->location_id,
				'statuses'    => OsBookingHelper::get_timeslot_blocking_statuses(),
			]
		);
		$filter = OsRolesHelper::filter_allowed_records_from_arguments_or_filter( $filter );

		// same collision window build_bookable_slots() uses, end is derived from the duration because
		// calculate_end_time() wraps it around midnight
		$slot_start_time = $booking_request->get_start_time_with_buffer();
		$slot_end_time   = $booking_request->start_time + $booking_request->duration + $booking_request->buffer_after;

		$conflicts = [];
		foreach ( OsBookingHelper::get_bookings( $filter, true ) as $booking ) {
			$is_overlapping = OsBookingHelper::is_period_overlapping(
				$slot_start_time,
				$slot_end_time,
				(int) $booking->start_time - (int) $booking->buffer_before,
				(int) $booking->end_time + (int) $booking->buffer_after
			);
			if ( $is_overlapping ) {
				$conflicts[] = [
					'id'          => (int) $booking->id,
					'service_id'  => (int) $booking->service_id,
					'agent_id'    => (int) $booking->agent_id,
					'location_id' => (int) $booking->location_id,
					'start_time'  => (int) $booking->start_time,
					'end_time'    => (int) $booking->end_time,
					'status'      => $booking->status,
				];
			}
		}

		return $conflicts;
	}
}
