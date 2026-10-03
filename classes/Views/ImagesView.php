<?php

namespace tobimori\Seo\Views;

use Kirby\Cms\ModelWithContent;
use Kirby\Content\Changes;
use Kirby\Content\VersionId;
use Kirby\Exception\NotFoundException;
use Kirby\Http\Response;
use Kirby\Panel\Ui\Item\FileItem;
use Kirby\Toolkit\I18n;
use tobimori\Seo\Ai\Content;
use tobimori\Seo\AltText;
use tobimori\Seo\Audit;
use tobimori\Seo\Field\AltTextField;

/**
 * Panel view listing the alt texts of all images, one row per alt text field
 */
class ImagesView extends EditableOverviewView
{
	public const SORTABLE = ['title', 'alt', 'decorative', 'template'];
	public const SEARCHABLE = ['id', 'alt', 'parent', 'template'];
	public const SOURCES = [AltText::SOURCE_AI, AltText::SOURCE_MANUAL, AltText::SOURCE_REVIEWED];

	public function load(): array
	{
		return VersionId::render('changes', function () {
			// states of alt texts, `issues` combines the ones that need work
			['search' => $search, 'sort' => $sort, 'dir' => $dir, 'issue' => $issue] = $this->query(
				self::SORTABLE,
				[...Audit\Images::FILTERS, 'issues']
			);

			$entries = array_values($this->images()->entries());

			if ($issue) {
				$entries = array_values(array_filter($entries, fn ($entry) => $this->images()->has($entry, $issue)));
			}

			$entries = $this->search($entries, $search, self::SEARCHABLE, $this->value(...));

			if ($sort) {
				$entries = $this->sort($entries, fn ($entry) => $this->value($entry, $sort === 'title' ? 'id' : $sort), $dir);
			}

			[$pagination, $visible] = $this->paginate($entries);

			return [
				'component' => 'k-seo-images-view',
				'title' => I18n::translate('seo.overview.title'),
				'props' => [
					...$this->layout('images'),
					'columns' => $this->columns(),
					'rows' => array_map($this->row(...), $visible),
					'changes' => $this->changes(),
					'pagination' => $pagination,
					'ids' => array_column($entries, 'id'),
					'summary' => $this->images()->summary(),
					'issue' => $issue,
					'ai' => $this->canUseAi(),
					'search' => $search,
					'sort' => $sort,
					'dir' => $dir,
				]
			];
		});
	}

	protected function find(string $id): array|null
	{
		return $this->images()->find($id);
	}

	/**
	 * Streams an AI-generated alt text for the row, with the same checks as the table
	 */
	public function generate(string $id): Response
	{
		$entry = $this->find($id) ?? throw $this->notFound($id);

		if (!$this->canGenerate($entry)) {
			return Response::json([
				'status' => 'error',
				'message' => I18n::translate(Content::supportsImage($entry['model']) ? 'seo.ai.error.permission' : 'seo.overview.images.ai.unsupported'),
			], 403);
		}

		AltTextField::stream($entry['model'], $this->kirby->request()->body()->data()['instructions'] ?? null);
	}

	/**
	 * Whether AI can generate the row's alt text: fields can opt out via `ai: false`,
	 * images need to be resizable (or Imagick installed) to be sent to the AI provider
	 */
	protected function canGenerate(array $entry): bool
	{
		$blueprint = $this->blueprint($entry['model'])->field($entry['field']) ?? [];

		return $this->canUseAi()
			&& $entry['model']->permissions()->can('update')
			&& ($blueprint['ai'] ?? true) !== false
			&& Content::supportsImage($entry['model']);
	}

	protected function notFound(string $id): NotFoundException
	{
		return new NotFoundException(key: 'file.notFound', data: ['filename' => $id]);
	}

	protected function ids(ModelWithContent $model): array
	{
		return array_map(
			fn ($field) => Audit\Images::id($model, $field),
			$this->images()->fields($model)
		);
	}

	protected function tracked(Changes $changes): iterable
	{
		return $changes->files();
	}

	protected function label(array $entry): string
	{
		return $entry['model']->filename();
	}

	/**
	 * Alt text fields store the text, whether the image is decorative & who wrote the text
	 */
	protected function input(array $entry, array $columns): array
	{
		$text = $columns['alt'] ?? null;
		$text = is_string($text) ? trim(preg_replace('/[\r\n]+/', ' ', $text)) : null;

		$current = AltText::parse($this->current($entry['model'], $entry['field']));
		$decorative = is_bool($columns['decorative'] ?? null) ? $columns['decorative'] : $current->isDecorative();
		$source = $columns['source'] ?? null;

		if (!in_array($source, self::SOURCES, true)) {
			// same as editing in the alt text field: editing an AI text means it has been reviewed
			$source = match (true) {
				$text === null || $text === $current->text() => $current->source(),
				$current->isAiGenerated() || $current->isReviewed() => AltText::SOURCE_REVIEWED,
				default => AltText::SOURCE_MANUAL,
			};
		}

		// writing a description means the image isn't decorative
		if ($text !== null && $text !== '' && !is_bool($columns['decorative'] ?? null)) {
			$decorative = false;
		}

		return [
			$entry['field'] => (new AltText(
				text: $text ?? $current->text(),
				decorative: $decorative,
				source: $source,
			))->toArray(),
		];
	}

