<?php
/**
 * Generic "tips & resources" box — a header, a stacked list of doc links, and a support link.
 * Include with `include LATEPOINT_LIB_ABSPATH . 'views/partials/_help_box.php';` after setting:
 *
 * @var array  $help_links        Required. Array of ['label' => string, 'url' => string].
 * @var string $help_header_label Optional. Defaults to "Tips & Resources".
 * @var string $help_support_url  Optional. Defaults to https://latepoint.com/support/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( empty( $help_links ) ) {
	return;
}

$help_header_label = $help_header_label ?? __( 'Resources', 'latepoint' );
$help_support_url  = $help_support_url ?? 'https://latepoint.com/support/';
?>
<div class="latepoint-help-box">
	<div class="latepoint-help-box-header">
		<i class="latepoint-icon latepoint-icon-book"></i>
		<span><?php echo esc_html( $help_header_label ); ?></span>
	</div>
	<div class="latepoint-help-box-links">
		<?php foreach ( $help_links as $help_link ) : ?>
			<a href="<?php echo esc_url( $help_link['url'] ); ?>" class="latepoint-help-box-link" target="_blank" rel="noopener noreferrer">
				<span><?php echo esc_html( $help_link['label'] ); ?></span>
				<i class="latepoint-icon latepoint-icon-external-link"></i>
			</a>
		<?php endforeach; ?>
	</div>
	<div class="latepoint-help-box-support">
		<span class="latepoint-help-box-support-label"><?php esc_html_e( 'Need help?', 'latepoint' ); ?></span>
		<a href="<?php echo esc_url( $help_support_url ); ?>" class="latepoint-help-box-support-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Contact support', 'latepoint' ); ?></a>
	</div>
</div>
