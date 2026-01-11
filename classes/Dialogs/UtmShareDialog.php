<?php

namespace tobimori\Seo\Dialogs;

use Kirby\Cms\App;
use Kirby\Cms\Find;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

class UtmShareDialog
{
	protected Page|Site $model;

	public function __construct(string $path)
	{
		$kirby = App::instance();

		if ($path === 'site') {
			$this->model = $kirby->site();
		} else {
			$id = preg_replace('/^pages\//', '', $path);
			$this->model = Find::page($id);
		}
	}

	public function load(): array
	{
		$url = $this->model instanceof Site
			? $this->model->homePage()->url()
			: $this->model->url();

		return [
			'component' => 'k-seo-utm-share-dialog',
			'props' => [
				'pageUrl' => $url
			]
		];
	}
}
