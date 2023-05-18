<?php

use Kirby\Cms\App;
use Kirby\Toolkit\Str;

return function (App $kirby) {
  if (!$kirby->option('tobimori.seo.robots.pageSettings', $kirby->option('tobimori.seo.robots.active', false))) {
    return [
      'type' => 'hidden'
    ];
  }

  $fields = [
    'robotsHeadline' => [
      'label' => 'robots',
      'type' => 'headline',
      'numbered' => false,
    ]
  ];

  foreach ($kirby->option('tobimori.seo.robots.types') as $robots) {
    $index = $kirby->option('tobimori.seo.robots.index');
    if (is_callable($index)) {
      $index = $index();
    };

    $fields["robots{$robots}"] = [
      'label' =>  'robots-' . Str::lower($robots),
      'type' => 'toggles',
      'help' => 'robots-' . Str::lower($robots) . '-help',
      'width' => '1/2',
      'default' => 'default',
      'required' => true,
      'options' => [
        'default' => t('default-select') . ' ' . ($index ? t('yes') : t('no')),
        'true' => t('yes'),
        'false' => t('no'),
      ]
    ];
  }

  $fields['seoLine3'] = [
    'type' => 'line'
  ];

  return [
    'type' => 'group',
    'fields' => $fields,
  ];
};
