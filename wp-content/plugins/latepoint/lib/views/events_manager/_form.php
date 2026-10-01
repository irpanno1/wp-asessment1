<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/* @var $event OsEventModel */
/* @var $categories OsEventCategoryModel[] */
/* @var $locations OsLocationModel[] */
/* @var $agents OsAgentModel[] */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_new    = $event->is_new_record();
$action    = $is_new ? 'create' : 'update';
$nonce_key = $is_new ? 'new_event' : 'edit_event_' . $event->id;

// Build category options.
// Note: this must stay in the list-of-['value' => ..., 'label' => ...] shape (matching
// OsLocationHelper::get_locations_list()/OsAgentHelper::get_agents_list() below) — an assoc
// array keyed by numeric id coerces the key to an int, and OsFormHelper::select_field() then
// falls back to using the label as the option value for any non-string key.
$category_options = [ [ 'value' => '', 'label' => __( 'No Category', 'latepoint' ) ] ];
foreach ( $categories as $cat ) {
	$category_options[] = [ 'value' => $cat->id, 'label' => $cat->name ];
}

// Build location options.
// Location field is temporarily disabled below (not needed right now) — kept fixed and ready
// to re-enable, see the render block further down.
// $location_options = [ [ 'value' => '', 'label' => __( 'No Location', 'latepoint' ) ] ];
// $location_options = array_merge( $location_options, OsLocationHelper::get_locations_list() );

// Build agent options.
// Host field is temporarily disabled below (not needed right now) — kept fixed and ready
// to re-enable, see the render block further down.
// $agent_options = [ [ 'value' => '', 'label' => __( 'No Host', 'latepoint' ) ] ];
// $agent_options = array_merge( $agent_options, OsAgentHelper::get_agents_list() );

// Status options.
$status_options = [
	LATEPOINT_EVENT_STATUS_DRAFT     => __( 'Draft', 'latepoint' ),
	LATEPOINT_EVENT_STATUS_PUBLISHED => __( 'Published', 'latepoint' ),
	LATEPOINT_EVENT_STATUS_CANCELLED => __( 'Cancelled', 'latepoint' ),
	LATEPOINT_EVENT_STATUS_COMPLETED => __( 'Completed', 'latepoint' ),
];

// Event type options.
$type_options = [
	LATEPOINT_EVENT_TYPE_IN_PERSON => __( 'In Person', 'latepoint' ),
	LATEPOINT_EVENT_TYPE_ONLINE    => __( 'Online', 'latepoint' ),
];

