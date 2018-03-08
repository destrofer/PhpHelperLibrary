<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Math;

class Vector2 {
	/** @var float */
	public $x = 0;
	/** @var float */
	public $y = 0;

	public function __construct($x = 0, $y = 0) {
		if( $x instanceof Vector2 || $x instanceof Vector3) {
			$this->x = $x->x;
			$this->y = $x->y;
		}
		else {
			$this->x = $x;
			$this->y = $y;
		}
	}

	/**
	 * @param float|Matrix2|Matrix3|Matrix4 $multiplier
	 * @return Vector2
	 */
	public function multiply($multiplier) {
		$result = clone $this;
		if( $multiplier instanceof Matrix2 ) {
			$result->x = $this->x * $multiplier->m[0][0] + $this->y * $multiplier->m[0][1];
			$result->y = $this->x * $multiplier->m[1][0] + $this->y * $multiplier->m[1][1];
		}
		else if( $multiplier instanceof Matrix3 ) {
			$result->x = $this->x * $multiplier->m[0][0] + $this->y * $multiplier->m[0][1] + $multiplier->m[0][2];
			$result->y = $this->x * $multiplier->m[1][0] + $this->y * $multiplier->m[1][1] + $multiplier->m[1][2];
		}
		else if( $multiplier instanceof Matrix4 ) {
			$result->x = $this->x * $multiplier->m[0][0] + $this->y * $multiplier->m[0][1] + $multiplier->m[0][3];
			$result->y = $this->x * $multiplier->m[1][0] + $this->y * $multiplier->m[1][1] + $multiplier->m[1][3];
		}
		else {
			$result->x *= $multiplier;
			$result->y *= $multiplier;
		}
		return $result;
	}

	/**
	 * @param Vector2|Vector3 $vector
	 * @return Vector2
	 */
	public function add($vector) {
		return new Vector2($this->x + $vector->x, $this->y + $vector->y);
	}

	/**
	 * @param Vector2|Vector3 $vector
	 * @return Vector2
	 */
	public function subtract($vector) {
		return new Vector2($this->x - $vector->x, $this->y - $vector->y);
	}

	public function __toString() {
		return "vector2({$this->x}, {$this->y})";
	}
}