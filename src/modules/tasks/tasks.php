<?php /* TASKS $Id: tasks.php,v 1.117 2006/04/05 17:57:31 Attest sw-libre@attest.es Exp $ */
/* TASKS $Id: tasks.php,v 1.116 2005/04/08 02:57:01 ajdonnison Exp $ */
GLOBAL $m, $a, $project_id, $f, $min_view, $query_string, $durnTypes;
GLOBAL $task_sort_item1, $task_sort_type1, $task_sort_order1;
GLOBAL $task_sort_item2, $task_sort_type2, $task_sort_order2;
GLOBAL $dPconfig, $currentTabId, $currentTabName, $canEdit, $showEditCheckbox;
//GLOBAL $user_id, $dPconfig, $currentTabId, $currentTabName, $canEdit, $showEditCheckbox;
/*
	tasks.php

	This file contains common task list rendering code used by
	modules/tasks/index.php and modules/projects/vw_tasks.php

	in

	External used variables:

	* $min_view: hide some elements when active (used in the vw_tasks.php)
	* $project_id
	* $f
	* $query_string
*/

if (empty($query_string)) {
	$query_string = "?m=$m&a=$a";
}

// Number of columns (used to calculate how many columns to span things through)
$cols = 13;

/****
// Let's figure out which tasks are selected
*/
global $tasks_opened;
global $tasks_closed;

$tasks_closed = array();
$tasks_opened = $AppUI->getState("tasks_opened");
if(!$tasks_opened){
    $tasks_opened = array();
}

$task_id = intval( dPgetParam( $_GET, "task_id", 0 ) );
$pinned_only = intval( dPgetParam( $_GET, 'pinned', 0) );
if (isset($_GET['pin']))
{
        $pin = intval( dPgetParam( $_GET, "pin", 0 ) );

        $msg = '';

        // load the record data 
        if($pin) {
        $sql = "INSERT INTO user_task_pin (user_id, task_id) VALUES($AppUI->user_id, $task_id)";
        } else {
        $sql = "DELETE FROM user_task_pin WHERE user_id=$AppUI->user_id AND task_id=$task_id";
        }
        
        if (!db_exec( $sql )) {
                $AppUI->setMsg( "ins/del err", UI_MSG_ERROR, true );
        }
        $AppUI->redirect('', -1);
}
else if($task_id > 0){
    $_GET["open_task_id"] = $task_id;
}

$AppUI->savePlace();

if(($open_task_id = dPGetParam($_GET, "open_task_id", 0)) > 0 && !in_array($_GET["open_task_id"], $tasks_opened)) {
    $tasks_opened[] = $_GET["open_task_id"];
}


// Closing tasks needs also to be within tasks iteration in order to
// close down all child tasks
if(($close_task_id = dPGetParam($_GET, "close_task_id", 0)) > 0) {
    closeOpenedTask($close_task_id);
}

// We need to save tasks_opened until the end because some tasks are closed within tasks iteration
//echo "<pre>"; print_r($tasks_opened); echo "</pre>";
/// End of tasks_opened routine


$durnTypes = dPgetSysVal( 'TaskDurationType' );
$taskPriority = dPgetSysVal( 'TaskPriority' );

$task_project = intval( dPgetParam( $_GET, 'task_project', null ) );
//$task_id = intval( dPgetParam( $_GET, 'task_id', null ) );

if ($_REQUEST["user_id"]!='') $user_id_f = $_REQUEST["user_id"];

