<?php

namespace tobimori\Seo\Buttons;

use Kirby\Cms\Site;
use Kirby\Panel\Ui\Buttons\ViewButton;

/**
 * The `languages` button of the SEO overview (registered as `seo.overview.languages`,
 * which Kirby prefers over `languages` in this view): renders whichever languages button
 * is registered for the site, i.e. Kirby's languages dropdown or a replacement from a plugin,
 * minus actions that only work in the site's own view
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

		// the language selector plugin (junohamburg/kirby-language-selector) offers to delete
		// the model's translations via dialogs relative to the current view (`seo/translation/{code}`),
		// which don't exist here. Deleting the site's translations doesn't belong in the overview anyway
		if ($button && $button['component'] === 'k-language-selector') {
			unset($button['props']['options']);
		}

		return $button;
	}
}
