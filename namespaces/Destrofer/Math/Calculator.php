<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Math;

class Calculator {
	const ERROR_VARIABLE_NOT_FOUND = 'variable_not_found';
	const ERROR_CIRCULAR_REFERENCE = 'circular_reference';
	const ERROR_INVALID_FORMULA = 'invalid_formula';

	/**
	 * @param string $formula
	 * @param string[] $vars
	 * @return bool|string
	 */
	public static function compileFormula($formula, $vars = []) {
		// tokenize the formula
		$rules = [
			'(?<r0>' . implode('|', $vars) . ')', // variables
			'(?<r1>-?[0-9]+(?:\.[0-9]+)?)', // numbers
			'(?<r2>[\\+\\*/])', // operators
			'(?<r3>-)', // operator "-"
			'(?<r4>\\()', // "("
			'(?<r5>\\))', // ")"
		];

		if( !preg_match_all('#\\s*(' . implode('|', $rules) . ')#su', $formula, $mtc) )
			return false;

		$data = [];
		foreach($mtc as $k => $parts) {
			if( is_numeric($k) )
				continue;
			$t = substr($k, 1);
			foreach( $parts as $idx => $part )
				if( $part !== '' )
					$data[$idx] = [$part, $t];
		}
		ksort($data);
		$data = array_values($data);

		// validate the syntax
		$nestingLevel = 0;
		$tokens = "";
		$prevPart = null;
		foreach( $data as $idx => $part ) {
			if( $prevPart && $part[1] == 1 && ($prevPart[1] == 0 || $prevPart[1] == 1 || $prevPart[1] == 2 || $prevPart[1] == 3 || $prevPart[1] == 5) ) {
				// treat negative number as  "-" operator followed by a positive number if prior to it was
				// something different than ")"
				if( substr($part[0], 0, 1) == '-' ) {
					$tokens .= 3;
					$data[$idx][0] = $part[0] = substr($part[0], 1);
					array_splice($data, $idx, 0, [['-', 3]]);
				}
			}
			$tokens .= $part[1];
			if( $part[1] == '4' )
				$nestingLevel++;
			else if( $part[1] == '5' ) {
				$nestingLevel--;
				if( $nestingLevel < 0 )
					return false; // unexpected ")"
			}
			$prevPart = $part;
		}

		if( $nestingLevel > 0 )
			return false; // there are more open brackets than closed
		if( preg_match('#^[25]#', $tokens) )
			return false; // formula starts from "+*/)"
		if( preg_match('#[234]$#', $tokens) )
			return false; // formula ends with "+-*/("
		if( preg_match('#^[3][235]#', $tokens) )
			return false; // formula starts from a "-" operator followed by "+-*/)"
		if( preg_match('#[015][014]#', $tokens) )
			return false; // variable/number/")" followed by variable/number/"("
		if( preg_match('#33#', $tokens) )
			return false; // two minuses in a row
		if( preg_match('#[24][3][^014]#', $tokens) )
			return false; // "+*/(" followed by "-" followed by something other than number/variable/"("
		if( preg_match('#[234][25]#', $tokens) )
			return false; // "+-*/(" followed by "+*/)"

		// create the PHP code
		$code = "return ";
		foreach( $data as $part )
			$code .= ($part[1] ? '' : '$') . $part[0];
		$code .= ';';

		try {
			foreach( $vars as $idx => $var )
				$$var = 1 + $idx;
			if( ($res = @eval($code)) === false ) {
				// there is a slight probability of division by zero during the first test so we try again with other
				// numbers
				foreach( $vars as $idx => $var )
					$$var = mt_rand(2, 99);
				if( ($res = @eval($code)) === false )
					return false;
			}
		}
		catch(\Exception $ex) {
			return false;
		}

		return $code;
	}

	/**
	 * @param string $formula
	 * @param float[] $vars
	 * @return float|null
	 */
	public static function executeFormula($formula, $vars = []) {
		$formula = self::compileFormula($formula, array_keys($vars));
		if( $formula === false )
			return null;
		extract($vars);
		return eval($formula);
	}

	/**
	 * @param string $formula
	 * @param string[] $vars
	 * @return bool
	 */
	public static function validateFormula($formula, $vars = []) {
		return self::compileFormula($formula, $vars) !== false;
	}

	/**
	 * @param string $formula
	 * @return string[]
	 */
	public static function getVariablesFromFormula($formula) {
		if( !preg_match_all('#[a-z][a-z0-9_]*#isu', $formula, $mtc) )
			return [];
		return $mtc[0];
	}