$MyC = (isset($_REQUEST["MyC"])) ? $_REQUEST["MyC"] : '';
$task_sort_item1 = dPgetParam( $_GET, 'task_sort_item1', '' );
$task_sort_type1 = dPgetParam( $_GET, 'task_sort_type1', '' );
$task_sort_item2 = dPgetParam( $_GET, 'task_sort_item2', '' );
$task_sort_type2 = dPgetParam( $_GET, 'task_sort_type2', '' );
$task_sort_order1 = intval( dPgetParam( $_GET, 'task_sort_order1', 0 ) );
$task_sort_order2 = intval( dPgetParam( $_GET, 'task_sort_order2', 0 ) );
if (isset($_POST['show_task_options'])) {
	$AppUI->setState('TaskListShowIncomplete', dPgetParam($_POST, 'show_incomplete', 0));
}
$showIncomplete = $AppUI->getState('TaskListShowIncomplete', 0);

$where = '';
require_once $AppUI->getModuleClass('projects');
$project =& new CProject;
// $allowedProjects = $project->getAllowedRecords($AppUI->user_id, 'project_id, project_name');
$allowedProjects = $project->getAllowedSQL($AppUI->user_id);
$where = "";
$whereMyC='';

if(isset($_REQUEST["MyC"])) {
	
			if (( $fcliente!='no' )||( $fmoldista!='no' )||( $f2!='all' ) ) $where.= " WHERE ";
         	if ( $fmoldista !='no' ) $where .= "\n project_mold=$fmoldista ";
         	if (( $fcliente!='no' ) && ( $fmoldista!='no' )) $where.= " AND ";
         	if ( $fcliente!='no' ) $where .= "\n project_client=$fcliente ";
         	if (( $f2 != 'all' )&&( $where!=' WHERE ' )) $where .=' AND ';
         	if ( $f2 != 'all' ) $where .='project_company ='. $f2.' ';
         	$whereMyC = str_replace('WHERE','',$where);
         	
}

if ( count($allowedProjects)) {
  $where = "WHERE " . implode(" AND ", $allowedProjects) ;
}
//echo $whereMyC;

$psql = "
SELECT project_id, project_color_identifier, project_name,
	COUNT(t1.task_id) as total_tasks,
	SUM(t1.task_duration*t1.task_percent_complete)/SUM(t1.task_duration) as project_percent_complete,
	company_name
FROM projects
LEFT JOIN tasks t1 ON projects.project_id = t1.task_project" .
" LEFT JOIN companies ON company_id = project_company
" . $where  . "
GROUP BY project_id
ORDER BY project_name
";

//echo "<pre>$psql</pre>";

$perms =& $AppUI->acl();
$projects = array();
$canViewTask = $perms->checkModule('tasks', 'view');
if ($canViewTask) {
	$prc = db_exec( $psql );
	echo db_error();
	while ($row = db_fetch_assoc( $prc )) {
		$projects[$row["project_id"]] = $row;
	}
}
//print_r($projects);
$join = "";
// pull tasks

$select = "
distinct tasks.task_id, task_parent, task_name, task_start_date, task_end_date, task_dynamic, task_pinned, pin.user_id as pin_user,
task_priority, task_percent_complete, task_duration, task_duration_type, task_project,
task_description, task_owner, task_status, usernames.user_username, usernames.user_id, task_milestone,
assignees.user_username as assignee_username, count(distinct assignees.user_id) as assignee_count, co.contact_first_name, co.contact_last_name,
count(distinct files.file_task) as file_count, tlog.task_log_problem, co.contact_department, dept_name ";

$from = "tasks";
$mods = $AppUI->getActiveModules();
//ina no

if (!empty($mods['history']) && !getDenyRead('history'))
{
        $select .= ", history_date as last_update";
        $join = "LEFT JOIN history ON history_item = tasks.task_id AND history_table='tasks' ";
}
$join .= "\nLEFT JOIN projects ON project_id = task_project";

$join .= " \nLEFT JOIN users as usernames ON task_owner = usernames.user_id";
// patch 2.12.04 show assignee and count
$join .= "\n LEFT JOIN user_tasks as ut ON ut.task_id = tasks.task_id";
$join .= "\n LEFT JOIN users as assignees ON assignees.user_id = ut.user_id";
$join .= " \nLEFT JOIN contacts as co ON co.contact_id = usernames.user_contact";

