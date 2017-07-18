<?php
/**
 * Copyright 2017 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Platform\Exceptions;

class SSHExecException extends SSHException {
	const REASON_EXEC_FAILED = 0;
	const REASON_BAD_EXIT_STATUS_FORMAT = 1;
}