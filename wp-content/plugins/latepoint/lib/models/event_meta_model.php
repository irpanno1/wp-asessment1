<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! class_exists( 'OsEventMetaModel' ) ) :

	class OsEventMetaModel extends OsMetaModel {

		public function __construct( $id = false ) {
			parent::__construct();
			$this->table_name = LATEPOINT_TABLE_EVENT_META;

			if ( $id ) {
				$this->load_by_id( $id );
			}
		}
	}

endif;
