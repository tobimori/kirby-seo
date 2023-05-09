<?php

use Kirby\Cms\Page;
use Kirby\Form\Form;

return [
  'mixins' => ['headline'],
  'computed' => [
    'value' => function () {
      $model = $this->model();
      $kirby = $model->kirby();

      if ($kirby->request()->query()->isNotEmpty()) {
        $form = Form::for($model, [ // Form class handles transformation of changed items
          'ignoreDisabled' => true,
          'input' => array_merge(['title' => $model->title()], $model->content()->data(), kirby()->request()->query()->data()),
          'language' => $kirby->language()->code()
        ]);

        $model = $model->clone(['content' => $form->data()]);
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
    }
  ]
];
