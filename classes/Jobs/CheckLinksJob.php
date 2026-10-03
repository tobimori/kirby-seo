<?php

namespace tobimori\Seo\Jobs;

use tobimori\Queues\BatchJob;
use tobimori\Seo\Audit\Links\Checker;
use tobimori\Seo\Audit\Links\Crawler;
use tobimori\Seo\Seo;

/**
 * Runs link audits in a queue worker, rescheduling incomplete scans
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

		// Reserve time for a final batch that exceeds the step budget
		$deadline = time() + $this->timeout() - self::STEP - max(Crawler::timeout(), (int)Seo::option('links.timeout') * 2);

		do {
			$progress = $checker->step(self::STEP);
		} while (!$progress['done'] && !$progress['running'] && time() < $deadline);

		if (!$progress['done'] && !$progress['running']) {
			Checker::dispatch(now: true);
		}
	}
}
