<?php

namespace tobimori\Seo;

use Kirby\Cms\Page;
use Kirby\Panel\Ui\Buttons\ViewButton;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;

class RobotsViewButton extends ViewButton
{
	public function __construct(Page $model)
	{
		if ($model->kirby()->option('tobimori.seo.robots.active', true) !== true) {
			parent::__construct(
				model: $model,
				disabled: true,
				style: 'display: none'
			);
			return;
		}

		$robots = $model->robots();

		$theme = 'positive-icon';
		$icon = 'robots';
		$text = I18n::translate('seo.fields.robots.indicator.index');

		if (Str::contains($robots, 'no') && !Str::contains($robots, 'noindex')) {
			$theme = 'notice-icon';
			$icon = 'robots-off';
			$text = I18n::translate('seo.fields.robots.indicator.any');
		}

		if (Str::contains($robots, 'noindex')) {
			$theme = 'negative-icon';
			$icon = 'robots-off';
			$text = I18n::translate('seo.fields.robots.indicator.noindex');
		}

		parent::__construct(
			model: $model,
			icon: $icon,
			text: $text,
			theme: $theme,
			link: $model->panel()->url() . '?tab=seo',
			responsive: true
		);
	}
}
