<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! class_exists( 'OsEventCategoryModel' ) ) :

	class OsEventCategoryModel extends OsModel {

		public $id;
		public $name;
		public $short_description;
		public $order_number;
		public $status;
		public $created_at;
		public $updated_at;

		public function __construct( $id = false ) {
			parent::__construct();
			$this->table_name = LATEPOINT_TABLE_EVENT_CATEGORIES;
			$this->nice_names = [ 'name' => __( 'Name', 'latepoint' ) ];

			if ( $id ) {
				$this->load_by_id( $id );
			}
		}

		public function get_events(): array {
			if ( ! isset( $this->events ) ) {
				$events       = new OsEventModel();
				$this->events = $events->where( [ 'category_id' => $this->id ] )->order_by( 'start_datetime_utc asc' )->get_results_as_models();
			}

			return $this->events;
		}

		public function get_active_events(): array {
			if ( ! isset( $this->active_events ) ) {
				$events              = new OsEventModel();
				$this->active_events = $events->should_be_published()->where( [ 'category_id' => $this->id ] )->order_by( 'start_datetime_utc asc' )->get_results_as_models();
			}

			return $this->active_events;
		}

		/**
		 * Batch version of count( $category->get_events() ) for the categories index page, which
		 * would otherwise hydrate every event row of every category just to count them. Returns
		 * category_id => event count; a category with no events is simply absent from the result
		 * (treat a missing key as 0).
		 *
		 * @param int[] $category_ids
		 * @return array<int, int>
		 */
		public static function get_event_counts_for_category_ids( array $category_ids ): array {
			$category_ids = array_filter( array_map( 'absint', $category_ids ) );
			if ( ! $category_ids ) {
				return [];
			}
			global $wpdb;
			$ids_placeholder = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
			$rows            = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT category_id, COUNT(*) as event_count FROM ' . LATEPOINT_TABLE_EVENTS .
					" WHERE category_id IN ({$ids_placeholder}) GROUP BY category_id",
					$category_ids
				)
			);

			$counts = [];
			foreach ( $rows as $row ) {
				$counts[ (int) $row->category_id ] = (int) $row->event_count;
			}

			return $counts;
		}

		public function should_be_active() {
			return $this->where( [ 'status' => 'active' ] );
		}

		/**
		 * Deletes the category and reassigns its events to "Uncategorized" in one pass — mirrors
		 * OsServiceCategoryModel::delete()'s single-query pattern. Reassignment only runs once the
		 * delete itself has actually succeeded (unlike a delete-then-hope-for-the-best ordering),
		 * and never loads/saves the events as models.
		 */
		public function delete( $id = false ) {
			if ( ! $id && isset( $this->id ) ) {
				$id = $this->id;
			}
			if ( $id && $this->db->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) ) ) {
				$this->db->update( LATEPOINT_TABLE_EVENTS, array( 'category_id' => 0 ), array( 'category_id' => $id ), array( '%d' ) );
				return true;
			}

			return false;
		}

		protected function allowed_params( $role = 'admin' ) {
			return [
				'id',
				'name',
				'short_description',
				'order_number',
				'status',
			];
		}

		protected function params_to_save( $role = 'admin' ) {
			return [
				'id',
				'name',
				'short_description',
				'order_number',
				'status',
			];
		}

		protected function properties_to_validate() {
			return [
				'name' => [ 'presence' ],
			];
		}

		protected function set_defaults() {
			if ( empty( $this->status ) ) {
				$this->status = 'active';
			}
			if ( ! isset( $this->order_number ) || '' === $this->order_number ) {
				$this->order_number = 0;
			}
		}
	}

endif;
