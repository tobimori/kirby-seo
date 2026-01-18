<?php

use Kirby\Cms\App;
use Kirby\Toolkit\Str;

return function (App $kirby) {
	$path = $kirby->request()->url()->toString();
	$isSite = Str::contains($path, '/site') && !Str::contains($path, '/pages/');

	if ($isSite) {
		return require __DIR__ . '/site.php';
	}

	return require __DIR__ . '/page.php';
};