// check if there is log report with the problem flag enabled for the task
$join .= " \nLEFT JOIN task_log AS tlog ON tlog.task_log_task = tasks.task_id AND tlog.task_log_problem > '0'";

// to figure out if a file is attached to task
$join .= "\n LEFT JOIN files on tasks.task_id = files.file_task ";
$join .= "\n LEFT JOIN user_task_pin as pin ON tasks.task_id = pin.task_id AND pin.user_id = ".((($user_id!='all')&&($user_id)) ?  $user_id  : $AppUI->user_id);

$join .= "\nLEFT JOIN departments d ON co.contact_department = d.dept_id ";

$where = $project_id ? " task_project = $project_id" : "project_active != 0";

if ($pinned_only)
        $where .= ' AND task_pinned = 1 ';

if ($_REQUEST["MyC"]==1) $f='all';
switch ($f) {
	case 'all':
		break;
	case 'compl':
		$where.= ' AND task_percent_complete = 100'; 
		break;
		
	case 'nocompl':
		$where.= ' AND task_percent_complete < 100'; 
		break;

	case 'noas':
		$where.=' AND ut.user_id is  NULL';
		break;
	case 'children':
	// patch 2.13.04 2, fixed ambigious task_id
		$where .= "\n	AND task_parent = $task_id AND tasks.task_id != $task_id";	
		break;	
		
}

if ($project_id && $showIncomplete) {
	$where .= "
	AND ( task_percent_complete < 100 or task_percent_complete is null )";
}

$task_status = 0;
if ( $min_view && isset($_GET['task_status']) )
	$task_status = intval( dPgetParam( $_GET, 'task_status', null ) );
else if ( stristr($currentTabName, 'inactive') )
	$task_status = '-1';
else if ( ! $currentTabName)  // If we aren't tabbed we are in the tasks list.
	$task_status = intval( $AppUI->getState( 'inactive' ) );

$where .= " AND task_status = '$task_status'";
if (($user_id_f!='all')&&($user_id_f!='')&&(isset($_REQUEST["MyC"]))) $where .= " AND usernames.user_id = $user_id_f ";

// patch 2.12.04 text search
if ( $search_text2 = $AppUI->getState('searchtext2') )
	$where .= "\n AND (task_name LIKE ('%$search_text2%') OR task_description LIKE ('%$search_text2%') )";
else if ( $search_text = $_POST["searchtext"] )
	$where .= "\n AND (task_name LIKE ('%$search_text%') OR task_description LIKE ('%$search_text%') )";
// filter tasks considering task and project permissions
$projects_filter = '';
$tasks_filter = '';

// TODO: Enable tasks filtering

$allowedProjects = $project->getAllowedSQL($AppUI->user_id, 'task_project');
if($_REQUEST["MyC"]==1) $where = $whereMyC;
else {
	
	if (count($allowedProjects)) $where .= " AND " . implode(" AND ", $allowedProjects);
}

 

//
$obj =& new CTask;
$allowedTasks = $obj->getAllowedSQL($AppUI->user_id);
if ( count($allowedTasks))
	$where .= " AND " . implode(" AND ", $allowedTasks);

// echo "<pre>$where</pre>";

// Filter by company

if ( ! $min_view && $f2 != 'all' ) {
	$join .= "\nLEFT JOIN companies ON company_id = projects.project_company";
	
	
    $where .= "\n AND company_id = " . intval($f2) . " ";
   
}
if (($dep != 'all')&&(strpos($query_string,'m=tasks')>0)&&(isset($dep))&&($dep!='')) {
		//$select.=', co.contact_department, dept_name ';
		//$join .= "\nLEFT JOIN departments d ON co.contact_department = d.dept_id ";
		$where.= " and co.contact_department = $dep";
	}

