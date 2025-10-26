<?php

namespace tobimori\Seo\Ai;

/**
 * Value object representing a streamed AI response chunk.
 */
final class Chunk
{
	public const string TYPE_STREAM_START = 'stream-start';
	public const string TYPE_STREAM_END = 'stream-end';
	public const string TYPE_TEXT_START = 'text-start';
	public const string TYPE_TEXT_DELTA = 'text-delta';
	public const string TYPE_TEXT_COMPLETE = 'text-complete';
	public const string TYPE_THINKING_START = 'thinking-start';
	public const string TYPE_THINKING_DELTA = 'thinking-delta';
	public const string TYPE_THINKING_COMPLETE = 'thinking-complete';
	public const string TYPE_TOOL_CALL = 'tool-call';
	public const string TYPE_TOOL_RESULT = 'tool-result';
	public const string TYPE_ERROR = 'error';

	private function __construct(
		public readonly string $type,
		public readonly mixed $payload = null,
		public readonly ?string $text = null
	) {
	}

	public static function streamStart(array $payload = []): self
	{
		return new self(self::TYPE_STREAM_START, $payload);
	}

	public static function streamEnd(array $payload = []): self
	{
		return new self(self::TYPE_STREAM_END, $payload);
	}

	public static function textStart(array $payload = []): self
	{
		return new self(self::TYPE_TEXT_START, $payload);
	}

	public static function textDelta(string $text, array $payload = []): self
	{
		return new self(self::TYPE_TEXT_DELTA, $payload, $text);
	}

	public static function textComplete(array $payload = []): self
	{
		return new self(self::TYPE_TEXT_COMPLETE, $payload);
	}

	public static function thinkingStart(array $payload = []): self
	{
		return new self(self::TYPE_THINKING_START, $payload);
	}

	public static function thinkingDelta(string $text, array $payload = []): self
	{
		return new self(self::TYPE_THINKING_DELTA, $payload, $text);
	}

	public static function thinkingComplete(array $payload = []): self
	{
		return new self(self::TYPE_THINKING_COMPLETE, $payload);
	}

	public static function toolCall(array $payload = []): self
	{
		return new self(self::TYPE_TOOL_CALL, $payload);
	}

	public static function toolResult(array $payload = []): self
	{
		return new self(self::TYPE_TOOL_RESULT, $payload);
	}

	public static function error(string $message, array $payload = []): self
	{
		return new self(self::TYPE_ERROR, [
			'message' => $message,
			'data' => $payload,
		]);
	}

	public function isStreamStart(): bool
	{
		return $this->type === self::TYPE_STREAM_START;
	}

	public function isStreamEnd(): bool
	{
		return $this->type === self::TYPE_STREAM_END;
	}

	public function isTextStart(): bool
	{
		return $this->type === self::TYPE_TEXT_START;
	}

	public function isTextDelta(): bool
	{
		return $this->type === self::TYPE_TEXT_DELTA;
	}

	public function isTextComplete(): bool
	{
		return $this->type === self::TYPE_TEXT_COMPLETE;
	}

	public function isThinkingStart(): bool
	{
		return $this->type === self::TYPE_THINKING_START;
	}

	public function isThinkingDelta(): bool
	{
		return $this->type === self::TYPE_THINKING_DELTA;
	}

	public function isThinkingComplete(): bool
	{
		return $this->type === self::TYPE_THINKING_COMPLETE;
	}

	public function isToolCall(): bool
	{
		return $this->type === self::TYPE_TOOL_CALL;
	}

	public function isToolResult(): bool
	{
		return $this->type === self::TYPE_TOOL_RESULT;
	}

	public function isError(): bool
	{
		return $this->type === self::TYPE_ERROR;
	}
}
