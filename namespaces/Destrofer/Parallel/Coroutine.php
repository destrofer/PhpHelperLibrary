<?php

namespace Destrofer\Parallel;

use Exception;
use Fiber;
use Throwable;

abstract class Coroutine {
	public readonly string $instanceId;

	/**
	 * @var float Current time synchronized between coroutines. Use this instead of individually calling {@see microtime()},
	 * since it will have same value for all coroutines executed on same loop.
	 */
	public float $time = 0;

	/**
	 * @var float Number of seconds passed since previous execution of coroutine code. This value is synchronized
	 * between coroutines, so all coroutines executed on same loop will have same value. When coroutine is paused time
	 * delta stops increasing, however if coroutine is in sleep mode (used {@see Coroutine::waitSeconds()}) - it does
	 * increase.
	 */
	public float $timeDelta = 0;

	/**
	 * @var float Number of seconds passed since previous coroutine pool loop, or 0 on first coroutine code execution
	 * (first loop).
	 */
	public float $lastLoopTimeDelta = 0;

	public function __construct() {
		$this->instanceId = $this->getNextInstanceId();
	}

	/**
	 * Used by coroutine constructor to get new ID for the coroutine task. If this method returns a constant
	 * value, then only one coroutine of this class may be running by any pool at the same time.
	 */
	public function getNextInstanceId(): string {
		static $nextIndex = 1;
		return get_class($this) . ":" . ($nextIndex++);
	}

	/**
	 * Main coroutine execution method. It may accept any number of additional arguments, that will be passed from
	 * {@see CoroutinePool::start()}.
	 */
	public abstract function run(): void;

	/**
	 * Called by {@see CoroutinePool} before {@see Coroutine::run()} starts executing.
	 */
	public function onStart(): void {
	}

	/**
	 * Called by {@see CoroutinePool} when coroutine has finally stopped by normal execution means
	 * ({@see Coroutine::run()} has finished executing).
	 */
	public function onEnd(): void {
	}

	/**
	 * Called by {@see CoroutinePool} when coroutine is finally stopped by force ({@see CoroutinePool::stopCoroutine()}
	 * is called).
	 */
	public function onCancel(): void {
	}

	/**
	 * Called by {@see CoroutinePool} when coroutine is finally stopped either by normal coroutine ending (after
	 * {@see Coroutine::onEnd()}) or by forced stop (after {@see Coroutine::onCancel()}).
	 */
	public function onDone(): void {
	}

	/**
	 * Called when this coroutine is paused by {@see CoroutinePool::pause()}.
	 */
	public function onPause(): void {
	}

	/**
	 * Called when this coroutine resumes its execution by {@see CoroutinePool::resume()} after being paused.
	 */
	public function onResume(): void {
	}

	/**
	 * Called by {@see CoroutinePool} when an exception occurs during this coroutine execution.
	 * In case of error {@see Coroutine::onCancel()}, {@see Coroutine::onEnd()} and {@see Coroutine::onDone()} will
	 * never be called.
	 * WARNING: Any errors that happen during this error handler are thrown back into "main thread".
	 * @param Throwable $error
	 */
	public function onError(Throwable $error): void {
	}

	/**
	 * Stops this coroutine execution. This method never returns.
	 * This method `exit()` the application if running in {main}.
	 * @throws Throwable
	 */
	public static function end(int $exitCode = 0): never {
		if (self::getCurrentPool()) {
			Fiber::suspend(new CoroutineControlEnd($exitCode));
			throw new Exception("Coroutine was stopped, but for some reason fiber was resumed. This should never happen!");
		}
		exit($exitCode);
	}

	/**
	 * Puts coroutine into suspended mode until next pool loop.
	 * This method does nothing, if running in {main}.
	 * @throws Throwable
	 */
	public static function waitNextFrame(): void {
		if (self::getCurrentPool())
			Fiber::suspend();
		else
			CoroutinePool::runGlobalPool(0); // Coroutine execution loop is ran at least once, no matter what maxExecutionTime is.
	}

	/**
	 * Puts coroutine into suspended mode until specified number of seconds passes.
	 * This method uses `usleep()`, if running in {main}.
	 * @param float $seconds
	 * @throws Throwable
	 */
	public static function waitSeconds(float $seconds): void {
		if (self::getCurrentPool())
			Fiber::suspend(new CoroutineControlSleep($seconds));
		else {
			$seconds -= CoroutinePool::runGlobalPool($seconds);
			if ($seconds > 0)
				usleep((int)($seconds * 1000000));
		}
	}

