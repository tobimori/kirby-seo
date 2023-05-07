<?php

use Kirby\Cms\Page;

return [
  'mixins' => ['headline'],
  'computed' => [
    'value' => function (Page $model = null) {
      $model = $this->model();

      if ($model instanceof Page) {
        $page = $model->render();
        $dom = new DOMDocument();
        $dom->loadHTML(htmlspecialchars_decode(iconv('UTF-8', 'ISO-8859-1', htmlentities($page, ENT_COMPAT, 'UTF-8')), ENT_QUOTES), libxml_use_internal_errors(true));

        $xpath = new DOMXPath($dom);
        $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        $data = [];

        foreach ($headings as $heading) {
          $data[] = [
            'level' => (int) str_replace('h', '', $heading->nodeName),
            'text' => $heading->textContent,
          ];
        }

        return $data;
      }

      return null;
    },
  ],
  'api' => function () {
    return [
      [
        'pattern' => '/test',
        'method' => 'GET|POST',
        'action' => function () {
          $model = $this->model();

          if (kirby()->request()->query()->isNotEmpty()) {
            $model = $model->clone(['content' => array_merge(['title' => $model->title()->value()], $model->content()->data(), kirby()->request()->query()->data())]);
          }

          return $this->value($model);
        }
      ]
    ];
  }
];
