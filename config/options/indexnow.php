<?php

return [
	'enabled' => true,
	'searchEngine' => 'https://api.indexnow.org', // one will propagate to all others. so this is fine @see https://www.indexnow.org/faq
	'rules' => [
		// by default, only the current page is requested to be indexed (if indexable: robots allow + listed status)
		// however you might want to index other pages as well. for example, the 'blog overview' page should always be reindexed when a new 'blog post' is indexed
		//
		// syntax: 'match pattern' => ['invalidation rules']
		//
		// match patterns:
		// - '/blog/*' - url pattern (glob or regex)
		// - 'article' - template name
		// - '*' - wildcard, matches all pages
		//
		// invalidation rules:
		// - 'parent' => true (direct parent) or number (levels up)
		// - 'children' => true (all descendants) or number (depth limit)
		// - 'siblings' => true (all siblings at same level)
		// - 'urls' => ['/shop', '/'] (specific urls to invalidate)
		// - 'templates' => ['category', 'shop'] (invalidate all pages with these templates)
		//
		// examples:
		// '/blog/*' => ['parent' => true],
		// 'article' => ['parent' => 2, 'urls' => ['/blog', '/']],
		// 'product' => ['parent' => true, 'siblings' => true, 'templates' => ['category']],
	],
];
