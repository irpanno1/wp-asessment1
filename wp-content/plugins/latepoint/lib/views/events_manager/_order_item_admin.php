<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/**
 * Read-only admin order item row for a single event_registration order item.
 *
 * Variables passed by OsEventsHooksHelper::render_event_order_items_admin():
 *
 * @var string $name     Event display name.
 * @var int    $quantity Number of tickets.
 * @var float  $amount   Line total (quantity × unit price).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="order-item order-item-variant-event-registration">
	<div class="order-item-pill order-item-pill-variant-event">
		<div class="order-item-pill-inner">
			<div class="summary-box main-box">
				<div class="summary-box-content">
					<div class="sbc-big-item"><?php echo esc_html( $name ); ?></div>
					<div class="sbc-subtle-item">
						<?php
						printf(
							/* translators: 1: number of tickets, 2: formatted total price */
							esc_html( _n( '%1$d Ticket &middot; %2$s', '%1$d Tickets &middot; %2$s', $quantity, 'latepoint' ) ),
							(int) $quantity,
							esc_html( OsMoneyHelper::format_price( $amount ) )
						);
						?>
					</div>
				</div>
			</div>
			<div class="bundle-icon"><i class="latepoint-icon latepoint-icon-calendar2"></i></div>
		</div>
	</div>
</div>
