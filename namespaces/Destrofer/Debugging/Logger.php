<?php

namespace Destrofer\Debugging;

class Logger {
	const LEVEL_VERBOSE = 0;
	const LEVEL_DEBUG = 1;
	const LEVEL_NOTICE = 2;
	const LEVEL_WARNING = 3;
	const LEVEL_ERROR = 4;
	const LEVEL_NONE = 5;

	private static $colorTags = [
		"{:RESET:}",
		"{:BOLD:}",
		"{:DIM:}",
		"{:ITALIC:}",
		"{:UNDERLINE:}",
		"{:BLINK:}",
		"{:INVERT:}",
		"{:STRIKE:}",
		"{:BLACK:}",
		"{:RED:}",
		"{:GREEN:}",
		"{:YELLOW:}",
		"{:BLUE:}",
		"{:MAGENTA:}",
		"{:CYAN:}",
		"{:WHITE:}",
		"{:GRAY:}",
		"{:B-RED:}",
		"{:B-GREEN:}",
		"{:B-YELLOW:}",
		"{:B-BLUE:}",
		"{:B-MAGENTA:}",
		"{:B-CYAN:}",
		"{:B-WHITE:}",
		"{:BG-BLACK:}",
		"{:BG-RED:}",
		"{:BG-GREEN:}",
		"{:BG-YELLOW:}",
		"{:BG-BLUE:}",
		"{:BG-MAGENTA:}",
		"{:BG-CYAN:}",
		"{:BG-WHITE:}",
		"{:BG-GRAY:}",
		"{:BG-B-RED:}",
		"{:BG-B-GREEN:}",
		"{:BG-B-YELLOW:}",
		"{:BG-B-BLUE:}",
		"{:BG-B-MAGENTA:}",
		"{:BG-B-CYAN:}",
		"{:BG-B-WHITE:}",
	];

	private static $aixColorTable = [
		"\e[0m",
		"\e[1m",
		"\e[2m",
		"\e[3m",
		"\e[4m",
		"\e[5m",
		"\e[7m",
		"\e[9m",
		"\e[30m",
		"\e[31m",
		"\e[32m",
		"\e[33m",
		"\e[34m",
		"\e[35m",
		"\e[36m",
		"\e[37m",
		"\e[90m",
		"\e[91m",
		"\e[92m",
		"\e[93m",
		"\e[94m",
		"\e[95m",
		"\e[96m",
		"\e[97m",
		"\e[40m",
		"\e[41m",
		"\e[42m",
		"\e[43m",
		"\e[44m",
		"\e[45m",
		"\e[46m",
		"\e[47m",
		"\e[100m",
		"\e[101m",
		"\e[102m",
		"\e[103m",
		"\e[104m",
		"\e[105m",
		"\e[106m",
		"\e[107m",
	];

	private static $logLevelNames = [
		self::LEVEL_VERBOSE => "debug",
		self::LEVEL_DEBUG => "debug",
		self::LEVEL_NOTICE => "notice",
		self::LEVEL_WARNING => "warning",
		self::LEVEL_ERROR => "error",
	];

	/**
	 * @var resource|false|null
	 */
	protected $fileHandle = null;
	/**
	 * @var string|null
	 */
	protected $filePath = null;
	/**
	 * @var bool If set to true all logged messages will be echoed to console.
	 */
	public $echoToConsole = false;
	/**
	 * @var int One of Logger::LEVEL_* constants. Messages with level below this level will be ignored. Setting value to
	 *      {@see Logger::LEVEL_NONE} will effectively disable logger.
	 */
	public $minLogLevel = self::LEVEL_DEBUG;

	/**
	 * @var bool When set to true color changing tags in messages will be replaced with console escape sequences before
	 *      writing to log file. Otherwise, if set to false, they will be removed.
	 */
	public $logColorsToFile = false;

	/**
	 * @var bool When set to true color changing tags in messages will be replaced with console escape sequences before
	 *      writing to console. Otherwise, if set to false, they will be removed.
	 */
	public $logColorsToConsole = true;

	/**
	 * @var string|null Format of logged message. Setting format to null is essentially same as setting it to "%m".
	 * "%d" is replaced with current date-time (ISO 8601).
	 * "%D" is replaced with date-time with "Y-m-d H:i:s" format according to PHP {@see date()} formatting (local time).
	 * "%l" is replaced with message log level ("debug", "notice", "warning" or "error")
	 * "%m" is replaced with message passed to logging method.
	 */
	public $format = "[%D] %m";

