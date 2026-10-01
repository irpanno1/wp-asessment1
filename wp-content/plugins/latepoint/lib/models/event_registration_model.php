<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! class_exists( 'OsEventRegistrationModel' ) ) :

	class OsEventRegistrationModel extends OsModel {

		public $id;
		public $registration_code;
		public $event_id;
		public $customer_id;
		public $order_id;
		public $order_item_id;
		public $quantity;
		public $status;
		public $payment_status;
		public $notes;
		public $created_at;
		public $updated_at;

		public function __construct( $id = false ) {
			parent::__construct();
			$this->table_name = LATEPOINT_TABLE_EVENT_REGISTRATIONS;
			$this->nice_names = [
				'event_id'    => __( 'Event', 'latepoint' ),
				'customer_id' => __( 'Customer', 'latepoint' ),
			];

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

		public function get_customer(): ?OsCustomerModel {
			if ( ! isset( $this->customer ) ) {
				$this->customer = $this->customer_id ? new OsCustomerModel( $this->customer_id ) : null;
			}

			return $this->customer;
		}

		public function get_order(): ?OsOrderModel {
			if ( ! isset( $this->order ) ) {
				$this->order = $this->order_id ? new OsOrderModel( $this->order_id ) : null;
			}

			return $this->order;
		}

		// ─── Status helpers ────────────────────────────────────────────────

		public function is_confirmed(): bool {
			return $this->status === LATEPOINT_EVENT_REGISTRATION_STATUS_CONFIRMED;
		}

		public function is_cancelled(): bool {
			return $this->status === LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED;
		}

		public function is_checked_in(): bool {
			return $this->status === LATEPOINT_EVENT_REGISTRATION_STATUS_CHECKED_IN;
		}

		public function get_status_label(): string {
			$labels = [
				LATEPOINT_EVENT_REGISTRATION_STATUS_PENDING    => __( 'Pending', 'latepoint' ),
				LATEPOINT_EVENT_REGISTRATION_STATUS_CONFIRMED  => __( 'Confirmed', 'latepoint' ),
				LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED  => __( 'Cancelled', 'latepoint' ),
				LATEPOINT_EVENT_REGISTRATION_STATUS_CHECKED_IN => __( 'Checked In', 'latepoint' ),
			];

			return $labels[ $this->status ] ?? ucfirst( $this->status );
		}

		public function should_not_be_cancelled() {
			return $this->where( [ $this->table_name . '.status !=' => LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED ] );
		}

		// ─── Lifecycle ────────────────────────────────────────────────────

		protected function before_create() {
			if ( empty( $this->registration_code ) ) {
				$this->registration_code = strtoupper( wp_generate_password( 8, false ) );
			}
		}

		protected function set_defaults() {
			if ( empty( $this->status ) ) {
				$this->status = LATEPOINT_EVENT_REGISTRATION_STATUS_PENDING;
			}
			if ( empty( $this->payment_status ) ) {
				$this->payment_status = 'not_paid';
			}
			if ( empty( $this->quantity ) ) {
				$this->quantity = 1;
			}
		}

		// ─── Mass-assignment ──────────────────────────────────────────────

		protected function allowed_params( $role = 'admin' ) {
			return [
				'id',
				'registration_code',
				'event_id',
				'customer_id',
				'order_id',
				'order_item_id',
				'quantity',
				'status',
				'payment_status',
				'notes',
			];
		}

		protected function params_to_save( $role = 'admin' ) {
			return [
				'id',
				'registration_code',
				'event_id',
				'customer_id',
				'order_id',
				'order_item_id',
				'quantity',
				'status',
				'payment_status',
				'notes',
			];
		}

		protected function properties_to_validate() {
			return [
				'event_id' => [ 'presence' ],
			];
		}
	}

endif;
