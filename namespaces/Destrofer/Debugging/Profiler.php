<?php
/**
 * Copyright 2017 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Debugging;

class Profiler {
	/** @var float */
	private static $startTime;

	/** @var int */
	private static $checkPointLevel = 0;
	/** @var float */
	private static $checkpointPrevTime;
	/** @var ProfilingCheckpoint[] */
	private static $checkpoints = [];

	/** @var ProfilingBlock[] */
	private static $rootBlocks = [];
	/** @var ProfilingBlock[] */
	private static $blockStack = [];

	/**
	 * @var bool
	 */
	public static $enabled = false;

	/**
	 * Prepare the profiler.
	 *
	 * This method should be called as close as possible to the beginning of the application.
	 */
	public static function init() {
		self::$startTime = self::$checkpointPrevTime = microtime(true);
	}

	/**
	 * @param string|null $name
	 * @param int $modLevel
	 * @return ProfilingCheckpoint|null
	 */
	public static function addCheckpoint($name = null, $modLevel = 0) {
		if( !self::$enabled )
			return null;
		$time = microtime(true);
		if( $modLevel < 0 )
			self::$checkPointLevel += $modLevel;
		self::$checkpoints[] = $checkpoint = new ProfilingCheckpoint($name, self::$checkPointLevel, $time, $time - self::$checkpointPrevTime);
		self::$checkpointPrevTime = $time;
		if( $modLevel > 0 )
			self::$checkPointLevel += $modLevel;
		return $checkpoint;
	}

	/**
	 * @param string|null $name
	 * @return ProfilingBlock|null
	 */
	public static function beginBlock($name = null) {
		if( !self::$enabled )
			return null;
		$time = microtime(true);
		$newBlock = new ProfilingBlock($name, count(self::$blockStack), $time);
		if( empty(self::$blockStack) )
			self::$rootBlocks[] = $newBlock;
		else {
			$cnt = count(self::$blockStack);
			$block = end(self::$blockStack);
			$block->children[] = $newBlock;
		}
		self::$blockStack[] = $newBlock;
		return $newBlock;
	}

	/**
	 * @param string|null $appendComment
	 * @return ProfilingBlock|null
	 */
	public static function endBlock($appendComment = null) {
		if( !self::$enabled )
			return null;
		$time = microtime(true);
		if( empty(self::$blockStack) )
			return null;
		$block = array_pop(self::$blockStack);
		$block->duration = $time - $block->time;
		$block->comment = $appendComment;
		$block->memoryDelta = memory_get_usage(true) - $block->memory;
		$block->peakMemory = memory_get_peak_usage(true);
		return $block;
	}

	/**
	 * @return string[]
	 */
	public static function getLog() {
		$outputTime = microtime(true);
		$lines = [];
		if( !empty(self::$checkpoints) ) {
			$lines[] = "CHECKPOINTS";
			$lines[] = sprintf("%-20s %-20s %-20s %-20s   %s", "SINCE BEGINNING", "SINCE PREV CP", "PEAK MEM", "MEM", "CHECKPOINT NAME");
			foreach( self::$checkpoints as $checkpoint )
				$checkpoint->output($lines, self::$startTime);
			$lines[] = "";
		}
		if( !empty(self::$rootBlocks) ) {
			$lines[] = "BLOCKS";
			$lines[] = sprintf("%-20s %-20s %-20s %-20s   %s", "START", "DURATION", "PEAK MEM", "MEM DELTA", "BLOCK NAME");
			foreach(self::$rootBlocks as $block )
				$block->output($lines, self::$startTime, $outputTime);
			$lines[] = "";
		}

		return $lines;
	}
}