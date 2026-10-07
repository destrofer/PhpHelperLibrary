# PhpHelperLibrary
A PHP 8.2 class library to make coders life easier.
All sources can be located at: https://github.com/destrofer/PhpHelperLibrary

== Installing ==

As of version 3 PhpHelperLibrary no longer has autoloader by itself. However, it
may be installed using [Composer](https://getcomposer.org/), which has its own
autoloader.

After installing composer you can add dependency of your project on this package
by executing following command:

```
php composer.phar require destrofer/helper-library:^v4.0.0
```

or by adding `"destrofer/helper-library": "^v4.0.0"` to `require` block in
`composer.json`.

== Tests ==

Test scripts under `tests/` exercise the components that are used in production. They
are part of the repository but not part of the package: `tests/` carries `export-ignore`
in `.gitattributes`, so the archives that GitHub and Packagist serve contain no tests at
all, and `composer archive` excludes them too through the `archive.exclude` list in
`composer.json`.

Run them from inside the `tests` directory, after installing the library's own
dependencies:

```
php composer.phar install
cd tests
php test-logger.php
```

`tests/test-data/` holds only run artefacts - log files, and the pages the downloader test
writes - which are ignored by the `.gitignore` inside it. That file also keeps the otherwise
empty directory in the repository. The socket-based `test-async-*` scripts additionally need
the `sockets` extension, because `Destrofer\Net` uses `socket_*` functions; run them where
it is available.

== License ==

Copyright 2016 Viacheslav Soroka

PhpHelperLibrary is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

PhpHelperLibrary is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with PhpHelperLibrary. If not, see <http://www.gnu.org/licenses/>.
