<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
	// The prefix configuration. If a non null value will be used, a random prefix will be generated.
	'prefix' => 'FuerteWpDep',

	// By default when running php-scoper add-prefix, it will prefix all relevant code found in the current working
	// directory. You can however define which files should be scoped by defining a collection of Finders in the
	// following configuration key.
	//
	// For more see: https://github.com/humbug/php-scoper#finders-and-paths
	'finders' => [
		Finder::create()
			->files()
			->ignoreVCS(true)
			->in(__DIR__ . '/vendor/'),
		Finder::create()->append([
			'composer.json',
			'composer.lock',
		]),
	],
];