	/**
	 * Puts coroutine into suspended mode until at least one of specified coroutines stops execution (no longer running by any coroutine pools).
	 * This method will return instantly without suspending if provided coroutine list is empty.
	 * @param Coroutine[] $coroutines
	 * @param float|null $maxWaitTime If specified, then this method will stop waiting if more than max time was spent.
	 * This doesn't affect other coroutines' execution. It only gets this coroutine back from suspended state after
	 * specified time has passed.
	 * @return Coroutine[] List of coroutines that ended before returning from this method.
	 * @throws Throwable
	 */
	public static function waitAnyCoroutines(array $coroutines, ?float $maxWaitTime = null): array {
		if (empty($coroutines))
			return [];
		if (self::getCurrentPool())
			return Fiber::suspend(new CoroutineControlWaitAny($coroutines, $maxWaitTime));
		else
			return CoroutinePool::getGlobalPool()->waitAnyCoroutines($coroutines, $maxWaitTime);
	}

	/**
	 * Puts coroutine into suspended mode until all specified coroutines stop execution (no longer running by any coroutine pools).
	 * This method will return instantly without suspending if provided coroutine list is empty.
	 * @param Coroutine[] $coroutines
	 * @param float|null $maxWaitTime If specified, then this method will stop waiting if more than max time was spent.
	 * This doesn't affect other coroutines' execution. It only gets this coroutine back from suspended state after
	 * specified time has passed.
	 * @return Coroutine[] List of coroutines that ended before returning from this method.
	 * @throws Throwable
	 */
	public static function waitAllCoroutines(array $coroutines, ?float $maxWaitTime = null): array {
		if (empty($coroutines))
			return [];
		if (self::getCurrentPool())
			return Fiber::suspend(new CoroutineControlWaitAll($coroutines, $maxWaitTime));
		else
			return CoroutinePool::getGlobalPool()->waitAllCoroutines($coroutines, $maxWaitTime);
	}

	/**
	 * Start another coroutine that will be registered as a child of this coroutine.
	 *
	 * If running in {main}, then coroutine is added to the global coroutine pool as detached instead of child.
	 *
	 * @param Coroutine|callable $coroutine
	 * @param mixed ...$arguments
	 * @return Coroutine
	 * @throws Throwable
	 * @see CoroutinePool::startChildCoroutine()
	 * @see CoroutinePool::start()
	 * @see CoroutinePool::getGlobalPool()
	 * @see CoroutinePool::runGlobalPool()
	 */
	public static function startChildCoroutine(Coroutine|callable $coroutine, mixed ...$arguments): Coroutine {
		$pool = self::getCurrentPool() ?? CoroutinePool::getGlobalPool();
		$current = self::getCurrentCoroutine();
		return $current ? $pool->startChildCoroutine($current, $coroutine, ...$arguments) : $pool->start($coroutine, ...$arguments);
	}

	/**
	 * Start another coroutine that will not be registered as a child of this coroutine.
	 *
	 * If running in {main}, then coroutine is added to the global coroutine pool.
	 *
	 * @param Coroutine|callable $coroutine
	 * @param mixed ...$arguments
	 * @return Coroutine
	 * @throws Throwable
	 * @see CoroutinePool::start()
	 * @see CoroutinePool::getGlobalPool()
	 * @see CoroutinePool::runGlobalPool()
	 */
	public static function start(Coroutine|callable $coroutine, mixed ...$arguments): Coroutine {
		$pool = self::getCurrentPool() ?? CoroutinePool::getGlobalPool();
		return $pool->start($coroutine, ...$arguments);
	}

	/**
	 * @return bool Returns true if running inside a coroutine (fiber, started by CoroutinePool).
	 */
	public static function isRunningInCoroutine(): bool {
		return !!self::getCurrentExecutionTask();
	}

	private static function getCurrentExecutionTask(): ?CoroutineExecutionTask {
		return CoroutineExecutionTask::getTaskFromFiber(Fiber::getCurrent());
	}

	/**
	 * @return Coroutine|null Will return null if running in {main}, or running in fiber without using coroutines.
	 */
	public static function getParentCoroutine(): ?Coroutine {
		return self::getCurrentExecutionTask()?->parent;
	}

	/**
	 * @return Coroutine[] Will return an empty array if running in {main}, or running in fiber without using coroutines.
	 */
	public static function getChildCoroutines(): array {
		return self::getCurrentExecutionTask()?->children ?? [];
	}

	/**
	 * @return Coroutine|null Will return null if running in {main}, or running in fiber without using coroutines.
	 */
	public static function getCurrentCoroutine(): ?Coroutine {
		return self::getCurrentExecutionTask()?->coroutine;
	}

	/**
	 * @return Coroutine|null Will return null if running in {main}, or running in fiber without using coroutines.
	 */
	public static function getCurrentPool(): ?CoroutinePool {
		return self::getCurrentExecutionTask()?->pool;
	}
}