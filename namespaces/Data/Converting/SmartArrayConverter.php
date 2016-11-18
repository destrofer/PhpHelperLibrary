<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Data\Converting;

/**
 * Converts arrays to objects and vice versa.
 *
 * The converter fills in only existing properties of given instances (except stdClass objects). It uses information
 * from doc blocks to determine how the imported or exported data should actually be treated.
 */
class SmartArrayConverter
{
	/**
	 * Fills in information form given array into the given object.
	 *
	 * @param string|object $newObject An instance of an existing object or a class name for a new instance of an object that must be filled in from array. If parameter is string new instance is created without calling a constructor. If parameter is an instance of an object then the same instance is returned by this method.
	 * @param array $array
	 * @return object
	 */
	public static function fillObjectFromArray($newObject, $array) {
		if( is_string($newObject) ) {
			$class = new \ReflectionClass($newObject);
			$newObject = $class->newInstanceWithoutConstructor();
		}
		if( $newObject instanceof \stdClass ) {
			foreach( $array as $propertyName => &$value )
				$newObject->$propertyName = is_array($value) ? (object)$value : $value;
		}
		else {
			$class = new \ReflectionClass($newObject);
			foreach( $class->getProperties() as $property ) {
				$propertyName = null;
				$docBlock = $property->getDocComment();
				if( $docBlock && preg_match("#@import(?:[ \t]+(?:([^\"\'][^\\s]*)|\"(.*)\"|\'(.*)\'))?#imu", $docBlock, $mtc) )
					$propertyName = isset($mtc[3]) ? $mtc[3] : (isset($mtc[2]) ? $mtc[2] : (isset($mtc[1]) ? $mtc[1] : $property->getName()));
				else
					continue;
				if( !array_key_exists($propertyName, $array) )
					continue;
				$expectedType = ($docBlock && preg_match("#@var[ \t]+([\\\\a-z0-9_]+(?:\\[\\])*)#isu", $docBlock, $mtc)) ? $mtc[1] : 'mixed';
				$value = is_object($array) ? $array->$propertyName : $array[$propertyName];
				$property->setValue($newObject, self::processValue($class->getNamespaceName(), $expectedType, $value));
			}
		}
		return $newObject;
	}

	private static function processValue($namespace, $expectedType, &$value) {
		if( !is_array($value) && !is_object($value) )
			return $value;

		if( preg_match('#^(.*)\\[\\]$#sUu', $expectedType, $mtc) ) {
			$newValue = [];
			foreach( $value as $k => &$v )
				$newValue[$k] = self::processValue($namespace, $mtc[1], $v);
			return $newValue;
		}

		if( $expectedType == 'array' || ($expectedType == 'mixed' && is_array($value)) )
			return self::processValue($namespace, 'mixed[]', $value);

		if( $expectedType == 'object' || $expectedType == 'mixed' )
			$expectedType = '\\stdClass';

		$className = class_exists($expectedType) ? $expectedType : ($namespace . '\\' . $expectedType);
		if( !class_exists($className) )
			return $value;

		if( method_exists($className, 'fromArray') )
			return call_user_func($className . '::fromArray', $value);

		return self::fillObjectFromArray($className, $value);
	}

	/**
	 * Converts given object to an array.
	 *
	 * @param object $object
	 * @return array
	 */
	public static function objectToArray($object) {
		$array = [];
		if( $object instanceof \stdClass ) {
			foreach( $object as $propertyName => $value ) {
				if( is_object($value) ) {
					if( method_exists($value, 'toArray') )
						$value = call_user_func([$value, 'toArray']);
					else
						$value = self::objectToArray($value);
				}
				$array[$propertyName] = $value;
			}
		}
		else {
			$class = new \ReflectionClass($object);
			foreach( $class->getProperties() as $property ) {
				$docBlock = $property->getDocComment();
				if( $docBlock && preg_match("#@export(?:[ \t]+(?:([^\"\'][^\\s]*)|\"(.*)\"|\'(.*)\'))?#imu", $docBlock, $mtc) )
					$propertyName = isset($mtc[3]) ? $mtc[3] : (isset($mtc[2]) ? $mtc[2] : (isset($mtc[1]) ? $mtc[1] : $property->getName()));
				else
					continue;
				$value = $property->getValue($object);
				if( is_object($value) ) {
					if( method_exists($value, 'toArray') )
						$value = call_user_func([$value, 'toArray']);
					else
						$value = self::objectToArray($value);
				}
				$array[$propertyName] = $value;
			}
		}
		return $array;
	}
}