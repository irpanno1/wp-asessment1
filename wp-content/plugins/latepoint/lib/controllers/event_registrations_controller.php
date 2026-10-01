<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OsEventRegistrationsController' ) ) :

	class OsEventRegistrationsController extends OsController {

		public function __construct() {
			parent::__construct();

			$this->views_folder            = LATEPOINT_VIEWS_ABSPATH . 'event_registrations/';
			$this->vars['page_header']     = OsMenuHelper::get_menu_items_by_id( 'events' );
			$this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id( 'events' );
			$this->vars['breadcrumbs'][]   = [
				'label' => __( 'Event Registrations', 'latepoint' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'event_registrations', 'index' ) ),
			];
		}

		// ─── Index ────────────────────────────────────────────────────────

		public function index() {
			$page_number = isset( $this->params['page_number'] ) ? (int) $this->params['page_number'] : 1;
			$per_page    = OsSettingsHelper::get_number_of_records_per_page();
			$offset      = ( $page_number > 1 ) ? ( ( $page_number - 1 ) * $per_page ) : 0;

			$reg_query  = new OsEventRegistrationModel();
			$query_args = [];

			$filter = isset( $this->params['filter'] ) ? $this->params['filter'] : false;

			// TABLE SEARCH FILTERS
			if ( $filter ) {
				if ( ! empty( $filter['id'] ) && filter_var( $filter['id'], FILTER_VALIDATE_INT ) ) {
					$query_args['id'] = (int) $filter['id'];
				}
				if ( ! empty( $filter['registration_code'] ) ) {
					$code_search                           = sanitize_text_field( $filter['registration_code'] );
					$query_args['registration_code LIKE']  = '%' . $code_search . '%';
					$this->vars['registration_code_query'] = $code_search;
				}
				if ( ! empty( $filter['event_id'] ) && filter_var( $filter['event_id'], FILTER_VALIDATE_INT ) ) {
					$query_args['event_id'] = (int) $filter['event_id'];
				}
				$allowed_statuses = [
					LATEPOINT_EVENT_REGISTRATION_STATUS_PENDING,
					LATEPOINT_EVENT_REGISTRATION_STATUS_CONFIRMED,
					LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED,
					LATEPOINT_EVENT_REGISTRATION_STATUS_CHECKED_IN,
				];
				if ( ! empty( $filter['status'] ) && in_array( $filter['status'], $allowed_statuses, true ) ) {
					$query_args['status'] = sanitize_text_field( $filter['status'] );
				}
				if ( ! empty( $filter['registration_date_from'] ) && ! empty( $filter['registration_date_to'] ) ) {
					$query_args[ LATEPOINT_TABLE_EVENT_REGISTRATIONS . '.created_at >=' ] = sanitize_text_field( $filter['registration_date_from'] );
					$query_args[ LATEPOINT_TABLE_EVENT_REGISTRATIONS . '.created_at <=' ] = sanitize_text_field( $filter['registration_date_to'] );
				}
				if ( ! empty( $filter['customer'] ) || ! empty( $filter['email'] ) ) {
					$customer_lookup = new OsCustomerModel();
					$customer_args   = [];
					if ( ! empty( $filter['customer'] ) ) {
						$name_search                  = sanitize_text_field( $filter['customer'] );
						$this->vars['customer_query'] = $name_search;
						$customer_args['CONCAT (first_name, " ", last_name) LIKE '] = '%' . $name_search . '%';
					}
					if ( ! empty( $filter['email'] ) ) {
						$email_search                = sanitize_text_field( $filter['email'] );
						$this->vars['email_query']   = $email_search;
						$customer_args['email LIKE'] = '%' . $email_search . '%';
					}
					$matching_customers = $customer_lookup->where( $customer_args )->get_results_as_models();
					if ( $matching_customers ) {
						$customer_ids = array_map( function ( $c ) { return $c->id; }, $matching_customers );
						$reg_query->where_in( 'customer_id', $customer_ids );
					} else {
						// No matching customers — force empty result set.
						$query_args['id'] = -1;
					}
				}
			}

			if ( ! empty( $query_args ) ) {
				$reg_query->where( $query_args );
			}

			// OUTPUT CSV IF REQUESTED
			if ( isset( $this->params['download'] ) && 'csv' === $this->params['download'] ) {
				$this->check_nonce( 'event_registrations_csv_export' );

				$csv_filename = 'event_registrations_' . OsUtilHelper::random_text();
				header( 'Content-Type: text/csv' );
				header( "Content-Disposition: attachment; filename={$csv_filename}.csv" );

				$labels_row = [
					__( 'ID', 'latepoint' ),
					__( 'Code', 'latepoint' ),
					__( 'Event', 'latepoint' ),
					__( 'Customer Name', 'latepoint' ),
					__( 'Customer Email', 'latepoint' ),
					__( 'Quantity', 'latepoint' ),
					__( 'Status', 'latepoint' ),
					__( 'Payment Status', 'latepoint' ),
					__( 'Registered On', 'latepoint' ),
				];

				$registrations_data   = [];
				$registrations_data[] = $labels_row;

				$all_registrations = $reg_query->order_by( 'created_at desc' )->get_results_as_models();
				if ( $all_registrations ) {
					// Batch-load customers/events instead of one query per registration per relation
					// (this export has no page limit, so N can be the full table).
					$customer_ids = array_unique( array_filter( array_map( function ( $reg ) { return $reg->customer_id; }, $all_registrations ) ) );
					$event_ids    = array_unique( array_filter( array_map( function ( $reg ) { return $reg->event_id; }, $all_registrations ) ) );

					$customers_by_id = [];
					if ( $customer_ids ) {
						foreach ( ( new OsCustomerModel() )->where_in( 'id', $customer_ids )->get_results_as_models() as $customer ) {
							$customers_by_id[ $customer->id ] = $customer;
						}
					}
					$events_by_id = [];
					if ( $event_ids ) {
						foreach ( ( new OsEventModel() )->where_in( 'id', $event_ids )->get_results_as_models() as $event ) {
							$events_by_id[ $event->id ] = $event;
						}
					}

					foreach ( $all_registrations as $reg ) {
						$customer             = $customers_by_id[ $reg->customer_id ] ?? null;
						$event                = $events_by_id[ $reg->event_id ] ?? null;
						$registrations_data[] = [
							$reg->id,
							$reg->registration_code,
							$event ? $event->name : '',
							$customer ? $customer->full_name : '',
							$customer ? $customer->email : '',
							$reg->quantity,
							$reg->get_status_label(),
							$reg->payment_status,
							$reg->created_at,
						];
					}
				}

				OsCSVHelper::array_to_csv( $registrations_data );
				return;
			}

			// SUMMARY AGGREGATES (before pagination limit)
			$count_clone   = clone $reg_query;
			$total_records = $count_clone->count();

			$confirmed_clone = clone $reg_query;
			$total_confirmed = $confirmed_clone->where( [ 'status' => LATEPOINT_EVENT_REGISTRATION_STATUS_CONFIRMED ] )->count();

			$sum_clone       = clone $reg_query;
			$sum_result      = $sum_clone->clear_select()->clear_group_by()->clear_having()->select( 'COALESCE(SUM(' . LATEPOINT_TABLE_EVENT_REGISTRATIONS . '.quantity), 0) as total_qty' )->set_limit( 1 )->get_results();
			$total_attendees = $sum_result ? (int) $sum_result->total_qty : 0;

			$total_pages = (int) ceil( $total_records / $per_page );

			$this->vars['showing_from'] = ( ( $page_number - 1 ) * $per_page ) ? ( ( $page_number - 1 ) * $per_page ) : 1;
			$this->vars['showing_to']   = min( $page_number * $per_page, $total_records );

			$this->vars['registrations']       = $reg_query->set_limit( $per_page )->set_offset( $offset )->order_by( 'created_at desc' )->get_results_as_models();
			$this->vars['events']              = ( new OsEventModel() )->order_by( 'start_datetime_utc asc' )->get_results_as_models();
			$this->vars['total_pages']         = $total_pages;
			$this->vars['current_page_number'] = $page_number;
			$this->vars['total_records']       = $total_records;
			$this->vars['total_attendees']     = $total_attendees;
			$this->vars['total_confirmed']     = $total_confirmed;

			$this->format_render(
				[
					'json_view_name' => '_table_body',
					'html_view_name' => __FUNCTION__,
				],
				[],
				[
					'total_pages'     => $total_pages,
					'showing_from'    => $this->vars['showing_from'],
					'showing_to'      => $this->vars['showing_to'],
					'total_records'   => $total_records,
					'total_attendees' => $total_attendees,
					'total_confirmed' => $total_confirmed,
				]
			);
		}

		// ─── Check-in ────────────────────────────────────────────────────
		// Commented out — this rolled up a whole registration to "checked_in" in one shot,
		// but never touched Pro's per-attendee ticket rows (OsFeatureEventTicketsHelper). With
		// E-Tickets active, an attendee checked in here could still have their QR ticket scanned
		// as a fresh, valid admission afterwards, since only Pro's scanner writes ticket status.
		// Ticket-level scanning is the real check-in path now; kept intact (not deleted) in case
		// a synced version is built later.

		// public function check_in() {
		// 	if ( empty( $this->params['id'] ) || ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
		// 		$this->send_json(
		// 			[
		// 				'status'  => LATEPOINT_STATUS_ERROR,
		// 				'message' => __( 'Invalid Registration ID', 'latepoint' ),
		// 			]
		// 		);
		// 		return;
		// 	}
		//
		// 	if ( $this->events_functionality_disabled() ) {
		// 		return;
		// 	}
		// 	$this->check_nonce( 'check_in_event_registration_' . $this->params['id'] );
		//
		// 	$reg         = new OsEventRegistrationModel( (int) $this->params['id'] );
		// 	$reg->status = LATEPOINT_EVENT_REGISTRATION_STATUS_CHECKED_IN;
		//
		// 	if ( $reg->save() ) {
		// 		/**
		// 		 * Fires after an attendee is checked in for an event.
		// 		 *
		// 		 * @param {OsEventRegistrationModel} $reg The registration that was checked in.
		// 		 *
		// 		 * @since 5.7.0
		// 		 * @hook latepoint_event_registration_checked_in
		// 		 */
		// 		do_action( 'latepoint_event_registration_checked_in', $reg );
		// 		$status        = LATEPOINT_STATUS_SUCCESS;
		// 		$response_html = __( 'Attendee checked in', 'latepoint' );
		// 	} else {
		// 		$status        = LATEPOINT_STATUS_ERROR;
		// 		$response_html = __( 'Error checking in attendee', 'latepoint' );
		// 	}
		//
		// 	$this->send_json(
		// 		[
		// 			'status'  => $status,
		// 			'message' => $response_html,
		// 		]
		// 	);
		// }

		// ─── Cancel ──────────────────────────────────────────────────────

		public function cancel() {
			if ( empty( $this->params['id'] ) || ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Invalid Registration ID', 'latepoint' ),
					]
				);
				return;
			}

			if ( $this->events_functionality_disabled() ) {
				return;
			}
			$this->check_nonce( 'cancel_event_registration_' . $this->params['id'] );

			$reg = new OsEventRegistrationModel( (int) $this->params['id'] );

			// Already cancelled — no-op instead of re-firing latepoint_event_registration_cancelled,
			// which would queue a duplicate cancellation email on every repeat click.
			if ( $reg->is_cancelled() ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Registration cancelled', 'latepoint' ),
					]
				);
				return;
			}

			$old_status  = $reg->status;
			$reg->status = LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED;

			if ( $reg->save() ) {
				/**
				 * Fires after an event registration is cancelled.
				 *
				 * @param {OsEventRegistrationModel} $reg        The registration that was cancelled.
				 * @param {string}                   $old_status The registration's status before cancellation.
				 *
				 * @since 5.7.0
				 * @hook latepoint_event_registration_cancelled
				 */
				do_action( 'latepoint_event_registration_cancelled', $reg, $old_status );
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Registration cancelled', 'latepoint' );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error cancelling registration', 'latepoint' );
			}

			$this->send_json(
				[
					'status'  => $status,
					'message' => $response_html,
				]
			);
		}

		/**
		 * Guards the mutating admin actions (cancel) against being reachable while
		 * the "Enable Events Functionality" toggle is off — the Events menu is hidden in that case
		 * (see OsMenuHelper::get_side_menu_items()), but routing has no allow-list, so these
		 * actions were otherwise still callable directly by route_name.
		 */
		private function events_functionality_disabled(): bool {
			if ( OsSettingsHelper::is_on( 'enable_events_functionality' ) ) {
				return false;
			}
			$this->send_json(
				[
					'status'  => LATEPOINT_STATUS_ERROR,
					'message' => __( 'Events functionality is disabled.', 'latepoint' ),
				]
			);
			return true;
		}
	}

endif;
