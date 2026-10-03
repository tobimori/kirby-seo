<?php

namespace tobimori\Seo\Agents;

use tobimori\Agents\Content\InputCheck;
use tobimori\Agents\Content\Presenter;
use tobimori\Agents\Fields\WriterField;
use tobimori\Agents\Schema\Compiler;

/**
 * Describes the `seo-writer` field to Kirby Agents.
 * Agents read and write template variables as `{{ title }}` placeholders,
 * the field stores them as `<span data-seo-template-variable="title">` nodes.
 * Only loaded when Kirby Agents is installed, @see index.php
 */
class SeoWriterField extends WriterField
{
	/**
	 * Writer nodes of the template variables, @see src/index.js
	 */
	private const array VARIABLES = [
		'seoTemplateTitle' => ['title', 'page title'],
		'seoTemplateSiteTitle' => ['site.title', 'site title'],
	];

	public function describe(Compiler $schema): string
	{
		$variables = array_map(
			fn (array $variable) => "{{ {$variable[0]} }} ({$variable[1]})",
			$this->variables()
		);

		return parent::describe($schema)
			. ', no line breaks'
			. ($variables !== [] ? ', placeholders: ' . implode(', ', $variables) : '');
	}

	public function input(mixed $value, mixed $current): mixed
	{
		if (!is_string($value)) {
			return parent::input($value, $current);
		}

		// the seo-writer has no hard breaks, unlike the core writer
		$value = preg_replace('/\s*<br\s*\/?>\s*/i', ' ', $value);

		if (($variables = $this->variables()) === []) {
			return parent::input($value, $current);
		}

		$names = implode('|', array_map(fn (array $variable) => preg_quote($variable[0], '/'), $variables));

		// agents may also send the stored form, so turn it into placeholders first
		return parent::input(preg_replace_callback(
			"/\{\{\s*({$names})\s*\}\}/",
			fn (array $match) => "<span data-seo-template-variable=\"{$match[1]}\">{{ {$match[1]} }}</span>",
			self::placeholders($value)
		), $current);
	}

	// template variable nodes are not in the allowed tags of the writer
	public function check(mixed $value, InputCheck $check, string $where): void
	{
		parent::check(is_string($value) ? self::placeholders($value) : $value, $check, $where);
	}

	public function present(mixed $value, array $path, Presenter $presenter): mixed
	{
		return is_string($value) ? self::placeholders($value) : $value;
	}

	/**
	 * The template variables that the blueprint enables with `nodes`
	 *
	 * @return array<string, array{string, string}>
	 */
	private function variables(): array
	{
		$nodes = $this->props['nodes'] ?? null;

		return is_array($nodes) ? array_intersect_key(self::VARIABLES, array_flip(array_filter($nodes, is_string(...)))) : [];
	}

	/**
	 * Replaces template variable nodes with `{{ variable }}` placeholders
	 */
	private static function placeholders(string $value): string
	{
		return preg_replace(
			'/<span data-seo-template-variable="([^"]+)">.*?<\/span>/s',
			'{{ $1 }}',
			$value
		) ?? $value;
	}
}
