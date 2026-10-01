<?php
/**
 * Event wizard step view — ticket quantity selector, shown after the event info step.
 *
 * @var OsEventModel|null $event
 * @var string            $current_step_code
 * @var array             $presets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="step-event-w latepoint-step-content"
     data-step-code="<?php echo esc_attr( $current_step_code ); ?>"
     data-next-btn-label="<?php echo esc_attr( OsStepsHelper::get_next_btn_label_for_step( $current_step_code ) ); ?>">
	<?php
	do_action( 'latepoint_before_step_content', $current_step_code );
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'before' );
	?>

	<?php if ( ! $event || ! $event->id ) : ?>
		<div class="os-error-message">
			<p><?php esc_html_e( 'Event not found. Please go back and try again.', 'latepoint' ); ?></p>
		</div>
	<?php else : ?>

		<div class="event-step-w">

			<?php
			/**
			 * Replace the default single quantity selector below with a custom selector — used
			 * by Pro's Ticket Types to render one priced row (with its own quantity stepper) per
			 * ticket type instead of one flat quantity for the whole event.
			 *
			 * @param {string}       $selector_html Empty string by default (renders the built-in selector).
			 * @param {OsEventModel} $event          The event being registered for.
			 * @returns {string} The filtered selector HTML. Non-empty replaces the default markup entirely.
			 *
			 * @since 5.7.0
			 * @hook latepoint_event_tickets_step_selector_html
			 *
			 */
			$custom_selector = apply_filters( 'latepoint_event_tickets_step_selector_html', '', $event );
			if ( $custom_selector ) :
				echo $custom_selector; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built, already-escaped markup from a trusted addon filter.
			else :
				// Quantity selector
				$max_qty = $event->has_capacity_limit() ? min( $event->get_available_capacity(), 10 ) : 10;
				$max_qty = max( 1, $max_qty );
				?>
				<div class="event-ticket-selector-w">
					<div class="event-ticket-selector-label">
						<h4><?php esc_html_e( 'Number of Tickets', 'latepoint' ); ?></h4>
						<?php if ( $event->has_capacity_limit() ) : ?>
							<div class="event-step-qty-sublabel">
								<?php printf( esc_html__( 'Maximum %d per order', 'latepoint' ), $max_qty ); ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="event-ticket-qty-w"
					     data-min-capacity="1"
					     data-max-capacity="<?php echo esc_attr( (string) $max_qty ); ?>">
						<div class="event-ticket-qty-btn event-ticket-qty-btn-minus">
							<i class="latepoint-icon latepoint-icon-minus"></i>
						</div>
						<input type="number"
						       tabindex="0"
						       min="1"
						       max="<?php echo esc_attr( (string) $max_qty ); ?>"
						       data-summary-singular="<?php esc_attr_e( 'Ticket', 'latepoint' ); ?>"
						       data-summary-plural="<?php esc_attr_e( 'Tickets', 'latepoint' ); ?>"
						       name="event_quantity"
						       class="event-ticket-qty-input"
						       value="1"
						       placeholder="<?php esc_attr_e( 'Qty', 'latepoint' ); ?>">
						<div class="event-ticket-qty-btn event-ticket-qty-btn-plus">
							<i class="latepoint-icon latepoint-icon-plus"></i>
						</div>
					</div>
				</div><!-- /.event-ticket-selector-w -->
			<?php endif; ?>

		</div><!-- /.event-step-w -->

	<?php endif; ?>

	<?php
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'after' );
	do_action( 'latepoint_after_step_content', $current_step_code );
	?>
</div>
