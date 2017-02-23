<?php
	//only admins can get this
	if(!function_exists("current_user_can") || (!current_user_can("manage_options") && !current_user_can("pmpro_discountcodes")))
	{
		die(__("You do not have permissions to perform this action.", 'paid-memberships-pro' ));
	}

	//vars
	global $wpdb, $pmpro_currency_symbol;

	if(isset($_REQUEST['edit']))
		$edit = intval($_REQUEST['edit']);
	else
		$edit = false;

	if(isset($_REQUEST['delete']))
		$delete = intval($_REQUEST['delete']);
	else
		$delete = false;

	if(isset($_REQUEST['saveid']))
		$saveid = intval($_POST['saveid']);
	else
		$saveid = false;			

	if(isset($_REQUEST['s']))
		$s = sanitize_text_field($_REQUEST['s']);
	else
		$s = "";
	
	if($saveid)
	{
		//get vars
		//disallow/strip all non-alphanumeric characters except -
		$code = preg_replace("/[^A-Za-z0-9\-]/", "", sanitize_text_field($_POST['code']));
		$starts_month = intval($_POST['starts_month']);
		$starts_day = intval($_POST['starts_day']);
		$starts_year = intval($_POST['starts_year']);
		$expires_month = intval($_POST['expires_month']);
		$expires_day = intval($_POST['expires_day']);
		$expires_year = intval($_POST['expires_year']);
		$uses = intval($_POST['uses']);
		
		//fix up dates		
		$starts = date_i18n("Y-m-d", strtotime($starts_month . "/" . $starts_day . "/" . $starts_year, current_time("timestamp")));
		$expires = date_i18n("Y-m-d", strtotime($expires_month . "/" . $expires_day . "/" . $expires_year, current_time("timestamp")));
		
		//insert/update/replace discount code
		$wpdb->replace(
			$wpdb->pmpro_discount_codes,
			array(
				'id'=>max($saveid, 0),
				'code' => $code,
				'starts' => $starts,
				'expires' => $expires,
				'uses' => $uses				
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%d'
			)
		);
		
		//check for errors and show appropriate message if inserted or updated
		if(empty($wpdb->last_error)) {
			if($saveid < 1) {
				//insert
				$pmpro_msg = __("Discount code added successfully.", 'paid-memberships-pro' );
				$pmpro_msgt = "success";
				$saved = true;
				$edit = $wpdb->insert_id;
			} else {
				//updated
				$pmpro_msg = __("Discount code updated successfully.", 'paid-memberships-pro' );
				$pmpro_msgt = "success";
				$saved = true;
				$edit = $saveid;
			}
		} else {
			if($saveid < 1) {
				//error inserting
				$pmpro_msg = __("Error adding discount code. That code may already be in use.", 'paid-memberships-pro' ) . $wpdb->last_error;
				$pmpro_msgt = "error";
			} else {
				//error updating
				$pmpro_msg = __("Error updating discount code. That code may already be in use.", 'paid-memberships-pro' );
				$pmpro_msgt = "error";
			}
		}				

		//now add the membership level rows
		if($saved && $edit > 0)
		{
			//get the submitted values
			$all_levels_a = $_REQUEST['all_levels'];
			if(!empty($_REQUEST['levels']))
				$levels_a = $_REQUEST['levels'];
			else
				$levels_a = array();
			$initial_payment_a = $_REQUEST['initial_payment'];
			
			if(!empty($_REQUEST['recurring']))
				$recurring_a = $_REQUEST['recurring'];				
			$billing_amount_a = $_REQUEST['billing_amount'];
			$cycle_number_a = $_REQUEST['cycle_number'];
			$cycle_period_a = $_REQUEST['cycle_period'];
			$billing_limit_a = $_REQUEST['billing_limit'];
			
			if(!empty($_REQUEST['custom_trial']))
				$custom_trial_a = $_REQUEST['custom_trial'];
			$trial_amount_a = $_REQUEST['trial_amount'];
			$trial_limit_a = $_REQUEST['trial_limit'];
			
			if(!empty($_REQUEST['expiration']))
				$expiration_a = $_REQUEST['expiration'];
			$expiration_number_a = $_REQUEST['expiration_number'];
			$expiration_period_a = $_REQUEST['expiration_period'];

			//clear the old rows
			$wpdb->delete($wpdb->pmpro_discount_codes_levels, array('code_id' => $edit), array('%d'));
						
			//add a row for each checked level
			if(!empty($levels_a))
			{
				foreach($levels_a as $level_id)
				{
					//get the values ready
					$n = array_search($level_id, $all_levels_a); 	//this is the key location of this level's values
					$initial_payment = sanitize_text_field($initial_payment_a[$n]);

					//is this recurring?
					if(!empty($recurring_a))
					{
						if(in_array($level_id, $recurring_a))
							$recurring = 1;
						else
							$recurring = 0;
					}
					else
						$recurring = 0;

					if(!empty($recurring))
					{
						$billing_amount = sanitize_text_field($billing_amount_a[$n]);
						$cycle_number = intval($cycle_number_a[$n]);
						$cycle_period = sanitize_text_field($cycle_period_a[$n]);
						$billing_limit = intval($billing_limit_a[$n]);

						//custom trial
						if(!empty($custom_trial_a))
						{
							if(in_array($level_id, $custom_trial_a))
								$custom_trial = 1;
							else
								$custom_trial = 0;
						}
						else
							$custom_trial = 0;

						if(!empty($custom_trial))
						{
							$trial_amount = sanitize_text_field($trial_amount_a[$n]);
							$trial_limit = intval($trial_limit_a[$n]);
						}
						else
						{
							$trial_amount = '';
							$trial_limit = '';
						}
					}
					else
					{
						$billing_amount = '';
						$cycle_number = '';
						$cycle_period = 'Month';
						$billing_limit = '';
						$custom_trial = 0;
						$trial_amount = '';
						$trial_limit = '';
					}

					if(!empty($expiration_a))
					{
						if(in_array($level_id, $expiration_a))
							$expiration = 1;
						else
							$expiration = 0;
					}
					else
						$expiration = 0;

					if(!empty($expiration))
					{
						$expiration_number = intval($expiration_number_a[$n]);
						$expiration_period = sanitize_text_field($expiration_period_a[$n]);
					}
					else
					{
						$expiration_number = '';
						$expiration_period = 'Month';
					}

					
					
					//okay, do the insert
					$wpdb->insert(
						$wpdb->pmpro_discount_codes_levels,
						array(
							'code_id' => $edit,
							'level_id' => $level_id,
							'initial_payment' => $initial_payment,
							'billing_amount' => $billing_amount,
							'cycle_number' => $cycle_number,
							'cycle_period' => $cycle_period,
							'billing_limit' => $billing_limit,
							'trial_amount' => $trial_amount,
							'expiration_number' => $expiration_number,
							'expiration_period' => $expiration_period
						),
						array(
							'%d',
							'%d',
							'%f',
							'%f',
							'%d',
							'%s',
							'%d',
							'%f',
							'%d',
							'%s'
						)
					);
										
					if(empty($wpdb->last_error))
					{
						//okay
						do_action("pmpro_save_discount_code_level", $edit, $level_id);
					}
					else
					{
						$level = pmpro_getLevel($level_id);
						$level_errors[] = sprintf(__("Error saving values for the %s level.", 'paid-memberships-pro' ), $level->name);
					}
				}
			}

			//errors?
			if(!empty($level_errors))
			{
				$pmpro_msg = __("There were errors updating the level values: ", 'paid-memberships-pro' ) . implode(" ", $level_errors);
				$pmpro_msgt = "error";
			}
			else
			{
				//all good. set edit = NULL so we go back to the overview page
				$edit = NULL;

				do_action("pmpro_save_discount_code", $saveid);
			}
		}
	}

	//are we deleting?
	if(!empty($delete))
	{
		//is this a code?
		$code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM $wpdb->pmpro_discount_codes WHERE id = %d LIMIT 1", $delete ) );
		if(!empty($code))
		{
			//action
			do_action("pmpro_delete_discount_code", $delete);

			//delete the code levels
			$r1 = $wpdb->delete($wpdb->pmpro_discount_codes_levels, array('code_id'=>$delete), array('%d'));

			if($r1 !== false)
			{
				//delete the code
				$r2 = $wpdb->delete($wpdb->pmpro_discount_codes, array('id'=>$delete), array('%d'));
				
				if($r2 !== false)
				{
					$pmpro_msg = sprintf(__("Code %s deleted successfully.", 'paid-memberships-pro' ), $code);
					$pmpro_msgt = "success";
				}
				else
				{
					$pmpro_msg = __("Error deleting discount code. The code was only partially deleted. Please try again.", 'paid-memberships-pro' );
					$pmpro_msgt = "error";
				}
			}
			else
			{
				$pmpro_msg = __("Error deleting code. Please try again.", 'paid-memberships-pro' );
				$pmpro_msgt = "error";
			}
		}
		else
		{
			$pmpro_msg = __("Code not found.", 'paid-memberships-pro' );
			$pmpro_msgt = "error";
		}
	}

	require_once(dirname(__FILE__) . "/admin_header.php");
