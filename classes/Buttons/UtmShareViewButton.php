<?php

namespace tobimori\Seo\Buttons;

use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Panel\Ui\Buttons\ViewButton;
use tobimori\Seo\Seo;

class UtmShareViewButton extends ViewButton
{
	public function __construct(Page|Site $model)
	{
		parent::__construct(
			model: $model,
			dialog: "seo/utm-share/{$model->panel()->path()}",
			icon: 'share',
			title: Seo::translate('seo.utmShare.button')
		);
	}
}
