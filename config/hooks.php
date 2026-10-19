<?php

use Kirby\Cms\App;
use Kirby\Cms\Event;
use Kirby\Cms\File;
use Kirby\Cms\Language;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Http\Params;
use Kirby\Toolkit\Str;
use tobimori\Seo\Audit\Images;
use tobimori\Seo\Audit\Links\Checker;
use tobimori\Seo\Field\AltTextField;
use tobimori\Seo\Seo;

// results of the overview audits depend on content changes
$audit = function (Event $event) {
	if (!in_array($event->action(), ['create', 'duplicate', 'update', 'move', 'changeSlug', 'changeStatus', 'changeTemplate', 'changeName', 'delete'], true)) {
		return;
	}

	Images::clearStats();

	// pages that are and were not indexable (e.g. form submissions) don't affect the other audits,
	// unless children inherit their robots settings or get new URLs with them
	// robots settings can differ per language
	$languages = App::instance()->multilang() ? App::instance()->languages()->values() : [null];
	$pages = array_filter([$event->argument('page'), $event->argument('newPage'), $event->argument('oldPage')]);
	if (
		$pages !== [] &&
		!array_filter(
			$pages,
			fn (Page $page) => $page->hasChildren() || array_filter($languages, fn (Language|null $language) => Seo::isIndexable($page, $language)) !== []
		)
	) {
		return;
	}

	if ($event->type() !== 'file') {
		Seo::clearIndexable();
	}

	Checker::dispatch();
};

$indexNow = function (Page $newPage): void {
	if (Seo::option('indexnow.enabled')) {
		(new (Seo::option('components.indexnow'))($newPage))->dispatch();
	}
};

return [
	'system.loadPlugins:after' => function () {
		if (class_exists('tobimori\Queues\Queues')) {
			\tobimori\Queues\Queues::register([
				\tobimori\Seo\Jobs\CheckLinksJob::class,
				\tobimori\Seo\Jobs\GenerateAltTextJob::class,
				\tobimori\Seo\Jobs\IndexNowBatchJob::class
			]);
		}
	},
	// Persisted schedules are synchronized when the worker starts
	'tobimori.queues.worker:before' => fn () => Checker::schedule(),
	'page.*:after' => $audit,
	'site.*:after' => $audit,
	'file.*:after' => $audit,
	'file.create:after' => function (File $file) {
		if ($file->type() !== 'image') {
			return;
		}

		if (class_exists('tobimori\Queues\Queues')) {
			\tobimori\Queues\Queues::push('seo:generate-alt-text', [
				'fileId' => $file->id()
			]);

			return $file;
		}

		return AltTextField::generateForFile($file);
	},
	'page.update:after' => function (Page $newPage, Page $oldPage) use ($indexNow) {
		// only inject blueprint defaults if the seo tab is present
		if ($newPage->blueprint()->tab('seo')) {
			$updates = [];
			foreach (Seo::option('robots.types') as $robots) {
				$key = 'robots' . Str::ucfirst($robots);

				/** @var Field $field */
				$field = $newPage->content()->get($key);
				if ($field->value() === '') {
					$updates[$key] = 'default';
				}
			}

			if ($updates !== []) {
				$newPage = $newPage->update($updates, $newPage->kirby()->languageCode());
			}
		}

		$indexNow($newPage);

		return $newPage;
	},
	'page.changeStatus:after' => $indexNow,
	'page.changeSlug:after' => $indexNow,
	'page.render:before' => function (string $contentType, array $data, Page $page) {
		// schemas are only output in HTML, skip markdown, XML and text representations
		if ($contentType !== 'html' || !class_exists('Spatie\SchemaOrg\Schema')) {
			return;
		}

		if (App::instance()->option('tobimori.seo.generateSchema')) {
			$meta = $page->metadata();
			// schema types come from the optional spatie/schema-org package
			// @mago-expect analysis:mixed-method-access(5)
			$page
				->schema('WebSite')
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

		// params follow the representation, e.g. `notes.md/page:2` from pagination links
		/** @var array<int, string>|null $segments */
		$segments = Params::extract($path)['path'];
		$route = implode('/', $segments ?? []);
		$isMarkdownPath = Str::endsWith(Str::lower($route), '.md');
		if ($isMarkdownPath) {
			$kirby = App::instance();
			$markdownPath = Str::substr($route, 0, -3);
			$language = $kirby->language();

			// the path starts with the URL prefix of its language (`de/blog.md`),
			// or is only the prefix for the home page (`de.md`)
			foreach ($kirby->multilang() ? $kirby->languages()->filter('baseurl', $kirby->url()) : [] as $candidate) {
				$prefix = $candidate->path();
				if ($prefix !== '' && ($markdownPath === $prefix || Str::startsWith($markdownPath, $prefix . '/'))) {
					$language = $candidate;
					$markdownPath = Str::substr($markdownPath, Str::length($prefix) + 1);
					break;
				}
			}

			$resolved = $kirby->resolve($markdownPath === '' ? null : $markdownPath, $language?->code());
			if ($resolved instanceof Page) {
				$result = $resolved;
			}
		}

		if (!$result instanceof Page && $result !== null && $result !== false) {
			return $result;
		}

		$page = $result instanceof Page ? $result : App::instance()->site()->errorPage();
		if ($page === null) {
			return $result;
		}

		$class = Seo::option('components.agentic');
		$llmContent = new $class($page);
		if ($llmContent->enabled() === false) {
			return $result;
		}

		$wantsMarkdown =
			$isMarkdownPath ||
			$llmContent->prefersMarkdown((string)App::instance()->request()->header('Accept'));

		if ($result instanceof Page) {
			if ($wantsMarkdown) {
				return $llmContent->markdownResponse(App::instance()->language(), $method) ?? $result;
			}

			if ($llmContent->available()) {
				$llmContent->addDiscoveryHeaders();
			}

			return $result;
		}

		return $wantsMarkdown
			? $llmContent->notFoundResponse(App::instance()->language(), $method)
			: $result;
	}
];
