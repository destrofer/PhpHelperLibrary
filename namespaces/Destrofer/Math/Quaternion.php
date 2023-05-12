<?php

namespace Destrofer\Math;

use JsonSerializable;

class Quaternion implements JsonSerializable {
	/** @var float */
	public $x = 0;
	/** @var float */
	public $y = 0;
	/** @var float */
	public $z = 0;
	/** @var float */
	public $w = 1;

	/**
	 * @param float|Vector2|Vector3|Quaternion $x
	 * @param float $y
	 * @param float $z
	 * @param float $w
	 */
	public function __construct($x = 0, $y = 0, $z = 0, $w = 1) {
		if( $x instanceof Vector2 ) {
			$this->x = $x->x;
			$this->y = $x->y;
		}
		else if( $x instanceof Vector3 ) {
			$this->x = $x->x;
			$this->y = $x->y;
			$this->z = $x->z;
		}
		else if( $x instanceof Quaternion ) {
			$this->x = $x->x;
			$this->y = $x->y;
			$this->z = $x->z;
			$this->w = $x->w;
		}
		else {
			$this->x = $x;
			$this->y = $y;
			$this->z = $z;
			$this->w = $w;
		}
	}

	/**
	 * @return float
	 */
	public function lengthPow2() {
		return $this->x * $this->x + $this->y * $this->y + $this->z * $this->z + $this->w * $this->w;
	}

	/**
	 * @return float
	 */
	public function length() {
		return sqrt($this->x * $this->x + $this->y * $this->y + $this->z * $this->z + $this->w * $this->w);
	}

	/**
	 * @return Quaternion
	 */
	public function normalize() {
		$l = $this->x * $this->x + $this->y * $this->y + $this->z * $this->z + $this->w * $this->w;
		if( !$l )
			return clone $this;
		return new Quaternion(
			$this->x / $l,
			$this->y / $l,
			$this->z / $l,
			$this->w / $l
		);
	}

	/**
	 * @return array{Vector3, float}
	 */
	public function getAxisAndAngle() {
		$q = (abs($this->w) > 1) ? $this->normalize() : $this;
		$angle = 2 * acos($this->w);
		$t = sqrt(1 - $this->w * $this->w);
		if( $t > 0.0001 ) {
			$t = 1 / $t;
			$axis = new Vector3($this->x * $t, $this->y * $t, $this->z * $t);
		}
		else {
			$axis = new Vector3(1, 0, 0);
		}
		return array($axis, $angle);
	}

	/**
	 * @param Vector3 $axis
	 * @param float $angle
	 * @return Quaternion
	 */
	public static function fromAxisAndAngle($axis, $angle) {
		$l = $axis->lengthPow2();
		if( !$l )
			return new Quaternion();
		$angle *= 0.5;
		$l = sin($angle) / sqrt($l);
		return new Quaternion(
			$axis->x * $l,
			$axis->y * $l,
			$axis->z * $l,
			cos($angle)
		);
	}

	/**
	 * @return Quaternion
	 */
	public function invert() {
		$l = $this->lengthPow2();
		if( !$l )
			return clone $this;
		return new Quaternion(
			$this->x / $l,
			$this->y / $l,
			$this->z / $l,
			$this->w / -$l
		);
	}

	/**
	 * @return Quaternion
	 */
	public function conjugate() {
		return new Quaternion(-$this->x, -$this->y, -$this->z, $this->w);
	}

	/**
	 * @param Quaternion $other
	 * @return Quaternion
	 */
	public function add($other) {
		return new Quaternion(
			$this->x + $other->x,
			$this->y + $other->y,
			$this->z + $other->z,
			$this->w + $other->w
		);
	}

	/**
	 * @param Quaternion $other
	 * @return Quaternion
	 */
	public function subtract($other) {
		return new Quaternion(
			$this->x - $other->x,
			$this->y - $other->y,
			$this->z - $other->z,
			$this->w - $other->w
		);
	}

	/**
	 * @param Quaternion|float $other
	 * @return Quaternion
	 */
	public function multiply($other) {
		if( $other instanceof Quaternion ) {
			// V = V1 * W2 + V2 * W1 + cross(V1, V2)
			// W = W1 * W2 - dot(V1, V2)
			return new Quaternion(
				$this->x * $other->w + $other->x * $this->w + $this->y * $other->z - $this->z * $other->y,
				$this->y * $other->w + $other->y * $this->w + $this->z * $other->x - $this->x * $other->z,
				$this->z * $other->w + $other->z * $this->w + $this->x * $other->y - $this->y * $other->x,
				$this->w * $other->w - ($this->x * $other->x + $this->y * $other->y + $this->z * $other->z)
			);
		}
		return new Quaternion(
			$this->x * $other,
			$this->y * $other,
			$this->z * $other,
			$this->w * $other
		);
	}

	/**
	 * @param Vector3 $v
	 * @return Vector3
	 */
	public function rotateVector($v) {
		// source: https://gamedev.stackexchange.com/questions/28395/rotating-vector3-by-a-quaternion
		$xyz = new Vector3($this->x, $this->y, $this->z);
		return $xyz->multiply(2 * $xyz->dot($v))
			->add($v->multiply($this->w * $this->w - $xyz->dot($xyz)))
			->add($xyz->cross($v)->multiply(2 * $this->w));
	}

	public function __toString() {
		return "quaternion({$this->x}, {$this->y}, {$this->z}, {$this->w})";
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [$this->x, $this->y, $this->z, $this->w];
	}

	/**
	 * @param float[] $json
	 * @return Quaternion
	 */
	public static function fromJson($json) {
		return new Quaternion($json[0], $json[1], $json[2], $json[3]);
	}
}