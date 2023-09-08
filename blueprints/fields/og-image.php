<?php

use Kirby\Toolkit\Str;

return function ($app) {
  $blueprint = [
    'type' => 'files',
    'multiple' => false,
    'uploads' => [],
    'query' => Str::contains($app->path(), 'site') ? "site.images" : "page.images" // small hack to get context for field using api path
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

    $blueprint['query'] = "{$blueprint['query']}.filterBy('template', '{$template}')";
  }

  return $blueprint;
};
