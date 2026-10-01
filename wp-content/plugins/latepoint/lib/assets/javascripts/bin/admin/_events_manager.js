/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

function latepoint_init_events_manager_form(){
  // Event capacity accepts blank, 0, or a positive number as-is -- blank and 0 both mean
  // unlimited, so neither gets rewritten. Only genuinely invalid input (negative, unparsable)
  // gets reset, to the minimum real capacity (1).
  jQuery('body.latepoint-admin').on('change blur', '#event_capacity', function(){
    const val = jQuery(this).val();
    if(val === '') return;
    const num = parseInt(val, 10);
    if(isNaN(num) || num < 0){
      jQuery(this).val(1);
    }
  });
}