// patch 2.12.04 ADD GROUP BY clause for assignee count
if (($MyC)&&($whereMyC=='')) $tsql = "SELECT $select FROM $from $join ";
else if ($MyC) $tsql = "SELECT $select FROM $from $join \nWHERE $whereMyC";
else $tsql = "SELECT $select FROM $from $join \nWHERE $where ".($whereMyC=='' ? '' : ' and '.$whereMyC);

$tsql .= "\nGROUP BY task_id" .
  "\nORDER BY project_id, task_start_date";

//echo "<pre>$tsql</pre>";

if ($canViewTask) {
	$ptrc = db_exec( $tsql );
	$nums = db_num_rows( $ptrc );
	echo db_error();
} else {
	$nums = 0;
}

for ($x=0; $x < $nums; $x++) {
	$row = db_fetch_assoc( $ptrc );

	//add information about assigned users into the page output
	$ausql = "SELECT ut.user_id,
	u.user_username, contacts.contact_department, contact_email, ut.perc_assignment, SUM(ut.perc_assignment) AS assign_extent, contact_first_name, contact_last_name, contact_department, dept_name
	FROM user_tasks ut
	LEFT JOIN users u ON u.user_id = ut.user_id
     LEFT JOIN contacts ON u.user_contact = contact_id
     LEFT JOIN departments on contact_department = dept_id
	WHERE ut.task_id=".$row['task_id']."
        GROUP BY ut.user_id";

	$assigned_users = array ();
	$paurc = db_exec( $ausql );
	$nnums = db_num_rows( $paurc );
	echo db_error();
	for ($xx=0; $xx < $nnums; $xx++) {
		$row['task_assigned_users'][] = db_fetch_assoc($paurc);
	}
	//pull the final task row into array
	$projects[$row['task_project']]['tasks'][] = $row;
}

if ( isset($canEdit) && $canEdit && $dPconfig['direct_edit_assignment'])
	$showEditCheckbox = true;
else
	$showEditCheckbox = false;

?>

<script type="text/JavaScript">
function toggle_users(id){
  var element = document.getElementById(id);
  element.style.display = (element.style.display == '' || element.style.display == "none") ? "inline" : "none";
}

<?php
// security improvement:
// some javascript functions may not appear on client side in case of user not having write permissions
// else users would be able to arbitrarily run 'bad' functions
if (isset($canEdit) && $canEdit && $dPconfig['direct_edit_assignment']) {
?>
function checkAll(project_id) {
        var f = eval( 'document.assFrm' + project_id );
        var cFlag = f.master.checked ? false : true;

        for (var i=0;i< f.elements.length;i++){
                var e = f.elements[i];
                // only if it's a checkbox.
                if(e.type == "checkbox" && e.checked == cFlag && e.name != 'master')
                {
                         e.checked = !e.checked;
                }
        }

}

function chAssignment(project_id, rmUser, del) {
        var f = eval( 'document.assFrm' + project_id );
        var fl = f.add_users.length-1;
        var c = 0;
        var a = 0;

        f.hassign.value = "";
        f.htasks.value = "";

        // harvest all checked checkboxes (tasks to process)
        for (var i=0;i< f.elements.length;i++){
                var e = f.elements[i];
                // only if it's a checkbox.
                if(e.type == "checkbox" && e.checked == true && e.name != 'master')
                {
                         c++;
                         f.htasks.value = f.htasks.value +","+ e.value;
                }
        }

        // harvest all selected possible User Assignees
        for (fl; fl > -1; fl--){
                if (f.add_users.options[fl].selected) {
                        a++;
                        f.hassign.value = "," + f.hassign.value +","+ f.add_users.options[fl].value;
                }
        }

        if (del == true) {
                        if (c == 0) {
                                 alert ('<?php echo $AppUI->_('Please select at least one Task!', UI_OUTPUT_JS); ?>');
                        } else if (a == 0 && rmUser == 1){
                                alert ('<?php echo $AppUI->_('Please select at least one Assignee!', UI_OUTPUT_JS); ?>');
                        } else {
                                if (confirm( '<?php echo $AppUI->_('Are you sure you want to unassign the User from Task(s)?', UI_OUTPUT_JS); ?>' )) {
                                        f.del.value = 1;
                                        f.rm.value = rmUser;
                                        f.project_id.value = project_id;
                                        f.submit();
                                }
                        }
        } else {

                if (c == 0) {
                        alert ('<?php echo $AppUI->_('Please select at least one Task!', UI_OUTPUT_JS); ?>');
                } else {

                        if (a == 0) {
                                alert ('<?php echo $AppUI->_('Please select at least one Assignee!', UI_OUTPUT_JS); ?>');
                        } else {
                                f.rm.value = rmUser;
                                f.del.value = del;
                                f.project_id.value = project_id;
                                f.submit();

                        }
                }
        }


}
<?php } ?>
</script>