	/**
	 * @param string $filePath Path of log file to write to. File is not opened until first message is logged. File is
	 *      always opened in append mode. File path can be changed later with {@see Logger::setFilePath()} method.
	 * @param bool $echoToConsole If set to true all logged messages will be echoed to console. Defaults to false,
	 *      but is forced to true if filePath parameter is null.
	 * @param int $minLogLevel One of Logger::LEVEL_* constants. Messages with level below this level will be ignored.
	 *      Setting value to {@see Logger::LEVEL_NONE} will effectively disable logger.
	 * @param null|string $format Format of logged message. See {@see Logger::$format} for format description.
	 */
	public function __construct($filePath = null, $echoToConsole = false, $minLogLevel = self::LEVEL_DEBUG, $format = "[%D] %m") {
		$this->filePath = $filePath;
		$this->echoToConsole = $filePath === null || $echoToConsole;
		$this->minLogLevel = $minLogLevel;
		$this->format = $format;
	}

	protected function close() {
		if( $this->fileHandle ) {
			if( is_resource($this->fileHandle) )
				@fclose($this->fileHandle);
			$this->fileHandle = null;
		}
	}

	public function __destruct() {
		$this->close();
	}

	/**
	 * @param string|null $filePath Path of log file to write to. If a file is already open then it gets closed
	 *      immediately. New file is not opened until next message is logged. Setting path to null will stop logging to
	 *      file, but unlike constructor, it will not force logging to console. To start logging to console set
	 *      {@see Logger::$echoToConsole} field to true.
	 * @return void
	 */
	public function setFilePath($filePath) {
		$this->close();
		$this->filePath = $filePath;
	}

	/**
	 * Removes or replaces color tags in the message with aixterm escape sequences
	 * ({@link https://sites.ualberta.ca/dept/chemeng/AIX-43/share/man/info/C/a_doc_lib/cmds/aixcmds1/aixterm.htm}).
	 *
	 * List of supported tags:
	 *
	 * Resets all styles and effects: {:RESET:}. Don't forget to use this tag at the end of a message, otherwise effects
	 * will be applied to all next logged messages until this tag is encountered.
	 *
	 * Adds a style of an effect: {:BOLD:}, {:DIM:}, {:ITALIC:}, {:UNDERLINE:}, {:BLINK:}, {:INVERT:}, {:STRIKE:}.
	 *
	 * Sets text color: {:BLACK:}, {:RED:}, {:GREEN:}, {:YELLOW:}, {:BLUE:}, {:MAGENTA:}, {:CYAN:}, {:WHITE:}.
	 *
	 * Sets bright text color: {:GRAY:}, {:B-RED:}, {:B-GREEN:}, {:B-YELLOW:}, {:B-BLUE:}, {:B-MAGENTA:}, {:B-CYAN:},
	 * {:B-WHITE:}.
	 *
	 * Sets background color: {:BG-BLACK:}, {:BG-RED:}, {:BG-GREEN:}, {:BG-YELLOW:}, {:BG-BLUE:}, {:BG-MAGENTA:},
	 * {:BG-CYAN:}, {:BG-WHITE:}.
	 *
	 * Sets bright background color: {:BG-GRAY:}, {:BG-B-RED:}, {:BG-B-GREEN:}, {:BG-B-YELLOW:}, {:BG-B-BLUE:},
	 * {:BG-B-MAGENTA:}, {:BG-B-CYAN:}, {:BG-B-WHITE:}.
	 *
	 * @param string $message Message containing color tags.
	 * @param bool $removeColors If set to true tags will be removed from the message.
	 * @return string
	 */
	public static function replaceColorTags($message, $removeColors = false) {
		return str_replace(self::$colorTags, $removeColors ? "" : self::$aixColorTable, $message);
	}

