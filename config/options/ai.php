<?php

use tobimori\Seo\Ai\Providers\OpenAi;

return [
	'enabled' => true,

	/**
	 * Identifier of the provider entry to use when a task
	 * does not declare its own provider.
	 */
	'defaultProvider' => 'openai',

	'providers' => [
		'openai' => [
			'class' => OpenAi::class,
			'config' => [
				'apiKey' => '', // needs to be defined
				'model' => 'gpt-5-mini',
				'endpoint' => 'https://api.openai.com/v1/responses',
			],
		],
		'openrouter' => [
			'class' => OpenAi::class,
			'config' => [
				'apiKey' => '', // needs to be defined
				'model' => 'openai/gpt-5-mini',
				'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
			],
		],
	],

	/**
	 * Task definitions describe how different jobs should
	 * call the AI service. Prompts are stored in the translation
	 * files and referenced by their key.
	 */
	'tasks' => [
		'pageTitle' => [
			'provider' => null, // e.g. => 'openai',
			'prompt' => 'seo.ai.prompts.pageTitle',
			'context' => [],
		],
		'imageAlt' => [
			'provider' => null, // e.g. => 'openai',
			'prompt' => 'seo.ai.prompts.imageAlt',
			'context' => [],
		],
	],
];
