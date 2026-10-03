<?php

use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use tobimori\Seo\Field\AltTextField;
use tobimori\Seo\Seo;

return [
	'system.loadPlugins:after' => function () {
		if (class_exists('tobimori\Queues\Queues')) {
			\tobimori\Queues\Queues::register([
				\tobimori\Seo\Jobs\GenerateAltTextJob::class,
				\tobimori\Seo\Jobs\IndexNowBatchJob::class,
			]);
		}
	},
	'file.create:after' => function (File $file) {
		if ($file->type() !== 'image') {
			return;
		}

		if (class_exists('tobimori\Queues\Queues')) {
			\tobimori\Queues\Queues::push('seo:generate-alt-text', [
				'fileId' => $file->id(),
			]);

			return $file;
		}

		return AltTextField::generateForFile($file);
	},
	'page.update:after' => function (Page $newPage, Page $oldPage) {
		// only inject blueprint defaults if the seo tab is present
		if ($newPage->blueprint()->tab('seo')) {
			$updates = A::reduce(
				$newPage->kirby()->option('tobimori.seo.robots.types'),
				function ($carry, $robots) use ($newPage) {
					$upper = Str::ucfirst($robots);

					if ($newPage->content()->get("robots{$upper}")->value() === '') {
						$carry["robots{$upper}"] = 'default';
					}

					return $carry;
				},
				[]
			);

			if (A::count($updates)) {
				$newPage = $newPage->update($updates, $newPage->kirby()->languageCode());
			}
		}

		if (Seo::option('indexnow.enabled')) {
			$indexNow = new (Seo::option('components.indexnow'))($newPage);
			$indexNow->dispatch();
		}

		return $newPage;
	},
	'page.changeStatus:after' => function (Page $newPage, Page $oldPage) {
		if (Seo::option('indexnow.enabled')) {
			$indexNow = new (Seo::option('components.indexnow'))($newPage);
			$indexNow->dispatch();
		}
	},
	'page.changeSlug:after' => function (Page $newPage, Page $oldPage) {
		if (Seo::option('indexnow.enabled')) {
			$indexNow = new (Seo::option('components.indexnow'))($newPage);
			$indexNow->dispatch();
		}
	},
	'page.render:before' => function (string $contentType, array $data, Page $page) {
		// schemas are only output in HTML, skip markdown, XML and text representations
		if ($contentType !== 'html' || !class_exists('Spatie\SchemaOrg\Schema')) {
			return;
		}

		if (option('tobimori.seo.generateSchema')) {
			$meta = $page->metadata();
			$page->schema('WebSite')
				->url($meta->canonicalUrl())
				->copyrightYear(date('Y'))
				->description($meta->metaDescription())
				->name($meta->metaTitle())
				->headline($meta->title());
		}
	},
	'route:after' => function (string $path, string $method, mixed $result, bool $final) {
		if ($final === false || !in_array($method, ['GET', 'HEAD'], true)) {
			return $result;
		}

		$isMarkdownPath = Str::endsWith(Str::lower($path), '.md');
		if ($isMarkdownPath) {
			$markdownPath = Str::substr($path, 0, -3);
			$resolved = kirby()->resolve($markdownPath === '' ? null : $markdownPath, kirby()->languageCode());
			if ($resolved instanceof Page) {
				$result = $resolved;
			}
		}

		if (!$result instanceof Page && $result !== null && $result !== false) {
			return $result;
		}

		$page = $result instanceof Page ? $result : kirby()->site()->errorPage();
		if ($page === null) {
			return $result;
		}

		$class = Seo::option('components.agentic');
		$llmContent = new $class($page);
		if ($llmContent->enabled() === false) {
			return $result;
		}

		$wantsMarkdown = $isMarkdownPath
			|| $llmContent->prefersMarkdown(kirby()->request()->header('Accept'));

		if ($result instanceof Page) {
			if ($wantsMarkdown) {
				return $llmContent->markdownResponse(kirby()->language(), $method) ?? $result;
			}

			if ($llmContent->available()) {
				$llmContent->addDiscoveryHeaders();
			}

			return $result;
		}

		return $wantsMarkdown
			? $llmContent->notFoundResponse(kirby()->language(), $method)
			: $result;
	},
];
