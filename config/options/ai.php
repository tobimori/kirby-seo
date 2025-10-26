<?php

use tobimori\Seo\Ai\Drivers\Anthropic;
use tobimori\Seo\Ai\Drivers\OpenAi;

// TODO: custom provider per task
return [
	'enabled' => true,
	'provider' => 'openai',
	'providers' => [
		'openai' => [
			'driver' => OpenAi::class,
			'config' => [
				'apiKey' => '', // needs to be defined
			],
		],
		'anthropic' => [
			'driver' => Anthropic::class,
			'config' => [
				'apiKey' => '', // needs to be defined
			],
		],
		'openrouter' => [
			'driver' => OpenAi::class,
			'config' => [
				'apiKey' => '', // needs to be defined
				'model' => 'openai/gpt-5-nano',
				'endpoint' => 'https://openrouter.ai/api/v1/responses',
			],
		],
	],
];
