<?php

namespace tobimori\Seo\Jobs;

use tobimori\Queues\BatchJob;
use tobimori\Queues\Queues;
use tobimori\Seo\Links\Checker;
use tobimori\Seo\Seo;

/**
 * Queue job for checking links after content changes (batched) & on a schedule.
 * Runs the steps of the check until the job's time is up and continues in a new job
 */
class CheckLinksJob extends BatchJob
{
	protected const STEP = 30;

	public function batchWindow(): int
	{
		return 60;
	}

	public function type(): string
	{
		return 'seo:check-links';
	}

	public function name(): string
	{
		return t('seo.job.checkLinks');
	}

	public function timeout(): int
	{
		return 300;
	}

	public function handle(): void
	{
		// batched jobs get the list of payloads, scheduled ones a single payload
		$payload = $this->payload();
		$payloads = array_is_list($payload) ? $payload : [$payload];
		$checker = new Checker();

		if (in_array(true, array_column($payloads, 'full'), true)) {
			$checker->invalidate();
		}

		// leave time for the last step, which might take longer (e.g. slow external URLs)
		$deadline = time() + $this->timeout() - self::STEP - (int)Seo::option('links.timeout') * 2;

		do {
			$progress = $checker->step(self::STEP);
		} while (!$progress['done'] && !$progress['running'] && time() < $deadline);

		// continue in a new job, unless another scan is running
		if (!$progress['done'] && !$progress['running']) {
			Queues::push(static::class, ['full' => false]);
		}
	}
}
