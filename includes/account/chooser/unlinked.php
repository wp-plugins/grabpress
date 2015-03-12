<form method="post" id="account-chooser">
	<table>
		<tr>
			<?php $checked = 'default' == $request['action'] ? ' checked="checked" ' : ''; ?>
			<td><input type="radio" id="existing_account_link" name="action" value="default" <?php echo $checked ?> />Link to an existing Publisher account</td>
		</tr>
		<tr> 
			<?php $checked = 'create' == $request['action'] ? ' checked="checked" ' : ''; ?>
			<td><input type="radio" id="new_account_link" name="action" value="create" <?php echo $checked ?> />Create and link to a new account</td>
		</tr>
	</table>
</form>