	private static function buildDependenciesInternal(&$cellData, $varName, $allowNonExisting, $checkedVars) {
		if( !isset($cellData[$varName]['formula']) || $cellData[$varName]['formula'] === '' ) {
			$cellData[$varName]['dependencies'] = [];
			return true;
		}

		$cellData[$varName]['dependencies'] = self::getVariablesFromFormula($cellData[$varName]['formula']);

		if( self::compileFormula($cellData[$varName]['formula'], $cellData[$varName]['dependencies']) === false ) {
			$cellData[$varName]['error'] = [self::ERROR_INVALID_FORMULA];
			return false;
		}

		$checkedVars[] = $varName; // must be before intersection to check so that cell does not point to itself
		$intersection = array_intersect($cellData[$varName]['dependencies'], $checkedVars);
		if( $intersection ) {
			$cellData[$varName]['error'] = [self::ERROR_CIRCULAR_REFERENCE, $intersection];
			return false;
		}

		if( !$allowNonExisting ) {
			$missing = array_diff($cellData[$varName]['dependencies'], array_keys($cellData));
			if( $missing ) {
				$cellData[$varName]['error'] = [self::ERROR_VARIABLE_NOT_FOUND, $missing];
				return false;
			}
		}

		if( isset($cellData[$varName]['error']) )
			return false;
		foreach( $cellData[$varName]['dependencies'] as $dependencyVar ) {
			if( isset($cellData[$dependencyVar]['error']) ) {
				$err = $cellData[$dependencyVar]['error'];
				if( $err[0] == self::ERROR_CIRCULAR_REFERENCE ) {
					if( !isset($cellData[$varName]['error']) )
						$cellData[$varName]['error'] = [self::ERROR_CIRCULAR_REFERENCE, []];
					$cellData[$varName]['error'][1][] = $dependencyVar;
				}
			}
		}
		if( isset($cellData[$varName]['error']) )
			return false;

		foreach( $cellData[$varName]['dependencies'] as $dependencyVar ) {
			if( ($allowNonExisting && !isset($cellData[$dependencyVar])) || isset($cellData[$dependencyVar]['dependencies']) )
				continue;
			if( !self::buildDependenciesInternal($cellData, $dependencyVar, $allowNonExisting, $checkedVars) )
				return false;
		}

		return true;
	}

	private static function calculateInternal(&$cellData, &$calculated, $varName, $valueKey) {
		$calculated[$varName] = true;
		if( !isset($cellData[$varName]['formula']) || $cellData[$varName]['formula'] === '' )
			return;

		$vars = [];
		foreach( $valueKey as $k )
			$vars[$k] = [];
		foreach( $cellData[$varName]['dependencies'] as $dependencyVar ) {
			if( !isset($cellData[$dependencyVar]) ) {
				$vars[$dependencyVar] = 0;
				continue;
			}
			self::calculateInternal($cellData, $calculated, $dependencyVar, $valueKey);

			foreach( $valueKey as $k )
				$vars[$k][$dependencyVar] = isset($cellData[$dependencyVar][$k]) ? $cellData[$dependencyVar][$k] : 0;
		}

		if( !isset($cellData[$varName]['skip_calc']) || !$cellData[$varName]['skip_calc'] )
			foreach( $valueKey as $k )
				$cellData[$varName][$k] = self::executeFormula($cellData[$varName]['formula'], $vars[$k]);
	}

	public static function buildDependencies(&$cellData, $allowNonExisting = false) {
		$result = true;
		foreach( array_keys($cellData) as $varName ) {
			if( isset($cellData[$varName]['error']) || $cellData[$varName]['dependencies'] )
				continue;
			if( !self::buildDependenciesInternal($cellData, $varName, $allowNonExisting, []) )
				$result = false;
		}

		if( !$result ) {
			// propagate circular reference errors as much as possible
			do {
				$repeat = false;
				foreach( array_keys($cellData) as $varName ) {
					if( isset($cellData[$varName]['error']) )
						continue;
					self::buildDependenciesInternal($cellData, $varName, $allowNonExisting, []);
					$repeat = $repeat || isset($cellData[$varName]['error']);
				}
			} while($repeat);
		}

		return $result;
	}

	public static function calculate(&$cellData, $valueKey = 'value', $allowNonExisting = false) {
		$calculated = [];
		if( !self::buildDependencies($cellData, $allowNonExisting) )
			return false;
		if( !is_array($valueKey) )
			$valueKey = [$valueKey];
		foreach( array_keys($cellData) as $varName )
			self::calculateInternal($cellData, $calculated, $varName, $valueKey);
		return true;
	}
}