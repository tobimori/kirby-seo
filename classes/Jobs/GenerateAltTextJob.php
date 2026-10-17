<?php

namespace tobimori\Seo\Jobs;

use Kirby\Cms\App;
use tobimori\Queues\Job;
use tobimori\Seo\Field\AltTextField;
use tobimori\Seo\Seo;

/**
 * Queue job for generating alt text via AI on file upload
 */
class GenerateAltTextJob extends Job
{
	public function type(): string
	{
		return 'seo:generate-alt-text';
	}

	public function name(): string
	{
		return Seo::translate('seo.job.generateAltText');
	}

	public function handle(): void
	{
		/** @var string $fileId */
		$fileId = $this->payload()['fileId'];
		$file = App::instance()->file($fileId);

		if ($file === null) {
			throw new \Exception("File not found: {$fileId}");
		}

		AltTextField::generateForFile($file);
	}
}
