<?php /* CONTACTS $Id: select_contact_company.php,v 1.1 2006/04/07 11:41:27 Attest sw-libre@attest.es  Exp $ */

	$table_name = dPgetParam($_GET, "table_name", "companies");

	switch($table_name) {
		case "companies":
			$id_field          = "company_id";
			$name_field        = "company_name";
			$selection_string  = "Company";
			$filter            = null;
			$additional_get_information = "";
			break;
		case "departments":
			$id_field          = "dept_id";
			$name_field        = "dept_name";
			$selection_string  = "Department";
			$filter            = "dept_company = ".$_GET["company_id"];
			$additional_get_information = "company_id=".$_GET["company_id"];
			break;
	}
	
	$q  = new DBQuery;
	$q->addTable($table_name);
	$q->addQuery("$id_field, $name_field");
	if ($filter != null) { $q->addWhere($filter); }
	$q->addOrder($name_field);
	$company_list = array("0" => "(ninguno)") + $q->loadHashList();

?>

<?php
	if(dPgetParam($_POST, $id_field, 0)!=null ) {
		$q  = new DBQuery;
		$q->addTable($table_name);
		$q->addQuery('*');
		$q->addWhere("$id_field=".$_POST[$id_field]);
		$sql = $q->prepare();
		$q->clear();
		db_loadHash($sql, $r_data);
		
		$data_update_script = "";
		$update_address     = isset($_POST["overwrite_address"]);
			
		if($table_name == "companies"){
			if($update_address){
				$update_fields = array("company_address1" => "contact_address1",
				                       "company_address2" => "contact_address2",
				                       "company_city"     => "contact_city",
				                       "company_state"    => "contact_state",
				                       "company_zip"      => "contact_zip",
				                       "company_phone1"   => "contact_phone",
				                       "company_phone2"   => "contact_phone2");
			}
			$data_update_script = "opener.setCompany('".$_POST[$id_field]."', '" . $r_data[$name_field] . "');\n";
		} else if($table_name == "departments"){
			$update_fields = array("dept_name"     => "contact_department");
			if($update_address){
				$update_fields = array("dept_address1" => "contact_address1",
				                       "dept_address2" => "contact_address2",
				                       "dept_city"     => "contact_city",
				                       "dept_state"    => "contact_state",
				                       "dept_zip"      => "contact_zip",
				                       "dept_phone"   => "contact_phone");
			}
			//$data_update_script = "opener.setDepartment('" . $_POST[$id_field] . "', '" . $r_data[$name_field] . "');\n";
			
		}
	
		// Let's figure out which fields are going to
		// be updated
		foreach ($update_fields as $record_field => $contact_field) {
			if($table_name == "companies") {
				$data_update_script .= "opener.document.changecontact.$contact_field.value = '".$r_data[$record_field]."';\n";
			}
			else {
				$data_update_script  = "opener.document.changecontact.$contact_field.value = '".$_POST[$id_field]."';\n";
				$data_update_script .= "opener.document.changecontact.".$contact_field."_name.value = '".$r_data[$name_field]."';\n";
				$data_update_script .= "opener.document.changecontact.cont.value=1;\n";
			}
		}
		?>
			<script language='javascript'>
			
			<?php echo $data_update_script; ?>
				
			self.close();
			</script>
		<?php
	} else {
		?>
		<div align=center>
		<form name="frmSelector" action="./index.php?m=contacts&a=select_contact_company&dialog=1&table_name=<?php echo $table_name."&$additional_get_information"; ?>" method="post">
			<table cellspacing="0" cellpadding="3" border="0">
			<tr>
				<td colspan="2"><br><strong>
			<?php
				echo $AppUI->_( 'Select' ).' '.$AppUI->_( $selection_string ).':<br /><br />';
				echo arraySelect( $company_list, $id_field, ' size="10"', $company_id );
			?></strong>
				</td>
			</tr>
			<tr>
				<td><br>
					<input type="button" class="button" value="<?php echo $AppUI->_( 'cancel' );?>" onclick="window.close()" />
				</td>
				<td align="right"><br>
					<!--ina <input type='checkbox' name='overwrite_address' /> <?php echo $AppUI->_("Overwrite contact address information"); ?>-->
					<input type="submit" class="button" value="<?php echo $AppUI->_( 'Select', UI_CASE_LOWER );?>" />
				</td>
			</tr>
			</table>

		</form>
		</div>
	<?php
	}
?>
