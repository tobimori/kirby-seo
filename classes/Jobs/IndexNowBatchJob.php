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
		return t('seo.job.indexNow');
	}

	public function handle(): void
	{
		$urls = array_unique(
			array_merge(...array_column($this->payload(), 'urls'))
		);

		if (empty($urls)) {
			return;
		}

		if (!IndexNow::send($urls)) {
			throw new \Exception('IndexNow request failed');
		}
	}
}
