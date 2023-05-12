<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Math;

class Matrix4 {
	/** @var float[][] */
	public $m = [[1, 0, 0, 0], [0, 1, 0, 0], [0, 0, 1, 0], [0, 0, 0, 1]];

	/**
	 * @param Matrix2|Matrix3|Matrix4|float[][]|string $m
	 */
	public function __construct($m = null) {
		if( $m instanceof Matrix2 )
			$this->m = [[$m->m[0][0], $m->m[0][1], 0, 0], [$m->m[1][0], $m->m[1][1], 0, 0], [0, 0, 1, 0], [0, 0, 0, 1]];
		else if( $m instanceof Matrix3)
			$this->m = [[$m->m[0][0], $m->m[0][1], $m->m[0][2], 0], [$m->m[1][0], $m->m[1][1], $m->m[1][2], 0], [$m->m[2][0], $m->m[2][1], $m->m[2][2], 0], [0, 0, 0, 1]];
		else if( $m instanceof Matrix4 )
			$this->m = $m->m;
		else if( is_array($m) )
			$this->m = [[$m[0][0], $m[0][1], $m[0][2], $m[0][3]], [$m[1][0], $m[1][1], $m[1][2], $m[1][3]], [$m[2][0], $m[2][1], $m[2][2], $m[2][3]], [$m[3][0], $m[3][1], $m[3][2], $m[3][3]]];
		else if( is_string($m) ) {
			if( preg_match("#matrix\\(\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*\\)#isu", $m, $mtc) ) {
				$this->m = [[floatval($mtc[1]), floatval($mtc[3]), 0, floatval($mtc[5])], [floatval($mtc[2]), floatval($mtc[4]), 0, floatval($mtc[6])], [0, 0, 1, 0], [0, 0, 0, 1]];
			}
			else if( preg_match("#matrix3d\\(\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*\\)#isu", $m, $mtc) ) {
				$this->m = [[floatval($mtc[1]), floatval($mtc[5]), floatval($mtc[9]), floatval($mtc[13])], [floatval($mtc[2]), floatval($mtc[6]), floatval($mtc[10]), floatval($mtc[14])], [floatval($mtc[3]), floatval($mtc[7]), floatval($mtc[11]), floatval($mtc[15])], [floatval($mtc[4]), floatval($mtc[8]), floatval($mtc[12]), floatval($mtc[16])]];
			}
		}
	}

	/**
	 * @return Vector3
	 */
	public function getXVector() {
		return new Vector3($this->m[0][0], $this->m[1][0], $this->m[2][0]);
	}

	/**
	 * @return Vector3
	 */
	public function getYVector() {
		return new Vector3($this->m[0][1], $this->m[1][1], $this->m[2][1]);
	}

	/**
	 * @return Vector3
	 */
	public function getZVector() {
		return new Vector3($this->m[0][2], $this->m[1][2], $this->m[2][2]);
	}

	/**
	 * @return Vector3
	 */
	public function getWVector() {
		return new Vector3($this->m[0][3], $this->m[1][3], $this->m[2][3]);
	}

	/**
	 * @return float
	 */
	function determinant() {
		list(
			list($a11, $a12, $a13, $a14),
			list($a21, $a22, $a23, $a24),
			list($a31, $a32, $a33, $a34),
			list($a41, $a42, $a43, $a44),
			) = $this->m;

		$j11 = $a22 * ($a33 * $a44 - $a34 * $a43)
			+ $a23 * ($a34 * $a42 - $a32 * $a44)
			+ $a24 * ($a32 * $a43 - $a33 * $a42);

		$j21 = $a12 * ($a33 * $a44 - $a34 * $a43)
			+ $a13 * ($a34 * $a42 - $a32 * $a44)
			+ $a14 * ($a32 * $a43 - $a33 * $a42);

		$j31 = $a12 * ($a23 * $a44 - $a24 * $a43)
			+ $a13 * ($a24 * $a42 - $a22 * $a44)
			+ $a14 * ($a22 * $a43 - $a23 * $a42);

		$j41 = $a12 * ($a23 * $a34 - $a24 * $a33)
			+ $a13 * ($a24 * $a32 - $a22 * $a34)
			+ $a14 * ($a22 * $a33 - $a23 * $a32);

		return $a11 * $j11 - $a21 * $j21 + $a31 * $j31 - $a41 * $j41;
	}

	/**
	 * @return Matrix4
	 */
	function transpose() {
		return new Matrix4([
			[$this->m[0][0], $this->m[1][0], $this->m[2][0], $this->m[3][0]],
			[$this->m[0][1], $this->m[1][1], $this->m[2][1], $this->m[3][1]],
			[$this->m[0][2], $this->m[1][2], $this->m[2][2], $this->m[3][2]],
			[$this->m[0][3], $this->m[1][3], $this->m[2][3], $this->m[3][3]],
		]);
	}

