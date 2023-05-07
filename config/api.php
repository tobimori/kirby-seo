<?php

use Kirby\Cms\App;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;

return [
  'routes' => function (App $kirby) {
    return [
      [
        'pattern' => 'tobimori/seo/heading-structure',
        'method' => 'POST',
        'action' => function () use ($kirby) {
          $model = $kirby->page($this->requestBody('page'));

          if ($this->requestBody('changes')) {
            $model = $model->clone();
            $model = $model->content()->update($this->requestBody('changes'));
          }

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
    ];
  }
];
