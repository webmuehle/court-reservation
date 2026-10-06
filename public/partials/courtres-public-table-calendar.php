<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
		<div class="cr-calendar-expanded-panel" id="drugi_kal_<?php echo esc_attr( $courtID ); ?>">
			<div class="cr-calendar-expanded-inner">
<?php
				$dani_tjedna=array("","M","T","W","T","F","S","S");

				$language_dani_tjedna=explode("_",get_locale());
				if ( $language_dani_tjedna[0] == "de" || $language_dani_tjedna[0] == "DE" ) { $dani_tjedna=array("","M","D","M","D","F","S","S"); }
				elseif ( $language_dani_tjedna[0] == "hr" || $language_dani_tjedna[0] == "HR" ) { $dani_tjedna=array("","P","U","S","Č","P","S","N"); }
				elseif ( $language_dani_tjedna[0] == "at" || $language_dani_tjedna[0] == "AT" ) { $dani_tjedna=array("","M","D","M","D","F","S","S"); }
				elseif ( $language_dani_tjedna[0] == "ch" || $language_dani_tjedna[0] == "CH" ) { $dani_tjedna=array("","M","D","M","D","F","S","S"); }
				elseif ( $language_dani_tjedna[0] == "es" || $language_dani_tjedna[0] == "ES" ) { $dani_tjedna=array("","L","M","M","J","V","S","D"); }
				elseif ( $language_dani_tjedna[0] == "it" || $language_dani_tjedna[0] == "IT" ) { $dani_tjedna=array("","L","M","M","G","V","S","D"); }
				elseif ( $language_dani_tjedna[0] == "nl" || $language_dani_tjedna[0] == "NL" ) { $dani_tjedna=array("","M","D","W","D","V","Z","Z"); }
				elseif ( $language_dani_tjedna[0] == "no" || $language_dani_tjedna[0] == "NO" ) { $dani_tjedna=array("","M","T","O","T","F","L","S"); }

				$mjeseci=array("01"=>"January", "02"=>"February", "03"=>"March", "04"=>"April", "05"=>"May", "06"=>"June", "07"=>"July", "08"=>"August", "09"=>"September", "10"=>"October", "11"=>"November", "12"=>"December");
				$odabrani_dan=gmdate('Y-m-d');
				$danas=gmdate('Y-m-d');
				if ( isset( $_POST['datum'] ) )
				{
					$odabrani_dan = sanitize_text_field( wp_unslash( $_POST['datum'] ) );
					if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $odabrani_dan ) ) {
						$odabrani_dan = gmdate( 'Y-m-d' );
					}
					$odabrani_dan_ = strtotime($odabrani_dan);
					$danas_ = strtotime($danas);
					$razlika =  round( ($odabrani_dan_-$danas_) / (60 * 60 * 24) );
				}

				if ( isset( $_REQUEST['navigator_step'] ) )
				{
					$koliko = intval( $_REQUEST['navigator_step'] );
					$odabrani_dan1=strtotime($danas);
					if ($koliko<0) { $odabrani_dan2 = strtotime("$koliko day", $odabrani_dan1); }
					else { $odabrani_dan2 = strtotime("+$koliko day", $odabrani_dan1); }
					$odabrani_dan = gmdate('Y-m-d', $odabrani_dan2);
				}

				// $danas=date('2023-06-05');
				$danas_bez1=explode("-",$odabrani_dan);
				$danas_bez=$danas_bez1[0] . "-" . $danas_bez1[1] . "-";
				$danas_prvi=$danas_bez . "01";
				$danas_zadnji=$danas_bez . gmdate('t', strtotime($danas));
				$danas_zadnji1=gmdate('t', strtotime($odabrani_dan));
				$prosli_zadnji1=gmdate('t', strtotime("last month", strtotime($odabrani_dan)));
				$danas_dan=$danas_bez1[2];
				$danas_mjesec=$danas_bez1[1];
				$danas_godina=$danas_bez1[0];

				if ($danas_dan<10) { $danas_dan1=explode("0",$danas_dan); $danas_dan=$danas_dan1[1]; }

				$odabrani_dan_ = strtotime($odabrani_dan);
				$danas_ = strtotime($danas);
				$razlika =  round( ($odabrani_dan_-$danas_) / (60 * 60 * 24) ); ?>

				<form action='' method='POST' name='kalendar'>

					<div class='cr-calendar-picker' id='cr_calendar_<?php echo esc_attr( $courtID ); ?>'>
						<input name='datum' id='cr-datum-<?php echo esc_attr( $courtID ); ?>' class='cr-reservation-date-input' data-court-id='<?php echo esc_attr( $courtID ); ?>' value='<?php echo esc_attr( $odabrani_dan ); ?>' type='text' readonly='readonly' autocomplete='off' placeholder='<?php echo esc_attr__( 'YYYY-MM-DD', 'court-reservation' ); ?>' style='outline: none; padding: 8px 10px 6px; width: 110px; background: transparent; font-size: 14px; color: inherit; border: none; cursor: pointer;'>
						<button type="button" class='button cr-calendar-dismiss' aria-label="<?php echo esc_attr__( 'Close calendar', 'court-reservation' ); ?>" data-court-id="<?php echo esc_attr( $courtID ); ?>">
							<?php courtres_calendar_icon( array( 'class' => 'cr-calendar-icon--sm' ) ); ?>
						</button>
					</div>

					<div style='width: 197px; box-sizing: border-box; border-top: 3px solid #2273d7; padding: 10px; position: relative; text-align: center;'>
						<?php echo esc_html__($mjeseci[$danas_mjesec], 'court-reservation') . " &nbsp; " . esc_html($danas_godina); ?>
					</div>

				</form> <?php

				$prvi2=gmdate("N",strtotime($danas_prvi));
	
				for ($x=1;$x<=7;$x++)
				{ ?>

					<div style="text-align: center; padding-top: 2px; float: left; width: 28px; height: 28px;">
						<?php echo esc_html($dani_tjedna[$x]); ?>
					</div> 

				<?php } ?>

				<div style="float: left; width: 0px; height: 28px; ">
					&nbsp;
				</div>  <?php

				$tjedan=0;
				for ($x=1;$x<=$prvi2-1;$x++)
				{ 
					$tjedan++; ?>

				<div style="text-align: center; float: left; width: 28px; height: 28px; ">
					&nbsp;
				</div> 

				<?php } 
				for ($x=1;$x<=$danas_zadnji1;$x++)
				{ 
					$tjedan++; 
					if ($tjedan==8)
					{ 
						$tjedan=1; ?>

				<div style="float: left; width: 0px; height: 28px;">
					&nbsp;
				</div>  <?php

					} 

					if ($x<10) { $trenutni_dan=$danas_bez . "0" . $x; } else { $trenutni_dan=$danas_bez . $x; }
					$trenutni_dan_ = strtotime($trenutni_dan);
					$razlika_ =  round( ($trenutni_dan_-$danas_) / (60 * 60 * 24) ); ?>

				<div id="cr_calendar_<?php echo esc_html($x); ?>_<?php echo esc_html($courtID); ?>" data-day="<?php echo esc_html($razlika_); ?>" class="kalendar-dani" style="cursor: pointer; <?php if ($x==$danas_dan) { echo "color: black; font-weight: bold;"; } else { echo "color: darkgray; "; } ?>text-align: center; padding-top: 3px; float: left; width: 28px; height: 28px;">
					<?php echo esc_html($x); ?>
				</div> <?php

				} 

				for ($x=$tjedan;$x<7;$x++)
				{  ?>

				<div style="text-align: center; float: left; width: 28px; height: 28px; ">
					&nbsp;
				</div>  <?php

				} ?>	

				<div style="float: left; width: 0px; height: 28px;">
					&nbsp;
				</div>  

				<div style="clear: both;"> </div>
			</div>  
		</div>  

