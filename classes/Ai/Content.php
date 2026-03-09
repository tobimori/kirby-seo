<?php

namespace tobimori\Seo\Ai;

use Imagick;
use Kirby\Cms\File;

/**
 * Fluent builder for AI message content.
 * Each instance represents a single message with a role and content blocks.
 */
class Content
{
	private string $role;
	private array $blocks = [];

	private function __construct(string $role)
	{
		$this->role = $role;
	}

	public static function user(): static
	{
		return new static('user');
	}

	public static function assistant(): static
	{
		return new static('assistant');
	}

	public static function system(): static
	{
		return new static('system');
	}

	public function text(string $text): static
	{
		$this->blocks[] = ['type' => 'text', 'text' => $text];
		return $this;
	}

	/**
	 * Adds an image block from a Kirby File, converted to WebP for smaller payloads.
	 * Non-resizable formats (SVG, etc.) are rasterized via Imagick.
	 */
	public function image(File $file, int $maxDimension = 1024): static
	{
		if ($file->isResizable()) {
			$thumb = $file->thumb([
				'width' => $maxDimension,
				'height' => $maxDimension,
				'format' => 'webp',
			]);

			$data = base64_encode($thumb->read());
		} else {
			// TODO: better handling without ext-imagick
			$imagick = new Imagick();
			$imagick->readImage($file->root());
			$imagick->setImageFormat('webp');
			$imagick->thumbnailImage($maxDimension, $maxDimension, true);
			$data = base64_encode($imagick->getImageBlob());
			$imagick->clear();
			$imagick->destroy();
		}

		$this->blocks[] = [
			'type' => 'image',
			'data' => $data,
			'mediaType' => 'image/webp',
		];

		return $this;
	}

	public function role(): string
	{
		return $this->role;
	}

	public function blocks(): array
	{
		return $this->blocks;
	}
}
