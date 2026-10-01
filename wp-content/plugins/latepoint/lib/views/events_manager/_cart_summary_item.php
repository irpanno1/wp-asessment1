<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/**
 * Cart / order summary box for a single event_registration cart item.
 *
 * Variables passed by OsEventsHooksHelper::render_event_cart_summary_items():
 *
 * @var string $name     Event display name.
 * @var int    $quantity Number of tickets selected.
 * @var float  $amount   Line total (quantity × unit price).
 * @var bool   $pending  True when a per-type selector (e.g. Pro's Ticket Types) is active for
 *                       this event but the customer hasn't picked any tickets yet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cart-item-wrapper single-item">
	<div class="summary-box main-box">
		<div class="summary-box-content os-cart-item">
			<div class="sbc-big-item"><?php echo esc_html( $name ); ?></div>
			<div class="sbc-highlighted-item">
				<?php if ( ! empty( $pending ) ) : ?>
					<?php esc_html_e( 'Select your tickets', 'latepoint' ); ?>
				<?php else : ?>
					<?php
					printf(
					/* translators: 1: number of tickets, 2: formatted total price */
						esc_html( _n( '%1$d Ticket &middot; %2$s', '%1$d Tickets &middot; %2$s', $quantity, 'latepoint' ) ),
						(int) $quantity,
						esc_html( OsMoneyHelper::format_price( $amount ) )
					);
					?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
