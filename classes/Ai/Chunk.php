<?php

namespace tobimori\Seo\Ai;

/**
 * Value object representing a streamed AI response chunk.
 */
final class Chunk
{
	public const string TYPE_TEXT_DELTA = 'text-delta';
	public const string TYPE_DONE = 'done';
	public const string TYPE_ERROR = 'error';

	private function __construct(
		public readonly string $type,
		public readonly ?string $text,
		public readonly mixed $payload
	) {
	}

	public static function textDelta(string $text): self
	{
		return new self(self::TYPE_TEXT_DELTA, text: $text, payload: null);
	}

	public static function done(mixed $payload = null): self
	{
		return new self(self::TYPE_DONE, text: null, payload: $payload);
	}

	public static function error(string $message, mixed $payload = null): self
	{
		return new self(self::TYPE_ERROR, text: null, payload: [
			'message' => $message,
			'data'    => $payload,
		]);
	}

	public function isTextDelta(): bool
	{
		return $this->type === self::TYPE_TEXT_DELTA;
	}

	public function isDone(): bool
	{
		return $this->type === self::TYPE_DONE;
	}

	public function isError(): bool
	{
		return $this->type === self::TYPE_ERROR;
	}
}
