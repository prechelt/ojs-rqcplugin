<?php

namespace APP\plugins\generic\rqc\classes;

use PKP\config\Config;


/**
 * class to unify logging within the RQC plugin
 * logFilePath() specifies where the custom log files lies
 * all the log methods use a unified logging message: [dateTime] [logLevel] message
 * logLevel can be: INFO, WARN, ERROR
 *
 * @ingroup plugins_generic_rqc
 */
class RqcLogger
{

	/**
	 * @return string Path to a custom RQC log file.
	 */
	protected static function logFilePath(): string
	{
		return Config::getVar('files', 'files_dir') . DIRECTORY_SEPARATOR . 'rqc.log';
	}

	protected static function infoLoggingOn(): bool
	{
		return Config::getVar('rqc', 'rqc_log_info_messages', true);
	}

	/**
	 * Write info message to log.
	 *
	 * @param  $message string Message to write
	 * @return void
	 */
	public static function logInfo(string $message): void
	{
		if (self::infoLoggingOn()) {
			self::writeLog($message, 'INFO');
		}
	}

	/**
	 * Write warn message to log.
	 *
	 * @param  $message string Message to write
	 * @return void
	 */
	public static function logWarning(string $message): void
	{
		self::writeLog($message, 'WARN');
	}

	/**
	 * Write error message to log.
	 *
	 * @param  $message string Message to write
	 * @return void
	 */
	public static function logError(string $message): void
	{
		self::writeLog($message, 'ERROR');
	}

	/**
	 * Write a message with specified level to log
	 *
	 * @param  $message string Message to write
	 * @param  $level   string Error level to add to message
	 * @return void
	 */
	protected static function writeLog(string $message, string $level): void
	{
		if (!$message) return;
		if (!in_array($level, array('INFO', 'WARN', 'ERROR'))) return;
		if (!is_file(self::logFilePath())) {
			touch(self::logFilePath());
		}
		$fineStamp = date('Y-m-d H:i:s', time());
		error_log("[$fineStamp]\t[$level]\t$message\n\n", 3, self::logFilePath());
	}
}
