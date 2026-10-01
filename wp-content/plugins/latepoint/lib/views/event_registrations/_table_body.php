<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/* @var $registrations OsEventRegistrationModel[] */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $registrations ) {
	foreach ( $registrations as $reg ) :
		$customer = $reg->customer;
		?>
		<tr>
			<td class="text-center os-column-faded"><?php echo esc_html( (string) $reg->id ); ?></td>
			<td><code><?php echo esc_html( $reg->registration_code ); ?></code></td>
			<td><?php echo $reg->event ? esc_html( $reg->event->name ) : '&mdash;'; ?></td>
			<td><?php echo $customer ? esc_html( $customer->full_name ) : '&mdash;'; ?></td>
			<td><?php echo $customer ? esc_html( $customer->email ) : '&mdash;'; ?></td>
			<td><?php echo esc_html( (string) $reg->quantity ); ?></td>
			<td>
				<span class="lp-order-status lp-order-status-<?php echo esc_attr( $reg->status ); ?>">
					<?php echo esc_html( $reg->get_status_label() ); ?>
				</span>
			</td>
			<td><?php echo esc_html( $reg->payment_status ); ?></td>
			<td><small><?php echo esc_html( $reg->created_at ); ?></small></td>
			<td class="event-reg-actions">
				<?php
				// Parked: this rolled a whole registration up to "checked_in" without touching Pro's
				// per-attendee ticket rows, so a QR ticket could still scan as a fresh admission
				// afterwards. Ticket-level scanning (Pro OsFeatureEventTicketsHelper) is the check-in
				// path now; paired action OsEventRegistrationsController::check_in() is parked too.
				/*
				if ( ! $reg->is_checked_in() && ! $reg->is_cancelled() ) : ?>
					<a href="#"
					   data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'event_registrations', 'check_in' ) ); ?>"
					   data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( [ 'id' => $reg->id ], 'check_in_event_registration_' . $reg->id ) ); ?>"
					   data-os-success-action="reload"
					   class="latepoint-btn latepoint-btn-link">
						<?php esc_html_e( 'Check In', 'latepoint' ); ?>
					</a>
				<?php endif;
				*/
				?>
				<?php if ( ! $reg->is_cancelled() ) : ?>
					<a href="#"
					   data-os-prompt="<?php esc_attr_e( 'Cancel this registration?', 'latepoint' ); ?>"
					   data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'event_registrations', 'cancel' ) ); ?>"
					   data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( [ 'id' => $reg->id ], 'cancel_event_registration_' . $reg->id ) ); ?>"
					   data-os-success-action="reload"
					   class="latepoint-btn latepoint-btn-link latepoint-btn-danger">
						<?php esc_html_e( 'Cancel', 'latepoint' ); ?>
					</a>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
		</tr>
		<?php
	endforeach;
} else {
	?>
	<tr>
		<td colspan="10" class="text-center">
			<?php esc_html_e( 'No registrations found.', 'latepoint' ); ?>
		</td>
	</tr>
	<?php
}
