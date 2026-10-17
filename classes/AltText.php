<?php

namespace tobimori\Seo;

use Kirby\Content\Field;
use Kirby\Data\Yaml;

/**
 * Value object for structured alt text data.
 * Handles both YAML object format and plain string migration.
 *
 * @consistent-constructor
 */
class AltText
{
	public const string SOURCE_AI = 'ai';
	public const string SOURCE_MANUAL = 'manual';
	public const string SOURCE_REVIEWED = 'reviewed';

	public function __construct(
		protected string $text = '',
		protected bool $decorative = false,
		protected string $source = self::SOURCE_MANUAL
	) {
	}

	/**
	 * Parses a raw field value into an AltText instance.
	 * Handles YAML object format and plain string migration.
	 */
	public static function parse(string|null $value): static
	{
		if ($value === null || $value === '') {
			return new static();
		}

		// @mago-expect lint:no-empty-catch-clause
		try {
			$data = Yaml::decode($value);

			if (array_key_exists('text', $data) || array_key_exists('decorative', $data)) {
				return new static(
					text: (string)($data['text'] ?? ''),
					decorative: ($data['decorative'] ?? false) === true,
					source: (string)($data['source'] ?? self::SOURCE_MANUAL)
				);
			}
		} catch (\Throwable) {
			// not valid YAML
		}

		// plain string = migration from old alt field
		return new static(text: $value, source: self::SOURCE_MANUAL);
	}

	public static function fromField(Field $field): static
	{
		return static::parse($field->toString());
	}

	public function text(): string
	{
		return $this->text;
	}

	public function isDecorative(): bool
	{
		return $this->decorative;
	}

	public function source(): string
	{
		return $this->source;
	}

	public function isMissing(): bool
	{
		return !$this->decorative && trim($this->text) === '';
	}

	public function isAiGenerated(): bool
	{
		return $this->source === self::SOURCE_AI;
	}

	public function isReviewed(): bool
	{
		return $this->source === self::SOURCE_REVIEWED;
	}

	public function toArray(): array
	{
		return [
			'text' => $this->text,
			'decorative' => $this->decorative,
			'source' => $this->source
		];
	}

	/**
	 * Returns HTML attributes for the alt text.
	 */
	public function toAttr(): array
	{
		return ['alt' => $this->decorative ? '' : $this->text];
	}

	public function toYaml(): string
	{
		return Yaml::encode($this->toArray());
	}

	/**
	 * Returns the resolved alt text as a Kirby Field for chaining (.or(), .isNotEmpty(), etc.)
	 */
	public function toField(): Field
	{
		return new Field(null, 'alt', (string)$this);
	}

	public function __toString(): string
	{
		if ($this->decorative) {
			return '';
		}

		return $this->text;
	}
}
