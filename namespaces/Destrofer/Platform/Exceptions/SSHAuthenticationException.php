<?php
/**
 * Copyright 2017 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Platform\Exceptions;

class SSHAuthenticationException extends SSHException {
	const REASON_NOT_AUTHENTICATED = 0;
	const REASON_NO_PRIVATE_KEY_FILE = 1;
	const REASON_PRIVATE_KEY_FILE_NOT_READABLE = 2;
	const REASON_NO_PUBLIC_KEY_FILE = 3;
	const REASON_PUBLIC_KEY_FILE_NOT_READABLE = 4;
	const REASON_BAD_FINGERPRINT = 5;
	const REASON_AUTHENTICATION_FAILED = 6;
}