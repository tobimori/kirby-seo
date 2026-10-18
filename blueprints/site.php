<?php

use Kirby\Cms\App;
use Kirby\Content\Field;
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

// the placeholder shows the value used while the field is empty, defaults receive a page
$homePage = App::instance()->site()->homePage();
$cropOgImage = $homePage !== null && (new Field($homePage, 'cropOgImage', Seo::option('default.cropOgImage', args: [$homePage])))->toBool();

return [
	'label' => 'seo.tabs.seo',
	'icon' => 'search',
	'columns' => [
		...$license,
		'main' => [
			'width' => '7/12',
			'fields' => [
				'_metaHeadline' => [
					'label' => 'seo.site.meta.headline',
					'type' => 'headline',
					'help' => 'seo.site.meta.headline.help'
				],
				'metaTemplate' => [
					'extends' => 'seo/fields/title-template',
					'label' => 'seo.fields.metaTitleTemplate.label',
					'help' => 'seo.fields.metaTitleTemplate.help'
				],
				'metaDescription' => [
					'label' => 'seo.fields.metaDescription.label',
					'type' => 'seo-writer',
					'ai' => 'site-description',
					'help' => 'seo.fields.metaDescription.help'
				],
				'_seoLine1' => [
					'type' => 'line'
				],
				'_ogHeadline' => [
					'label' => 'seo.site.og.headline',
					'type' => 'headline',
					'numbered' => false,
					'help' => 'seo.site.og.headline.help'
				],
				'ogTemplate' => [
					'extends' => 'seo/fields/title-template',
					'label' => 'seo.fields.ogTitleTemplate.label',
					'default' => '{{ title }}',
					'help' => 'seo.fields.metaTitleTemplate.help',
					'placeholder' => '{{ site.metaTemplate }}'
				],
				'ogDescription' => [
					'label' => 'seo.fields.ogDescription.label',
					'type' => 'seo-writer',
					'ai' => 'og-site-description',
					'placeholder' => '{{ site.metaDescription }}'
				],
				'ogSiteName' => [
					'label' => 'seo.fields.ogSiteName.label',
					'type' => 'text',
					'default' => '{{ site.title }}',
					'placeholder' => '{{ site.title }}',
					'width' => '1/2'
				],
				'ogImage' => [
					'label' => 'seo.fields.ogImage.label',
					'extends' => 'seo/fields/og-image',
					'empty' => 'seo.fields.ogImage.empty',
					'width' => '1/2'
				],
				'cropOgImage' => [
					'label' => 'seo.fields.cropOgImage.label',
					'type' => 'select',
					'placeholder' => "{{ t('seo.common.default') }} {{ t('seo.common." . ($cropOgImage ? 'yes' : 'no') . "') }}",
					'options' => [
						'true' => "{{ t('seo.common.yes') }}",
						'false' => "{{ t('seo.common.no') }}"
					],
					'help' => 'seo.fields.cropOgImage.help'
				],
				'_seoLine2' => [
					'type' => 'line'
				],
				'robots' => 'seo/fields/site-robots',
				'socialMediaAccounts' => 'seo/fields/social-media'
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
