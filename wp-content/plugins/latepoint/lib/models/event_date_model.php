<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! class_exists( 'OsEventDateModel' ) ) :

	/**
	 * One row of a repeatable date/time range belonging to an OsEventModel.
	 * e.g. an event may have "Aug 1-5, 9am-5pm" as one row and "Aug 6, 9am-1pm" as another.
	 */
	class OsEventDateModel extends OsModel {

		public $id;
		public $event_id;
		public $start_datetime_utc;
		public $end_datetime_utc;
		public $order_number;
		public $created_at;
		public $updated_at;

		public function __construct( $id = false ) {
			parent::__construct();
			$this->table_name = LATEPOINT_TABLE_EVENT_DATES;
			$this->nice_names = [];

			if ( $id ) {
				$this->load_by_id( $id );
			}
		}

		// ─── Relationship getters ──────────────────────────────────────────

		public function get_event(): ?OsEventModel {
			if ( ! isset( $this->event ) ) {
				$this->event = $this->event_id ? new OsEventModel( $this->event_id ) : null;
			}

			return $this->event;
		}

		// ─── Display helpers ───────────────────────────────────────────────

		/**
		 * "Aug 1 - 5, 2026" for a multi-day range, or "Aug 6, 2026" when start/end fall on the same day.
		 * Uses the readable date format (not the site's flat numeric setting) so this reads
		 * naturally to a customer, same convention as OsSettingsHelper::get_readable_date_format()'s
		 * other callers — still falls back to the numeric format if the admin disabled verbose dates.
		 */
		public function get_formatted_date_range(): string {
			if ( ! $this->start_datetime_utc ) {
				return '';
			}
			$date_format = OsSettingsHelper::get_readable_date_format();
			$tz_name     = OsTimeHelper::get_wp_timezone_name();
			$start_date  = OsUtilHelper::translate_months( OsTimeHelper::date_from_db( $this->start_datetime_utc, $date_format, $tz_name ) );

			if ( ! $this->end_datetime_utc ) {
				return $start_date;
			}

			$end_date = OsUtilHelper::translate_months( OsTimeHelper::date_from_db( $this->end_datetime_utc, $date_format, $tz_name ) );

			if ( $end_date === $start_date ) {
				return $start_date;
			}

			return $start_date . ' - ' . $end_date;
		}

		/**
		 * "9:00 am - 5:00 pm" for the shared time window of this date range.
		 */
		public function get_formatted_time_range(): string {
			if ( ! $this->start_datetime_utc ) {
				return '';
			}
			$time_format = get_option( 'time_format', 'g:i a' );
			$tz_name     = OsTimeHelper::get_wp_timezone_name();
			$start_time  = OsTimeHelper::date_from_db( $this->start_datetime_utc, $time_format, $tz_name );

			if ( ! $this->end_datetime_utc ) {
				return $start_time;
			}

			$end_time = OsTimeHelper::date_from_db( $this->end_datetime_utc, $time_format, $tz_name );

			return $start_time . ' - ' . $end_time;
		}

		// ─── Lifecycle ────────────────────────────────────────────────────

		protected function set_defaults() {
			if ( ! isset( $this->order_number ) || $this->order_number === null ) {
				$this->order_number = 0;
			}
		}

		// ─── Mass-assignment ──────────────────────────────────────────────

		protected function allowed_params( $role = 'admin' ) {
			return [
				'id',
				'event_id',
				'start_datetime_utc',
				'end_datetime_utc',
				'order_number',
			];
		}

		protected function params_to_save( $role = 'admin' ) {
			return [
				'id',
				'event_id',
				'start_datetime_utc',
				'end_datetime_utc',
				'order_number',
			];
		}

		protected function properties_to_validate() {
			return [
				'event_id' => [ 'presence' ],
			];
		}
	}

endif;
