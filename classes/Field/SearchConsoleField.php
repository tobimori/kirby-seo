<?php

namespace tobimori\Seo\Field;

use Exception;
use Kirby\Form\Field\DisplayField;
use tobimori\Seo\GoogleSearchConsole;
use tobimori\Seo\Seo;

/**
 * Shows the top Google Search Console queries of the page (or of the site)
 */
class SearchConsoleField extends DisplayField
{
	/**
	 * The connection status & the top queries, loaded by the Panel
	 * as requests to Google are slow
	 */
	public function api(): array
	{
		$field = $this;

		return [
			[
				'pattern' => '',
				'method' => 'GET',
				'action' => fn () => [
					'status' => $field->status(),
					'data' => $field->queries()
				]
			]
		];
	}

	public function status(): string
	{
		/** @var class-string<GoogleSearchConsole> $gsc */
		$gsc = Seo::option('components.gsc');

		return match (true) {
			!$gsc::hasCredentials() => 'NO_CREDENTIALS',
			!$gsc::isConnected() => 'NOT_CONNECTED',
			!$gsc::property() => 'SELECT_PROPERTY',
			default => 'CONNECTED'
		};
	}

	public function queries(): array
	{
		/** @var class-string<GoogleSearchConsole> $gsc */
		$gsc = Seo::option('components.gsc');
		if (!$gsc::ready()) {
			return [];
		}

		$request = $this->kirby()->request();
		$metric = (string)$request->get('metric', 'clicks');
		$limit = (int)$request->get('limit', 10);
		$asc = in_array($metric, ['position', 'query'], true);

		try {
			return $gsc::queryForModel($this->model(), $metric, $limit, $asc);
		} catch (Exception) {
			return [];
		}
	}
}
