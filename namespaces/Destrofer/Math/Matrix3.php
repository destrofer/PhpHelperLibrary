<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Math;

class Matrix3 {
	/** @var float[][] */
	public $m = [[1, 0, 0], [0, 1, 0], [0, 0, 1]];

	/**
	 * @param Matrix2|Matrix3|Matrix4|float[][]|string $m
	 */
	public function __construct($m = null) {
		if( $m instanceof Matrix2 )
			$this->m = [[$m->m[0][0], $m->m[0][1], 0], [$m->m[1][0], $m->m[1][1], 0], [0, 0, 1]];
		else if( $m instanceof Matrix3)
			$this->m = $m->m;
		else if( $m instanceof Matrix4 )
			$this->m = [[$m->m[0][0], $m->m[0][1], $m->m[0][2]], [$m->m[1][0], $m->m[1][1], $m->m[1][2]], [$m->m[2][0], $m->m[2][1], $m->m[2][2]]];
		else if( is_array($m) )
			$this->m = [[$m[0][0], $m[0][1], $m[0][2]], [$m[1][0], $m[1][1], $m[1][2]], [$m[2][0], $m[2][1], $m[2][2]]];
		else if( is_string($m) ) {
			if( preg_match("#matrix\\(\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*\\)#isu", $m, $mtc) ) {
				$this->m = [[floatval($mtc[1]), floatval($mtc[3]), floatval($mtc[5])], [floatval($mtc[2]), floatval($mtc[4]), floatval($mtc[6])], [0, 0, 1]];
			}
			else if( preg_match("#matrix3d\\(\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*\\)#isu", $m, $mtc) ) {
				$this->m = [[floatval($mtc[1]), floatval($mtc[5]), floatval($mtc[9])], [floatval($mtc[2]), floatval($mtc[6]), floatval($mtc[10])], [floatval($mtc[3]), floatval($mtc[7]), floatval($mtc[11])]];
			}
		}
	}

	public function getXVector() {
		return new Vector3($this->m[0][0], $this->m[1][0], $this->m[2][0]);
	}

	public function getYVector() {
		return new Vector3($this->m[0][1], $this->m[1][1], $this->m[2][1]);
	}

	public function getZVector() {
		return new Vector3($this->m[0][2], $this->m[1][2], $this->m[2][2]);
	}

	public function __toString() {
		return "matrix3([{$this->m[0][0]}, {$this->m[0][1]}, {$this->m[0][2]}], [{$this->m[1][0]}, {$this->m[1][1]}, {$this->m[1][2]}], [{$this->m[2][0]}, {$this->m[2][1]}, {$this->m[2][2]}])";
	}
}