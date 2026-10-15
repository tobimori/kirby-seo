<?php

namespace tobimori\Seo\Field;

use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Form\Field\DisplayField;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use tobimori\Seo\Meta;

/**
 * Previews the meta & Open Graph tags of the page (or of the home page for the site)
 * as Google, Facebook & Slack show them
 */
class SeoPreviewField extends DisplayField
{
	/**
	 * The preview data, loaded by the Panel after each save
	 */
	public function api(): array
	{
		$field = $this;

		return [
			[
				'pattern' => '',
				'method' => 'GET',
				'action' => fn () => ['meta' => $field->meta()]
			]
		];
	}

	public function meta(): array|null
	{
		$model = $this->model();

		if (!$model instanceof Site && !$model instanceof Page) {
			return null;
		}

		// clone the model with the content from the changes version
		$changesVersion = $model->version('changes');
		if ($changesVersion->exists('current')) {
			$model = $model->clone(['content' => $changesVersion->content()->toArray()]);
		}

		// if it's a site, fall back to the home page for preview data
		$model = $model instanceof Site ? $model->homePage() : $model;
		if (!$model) {
			return null;
		}

		/** @var Meta $meta */
		$meta = $model->metadata();
		return [
			'page' => $model->slug(),
			'url' => $meta->canonicalUrl(),
			'pageTitle' => Str::unhtml($model->title()->toString()),
			'title' => Str::unhtml($meta->metaTitle()->toString()),
			'description' => Str::unhtml($meta->metaDescription()->toString()),
			'ogSiteName' => Str::unhtml($meta->ogSiteName()->toString()),
			'ogTitle' => Str::unhtml($meta->ogTitle()->toString()),
			'ogDescription' => Str::unhtml($meta->ogDescription()->toString()),
			'ogImage' => $meta->ogImage(),
			'cropOgImage' => $meta->cropOgImage()->toBool(),
			'panelUrl' => "{$model->panel()->url()}?tab=seo"
		];
	}

	public function options(): array
	{
		/** @var list<string> $previews */
		$previews = $this->kirby()->option('tobimori.seo.previews');

		return A::map($previews, fn (string $item) => [
			'value' => $item,
			'text' => $this->i18n("seo.sections.preview.{$item}")
		]);
	}

	public function props(): array
	{
		return [
			...parent::props(),
			'options' => $this->options()
		];
	}
}
