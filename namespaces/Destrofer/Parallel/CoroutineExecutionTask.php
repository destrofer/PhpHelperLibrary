<?php

namespace Destrofer\Parallel;

use Fiber;
use WeakMap;

class CoroutineExecutionTask {
	public bool $paused = false;
	public float $sleepUntil = 0;
	public CoroutineControlWaitAny|CoroutineControlWaitAll|null $waitFor = null;
	public readonly Fiber $fiber;
	public ?Coroutine $parent = null;
	/** @var Coroutine[] */
	public array $children = [];

	private static ?WeakMap $fiberToTask = null;

	public function __construct(
		public readonly CoroutinePool $pool,
		public readonly Coroutine $coroutine,
		public array $arguments
	) {
		/** @uses Coroutine::run() */
		$this->fiber = new Fiber([$this->coroutine, "run"]);
		self::$fiberToTask ??= new WeakMap();
		self::$fiberToTask[$this->fiber] = $this;
	}

	public static function getTaskFromFiber(?Fiber $fiber): ?self {
		return $fiber ? (self::$fiberToTask[$fiber] ?? null) : null;
	}
}
