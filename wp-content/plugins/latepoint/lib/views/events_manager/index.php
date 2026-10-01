<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
	<?php if ( $uncategorized_events ) : ?>
		<div class="os-item-category-w">
			<div class="os-form-sub-header sub-level"><h3><?php esc_html_e( 'Uncategorized', 'latepoint' ); ?></h3></div>
			<div class="os-events-list os-resources-grid">
				<?php foreach ( $uncategorized_events as $event ) : ?>
					<?php include LATEPOINT_VIEWS_ABSPATH . 'events_manager/_event_index_item.php'; ?>
				<?php endforeach; ?>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<?php echo OsUtilHelper::add_resource_link_html( __( 'New Event', 'latepoint' ), OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'events_manager', 'new_form' ) ) ); ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $event_categories ) : ?>
		<?php foreach ( $event_categories as $event_category ) : ?>
			<div class="os-item-category-w">
				<div class="os-form-sub-header sub-level"><h3><?php echo esc_html( $event_category->name ); ?></h3></div>
				<div class="os-events-list os-resources-grid">
					<?php
					// All events in this category regardless of status — draft/cancelled ones are
					// visually distinguished by _event_index_item.php's own os-event-status-* class,
					// not by being split into a separate section.
					$category_events = $event_category->get_events();
					if ( $category_events ) :
						foreach ( $category_events as $event ) :
							include LATEPOINT_VIEWS_ABSPATH . 'events_manager/_event_index_item.php';
						endforeach;
					endif;
					?>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<?php echo OsUtilHelper::add_resource_link_html( __( 'New Event', 'latepoint' ), OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'events_manager', 'new_form' ), [ 'category_id' => $event_category->id ] ) ); ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	<?php else : ?>
		<?php if ( ! $uncategorized_events ) : ?>
			<div class="no-results-w">
				<div class="icon-w"><i class="latepoint-icon latepoint-icon-calendar"></i></div>
				<h2><?php esc_html_e( 'No Events Found', 'latepoint' ); ?></h2>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'events_manager', 'new_form' ) ) ); ?>" class="latepoint-btn">
						<i class="latepoint-icon latepoint-icon-plus-square"></i>
						<span><?php esc_html_e( 'Add Event', 'latepoint' ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>
