<?php

namespace tobimori\Seo\Buttons;

use Kirby\Cms\Site;
use Kirby\Panel\Ui\Buttons\ViewButton;

/**
 * Uses the site's registered language button, including plugin replacements.
 * Registering as `seo.overview.languages` overrides only the overview button
 */
class OverviewLanguagesButton extends ViewButton
{
	public function __construct(Site $site)
	{
		parent::__construct(model: $site);
	}

	public function render(): array|null
	{
		$button = ViewButton::factory(name: 'languages', model: $this->model)?->render();

		// junohamburg/kirby-language-selector opens translation dialogs relative to the current view
		// These routes (e.g. `seo/translation/{code}`) do not exist in the overview
		if ($button && $button['component'] === 'k-language-selector') {
			unset($button['props']['options']);
		}

		return $button;
	}
}
