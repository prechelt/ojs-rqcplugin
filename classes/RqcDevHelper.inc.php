<?php

/**
 * @file     plugins/generic/rqc/classes/RqcDevHelper.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @trait    RqcDevHelper
 * @class    RqcDevHelperStatic
 * @ingroup  plugins_generic_rqc
 *
 * @brief    trait (non-static) providing a _print() method for ad-hoc manual tracing during development.
 *           class providing _staticPrint() and _staticObjectPrint() doing the same for static methods
 */

// namespace APP\plugins\generic\rqc;
import('plugins.generic.rqc.RqcPlugin');

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

class RqcDevHelperStatic // TODO 3: Is there a better variant?
{
	public static function _staticPrint($msg): void
	{
		# print to php -S console stream (during development only)
		if (RqcPlugin::hasDeveloperFunctions()) {
			$stderr = fopen('php://stderr', 'w');
			fwrite($stderr, $msg);
			fclose($stderr);
		}
	}

	public static function _staticObjectPrint($object = null, string $msg = "", bool $printStackTrace = false): void
	{
		self::_staticPrint("\n$msg\n".print_r($object, true)."\n");
		if ($printStackTrace) {
			try {
				throw new Exception("printStackTrace");
			} catch (Exception $e) {
				self::_staticPrint("\nStacktrace:".($e->getTraceAsString())."\n");
			}
		}
	}
}
