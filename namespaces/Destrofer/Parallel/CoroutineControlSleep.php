<?php

namespace Destrofer\Parallel;

class CoroutineControlSleep extends CoroutineControl {
	public function __construct(public float $sleepTime) {
		parent::__construct();
	}
}