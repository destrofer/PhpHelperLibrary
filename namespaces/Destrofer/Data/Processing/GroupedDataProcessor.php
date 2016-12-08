<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Data\Processing;

class GroupedDataProcessor
{
	/**
	 * Processes grouped data using supplied callbacks.
	 *
	 * This method iterates through all grouped data items and calls groupProcessCallback when groups change. Make sure
	 * that data is already ordered according to desired grouping, otherwise this method might call groupProcessCallback
	 * multiple times for the same group.
	 *
	 * @param array|\Traversable|\PDOStatement $data A store for enumerable data that must be processed.
	 *
	 * @param callable $groupKeyCallback function(mixed $value, int|string $key) : null|false|int|string|int[]|string[]
	 *
	 * Called for each item unless processing is stopped. Determines which group(s) an item belongs to.
	 *
	 * If callback returns NULL the item is skipped (itemProcessCallback and groupProcessCallback are not called).
	 *
	 * If callback returns FALSE the data processing stops (final groupProcessCallback) is not called.
	 *
	 * If callback returns a number or a string then item is considered belonging to the returned group id.
	 *
	 * If callback returns an indexed or associative array of number or string then the data is parsed using multiple
	 * grouping criteria and an item is considered to belong to groups specified in returned array. The key of such an
	 * array is the criteria id and the value is the group id.
	 *
	 * Note: returning from callback "vegetables" is same as returning array("vegetables").
	 *
	 * @param callable $itemProcessCallback function(mixed $value, int|string $key) : void
	 *
	 * Called for each item unless skipped or processing is stopped.
	 *
	 * @param callable $groupProcessCallback function(int|string $groupId, int|string $criteriaId) : void
	 *
	 * Finalizes the item group (called every time group code(s) returned by groupKeyCallback change). It is also called
	 * in the end of processing if there are any items in the data. Criteria id will always be 0 if groupKeyCallback
	 * returns numbers or strings instead of arrays.
	 *
	 * @throws \Exception Exception is thrown in case of invalid parameters.
	 */
	public static function process($data, $groupKeyCallback, $itemProcessCallback, $groupProcessCallback) {
		if( !is_array($data) && !($data instanceof \Traversable) && !($data instanceof \PDOStatement) )
			throw new \Exception('Data must be an array, traversavle or PDO statement');
		if( !is_callable($groupKeyCallback) || !is_callable($itemProcessCallback) || !is_callable($groupProcessCallback) )
			throw new \Exception('groupKeyCallback, groupProcessCallback and itemProcessCallback must be callable');

		$prevGroupKeys = [];

		if( $data instanceof \PDOStatement ) {
			$key = 0;
			while( $row = $data->fetch(\PDO::FETCH_ASSOC) ) {
				$groupKey = $groupKeyCallback($row, $key);
				if( $groupKey === null )
					continue;
				if( $groupKey === false )
					return;
				if( !is_array($groupKey) )
					$groupKey = [$groupKey];
				foreach( $groupKey as $groupType => $k ) {
					$exists = array_key_exists($groupType, $prevGroupKeys);
					if( !$exists || $k !== $prevGroupKeys[$groupType] ) {
						if( $exists )
							$groupProcessCallback($prevGroupKeys[$groupType], $groupType);
						$prevGroupKeys[$groupType] = $k;
					}
				}
				$itemProcessCallback($row, $key);
				$key++;
			}
		}
		else {
			foreach( $data as $key => $row ) {
				$groupKey = $groupKeyCallback($row, $key);
				if( $groupKey === null )
					continue;
				if( $groupKey === false )
					return;
				if( !is_array($groupKey) )
					$groupKey = [$groupKey];
				foreach( $groupKey as $groupType => $k ) {
					$exists = array_key_exists($groupType, $prevGroupKeys);
					if( !$exists || $k !== $prevGroupKeys[$groupType] ) {
						if( $exists )
							$groupProcessCallback($prevGroupKeys[$groupType], $groupType);
						$prevGroupKeys[$groupType] = $k;
					}
				}
				$itemProcessCallback($row, $key);
			}
		}

		foreach( $prevGroupKeys as $t => $k )
			$groupProcessCallback($k, $t);
	}
}