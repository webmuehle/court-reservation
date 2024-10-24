<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://webmuehle.at
 * @since      1.0.3
 *
 * @package    Courtres
 * @subpackage Courtres/admin/partials
 */
?>

<?php

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die();
}

global $wpdb;
$table_name = $this->getTable( 'courts' );
$table_settings = $this->getTable( 'settings' );

if ( isset( $_GET['courtID'] ) ) {
	$courtID = (int) $_GET['courtID'];
}

if ( isset( $_POST['option_closed_court'] ) ) {
	// Checkbox is selected
	$form_closed_court = '1';
} elseif ( !isset( $_POST['option_closed_court'] ) && isset($_POST['submit']) ) {
	$form_closed_court = '0';
} else {
	$form_closed_court = '';
}

// 17.01.2019, astoian - if not premium, stop it
if ( isset( $courtID ) && ! $this->isCourtPremium( $courtID ) ) {
	include 'courtres-notice-upgrade.php';
	wp_die( esc_html__( 'Free version allow one Court only.', 'court-reservation' ) );
}

if ( isset( $_POST['delete'] ) && isset( $_POST['id'] ) && (int) $_POST['id'] > 0 ) { // delete
	$wpdb->delete( $table_name, array( 'id' => (int) $_POST['id'] ) );
}

if ( isset( $_POST['submit'] ) ) {
	if ( isset( $_POST['id'] ) && (int) $_POST['id'] > 0 ) { // edit
		$wpdb->update(
			$table_name,
			array(
				'name'  => sanitize_text_field( $_POST['name'] ),
				'open'  => sanitize_text_field( $_POST['open'] ),
				'close' => sanitize_text_field( $_POST['close'] ),
				'days'  => sanitize_text_field( $_POST['days'] ),
			),
			array( 'id' => (int) $_POST['id'] ),
			array(
				'%s',
				'%d',
				'%d',
				'%d',
			)
		);
		$courtID = (int) $_POST['id'];
		$message = __( 'Successfully changed!', 'court-reservation' );
	} else { // create
		$wpdb->insert(
			$table_name,
			array(
				'name'  => sanitize_text_field( $_POST['name'] ),
				'open'  => sanitize_text_field( $_POST['open'] ),
				'close' => sanitize_text_field( $_POST['close'] ),
				'days'  => sanitize_text_field( $_POST['days'] ),
			),
			array(
				'%s',
				'%d',
				'%d',
				'%d',
			)
		);
		$message = __( 'Successfully created!', 'court-reservation' );
		$courtID = $wpdb->insert_id;
	}
}

if (isset($courtID) && is_numeric($courtID))
{
	$courtres_option_closed_name = "option_closed_court_" . (int) $courtID;

	$database_closed_court = $wpdb->get_row( "SELECT * FROM $table_settings WHERE option_name = '$courtres_option_closed_name' ORDER BY `option_id` DESC LIMIT 1" );

	if ( $database_closed_court !== null  && $form_closed_court != '' ) {
		$wpdb->update(
			$table_settings,
			array(
				'option_value' => $form_closed_court,
			),
			array( 'option_id' => (int) $database_closed_court->option_id ),
			array( '%s' )
		);
		$message = __( 'Successfully changed!', 'court-reservation' );

	$option_closed_court = new stdClass();
	$option_closed_court->option_id    = 0;
	$option_closed_court->option_name  = $courtres_option_closed_name;
	$option_closed_court->option_value = $form_closed_court;
	}
	elseif ( $database_closed_court !== null  && $form_closed_court == '' ) {
		$option_closed_court = new stdClass();
		$option_closed_court->option_id    = 0;
		$option_closed_court->option_name  = $courtres_option_closed_name;
		$option_closed_court->option_value = (int) $database_closed_court->option_value;
	}
	elseif ( $database_closed_court === null  && $form_closed_court != ''  ) {

		$wpdb->insert(
			$table_settings,
			array(
				'option_name'  => $courtres_option_closed_name,
				'option_value' => (int) $form_closed_court,
			),
			array( '%s', '%s' )
		);
		$message = __( 'Successfully changed!', 'court-reservation' );

		$option_closed_court = new stdClass();
		$option_closed_court->option_id    = 0;
		$option_closed_court->option_name  = $courtres_option_closed_name;
		$option_closed_court->option_value = (int) $form_closed_court;
	}
	else
	{
		$option_closed_court = new stdClass();
		$option_closed_court->option_id    = 0;
		$option_closed_court->option_name  = $courtres_option_closed_name;
		$option_closed_court->option_value = '0';
	}
}

