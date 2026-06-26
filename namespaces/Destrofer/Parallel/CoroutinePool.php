<?php

namespace Destrofer\Parallel;

use Exception;
use Fiber;
use Throwable;

class CoroutinePool {
	public const DEFAULT_INTERVAL = 0.05;

	/** @var CoroutineExecutionTask[] */
	private array $tasks = [];
	/** @var CoroutineExecutionTask[] */
	private static array $globalRunningTasks = [];

	private float $lastTime = 0;

	/** @var callable|null */
	private mixed $coroutineErrorCallback = null;

	private static ?self $globalPool = null;

	private float $interval = self::DEFAULT_INTERVAL;

	/**
	 * Starts up a global coroutine pool if it's not yet started, and returns it's instance.
	 * It is recommended to use {@see CoroutinePool::runGlobalPool()} at the end of application that uses
	 * coroutines, so that all coroutines, that were added to the global pool, would finish their execution.
	 * @return self
	 */
	public static function getGlobalPool(): self {
		self::$globalPool ??= new self();
		return self::$globalPool;
	}

	/**
	 * Runs global pool if it was started.
	 * @see CoroutinePool::run()
	 * @param float|null $maxExecutionTime
	 * @return float
	 * @throws Throwable
	 */
	public static function runGlobalPool(?float $maxExecutionTime = null): float {
		if (!self::$globalPool)
			return 0;
		return self::$globalPool->run($maxExecutionTime);
	}

	/**
	 * Sets minimum interval in seconds between running all pooled coroutines.
	 * Default is 0.05 seconds (20 times per second).
	 *
	 * @param float $interval
	 * @return $this
	 */
	public function setInterval(float $interval): self {
		$this->interval = max($interval, 0);
		return $this;
	}

	/**
	 * Adds coroutine to the execution pool. Added coroutines are not executed immediately - only on the next pool loop.
	 * @param Coroutine|callable $coroutine
	 * @param mixed ...$arguments
	 * @return Coroutine Will return a new instance of {@see AnonymousCoroutine} in case if a closure or callable is provided, or same coroutine instance that was passed to this method.
	 * @throws Exception
	 */
	public function start(Coroutine|callable $coroutine, mixed ...$arguments): Coroutine {
		if (!($coroutine instanceof Coroutine))
			$coroutine = new AnonymousCoroutine($coroutine);
		if (isset(self::$globalRunningTasks[$coroutine->instanceId]))
			throw new Exception("Coroutine '{$coroutine->instanceId}' is already running");
		$task = new CoroutineExecutionTask($this, $coroutine, $arguments);
		self::$globalRunningTasks[$coroutine->instanceId] = $task;
		$this->tasks[$coroutine->instanceId] = $task;
		return $coroutine;
	}

	/**
	 * Adds coroutine to the execution pool as a child of another coroutine. Added coroutines are not executed immediately - only on the next pool loop.
	 * All remaining working children coroutines are force-stopped when parent coroutine has ended.
	 * @param Coroutine $parentCoroutine
	 * @param Coroutine|callable $coroutine
	 * @param mixed ...$arguments
	 * @return Coroutine Will return a new instance of {@see AnonymousCoroutine} in case if a callable is provided, or same coroutine instance that was passed to this method.
	 * @throws Exception
	 */
	public function startChildCoroutine(Coroutine $parentCoroutine, Coroutine|callable $coroutine, mixed ...$arguments): Coroutine {
		if (!isset(self::$globalRunningTasks[$parentCoroutine->instanceId]))
			throw new Exception("Coroutine '{$parentCoroutine->instanceId}' is not running");
		$started = $this->start($coroutine, ...$arguments);
		$this->tasks[$started->instanceId]->parent = $parentCoroutine;
		self::$globalRunningTasks[$parentCoroutine->instanceId]->children[$started->instanceId] = $started;
		return $started;
	}

