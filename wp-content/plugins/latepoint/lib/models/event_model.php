<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! class_exists( 'OsEventModel' ) ) :

	class OsEventModel extends OsModel {

		public $id;
		public $name;
		public $slug;
		public $summary;
		public $description;
		public $featured_image_id;
		public $category_id;
		public $location_id;
		public $agent_id;
		public $event_type;
		public $online_url;
		public $start_datetime_utc;
		public $end_datetime_utc;
		public $timezone;
		public $capacity;
		public $price;
		public $registration_start_utc;
		public $registration_end_utc;
		public $status;
		public $visibility;
		public $order_number;
		public $created_at;
		public $updated_at;

		public function __construct( $id = false ) {
			parent::__construct();
			$this->table_name = LATEPOINT_TABLE_EVENTS;
			$this->meta_class = 'OsEventMetaModel';
			$this->nice_names = [ 'name' => __( 'Name', 'latepoint' ) ];

			if ( $id ) {
				$this->load_by_id( $id );
			}
		}

		// ─── Meta accessors ──────────────────────────────────────────────────

		public function get_meta_by_key( $meta_key, $default = false ) {
			if ( $this->is_new_record() ) {
				return $default;
			}

			$meta = new OsEventMetaModel();

			return $meta->get_by_key( $meta_key, $this->id, $default );
		}

		public function save_meta_by_key( $meta_key, $meta_value ) {
			if ( $this->is_new_record() ) {
				return false;
			}

			$meta = new OsEventMetaModel();

			return $meta->save_by_key( $meta_key, $meta_value, $this->id );
		}

		// ─── Relationship getters ──────────────────────────────────────────

		public function get_category(): ?OsEventCategoryModel {
			if ( ! isset( $this->category ) ) {
				$this->category = $this->category_id ? new OsEventCategoryModel( $this->category_id ) : null;
			}

			return $this->category;
		}

		public function get_location(): ?OsLocationModel {
			if ( ! isset( $this->location ) ) {
				$this->location = $this->location_id ? new OsLocationModel( $this->location_id ) : null;
			}

			return $this->location;
		}

		public function get_agent(): ?OsAgentModel {
			if ( ! isset( $this->agent ) ) {
				$this->agent = $this->agent_id ? new OsAgentModel( $this->agent_id ) : null;
			}

			return $this->agent;
		}

		/**
		 * Location display value while the Location select is temporarily replaced by a plain
		 * text field (location_text meta) — falls back to the real relation once repopulated.
		 */
		public function get_display_location_name(): string {
			$location_text = $this->get_meta_by_key( 'location_text', '' );
			if ( $location_text ) {
				return $location_text;
			}
			$location = $this->get_location();

			return $location ? $location->name : '';
		}

		public function get_display_location_address(): string {
			if ( $this->get_meta_by_key( 'location_text', '' ) ) {
				return '';
			}
			$location = $this->get_location();

			return $location ? $location->full_address : '';
		}

		/**
		 * Organizer display value while the Organizer select is temporarily replaced by a plain
		 * text field (organizer_name meta) — falls back to the real relation once repopulated.
		 */
		public function get_display_organizer_name(): string {
			$organizer_name = $this->get_meta_by_key( 'organizer_name', '' );
			if ( $organizer_name ) {
				return $organizer_name;
			}
			$agent = $this->get_agent();

			return $agent ? $agent->full_name : '';
		}

		public function get_registrations(): array {
			if ( ! isset( $this->registrations ) ) {
				$reg                 = new OsEventRegistrationModel();
				$this->registrations = $reg->where( [ 'event_id' => $this->id ] )->get_results_as_models();
			}

			return $this->registrations;
		}

		/**
		 * All date/time ranges for this event's schedule, ordered earliest first.
		 */
		public function get_dates(): array {
			if ( ! isset( $this->dates ) ) {
				$date_model  = new OsEventDateModel();
				$this->dates = $date_model->where( [ 'event_id' => $this->id ] )->order_by( 'start_datetime_utc asc' )->get_results_as_models();
			}

			return $this->dates;
		}

		// ─── Capacity helpers ──────────────────────────────────────────────

		/**
		 * Sum of all non-cancelled registration quantities for this event. Memoized on the
		 * instance — call sites like the front-end detail page compute this more than once per
		 * request (available capacity, then is_sold_out()), which otherwise re-runs the same query.
		 */
		public function get_booked_capacity(): int {
			if ( isset( $this->booked_capacity ) ) {
				return $this->booked_capacity;
			}
			if ( ! $this->id ) {
				return 0;
			}
			global $wpdb;
			$total = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT SUM(quantity) FROM ' . LATEPOINT_TABLE_EVENT_REGISTRATIONS .
					' WHERE event_id = %d AND status NOT IN (%s)',
					$this->id,
					LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED
				)
			);

			$this->booked_capacity = (int) $total;

			return $this->booked_capacity;
		}

		/**
		 * Batch version of get_booked_capacity() for list/index views that would otherwise run
		 * one SUM query per event in a loop. Returns event_id => booked quantity; events with no
		 * registrations at all are simply absent from the result (treat a missing key as 0).
		 *
		 * @param int[] $event_ids
		 * @return array<int, int>
		 */
		public static function get_booked_capacities_for_event_ids( array $event_ids ): array {
			$event_ids = array_filter( array_map( 'absint', $event_ids ) );
			if ( ! $event_ids ) {
				return [];
			}
			global $wpdb;
			$ids_placeholder = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
			$query_args      = $event_ids;
			$query_args[]    = LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED;
			$rows            = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT event_id, SUM(quantity) as booked FROM ' . LATEPOINT_TABLE_EVENT_REGISTRATIONS .
					" WHERE event_id IN ({$ids_placeholder}) AND status NOT IN (%s) GROUP BY event_id",
					$query_args
				)
			);

			$capacities = [];
			foreach ( $rows as $row ) {
				$capacities[ (int) $row->event_id ] = (int) $row->booked;
			}

			return $capacities;
		}

		public function get_available_capacity(): int {
			$available = $this->capacity ? max( 0, (int) $this->capacity - $this->get_booked_capacity() ) : PHP_INT_MAX;

			/**
			 * Filters the event's available capacity — lets an addon (e.g. Pro's Ticket Types)
			 * override this with capacity tracked outside the event's own `capacity` column.
			 *
			 * @param {int}          $available Available capacity computed from the event's own column.
			 * @param {OsEventModel} $event     The event.
			 * @returns {int} The filtered available capacity.
			 *
			 * @since 5.7.0
			 * @hook latepoint_event_available_capacity
			 *
			 */
			return apply_filters( 'latepoint_event_available_capacity', $available, $this );
		}

		/**
		 * Whether this event has any capacity limit at all — false ("unlimited") by default when
		 * `capacity` is 0, filterable so an addon can override it with a limit tracked elsewhere.
		 */
		public function has_capacity_limit(): bool {
			$has_limit = (bool) $this->capacity;

			/**
			 * Filters whether the event has any capacity limit to report.
			 *
			 * @param {bool}         $has_limit Whether the event's own `capacity` column is set.
			 * @param {OsEventModel} $event     The event.
			 * @returns {bool} The filtered capacity-limit flag.
			 *
			 * @since 5.7.0
			 * @hook latepoint_event_has_capacity_limit
			 *
			 */
			return apply_filters( 'latepoint_event_has_capacity_limit', $has_limit, $this );
		}

		/**
		 * The event's total capacity (not remaining) — filterable so an addon can report a total
		 * tracked outside the event's own `capacity` column. Only meaningful when
		 * has_capacity_limit() is true; callers should check that first.
		 */
		public function get_total_capacity(): int {
			$total = (int) $this->capacity;

			/**
			 * Filters the event's total capacity.
			 *
			 * @param {int}          $total Total capacity from the event's own column.
			 * @param {OsEventModel} $event The event.
			 * @returns {int} The filtered total capacity.
			 *
			 * @since 5.7.0
			 * @hook latepoint_event_total_capacity
			 *
			 */
			return apply_filters( 'latepoint_event_total_capacity', $total, $this );
		}

		/**
		 * Whether the wizard should ask the customer how many tickets they want.
		 * Mirrors OsServiceModel::should_show_capacity_selector() for group services.
		 */
		public function should_show_ticket_selector(): bool {
			return ! OsUtilHelper::is_on( $this->get_meta_by_key( 'dont_ask_ticket_quantity', 'off' ) );
		}

		/**
		 * Whether the ticket price is charged per ticket (default) or once, flat, per registration.
		 */
		public function should_multiply_price_by_tickets(): bool {
			return ! OsUtilHelper::is_on( $this->get_meta_by_key( 'dont_multiply_charge_amount_by_tickets', 'off' ) );
		}

		public function is_sold_out(): bool {
			if ( ! $this->has_capacity_limit() ) {
				return false;
			}

			return $this->get_available_capacity() <= 0;
		}

		public function is_registration_open(): bool {
			if ( $this->status !== LATEPOINT_EVENT_STATUS_PUBLISHED ) {
				return false;
			}

			$now = current_time( 'timestamp', true );

			if ( $this->registration_start_utc ) {
				$reg_start = strtotime( $this->registration_start_utc . ' UTC' );
				// Guard: skip MySQL zero-dates (0000-00-00) which parse to false or negative timestamps
				if ( $reg_start > 0 && $now < $reg_start ) {
					return false;
				}
			}

			$has_custom_reg_end = false;
			if ( $this->registration_end_utc ) {
				$reg_end = strtotime( $this->registration_end_utc . ' UTC' );
				// Guard: skip MySQL zero-dates (0000-00-00) which parse to false or negative timestamps
				if ( $reg_end > 0 ) {
					$has_custom_reg_end = true;
					if ( $now > $reg_end ) {
						return false;
					}
				}
			}

			// No custom close date set — default behavior: registration closes when the event starts.
			if ( ! $has_custom_reg_end && $this->start_datetime_utc ) {
				$event_start = strtotime( $this->start_datetime_utc . ' UTC' );
				if ( $event_start > 0 && $now > $event_start ) {
					return false;
				}
			}

			return ! $this->is_sold_out();
		}

		public function is_paid(): bool {
			return (float) $this->price > 0;
		}

		// ─── Display helpers ───────────────────────────────────────────────

		public function get_formatted_price(): string {
			if ( $this->is_paid() ) {
				return OsMoneyHelper::format_price( $this->price, true, false );
			}

			return __( 'Free', 'latepoint' );
		}

		public function get_featured_image_url( string $size = 'full' ): string {
			$default = LATEPOINT_IMAGES_URL . 'service-image.png';

			return OsImageHelper::get_image_url_by_id( $this->featured_image_id, $size, $default );
		}

		public function get_formatted_start_date(): string {
			if ( ! $this->start_datetime_utc ) {
				return '';
			}

			return OsTimeHelper::date_from_db( $this->start_datetime_utc, OsSettingsHelper::get_date_format(), OsTimeHelper::get_wp_timezone_name() );
		}

		public function get_formatted_end_date(): string {
			if ( ! $this->end_datetime_utc ) {
				return '';
			}

			return OsTimeHelper::date_from_db( $this->end_datetime_utc, OsSettingsHelper::get_date_format(), OsTimeHelper::get_wp_timezone_name() );
		}

		public function get_formatted_start_time(): string {
			if ( ! $this->start_datetime_utc ) {
				return '';
			}

			return OsTimeHelper::date_from_db( $this->start_datetime_utc, get_option( 'time_format', 'g:i a' ), OsTimeHelper::get_wp_timezone_name() );
		}

		public function get_formatted_end_time(): string {
			if ( ! $this->end_datetime_utc ) {
				return '';
			}

			return OsTimeHelper::date_from_db( $this->end_datetime_utc, get_option( 'time_format', 'g:i a' ), OsTimeHelper::get_wp_timezone_name() );
		}

		public function should_be_published() {
			return $this->where( [ 'status' => LATEPOINT_EVENT_STATUS_PUBLISHED ] );
		}

		// ─── Lifecycle ────────────────────────────────────────────────────

		protected function set_defaults() {
			if ( empty( $this->status ) ) {
				$this->status = LATEPOINT_EVENT_STATUS_DRAFT;
			}
			if ( empty( $this->event_type ) ) {
				$this->event_type = LATEPOINT_EVENT_TYPE_IN_PERSON;
			}
			if ( empty( $this->visibility ) ) {
				$this->visibility = 'public';
			}
			if ( ! isset( $this->order_number ) || $this->order_number === null ) {
				$this->order_number = 0;
			}
			if ( ! isset( $this->capacity ) || $this->capacity === null ) {
				$this->capacity = 0;
			}
			if ( ! isset( $this->price ) || $this->price === null ) {
				$this->price = 0;
			}
		}

		protected function before_save() {
			if ( empty( $this->slug ) && ! empty( $this->name ) ) {
				$this->slug = sanitize_title( $this->name );
			}
		}

		// ─── Mass-assignment ──────────────────────────────────────────────

		protected function allowed_params( $role = 'admin' ) {
			return [
				'id',
				'name',
				'slug',
				'summary',
				'description',
				'featured_image_id',
				'category_id',
				'location_id',
				'agent_id',
				'event_type',
				'online_url',
				'start_datetime_utc',
				'end_datetime_utc',
				'timezone',
				'capacity',
				'price',
				'registration_start_utc',
				'registration_end_utc',
				'status',
				'visibility',
				'order_number',
			];
		}

		protected function params_to_save( $role = 'admin' ) {
			return [
				'id',
				'name',
				'slug',
				'summary',
				'description',
				'featured_image_id',
				'category_id',
				'location_id',
				'agent_id',
				'event_type',
				'online_url',
				'start_datetime_utc',
				'end_datetime_utc',
				'timezone',
				'capacity',
				'price',
				'registration_start_utc',
				'registration_end_utc',
				'status',
				'visibility',
				'order_number',
			];
		}

		protected function params_to_sanitize() {
			return [
				'price'    => 'money',
				'capacity' => 'integer',
			];
		}

		protected function properties_to_validate() {
			return [
				'name' => [ 'presence' ],
			];
		}
	}

endif;
