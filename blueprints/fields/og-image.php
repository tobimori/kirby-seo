<?php

use Kirby\Cms\App;

return function () {
  $blueprint = [
    'type' => 'files',
    'multiple' => false,
  ];

  if ($parent = option('tobimori.seo.files.parent')) {
    $blueprint['uploads'] = [
      'parent' => $parent
    ];
    $blueprint['query'] = "{$parent}.images";
  }

  if ($template = option('tobimori.seo.files.template')) {
    $blueprint['uploads'] = [
      ...$blueprint['uploads'],
      'template' => $template
    ];

    if (!isset($blueprint['query'])) {
      $blueprint['query'] = "page.images";
    }

    $blueprint['query'] = "{$blueprint['query']}.filterBy('template', '{$template}')";
  }

  return $blueprint;
};
