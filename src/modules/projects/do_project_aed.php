<?php /* PROJECTS $Id: do_project_aed.php,v 1.11 2005/03/02 22:38:51 mosen Exp $ */
$obj = new CProject();
$msg = '';

if (!$obj->bind( $_POST )) {
	$AppUI->setMsg( $obj->getError(), UI_MSG_ERROR );
	$AppUI->redirect();
}

require_once("./classes/CustomFields.class.php");
// convert dates to SQL format first
$date = new CDate( $obj->project_start_date );
$obj->project_start_date = $date->format( FMT_DATETIME_MYSQL );

if ($obj->project_end_date) {
	$date = new CDate( $obj->project_end_date );
	$obj->project_end_date = $date->format( FMT_DATETIME_MYSQL );
}
if ($obj->project_actual_end_date) {
	$date = new CDate( $obj->project_actual_end_date );
	$obj->project_actual_end_date = $date->format( FMT_DATETIME_MYSQL );
}

// let's check if there are some assigned departments to project
if(!dPgetParam($_POST, "project_departments", 0)){
	$obj->project_departments = implode(",", dPgetParam($_POST, "dept_ids", array()));
}

$del = dPgetParam( $_POST, 'del', 0 );

// prepare (and translate) the module name ready for the suffix
if ($del) {
	if (!$obj->canDelete( $msg )) {
		$AppUI->setMsg( $msg, UI_MSG_ERROR );
		$AppUI->redirect();
	}
	if (($msg = $obj->delete())) {
		$AppUI->setMsg( $msg, UI_MSG_ERROR );
		$AppUI->redirect();
	} else {
		$AppUI->setMsg( "Project deleted", UI_MSG_ALERT);
		$AppUI->redirect( "m=projects" );
	}
} else {
	if (($msg = $obj->store())) {
		$AppUI->setMsg( $msg, UI_MSG_ERROR );
	} else {
		$isNotNew = @$_POST['project_id'];
		
		if ( $importTask_projectId = dPgetParam( $_POST, 'import_tasks_from', '0' ) )
			$obj->importTasks ($importTask_projectId);

 		$custom_fields = New CustomFields( $m, 'addedit', $obj->project_id, "edit" );
 		$custom_fields->bind( $_POST );
 		$sql = $custom_fields->store( $obj->project_id ); // Store Custom Fields

		$AppUI->setMsg( $isNotNew ? 'Project updated' : 'Project inserted', UI_MSG_OK);
	}
	$AppUI->redirect();
}
?>