<?php if ($project_id) { ?>
<br><table width='100%' border='0' cellpadding='1' cellspacing='0'>
<form name='task_list_options' method='POST' action='<?php echo $query_string; ?>'>
<input type='hidden' name='show_task_options' value='1'>
<tr>
	<td align='right'>
		<table>
			<tr>
				<td><?php echo $AppUI->_('Show');?>:</td>
				<td>
					<input type='checkbox' name='show_incomplete' onclick='document.task_list_options.submit();' <?php echo $showIncomplete ? 'checked="checked"' : '';?> />
				</td>
				<td>
					<?php echo $AppUI->_('Incomplete Tasks Only'); ?></td>
				</td>
			</tr>
		</table>
	</td>
</tr>
</form>
</table>
<?php } ?>
<table width="100%" border="0" cellpadding="2" cellspacing="1" class="tbl">
<tr>
	<?if ($_GET["impr"]!=1) {?>
		<th width="10">&nbsp;</th>
	    <th width="10"><?php echo $AppUI->_('Pin'); ?></th>
		<th width="10"><?php echo $AppUI->_('New Log'); ?></th>
	<?}?>
	<th width="20"><?php echo $AppUI->_('Work');?></th>
	<th align="center"><?php echo $AppUI->_('P', 'task_priority', SORT_NUMERIC ); ?></th>
	<th width="150"><?php echo $AppUI->_('Task Name', 'task_name', SORT_STRING );?></th>
	<th nowrap="nowrap"><?php echo $AppUI->_('Task Creator' );?></th>
	<th nowrap="nowrap"><?php echo $AppUI->_('Assigned Users').'/'.$AppUI->_('Department')?></th>
	<th nowrap="nowrap"><?php echo $AppUI->_('etiq_Task Start Date' );?></th>
	<th nowrap="nowrap"><?php echo $AppUI->_('Duration' );?>&nbsp;&nbsp;</th>
	<th nowrap="nowrap"><?php echo $AppUI->_('etiq_Task End Date' );?></th>
	<!--<th nowrap="nowrap"><?php echo $AppUI->_('Last Update' );?></th>-->
        <?php if ($showEditCheckbox) { echo '<th width="1">&nbsp;</th>'; }?>
</tr>
<?php
//echo '<pre>'; print_r($projects); echo '</pre>';
reset( $projects );

