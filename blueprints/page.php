<?php

use Kirby\Cms\App;
use tobimori\Seo\Seo;

$license = [];

if (App::plugin('tobimori/seo')?->license()->status()->value() !== 'active') {
	$license['license'] = [
		'width' => '1/1',
		'sections' => [
			'seoLicense' => [
				'type' => 'seo-license'
			]
		]
	];
}

$searchConsole = [];

if (Seo::option('searchConsole.enabled')) {
	$searchConsole['seoSearchConsole'] = [
		'type' => 'seo-search-console'
	];
}

return [
	'label' => 'seo.tabs.seo',
	'icon' => 'search',
	'columns' => [
		...$license,
		'main' => [
			'width' => '7/12',
			'fields' => [
				'metaGroup' => 'seo/fields/meta-group',
				'ogGroup' => 'seo/fields/og-group',
				'robots' => 'seo/fields/robots',
				'metaInherit' => [
					'label' => 'seo.fields.inheritSettings.label',
					'type' => 'multiselect',
					'help' => 'seo.fields.inheritSettings.help',
					'options' => [
						'metaTemplate' => [
							'*' => 'seo.fields.metaTitleTemplate.label'
						],
						'metaDescription' => [
							'*' => 'seo.fields.metaDescription.label'
						],
						'ogTemplate' => [
							'*' => 'seo.fields.ogTitleTemplate.label'
						],
						'ogDescription' => [
							'*' => 'seo.fields.ogDescription.label'
						],
						'ogImage' => [
							'*' => 'seo.fields.ogImage.label'
						],
						'cropOgImage' => [
							'*' => 'seo.fields.cropOgImage.label'
						],
						'robots' => [
							'*' => 'seo.fields.robots.label'
						]
					]
				]
			]
		],
		'sidebar' => [
			'width' => '5/12',
			'sticky' => true,
			'sections' => [
				'seoPreview' => [
					'type' => 'seo-preview'
				],
				...$searchConsole
			]
		]
	]
];