?>

	<?php if($edit) { ?>

		<h2>
			<?php
				if($edit > 0)
					echo __("Edit Discount Code", 'paid-memberships-pro' );
				else
					echo __("Add New Discount Code", 'paid-memberships-pro' );
			?>
		</h2>

		<?php if(!empty($pmpro_msg)) { ?>
			<div id="message" class="<?php if($pmpro_msgt == "success") echo "updated fade"; else echo "error"; ?>"><p><?php echo $pmpro_msg?></p></div>
		<?php } ?>

		<div>
			<?php
				// get the code...
				if($edit > 0)
				{
					$code = $wpdb->get_row(
						$wpdb->prepare("
						SELECT *, UNIX_TIMESTAMP(starts) as starts, UNIX_TIMESTAMP(expires) as expires
						FROM $wpdb->pmpro_discount_codes
						WHERE id = %d LIMIT 1",
						$edit ),
						OBJECT
					);

					$uses = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = %d", $code->id ) );
					$levels = $wpdb->get_results( $wpdb->prepare("
					SELECT l.id, l.name, cl.initial_payment, cl.billing_amount, cl.cycle_number, cl.cycle_period, cl.billing_limit, cl.trial_amount, cl.trial_limit
					FROM $wpdb->pmpro_membership_levels l
					LEFT JOIN $wpdb->pmpro_discount_codes_levels cl
					ON l.id = cl.level_id
					WHERE cl.code_id = %s",
					$code->code
					) );
					$temp_id = $code->id;
				}
				elseif(!empty($copy) && $copy > 0)
				{
					$code = $wpdb->get_row( $wpdb->prepare("
					SELECT *, UNIX_TIMESTAMP(starts) as starts, UNIX_TIMESTAMP(expires) as expires
					FROM $wpdb->pmpro_discount_codes
					WHERE id = %d LIMIT 1",
					$copy ),
					OBJECT
				);
					$temp_id = $level->id;
					$level->id = NULL;
				}

				// didn't find a discount code, let's add a new one...
				if(empty($code->id)) $edit = -1;

				//defaults for new codes
				if($edit == -1)
				{
					$code = new stdClass();
					$code->code = pmpro_getDiscountCode();
				}
			?>
			<form action="" method="post">
				<input name="saveid" type="hidden" value="<?php echo $edit?>" />
				<table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row" valign="top"><label><?php _e('ID', 'paid-memberships-pro' );?>:</label></th>
                        <td class="pmpro_lite"><?php if(!empty($code->id)) echo $code->id; else echo __("This will be generated when you save.", 'paid-memberships-pro' );?></td>
                    </tr>

                    <tr>
                        <th scope="row" valign="top"><label for="code"><?php _e('Code', 'paid-memberships-pro' );?>:</label></th>
                        <td><input name="code" type="text" size="20" value="<?php echo str_replace("\"", "&quot;", stripslashes($code->code))?>" /></td>
                    </tr>

					<?php
						//some vars for the dates
						$current_day = date_i18n("j");
						if(!empty($code->starts))
							$selected_starts_day = date_i18n("j", $code->starts);
						else
							$selected_starts_day = $current_day;
						if(!empty($code->expires))
							$selected_expires_day = date_i18n("j", $code->expires);
						else
							$selected_expires_day = $current_day;

						$current_month = date_i18n("M");
						if(!empty($code->starts))
							$selected_starts_month = date_i18n("m", $code->starts);
						else
							$selected_starts_month = date_i18n("m");
						if(!empty($code->expires))
							$selected_expires_month = date_i18n("m", $code->expires);
						else
							$selected_expires_month = date_i18n("m");							
						
						$current_year = date_i18n("Y");
						if(!empty($code->starts))
							$selected_starts_year = date_i18n("Y", $code->starts);
						else
							$selected_starts_year = $current_year;
						if(!empty($code->expires))
							$selected_expires_year = date_i18n("Y", $code->expires);
						else
							$selected_expires_year = (int)$current_year + 1;
					?>

					<tr>
                        <th scope="row" valign="top"><label for="starts"><?php _e('Start Date', 'paid-memberships-pro' );?>:</label></th>
                        <td>
							<select name="starts_month">
								<?php
									for($i = 1; $i < 13; $i++)
									{
									?>
									<option value="<?php echo $i?>" <?php if($i == $selected_starts_month) { ?>selected="selected"<?php } ?>><?php echo date_i18n("M", strtotime($i . "/1/" . $current_year, current_time("timestamp")))?></option>
									<?php
									}
								?>
							</select>
							<input name="starts_day" type="text" size="2" value="<?php echo $selected_starts_day?>" />
							<input name="starts_year" type="text" size="4" value="<?php echo $selected_starts_year?>" />
						</td>
                    </tr>

					<tr>
                        <th scope="row" valign="top"><label for="expires"><?php _e('Expiration Date', 'paid-memberships-pro' );?>:</label></th>
                        <td>
							<select name="expires_month">
								<?php
									for($i = 1; $i < 13; $i++)
									{
									?>
									<option value="<?php echo $i?>" <?php if($i == $selected_expires_month) { ?>selected="selected"<?php } ?>><?php echo date_i18n("M", strtotime($i . "/1/" . $current_year, current_time("timestamp")))?></option>
									<?php
									}
								?>
							</select>
							<input name="expires_day" type="text" size="2" value="<?php echo $selected_expires_day?>" />
							<input name="expires_year" type="text" size="4" value="<?php echo $selected_expires_year?>" />
						</td>
                    </tr>

					<tr>
                        <th scope="row" valign="top"><label for="uses"><?php _e('Uses', 'paid-memberships-pro' );?>:</label></th>
                        <td>
							<input name="uses" type="text" size="10" value="<?php if(!empty($code->uses)) echo str_replace("\"", "&quot;", stripslashes($code->uses));?>" />
							<small class="pmpro_lite"><?php _e('Leave blank for unlimited uses.', 'paid-memberships-pro' );?></small>
						</td>
                    </tr>

				</tbody>
			</table>

			<?php do_action("pmpro_discount_code_after_settings", $edit); ?>

			<h3><?php _e('Which Levels Will This Code Apply To?', 'paid-memberships-pro' ); ?></h3>

			<div class="pmpro_discount_levels">
			<?php
				$levels = $wpdb->get_results("SELECT * FROM $wpdb->pmpro_membership_levels");
				foreach($levels as $level)
				{
					//if this level is already managed for this discount code, use the code values
					if($edit > 0)
					{
						$code_level = $wpdb->get_row( $wpdb->prepare("
						SELECT l.id, cl.*, l.name, l.description, l.allow_signups
						FROM $wpdb->pmpro_discount_codes_levels cl
						LEFT JOIN $wpdb->pmpro_membership_levels l
						ON cl.level_id = l.id
						WHERE cl.code_id = %d AND cl.level_id = %d LIMIT 1",
						$edit,
						$level->id )
					);
						if($code_level)
						{
							$level = $code_level;
							$level->checked = true;
						}
						else
							$level_checked = false;
					}
					else
						$level_checked = false;
				?>
				<div>
					<input type="hidden" name="all_levels[]" value="<?php echo $level->id?>" />
					<input type="checkbox" id="levels_<?php echo $level->id;?>" name="levels[]" value="<?php echo $level->id?>" <?php if(!empty($level->checked)) { ?>checked="checked"<?php } ?> onclick="if(jQuery(this).is(':checked')) jQuery(this).next().next().show();	else jQuery(this).next().next().hide();" />
					<label for="levels_<?php echo $level->id;?>"><?php echo $level->name?></label>
					<div class="pmpro_discount_levels_pricing level_<?php echo $level->id?>" <?php if(empty($level->checked)) { ?>style="display: none;"<?php } ?>>
						<table class="form-table">
						<tbody>
							<tr>
								<th scope="row" valign="top"><label for="initial_payment"><?php _e('Initial Payment', 'paid-memberships-pro' );?>:</label></th>
								<td>
									<?php
									if(pmpro_getCurrencyPosition() == "left")
										echo $pmpro_currency_symbol;
									?>
									<input name="initial_payment[]" type="text" size="20" value="<?php echo str_replace("\"", "&quot;", stripslashes($level->initial_payment))?>" />
									<?php
									if(pmpro_getCurrencyPosition() == "right")
										echo $pmpro_currency_symbol;
									?>
									<small><?php _e('The initial amount collected at registration.', 'paid-memberships-pro' );?></small>
								</td>
							</tr>

							<tr>
								<th scope="row" valign="top"><label><?php _e('Recurring Subscription', 'paid-memberships-pro' );?>:</label></th>
								<td><input class="recurring_checkbox" id="recurring_<?php echo $level->id;?>" name="recurring[]" type="checkbox" value="<?php echo $level->id?>" <?php if(pmpro_isLevelRecurring($level)) { echo "checked='checked'"; } ?> onclick="if(jQuery(this).attr('checked')) {					jQuery(this).parent().parent().siblings('.recurring_info').show(); if(!jQuery('#custom_trial_<?php echo $level->id?>').is(':checked')) jQuery(this).parent().parent().siblings('.trial_info').hide();} else					jQuery(this).parent().parent().siblings('.recurring_info').hide();" /> <label for="recurring_<?php echo $level->id;?>"><?php _e('Check if this level has a recurring subscription payment.', 'paid-memberships-pro' );?></label></td>
							</tr>

							<tr class="recurring_info" <?php if(!pmpro_isLevelRecurring($level)) {?>style="display: none;"<?php } ?>>
								<th scope="row" valign="top"><label for="billing_amount"><?php _e('Billing Amount', 'paid-memberships-pro' );?>:</label></th>
								<td>
									<?php
									if(pmpro_getCurrencyPosition() == "left")
										echo $pmpro_currency_symbol;
									?>
									<input name="billing_amount[]" type="text" size="20" value="<?php echo str_replace("\"", "&quot;", stripslashes($level->billing_amount))?>" />
									<?php
									if(pmpro_getCurrencyPosition() == "right")
										echo $pmpro_currency_symbol;
									?>
									<small>per</small>
									<input name="cycle_number[]" type="text" size="10" value="<?php echo str_replace("\"", "&quot;", stripslashes($level->cycle_number))?>" />
									<select name="cycle_period[]" onchange="updateCyclePeriod();">
									  <?php
										$cycles = array( __('Day(s)', 'paid-memberships-pro' ) => 'Day', __('Week(s)', 'paid-memberships-pro' ) => 'Week', __('Month(s)', 'paid-memberships-pro' ) => 'Month', __('Year(s)', 'paid-memberships-pro' ) => 'Year' );
										foreach ( $cycles as $name => $value ) {
										  echo "<option value='$value'";
										  if ( $level->cycle_period == $value ) echo " selected='selected'";
										  echo ">$name</option>";
										}
									  ?>
									</select>
									<br /><small><?php _e('The amount to be billed one cycle after the initial payment.', 'paid-memberships-pro' );?></small>
								</td>
							</tr>

							<tr class="recurring_info" <?php if(!pmpro_isLevelRecurring($level)) {?>style="display: none;"<?php } ?>>
								<th scope="row" valign="top"><label for="billing_limit"><?php _e('Billing Cycle Limit', 'paid-memberships-pro' );?>:</label></th>
								<td>
									<input name="billing_limit[]" type="text" size="20" value="<?php echo $level->billing_limit?>" />
									<br /><small><?php _e('The <strong>total</strong> number of recurring billing cycles for this level, including the trial period (if applicable) but not including the initial payment. Set to zero if membership is indefinite.', 'paid-memberships-pro' );?></small>
								</td>
							</tr>

							<tr class="recurring_info" <?php if (!pmpro_isLevelRecurring($level)) echo "style='display:none;'";?>>
								<th scope="row" valign="top"><label><?php _e('Custom Trial', 'paid-memberships-pro' );?>:</label></th>
								<td><input id="custom_trial_<?php echo $level->id?>" id="custom_trial_<?php echo $level->id;?>" name="custom_trial[]" type="checkbox" value="<?php echo $level->id?>" <?php if ( pmpro_isLevelTrial($level) ) { echo "checked='checked'"; } ?> onclick="if(jQuery(this).attr('checked')) jQuery(this).parent().parent().siblings('.trial_info').show();	else jQuery(this).parent().parent().siblings('.trial_info').hide();" /> <label for="custom_trial_<?php echo $level->id;?>"><?php _e('Check to add a custom trial period.', 'paid-memberships-pro' );?></label></td>
							</tr>

							<tr class="trial_info recurring_info" <?php if (!pmpro_isLevelTrial($level)) echo "style='display:none;'";?>>
								<th scope="row" valign="top"><label for="trial_amount"><?php _e('Trial Billing Amount', 'paid-memberships-pro' );?>:</label></th>
								<td>
									<?php
									if(pmpro_getCurrencyPosition() == "left")
										echo $pmpro_currency_symbol;
									?>
									<input name="trial_amount[]" type="text" size="20" value="<?php echo str_replace("\"", "&quot;", stripslashes($level->trial_amount))?>" />
									<?php
									if(pmpro_getCurrencyPosition() == "right")
										echo $pmpro_currency_symbol;
									?>
									<small><?php _e('for the first', 'paid-memberships-pro' );?></small>
									<input name="trial_limit[]" type="text" size="10" value="<?php echo str_replace("\"", "&quot;", stripslashes($level->trial_limit))?>" />
									<small><?php _e('subscription payments', 'paid-memberships-pro' );?>.</small>
								</td>
							</tr>

							<tr>
								<th scope="row" valign="top"><label><?php _e('Membership Expiration', 'paid-memberships-pro' );?>:</label></th>
								<td><input id="expiration_<?php echo $level->id;?>" name="expiration[]" type="checkbox" value="<?php echo $level->id?>" <?php if(pmpro_isLevelExpiring($level)) { echo "checked='checked'"; } ?> onclick="if(jQuery(this).is(':checked')) { jQuery(this).parent().parent().siblings('.expiration_info').show(); } else { jQuery(this).parent().parent().siblings('.expiration_info').hide();}" /> <label for="expiration_<?php echo $level->id;?>"><?php _e('Check this to set when membership access expires.', 'paid-memberships-pro' );?></label></td>
							</tr>

							<tr class="expiration_info" <?php if(!pmpro_isLevelExpiring($level)) {?>style="display: none;"<?php } ?>>
								<th scope="row" valign="top"><label for="billing_amount"><?php _e('Expires In', 'paid-memberships-pro' );?>:</label></th>
								<td>
									<input id="expiration_number" name="expiration_number[]" type="text" size="10" value="<?php echo str_replace("\"", "&quot;", stripslashes($level->expiration_number))?>" />
									<select id="expiration_period" name="expiration_period[]">
									  <?php
										$cycles = array( __('Day(s)', 'paid-memberships-pro' ) => 'Day', __('Week(s)', 'paid-memberships-pro' ) => 'Week', __('Month(s)', 'paid-memberships-pro' ) => 'Month', __('Year(s)', 'paid-memberships-pro' ) => 'Year' );
										foreach ( $cycles as $name => $value ) {
										  echo "<option value='$value'";
										  if ( $level->expiration_period == $value ) echo " selected='selected'";
										  echo ">$name</option>";
										}
									  ?>
									</select>
									<br /><small><?php _e('Set the duration of membership access. Note that the any future payments (recurring subscription, if any) will be cancelled when the membership expires.', 'paid-memberships-pro' );?></small>
								</td>
							</tr>
						</tbody>
					</table>

					<?php do_action("pmpro_discount_code_after_level_settings", $edit, $level); ?>

					</div>
				</div>
				<script>

				</script>
				<?php
				}
			?>
			</div>

			<p class="submit topborder">
				<input name="save" type="submit" class="button button-primary" value="Save Code" />
				<input name="cancel" type="button" class="button button-secondary" value="Cancel" onclick="location.href='<?php echo get_admin_url(NULL, '/admin.php?page=pmpro-discountcodes')?>';" />
			</p>
			</form>
		</div>

	<?php } else { ?>

		<h2>
			<?php _e('Memberships Discount Codes', 'paid-memberships-pro' );?>
			<a href="admin.php?page=pmpro-discountcodes&edit=-1" class="add-new-h2"><?php _e('Add New Discount Code', 'paid-memberships-pro' );?></a>
		</h2>

		<?php if(!empty($pmpro_msg)) { ?>
			<div id="message" class="<?php if($pmpro_msgt == "success") echo "updated fade"; else echo "error"; ?>"><p><?php echo $pmpro_msg?></p></div>
		<?php } ?>

		<form id="posts-filter" method="get" action="">
			<p class="search-box">
				<label class="screen-reader-text" for="post-search-input"><?php _e('Search Discount Codes', 'paid-memberships-pro' );?>:</label>
				<input type="hidden" name="page" value="pmpro-discountcodes" />
				<input id="post-search-input" type="text" value="<?php if(!empty($s)) echo $s;?>" name="s" size="30" />
				<input class="button" type="submit" value="<?php _e('Search', 'paid-memberships-pro' );?>" id="search-submit "/>
			</p>
		</form>

		<br class="clear" />
		<?php
			$sqlQuery = "SELECT *, UNIX_TIMESTAMP(starts) as starts, UNIX_TIMESTAMP(expires) as expires FROM $wpdb->pmpro_discount_codes ";
			if(!empty($s))
				$sqlQuery .= "WHERE code LIKE '%$s%' ";
				$sqlQuery .= "ORDER BY id ASC";

				$codes = $wpdb->get_results($sqlQuery, OBJECT);
		?>
		<table class="widefat">
		<thead>
			<tr>
				<th><?php _e('ID', 'paid-memberships-pro' );?></th>
				<th><?php _e('Code', 'paid-memberships-pro' );?></th>
				<th><?php _e('Starts', 'paid-memberships-pro' );?></th>
				<th><?php _e('Expires', 'paid-memberships-pro' );?></th>
				<th><?php _e('Uses', 'paid-memberships-pro' );?></th>
				<th><?php _e('Levels', 'paid-memberships-pro' );?></th>
				<?php do_action("pmpro_discountcodes_extra_cols_header", $codes);?>
				<th></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
				if(!$codes)
				{
				?>
					<tr><td colspan="7" class="pmpro_pad20">
						<p><?php _e('Discount codes allow you to offer your memberships at discounted prices to select customers.', 'paid-memberships-pro' );?> <a href="admin.php?page=pmpro-discountcodes&edit=-1"><?php _e('Create your first discount code now', 'paid-memberships-pro' );?></a>.</p>
					</td></tr>
				<?php
				}
				else
				{
					$count = 0;
					foreach($codes as $code)
					{
					?>
					<tr<?php if($count++ % 2 == 1) { ?> class="alternate"<?php } ?>>
						<td><?php echo $code->id?></td>
						<td>
							<a href="?page=pmpro-discountcodes&edit=<?php echo $code->id?>"><?php echo $code->code?></a>
						</td>
						<td>
							<?php echo date_i18n(get_option('date_format'), $code->starts)?>
						</td>
						<td>
							<?php echo date_i18n(get_option('date_format'), $code->expires)?>
						</td>				
						<td>
							<?php
								$uses = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = %d", $code->id ) );
								if($code->uses > 0)
									echo "<strong>" . (int)$uses . "</strong>/" . $code->uses;
								else
									echo "<strong>" . (int)$uses . "</strong>/unlimited";
							?>
						</td>
						<td>
							<?php
								$sqlQuery = $wpdb->prepare("
									SELECT l.id, l.name
									FROM $wpdb->pmpro_membership_levels l
									LEFT JOIN $wpdb->pmpro_discount_codes_levels cl
									ON l.id = cl.level_id
									WHERE cl.code_id = %d",
									$code->id
								);
								$levels = $wpdb->get_results($sqlQuery);

								$level_names = array();
								foreach($levels as $level)
									$level_names[] = "<a target=\"_blank\" href=\"" . pmpro_url("checkout", "?level=" . $level->id . "&discount_code=" . $code->code) . "\">" . $level->name . "</a>";
								if($level_names)
									echo implode(", ", $level_names);
								else
									echo "None";
							?>
						</td>
						<?php do_action("pmpro_discountcodes_extra_cols_body", $code);?>
						<td>
							<a href="?page=pmpro-discountcodes&edit=<?php echo $code->id?>"><?php _e('edit', 'paid-memberships-pro' );?></a>
						</td>
						<td>
							<a href="javascript:askfirst('<?php echo str_replace("'", "\'", sprintf(__('Are you sure you want to delete the %s discount code? The subscriptions for existing users will not change, but new users will not be able to use this code anymore.', 'paid-memberships-pro' ), $code->code));?>', '?page=pmpro-discountcodes&delete=<?php echo $code->id?>'); void(0);"><?php _e('delete', 'paid-memberships-pro' );?></a>
						</td>
					</tr>
					<?php
					}
				}
				?>
		</tbody>
		</table>

	<?php } ?>

<?php
	require_once(dirname(__FILE__) . "/admin_footer.php");
?>
