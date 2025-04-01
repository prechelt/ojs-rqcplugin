<?php

class RqcLogger
{

	/**
	 * @return string Path to a custom ORCID log file.
	 */
	public static function logFilePath(): string
	{
		return Config::getVar('files', 'files_dir') . DIRECTORY_SEPARATOR . 'rqc.log';
	}

	/**
	 * Write info message to log.
	 *
	 * @param  $message string Message to write
	 * @return void
	 */
	public function logInfo($message)
	{
		self::writeLog($message, 'INFO');
	}

	/**
	 * Write error message to log.
	 *
	 * @param  $message string Message to write
	 * @return void
	 */
	public function logError($message)
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
	protected static function writeLog($message, $level)
	{
		if (!is_file(self::logFilePath())) {
			touch(self::logFilePath());
		}
		$fineStamp = date('Y-m-d H:i:s', time());
		error_log("$fineStamp $level $message\n", 3, self::logFilePath());
	}
}
