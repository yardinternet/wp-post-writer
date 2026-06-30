<?php

declare(strict_types=1);

use PhpCsFixer\Finder;
use Yard\PhpCsFixerRules\Config;

$finder = Finder::create()
	->in([__DIR__ . '/src', __DIR__ . '/stubs'])
	->name('*.php')
	->ignoreDotFiles(true)
	->ignoreVCS(true)
	->append(['.php-cs-fixer.php']);

return Config::create($finder);
