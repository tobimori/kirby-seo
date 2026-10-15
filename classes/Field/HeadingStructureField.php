<?php

namespace tobimori\Seo\Field;

use DOMDocument;
use Kirby\Cms\Page;
use Kirby\Form\Field\DisplayField;

/**
 * Shows the outline of the headings in the rendered page
 */
class HeadingStructureField extends DisplayField
{
	/**
	 * The headings, loaded by the Panel after each save, as rendering the page is slow
	 */
	public function api(): array
	{
		$field = $this;

		return [
			[
				'pattern' => '',
				'method' => 'GET',
				'action' => fn () => ['data' => $field->headings()]
			]
		];
	}

	/**
	 * @return array<array{level: int, text: string}>
	 */
	public function headings(): array
	{
		$model = $this->model();
		if (!$model instanceof Page) {
			// only works for pages (not site, files, etc.)
			return [];
		}

		// clone the model with the content from the changes version
		$changesVersion = $model->version('changes');
		if ($changesVersion->exists('current')) {
			$model = $model->clone(['content' => $changesVersion->content()->toArray()]);
		}

		// the encoding prefix makes libxml read the HTML as UTF-8
		$dom = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $model->render(), LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$data = [];

		// all elements in document order, to keep the order of the headings
		foreach ($dom->getElementsByTagName('*') as $element) {
			if (!in_array($element->tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
				continue;
			}

			$data[] = [
				'level' => (int)$element->tagName[1],
				'text' => $element->textContent
			];
		}

		return $data;
	}
}