if ( isset( $courtID ) && $courtID > 0 ) {
	$court = $wpdb->get_row( "SELECT * FROM $table_name WHERE id = $courtID" );
}

if ( ! isset( $court ) ) {
	$court        = new stdClass();
	$court->id    = 0;
	$court->name  = '';
	$court->open  = 8;
	$court->close = 22;
	$court->days  = 3;
}
?>

<div class="wrap">
  <a class="page-title-action" href="<?php echo esc_url(admin_url( 'admin.php?page=courtres' )); ?>"><?php echo esc_html__( 'Back', 'court-reservation' ); ?></a>
  <h1 class="wp-heading-inline"><?php echo ( isset( $court ) && $court->id > 0 ) ? esc_html( $court->name ) . esc_html__( ' edit', 'court-reservation' ) : esc_html__( 'Create Court', 'court-reservation' ); ?></h1>
  <hr class="wp-header-end">

  <form method="post">
	<input type="hidden" name="id" value="<?php echo esc_html( $court->id ); ?>" />
	<table>
	  <tr>
		<td><?php echo esc_html__( 'Name', 'court-reservation' ); ?></td>
		<td><input type="text" name="name" maxlength="255" value="<?php echo esc_html( $court->name ); ?>" required /></td>
	  </tr>
	  <tr>
		<td><?php echo esc_html__( 'Opens (hour)', 'court-reservation' ); ?></td>
		<td><input type="number" name="open" min="0" max="23" maxlength="2" value="<?php echo esc_html( $court->open ); ?>" required /></td>
	  </tr>
	  <tr>
		<td><?php echo esc_html__( 'Closes (hour)', 'court-reservation' ); ?></td>
		<td><input type="number" name="close" min="0" max="24" maxlength="2" value="<?php echo esc_html( $court->close ); ?>" required /></td>
	  </tr>
	  <tr>
		<td><?php echo esc_html__( 'Reservation Days in Advance', 'court-reservation' ); ?></td>
		<td><input type="number" name="days" min="0" max="9" maxlength="1" value="<?php echo esc_html( $court->days ); ?>" required /></td>
	  </tr>

     <?php if (isset($courtID) && is_numeric($courtID)) { ?>

	  <tr>
		<td>
			<?php echo esc_html__( 'Close Court', 'court-reservation' ); ?>
		</td>
		<td>
			<label class="switch">
				<input type="checkbox" name="option_closed_court" <?php echo ( $option_closed_court->option_value == '1' ) ? 'checked' : ''; ?>>
				<span class="slider round"></span>
			</label>
		</td>
	  </tr>

     <?php } ?>

	  <tr>
		<td></td>
		<td><input class="button" type="submit" name="submit" value=<?php echo esc_html__( 'Save', 'court-reservation' ); ?> /></td>
	  </tr>
	  <?php if ( isset( $court ) && $court->id > 0 ) { ?>
		<tr>
		  <td colspan="2"><hr/></td>
		</tr>
		<tr>
		  <td><?php echo esc_html__( 'Delete Court', 'court-reservation' ); ?></td>
		  <td><input class="button" type="submit" name="delete" value=<?php echo esc_html__( 'Delete', 'court-reservation' ); ?> /></td>
		</tr>
	  <?php } ?>
  </form>
</div>
</div>
</div>
