<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="os-event os-resource-grid-item os-event-status-<?php echo esc_attr( $event->status ); ?>">
	<div class="os-event-header">
		<h3 class="event-name"><?php echo esc_html( $event->name ); ?></h3>
	</div>
	<div class="os-event-body">
		<div class="os-event-info">
			<?php $event_dates_count = count( $event->get_dates() ); ?>
			<div class="service-info-row">
				<div class="label"><?php esc_html_e( 'Date:', 'latepoint' ); ?></div>
				<div class="value">
					<strong><?php echo esc_html( $event->get_formatted_start_date() . ' ' . $event->get_formatted_start_time() ); ?></strong>
					<?php if ( $event_dates_count > 1 ) : ?>
						<?php
						printf(
							/* translators: %d: number of date ranges in the event's schedule */
							esc_html__( ' (%d date ranges)', 'latepoint' ),
							$event_dates_count
						);
						?>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $event->category ) : ?>
				<div class="service-info-row">
					<div class="label"><?php esc_html_e( 'Category:', 'latepoint' ); ?></div>
					<div class="value"><strong><?php echo esc_html( $event->category->name ); ?></strong></div>
				</div>
			<?php endif; ?>
			<div class="service-info-row">
				<div class="label"><?php esc_html_e( 'Capacity:', 'latepoint' ); ?></div>
				<div class="value"><strong><?php
				if ( $event->has_capacity_limit() ) {
					echo esc_html( $event->get_booked_capacity() . '/' . $event->get_total_capacity() );
				} else {
					esc_html_e( 'Unlimited', 'latepoint' );
				}
				?></strong></div>
			</div>
			<div class="service-info-row">
				<div class="label"><?php esc_html_e( 'Price:', 'latepoint' ); ?></div>
				<div class="value"><strong><?php echo esc_html( $event->get_formatted_price() ); ?></strong></div>
			</div>
			<?php
			/**
			 * Fires after the event tile's built-in info rows (date, category, capacity, price), so
			 * addons can append their own rows to the same tile on the Events index page.
			 *
			 * @param {OsEventModel} $event The event being rendered.
			 *
			 * @since 5.7.0
			 * @hook latepoint_event_tile_info_rows_after
			 */
			do_action( 'latepoint_event_tile_info_rows_after', $event );
			?>
		</div>
	</div>
	<div class="os-event-foot">
		<a href="<?php echo esc_url( OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'events_manager', 'edit_form' ), [ 'id' => $event->id ] ) ); ?>"
		   class="latepoint-btn latepoint-btn-block latepoint-btn-secondary">
			<i class="latepoint-icon latepoint-icon-edit-3"></i>
			<span><?php esc_html_e( 'Edit Event', 'latepoint' ); ?></span>
		</a>
	</div>
</div>
