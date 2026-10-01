<?php
/**
 * Event wizard step view — shown in place of service/agent/datepicker steps.
 *
 * @var OsEventModel|null $event
 * @var string            $current_step_code
 * @var array             $presets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="step-event-w latepoint-step-content"
     data-step-code="<?php echo esc_attr( $current_step_code ); ?>"
     data-next-btn-label="<?php echo esc_attr( OsStepsHelper::get_next_btn_label_for_step( $current_step_code ) ); ?>">
	<?php
	do_action( 'latepoint_before_step_content', $current_step_code );
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'before' );
	?>

	<?php if ( ! $event || ! $event->id ) : ?>
		<div class="os-error-message">
			<p><?php esc_html_e( 'Event not found. Please go back and try again.', 'latepoint' ); ?></p>
		</div>
	<?php else : ?>

		<!-- Carry the event context forward through all subsequent wizard steps -->
		<input type="hidden" name="presets[selected_event_id]" value="<?php echo esc_attr( (string) $event->id ); ?>">
		<input type="hidden" name="presets[booking_intent]" value="event">

		<div class="event-step-w">

			<!-- Event summary card -->
			<div class="event-step-summary">

				<!-- Header: title left, price right -->
				<div class="event-step-header">
					<h3 class="event-step-title"><?php echo esc_html( $event->name ); ?></h3>
					<div class="event-step-price-value"><?php echo esc_html( $event->get_formatted_price() ); ?></div>
				</div>

				<?php if ( $event->summary ) : ?>
					<p class="event-step-desc"><?php echo esc_html( $event->summary ); ?></p>
				<?php endif; ?>

				<!-- Meta grid: 2-column (date & availability) + optional full-width location row -->
				<div class="event-step-meta">

					<?php $event_dates = $event->get_dates(); ?>
					<?php if ( $event_dates ) : ?>
						<div class="event-step-meta-cell">
							<span class="event-step-meta-label"><?php esc_html_e( 'Date &amp; time', 'latepoint' ); ?></span>
							<span class="event-step-meta-value event-step-meta-schedule">
								<?php foreach ( $event_dates as $event_date ) : ?>
									<span class="event-step-schedule-row">
										<?php echo esc_html( $event_date->get_formatted_date_range() ); ?>
										<span class="event-step-meta-time"><?php echo esc_html( $event_date->get_formatted_time_range() ); ?></span>
									</span>
								<?php endforeach; ?>
							</span>
						</div>
					<?php endif; ?>

					<?php $available = $event->get_available_capacity(); ?>
					<div class="event-step-meta-cell">
						<span class="event-step-meta-label"><?php esc_html_e( 'Availability', 'latepoint' ); ?></span>
						<?php if ( ! $event->has_capacity_limit() ) : ?>
							<span class="event-step-meta-value"><?php esc_html_e( 'Unlimited', 'latepoint' ); ?></span>
						<?php elseif ( $available <= 0 ) : ?>
							<span class="event-step-meta-value event-sold-out"><?php esc_html_e( 'Sold Out', 'latepoint' ); ?></span>
						<?php else : ?>
							<span class="event-step-meta-value">
								<?php
								printf(
									/* translators: %d: number of spots available */
									esc_html__( '%d spot(s) remaining', 'latepoint' ),
									$available
								);
								?>
							</span>
						<?php endif; ?>
					</div>

					<?php
					$display_location   = $event->get_display_location_name();
					$location_label     = $display_location ?: ( $event->event_type === LATEPOINT_EVENT_TYPE_ONLINE ? __( 'Online Event', 'latepoint' ) : '' );
					$display_organizer  = $event->get_display_organizer_name();
					?>
					<?php if ( $location_label && $display_organizer ) : ?>
						<div class="event-step-meta-cell">
							<span class="event-step-meta-label"><?php esc_html_e( 'Organizer', 'latepoint' ); ?></span>
							<span class="event-step-meta-value"><?php echo esc_html( $display_organizer ); ?></span>
						</div>
						<div class="event-step-meta-cell">
							<span class="event-step-meta-label"><?php esc_html_e( 'Location', 'latepoint' ); ?></span>
							<span class="event-step-meta-value"><?php echo esc_html( $location_label ); ?></span>
						</div>
					<?php elseif ( $location_label ) : ?>
						<div class="event-step-meta-cell event-step-meta-cell-full">
							<span class="event-step-meta-label"><?php esc_html_e( 'Location', 'latepoint' ); ?></span>
							<span class="event-step-meta-value"><?php echo esc_html( $location_label ); ?></span>
						</div>
					<?php elseif ( $display_organizer ) : ?>
						<div class="event-step-meta-cell event-step-meta-cell-full">
							<span class="event-step-meta-label"><?php esc_html_e( 'Organizer', 'latepoint' ); ?></span>
							<span class="event-step-meta-value"><?php echo esc_html( $display_organizer ); ?></span>
						</div>
					<?php endif; ?>

				</div>
			</div>

		</div><!-- /.event-step-w -->

	<?php endif; ?>

	<?php
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'after' );
	do_action( 'latepoint_after_step_content', $current_step_code );
	?>
</div>
