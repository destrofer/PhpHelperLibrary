<?php
/**
 * Copyright 2020 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Collections;

class AreaTree {
	const MAX_AREAS = 18; // 18 seems to be optimal according to tests on website having many imported elements
	
	public $axis = "y";
	public $median = null;
	public $leaves = null;
	public $areas = null;

	/**
	 * @param object[] $areas List of areas to build tree from. See {@see AreaTree::build()} for area object requirements.
	 * @param string $axis Area list splitting axis. Defaults to "y".
	 * @see AreaTree::build()
	 */
	public function __construct(array $areas = null, $axis = "y") {
		if( $areas !== null )
			$this->build($areas, $axis);
	}

	/**
	 * Build area tree node from list of areas.
	 *
	 * Each area in the array must me an instance of an object with following fields:
	 * - **id** `string` Area identifier. Used by searching algorithm to prevent duplicate records for areas that intersect medians.
	 * - **x** `float` Area horizontal position.
	 * - **y** `float` Area vertical position.
	 * - **width** `float` Area width.
	 * - **height** `float` Area height.
	 *
	 * @param object[] $areas List of areas to build tree from.
	 * @param string $axis Area list splitting axis. Defaults to "y".
	 * @param bool $tryDifferentAxis Should build algorithm try splitting on a different axis if unable to split areas into two nodes using current axis.
	 */
	public function build(array $areas, $axis = "y", $tryDifferentAxis = true) {
		$this->axis = $axis;
		$this->median = null;
		$this->leaves = null;
		$cnt = count($areas);
		$this->areas = ($cnt <= self::MAX_AREAS) ? $areas : null;
		if( $this->areas )
			return;
		
		$this->median = 0;

		$medianCnt = 0;
		if( $axis == "x" ) {
			foreach( $areas as $area ) {
				if( $area->width > 0 ) {
					$this->median += $area->x + $area->width / 2;
					$medianCnt++;
				}
			}
		}
		else {
			foreach( $areas as $area ) {
				if( $area->height > 0 ) {
					$this->median += $area->y + $area->height / 2;
					$medianCnt++;
				}
			}
		}
		if( $medianCnt == 0 ) {
			$medianCnt = $cnt;
			if( $axis == "x" ) {
				foreach( $areas as $area )
					$this->median += $area->x + $area->width / 2;
			}
			else {
				foreach( $areas as $area )
					$this->median += $area->y + $area->height / 2;
			}
		}

		$this->median = round($this->median / $medianCnt);

		$left = [];
		$right = [];
		if( $axis == "x" ) {
			foreach( $areas as $area ) {
				if( $area->x < $this->median || ($area->width === 0 && $area->x === $this->median) )
					$left[] = $area;
				if( $area->x + $area->width > $this->median || ($area->width === 0 && $area->x === $this->median) )
					$right[] = $area;
			}
		}
		else {
			foreach( $areas as $area ) {
				if( $area->y < $this->median || ($area->height === 0 && $area->y === $this->median) )
					$left[] = $area;
				if( $area->y + $area->height > $this->median || ($area->height === 0 && $area->y === $this->median) )
					$right[] = $area;
			}
		}
		
		$nextAxis = ($axis != "x") ? "x" : "y";
		
		if( count($left) == $cnt || count($right) == $cnt ) {
			if( $tryDifferentAxis )
				$this->build($areas, $nextAxis, false);
			else
				$this->areas = $areas;
			return;
		}
		
		$this->leaves = [
			new AreaTree($left, $nextAxis),
			new AreaTree($right, $nextAxis),
		];
	}
	
	protected function search($x1, $y1, $x2, $y2, &$results) {
		if( $this->areas ) {
			foreach( $this->areas as $area ) {
				if( $area->x < $x2
					&& $area->y < $y2
					&& $area->x + $area->width > $x1
					&& $area->y + $area->height > $y1
				)
					$results[$area->id] = $area;
			}
		}
		else if( $this->axis == "x" ) {
			if( $x1 < $this->median )
				$this->leaves[0]->search($x1, $y1, $x2, $y2, $results);
			if( $x2 > $this->median )
				$this->leaves[1]->search($x1, $y1, $x2, $y2, $results);
		}
		else {
			if( $y1 < $this->median )
				$this->leaves[0]->search($x1, $y1, $x2, $y2, $results);
			if( $y2 > $this->median )
				$this->leaves[1]->search($x1, $y1, $x2, $y2, $results);
		}
	}

	/**
	 * Find all areas intersecting specified rectangle.
	 *
	 * @param float $x
	 * @param float $y
	 * @param float $width
	 * @param float $height
	 * @return object[] An associated array of areas where key is area id.
	 */
	public function find($x, $y, $width, $height) {
		$results = [];
		$this->search($x, $y, $x + $width, $y + $height, $results);
		return $results;
	}

	/**
	 * Outputs tree to the console.
	 *
	 * @param int $indent
	 */
	public function output($indent = 0) {
		$iStr = str_repeat(" ", $indent);
		if( $this->areas ) {
			echo "{$iStr}{$this->axis} {$this->median}:\n";
			foreach( $this->areas as $area )
				echo "{$iStr} {$area->id} {$area->x},{$area->y} {$area->width}x{$area->height}\n";
		}
		else {
			echo "{$iStr}{$this->axis} < {$this->median}\n";
			$this->leaves[0]->output($indent + 1);

			echo "{$iStr}{$this->axis} >= {$this->median}\n";
			$this->leaves[1]->output($indent + 1);
		}
	}
}
