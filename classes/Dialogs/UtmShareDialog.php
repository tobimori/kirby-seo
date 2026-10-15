<?php

namespace tobimori\Seo\Dialogs;

use Kirby\Cms\Find;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Exception\NotFoundException;
use Kirby\Panel\Controller\DialogController;
use Kirby\Panel\Ui\Dialog;

/**
 * Builds a link with UTM parameters to a page, or to the home page for the site
 */
class UtmShareDialog extends DialogController
{
	public function __construct(
		protected Page $page
	) {
		parent::__construct();
	}

	public static function factory(string $path): self
	{
		$model = Find::parent($path);

		if ($model instanceof Site) {
			$model = $model->homePage();
		}

		if ($model instanceof Page === false) {
			throw new NotFoundException(key: 'page.undefined');
		}

		return new self($model);
	}

	public function load(): Dialog
	{
		return new Dialog(
			component: 'k-seo-utm-share-dialog',
			pageUrl: $this->page->url()
		);
	}
}