	/**
	 * @return Matrix4
	 */
	function invert() {
		list(
			list($a11, $a12, $a13, $a14),
			list($a21, $a22, $a23, $a24),
			list($a31, $a32, $a33, $a34),
			list($a41, $a42, $a43, $a44),
			) = $this->m;

		$j11 = $a22 * ($a33 * $a44 - $a34 * $a43)
			+ $a23 * ($a34 * $a42 - $a32 * $a44)
			+ $a24 * ($a32 * $a43 - $a33 * $a42);

		$j12 = $a21 * ($a33 * $a44 - $a34 * $a43)
			+ $a23 * ($a34 * $a41 - $a31 * $a44)
			+ $a24 * ($a31 * $a43 - $a33 * $a41);

		$j13 = $a21 * ($a32 * $a44 - $a34 * $a42)
			+ $a22 * ($a34 * $a41 - $a31 * $a44)
			+ $a24 * ($a31 * $a42 - $a32 * $a41);

		$j14 = $a21 * ($a32 * $a43 - $a33 * $a42)
			+ $a22 * ($a33 * $a41 - $a31 * $a43)
			+ $a23 * ($a31 * $a42 - $a32 * $a41);


		$j21 = $a12 * ($a33 * $a44 - $a34 * $a43)
			+ $a13 * ($a34 * $a42 - $a32 * $a44)
			+ $a14 * ($a32 * $a43 - $a33 * $a42);

		$j22 = $a11 * ($a33 * $a44 - $a34 * $a43)
			+ $a13 * ($a34 * $a41 - $a31 * $a44)
			+ $a14 * ($a31 * $a43 - $a33 * $a41);

		$j23 = $a11 * ($a32 * $a44 - $a34 * $a42)
			+ $a12 * ($a34 * $a41 - $a31 * $a44)
			+ $a14 * ($a31 * $a42 - $a32 * $a41);

		$j24 = $a11 * ($a32 * $a43 - $a33 * $a42)
			+ $a12 * ($a33 * $a41 - $a31 * $a43)
			+ $a13 * ($a31 * $a42 - $a32 * $a41);


		$j31 = $a12 * ($a23 * $a44 - $a24 * $a43)
			+ $a13 * ($a24 * $a42 - $a22 * $a44)
			+ $a14 * ($a22 * $a43 - $a23 * $a42);

		$j32 = $a11 * ($a23 * $a44 - $a24 * $a43)
			+ $a13 * ($a24 * $a41 - $a21 * $a44)
			+ $a14 * ($a21 * $a43 - $a23 * $a41);

		$j33 = $a11 * ($a22 * $a44 - $a24 * $a42)
			+ $a12 * ($a24 * $a41 - $a21 * $a44)
			+ $a14 * ($a21 * $a42 - $a22 * $a41);

		$j34 = $a11 * ($a22 * $a43 - $a23 * $a42)
			+ $a12 * ($a23 * $a41 - $a21 * $a43)
			+ $a13 * ($a21 * $a42 - $a22 * $a41);


		$j41 = $a12 * ($a23 * $a34 - $a24 * $a33)
			+ $a13 * ($a24 * $a32 - $a22 * $a34)
			+ $a14 * ($a22 * $a33 - $a23 * $a32);

		$j42 = $a11 * ($a23 * $a34 - $a24 * $a33)
			+ $a13 * ($a24 * $a31 - $a21 * $a34)
			+ $a14 * ($a21 * $a33 - $a23 * $a31);

		$j43 = $a11 * ($a22 * $a34 - $a24 * $a32)
			+ $a12 * ($a24 * $a31 - $a21 * $a34)
			+ $a14 * ($a21 * $a32 - $a22 * $a31);

		$j44 = $a11 * ($a22 * $a33 - $a23 * $a32)
			+ $a12 * ($a23 * $a31 - $a21 * $a33)
			+ $a13 * ($a21 * $a32 - $a22 * $a31);

		$d = $a11 * $j11 - $a21 * $j21 + $a31 * $j31 - $a41 * $j41;
		if( !$d )
			return clone $this;

		$d = 1 / $d;

		return new Matrix4([
			[+$d*$j11, -$d*$j21, +$d*$j31, -$d*$j41],
			[-$d*$j12, +$d*$j22, -$d*$j32, +$d*$j42],
			[+$d*$j13, -$d*$j23, +$d*$j33, -$d*$j43],
			[-$d*$j14, +$d*$j24, -$d*$j34, +$d*$j44],
		]);
	}

	public function __toString() {
		return "matrix4([{$this->m[0][0]}, {$this->m[0][1]}, {$this->m[0][2]}, {$this->m[0][3]}], [{$this->m[1][0]}, {$this->m[1][1]}, {$this->m[1][2]}, {$this->m[1][3]}], [{$this->m[2][0]}, {$this->m[2][1]}, {$this->m[2][2]}, {$this->m[2][3]}], [{$this->m[3][0]}, {$this->m[3][1]}, {$this->m[3][2]}, {$this->m[3][3]}])";
	}
}