<?php

/**
 * @file plugins/generic/rqc/classes/RqcDevHelper.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcDevHelper
 * @ingroup plugins_generic_rqc
 *
 * @brief Base class providing a _print() method for ad-hoc manual tracing during development
 */

// namespace APP\plugins\generic\rqc;

class RqcDevHelper {
	public function __construct() {
		$this->stderr = fopen('php://stderr', 'w');  # print to php -S console stream
	}

	public function _print($msg) {
		# print to php -S console stream (during development only)
		if (RqcPlugin::has_developer_functions()) {
			fwrite($this->stderr, $msg);
		}
	}

}
