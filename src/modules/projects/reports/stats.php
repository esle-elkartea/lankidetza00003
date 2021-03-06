<?php

function file_size($size)
{
	if (empty($size))
		return '0 B';
        if ($size > 1024*1024*1024)
                return round($size / 1024 / 1024 / 1024, 2) . ' Gb';
        if ($size > 1024*1024)
                return round($size / 1024 / 1024, 2) . ' Mb';
        if ($size > 1024)
                return round($size / 1024, 2) . ' Kb';
        return $size . ' B';
}

$sql = '
SELECT * 
FROM projects
LEFT JOIN tasks ON task_project = project_id';
if (!empty($project_id))
	$sql .= ' WHERE project_id = ' . $project_id;
$all_tasks = db_loadList($sql);

$sql = '
SELECT *, round(sum(task_log_hours),2) as work
FROM projects
LEFT JOIN tasks ON task_project = project_id
LEFT JOIN user_tasks ON user_tasks.task_id = tasks.task_id
LEFT JOIN users ON user_tasks.user_id = users.user_id
LEFT JOIN task_log ON task_log_task = tasks.task_id AND task_log_creator = users.user_id';
if (!empty($project_id))
	$sql .= ' WHERE project_id = ' . $project_id;
$sql .= ' GROUP BY tasks.task_id, users.user_id';
$users_all = db_loadList($sql);

foreach ($users_all as $user)
{
	$users_per_task[$user['task_id']][] = $user['user_id'];
	$users[$user['user_id']]['all'][$user['task_id']] = $user;
	$users[$user['user_id']]['name'] = (!empty($user['user_username']))?$user['user_username']:$user['user_id'];
	$users[$user['user_id']]['hours'] = 0;
	$users[$user['user_id']]['completed'] = array();
	$users[$user['user_id']]['inprogress'] = array();
	$users[$user['user_id']]['pending'] = array();
	$users[$user['user_id']]['overdue'] = array();		
}

$tasks['hours'] = 0;
$tasks['inprogress'] = array();
$tasks['completed'] = array();
$tasks['pending'] = array();
$tasks['overdue'] = array();
foreach($all_tasks as $task)
{
	if ($task['task_percent_complete'] == 100)
		$tasks['completed'][] = & $task;
	else
	{
		if ($task['task_end_date'] < date('Y-m-d'))
			$tasks['overdue'][] = & $task;
		if ($task['task_percent_complete'] == 0)
			$tasks['pending'][] = & $task;
		else
			$tasks['inprogress'][] = & $task;
	}

	if (isset($users_per_task[$task['task_id']]))
	{
		foreach($users_per_task[$task['task_id']] as $user)
		{
			if ($task['task_percent_complete'] == 100)
				$users[$user]['completed'][] = & $task;
			else
			{
				if ($task['task_end_date'] < date('Y-m-d'))
					$users[$user]['overdue'][] = & $task;
				if ($task['task_percent_complete'] == 0)
					$users[$user]['pending'][] = & $task;
				else
					$users[$user]['inprogress'][] = & $task;
			}

			
			$users[$user]['hours'] += $users[$user]['all'][$task['task_id']]['work'];
			$tasks['hours'] += $users[$user]['all'][$task['task_id']]['work'];
		}
	}
}

$sql = '
SELECT sum(file_size)
FROM files
WHERE file_project = ' . $project_id . '
GROUP BY file_project';
$files = db_loadResult($sql);

$ontime = round(100 * (1 - (count($tasks['overdue']) / count($all_tasks)) - (count($tasks['completed']) / count($all_tasks))));
?>

<table width="100%" border="1" cellpadding="0" cellspacing="0" class="tbl">
<tr>
	<th colspan="3">Progress Chart (completed/in progress/pending)</th>
</tr>
<tr height="30">
	<td width="<?php echo round(count($tasks['completed']) / count($all_tasks) * 100); ?>%" style="background: springgreen; text-align: center;">completed</td>
	<td width="<?php echo round(count($tasks['inprogress']) / count($all_tasks) * 100); ?>%" style="background: aquamarine; text-align: center;">in progress</td>
	<td width="<?php echo round(count($tasks['pending']) / count($all_tasks) * 100); ?>%" style="background: gold; text-align: center;">pending</td>
