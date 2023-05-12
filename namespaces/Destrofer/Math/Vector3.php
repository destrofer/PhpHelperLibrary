<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Math;

use JsonSerializable;

class Vector3 implements JsonSerializable {
	/** @var float */
	public $x = 0;
	/** @var float */
	public $y = 0;
	/** @var float */
	public $z = 0;

	/** @var Vector3 */
	public static $left;
	/** @var Vector3 */
	public static $right;
	/** @var Vector3 */
	public static $down;
	/** @var Vector3 */
	public static $up;
	/** @var Vector3 */
	public static $back;
	/** @var Vector3 */
	public static $forward;

	public static function staticConstruct() {
		self::$left = new Vector3(-1, 0, 0);
		self::$right = new Vector3(1, 0, 0);
		self::$down = new Vector3(0, -1, 0);
		self::$up = new Vector3(0, 1, 0);
		self::$back = new Vector3(0, 0, -1);
		self::$forward = new Vector3(0, 0, 1);
	}

	/**
	 * @param float|Vector2|Vector3 $x
	 * @param float $y
	 * @param float $z
	 */
	public function __construct($x = 0, $y = 0, $z = 0) {
		if( $x instanceof Vector2 ) {
			$this->x = $x->x;
			$this->y = $x->y;
		}
		else if( $x instanceof Vector3 ) {
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
	 * @param float|Vector3|Matrix3|Matrix4|Quaternion $multiplier
	 * @return Vector3
	 */
	public function multiply($multiplier) {
		$result = clone $this;
		if( $multiplier instanceof Vector3 ) {
			$result->x = $this->x * $multiplier->x;
			$result->y = $this->y * $multiplier->y;
			$result->z = $this->z * $multiplier->z;
		}
		else if( $multiplier instanceof Matrix3 ) {
			$result->x = $this->x * $multiplier->m[0][0] + $this->y * $multiplier->m[0][1] + $this->z * $multiplier->m[0][2];
			$result->y = $this->x * $multiplier->m[1][0] + $this->y * $multiplier->m[1][1] + $this->z * $multiplier->m[1][2];
			$result->z = $this->x * $multiplier->m[2][0] + $this->y * $multiplier->m[2][1] + $this->z * $multiplier->m[2][2];
		}
		else if( $multiplier instanceof Matrix4 ) {
			$x = $this->x - $multiplier->m[3][0];
			$y = $this->y - $multiplier->m[3][1];
			$z = $this->z - $multiplier->m[3][2];
			$result->x = $x * $multiplier->m[0][0] + $y * $multiplier->m[0][1] + $z * $multiplier->m[0][2] + $multiplier->m[0][3];
			$result->y = $x * $multiplier->m[1][0] + $y * $multiplier->m[1][1] + $z * $multiplier->m[1][2] + $multiplier->m[1][3];
			$result->z = $x * $multiplier->m[2][0] + $y * $multiplier->m[2][1] + $z * $multiplier->m[2][2] + $multiplier->m[2][3];
		}
		else if( $multiplier instanceof Quaternion ) {
			$result = $multiplier->rotateVector($this);
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
		return new Vector3($this->x + $vector->x, $this->y + $vector->y, $this->z + (($vector instanceof Vector2) ? 0 : $vector->z));
	}

	/**
	 * @param Vector2|Vector3 $vector
	 * @return Vector3
	 */
	public function subtract($vector) {
		return new Vector3($this->x - $vector->x, $this->y - $vector->y, $this->z - (($vector instanceof Vector2) ? 0 : $vector->z));
	}

	/**
	 * @return float
	 */
	public function lengthPow2() {
		return $this->x * $this->x + $this->y * $this->y + $this->z * $this->z;
	}

	/**
	 * @return float
	 */
	public function length() {
		return sqrt($this->x * $this->x + $this->y * $this->y + $this->z * $this->z);
	}

	/**
	 * @return Vector3
	 */
	public function normalize() {
		$l = $this->x * $this->x + $this->y * $this->y + $this->z * $this->z;
		if( !$l )
			return clone $this;
		$l = sqrt($l);
		return new Vector3($this->x / $l, $this->y / $l, $this->z / $l);
	}

	/**
	 * @param Vector3 $other
	 * @return float
	 */
	public function dot(Vector3 $other) {
		return $this->x * $other->x + $this->y * $other->y + $this->z * $other->z;
	}

	/**
	 * @param Vector3 $other
	 * @return Vector3
	 */
	public function cross(Vector3 $other) {
		return new Vector3(
			$this->y * $other->z - $this->z * $other->y,
			$this->z * $other->x - $this->x * $other->z,
			$this->x * $other->y - $this->y * $other->x
		);
	}

	/**
	 * @param Vector3 $other
	 * @return bool
	 */
	public function equals(Vector3 $other) {
		return $this->x == $other->x && $this->y == $other->y && $this->z == $other->z;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return "vector3({$this->x}, {$this->y}, {$this->z})";
	}

	/**
	 * @return float[]
	 */
	public function jsonSerialize() {
		return [$this->x, $this->y, $this->z];
	}

	/**
	 * @param float[] $json
	 * @return Vector3
	 */
	public static function fromJson($json) {
		return new Vector3($json[0], $json[1], $json[2]);
	}
}

Vector3::staticConstruct();