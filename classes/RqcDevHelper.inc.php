<?php

/**
 * @file plugins/generic/rqc/classes/RqcDevHelper.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @trait RqcDevHelper
 * @ingroup plugins_generic_rqc
 *
 * @brief trait providing a _print() method for ad-hoc manual tracing during development
 */

// namespace APP\plugins\generic\rqc;

trait RqcDevHelper
{
	private $stderr = null;

	public function _print($msg): void
	{
		if ($this->stderr === null) {
			$this->stderr = fopen('php://stderr', 'w');  # open php -S console stream
		}
		if (RqcPlugin::hasDeveloperFunctions()) { # print to php -S console stream (during development only)
			fwrite($this->stderr, $msg);
		}
	}
}

class RqcDevHelperStatic
{
	public static function _staticPrint($msg): void // TODO 3: Is there a better variant?
	{
		# print to php -S console stream (during development only)
		if (RqcPlugin::hasDeveloperFunctions()) {
			$stderr = fopen('php://stderr', 'w');
			fwrite($stderr, $msg);
			fclose($stderr);
		}
	}

}
