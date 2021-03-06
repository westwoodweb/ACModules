<?php

class FrossoTasksTabModModel {
	/**
	 * Find all tasks in project, and prepare them for objects list
	 *
	 * @param Project $project
	 * @param User $user
	 * @param int $state
	 * @return array
	 */
	static function findForObjectsList(Project $project, User $user, $state = STATE_VISIBLE) {
		$result = array();
		$today = strtotime(date('Y-m-d'));
	
		$tasks = DB::execute("SELECT o.id, o.name,
						o.category_id,
						o.milestone_id,
						o.completed_on,
						o.integer_field_1 as task_id,
						o.label_id,
						o.assignee_id,
						o.priority,
						o.delegated_by_id,
						o.state,
						o.visibility,
						o.created_on,
						o.updated_on,
						o.due_on,
						rec.tracked_time
					FROM " . TABLE_PREFIX . "project_objects o 
					LEFT JOIN (SELECT parent_id, sum(value) tracked_time FROM " . TABLE_PREFIX . "time_records WHERE state = ? GROUP BY(parent_id)) rec ON(o.id=rec.parent_id)
					WHERE o.type = 'Task' AND o.project_id = ? AND o.state = ? AND o.visibility >= ? ORDER BY o.id DESC"
				, $state, $project->getId(), $state, $user->getMinVisibility());
		if (is_foreachable($tasks)) {
			$task_url = Router::assemble('project_task', array('project_slug' => $project->getSlug(), 'task_id' => '--TASKID--'));
			$project_id = $project->getId();
	
			$labels = Labels::getIdDetailsMap('AssignmentLabel');
	
			foreach ($tasks as $task) {
					list($total_subtasks, $open_subtasks) = ProjectProgress::getObjectProgress(array(
							'project_id' => $project_id,
							'object_type' => 'Task',
							'object_id' => $task['id'],
					));
	
					$taskObj = new Task($task['id']);
					
					$result[] = array(
							'id'                => $task['id'],
							'name'              => $task['name'],
							'project_id'        => $project_id,
							'category_id'       => $task['category_id'],
							'milestone_id'      => $task['milestone_id'],
							'task_id'           => $task['task_id'],
							'is_completed'      => $task['completed_on'] ? 1 : 0,
							'permalink'         => str_replace('--TASKID--', $task['task_id'], $task_url),
							'label_id'          => $task['label_id'],
							'label'             => $task['label_id'] ? $labels[$task['label_id']] : null,
							'assignee_id'       => $task['assignee_id'],
							'priority'          => $task['priority'],
							'delegated_by_id'   => $task['delegated_by_id'],
							'total_subtasks'    => $total_subtasks,
							'open_subtasks'     => $open_subtasks,
							'estimated_time'    => $taskObj->tracking()->canAdd($user) && $taskObj->tracking()->getEstimate() ? $taskObj->tracking()->getEstimate()->getValue() : 0,
							'tracked_time'      => $taskObj->tracking()->canAdd($user) ? $task['tracked_time'] 	: 0,
							'is_favorite'       => Favorites::isFavorite(array('Task', $task['id']), $user),
							'is_archived'       => $task['state'] == STATE_ARCHIVED ? 1 : 0,
							'visibility'        => $task['visibility'],
							'created_on'		=> $task['created_on'] ? $task['created_on'] : $task['updated_on'],
							'updated_on'		=> $task['updated_on'],
							'has_attachments'	=> $taskObj->attachments()->has() ? true : false,
							'due_on'			=> $task['due_on'] ? $task['due_on'] : lang('No due date set')
					);
			} // foreach
		} // if
	
		return $result;
	} // findForObjectsList
}
