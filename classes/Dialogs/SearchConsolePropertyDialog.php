<?php

namespace tobimori\Seo\Dialogs;

use Kirby\Panel\Controller\DialogController;
use Kirby\Panel\Ui\Dialog\FormDialog;
use tobimori\Seo\GoogleSearchConsole;
use tobimori\Seo\Seo;

/**
 * Selects the Search Console property that the plugin reads its data from
 */
class SearchConsolePropertyDialog extends DialogController
{
	public function load(): FormDialog
	{
		/** @var class-string<GoogleSearchConsole> $gsc */
		$gsc = Seo::option('components.gsc');

		return new FormDialog(
			fields: [
				'property' => [
					'label' => $this->i18n('seo.sections.searchConsole.selectPropertyLabel'),
					'type' => 'select',
					'required' => true,
					'empty' => false,
					'options' => array_map(fn (array $property) => [
						'value' => $property['siteUrl'],
						'text' => str_starts_with($property['siteUrl'], 'sc-domain:')
							? substr($property['siteUrl'], 10) . ' (' . $this->i18n('seo.sections.searchConsole.scDomain') . ')'
							: $property['siteUrl']
					], $gsc::listProperties())
				]
			],
			submitButton: $this->i18n('select'),
			value: [
				'property' => $gsc::property() ?? $gsc::findMatchingProperty($this->kirby->site()->url())
			]
		);
	}

	public function submit(): array
	{
		/** @var class-string<GoogleSearchConsole> $gsc */
		$gsc = Seo::option('components.gsc');
		$gsc::setProperty((string)$this->request->get('property'));

		return [
			'event' => 'gsc.propertySelected'
		];
	}
}
