<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/* @var $categories OsEventCategoryModel[] */
/* @var $event_counts_by_category array<int, int> */

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
	<div class="os-event-categories-w">
		<?php foreach ( $categories as $category ) : ?>
			<div class="os-category-w">
				<div class="os-category-head">
					<div class="os-category-name"><?php echo esc_html( $category->name ); ?></div>
					<div class="os-category-items-meta"><?php esc_html_e( 'ID: ', 'latepoint' ); ?><span><?php echo esc_html( $category->id ); ?></span></div>
					<div class="os-category-items-count"><span><?php echo esc_html( $event_counts_by_category[ $category->id ] ?? 0 ); ?></span> <?php esc_html_e( 'Events Linked', 'latepoint' ); ?></div>
					<button type="button" class="os-category-edit-btn"><i class="latepoint-icon latepoint-icon-edit-3"></i></button>
				</div>
				<div class="os-category-body">
					<?php include LATEPOINT_VIEWS_ABSPATH . 'event_categories/_form.php'; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="os-add-box add-item-category-box add-item-category-trigger">
			<div class="add-box-graphic-w">
				<div class="add-box-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
			</div>
			<div class="add-box-label"><?php esc_html_e( 'New Category', 'latepoint' ); ?></div>
		</div>
		<div class="os-form-w os-category-w editing os-new-item-category-form-w" style="display:none;">
			<div class="os-category-head">
				<div class="os-category-name"><?php esc_html_e( 'Create New Event Category', 'latepoint' ); ?></div>
			</div>
			<div class="os-category-body">
				<?php
				$category = new OsEventCategoryModel();
				include LATEPOINT_VIEWS_ABSPATH . 'event_categories/_form.php';
				?>
			</div>
		</div>
	</div>
<?php endif; ?>
