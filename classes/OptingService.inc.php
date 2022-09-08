<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/OptingService.inc.php
 *
 * Copyright (c) 2022 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class OptingService
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Store or query the opt-in/opt-out status of a user.
 */

import('plugins.generic.reviewqualitycollector.RQCPlugin');

define('RQC_OPTING_STATUS_IN',  32);
define('RQC_OPTING_STATUS_OUT', 31);
define('RQC_OPTING_STATUS_UNDEFINED',  30);  // never stored in DB; it indicates no entry is present

/**
 * Store or query the opt-in/opt-out status of a user.
 */
class OptingService
{
	static string $datename = 'rqc_opting_date';
	static string $statusname = 'rqc_opting_status';

	/**
	 * Store timestamped opting status into the DB for this user and journal.
	 * @param $context: context ID
	 * @param $user:	user ID
	 * @param $status:  RQC_OPTING_STATUS_IN or RQC_OPTING_STATUS_OUT
	 */
	public static function setStatus(int $context_id, User $user, int $status)
	{
		if($status != RQC_OPTING_STATUS_IN and $status != RQC_OPTING_STATUS_OUT) {
			trigger_error("Illegal opting status " . $status, E_USER_ERROR);
		}
		$user->updateSetting(self::$datename, gmdate("Y-m-d"), 'string', $context_id);
		$user->updateSetting(self::$statusname, $status, 'int', $context_id);
	}

	/**
	 * Retrieve valid opting status or return RQC_OPTING_STATUS_UNDEFINED.
	 * @param $context: context ID
	 * @param $user:	user ID
	 * @returns $status:  one of RQC_OPTING_STATUS_*
	 */
	public static function getStatus(int $context_id, User $user): int
	{
		$opting_date = $user->getSetting(self::$datename, $context_id);
		if ($opting_date == null) {
			return RQC_OPTING_STATUS_UNDEFINED;  // no opting entry found at all
		}
		$currentyear = (int)substr(gmdate("Y-m-d"), 0, 4);
		$statusyear = (int)substr($opting_date, 0, 4);
		if ($currentyear > $statusyear) {
			return RQC_OPTING_STATUS_UNDEFINED;  // opting entry is outdated
		}
		$opting_status = $user->getSetting(self::$statusname, $context_id);
		return $opting_status;
	}

}
