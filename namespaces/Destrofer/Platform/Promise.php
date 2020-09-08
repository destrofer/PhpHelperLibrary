<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Platform;
use Destrofer\Platform\Exceptions\UncaughtPromiseErrorException;

/**
 * An implementation of promises very similar to ECMAScript 6
 *
 * @package Destrofer\Platform
 */
class Promise {
	const STATE_EXECUTING = 0;
	const STATE_FULFILLED = 1;
	const STATE_REJECTED = 2;

	/** @var int */
	private $id;
	/** @var \Generator */
	private $generator;
	/** @var int */
	private $state;
	/** @var array[] */
	private $callStack = [];
	/** @var callable */
	private $finallyCallback = null;
	/** @var mixed */
	private $value = null;
	/** @var mixed */
	private $error = null;
	/** @var null|\Exception */
	private $throwException = null;
	/** @var null|Promise */
	private $chainedPromise = null;

	/** @var Promise[] */
	private static $activePromises = [];
	/** @var int */
	private static $nextId = 1;

	/**
	 * Promise constructor.
	 *
	 * Expected resolver function signature:
	 * ```
	 * function(callable $resolve, callable $reject): void;
	 * ```
	 *
	 * Passed `$resolve` and `$reject` are callback functions that accept exactly up to one parameter of type mixed
	 *
	 * @param callable $resolver
	 * @throws \Exception
	 */
	public function __construct($resolver) {
		if( self::$nextId == 1 )
			register_shutdown_function([self::class, "awaitAllActivePromises"]);
		$this->id = self::$nextId++;
		$this->generator = $resolver(function($value = null) {
			$this->value = $value;
			$this->state = self::STATE_FULFILLED;
			$this->runCallbacks();
		}, function($value = null) {
			$this->error = $value;
			$this->state = self::STATE_REJECTED;
			$this->runCallbacks();
		});
		if( $this->generator instanceof \Generator ) {
			try {
				$this->generator->rewind();
			}
			catch(\Exception $ex) {
				$this->state = self::STATE_REJECTED;
				$this->error = $ex;
				$this->runCallbacks();
			}
			if( $this->throwException ) {
				$ex = $this->throwException;
				$this->throwException = null;
				throw $ex;
			}
		}
		self::$activePromises[$this->id] = $this;
	}

	/**
	 * Creates a resolved promise.
	 *
	 * @param null $value
	 * @return Promise
	 */
	public static function resolve($value = null) {
		return new Promise(function($resolve, $reject) use(&$value) {
			$resolve($value);
		});
	}

	/**
	 * Creates a rejected promise.
	 *
	 * @param mixed $error
	 * @return Promise
	 */
	public static function reject($error = null) {
		return new Promise(function($resolve, $reject) use(&$error) {
			$reject($error);
		});
	}

	/**
	 *
	 */
	private function runCallbacks() {
		try {
			if( $this->state == self::STATE_EXECUTING )
				return;
			if( $this->chainedPromise ) {
				$this->chainedPromise->runCallbacks();
				return;
			}
			while( !empty($this->callStack) ) {
				$toCall = array_shift($this->callStack);
				if( $toCall[0] == 0 ) {
					if( $this->state != self::STATE_FULFILLED )
						continue;
					$value = call_user_func($toCall[1], $this->value);
					if( $value instanceof Promise )
						$this->chainedPromise = $value;
					else
						$this->value = $value;
				}
				else {
					if( $this->state != self::STATE_REJECTED )
						continue;
					try {
						$value = call_user_func($toCall[1], $this->error);
						if( $value instanceof Promise )
							$this->chainedPromise = $value;
						else
							$this->value = $value;
					}
					finally {
						$this->state = self::STATE_FULFILLED;
						$this->error = null;
					}
				}
				if( $this->chainedPromise ) {
					$this->chainedPromise->callStack = $this->callStack;
					$this->chainedPromise->finallyCallback = $this->finallyCallback;
					$this->callStack = [];
					$this->finallyCallback = null;
					$this->chainedPromise->runCallbacks();
					return;
				}
			}
		}
		catch(\Exception $ex) {
			$this->throwException = $ex;
		}
	}

	/**
	 * Adds resolution and optionally rejection catching callbacks into execution chain.
	 *
	 * @param callable $resolveCallback
	 * @param callable|null $rejectCallback
	 * @return $this
	 */
	public function then($resolveCallback, $rejectCallback = null) {
		if( $this->chainedPromise )
			return $this->chainedPromise->then($resolveCallback, $rejectCallback);
		$this->callStack[] = [0, $resolveCallback];
		if( $rejectCallback !== null )
			$this->callStack[] = [1, $rejectCallback];
		$this->runCallbacks();
		return $this;
	}

	/**
	 * Adds rejection catching callback into execution chain.
	 *
	 * @param callable $rejectCallback
	 * @return $this
	 */
	public function _catch($rejectCallback) {
		if( $this->chainedPromise )
			return $this->chainedPromise->_catch($rejectCallback);
		$this->callStack[] = [1, $rejectCallback];
		$this->runCallbacks();
		return $this;
	}

