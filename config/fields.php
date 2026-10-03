<?php

use Kirby\Cms\Page;
use Kirby\Content\VersionId;
use Kirby\Toolkit\Str;
use tobimori\Seo\Ai;
use tobimori\Seo\Field\AltTextField;
use tobimori\Seo\Seo;

return [
	'seo-writer' => [
		'extends' => 'writer',
		'computed' => [
			'placeholder' => function () {
				if ($this->placeholder === null) {
					return null;
				}

				$value = $this->model()->toString($this->placeholder);

				if (Str::contains($value, 'data-seo-template-variable')) {
					$value = Str::unhtml($value);
				}

				return str_replace(
					['{{ title }}', '{{ site.title }}'],
					[t('seo.writerNodes.template.title'), t('seo.writerNodes.template.siteTitle')],
					$value
				);
			}
		],
		'props' => [
			/**
			 * Enables/disables the character counter in the top right corner
			 */
			'ai' => function (string|bool $ai = false) {
				$component = Seo::option('components.ai');
				return $component::enabled() && $component::permitted() ? $ai : false;
			},

			// reset defaults
			'counter' => fn (bool $counter = false) => $counter, // we have to disable the counter because its at the same place as our ai button
			'inline' => fn (bool $inline = true) => $inline,
			'marks' => fn (array|bool|null $marks = false) => $marks,
			'nodes' => fn (array|bool|null $nodes = false) => $nodes,
		],
		'api' => fn () => [
			[
				'pattern' => 'ai/stream',
				'method' => 'POST',
				'action' => function () {
					$kirby = $this->kirby();
					$component = Seo::option('components.ai');

					if ($error = $component::denied()) {
						return $error;
					}

					$data = $kirby->request()->body()->data();
					$lang = $kirby->api()->language();

					// for site, use homepage
					$model = $this->field()->model();
					$page = $model instanceof Page ? $model : $model->homePage();
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
					$component::sendStream(fn (Closure $send) => VersionId::render('changes', function () use ($component, $data, $send) {
						foreach (
							$component::streamTask($this->field()->ai(), [
								'instructions' => $data['instructions'] ?? null,
								'edit' => $data['edit'] ?? null
							]) as $chunk
						) {
							$send($chunk);
						}
					}));
				}
			]
		]
	],
	'alt-text' => AltTextField::class,
];
