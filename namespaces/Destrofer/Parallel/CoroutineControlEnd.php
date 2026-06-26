<?php

namespace Destrofer\Parallel;

class CoroutineControlEnd extends CoroutineControl {
	public function __construct(public int $exitCode = 0) {
		parent::__construct();
	}
}
