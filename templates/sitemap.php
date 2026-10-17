<?php

use Kirby\Http\Response;

/**
 * @var \Kirby\Cms\Page $page
 */

Response::go($page->url() . '.xml');
