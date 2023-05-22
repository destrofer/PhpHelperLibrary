<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Math;

use JsonSerializable;

class Vector2 implements JsonSerializable {
	/** @var float */
	public $x = 0;
	/** @var float */
	public $y = 0;

	/** @var Vector2 */
	public static $zero;
	/** @var Vector2 */
	public static $left;
	/** @var Vector2 */
	public static $right;
	/** @var Vector2 */
	public static $down;
	/** @var Vector2 */
	public static $up;

	public static function staticConstruct() {
		self::$zero = new Vector2(0, 0);
		self::$left = new Vector2(-1, 0);
		self::$right = new Vector2(1, 0);
		self::$down = new Vector2(0, -1);
		self::$up = new Vector2(0, 1);
	}

	/**
	 * @param float|Vector2|Vector3 $x
	 * @param float $y
	 */
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
	 * @param float|Vector2|Vector3|Matrix2|Matrix3|Matrix4 $multiplier
	 * @return Vector2
	 */
	public function multiply($multiplier) {
		$result = clone $this;
		if( $multiplier instanceof Vector2 || $multiplier instanceof Vector3 ) {
			$result->x = $this->x * $multiplier->x;
			$result->y = $this->y * $multiplier->y;
		}
		else if( $multiplier instanceof Matrix2 ) {
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

	/**
	 * @param float $t
	 * @param Vector2 $vector1
	 * @param Vector2 $vector2
	 * @return Vector2
	 */
	public static function lerp($t, $vector1, $vector2) {
		return new Vector2(
			$vector1->x + $t * ($vector2->x - $vector1->x),
			$vector1->y + $t * ($vector2->y - $vector1->y)
		);
	}

	/**
	 * @return float
	 */
	public function lengthPow2() {
		return $this->x * $this->x + $this->y * $this->y;
	}

	/**
	 * @return float
	 */
	public function length() {
		return sqrt($this->x * $this->x + $this->y * $this->y);
	}

	/**
	 * @return Vector2
	 */
	public function normalize() {
		$l = $this->x * $this->x + $this->y * $this->y;
		if( !$l )
			return clone $this;
		$l = sqrt($l);
		return new Vector2($this->x / $l, $this->y / $l);
	}

	/**
	 * @param Vector2 $other
	 * @return float
	 */
	public function dot(Vector2 $other) {
		return $this->x * $other->x + $this->y * $other->y;
	}

	/**
	 * @return Vector2
	 */
	public function perpendicular() {
		return new Vector2(
			-$this->y,
			$this->x
		);
	}

	/**
	 * @param Vector2 $other
	 * @return bool
	 */
	public function equals(Vector2 $other) {
		return $this->x == $other->x && $this->y == $other->y;
	}

	public function __toString() {
		return "vector2({$this->x}, {$this->y})";
	}

	/**
	 * @return float[]
	 */
	public function jsonSerialize() {
		return [$this->x, $this->y];
	}

	/**
	 * @param float[] $json
	 * @return Vector2
	 */
	public static function fromJson($json) {
		return new Vector2($json[0], $json[1]);
	}
}

Vector2::staticConstruct();