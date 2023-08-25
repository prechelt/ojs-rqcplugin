<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/RqcDevHelper.php
 *
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcDevHelper
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Base class providing a _print() method for ad-hoc manual tracing during development
 */

// namespace APP\plugins\generic\reviewqualitycollector;

class RqcDevHelper {
	public function __construct() {
		$this->stderr = fopen('php://stderr', 'w');  # print to php -S console stream
	}

	public function _print($msg) {
		# print to php -S console stream (to be used during development only; remove calls in final code)
		if (RQCPlugin::has_developer_functions()) {
			fwrite($this->stderr, $msg);
		}
	}

}
