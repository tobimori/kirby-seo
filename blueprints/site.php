<?php

use tobimori\Seo\Seo;

return [
	'label' => 'seo.tabs.seo',
	'icon' => 'search',
	'columns' => [
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
					'type' => 'toggle',
					'default' => true,
					'text' => [
						"{{ t('seo.common.no') }}",
						"{{ t('seo.common.yes') }}"
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
				...(Seo::option('searchConsole.enabled') ? [
					'seoSearchConsole' => [
						'type' => 'seo-search-console'
					]
				] : [])
			]
		]
	]
];