foreach ($projects as $k => $p) {
	$tnums = count( @$p['tasks'] );
// don't show project if it has no tasks
// patch 2.12.04, show project if it is the only project in view
	if ($tnums > 0 || $project_id == $p['project_id']) {
//echo '<pre>'; print_r($p); echo '</pre>';
		if (!$min_view) {

                echo "<form name=\"assFrm{$p['project_id']}\" action=\"index.php?m=$m&a=$a\" method=\"post\">
                                <input type=\"hidden\" name=\"del\" value=\"1\" />
                                <input type=\"hidden\" name=\"rm\" value=\"0\" />
                                <input type=\"hidden\" name=\"store\" value=\"0\" />
                                <input type=\"hidden\" name=\"dosql\" value=\"do_task_assign_aed\" />
                                <input type=\"hidden\" name=\"project_id\" value=\"{$p['project_id']}\" />
                                <input type=\"hidden\" name=\"hassign\" />
                                <input type=\"hidden\" name=\"htasks\" />"
?>
<tr>
	<!--<td>
		<a href="index.php?m=tasks&f=<?php echo $f;?>&project_id=<?php echo $project_id ? 0 : $k;?>">
			<img src="./images/icons/<?php echo $project_id ? 'expand.gif' : 'collapse.gif';?>" width="16" height="16" border="0" alt="<?php echo $project_id ? 'show other projects' : 'show only this project';?>">
		</a>
	</td>-->
	<td colspan="<?php echo $dPconfig['direct_edit_assignment'] ? $cols-4 : $cols-1; ?>">
		<table width="100%" border="0">
		<tr>
			<!-- patch 2.12.04 display company name next to project name -->
			<td nowrap style="border: outset #eeeeee 2px;background-color:#<?php echo @$p["project_color_identifier"];?>">
				<a href="./index.php?m=projects&a=view&project_id=<?php echo $k;?>">
				<span style='color:<?php echo bestColor( @$p["project_color_identifier"] ); ?>;text-decoration:none;'><strong><?php echo @$p["company_name"].' :: '.@$p["project_name"];?></strong></span></a>
			</td>
			<td width="<?php echo (101 - intval(@$p["project_percent_complete"]));?>%">
				<?php echo (intval(@$p["project_percent_complete"]));?>%
			</td>
		</tr>
		</table>
        </td>
        <?php if ($dPconfig['direct_edit_assignment']) { 
            // get Users with all Allocation info (e.g. their freeCapacity)
            $tempoTask = new CTask();
            $userAlloc = $tempoTask->getAllocation("user_id");
                ?>
         <td colspan="3" align="right" valign="middle">
                <table width="100%" border="0">
                        <tr>
                                <td align="right">
                                <select name="add_users" style="width:200px" size="2" multiple="multiple" class="text"  ondblclick="javascript:chAssignment('.$user_id.', 0, false)">
                                <?php foreach ($userAlloc as $v => $u) {
                                echo "\n\t<option value=\"".$u['user_id']."\">" . dPformSafe( $u['userFC'] ) . "</option>";
                                }?>
                                </select>
                                </td>
                                 <td align="center">
                                <?php
                                        echo "<a href='javascript:chAssignment({$p['project_id']}, 0, 0);'>".
                                        dPshowImage(dPfindImage('add.png', 'tasks'), 16, 16, 'Assign Users', 'Assign selected Users to selected Tasks')."</a>";
                                        echo  "&nbsp;<a href='javascript:chAssignment({$p['project_id']}, 1, 1);'>".
                                        dPshowImage(dPfindImage('remove.png', 'tasks'), 16, 16, 'Unassign Users', 'Unassign Users from Task')."</a>";
                                ?><br />
                                <?php
                                        echo "<select class=\"text\" name=\"percentage_assignment\" title=\"".$AppUI->_('Assign with Percentage')."\">";
                                        for ($i = 0; $i <= 100; $i+=5) {
                                                echo "<option ".(($i==30)? "selected=\"true\"" : "" )." value=\"".$i."\">".$i."%</option>";
                                        }
                                ?>
                                </select>
                                </td>
                        </tr>
                </table>
         </td>
         <?php }?>
</tr>
<?php
		}
		global $done;
		$done = array();
		if ( $task_sort_item1 != "" )
		{
			if ( $task_sort_item2 != "" && $task_sort_item1 != $task_sort_item2 )
				$p['tasks'] = array_csort($p['tasks'], $task_sort_item1, $task_sort_order1, $task_sort_type1
										  , $task_sort_item2, $task_sort_order2, $task_sort_type2 );
			else $p['tasks'] = array_csort($p['tasks'], $task_sort_item1, $task_sort_order1, $task_sort_type1 );
		}
		//print_r($p['tasks']);
		for ($i=0; $i < $tnums; $i++) {
			$t = $p['tasks'][$i];

			/* 20041118 gregorerhardt bug #311:
			** added the following task status condition to the if clause in order to make sure inactive children
			** are shown in the 'inactive view'; their parents are for instance not listed with them.
			*/
			
			if ($t["task_parent"] == $t["task_id"] || $p['tasks'][$i]["task_status"] != 0) {
			    $is_opened = in_array($t["task_id"], $tasks_opened);
				showtask( $t, 0, $is_opened,'',$_GET["impr"] );
				if($is_opened || !$t["task_dynamic"]){
				   findchild( $p['tasks'], $t["task_id"] );
				}
			} // else showtask( $t, 0, $is_opened );
		}
// check that any 'orphaned' user tasks are also display
		for ($i=0; $i < $tnums; $i++) {
			if ( !in_array( $p['tasks'][$i]["task_id"], $done ) ) {
			    if($p['tasks'][$i]["task_dynamic"] && in_array( $p['tasks'][$i]["task_parent"], $tasks_closed)) {
			        closeOpenedTask($p['tasks'][$i]["task_id"]);
			    }
			    if(in_array($p['tasks'][$i]["task_parent"], $tasks_opened)){
				    showtask( $p['tasks'][$i], 1, false,'',$_GET["impr"]);
			    }
			} 
		
		}

		if($tnums && $dPconfig['enable_gantt_charts'] && !$min_view) { ?>
		<?if ($_GET["impr"]!=1){?><tr>
			<td colspan="<?php echo $cols; ?>" align="right">
				<!--<input type="button" class="button" value="<?php echo $AppUI->_('Reports');?>" onclick="javascript:window.location='index.php?m=projects&a=reports&project_id=<?php echo $k;?>';" />-->
				<input type="button" class="button" value="<?php echo $AppUI->_('Gantt Chart');?>" onclick="javascript:window.location='index.php?m=tasks&a=viewgantt&project_id=<?php echo $k;?>';" />
			</td>
		</tr><?}?>
                </form>
		<?php }
	}
}
$AppUI->setState("tasks_opened", $tasks_opened);
?>
<?if (($_GET["impr"]!=1)&&($m=='tasks')){?>
	<tr>
		<td align="" colspan=11><input type="button" class="button" value="Imprimir resultado de la b&uacute;squeda" onClick="javascript:document.companyFilter.action='?m=tasks&impr=1';document.companyFilter.submit()"></td>
	</tr>
<?}?>
</table>
<?if ($_GET["impr"]!=1){?>
<table>
<tr>
	<td><?php echo $AppUI->_('Key');?>:</td>
	<td>&nbsp; &nbsp;</td>
	<td bgcolor="#ffffff">&nbsp; &nbsp;</td>
	<td>=<?php echo $AppUI->_('Future Task');?></td>
	<td>&nbsp; &nbsp;</td>
	<td bgcolor="#e6eedd">&nbsp; &nbsp;</td>
	<td>=<?php echo $AppUI->_('Started and on time');?></td>
	<td bgcolor="#ffeebb">&nbsp; &nbsp;</td>
	<td>=<?php echo $AppUI->_('Should have started');?></td>
	<td>&nbsp; &nbsp;</td>
	<td bgcolor="#CC6666">&nbsp; &nbsp;</td>
	<td>=<?php echo $AppUI->_('Overdue');?></td>
	<td>&nbsp; &nbsp;</td>
	<td bgcolor="#aaddaa">&nbsp; &nbsp;</td>
	<td>=<?php echo $AppUI->_('Done');?></td>
</tr>
</table><?}?>
