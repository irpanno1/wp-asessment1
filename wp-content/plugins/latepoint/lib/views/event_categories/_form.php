<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/**
 * Shared create/update form for an event category, included inline inside the card's
 * .os-category-body by OsEventCategoriesController::index() (see events_categories/index.php) —
 * toggled open/closed via the .editing class (admin.js's generic category-card handler).
 *
 * @var OsEventCategoryModel $category
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action = $category->is_new_record() ? 'create' : 'update';
?>
<div class="os-form-w">
	<form data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'event_categories', $action ) ); ?>"
	      data-os-success-action="reload">
		<?php echo OsFormHelper::text_field( 'event_category[name]', __( 'Category Name', 'latepoint' ), $category->name ); ?>
		<?php echo OsFormHelper::textarea_field( 'event_category[short_description]', __( 'Description', 'latepoint' ), $category->short_description, [ 'rows' => 2 ] ); ?>
		<?php echo OsFormHelper::number_field( 'event_category[order_number]', __( 'Display Order', 'latepoint' ), $category->order_number, 0 ); ?>
		<?php if ( ! $category->is_new_record() ) : ?>
			<?php echo OsFormHelper::hidden_field( 'event_category[id]', $category->id ); ?>
		<?php endif; ?>
		<div class="os-form-buttons os-flex">
			<?php echo OsFormHelper::button( 'submit', __( 'Save Category', 'latepoint' ), 'submit', [ 'class' => 'latepoint-btn' ] ); ?>
			<?php if ( $category->is_new_record() ) : ?>
				<a href="#" class="latepoint-btn latepoint-btn-secondary add-item-category-trigger">
					<?php esc_html_e( 'Cancel', 'latepoint' ); ?>
				</a>
			<?php else : ?>
				<a href="#" class="latepoint-btn latepoint-btn-danger" style="margin-left: auto;"
				   data-os-prompt="<?php esc_attr_e( 'Are you sure you want to remove this category? Its events will become Uncategorized.', 'latepoint' ); ?>"
				   data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'event_categories', 'destroy' ) ); ?>"
				   data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( [ 'id' => $category->id ], 'destroy_event_category_' . $category->id ) ); ?>"
				   data-os-success-action="reload">
					<?php esc_html_e( 'Delete Category', 'latepoint' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php wp_nonce_field( $category->is_new_record() ? 'new_event_category' : 'edit_event_category_' . $category->id ); ?>
	</form>
</div>