	/**
	 * @return bool TRUE in case there are any coroutines in the pool after executing the loop, or FALSE otherwise.
	 * @throws Throwable
	 */
	public function doLoop(): bool {
		$time = microtime(true);
		$delta = $this->lastTime ? ($time - $this->lastTime) : 0;
		$this->lastTime = $time;

		foreach ($this->tasks as $id => $task) {
			if ($task->paused)
				continue;

			// Handle coroutines in sleep mode.
			$task->coroutine->timeDelta += $delta;
			if ($task->sleepUntil > $time)
				continue;

			// Handle situations when coroutines are waiting for other coroutines to finish.
			$resumeArgs = null;
			if ($task->waitFor) {
				$doneWaiting = true;
				if ($task->waitFor instanceof CoroutineControlWaitAny) {
					$diff = array_diff(array_keys($task->waitFor->coroutines), array_keys(self::$globalRunningTasks));
					$doneWaiting = ($task->waitFor->maxWaitTime !== null && $time - $task->waitFor->waitStartTime >= $task->waitFor->maxWaitTime) || !empty($diff);
					if ($doneWaiting) {
						$endedCoroutines = [];
						foreach ($diff as $endedId)
							$endedCoroutines[] = $task->waitFor->coroutines[$endedId];
						$resumeArgs = [$endedCoroutines];
					}
				}
				else if ($task->waitFor instanceof CoroutineControlWaitAll) {
					if ($task->waitFor->maxWaitTime !== null && $time - $task->waitFor->waitStartTime >= $task->waitFor->maxWaitTime) {
						$diff = array_diff(array_keys($task->waitFor->coroutines), array_keys(self::$globalRunningTasks));
						$endedCoroutines = [];
						foreach ($diff as $endedId)
							$endedCoroutines[] = $task->waitFor->coroutines[$endedId];
						$resumeArgs = [$endedCoroutines];
					}
					else {
						$doneWaiting = empty(array_intersect(array_keys($task->waitFor->coroutines), array_keys(self::$globalRunningTasks)));
						if ($doneWaiting)
							$resumeArgs = [array_values($task->waitFor->coroutines)];
					}
				}
				if ($doneWaiting)
					$task->waitFor = null;
				else
					continue;
			}

			// Execute coroutine code
			$task->coroutine->time = $time;
			if (!$task->fiber->isStarted()) {
				$task->coroutine->lastLoopTimeDelta = 0;
				$task->coroutine->timeDelta = 0;
				try {
					$task->coroutine->onStart();
					$ctl = $task->fiber->start(...$task->arguments);
				}
				catch (Throwable $throwable) {
					$this->onError($task->coroutine, $throwable); // This also removes coroutine from pool.
					continue;
				}
			}
			else {
				$task->coroutine->lastLoopTimeDelta = $delta;
				try {
					$ctl = ($resumeArgs === null) ? $task->fiber->resume() : $task->fiber->resume(...$resumeArgs);
				}
				catch (Throwable $throwable) {
					$this->onError($task->coroutine, $throwable); // This also removes coroutine from pool.
					continue;
				}
			}

			if (!isset($this->tasks[$id])) // This should never happen.
				continue;

			// Handle terminated fibers and returned execution control commands.
			$ended = $ctl instanceof CoroutineControlEnd || $task->fiber->isTerminated();
			if ($ended) {
				$currentStopped = $this->unregisterTask($task);
				try {
					$task->coroutine->onEnd();
					$task->coroutine->onDone();
				}
				catch (Throwable $throwable) {
					$this->onError($task->coroutine, $throwable);
				}
				if ($currentStopped)
					Coroutine::end(($ctl instanceof CoroutineControlEnd) ? $ctl->exitCode : 0); // In case if this pool is being executed inside a coroutine that got stopped.
				continue;
			}
			if ($ctl instanceof CoroutineControlSleep) {
				$task->sleepUntil = $time + $ctl->sleepTime;
			}
			else if ($ctl instanceof CoroutineControlWaitAny || $ctl instanceof CoroutineControlWaitAll) {
				$task->waitFor = $ctl;
			}
			$task->coroutine->timeDelta = 0;
		}

		return !empty($this->tasks);
	}

	/**
	 * @param float|null $maxExecutionTime If specified, then this method will stop executing coroutines if more than
	 * max time was spent. This doesn't stop deadlocked or long waiting coroutines. It just breaks the loop after all
	 * coroutines had their chance to execute some of their code.
	 * @return float Returns time that was spent on executing all coroutines.
	 * @throws Throwable
	 */
	public function run(?float $maxExecutionTime = null): float {
		$runStartTime = microtime(true);
		while(true) {
			$loopStartTime = microtime(true);
			if(!$this->doLoop())
				break;
			$loopEndTime = microtime(true);
			if ($maxExecutionTime !== null && $loopEndTime - $runStartTime >= $maxExecutionTime)
				break;
			$leftTime = $this->interval + $loopStartTime - $loopEndTime;
			if ($leftTime > 0)
				usleep((int)($leftTime * 1000000));
		}
		return microtime(true) - $runStartTime;
	}

