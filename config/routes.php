<?php

use Kirby\Http\Response;

return [
  [
    'pattern' => 'robots.txt',
    'action' => function () {
      if (option('tobimori.seo.robots.active', true)) {
        $content = snippet('seo/robots.txt', [], true);
        return new Response($content, 'text/plain', 200);
      }

      $this->next();
    }
  ]
];
