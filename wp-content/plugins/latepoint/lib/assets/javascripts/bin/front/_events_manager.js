/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */
class LatepointEventsFrontFeature {

  constructor() {
    this.ready();
  }

  ready() {
    jQuery(document).ready(() => {

      // ── Register button click ─────────────────────────────────────────────
      jQuery(document).on('click', '.latepoint-event-register-btn', (e) => {
        e.preventDefault();
        const $btn    = jQuery(e.currentTarget);
        const eventId = $btn.data('event-id');
        if (!eventId) {return;}

        $btn.addClass('os-loading').prop('disabled', true);

        // Step 1: store event in session, validate registration open
        jQuery.ajax({
          type: 'POST',
          dataType: 'json',
          url: latepoint_helper.ajaxurl,
          data: {
            action: latepoint_helper.route_action,
            route_name: 'events_manager__pre_register',
            params: { event_id: eventId },
            return_format: 'json'
          },
          success: (resp) => {
            if (resp.status === 'success') {
              // Step 2: open the booking wizard with event presets
              jQuery.ajax({
                type: 'POST',
                dataType: 'json',
                url: latepoint_helper.ajaxurl,
                data: {
                  action: latepoint_helper.route_action,
                  route_name: latepoint_helper.booking_button_route,
                  params: { presets: { booking_intent: 'event', selected_event_id: eventId } },
                  layout: 'none',
                  return_format: 'json'
                },
                success: (data) => {
                  $btn.removeClass('os-loading').prop('disabled', false);
                  if (data.status === 'success') {
                    latepoint_show_data_in_lightbox(data.message, 'booking-form-in-lightbox', false);
                    const $el = jQuery('.latepoint-lightbox-w .latepoint-booking-form-element');
                    jQuery('body').addClass('latepoint-lightbox-active');
                    latepoint_init_booking_form($el);
                    latepoint_init_step(data.step, $el);
                  }
                },
                error: () => {
                  $btn.removeClass('os-loading').prop('disabled', false);
                }
              });
            } else {
              $btn.removeClass('os-loading').prop('disabled', false);
              alert(resp.message || latepoint_helper.i18n.registration_unavailable || 'Registration unavailable.');
            }
          },
          error: () => {
            $btn.removeClass('os-loading').prop('disabled', false);
          }
        });
      });

      // ── Ticket selection step: quantity +/- selector ─────────────────────
      const ticketsStepCode = 'booking__event_tickets';
      jQuery('body').on('latepoint:initStep:' + ticketsStepCode, '.latepoint-booking-form-element', (e) => {
        const $step = jQuery('.latepoint-step-content[data-step-code="' + ticketsStepCode + '"]');

        $step.on('change', '.event-ticket-qty-input', function () {
          const $w          = jQuery(this).closest('.event-ticket-qty-w');
          // Scoped to this row (present for both the default single selector and each Pro
          // ticket-type row) so the "max per order" notice never leaks onto/concatenates with
          // another ticket type's row when there are 2+ types on the same step.
          const $row        = jQuery(this).closest('.event-ticket-selector-w');
          const maxAttr     = $w.data('max-capacity');
          // A sold-out row's max-capacity is a real 0 — `Number(...) || Infinity` would coerce
          // that falsy 0 into Infinity and remove the clamp exactly where it matters most.
          const maxCapacity = maxAttr === undefined ? Infinity : Number(maxAttr);
          const minCapacity = $w.data('min-capacity') !== undefined ? Number($w.data('min-capacity')) : 1;
          const requested   = Number(jQuery(this).val());
          const newVal      = Math.min(maxCapacity, Math.max(minCapacity, requested));
          jQuery(this).val(newVal);

          // Surface a notice when the requested quantity exceeds the per-order cap.
          const $notice = $row.find('.event-step-qty-notice');
          if (requested > maxCapacity && maxCapacity !== Infinity) {
            const msg = $row.find('.event-step-qty-sublabel').first().text() ||
                        'Maximum ' + maxCapacity + ' per order';
            if ($notice.length) {
              $notice.text(msg).show();
            } else {
              $w.after('<div class="event-step-qty-notice os-form-note os-form-note-error">' + msg + '</div>');
            }
          } else {
            $notice.hide();
          }

          // Persist the new quantity on the cart item, then reload the summary panel — same
          // live-update UX as the Total Attendees stepper (group bookings). The summary reads
          // price from the persisted cart item, not from form fields, so it has to be saved
          // server-side first before latepoint_reload_summary() will show the new total.
          const $booking_form_element = jQuery(this).closest('.latepoint-booking-form-element');
          const form_data = new FormData($booking_form_element.find('.latepoint-form')[0]);
          jQuery.ajax({
            type: 'post',
            dataType: 'json',
            url: latepoint_timestamped_ajaxurl(),
            data: {
              action: latepoint_helper.route_action,
              route_name: 'events_manager__update_ticket_quantity',
              params: latepoint_formdata_to_url_encoded_string(form_data),
              layout: 'none',
              return_format: 'json'
            }
          }).then(() => {
            latepoint_reload_summary($booking_form_element);
          });
        });

        $step.on('click', '.event-ticket-qty-btn', function () {
          const addVal      = jQuery(this).hasClass('event-ticket-qty-btn-plus') ? 1 : -1;
          const $w          = jQuery(this).closest('.event-ticket-qty-w');
          const maxAttr     = $w.data('max-capacity');
          const maxCapacity = maxAttr === undefined ? Infinity : Number(maxAttr);
          const minCapacity = $w.data('min-capacity') !== undefined ? Number($w.data('min-capacity')) : 1;
          const currentRaw  = $w.find('input.event-ticket-qty-input').val();
          const current     = currentRaw !== '' && !isNaN(currentRaw) ? Number(currentRaw) : minCapacity;
          const newVal      = Math.min(maxCapacity, Math.max(minCapacity, current + addVal));
          $w.find('input').val(newVal).trigger('change');
          return false;
        });
      });

    });
  }
}

window.latepointEventsFrontFeature = new LatepointEventsFrontFeature();
