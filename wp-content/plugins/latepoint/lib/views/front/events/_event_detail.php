<?php
/**
 * Front-end event detail page — rendered by [latepoint_event id="X"] shortcode.
 *
 * @var OsEventModel $event
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="latepoint-event-detail-w">

	<!-- Header -->
	<div class="latepoint-event-detail-header">
		<?php $img = $event->get_featured_image_url( 'large' ); ?>
		<?php if ( $img ) : ?>
			<div class="latepoint-event-detail-image">
				<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $event->name ); ?>">
			</div>
		<?php endif; ?>

		<div class="latepoint-event-detail-headline">
			<?php if ( $event->category ) : ?>
				<div class="latepoint-event-detail-category">
					<?php echo esc_html( $event->category->name ); ?>
				</div>
			<?php endif; ?>
			<h1 class="latepoint-event-detail-title"><?php echo esc_html( $event->name ); ?></h1>
			<?php if ( $event->summary ) : ?>
				<p class="latepoint-event-detail-summary"><?php echo esc_html( $event->summary ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Main content -->
	<div class="latepoint-event-detail-body">

		<div class="latepoint-event-detail-main">
			<?php if ( $event->description ) : ?>
				<div class="latepoint-event-detail-description">
					<?php echo wp_kses_post( wpautop( $event->description ) ); ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Sidebar / info panel -->
		<div class="latepoint-event-detail-sidebar">
			<div class="latepoint-event-detail-info-card">

				<!-- Date & time -->
				<?php $event_dates = $event->get_dates(); ?>
				<?php if ( $event_dates ) : ?>
					<div class="latepoint-event-detail-info-row latepoint-event-detail-schedule-row">
						<span class="latepoint-icon latepoint-icon-clock"></span>
						<div class="latepoint-event-detail-schedule">
							<?php foreach ( $event_dates as $event_date ) : ?>
								<div class="latepoint-event-detail-schedule-item">
									<strong><?php echo esc_html( $event_date->get_formatted_date_range() ); ?></strong><br>
									<?php echo esc_html( $event_date->get_formatted_time_range() ); ?>
								</div>
							<?php endforeach; ?>
							<?php if ( $event->timezone ) : ?>
								<small>(<?php echo esc_html( $event->timezone ); ?>)</small>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Location -->
				<?php $display_location = $event->get_display_location_name(); ?>
				<?php if ( $display_location ) : ?>
					<div class="latepoint-event-detail-info-row">
						<span class="latepoint-icon latepoint-icon-location"></span>
						<div>
							<strong><?php echo esc_html( $display_location ); ?></strong>
							<?php if ( $event->get_display_location_address() ) : ?>
								<br><small><?php echo esc_html( $event->get_display_location_address() ); ?></small>
							<?php endif; ?>
						</div>
					</div>
				<?php elseif ( $event->event_type === LATEPOINT_EVENT_TYPE_ONLINE ) : ?>
					<div class="latepoint-event-detail-info-row">
						<span class="latepoint-icon latepoint-icon-link"></span>
						<div><strong><?php esc_html_e( 'Online Event', 'latepoint' ); ?></strong></div>
					</div>
				<?php endif; ?>

				<!-- Host -->
				<?php $display_host = $event->get_display_organizer_name(); ?>
				<?php if ( $display_host ) : ?>
					<div class="latepoint-event-detail-info-row">
						<span class="latepoint-icon latepoint-icon-user"></span>
						<div>
							<strong><?php esc_html_e( 'Hosted by', 'latepoint' ); ?></strong><br>
							<?php echo esc_html( $display_host ); ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Price -->
				<div class="latepoint-event-detail-info-row latepoint-event-detail-price-row">
					<span class="latepoint-icon latepoint-icon-tag"></span>
					<div>
						<strong class="latepoint-event-detail-price">
							<?php echo esc_html( $event->get_formatted_price() ); ?>
						</strong>
					</div>
				</div>

				<!-- Capacity / availability -->
				<?php if ( $event->has_capacity_limit() ) : ?>
					<div class="latepoint-event-detail-info-row">
						<span class="latepoint-icon latepoint-icon-users"></span>
						<div>
							<?php $available = $event->get_available_capacity(); ?>
							<?php if ( $available <= 0 ) : ?>
								<span class="latepoint-event-sold-out"><?php esc_html_e( 'Sold Out', 'latepoint' ); ?></span>
							<?php else : ?>
								<?php
								printf(
									/* translators: %d: number of spots remaining */
									esc_html__( '%d spot(s) remaining', 'latepoint' ),
									$available
								);
								?>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- CTA -->
				<div class="latepoint-event-detail-cta">
					<?php if ( $event->is_sold_out() ) : ?>
						<button class="latepoint-event-sold-out latepoint-btn latepoint-btn-secondary" disabled>
							<?php esc_html_e( 'Sold Out', 'latepoint' ); ?>
						</button>
					<?php elseif ( $event->is_registration_open() ) : ?>
						<button class="latepoint-event-register-btn latepoint-btn latepoint-btn-primary"
						        data-event-id="<?php echo esc_attr( (string) $event->id ); ?>">
							<?php esc_html_e( 'Register Now', 'latepoint' ); ?>
						</button>
					<?php else : ?>
						<button class="latepoint-event-unavailable latepoint-btn latepoint-btn-secondary" disabled>
							<?php esc_html_e( 'Registration Closed', 'latepoint' ); ?>
						</button>
					<?php endif; ?>
				</div>

			</div><!-- /.latepoint-event-detail-info-card -->
		</div>

	</div><!-- /.latepoint-event-detail-body -->
</div>