// Build delete/extra context menu (edit only) to reuse in both buttons block and side nav.
$extra_actions_html = '';
if ( ! $is_new && current_user_can( 'manage_options' ) ) {
	$extra_actions_html  = '<div class="os-trigger-dots"><div class="os-trigger-dots-context">';
	$extra_actions_html .= '<div class="os-context-item os-danger os-delete-confirm"'
		. ' data-os-confirm-title="' . esc_attr__( 'Delete Event', 'latepoint' ) . '"'
		. ' data-os-prompt="' . esc_attr__( 'Are you sure you want to remove this event? It will remove all registrations associated with it. You can also change status to Draft if you want to temporarily disable it instead.', 'latepoint' ) . '"'
		. ' data-os-redirect-to="' . esc_url( OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'events_manager', 'index' ) ) ) . '"'
		. ' data-os-params="' . esc_attr( OsUtilHelper::build_os_params( [ 'id' => $event->id ], 'destroy_event_' . $event->id ) ) . '"'
		. ' data-os-success-action="redirect"'
		. ' data-os-action="' . esc_attr( OsRouterHelper::build_route_name( 'events_manager', 'destroy' ) ) . '">'
		. '<i class="latepoint-icon latepoint-icon-trash-2"></i><span>' . esc_html__( 'Delete', 'latepoint' ) . '</span></div>';
	$extra_actions_html .= '</div><i class="latepoint-icon latepoint-icon-more-horizontal"></i></div>';
}
?>
<form action="" data-os-success-action="redirect"
	  <?php // New events redirect to the URL returned by create() (the edit page for the new
	  // event, so its Event Schedule section is immediately usable) instead of a fixed target. ?>
	  <?php if ( ! $is_new ) : ?>
	  data-os-redirect-to="<?php echo esc_url( OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'events_manager', 'index' ) ) ); ?>"
	  <?php endif; ?>
	  data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'events_manager', $action ) ); ?>">
	<div class="latepoint-page-with-side-nav">
		<div class="os-form-w">

			<div class="white-box section-anchor" id="stickySectionGeneral">
				<div class="white-box-header">
					<div class="os-form-sub-header">
						<h3><?php esc_html_e( 'General', 'latepoint' ); ?></h3>
						<?php if ( ! $is_new ) : ?>
							<div class="os-form-sub-header-actions os-highlight"><?php echo sprintf( esc_html__( 'Event ID: %d', 'latepoint' ), esc_html( (string) $event->id ) ); ?></div>
						<?php endif; ?>
					</div>
				</div>
				<div class="white-box-content">
					<div class="os-row">
						<div class="os-col-lg-8">
							<?php echo OsFormHelper::text_field( 'event[name]', __( 'Event Name', 'latepoint' ), $event->name, [ 'theme' => 'simple' ] ); ?>
						</div>
						<div class="os-col-lg-4">
							<?php echo OsFormHelper::select_field( 'event[status]', __( 'Status', 'latepoint' ), $status_options, $event->status ); ?>
						</div>
					</div>
					<div class="os-row">
						<div class="os-col-lg-12">
							<?php echo OsFormHelper::textarea_field( 'event[summary]', __( 'Summary', 'latepoint' ), $event->summary, [ 'rows' => 2, 'theme' => 'simple' ] ); ?>
						</div>
					</div>
					<?php if ( false ) : // Description field hidden — flip back to `if ( true )` to re-enable. ?>
					<div class="os-row">
						<div class="os-col-lg-12">
							<?php echo OsFormHelper::textarea_field( 'event[description]', __( 'Full Description', 'latepoint' ), $event->description, [ 'rows' => 6, 'theme' => 'simple' ] ); ?>
						</div>
					</div>
					<?php endif; ?>
					<div class="os-row">
						<div class="os-col-lg-4">
							<?php echo OsFormHelper::select_field( 'event[category_id]', __( 'Category', 'latepoint' ), $category_options, $event->category_id ); ?>
						</div>
						<div class="os-col-lg-4">
							<?php echo OsFormHelper::select_field( 'event[event_type]', __( 'Event Type', 'latepoint' ), $type_options, $event->event_type ); ?>
						</div>
						<div class="os-col-lg-4">
							<?php echo OsFormHelper::text_field( 'event[online_url]', __( 'Online Meeting URL', 'latepoint' ), $event->online_url, [ 'theme' => 'simple' ] ); ?>
						</div>
					</div>
					<div class="os-row">
						<?php
						// Host & Location selects temporarily disabled — not needed right now. Fixed and
						// ready to re-enable: flip both back to `if ( true )` along with their
						// $location_options / $agent_options blocks above. Using organizer_name/location_text
						// meta in the meantime so re-enabling agent_id/location_id later has zero conflict.
						if ( false ) :
							?>
							<div class="os-col-lg-6">
								<?php echo OsFormHelper::select_field( 'event[location_id]', __( 'Location', 'latepoint' ), $location_options, $event->location_id ); ?>
							</div>
							<div class="os-col-lg-6">
								<?php echo OsFormHelper::select_field( 'event[agent_id]', __( 'Organizer', 'latepoint' ), $agent_options, $event->agent_id ); ?>
							</div>
							<?php
						endif;
						?>
						<div class="os-col-lg-6">
							<?php echo OsFormHelper::text_field( 'event[location_text]', __( 'Location', 'latepoint' ), $event->get_meta_by_key( 'location_text', '' ), [ 'theme' => 'simple' ] ); ?>
						</div>
						<div class="os-col-lg-6">
							<?php echo OsFormHelper::text_field( 'event[organizer_name]', __( 'Organizer', 'latepoint' ), $event->get_meta_by_key( 'organizer_name', '' ), [ 'theme' => 'simple' ] ); ?>
						</div>
					</div>
				</div>
			</div>

			<div class="white-box section-anchor" id="stickySectionMedia">
				<div class="white-box-header">
					<div class="os-form-sub-header"><h3><?php esc_html_e( 'Media', 'latepoint' ); ?></h3></div>
				</div>
				<div class="white-box-content">
					<?php echo OsFormHelper::media_uploader_field( 'event[featured_image_id]', 0, __( 'Event Image', 'latepoint' ), __( 'Remove Image', 'latepoint' ), $event->featured_image_id ); ?>
				</div>
			</div>

			<div class="white-box section-anchor" id="stickySectionDateTime">
				<div class="white-box-header">
					<div class="os-form-sub-header"><h3><?php esc_html_e( 'Date & Time', 'latepoint' ); ?></h3></div>
				</div>
				<div class="white-box-content">
					<?php
					// Registration opens — "Immediately" (default) or a custom date/time.
					$reg_start_raw = $event->registration_start_utc;
					$reg_start_set = ( ! empty( $reg_start_raw ) && 0 !== strpos( $reg_start_raw, '0000-00-00' ) );
					if ( $reg_start_set ) {
						$reg_start_parts = OsEventsManagerHelper::local_date_and_minutes_from_utc( $reg_start_raw );
						$reg_start_d     = $reg_start_parts['date'];
						$reg_start_min   = $reg_start_parts['minutes'];
					} else {
						$reg_start_d   = '';
						$reg_start_min = '';
					}
					$reg_start_d_f = $reg_start_set ? OsTimeHelper::get_readable_date_from_string( $reg_start_d ) : __( 'Select date', 'latepoint' );

					// Registration closes — "When event starts" (default) or a custom date/time.
					$reg_end_raw = $event->registration_end_utc;
					$reg_end_set = ( ! empty( $reg_end_raw ) && 0 !== strpos( $reg_end_raw, '0000-00-00' ) );
					if ( $reg_end_set ) {
						$reg_end_parts = OsEventsManagerHelper::local_date_and_minutes_from_utc( $reg_end_raw );
						$reg_end_d     = $reg_end_parts['date'];
						$reg_end_min   = $reg_end_parts['minutes'];
					} else {
						$reg_end_d   = '';
						$reg_end_min = '';
					}
					$reg_end_d_f = $reg_end_set ? OsTimeHelper::get_readable_date_from_string( $reg_end_d ) : __( 'Select date', 'latepoint' );
					?>
					<div class="os-row os-event-schedule-row">
						<div class="os-col-lg-12">
							<div class="os-form-group">
								<label><?php esc_html_e( 'Event Schedule', 'latepoint' ); ?></label>
								<?php if ( $is_new ) : ?>
									<p class="description"><?php esc_html_e( 'Save this event first, then you can add its date/time schedule.', 'latepoint' ); ?></p>
								<?php else : ?>
									<?php echo OsEventsManagerHelper::generate_event_date_range_tiles( $event->id ); ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<div class="os-row os-event-registration-row">
						<div class="os-col-lg-6">
							<div class="os-form-group">
								<label><?php esc_html_e( 'Registration Opens', 'latepoint' ); ?> <i class="latepoint-icon latepoint-icon-info os-label-tooltip-icon" data-late-tooltip="<?php esc_attr_e( 'Keep the toggle disabled to open registration immediately, or enable it to set a custom opening date.', 'latepoint' ); ?>"></i></label>
								<?php
								echo OsFormHelper::toggler_field(
									'event[use_custom_registration_start]',
									__( 'Custom date & time', 'latepoint' ),
									$reg_start_set,
									'eventRegStartCustomWrap',
									false,
									[ 'sub_label' => __( 'Disabled: Opens immediately.', 'latepoint' ) ]
								);
								?>
								<div id="eventRegStartCustomWrap"<?php echo $reg_start_set ? '' : ' style="display:none;"'; ?>>
									<div class="os-datetime-pair">
										<?php echo OsFormHelper::date_picker_field( 'event[registration_start_date]', $reg_start_d_f, $reg_start_d ); ?>
										<?php echo OsFormHelper::time_field( 'event[registration_start_time]', '', $reg_start_min ); ?>
									</div>
								</div>
							</div>
						</div>
						<div class="os-col-lg-6">
							<div class="os-form-group">
								<label><?php esc_html_e( 'Registration Closes', 'latepoint' ); ?> <i class="latepoint-icon latepoint-icon-info os-label-tooltip-icon" data-late-tooltip="<?php esc_attr_e( 'Keep the toggle disabled to close registration when the event starts, or enable it to set a custom closing date.', 'latepoint' ); ?>"></i></label>
								<?php
								echo OsFormHelper::toggler_field(
									'event[use_custom_registration_end]',
									__( 'Custom date & time', 'latepoint' ),
									$reg_end_set,
									'eventRegEndCustomWrap',
									false,
									[ 'sub_label' => __( 'Disabled: Closes when the event starts.', 'latepoint' ) ]
								);
								?>
								<div id="eventRegEndCustomWrap"<?php echo $reg_end_set ? '' : ' style="display:none;"'; ?>>
									<div class="os-datetime-pair">
										<?php echo OsFormHelper::date_picker_field( 'event[registration_end_date]', $reg_end_d_f, $reg_end_d ); ?>
										<?php echo OsFormHelper::time_field( 'event[registration_end_time]', '', $reg_end_min ); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="white-box section-anchor" id="stickySectionCapacity">
				<div class="white-box-header">
					<div class="os-form-sub-header"><h3><?php esc_html_e( 'Capacity & Pricing', 'latepoint' ); ?></h3></div>
				</div>
				<div class="white-box-content">
					<?php
					/**
					 * Filters whether the capacity/price fields render as readonly.
					 *
					 * @param {bool}         $readonly Whether the fields should be readonly, default false.
					 * @param {OsEventModel} $event    The event being edited.
					 *
					 * @returns {bool} Filtered readonly flag.
					 * @since 5.7.0
					 * @hook latepoint_event_capacity_pricing_readonly
					 */
					$capacity_pricing_readonly = apply_filters( 'latepoint_event_capacity_pricing_readonly', false, $event );
					$capacity_pricing_atts     = [
						'theme'   => 'simple',
						'tooltip' => __( 'Enter 0 for a free ticket.', 'latepoint' ),
					];
					if ( $capacity_pricing_readonly ) {
						$capacity_pricing_atts['readonly'] = 'readonly';
					}
					// Separate from $capacity_pricing_atts so these don't also apply to the price field below.
					$capacity_atts = array_merge(
						$capacity_pricing_atts,
						[
							'type'        => 'number',
							'min'         => '0',
							'placeholder' => 'e.g. 10',
							'tooltip'     => __( 'Leave blank for unlimited capacity.', 'latepoint' ),
						]
					);
					?>
					<div class="os-row">
						<div class="os-col-lg-4">
							<?php echo OsFormHelper::money_field( 'event[price]', __( 'Price', 'latepoint' ), $event->price, $capacity_pricing_atts ); ?>
						</div>
						<div class="os-col-lg-4">
							<?php echo OsFormHelper::text_field( 'event[capacity]', __( 'Capacity', 'latepoint' ), $event->capacity ?: '', $capacity_atts ); ?>
						</div>
					</div>
					<?php
					/**
					 * Fires right after the capacity/price fields, before the ticket-quantity toggles.
					 * Pro's Ticket Types addon uses this to render its own per-type rows.
					 *
					 * @param {OsEventModel} $event The event being edited.
					 *
					 * @since 5.7.0
					 * @hook latepoint_event_capacity_pricing_after
					 */
					do_action( 'latepoint_event_capacity_pricing_after', $event );
					?>
					<div class="os-row">
						<div class="os-col-lg-12">
							<?php
							echo OsFormHelper::toggler_field(
								'event[dont_multiply_charge_amount_by_tickets]',
								__( 'Do not multiply charge amount by the number of tickets', 'latepoint' ),
								OsUtilHelper::is_on( $event->get_meta_by_key( 'dont_multiply_charge_amount_by_tickets', 'off' ) ),
								false,
								false,
								[ 'sub_label' => __( 'Charge the ticket price once per registration, regardless of how many tickets are selected.', 'latepoint' ) ]
							);
							echo OsFormHelper::toggler_field(
								'event[dont_ask_ticket_quantity]',
								__( 'Do not ask customers to select number of tickets', 'latepoint' ),
								OsUtilHelper::is_on( $event->get_meta_by_key( 'dont_ask_ticket_quantity', 'off' ) ),
								false,
								false,
								[ 'sub_label' => __( 'Skips the ticket quantity step and automatically sets the quantity to 1 for each registration.', 'latepoint' ) ]
							);
							?>
						</div>
					</div>
				</div>
			</div>

			<?php
			// Pro's Ticket Types addon listens on this action when active.
			if ( ! defined( 'LATEPOINT_ADDON_PRO_VERSION' ) ) {
				OsEventsHooksHelper::output_ticket_types_upsell( $event );
			}
			/**
			 * Fires after the event form's main sections, before the save buttons.
			 *
			 * @param {OsEventModel} $event The event being edited.
			 *
			 * @since 5.7.0
			 * @hook latepoint_event_form_after
			 */
			do_action( 'latepoint_event_form_after', $event );
			?>

			<div class="os-form-buttons os-flex hidden-with-side-nav">
				<?php
				if ( $is_new ) {
					echo OsFormHelper::hidden_field( 'event[id]', '' );
					echo OsFormHelper::button( 'submit', __( 'Add Event', 'latepoint' ), 'submit', [ 'class' => 'latepoint-btn' ] );
				} else {
					echo OsFormHelper::hidden_field( 'event[id]', $event->id );
					echo $extra_actions_html;
					if ( current_user_can( 'manage_options' ) ) {
						echo OsFormHelper::button( 'submit', __( 'Save Changes', 'latepoint' ), 'submit', [ 'class' => 'latepoint-btn' ] );
					}
				}
				?>
			</div>
			<?php wp_nonce_field( $nonce_key ); ?>
		</div>

		<div class="latepoint-page-side-nav">
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div class="side-nav-actions">
					<?php echo $extra_actions_html; ?>
					<button type="submit" class="latepoint-btn latepoint-btn-block"><i class="latepoint-icon latepoint-icon-check"></i><span><?php echo $is_new ? esc_html__( 'Add Event', 'latepoint' ) : esc_html__( 'Save Changes', 'latepoint' ); ?></span></button>
				</div>
			<?php endif; ?>
			<div class="side-nav-body">
				<div><a href="#stickySectionGeneral" class="is-active"><?php esc_html_e( 'General', 'latepoint' ); ?></a></div>
				<div><a href="#stickySectionMedia"><?php esc_html_e( 'Media', 'latepoint' ); ?></a></div>
				<div><a href="#stickySectionDateTime"><?php esc_html_e( 'Date & Time', 'latepoint' ); ?></a></div>
				<div><a href="#stickySectionCapacity"><?php esc_html_e( 'Capacity & Pricing', 'latepoint' ); ?></a></div>
				<div><a href="#stickySectionTicketTypes"><?php esc_html_e( 'Ticket Types', 'latepoint' ); ?></a></div>
				<?php
				/**
				 * Sticky menu items links for the event edit form
				 *
				 * @param {array} $sticky_menu_items items that go into sticky menu on the right of settings, in format ['href' => '', 'label' => '']
				 * @returns {array} The filtered array of sticky menu items
				 *
				 * @since 5.7.0
				 * @hook latepoint_event_edit_form_sticky_section_items
				 *
				 */
				$before_other_items = apply_filters( 'latepoint_event_edit_form_sticky_section_items', [] );
				foreach ( $before_other_items as $item ) {
					echo '<div><a href="#' . esc_attr( $item['href'] ) . '">' . esc_html( $item['label'] ) . '</a></div>';
				}
				?>
			</div>
			<?php
			/**
			 * Doc links shown in the "Tips & Resources" box below the event form's side nav.
			 *
			 * @param {array}        $help_links Array of ['label' => string, 'url' => string].
			 * @param {OsEventModel} $event      The event being edited.
			 * @returns {array} The filtered array of help links.
			 *
			 * @since 5.7.0
			 * @hook latepoint_event_edit_form_help_links
			 *
			 */
			$help_links = apply_filters(
				'latepoint_event_edit_form_help_links',
				[
					[
						'label' => __( 'Setting up your first event', 'latepoint' ),
						'url'   => 'https://latepoint.com/docs/events-overview-getting-started/',
					],
					[
						'label' => __( 'Selling ticket types', 'latepoint' ),
						'url'   => 'https://latepoint.com/docs/event-ticket-types/',
					],
					[
						'label' => __( 'Displaying events on your website', 'latepoint' ),
						'url'   => 'https://latepoint.com/docs/displaying-events-registration-buttons/',
					],
					[
						'label' => __( 'Managing event registrations', 'latepoint' ),
						'url'   => 'https://latepoint.com/docs/managing-event-registrations/',
					],
					[
						'label' => __( 'Checking in attendees with QR tickets', 'latepoint' ),
						'url'   => 'https://latepoint.com/docs/e-tickets-qr-code-check-in',
					],
				],
				$event
			);
			include LATEPOINT_LIB_ABSPATH . 'views/partials/_help_box.php';
			?>
		</div>
	</div>
</form>
