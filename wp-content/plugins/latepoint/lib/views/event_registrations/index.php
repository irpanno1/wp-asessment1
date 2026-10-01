<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/* @var $events OsEventModel[] */
/* @var $registrations OsEventRegistrationModel[] */
/* @var $total_pages int */
/* @var $total_records int */
/* @var $total_attendees int */
/* @var $total_confirmed int */
/* @var $showing_from int */
/* @var $showing_to int */
/* @var $current_page_number int */
/* @var $customer_query string */
/* @var $email_query string */
/* @var $registration_code_query string */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Build event filter options using value/label pairs so select_field() renders correct option values.
$event_filter_options = [ [ 'value' => '', 'label' => __( 'All Events', 'latepoint' ) ] ];
foreach ( $events as $ev ) {
	$event_filter_options[] = [ 'value' => $ev->id, 'label' => $ev->name ];
}

$status_filter_options = [
	''                                             => __( 'All Statuses', 'latepoint' ),
	LATEPOINT_EVENT_REGISTRATION_STATUS_PENDING    => __( 'Pending', 'latepoint' ),
	LATEPOINT_EVENT_REGISTRATION_STATUS_CONFIRMED  => __( 'Confirmed', 'latepoint' ),
	LATEPOINT_EVENT_REGISTRATION_STATUS_CANCELLED  => __( 'Cancelled', 'latepoint' ),
	LATEPOINT_EVENT_REGISTRATION_STATUS_CHECKED_IN => __( 'Checked In', 'latepoint' ),
];
?>

<?php if ( ! OsSettingsHelper::is_on( 'enable_events_functionality' ) ) : ?>
	<?php
	OsUtilHelper::render_feature_disabled_notice(
		/* translators: %1$s and %2$s are opening and closing anchor tags */
		__( 'Events functionality is currently disabled. You can enable it in %1$sGeneral Settings%2$s.', 'latepoint' ),
		'stickySectionEvents'
	);
	?>
<?php else : ?>
<div class="table-with-pagination-w has-scrollable-table">
	<div class="os-pagination-w with-actions">
		<div class="table-heading-w">
			<h2 class="table-heading"><?php esc_html_e( 'Registrations', 'latepoint' ); ?></h2>
			<div class="pagination-info">
				<?php
				echo esc_html__( 'Showing', 'latepoint' ) . ' <span class="os-pagination-from">' . esc_html( (string) $showing_from ) . '</span>&#8211;<span class="os-pagination-to">' . esc_html( (string) $showing_to ) . '</span> ' . esc_html__( 'of', 'latepoint' ) . ' <span class="os-pagination-total">' . esc_html( (string) $total_records ) . '</span>';
				?>
				&nbsp;&middot;&nbsp;
				<span class="os-pagination-attendees"><?php echo esc_html( (string) $total_attendees ); ?></span> <?php esc_html_e( 'attendees', 'latepoint' ); ?>
				&nbsp;&middot;&nbsp;
				<span class="os-pagination-confirmed"><?php echo esc_html( (string) $total_confirmed ); ?></span> <?php esc_html_e( 'confirmed', 'latepoint' ); ?>
			</div>
		</div>
		<div class="mobile-table-actions-trigger"><i class="latepoint-icon latepoint-icon-more-horizontal"></i></div>
		<div class="table-actions">
			<?php if ( OsSettingsHelper::can_download_records_as_csv() ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( OsRouterHelper::build_admin_post_link( OsRouterHelper::build_route_name( 'event_registrations', 'index' ) ), 'event_registrations_csv_export', '_wpnonce' ) ); ?>"
				   target="_blank"
				   class="latepoint-btn latepoint-btn-outline latepoint-btn-grey download-csv-with-filters">
					<i class="latepoint-icon latepoint-icon-upload"></i>
					<span><?php esc_html_e( 'Export .csv', 'latepoint' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="os-scrollable-table-w">
		<div class="os-table-w os-table-compact">
			<table class="os-table os-scrollable-table" data-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'event_registrations', 'index' ) ); ?>">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Code', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Event', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Name', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Email', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Qty', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Status', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Payment', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'latepoint' ); ?></th>
					</tr>
					<tr>
						<th><?php echo OsFormHelper::text_field( 'filter[id]', false, '', [ 'style' => 'width: 40px;', 'class' => 'os-table-filter', 'placeholder' => __( 'ID', 'latepoint' ) ] ); ?></th>
						<th><?php echo OsFormHelper::text_field( 'filter[registration_code]', false, isset( $registration_code_query ) ? $registration_code_query : '', [ 'class' => 'os-table-filter', 'placeholder' => __( 'Code...', 'latepoint' ) ] ); ?></th>
						<th><?php echo OsFormHelper::select_field( 'filter[event_id]', false, $event_filter_options, '', [ 'class' => 'os-table-filter' ] ); ?></th>
						<th><?php echo OsFormHelper::text_field( 'filter[customer]', false, isset( $customer_query ) ? $customer_query : '', [ 'class' => 'os-table-filter', 'placeholder' => __( 'Search by Name', 'latepoint' ) ] ); ?></th>
						<th><?php echo OsFormHelper::text_field( 'filter[email]', false, isset( $email_query ) ? $email_query : '', [ 'class' => 'os-table-filter', 'placeholder' => __( 'Search by Email', 'latepoint' ) ] ); ?></th>
						<th></th>
						<th><?php echo OsFormHelper::select_field( 'filter[status]', false, $status_filter_options, '', [ 'class' => 'os-table-filter' ] ); ?></th>
						<th></th>
						<th>
							<div class="os-form-group">
								<div class="os-date-range-picker os-table-filter-datepicker"
								     data-can-be-cleared="yes"
								     data-no-value-label="<?php esc_attr_e( 'Filter By Date', 'latepoint' ); ?>"
								     data-clear-btn-label="<?php esc_attr_e( 'Reset Date Filtering', 'latepoint' ); ?>">
									<span class="range-picker-value"><?php esc_html_e( 'Filter By Date', 'latepoint' ); ?></span>
									<i class="latepoint-icon latepoint-icon-chevron-down"></i>
									<input type="hidden" class="os-table-filter os-datepicker-date-from" name="filter[registration_date_from]" value=""/>
									<input type="hidden" class="os-table-filter os-datepicker-date-to" name="filter[registration_date_to]" value=""/>
								</div>
							</div>
						</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php include( '_table_body.php' ); ?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php esc_html_e( 'ID', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Code', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Event', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Name', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Email', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Qty', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Status', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Payment', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'latepoint' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'latepoint' ); ?></th>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>

	<div class="os-pagination-w">
		<div class="pagination-info">
			<?php
			echo esc_html__( 'Showing', 'latepoint' ) . ' <span class="os-pagination-from">' . esc_html( (string) $showing_from ) . '</span>&#8211;<span class="os-pagination-to">' . esc_html( (string) $showing_to ) . '</span> ' . esc_html__( 'of', 'latepoint' ) . ' <span class="os-pagination-total">' . esc_html( (string) $total_records ) . '</span>';
			?>
		</div>
		<div class="pagination-page-select-w">
			<label for="tablePaginationPageSelector"><?php esc_html_e( 'Page:', 'latepoint' ); ?></label>
			<select id="tablePaginationPageSelector" name="page" class="pagination-page-select">
				<?php
				for ( $i = 1; $i <= $total_pages; $i++ ) {
					$selected = ( $current_page_number === $i ) ? 'selected' : '';
					echo '<option ' . esc_html( $selected ) . '>' . esc_html( (string) $i ) . '</option>';
				}
				?>
			</select>
		</div>
	</div>
</div>
<?php endif; ?>