	/**
	 * Runs this coroutine pool loops until any coroutine from the provided list finishes execution. Any coroutines that
	 * are not currently running, are considered finished. This may end up running indefinitely, if waiting for a
	 * coroutine that belongs to a pool, that never gets running.
	 *
	 * @param Coroutine[] $coroutines
	 * @param float|null $maxWaitTime If specified, then this method will stop executing coroutines if more than
	 * max time was spent. This doesn't stop deadlocked or long waiting coroutines. It just breaks the loop after all
	 * coroutines had their chance to execute some of their code.
	 * @return Coroutine[] List of coroutines that ended before returning from this method.
	 * @throws Throwable
	 * @see CoroutinePool::run()
	 */
	public function waitAnyCoroutines(array $coroutines, ?float $maxWaitTime = null): array {
		if (empty($coroutines))
			return [];
		$runStartTime = microtime(true);
		while(true) {
			$loopStartTime = microtime(true);
			if(!$this->doLoop())
				break;
			$ended = array_filter($coroutines, function ($coroutine) {
				return !isset(self::$globalRunningTasks[$coroutine->instanceId]);
			});
			if (!empty($ended))
				return $ended;
			$loopEndTime = microtime(true);
			if ($maxWaitTime !== null && $loopEndTime - $runStartTime >= $maxWaitTime)
				return [];
			$leftTime = $this->interval + $loopStartTime - $loopEndTime;
			if ($leftTime > 0)
				usleep((int)($leftTime * 1000000));
		}
		return $coroutines;
	}

	/**
	 * Waits for all coroutine from the provided list to finish execution. Any coroutines that are not currently
	 * running, are considered finished. This may end up running indefinitely, if waiting for a coroutine that belongs
	 * to a pool, that never gets running.
	 *
	 * @param Coroutine[] $coroutines
	 * @param float|null $maxWaitTime If specified, then this method will stop executing coroutines if more than
	 * max time was spent. This doesn't stop deadlocked or long waiting coroutines. It just breaks the loop after all
	 * coroutines had their chance to execute some of their code.
	 * @return Coroutine[] List of coroutines that ended before returning from this method.
	 * @throws Throwable
	 */
	public function waitAllCoroutines(array $coroutines, ?float $maxWaitTime = null): array {
		if (empty($coroutines))
			return [];
		$runStartTime = microtime(true);
		while(true) {
			$loopStartTime = microtime(true);
			if(!$this->doLoop())
				break;
			$all = true;
			$ended = [];
			foreach ($coroutines as $k => $coroutine) {
				if (!isset(self::$globalRunningTasks[$coroutine->instanceId]))
					$ended[$k] = $coroutine;
				else
					$all = false;
			}
			if ($all)
				return $ended;
			$loopEndTime = microtime(true);
			if ($maxWaitTime !== null && $loopEndTime - $runStartTime >= $maxWaitTime)
				return $ended;
			$leftTime = $this->interval + $loopStartTime - $loopEndTime;
			if ($leftTime > 0)
				usleep((int)($leftTime * 1000000));
		}
		return $coroutines;
	}

	/**
	 * @param CoroutineExecutionTask $task
	 * @return bool Returns TRUE if provided coroutine or one of its children is currently executing code.
	 * @throws Throwable
	 */
	private function unregisterTask(CoroutineExecutionTask $task): bool {
		$runningChildStopped = false;
		$taskId = $task->coroutine->instanceId;
		foreach (self::$globalRunningTasks[$taskId]->children as $childCoroutine) {
			$childTask = self::$globalRunningTasks[$childCoroutine->instanceId];
			$runningChildStopped = $childTask->pool->stopInternal($childCoroutine) || $runningChildStopped;
		}
		unset($this->tasks[$taskId], self::$globalRunningTasks[$taskId]);
		if ($task->parent) {
			unset(self::$globalRunningTasks[$task->parent->instanceId]->children[$taskId]);
			$task->parent = null;
		}
		return $runningChildStopped || $task->fiber === Fiber::getCurrent();
	}

	/**
	 * Stops coroutine execution. This method will never return if called from coroutine that is being stopped.
	 * @param Coroutine $coroutine
	 * @return bool Returns TRUE if stopped coroutine or one of its children is currently running fiber.
	 * @throws Throwable
	 */
	private function stopInternal(Coroutine $coroutine): bool {
		if (!isset($this->tasks[$coroutine->instanceId]))
			throw new Exception("Coroutine '{$coroutine->instanceId}' not found in the pool");
		$task = $this->tasks[$coroutine->instanceId];
		$currentlyRunning = $this->unregisterTask($task);
		try {
			$coroutine->onCancel();
			$coroutine->onDone();
		}
		catch (Throwable $throwable) {
			$this->onError($coroutine, $throwable);
		}
		return $currentlyRunning;
	}

