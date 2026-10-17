<?php

namespace tobimori\Seo\Jobs;

use tobimori\Queues\BatchJob;
use tobimori\Seo\IndexNow;
use tobimori\Seo\Seo;

/**
 * Batch job for sending IndexNow requests
 *
 * Accumulates URLs from multiple page changes within a configurable
 * time window, deduplicates them, and sends a single IndexNow request.
 */
class IndexNowBatchJob extends BatchJob
{
	public function batchWindow(): int
	{
		return Seo::option('indexnow.delay', 30);
	}

	public function type(): string
	{
		return 'seo:indexnow';
	}

	public function name(): string
	{
		return Seo::translate('seo.job.indexNow');
	}

	public function handle(): void
	{
		/** @var array<array{urls: list<string>}> $payloads */
		$payloads = $this->payload();
		$urls = array_unique(
			array_merge(...array_column($payloads, 'urls'))
		);

		if (empty($urls) || !IndexNow::isActive()) {
			return;
		}

		IndexNow::submit($urls);
	}
}
