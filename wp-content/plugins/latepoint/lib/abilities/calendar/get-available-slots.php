<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetAvailableSlots extends LatePointAbstractCalendarAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-available-slots';
		$this->label       = __( 'Get available slots', 'latepoint' );
		$this->description = __( 'Returns available booking time slots for a service/agent/location on a given date.', 'latepoint' );
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
				'service_id'  => [
					'type'        => 'integer',
					'description' => __( 'Service ID.', 'latepoint' ),
				],
				'date'        => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'Date to check (Y-m-d).', 'latepoint' ),
				],
				'agent_id'    => [ 'type' => 'integer' ],
				'location_id' => [ 'type' => 'integer' ],
				'duration'    => [ 'type' => 'integer' ],
			],
			'required'   => [ 'service_id', 'date' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slots' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'start_time' => [ 'type' => 'integer' ],
							'end_time'   => [ 'type' => 'integer' ],
							'agent_id'   => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	public function execute( array $args ) {
		$booking_request = $this->build_booking_request( $args );
		if ( is_wp_error( $booking_request ) ) {
			return $booking_request;
		}

		$slots = $this->get_bookable_slots( $booking_request );

		return [ 'slots' => apply_filters( 'latepoint_get_available_slots', $slots, $args ) ];
	}

	/**
	 * Bookable slots for the requested day, calculated the same way as in
	 * OsCalendarHelper::generate_single_month().
	 *
	 * @param \LatePoint\Misc\BookingRequest $booking_request
	 * @return array
	 */
	private function get_bookable_slots( \LatePoint\Misc\BookingRequest $booking_request ): array {
		if ( $this->is_requested_day_in_the_past( $booking_request ) ) {
			return [];
		}

		$requested_date = new OsWpDateTime( $booking_request->start_date );
		// clones, get_resources_grouped_by_day modifies the dates it receives
		$resources = OsResourceHelper::get_resources_grouped_by_day( $booking_request, clone $requested_date, clone $requested_date );

		$slots_by_time = [];
		foreach ( $resources[ $requested_date->format( 'Y-m-d' ) ] ?? [] as $resource ) {
			foreach ( $resource->slots as $slot ) {
				if ( ! $slot->can_accomodate( $booking_request->total_attendees ) ) {
					continue;
				}
				// an agent has one resource per location, list the slot once
				$slots_by_time[ $slot->start_time ][ $resource->agent_id ] = [
					'start_time' => (int) $slot->start_time,
					'end_time'   => (int) $slot->start_time + (int) $booking_request->duration,
					'agent_id'   => (int) $resource->agent_id,
				];
			}
		}
		ksort( $slots_by_time, SORT_NUMERIC );

		$slots = [];
		foreach ( $slots_by_time as $slots_for_time ) {
			foreach ( $slots_for_time as $slot_data ) {
				$slots[] = $slot_data;
			}
		}

		return $slots;
	}
}
