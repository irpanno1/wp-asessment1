<?php
/**
 * Front-end events list — rendered by [latepoint_events_list] shortcode.
 *
 * @var OsEventModel[] $events
 * @var string         $image_mode      'thumbnail' | 'date' | 'none'
 * @var bool           $show_seats_left
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="latepoint-events-list-w">
	<?php if ( empty( $events ) ) : ?>
		<p class="latepoint-events-empty"><?php esc_html_e( 'No upcoming events found.', 'latepoint' ); ?></p>
	<?php else : ?>
		<div class="latepoint-events-list">
			<?php foreach ( $events as $event ) : ?>
				<div class="latepoint-event-list-item">

					<?php // get_featured_image_url() always returns a URL (falls back to a generic
					// placeholder graphic when no image is set) — check featured_image_id itself
					// so the image slot is hidden entirely, rather than filled with a placeholder. ?>
					<?php if ( 'thumbnail' === $image_mode && $event->featured_image_id ) : ?>
						<div class="latepoint-event-list-item-image">
							<img src="<?php echo esc_url( $event->get_featured_image_url( 'medium' ) ); ?>" alt="<?php echo esc_attr( $event->name ); ?>">
						</div>
					<?php elseif ( 'date' === $image_mode && $event->start_datetime_utc ) : ?>
						<div class="latepoint-event-list-item-date">
							<span class="latepoint-event-list-item-date-month"><?php echo esc_html( OsTimeHelper::date_from_db( $event->start_datetime_utc, 'M', OsTimeHelper::get_wp_timezone_name() ) ); ?></span>
							<span class="latepoint-event-list-item-date-day"><?php echo esc_html( OsTimeHelper::date_from_db( $event->start_datetime_utc, 'd', OsTimeHelper::get_wp_timezone_name() ) ); ?></span>
						</div>
					<?php endif; ?>

					<div class="latepoint-event-list-item-body">

						<div class="latepoint-event-title-row">
							<h3 class="latepoint-event-title"><?php echo esc_html( $event->name ); ?></h3>

							<?php if ( LATEPOINT_EVENT_TYPE_ONLINE === $event->event_type ) : ?>
								<span class="latepoint-event-type-badge latepoint-event-type-online"><?php esc_html_e( 'Online', 'latepoint' ); ?></span>
							<?php elseif ( LATEPOINT_EVENT_TYPE_IN_PERSON === $event->event_type ) : ?>
								<span class="latepoint-event-type-badge latepoint-event-type-in-person"><?php esc_html_e( 'In Person', 'latepoint' ); ?></span>
							<?php endif; ?>
						</div>

						<?php if ( $event->summary ) : ?>
							<p class="latepoint-event-summary"><?php echo esc_html( $event->summary ); ?></p>
						<?php endif; ?>

						<?php
						// Written out (e.g. "5 September 2026") rather than the site-wide numeric
						// date format setting, which reads too terse for a public events list.
						$date_part = $event->start_datetime_utc ? OsTimeHelper::date_from_db( $event->start_datetime_utc, 'j F Y', OsTimeHelper::get_wp_timezone_name() ) : '';
						$time_part = $event->get_formatted_start_time();
						$place     = $event->get_display_location_name();
						if ( ! $place && LATEPOINT_EVENT_TYPE_ONLINE === $event->event_type ) {
							$place = __( 'Online', 'latepoint' );
						}
						?>
						<?php if ( $date_part || $place ) : ?>
							<div class="latepoint-event-meta">
								<?php if ( $date_part ) : ?>
									<span>
										<?php echo esc_html( $date_part ); ?>
										<?php if ( $time_part ) : ?>
											&middot; <?php echo esc_html( $time_part ); ?>
										<?php endif; ?>
									</span>
								<?php endif; ?>
								<?php if ( $date_part && $place ) : ?>
									<span class="latepoint-event-meta-dot"></span>
								<?php endif; ?>
								<?php if ( $place ) : ?>
									<span><?php echo esc_html( $place ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

					</div>

					<div class="latepoint-event-list-item-actions">
						<div class="latepoint-event-price">
							<?php echo esc_html( $event->get_formatted_price() ); ?>
						</div>

						<?php if ( $event->is_sold_out() ) : ?>
							<button class="latepoint-event-sold-out latepoint-btn latepoint-btn-secondary" disabled>
								<?php esc_html_e( 'Sold Out', 'latepoint' ); ?>
							</button>
						<?php elseif ( $event->is_registration_open() ) : ?>
							<button class="latepoint-event-register-btn latepoint-btn latepoint-btn-primary"
							        data-event-id="<?php echo esc_attr( (string) $event->id ); ?>">
								<?php esc_html_e( 'Register Now', 'latepoint' ); ?>
							</button>
							<?php if ( $show_seats_left && $event->has_capacity_limit() ) : ?>
								<div class="latepoint-event-seats-left">
									<?php
									printf(
										/* translators: %d: available capacity */
										esc_html__( '%d spot(s) remaining', 'latepoint' ),
										$event->get_available_capacity()
									);
									?>
								</div>
							<?php endif; ?>
						<?php else : ?>
							<button class="latepoint-event-unavailable latepoint-btn latepoint-btn-secondary" disabled>
								<?php esc_html_e( 'Registration Closed', 'latepoint' ); ?>
							</button>
						<?php endif; ?>
					</div>

				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
