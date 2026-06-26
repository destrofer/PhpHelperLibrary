<?php

namespace Destrofer\Parallel;

use Exception;

class AnonymousCoroutine extends Coroutine {
	/**
	 * @param callable $func
	 * @throws Exception
	 */
	public function __construct(private mixed $func) {
		if (!is_callable($this->func))
			throw new Exception("Only callable functions are accepted");
		parent::__construct();
	}

	public function run(...$arguments): void {
		call_user_func_array($this->func, $arguments);
	}
}