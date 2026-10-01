<?php
/**
 * Generic "Pro Feature" upgrade banner — rendered by OsProController's stub actions
 * (format_render('pro_feature', [], [], true)) and included directly by feature-specific
 * placeholders that want the same treatment with more specific copy.
 *
 * @var string|null $description Optional feature-specific message. Falls back to the generic
 *                                "over 30 other premium features" copy when not set. May contain
 *                                <br> tags — admin-authored, not user input, so kses'd rather than
 *                                fully escaped.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="pro-feature-banner">
	<h4>&#128274; <?php esc_html_e('Pro Feature', 'latepoint');?></h4>
	<div class="pro-desc">
		<div><?php echo empty( $description ) ? esc_html__( 'This feature is available with a paid version, along with over 30 other premium features.', 'latepoint' ) : wp_kses( $description, [ 'br' => [] ] ); ?></div>
	</div>
	<a target="_blank" href="<?php echo esc_url(OsUtilHelper::get_upgrade_url('banner')); ?>" class="latepoint-pro-link"><?php esc_html_e('Upgrade to paid version', 'latepoint'); ?></a>
	<a href="#" class="latepoint-pro-link-subtle" <?php echo OsSettingsHelper::get_link_attributes_for_premium_features(); ?>><?php esc_html_e('Show All premium features', 'latepoint'); ?></a>
</div>
