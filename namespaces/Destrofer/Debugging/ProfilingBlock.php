<?php
/**
 * Copyright 2017 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Debugging;

class ProfilingBlock {
	/** @var string */
	public $name;
	/** @var int */
	public $indent = 0;
	/** @var float */
	public $time;
	/** @var int */
	public $memory;
	/** @var int */
	public $memoryDelta = 0;
	/** @var int */
	public $peakMemory = 0;
	/** @var float|null */
	public $duration;
	/** @var string */
	public $comment = null;
	/** @var ProfilingBlock[] */
	public $children = [];

	/** @var int */
	public static $nextIndex = 1;

	/**
	 * @param null|string $name
	 * @param int $indent
	 * @param float $time
	 * @param null|float $duration
	 */
	public function __construct($name = null, $indent = 0, $time = 0.0, $duration = null) {
		$this->memory = memory_get_usage(true);
		$this->name = (($name === null) ? ("block " . (self::$nextIndex++)) : $name);
		$this->indent = $indent;
		$this->time = $time;
		$this->duration = $duration;
	}

	/**
	 * @param string[] $lines
	 * @param float $startTime
	 * @param float $outputTime
	 */
	public function output(&$lines, $startTime, $outputTime) {
		$duration = ($this->duration === null) ? ($outputTime - $this->time) . " *" : $this->duration;
		$text = $this->name . ($this->comment ? " [{$this->comment}]" : "");
		$lines[] = sprintf(
			"%-20s %-20s %-20s %-20s   %s",
			sprintf("%0.9f", $this->time - $startTime),
			sprintf("%0.9f", $duration),
			number_format($this->peakMemory, 0, "", " "),
			number_format($this->memoryDelta, 0, "", " "),
			str_repeat(" ", max(0, $this->indent)) . $text
		);
		foreach( $this->children as $child )
			$child->output($lines, $startTime, $outputTime);
	}
}