	/**
	 * Assigns finally callback.
	 *
	 * @param $finallyCallback
	 * @return $this
	 */
	public function _finally($finallyCallback) {
		if( $this->chainedPromise )
			return $this->chainedPromise->_finally($finallyCallback);
		$this->finallyCallback = $finallyCallback;
		$this->runCallbacks();
		return $this;
	}

	/**
	 * Does a check on promise.
	 *
	 * @return bool Returns TRUE if promise was resolved or rejected.
	 * @throws \Exception
	 */
	public function awaitLoopTick() {
		if( $this->state == self::STATE_EXECUTING && $this->generator instanceof \Generator && $this->generator->valid() ) {
			try {
				$this->generator->next();
			}
			catch(\Exception $ex) {
				$this->state = self::STATE_REJECTED;
				$this->error = $ex;
				$this->runCallbacks();
			}
			if( $this->throwException ) {
				unset(self::$activePromises[$this->id]);
				$ex = $this->throwException;
				$this->throwException = null;
				throw $ex;
			}
			if( $this->state == self::STATE_EXECUTING )
				return false;
		}
		unset(self::$activePromises[$this->id]);
		if( $this->error !== null )
			throw new UncaughtPromiseErrorException($this->error);
		return $this->chainedPromise ? $this->chainedPromise->awaitLoopTick() : true;
	}

	/**
	 * Waits (blocks) till promise finishes execution.
	 *
	 * If there is promise chaining then this method will block until last chained promise finishes execution.
	 *
	 * @return mixed Returns value returned from last executed resolve or reject handler.
	 * @throws \Exception
	 */
	public function await() {
		while( !$this->awaitLoopTick() )
			usleep(100);
		return $this->chainedPromise ? $this->chainedPromise->await() : $this->value;
	}

	/**
	 * @param Promise[] $promises
	 * @return Promise[]
	 */
	private static function doLoopCycle(&$promises) {
		$finishedPromises = [];
		foreach( $promises as $key => $promise ) {
			if( $promise->awaitLoopTick() )
				$finishedPromises[$key] = $promise;
		}
		return $finishedPromises;
	}

	/**
	 * Creates a promise that waits till all given promises are resolved or any of them rejected.
	 *
	 * @param Promise[] $promises
	 * @return Promise
	 */
	public static function all($promises) {
		return new Promise(function($resolve, $reject) use(&$promises) {
			$leftPromises = [];
			foreach( $promises as $k => $p )
				$leftPromises[$k] = $p;
			while( !empty($leftPromises) ) {
				try {
					foreach(self::doLoopCycle($leftPromises) as $k => $p ) {
						if( $p->getState() == self::STATE_REJECTED ) {
							$reject($p->getError());
							return;
						}
						unset($leftPromises[$k]);
					}
				}
				catch (UncaughtPromiseErrorException $ex) {
					$reject($ex->getMessage());
				}
				yield;
			}
			$values = [];
			foreach( $promises as $k => $p )
				$values[$k] = $p->getValue();
			$resolve($values);
		});
	}

	/**
	 * Waits till all currently active promises finish execution.
	 *
	 * @param bool $ignoreExceptions Set to TRUE to ignore all exceptions that happen during the waiting cycle.
	 * @param callable $loopCallback This callback will be called without any parameters before every loop (every 100ms). Waiting for promises to finish will be canceled if callback returns a value that evaluates to TRUE.
	 * @throws \Exception
	 */
	public static function awaitAllActivePromises($ignoreExceptions = false, $loopCallback = null) {
		while( !empty(self::$activePromises) ) {
			try {
				if( $loopCallback && is_callable($loopCallback) && call_user_func($loopCallback) )
					return;
				self::doLoopCycle(self::$activePromises);
			}
			catch(\Exception $ex) {
				if( !$ignoreExceptions )
					throw $ex;
			}
			if( !empty(self::$activePromises) )
				usleep(100);
		}
		self::$activePromises = [];
	}

	/**
	 * Get ID of the promise.
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Get state of the promise.
	 *
	 * @return int Returns state of the promise or, state of last promise in the chain if the promise is chained.
	 */
	public function getState() {
		return $this->chainedPromise ? $this->chainedPromise->getState() : $this->state;
	}

	/**
	 * Get value of the promise.
	 *
	 * @return int Returns value of the promise or, value of last promise in the chain if the promise is chained.
	 */
	public function getValue() {
		return $this->chainedPromise ? $this->chainedPromise->getValue() : $this->value;
	}

	/**
	 * Get error of the promise.
	 *
	 * @return int Returns error of the promise or, error of last promise in the chain if the promise is chained.
	 */
	public function getError() {
		return $this->chainedPromise ? $this->chainedPromise->getError() : $this->error;
	}
}