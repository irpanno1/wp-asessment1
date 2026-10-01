<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OsEventsManagerController' ) ) :

	class OsEventsManagerController extends OsController {

		public function __construct() {
			parent::__construct();

			$this->action_access['public'] = array_merge( $this->action_access['public'], [ 'pre_register', 'update_ticket_quantity' ] );

			$this->views_folder            = LATEPOINT_VIEWS_ABSPATH . 'events_manager/';
			$this->vars['page_header']     = OsMenuHelper::get_menu_items_by_id( 'events' );
			$this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id( 'events' );
			$this->vars['breadcrumbs'][]   = [
				'label' => __( 'Events', 'latepoint' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'events_manager', 'index' ) ),
			];
		}

		// ─── Index ────────────────────────────────────────────────────────

		public function index() {
			$this->vars['event_categories'] = ( new OsEventCategoryModel() )->should_be_active()->order_by( 'order_number asc' )->get_results_as_models();

			// All statuses, not just published — draft/cancelled events are shown alongside their
			// published siblings (with their own dimmed styling), not split into a separate bucket.
			$uncategorized_events_query         = new OsEventModel();
			$this->vars['uncategorized_events'] = $uncategorized_events_query->where(
				[
					'category_id' => [
						'OR' => [
							0,
							'IS NULL',
						],
					],
				]
			)->order_by( 'start_datetime_utc asc' )->get_results_as_models();

			// Batch-load booked capacity for every event on this page instead of letting each
			// tile's get_booked_capacity() call run its own SUM query. get_events() is memoized on
			// the category instance, so priming it here also warms the same call the view makes.
			$all_events = $this->vars['uncategorized_events'];
			foreach ( $this->vars['event_categories'] as $event_category ) {
				$all_events = array_merge( $all_events, $event_category->get_events() );
			}
			$event_ids  = array_map( function ( $event ) { return $event->id; }, $all_events );
			$capacities = OsEventModel::get_booked_capacities_for_event_ids( $event_ids );
			foreach ( $all_events as $event ) {
				$event->booked_capacity = $capacities[ $event->id ] ?? 0;
			}

			$this->format_render( __FUNCTION__ );
		}

		// ─── New / Edit forms ────────────────────────────────────────────

		public function new_form() {
			$this->vars['pre_page_header'] = '';
			$this->vars['page_header']     = __( 'Create New Event', 'latepoint' );
			$this->vars['breadcrumbs'][]   = [
				'label' => __( 'Create New Event', 'latepoint' ),
				'link'  => false,
			];
			$event                         = new OsEventModel();
			// Preset the category when arriving from a category tile's "New Event" link
			// (events_manager/index.php), so the form doesn't default back to "No Category".
			if ( ! empty( $this->params['category_id'] ) ) {
				$event->category_id = absint( $this->params['category_id'] );
			}
			$this->vars['event']      = $event;
			$this->vars['categories'] = ( new OsEventCategoryModel() )->should_be_active()->get_results_as_models();
			// $this->vars['locations']  = ( new OsLocationModel() )->get_results_as_models();
			// $this->vars['agents']     = ( new OsAgentModel() )->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}

		public function edit_form() {
			if ( empty( $this->params['id'] ) || ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				wp_die( esc_html__( 'Invalid Event ID', 'latepoint' ) );
			}

			$event = new OsEventModel( (int) $this->params['id'] );
			if ( ! $event->id ) {
				wp_die( esc_html__( 'Event not found', 'latepoint' ) );
			}

			$this->vars['pre_page_header'] = '';
			$this->vars['page_header']     = __( 'Edit Event', 'latepoint' );
			$this->vars['breadcrumbs'][]   = [
				'label' => __( 'Edit Event', 'latepoint' ),
				'link'  => false,
			];

			$this->vars['event']      = $event;
			$this->vars['categories'] = ( new OsEventCategoryModel() )->should_be_active()->get_results_as_models();
			// $this->vars['locations']  = ( new OsLocationModel() )->get_results_as_models();
			// $this->vars['agents']     = ( new OsAgentModel() )->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}

		// ─── Create / Update ─────────────────────────────────────────────

		public function create() {
			if ( $this->events_functionality_disabled() ) {
				return;
			}
			$this->check_nonce( 'new_event' );

			$this->params['event'] = $this->combine_registration_datetime_params( $this->params['event'] );
			$this->params['event'] = $this->normalize_capacity_param( $this->params['event'] );
			$event                 = new OsEventModel();
			$event->set_data( $this->params['event'] );

			if ( $event->save() ) {
				$this->save_event_meta( $event, $this->params['event'] );
				/**
				 * Fires after a new event is saved.
				 *
				 * @param {OsEventModel} $event The event that was created.
				 *
				 * @since 5.7.0
				 * @hook latepoint_event_created
				 */
				do_action( 'latepoint_event_created', $event );
				// Redirect straight into the edit page so the (now-available) Event Schedule
				// section is immediately usable, instead of bouncing back to the events list.
				$response_html = OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'events_manager', 'edit_form' ), [ 'id' => $event->id ] );
				$status        = LATEPOINT_STATUS_SUCCESS;
			} else {
				$response_html = $event->get_error_messages();
				$status        = LATEPOINT_STATUS_ERROR;
			}

			$this->send_json(
				[
					'status'  => $status,
					'message' => $response_html,
				]
			);
		}

		public function update() {
			if ( empty( $this->params['event']['id'] ) || ! filter_var( $this->params['event']['id'], FILTER_VALIDATE_INT ) ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Invalid Event ID', 'latepoint' ),
					]
				);
				return;
			}

			if ( $this->events_functionality_disabled() ) {
				return;
			}
			$this->check_nonce( 'edit_event_' . $this->params['event']['id'] );

			$event = new OsEventModel( (int) $this->params['event']['id'] );
			if ( ! $event->id ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Event not found', 'latepoint' ),
					]
				);
				return;
			}
			$this->params['event'] = $this->combine_registration_datetime_params( $this->params['event'] );
			$this->params['event'] = $this->normalize_capacity_param( $this->params['event'] );
			$event->set_data( $this->params['event'] );

			if ( $event->save() ) {
				$this->save_event_meta( $event, $this->params['event'] );
				/**
				 * Fires after an existing event is updated.
				 *
				 * @param {OsEventModel} $event The event that was updated.
				 *
				 * @since 5.7.0
				 * @hook latepoint_event_saved
				 */
				do_action( 'latepoint_event_saved', $event );
				$response_html = __( 'Event saved. ID: ', 'latepoint' ) . $event->id;
				$status        = LATEPOINT_STATUS_SUCCESS;
			} else {
				$response_html = $event->get_error_messages();
				$status        = LATEPOINT_STATUS_ERROR;
			}

			$this->send_json(
				[
					'status'  => $status,
					'message' => $response_html,
				]
			);
		}

		// ─── Destroy ─────────────────────────────────────────────────────

		public function destroy() {
			if ( empty( $this->params['id'] ) || ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Invalid Event ID', 'latepoint' ),
					]
				);
				return;
			}

			if ( $this->events_functionality_disabled() ) {
				return;
			}
			$this->check_nonce( 'destroy_event_' . $this->params['id'] );

			$event = new OsEventModel( (int) $this->params['id'] );
			if ( $event->delete() ) {
				/**
				 * Fires after an event is deleted. Pro hooks this to clean up ticket types,
				 * registration-ticket-type joins, and tickets for the deleted event.
				 *
				 * @param {OsEventModel} $event The event that was deleted (id still set, row gone).
				 *
				 * @since 5.7.0
				 * @hook latepoint_event_deleted
				 */
				do_action( 'latepoint_event_deleted', $event );
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Event removed', 'latepoint' );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error removing event', 'latepoint' );
			}

			$this->send_json(
				[
					'status'  => $status,
					'message' => $response_html,
				]
			);
		}

		// ─── Pre-register (validates event before opening wizard) ─────────

		public function pre_register() {
			$event_id = absint( $this->params['event_id'] ?? 0 );
			$event    = new OsEventModel( $event_id );

			if ( ! $event->id ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Event not found', 'latepoint' ),
					]
				);
				return;
			}

			if ( ! $event->is_registration_open() ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Registration is not open for this event', 'latepoint' ),
					]
				);
				return;
			}

			// Event context is now carried via presets[selected_event_id] in the
			// booking-form params area (see OsEventsHooksHelper::render_event_preset_field).
			// We no longer store it in the global transient session — that leaked event mode
			// into all subsequent booking-wizard loads in the same browser session.
			$this->send_json(
				[
					'status' => LATEPOINT_STATUS_SUCCESS,
				]
			);
		}

		// ─── Live ticket-quantity preview (tickets step +/- stepper) ───────

		/**
		 * Persist a quantity change on the current active cart item without advancing the wizard, so
		 * the client can follow up with a summary-panel reload (latepoint_reload_summary()) and show
		 * live price updates as the visitor clicks +/- — same UX as the Total Attendees stepper.
		 */
		public function update_ticket_quantity() {
			OsStepsHelper::set_required_objects( $this->params );

			$event_id = (int) ( OsStepsHelper::$presets['selected_event_id'] ?? 0 );
			$quantity = max( 1, absint( $this->params['event_quantity'] ?? 1 ) );
			$event    = $event_id ? new OsEventModel( $event_id ) : null;

			if ( ! $event || ! $event->id ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Event not found.', 'latepoint' ),
					]
				);
				return;
			}

			$result = OsEventsHooksHelper::resolve_and_validate_event_quantity( $event, $this->params, $quantity );
			if ( $result['error'] ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $result['error'],
					]
				);
				return;
			}
			$quantity = $result['quantity'];

			OsEventsHooksHelper::update_active_cart_item_quantity( $event, $quantity, $this->params );

			$this->send_json(
				[
					'status' => LATEPOINT_STATUS_SUCCESS,
				]
			);
		}

		// ─── Event date-range lightbox (mirrors OsSettingsController::custom_day_schedule_form()) ──

		/**
		 * Render the "Add/Edit date range" lightbox — a calendar to pick start/end dates
		 * plus a single Start/Finish time card, reusing the same lightbox shell, calendar
		 * widget, and time-card markup as the Agent "Custom schedule" lightbox.
		 */
		public function event_date_range_form() {
			$event_id      = absint( $this->params['event_id'] ?? 0 );
			$date_range_id = absint( $this->params['date_range_id'] ?? 0 );

			$event = new OsEventModel( $event_id );

			$target_date_string = 'now';
			$start_value        = '';
			$end_value          = '';
			$start_minutes      = 540;  // 9:00 am
			$end_minutes        = 1020; // 5:00 pm

			if ( $date_range_id ) {
				$date_range = new OsEventDateModel( $date_range_id );
				if ( $date_range->id && (int) $date_range->event_id === (int) $event->id ) {
					$start_parts        = OsEventsManagerHelper::local_date_and_minutes_from_utc( $date_range->start_datetime_utc );
					$end_parts          = OsEventsManagerHelper::local_date_and_minutes_from_utc( $date_range->end_datetime_utc );
					$start_value        = $start_parts['date'];
					$end_value          = $end_parts['date'];
					$start_minutes      = $start_parts['minutes'];
					$end_minutes        = $end_parts['minutes'];
					$target_date_string = $start_value;
				} else {
					$date_range_id = 0;
				}
			}

			$this->vars['event']         = $event;
			$this->vars['date_range_id'] = $date_range_id;
			$this->vars['target_date']   = new OsWpDateTime( $target_date_string );
			$this->vars['start_value']   = $start_value;
			$this->vars['end_value']     = $end_value;
			$this->vars['start_minutes'] = $start_minutes;
			$this->vars['end_minutes']   = $end_minutes;

			$this->format_render( __FUNCTION__ );
		}

		/**
		 * Save one OsEventDateModel row from the lightbox submission and recompute
		 * the event's aggregate start_datetime_utc / end_datetime_utc.
		 */
		public function save_event_date_range() {
			$event_id = absint( $this->params['event_id'] ?? 0 );

			$this->check_nonce( 'save_event_date_range_' . $event_id );

			$event = new OsEventModel( $event_id );
			if ( ! $event->id ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Event not found', 'latepoint' ),
					]
				);
				return;
			}

			$date_range_id = absint( $this->params['date_range_id'] ?? 0 );
			$date_range    = $date_range_id ? new OsEventDateModel( $date_range_id ) : new OsEventDateModel();
			if ( $date_range_id && ( ! $date_range->id || (int) $date_range->event_id !== (int) $event->id ) ) {
				$date_range = new OsEventDateModel();
			}

			$start_date = isset( $this->params['start_custom_date'] ) ? sanitize_text_field( $this->params['start_custom_date'] ) : '';
			$end_date   = isset( $this->params['end_custom_date'] ) ? sanitize_text_field( $this->params['end_custom_date'] ) : '';
			if ( '' === $end_date ) {
				$end_date = $start_date;
			}

			$start_time_data = ( isset( $this->params['start_time'] ) && is_array( $this->params['start_time'] ) ) ? $this->params['start_time'] : [];
			$end_time_data   = ( isset( $this->params['end_time'] ) && is_array( $this->params['end_time'] ) ) ? $this->params['end_time'] : [];

			$start_minutes = OsTimeHelper::convert_time_to_minutes(
				$start_time_data['formatted_value'] ?? '',
				$start_time_data['ampm'] ?? false
			);
			$end_minutes   = OsTimeHelper::convert_time_to_minutes(
				$end_time_data['formatted_value'] ?? '',
				$end_time_data['ampm'] ?? false
			);

			$start_datetime_utc = OsEventsManagerHelper::utc_datetime_from_local( $start_date, $start_minutes );
			$end_datetime_utc   = OsEventsManagerHelper::utc_datetime_from_local( $end_date, $end_minutes );

			if ( '' === $start_date || $end_datetime_utc < $start_datetime_utc ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Please pick a valid date range with an end time on or after the start time.', 'latepoint' ),
					]
				);
				return;
			}

			$date_range->event_id           = $event->id;
			$date_range->start_datetime_utc = $start_datetime_utc;
			$date_range->end_datetime_utc   = $end_datetime_utc;

			if ( $date_range->save() ) {
				$this->recompute_event_date_aggregate( $event );

				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Date range saved', 'latepoint' ),
					]
				);
			} else {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $date_range->get_error_messages(),
					]
				);
			}
		}

		/**
		 * Delete one OsEventDateModel row and recompute the event's aggregate dates.
		 */
		public function remove_event_date_range() {
			$date_range_id = absint( $this->params['id'] ?? 0 );

			$this->check_nonce( 'remove_event_date_range_' . $date_range_id );

			$date_range = new OsEventDateModel( $date_range_id );
			if ( ! $date_range->id ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Date range not found', 'latepoint' ),
					]
				);
				return;
			}

			$event = new OsEventModel( (int) $date_range->event_id );
			$date_range->delete( $date_range->id );

			if ( $event->id ) {
				$this->recompute_event_date_aggregate( $event );
			}

			$this->send_json(
				[
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Date range removed', 'latepoint' ),
				]
			);
		}

		// ─── Helpers ─────────────────────────────────────────────────────

		/**
		 * Merge split date/time picker fields back into the combined *_utc columns
		 * that OsEventModel::params_to_save() expects. Only the (single, optional)
		 * registration window still uses this shape — start_datetime_utc /
		 * end_datetime_utc are derived instead from the event's OsEventDateModel
		 * rows, see recompute_event_date_aggregate().
		 *
		 * Each field is gated by an "Immediately"/"When event starts" vs "Custom
		 * date & time" toggle (event[use_custom_registration_start/_end], 'on'/'off').
		 * The date is only trusted when its toggle is on — turning the toggle off
		 * doesn't clear the picker's underlying hidden input, it only hides the div,
		 * so without this gate a previously-set custom date would silently resurrect
		 * on save even with the toggle off.
		 *
		 * The event form submits event[registration_start_date] (Y-m-d) and
		 * event[registration_start_time][formatted_value] (HH:MM) + [ampm].
		 * This method collapses them into event[registration_start_utc] = 'Y-m-d HH:MM:SS'.
		 * When the toggle is off (or the date is blank) the target column is set to
		 * '' (NULL on save) — which OsEventModel::is_registration_open() already
		 * reads as "open immediately" / "no explicit close".
		 *
		 * @param array $event_params Raw event params from the request.
		 * @return array Params with combined datetime columns ready for set_data().
		 */
		private function combine_registration_datetime_params( array $event_params ): array {
			$map = [
				'registration_start_utc' => [ 'registration_start_date', 'registration_start_time', 'use_custom_registration_start' ],
				'registration_end_utc'   => [ 'registration_end_date', 'registration_end_time', 'use_custom_registration_end' ],
			];

			foreach ( $map as $target => $parts ) {
				list( $date_key, $time_key, $toggle_key ) = $parts;

				$use_custom = isset( $event_params[ $toggle_key ] ) && 'on' === $event_params[ $toggle_key ];
				$date       = ( $use_custom && isset( $event_params[ $date_key ] ) ) ? sanitize_text_field( $event_params[ $date_key ] ) : '';

				if ( '' === $date ) {
					$event_params[ $target ] = '';
				} else {
					$time_data = ( isset( $event_params[ $time_key ] ) && is_array( $event_params[ $time_key ] ) )
						? $event_params[ $time_key ]
						: [];
					$formatted = isset( $time_data['formatted_value'] ) ? $time_data['formatted_value'] : '';
					$ampm      = isset( $time_data['ampm'] ) ? $time_data['ampm'] : false;
					$minutes   = OsTimeHelper::convert_time_to_minutes( $formatted, $ampm );

					$event_params[ $target ] = OsEventsManagerHelper::utc_datetime_from_local( $date, $minutes );
				}

				unset( $event_params[ $date_key ], $event_params[ $time_key ], $event_params[ $toggle_key ] );
			}

			return $event_params;
		}

		/**
		 * 0 and blank both mean unlimited (same as always) — nothing to normalize there, it's a
		 * deliberate choice. A negative value isn't a deliberate choice for anything, so it's
		 * treated as the minimum real capacity (1) rather than unlimited — matches the admin
		 * form's JS clamp. Only reachable via a bypassed client — the admin form's min="1" + JS
		 * clamp never produce a negative value.
		 *
		 * @param array $event_params Raw event params from the request.
		 * @return array Params with a negative capacity clamped to 1.
		 */
		private function normalize_capacity_param( array $event_params ): array {
			if ( isset( $event_params['capacity'] ) && '' !== $event_params['capacity'] && (int) $event_params['capacity'] < 0 ) {
				$event_params['capacity'] = 1;
			}

			return $event_params;
		}

		/**
		 * Persist event options that live in meta rather than table columns.
		 * Absent keys mean the toggle was switched off (unchecked togglers post nothing).
		 *
		 * @param OsEventModel $event        Saved event (must have an id).
		 * @param array        $event_params Raw event params from the request.
		 */
		private function save_event_meta( OsEventModel $event, array $event_params ): void {
			foreach ( [ 'dont_multiply_charge_amount_by_tickets', 'dont_ask_ticket_quantity' ] as $key ) {
				$value = ( isset( $event_params[ $key ] ) && OsUtilHelper::is_on( $event_params[ $key ] ) ) ? 'on' : 'off';
				$event->save_meta_by_key( $key, $value );
			}
			foreach ( [ 'organizer_name', 'location_text' ] as $key ) {
				if ( isset( $event_params[ $key ] ) ) {
					$event->save_meta_by_key( $key, sanitize_text_field( $event_params[ $key ] ) );
				}
			}
		}

		/**
		 * Recompute the event's aggregate start_datetime_utc / end_datetime_utc
		 * (earliest range start, latest range end) and save. Existing ORDER BY /
		 * is_registration_open() / display code reads those two columns and keeps
		 * working unmodified as long as this cache stays in sync with the
		 * OsEventDateModel rows, which is why every save/remove of a date range
		 * calls this immediately afterward.
		 *
		 * @param OsEventModel $event Event to recompute and save.
		 */
		private function recompute_event_date_aggregate( OsEventModel $event ): void {
			$all_dates = ( new OsEventDateModel() )->where( [ 'event_id' => $event->id ] )->order_by( 'start_datetime_utc asc' )->get_results_as_models();

			if ( $all_dates ) {
				$event->start_datetime_utc = $all_dates[0]->start_datetime_utc;
				$latest_end                = $all_dates[0]->end_datetime_utc;
				foreach ( $all_dates as $date_range ) {
					if ( $date_range->end_datetime_utc > $latest_end ) {
						$latest_end = $date_range->end_datetime_utc;
					}
				}
				$event->end_datetime_utc = $latest_end;
			} else {
				$event->start_datetime_utc = '';
				$event->end_datetime_utc   = '';
			}

			$event->save();
		}

		/**
		 * Guards the mutating admin actions (create/update/destroy) against being reachable while
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