	/**
	 * Stops coroutine execution. This method will never return if called by a coroutine that is running in this pool.
	 * @param Coroutine $coroutine
	 * @throws Throwable
	 */
	public function stop(Coroutine $coroutine): void {
		if ($this->stopInternal($coroutine))
			Coroutine::end();
	}

	/**
	 * Stops all coroutines running in this pool. This method will never return if called by a coroutine that is running in this pool.
	 * @throws Throwable
	 */
	public function stopAll(): void {
		$currentlyRunning = false;
		foreach ($this->tasks as $task)
			$currentlyRunning = $this->stopInternal($task->coroutine) || $currentlyRunning;
		if ($currentlyRunning)
			Coroutine::end();
	}

	/**
	 * Puts coroutine into paused state. If called from inside same coroutine that is being paused, then execution of
	 * that coroutine stops right away until something else continues it.
	 * @param Coroutine $coroutine
	 * @throws Throwable
	 */
	public function pause(Coroutine $coroutine): void {
		if (!isset($this->tasks[$coroutine->instanceId]))
			throw new Exception("Coroutine '{$coroutine->instanceId}' not found in the pool");
		$task = $this->tasks[$coroutine->instanceId];
		if (!$task->paused) {
			$task->paused = true;
			try {
				$coroutine->onPause();
			}
			catch (Throwable $throwable) {
				$this->onError($task->coroutine, $throwable);
				return;
			}
			if (Fiber::getCurrent() === $task->fiber)
				Coroutine::waitNextFrame();
		}
	}

	/**
	 * Resumes paused coroutine execution.
	 * @param Coroutine $coroutine
	 * @throws Throwable
	 */
	public function resume(Coroutine $coroutine): void {
		if (!isset($this->tasks[$coroutine->instanceId]))
			throw new Exception("Coroutine '{$coroutine->instanceId}' not found in the pool");
		$task = $this->tasks[$coroutine->instanceId];
		if ($task->paused) {
			$task->paused = false;
			try {
				$coroutine->onResume();
			}
			catch (Throwable $throwable) {
				$this->onError($coroutine, $throwable);
			}
		}
	}

	/**
	 * @return bool TRUE if no coroutines are running in this pool.
	 */
	public function isEmpty(): bool {
		return empty($this->tasks);
	}

	/**
	 * Returns pool that is currently running provided coroutine.
	 * @param Coroutine $coroutine
	 * @return CoroutinePool|null
	 */
	public static function getRunningCoroutinePool(Coroutine $coroutine): ?CoroutinePool {
		return self::$globalRunningTasks[$coroutine->instanceId]->pool ?? null;
	}

	/**
	 * @throws Throwable
	 */
	private function onError(Coroutine $coroutine, Throwable $error): void {
		try {
			$currentStopped = $this->unregisterTask($this->tasks[$coroutine->instanceId]);
		}
		catch (Throwable $throwable) {
			throw new Exception("There was an error while stopping all children coroutine of another coroutine that caused an error: {$throwable}", 0, $error);
		}
		try {
			$handled = $coroutine->onError($error) || ($this->coroutineErrorCallback && call_user_func($this->coroutineErrorCallback, $coroutine, $error));
		}
		catch (Throwable $throwable) {
			throw new Exception("Coroutine error handler has thrown an error by itself: {$throwable}", 0, $error);
		}
		if (!$handled)
			throw $error;
		if ($currentStopped)
			Coroutine::end(); // In case if this pool is being executed inside a coroutine that got stopped.
	}

	/**
	 * Sets function that gets called in case if any unhandled error occurs in any coroutine. Callback must return TRUE
	 * if error was handled, or FALSE otherwise. If {@see Coroutine::onError()} handles the error, then this function
	 * doesn't get called. If this function returns FALSE, then error thrown in coroutine will be rethrown back into the
	 * main thread.
	 *
	 * Callback function signature: function(Coroutine $coroutine, Throwable $error): bool;
	 */
	public function setCoroutineErrorCallback(?callable $callback): void {
		$this->coroutineErrorCallback = $callback;
	}

	public function getCoroutineCount(): int {
		return count($this->tasks);
	}
}
