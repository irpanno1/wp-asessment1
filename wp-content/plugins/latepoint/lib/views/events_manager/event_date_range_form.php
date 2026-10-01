<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/**
 * "Add/Edit date range" lightbox for an event's schedule.
 * Trimmed copy of lib/views/settings/custom_day_schedule_form.php — reuses the same
 * lightbox shell, calendar widget, and Start/Finish time card, but always operates in
 * range mode (no Single Day/Date Range selector) and has no repeatable work periods,
 * since one OsEventDateModel row already stores one real start+end range.
 *
 * @var OsEventModel  $event
 * @var int           $date_range_id
 * @var OsWpDateTime  $target_date
 * @var string        $start_value
 * @var string        $end_value
 * @var int           $start_minutes
 * @var int           $end_minutes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="latepoint-lightbox-wrapper-form" action="" data-os-success-action="reload"
	  data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'events_manager', 'save_event_date_range' ) ); ?>">
	<?php wp_nonce_field( 'save_event_date_range_' . $event->id ); ?>
	<?php echo OsFormHelper::hidden_field( 'event_id', $event->id ); ?>
	<?php if ( $date_range_id ) : ?>
		<?php echo OsFormHelper::hidden_field( 'date_range_id', $date_range_id ); ?>
	<?php endif; ?>

	<div class="latepoint-lightbox-heading">
		<h2><?php echo $date_range_id ? esc_html__( 'Edit Date Range', 'latepoint' ) : esc_html__( 'Add Date Range', 'latepoint' ); ?></h2>
	</div>
	<div class="latepoint-lightbox-content">
		<div class="custom-day-schedule-w">
			<div class="custom-day-calendar" data-show-schedule="yes" data-period-type="range" data-picking="start">
				<div class="custom-day-settings-w">
					<?php
					// No visible Single Day/Date Range selector — an event range is always start+end
					// (a single day is just start=end) — but latepoint_init_custom_day_schedule()'s
					// click handler reads .period-type-selector's value to decide whether to
					// auto-advance to picking the end date, so it still needs to exist (fixed to "range").
					?>
					<input type="hidden" class="period-type-selector" value="range">
					<div class="start-day-input-w">
						<?php echo OsFormHelper::text_field( 'start_custom_date', false, $start_value, [ 'placeholder' => __( 'Pick a Start', 'latepoint' ), 'theme' => 'simple' ] ); ?>
					</div>
					<div class="end-day-input-w">
						<?php echo OsFormHelper::text_field( 'end_custom_date', false, $end_value, [ 'placeholder' => __( 'Pick an End', 'latepoint' ), 'theme' => 'simple' ] ); ?>
					</div>
				</div>
				<div class="custom-day-calendar-head">
					<h3 class="calendar-heading"
						data-label-single="<?php esc_attr_e( 'Pick a Date', 'latepoint' ); ?>"
						data-label-start="<?php esc_attr_e( 'Pick a Start Date', 'latepoint' ); ?>"
						data-label-end="<?php esc_attr_e( 'Pick an End Date', 'latepoint' ); ?>"><?php esc_html_e( 'Pick a Start Date', 'latepoint' ); ?></h3>
					<?php echo OsFormHelper::select_field( 'custom_day_calendar_month', false, OsUtilHelper::get_months_for_select(), $target_date->format( 'n' ) ); ?>
					<?php
					// Events are often scheduled further out than a recurring weekly agent/service
					// schedule, so this lightbox's year range is wider than the shared default (+1).
					$year_options = [];
					for ( $offset = 0; $offset <= 3; $offset++ ) {
						$year_options[] = (int) OsTimeHelper::today_date( 'Y' ) + $offset;
					}
					echo OsFormHelper::select_field( 'custom_day_calendar_year', false, $year_options, $target_date->format( 'Y' ) );
					?>
				</div>
				<div class="custom-day-calendar-month" data-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'calendars', 'load_monthly_calendar_days_only' ) ); ?>">
					<?php OsCalendarHelper::generate_monthly_calendar_days_only( $target_date->format( 'Y-m-d' ), $date_range_id ? $target_date->format( 'Y-m-d' ) : false ); ?>
				</div>
			</div>
			<div class="custom-day-schedule">
				<div class="custom-day-schedule-head">
					<h3><?php esc_html_e( 'Set Time', 'latepoint' ); ?></h3>
				</div>
				<div class="weekday-schedule-form active">
					<div class="ws-period">
						<?php echo OsFormHelper::time_field( 'start_time', __( 'Start', 'latepoint' ), $start_minutes, true ); ?>
						<?php echo OsFormHelper::time_field( 'end_time', __( 'Finish', 'latepoint' ), $end_minutes, true ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="latepoint-lightbox-footer"<?php if ( ! $date_range_id ) { echo ' style="display: none;"'; } ?>>
		<button type="submit" class="latepoint-btn latepoint-btn-block latepoint-btn-lg latepoint-btn-outline">
			<?php esc_html_e( 'Save Date Range', 'latepoint' ); ?>
		</button>
	</div>
</form>
