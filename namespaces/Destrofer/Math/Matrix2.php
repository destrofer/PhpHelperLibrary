<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Math;

class Matrix2 {
	/** @var float[][] */
	public $m = [[1, 0], [0, 1]];

	/**
	 * @param Matrix2|Matrix3|Matrix4|float[][]|string $m
	 */
	public function __construct($m) {
		if( $m instanceof Matrix2 )
			$this->m = $m->m;
		else if( $m instanceof Matrix3 || $m instanceof Matrix4)
			$this->m = [[$m->m[0][0], $m->m[0][1]], [$m->m[1][0], $m->m[1][1]]];
		else if( is_array($m) )
			$this->m = [[$m[0][0], $m[0][1]], [$m[1][0], $m[1][1]]];
		else if( is_string($m) ) {
			if( preg_match("#matrix\\(\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*\\)#isu", $m, $mtc) ) {
				$this->m = [[floatval($mtc[1]), floatval($mtc[3])], [floatval($mtc[2]), floatval($mtc[4])]];
			}
			else if( preg_match("#matrix3d\\(\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*,\\s*([0-9\\-\\.]+)\\s*\\)#isu", $m, $mtc) ) {
				$this->m = [[floatval($mtc[1]), floatval($mtc[5])], [floatval($mtc[2]), floatval($mtc[6])]];
			}
		}
	}

	public function getXVector() {
		return new Vector2($this->m[0][0], $this->m[1][0]);
	}

	public function getYVector() {
		return new Vector2($this->m[0][1], $this->m[1][1]);
	}

	public function __toString() {
		return "matrix2([{$this->m[0][0]}, {$this->m[0][1]}], [{$this->m[1][0]}, {$this->m[1][1]}])";
	}
}