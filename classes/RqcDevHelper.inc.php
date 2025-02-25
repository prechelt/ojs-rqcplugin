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

class RqcDevHelper { // TODO 2: Use trait instead of a Class: https://www.geeksforgeeks.org/multiple-inheritance-in-php/
	public function __construct() {
		$this->stderr = fopen('php://stderr', 'w');  # print to php -S console stream
	}

	public function _print($msg) {
		# print to php -S console stream (during development only)
		if (RqcPlugin::hasDeveloperFunctions()) {
			fwrite($this->stderr, $msg);
		}
	}

	public static function _staticPrint($msg) { // TODO 3: Is there a better variant?
		# print to php -S console stream (during development only)
		if (RqcPlugin::hasDeveloperFunctions()) {
			$stderr = fopen('php://stderr', 'w');
			fwrite($stderr, $msg);
			fclose($stderr);
		}
	}

}
