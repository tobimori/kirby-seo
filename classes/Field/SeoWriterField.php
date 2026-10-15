<?php

namespace tobimori\Seo\Field;

use Closure;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Content\VersionId;
use Kirby\Exception\NotFoundException;
use Kirby\Form\Field\WriterField;
use Kirby\Reflection\Attributes\Prop;
use Kirby\Toolkit\Str;
use tobimori\Seo\Ai;
use tobimori\Seo\Seo;

/**
 * Single-line writer with template variables and AI generation
 */
class SeoWriterField extends WriterField
{
	/**
	 * AI task that generates the value (e.g. `title` or `description`), `false` disables AI
	 */
	#[Prop]
	protected string|bool $ai = false;

	// the counter would be at the same place as the AI buttons
	protected bool $counter = false;
	protected bool $inline = true;
	protected array|bool|null $marks = false;
	protected array|bool|null $nodes = false;

	public function ai(): string|bool
	{
		/** @var class-string<Ai> $component */
		$component = Seo::option('components.ai');
		return $component::enabled() && $component::permitted() ? $this->ai : false;
	}

	public function placeholder(): string|null
	{
		if ($this->placeholder === null) {
			return null;
		}

		$value = $this->stringTemplateI18n($this->placeholder, safe: false);

		if (Str::contains($value, 'data-seo-template-variable')) {
			$value = Str::unhtml($value);
		}

		return str_replace(
			['{{ title }}', '{{ site.title }}'],
			[$this->i18n('seo.writerNodes.template.title'), $this->i18n('seo.writerNodes.template.siteTitle')],
			$value
		);
	}

	public function api(): array
	{
		$field = $this;

		return [
			[
				'pattern' => 'ai/stream',
				'method' => 'POST',
				'action' => function () use ($field) {
					$kirby = $field->kirby();
					/** @var class-string<Ai> $component */
					$component = Seo::option('components.ai');
					$task = $field->ai();

					if ($error = $component::denied()) {
						return $error;
					}

					// for site, use homepage
					$model = $field->model();
					$page = $model instanceof Site ? $model->homePage() : $model;

					// the field has no AI task, or the site has no home page
					if (!is_string($task) || !$page instanceof Page) {
						throw new NotFoundException();
					}

					$data = $kirby->request()->body()->data();
					$lang = $kirby->api()->language();
					$kirby->site()->visit($page, $lang);
					if ($lang) {
						$kirby->setCurrentLanguage($lang);
					}

					// inject data in snippets / rendering process
					$kirby->data = [
						'page' => $page,
						'site' => $kirby->site(),
						'kirby' => $kirby
					];

					// use the unsaved Panel changes (if any) for rendering & reading content,
					// falls back to the latest version if no changes exist
					$component::sendStream(fn (Closure $send) => VersionId::render('changes', function () use ($component, $data, $task, $send) {
						foreach ($component::streamTask($task, [
							'instructions' => $data['instructions'] ?? null,
							'edit' => $data['edit'] ?? null
						]) as $chunk) {
							$send($chunk);
						}
					}));
				}
			]
		];
	}
}
