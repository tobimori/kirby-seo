<?php

use Kirby\CLI\CLI;

return [
	'description' => 'Hello world',
	'args' => [],
	'command' => static function (CLI $cli): void {
		$cli->success('Hello world! This command is a preparation for a future release.');
	}
];
