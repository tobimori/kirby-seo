<?php

namespace tobimori\Seo\Field;

use Kirby\Form\Field\DisplayField;
use tobimori\Seo\License;

/**
 * Shows a banner to activate the plugin while the license is not active
 */
class LicenseField extends DisplayField
{
	public function props(): array
	{
		return [
			...parent::props(),
			'local' => $this->kirby()->system()->isLocal(),
			'state' => License::current()->state()
		];
	}
}
