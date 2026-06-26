<?php

namespace Destrofer\Parallel;

/**
 * Tells coroutine pool to put task into sleep mode until at least one of specified tasks is finished. All non-existing
 * task IDs are considered same as already finished.
 */
class CoroutineControlWaitAny extends CoroutineControl {
	/** @var Coroutine[]  */
	public array $coroutines = [];
	public float $waitStartTime;

	/**
	 * @param Coroutine[] $coroutines List of coroutines to wait for.
	 */
	public function __construct(array $coroutines, public ?float $maxWaitTime = null) {
		parent::__construct();
		$this->waitStartTime = microtime(true);
		foreach ($coroutines as $coroutine)
			$this->coroutines[$coroutine->instanceId] = $coroutine;
	}
}