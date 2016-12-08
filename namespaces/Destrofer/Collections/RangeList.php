<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Collections;

use \InvalidArgumentException;

class RangeList {
	public $ranges = [];

	/**
	 * @param array $newRange An array with at least two keys: 0 for range start and 1 for range end.
	 * @param bool $checkIntersections If TRUE ranges that intersect with the new range are compared for priority and one of them is removed, truncated or split in two.
	 * @param callable $priorityCheckCallback Must be either NULL or a callable function that is called on item range collision. The function must accept two parameters (two collided items) and return which item must keep its range unchanged (0 for first item and 1 for second). Defaults to NULL, which gives same results as giving function that always returns 0.
	 */
	public function add($newRange, $checkIntersections = true, $priorityCheckCallback = null) {
		$secondaryRanges = [];

		if( $checkIntersections ) {
			if( $priorityCheckCallback && !is_callable($priorityCheckCallback) )
				throw new InvalidArgumentException("priorityCheckCallback argument must be either NULL or a callable");
			foreach($this->ranges as $idx => $range ) {
				if( $newRange[1] > $range[0] && $newRange[0] < $range[1] ) {
					$priority = $priorityCheckCallback ? $priorityCheckCallback($newRange, $range) : 0;
					if( $priority ) {
						// keep already existing item intact
						if( $newRange[0] >= $range[0] && $newRange[1] <= $range[1] ) {
							$newRange = null;
							break;
						}
						if( $newRange[0] < $range[0] ) {
							if( $newRange[1] > $range[1] ) {
								// split new item in two
								$secondaryRange = $newRange;
								$secondaryRange[0] = $range[1];
								$secondaryRanges[] = $secondaryRange;
							}
							$newRange[1] = $range[0]; // truncate ending of new item
						}
						else
							$newRange[0] = $range[1]; // truncate beginning of new item
					}
					else {
						// keep the new item intact
						if( $range[0] >= $newRange[0] && $range[1] <= $newRange[1] )
							unset($this->ranges[$idx]); // remove existing item
						else if( $range[0] < $newRange[0] ) {
							if( $range[1] > $newRange[1] ) {
								// split existing item in two
								$secondaryRange = $range;
								$secondaryRange[0] = $newRange[1];
								$secondaryRanges[] = $secondaryRange;
							}
							$this->ranges[$idx][1] = $newRange[0]; // truncate ending of existing item
						}
						else
							$this->ranges[$idx][0] = $newRange[1]; // truncate beginning of existing item
					}
				}
			}
		}

		if( !empty($secondaryRanges) ) {
			foreach( $secondaryRanges as $secondaryRange )
				$this->add($secondaryRange, $checkIntersections, $priorityCheckCallback);
		}

		if( $newRange )
			$this->ranges[] = $newRange;
	}

	/**
	 * Sorts the stored ranges.
	 * 
	 * @param callback $callback (optional) Either NULL or a valid comparison callback that is used with usort function. If NULL is passed then the default sorting is used - by range start. Defaults to NULL.
	 */
	public function sort($callback = null) {
		if( $callback )
			usort($this->ranges, $callback);
		else
			usort($this->ranges, function($a, $b) {
				if( $a[0] == $b[0] )
					return 0;
				return ($a[0] < $b[0]) ? -1 : 1;
			});
	}

	/**
	 * Returns an array of items that have range intersecting with given range.
	 *
	 * WARNING: Returned array contains references to stored ranges, not their copies. Changing
	 * any item will affect data stored in the RangeList.
	 *
	 * @param mixed $from
	 * @param mixed $to
	 * @return array[]
	 */
	public function find($from, $to) {
		$ranges = [];
		foreach($this->ranges as &$range ) {
			if( $to > $range[0] && $from < $range[1] )
				$ranges[] = &$range;
		}
		return $ranges;
	}

	/**
	 * Returns an array of intersections between given items and stored ranges.
	 *
	 * WARNING: Returned array contains references to items and stored ranges, not their copies. Changing
	 * any range will affect data stored in the RangeList.
	 *
	 * Each intersection in the returned array is itself an array, where:
	 * - element 0 has a range of intersection;
	 * - element 1 has the item that intersected with the range in element 2;
	 * - element 2 has the range that intersected with the item in element 1.
	 *
	 * @param array[]|RangeList $items May be either an array of items or another RangeList. If it is an array each item must be an array itself containing at least two keys: 0 for item range start and 1 for item range end.
	 * @return array[]
	 */
	public function findIntersections(&$items) {
		if( $items instanceof RangeList )
			return $this->findIntersections($items->ranges);

		$intersections = [];
		foreach( $items as &$item ) {
			foreach($this->ranges as &$range ) {
				if( $item[1] > $range[0] && $item[0] < $range[1] ) {
					$intersections[] = [
						[
							max($item[0], $range[0]),
							min($item[1], $range[1]),
						],
						&$item,
						&$range,
					];
				}
			}
		}

		return $intersections;
	}
}