	protected function live(): array
	{
		return [
			'summary' => $this->images()->summary(),
			'stats' => ['images' => $this->images()->stats()],
		];
	}

	/**
	 * Plain text value of a searchable/sortable column
	 */
	protected function value(array $entry, string $key): string
	{
		$file = $entry['model'];

		return match ($key) {
			'id' => $file->id(),
			'alt' => $this->images()->altText($entry)->text(),
			// decorative images first when sorting ascending
			'decorative' => $this->images()->altText($entry)->isDecorative() ? '0' : '1',
			'parent' => (string)$file->parent()->title()->value(),
			'template' => (string)$this->blueprint($file)->title(),
		};
	}

	/**
	 * Columns with a `toggle` label can be shown/hidden by the user,
	 * columns with `hidden: true` are hidden until the user enables them,
	 * columns with `resizable: false` keep their width.
	 */
	protected function columns(): array
	{
		return [
			'status' => [
				'label' => ' ',
				'mobile' => true,
				'resizable' => false,
				'toggle' => I18n::translate('seo.overview.images.columns.status'),
				'type' => 'seo-alt-status',
				'width' => 'var(--table-row-height)'
			],
			'image' => [
				'label' => ' ',
				'mobile' => true,
				'resizable' => false,
				'ratio' => '1/1',
				// the whole image, to judge its alt text
				'cover' => false,
				'back' => 'white',
				'toggle' => I18n::translate('seo.overview.images.columns.image'),
				'type' => 'seo-image',
				'width' => '4rem'
			],
			'title' => [
				'label' => I18n::translate('file'),
				'mobile' => true,
				'sortable' => true,
				'type' => 'seo-page',
				'width' => '1/3'
			],
			'alt' => [
				'editable' => true,
				'label' => I18n::translate('seo.overview.images.columns.alt'),
				'mobile' => true,
				// plain text instead of a writer
				'plain' => true,
				'sortable' => true,
				'type' => 'seo-meta'
			],
			'decorative' => [
				'label' => I18n::translate('seo.overview.images.columns.decorative'),
				'sortable' => true,
				'toggle' => I18n::translate('seo.overview.images.columns.decorative'),
				'type' => 'seo-decorative',
				'width' => '7.5rem'
			],
			'dimensions' => [
				'align' => 'right',
				'hidden' => true,
				'label' => I18n::translate('dimensions'),
				'toggle' => I18n::translate('dimensions'),
				'type' => 'text',
				'width' => '8rem'
			],
			'template' => [
				'hidden' => true,
				'label' => I18n::translate('template'),
				'sortable' => true,
				'toggle' => I18n::translate('template'),
				'type' => 'text',
				'width' => '10rem'
			],
		];
	}

	protected function row(array $entry): array
	{
		/** @var \Kirby\Cms\File $file */
		$file = $entry['model'];
		['lock' => $lock, 'editable' => $editable, 'translated' => $translated] = $this->state($file);
		$hasChanges = $this->fieldChanges($entry) !== [];
		$alt = $this->images()->altText($entry);
		$blueprint = $this->blueprint($file)->field($entry['field']) ?? [];
		$parent = $file->parent();
		$label = count($this->images()->fields($file)) > 1
			? I18n::translate($blueprint['label'] ?? null, $blueprint['label'] ?? $entry['field'])
			: null;

		return [
			...(new FileItem(file: $file))->props(),
			'id' => $entry['id'],
			'file' => $file->id(),
			'changes' => $hasChanges,
			'previewUrl' => $file->url(),
			'editable' => $editable,
			'lock' => $lock,
			'selectable' => $lock === null,
			'parent' => [
				'text' => (string)$parent->title()->value(),
				'link' => $parent->panel()->url(true),
			],
			'title' => [
				// files with multiple alt text fields have a row per field
				'text' => $label ? "{$file->filename()} · {$label}" : $file->filename(),
				'href' => $file->panel()->url(true),
				// the page is easier to recognize by its title than by its path
				'info' => (string)$parent->title()->value(),
				// the lock already tells that someone else is editing
				'changes' => $hasChanges && $lock === null,
				'translated' => $translated,
			],
			'alt' => [
				'text' => $alt->text(),
				'value' => $alt->text(),
				'decorative' => $alt->isDecorative(),
				'source' => $alt->source(),
				'field' => $entry['field'],
				'editable' => $editable,
				'ai' => $editable && $this->canGenerate($entry),
			],
			'image' => ['src' => match (true) {
				$file->isResizable() => $file->resize(112, 112)->url(),
				$file->extension() === 'svg' => $file->url(),
				default => null,
			}],
			'template' => (string)$this->blueprint($file)->title(),
			'dimensions' => $file->isResizable() || $file->extension() === 'svg'
				? $file->width() . ' × ' . $file->height()
				: null,
		];
	}
}
