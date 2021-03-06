<?php

class NotificationsActivityLogs extends ActivityLogs {

	private static function ignoreActionConditions() {
		$actionsToIgnore = array(
			'%/moved_to_trash', 
			'%/moved_to_archive', 
			'time_record/%', 
			'invoice/%'
		);

		$ignore_conditions = array();

		foreach ($actionsToIgnore as $action) {
			$ignore_conditions[] = DB::prepare('action LIKE ?', array($action));
		}// foreach

		$conditions = ' AND NOT (' . implode(' OR ', $ignore_conditions) . ')';

		return $conditions;

	}

	/**
	 * Return recent log entires
	 *
	 * @param IUser $user
	 */
	static function findRecent(IUser $user) {

		$query = self::getDBQuery($user);

		if ($query) {
			
			Logger::log("Inizio a fare la query");
			$timestamp = time();
			$result = DB::execute($query);
			$timestamp = time() - $timestamp;
			Logger::log("Finita query in ".$timestamp." sec");

			if ($result instanceof DBResult) {
				$result -> setCasting(array(
					'id' => DBResult::CAST_INT, 
					'subject_id' => DBResult::CAST_INT, 
					'target_id' => DBResult::CAST_INT, 
					'created_on' => DBResult::CAST_DATETIME, 
					'created_by_id' => DBResult::CAST_INT, 
				));
			}// if

			return $result;

		} else {
			return null;
		} // if
	}// findRecent

	static function countSinceLastVisit(IUser $user) {
		$query = self::getDBQuery($user, 100, ConfigOptions::getValueFor('fmn_last_visited', $user));
		
		if($query) {
			$result = DB::execute($query);
			if ($result instanceof DBResult) {
				return $result->count();
			}// if
		} else {
			return 0;
		}
	}

	/**
	 *
	 */
	private static function getDBQuery(IUser $user, $limit = 30, $created_on = null) {
		list($contexts, $ignore_contexts) = ApplicationObjects::getVisibileContexts($user);
		
		if ($contexts) {
			$query = 'SELECT * FROM ( SELECT * FROM ' . TABLE_PREFIX . 'activity_logs l '
					. 'JOIN (SELECT parent_id, parent_type FROM ' . TABLE_PREFIX . 'subscriptions s WHERE s.user_id=' . $user->getId() . ' GROUP BY parent_id) s ON((l.target_id=s.parent_id AND l.target_type=s.parent_type) OR (l.subject_id=s.parent_id AND l.subject_type=s.parent_type)) '
					. ') ljs '
					. 'WHERE created_by_id <> ' . $user->getId() . ' AND '
					. self::conditionsFromContexts($contexts, $ignore_contexts) 
					. self::ignoreActionConditions();
					// . 'AND subject_id IN (SELECT parent_id FROM ' . TABLE_PREFIX . 'subscriptions WHERE user_id=' . $user->getId() . ' GROUP BY parent_id)';

			if ($created_on instanceof DateTimeValue) {
				
				$query .= ' AND ' . DB::prepare('created_on > ?', array($created_on));
			}// if

			$query .= ' ORDER BY created_on DESC, id DESC LIMIT 0, ' . $limit;

			return $query;
		} else {
			return null;
		} // if
	} // getDBQuery

}
