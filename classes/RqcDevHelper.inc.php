<?php

// namespace APP\plugins\generic\rqc;

import('plugins.generic.rqc.RqcPlugin');

/**
 * class providing writeToConsole() and writeObjectToConsole() for ad-hoc manual tracing during development
 *
 * @ingroup  plugins_generic_rqc
 */
class RqcDevHelper // TODO 3: Is there a better variant?
{
	/**
	 * RqcDevHelper::writeToConsole // for easy copying
	 * write the $msg to the console
	 */
	public static function writeToConsole(string $msg): void
	{
		if (RqcPlugin::hasDeveloperFunctions()) { # print to php -S console stream (during development only)
			$stderr = fopen('php://stderr', 'w');
			fwrite($stderr, $msg);
			fclose($stderr);
		}
	}

	/**
	 * RqcDevHelper::writeObjectToConsole // for easy copying
	 * the object will be written with print_r to the console
	 * if $msg is given it is put in front of print_r($object)
	 * if $printStackTrace = true: write the stacktrace from where this method is called in a newline in java-style
	 */
	public static function writeObjectToConsole($object, string $msg = "", bool $printStackTrace = false): void
	{
		self::writeToConsole("\n$msg\n" . print_r($object, true) . "\n");
		if ($printStackTrace) {
			try {
				throw new Exception("printStackTrace");
			} catch (Exception $e) {
				self::writeToConsole("\nStacktrace:" . ($e->getTraceAsString()) . "\n");
			}
		}
	}
}
