<?php

use Kirby\Cms\Page;
use Kirby\Toolkit\I18n;

return [
  'props' => [
    /**
     * @deprecated 3.8.0 Use `label` instead
     */
    'headline' => function ($headline = null) {
      return I18n::translate($headline, $headline);
    },
    /**
     * The label for the section. This can be a simple string or
     * a template with additional info from the parent page.
     * Replaces the `headline` prop.
     */
    'label' => function ($label = null) {
      return I18n::translate($label, $label);
    }
  ],
  'computed' => [
    'label' => function () {
      if ($this->label) {
        return $this->model()->toString($this->label);
      }

      if ($this->headline) {
        return $this->model()->toString($this->headline);
      }

      return null;
    },
    'value' => function () {
      $model = $this->model();

      if (kirby()->request()->query()->isNotEmpty()) {
        $model = $model->clone(['content' => array_merge(['title' => $model->title()->value()], $model->content()->data(), kirby()->request()->query()->data())]);
      }

      if ($model instanceof Page) {
        $page = $model->render();
        $dom = new DOMDocument();
        $dom->loadHTML($page, libxml_use_internal_errors(true));

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
