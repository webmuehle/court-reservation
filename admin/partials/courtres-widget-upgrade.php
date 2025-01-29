<?php

/**
 * Provide notice for pro-upgrade in admin view
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://webmuehle.at
 * @since      1.1.0
 *
 * @package    Courtres
 * @subpackage Courtres/admin/partials
 */
?>
<?php if ( ! cr_fs()->is_plan( 'ultimate' ) ) { ?>                                                        
        <div class="cr-widget-head nav-tab-wrapper">
                <span class="nav-tab"><?php echo esc_html__( 'New Features for Court Reservation Ultimate - GET IT NOW!', 'court-reservation' ); ?></span>     
        </div>
        <div class="cr-widget-item">+ <?php echo esc_html__( 'WooCommerce Payment Gateway - Earn more by enabling online booking for your Courts', 'court-reservation' ); ?></div>
        <div class="cr-widget-item">+ <?php echo esc_html__( 'Anonymization Mode - for 100% GDPR Compliance', 'court-reservation' ); ?></div>
        <div class="cr-widget-item">+ <?php echo esc_html__( 'Colored Events and Reservations', 'court-reservation' ); ?></div>
        <div class="cr-widget-item">+ <?php echo esc_html__( 'Pyramid Challenges', 'court-reservation' ); ?></div>
        <div class="cr-widget-item">
                <a href="https://checkout.freemius.com/plugin/3086/plan/12626/" class="button"><?php echo esc_html__( 'Upgrade Now', 'court-reservation' ); ?></a>
        </div>
<?php } ?>
<?php
if ( ! cr_fs()->is_plan( 'premium' ) ) {
	?>
	<div class="cr-widget-head nav-tab-wrapper">
		<span class="nav-tab"><?php echo esc_html__( 'Upgrade to Court Reservation Premium', 'court-reservation' ); ?></span>
	</div>
	<div class="cr-widget-item">+ <?php echo esc_html__( 'Create unlimited number of courts', 'court-reservation' ); ?></div>
	<div class="cr-widget-item">+ <?php echo esc_html__( 'Be able to manage over 100 members in the members section', 'court-reservation' ); ?></div>
	<div class="cr-widget-item">+ <?php echo esc_html__( 'Get E-Mail Notifications for reservations', 'court-reservation' ); ?></div>
	<div class="cr-widget-item">+ <?php echo esc_html__( '24/7 E-Mail Support', 'court-reservation' ); ?></div>
	<div class="cr-widget-item">
		<button id="cr-purchase-premium" class="button"><?php echo esc_html__( 'Upgrade to Premium', 'court-reservation' ); ?></button>
	</div>

<?php } ?>
<?php if ( ! cr_fs()->is_plan( 'ultimate' ) ) { ?>
	<div class="cr-widget-head nav-tab-wrapper">
		<span class="nav-tab"><?php echo esc_html__( 'Upgrade to Court Reservation Ultimate', 'court-reservation' ); ?></span>
	</div>
	<div class="cr-widget-item">+ <?php echo esc_html__( 'New feature: Revive your club life with Ladder Competitions', 'court-reservation' ); ?></div>
	<div class="cr-widget-item">
		<button id="cr-purchase-ultimate" class="button"><?php echo esc_html__( 'Upgrade to Ultimate', 'court-reservation' ); ?></button>
	</div>
<?php } ?>