	/**
	 * @param int $logLevel Must be one of Logger::LEVEL_* constants. Any value above {@see Logger::LEVEL_ERROR} will
	 *      force logging regardless of what is the value of {@see Logger::$minLogLevel} field.
	 * @param string $message Message to log to file and/or console. A new line (\n) is always added to the end of the
	 *      message. Color tags inside message will be replaced with aixterm
	 *      ({@link https://sites.ualberta.ca/dept/chemeng/AIX-43/share/man/info/C/a_doc_lib/cmds/aixcmds1/aixterm.htm})
	 *      compatible escape sequences. See {@see Logger::replaceColorTags()} for the list of supported color tags.
	 * @return void
	 */
	public function log($logLevel, $message) {
		if( $logLevel <= Logger::LEVEL_ERROR && $this->minLogLevel > $logLevel )
			return;
		if( $this->fileHandle === null && $this->filePath !== null )
			$this->fileHandle = fopen($this->filePath, "a");
		if( $this->fileHandle || $this->echoToConsole ) {
			if( $this->format !== null && $this->format !== "%m" ) {
				$time = time();
				$message = str_replace([
					"%d",
					"%D",
					"%l",
					"%m"
				], [
					date("c", $time),
					date("Y-m-d H:i:s", $time),
					isset(self::$logLevelNames[$logLevel]) ? self::$logLevelNames[$logLevel] : "user",
					$message
				], $this->format);
			}
			if( $this->fileHandle && is_resource($this->fileHandle) ) // is_resource check is required to check if file was forcibly closed by PHP when shutting down
				@fputs($this->fileHandle, self::replaceColorTags("{$message}\n", !$this->logColorsToFile));
			if( $this->echoToConsole )
				echo self::replaceColorTags("{$message}\n", !$this->logColorsToConsole);
		}
	}

	/**
	 * @param string $message Message to log to file and/or console. A new line (\n) is always added to the end of the
	 *      message. Color tags inside message will be replaced with aixterm
	 *      ({@link https://sites.ualberta.ca/dept/chemeng/AIX-43/share/man/info/C/a_doc_lib/cmds/aixcmds1/aixterm.htm})
	 *      compatible escape sequences. See {@see Logger::replaceColorTags()} for the list of supported color tags.
	 *      Logged message will have {@see Logger::LEVEL_ERROR} level.
	 * @return void
	 */
	public function error($message) {
		$this->log(self::LEVEL_ERROR, $message);
	}

	/**
	 * @param string $message Message to log to file and/or console. A new line (\n) is always added to the end of the
	 *      message. Color tags inside message will be replaced with aixterm
	 *      ({@link https://sites.ualberta.ca/dept/chemeng/AIX-43/share/man/info/C/a_doc_lib/cmds/aixcmds1/aixterm.htm})
	 *      compatible escape sequences. See {@see Logger::replaceColorTags()} for the list of supported color tags.
	 *      Logged message will have {@see Logger::LEVEL_WARNING} level.
	 * @return void
	 */
	public function warning($message) {
		$this->log(self::LEVEL_WARNING, $message);
	}

	/**
	 * @param string $message Message to log to file and/or console. A new line (\n) is always added to the end of the
	 *      message. Color tags inside message will be replaced with aixterm
	 *      ({@link https://sites.ualberta.ca/dept/chemeng/AIX-43/share/man/info/C/a_doc_lib/cmds/aixcmds1/aixterm.htm})
	 *      compatible escape sequences. See {@see Logger::replaceColorTags()} for the list of supported color tags.
	 *      Logged message will have {@see Logger::LEVEL_NOTICE} level.
	 * @return void
	 */
	public function notice($message) {
		$this->log(self::LEVEL_NOTICE, $message);
	}

	/**
	 * @param string $message Message to log to file and/or console. A new line (\n) is always added to the end of the
	 *      message. Color tags inside message will be replaced with aixterm
	 *      ({@link https://sites.ualberta.ca/dept/chemeng/AIX-43/share/man/info/C/a_doc_lib/cmds/aixcmds1/aixterm.htm})
	 *      compatible escape sequences. See {@see Logger::replaceColorTags()} for the list of supported color tags.
	 *      Logged message will have {@see Logger::LEVEL_DEBUG} level.
	 * @return void
	 */
	public function debug($message) {
		$this->log(self::LEVEL_DEBUG, $message);
	}

	/**
	 * @param string $message Message to log to file and/or console. A new line (\n) is always added to the end of the
	 *      message. Color tags inside message will be replaced with aixterm
	 *      ({@link https://sites.ualberta.ca/dept/chemeng/AIX-43/share/man/info/C/a_doc_lib/cmds/aixcmds1/aixterm.htm})
	 *      compatible escape sequences. See {@see Logger::replaceColorTags()} for the list of supported color tags.
	 *      Logged message will have {@see Logger::LEVEL_VERBOSE} level. Used for more detailed debugging.
	 * @return void
	 */
	public function verbose($message) {
		$this->log(self::LEVEL_VERBOSE, $message);
	}

}