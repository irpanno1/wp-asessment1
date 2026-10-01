<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OsEventCategoriesController' ) ) :

	class OsEventCategoriesController extends OsController {

		public function __construct() {
			parent::__construct();

			$this->views_folder            = LATEPOINT_VIEWS_ABSPATH . 'event_categories/';
			$this->vars['page_header']     = OsMenuHelper::get_menu_items_by_id( 'events' );
			$this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id( 'events' );
			$this->vars['breadcrumbs'][]   = [
				'label' => __( 'Event Categories', 'latepoint' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'event_categories', 'index' ) ),
			];
		}

		// ─── Index ────────────────────────────────────────────────────────

		public function index() {
			$this->vars['categories'] = ( new OsEventCategoryModel() )->order_by( 'order_number asc' )->get_results_as_models();

			$category_ids                           = array_map( function ( $category ) { return $category->id; }, $this->vars['categories'] );
			$this->vars['event_counts_by_category'] = OsEventCategoryModel::get_event_counts_for_category_ids( $category_ids );

			$this->format_render( __FUNCTION__ );
		}

		// ─── Create ───────────────────────────────────────────────────────

		public function create() {
			if ( $this->events_functionality_disabled() ) {
				return;
			}
			$this->check_nonce( 'new_event_category' );

			$category = new OsEventCategoryModel();
			$category->set_data( $this->params['event_category'] );

			if ( $category->save() ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Category created. ID: ', 'latepoint' ) . $category->id;
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = $category->get_error_messages();
			}

			$this->send_json(
				[
					'status'  => $status,
					'message' => $response_html,
				]
			);
		}

		// ─── Update ───────────────────────────────────────────────────────

		public function update() {
			if ( empty( $this->params['event_category']['id'] ) || ! filter_var( $this->params['event_category']['id'], FILTER_VALIDATE_INT ) ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Invalid Category ID', 'latepoint' ),
					]
				);
				return;
			}

			if ( $this->events_functionality_disabled() ) {
				return;
			}
			$this->check_nonce( 'edit_event_category_' . $this->params['event_category']['id'] );

			$category = new OsEventCategoryModel( (int) $this->params['event_category']['id'] );
			if ( ! $category->id ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Category not found', 'latepoint' ),
					]
				);
				return;
			}
			$category->set_data( $this->params['event_category'] );

			if ( $category->save() ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Category updated. ID: ', 'latepoint' ) . $category->id;
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = $category->get_error_messages();
			}

			$this->send_json(
				[
					'status'  => $status,
					'message' => $response_html,
				]
			);
		}

		// ─── Destroy ─────────────────────────────────────────────────────

		public function destroy() {
			if ( empty( $this->params['id'] ) || ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Invalid Category ID', 'latepoint' ),
					]
				);
				return;
			}

			if ( $this->events_functionality_disabled() ) {
				return;
			}
			$this->check_nonce( 'destroy_event_category_' . $this->params['id'] );

			$category = new OsEventCategoryModel( (int) $this->params['id'] );

			if ( $category->delete() ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Category removed', 'latepoint' );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error removing category', 'latepoint' );
			}

			$this->send_json(
				[
					'status'  => $status,
					'message' => $response_html,
				]
			);
		}

		/**
		 * Guards the mutating admin actions (create/update/destroy) against being reachable while
		 * the "Enable Events Functionality" toggle is off — the Events menu is hidden in that case
		 * (see OsMenuHelper::get_side_menu_items()), but routing has no allow-list, so these
		 * actions were otherwise still callable directly by route_name.
		 */
		private function events_functionality_disabled(): bool {
			if ( OsSettingsHelper::is_on( 'enable_events_functionality' ) ) {
				return false;
			}
			$this->send_json(
				[
					'status'  => LATEPOINT_STATUS_ERROR,
					'message' => __( 'Events functionality is disabled.', 'latepoint' ),
				]
			);
			return true;
		}
	}

endif;
