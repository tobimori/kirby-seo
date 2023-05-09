<?php

use Kirby\Cms\Page;
use Kirby\Cms\Site;
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

      if ($model instanceof Site) {
        $model = $model->homePage(); // todo: show actual site fields instead of home page
      }

      if ($model instanceof Page) {
        $meta = $model->metadata();

        $ogImage = $meta->ogImage()->toFile()?->thumb([
          'width' => 1200,
          'height' => 630,
          'crop' => true,
        ]);

        return [
          'page' => $model->slug(),
          'url' => $model->url(),
          'title' => $meta->title()->value(),
          'description' => $meta->metaDescription()->value(),
          'ogTitle' => $meta->ogTitle()->value(),
          'ogDescription' => $meta->ogDescription()->value(),
          'ogImage' => $ogImage?->url(),
        ];
      }


      return null;
    }
  ]
];
