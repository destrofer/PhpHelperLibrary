<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Math;

class Vector3 {
	/** @var float */
	public $x = 0;
	/** @var float */
	public $y = 0;
	/** @var float */
	public $z = 0;

	public function __construct($x = 0, $y = 0, $z = 0) {
		if( $x instanceof Vector2 ) {
			$this->x = $x->x;
			$this->y = $x->y;
		}
		if( $x instanceof Vector3 ) {
			$this->x = $x->x;
			$this->y = $x->y;
			$this->z = $x->z;
		}
		else {
			$this->x = $x;
			$this->y = $y;
			$this->z = $z;
		}
	}

	/**
	 * @param float|Matrix3|Matrix4 $multiplier
	 * @return Vector3
	 */
	public function multiply($multiplier) {
		$result = clone $this;
		if( $multiplier instanceof Matrix3 ) {
			$result->x = $this->x * $multiplier->m[0][0] + $this->y * $multiplier->m[0][1] + $this->z * $multiplier->m[0][2];
			$result->y = $this->x * $multiplier->m[1][0] + $this->y * $multiplier->m[1][1] + $this->z * $multiplier->m[1][2];
			$result->z = $this->x * $multiplier->m[2][0] + $this->y * $multiplier->m[2][1] + $this->z * $multiplier->m[2][2];
		}
		else if( $multiplier instanceof Matrix4 ) {
			$result->x = $this->x * $multiplier->m[0][0] + $this->y * $multiplier->m[0][1] + $this->z * $multiplier->m[0][2] + $multiplier->m[0][3];
			$result->y = $this->x * $multiplier->m[1][0] + $this->y * $multiplier->m[1][1] + $this->z * $multiplier->m[1][2] + $multiplier->m[1][3];
			$result->z = $this->x * $multiplier->m[2][0] + $this->y * $multiplier->m[2][1] + $this->z * $multiplier->m[2][2] + $multiplier->m[2][3];
		}
		else {
			$result->x *= $multiplier;
			$result->y *= $multiplier;
			$result->z *= $multiplier;
		}
		return $result;
	}

	/**
	 * @param Vector2|Vector3 $vector
	 * @return Vector3
	 */
	public function add($vector) {
		return new Vector3($this->x + $vector->x, $this->y + $vector->y, $this->z + (($vector instanceof Vector2) ? 1 : $vector->z));
	}

	/**
	 * @param Vector2|Vector3 $vector
	 * @return Vector3
	 */
	public function subtract($vector) {
		return new Vector3($this->x - $vector->x, $this->y - $vector->y, $this->z - (($vector instanceof Vector2) ? 1 : $vector->z));
	}

	public function lengthPow2() {
		return $this->x * $this->x + $this->y * $this->y + $this->z * $this->z;
	}

	public function length() {
		return sqrt($this->x * $this->x + $this->y * $this->y + $this->z * $this->z);
	}

	public function dot(Vector3 $other) {
		return $this->x * $other->x + $this->y * $other->y + $this->z * $other->z;
	}

	public function cross(Vector3 $other) {
		return new Vector3(
			$this->y * $other->z - $this->z * $other->y,
			$this->z * $other->x - $this->x * $other->z,
			$this->x * $other->y - $this->y * $other->x
		);
	}

	public function equals(Vector3 $other) {
		return $this->x == $other->x && $this->y == $other->y && $this->z == $other->z;
	}

	public function __toString() {
		return "vector3({$this->x}, {$this->y}, {$this->z})";
	}

}