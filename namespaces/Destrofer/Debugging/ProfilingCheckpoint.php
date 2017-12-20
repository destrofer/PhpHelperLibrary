<?php
/**
 * Copyright 2017 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Debugging;

class ProfilingCheckpoint {
	/** @var string */
	public $name;
	/** @var int */
	public $indent;
	/** @var float */
	public $time;
	/** @var float */
	public $delta;
	/** @var int */
	public $memory;
	/** @var int */
	public $peakMemory;

	/** @var int */
	public static $nextIndex = 1;

	/**
	 * @param null|string $name
	 * @param int $indent
	 * @param float $time
	 * @param float $delta
	 */
	public function __construct($name = null, $indent = 0, $time = 0.0, $delta = 0.0) {
		$this->name = (($name === null) ? ("checkpoint " . (self::$nextIndex++)) : $name);
		$this->indent = $indent;
		$this->time = $time;
		$this->delta = $delta;
		$this->memory = memory_get_usage(true);
		$this->peakMemory = memory_get_peak_usage(true);
	}

	/**
	 * @param string[] $lines
	 * @param float $startTime
	 */
	public function output(&$lines, $startTime) {
		$lines[] = sprintf(
			"%-20s %-20s   %s",
			sprintf("%0.9f", $this->time - $startTime),
			sprintf("%0.9f", $this->delta),
			number_format($this->peakMemory, 0, "", " "),
			number_format($this->memory, 0, "", " "),
			str_repeat(" ", max(0, $this->indent)) . $this->name
		);
	}
}