</tr>
</table>
<br />

<table width="100%" border="1" cellpadding="0" cellspacing="0" class="tbl">
<tr>
	<th colspan="3">Time Chart (completed/on time/overdue)</td>
</tr>
<tr height="30">
	<td width="<?php echo round(count($tasks['completed']) / count($all_tasks) * 100); ?>%" style="background: springgreen; text-align: center;">completed</td>
	<td width="<?php echo $ontime; ?>%" style="background: aquamarine; text-align: center;">on time</td>
	<td width="<?php echo round(count($tasks['overdue']) / count($all_tasks) * 100); ?>%" style="background: tomato; text-align: center;">overdue</td>
</tr>
</table>
<br />

<table class="tbl">
<tr>
	<td>
<table width="100%" cellspacing="1" cellpadding="4" border="0" class="tbl">
<tr>
	<th colspan="3">Current Project Status</th>
</tr>
<tr>
	<th>Status</th>
	<th>Task Details</th>
	<th>%</th>
</tr>
<tr>
	<td nowrap>Complete:</td>
	<td><?php echo count($tasks['completed']); ?></td>
	<td><?php echo round(count($tasks['completed']) / count($all_tasks) * 100); ?>%</td>
</tr>
<tr>
	<td nowrap>In Progress:</td>
	<td><?php echo count($tasks['inprogress']); ?></td>
	<td><?php echo round(count($tasks['inprogress']) / count($all_tasks) * 100); ?>%</td>
</tr>
<tr>
	<td nowrap>Not Started:</td>
	<td><?php echo count($tasks['pending']); ?></td>
	<td><?php echo round(count($tasks['pending']) / count($all_tasks) * 100); ?>%</td>
</tr>
<tr>
	<td nowrap>Past Due:</td>
	<td><?php echo count($tasks['overdue']); ?></td>
	<td><?php echo round(count($tasks['overdue']) / count($all_tasks) * 100); ?>%</td>
</tr>
<tr>
	<td>Total:</td>
	<td><?php echo count($all_tasks); ?></td>
	<td>100%</td>
</tr>
</table>
<br />

<table width="100%" cellspacing="1" cellpadding="4" border="0" class="tbl">
<tr>
	<th colspan="2">Project Assignee Details</th>
</tr>
<tr>
	<td>Team Size:</td>
	<td><?php echo count($users); ?> users</td>
</tr>
</table>
<br />

<table width="100%" cellspacing="1" cellpadding="4" border="0" class="tbl">
<tr>
	<th colspan="2">Document Space Utilized</th>
</tr>
<tr>
	<td>Space Utilized:</td>
	<td nowrap="nowrap"><?php echo file_size($files); ?></td>
</tr>
</table>
	</td>
	<td width="100%" valign="top">
<table width="100%" cellspacing="1" cellpadding="4" border="0" class="tbl">
<tr>
	<th>Task Assignee</th>
	<th>Pending Tasks</th>
	<th>Overdue Tasks</th>
	<th>In progress</th>
	<th>Completed Tasks</th>
	<th>Total Tasks</th>
	<th>Hours worked</th>
</tr>
<?php foreach($users as $user => $stats) {?>
<tr>
	<td><?php echo $stats['name']; ?></td>
	<td><?php echo count($stats['pending']); ?></td>
	<td><?php echo count($stats['overdue']); ?></td>
	<td><?php echo count($stats['inprogress']); ?></td>
	<td><?php echo count($stats['completed']); ?></td>
	<td><?php echo count($stats['all']); ?></td>
	<td><?php echo $stats['hours']; ?> hours</td>
</tr>
<?php } ?>
<tr>
	<td class="highlight">Total:</td>
	<td class="highlight"><?php echo count($tasks['pending']); ?></td>
	<td class="highlight"><?php echo count($tasks['pending']); ?></td>
	<td class="highlight"><?php echo count($tasks['inprogress']); ?></td>
	<td class="highlight"><?php echo count($tasks['completed']); ?></td>
	<td class="highlight"><?php echo count($all_tasks); ?></td>
	<td class="highlight"><?php echo $tasks['hours']; ?> hours</td>
</tr>
</table>
	</td>
</tr>
</table>
