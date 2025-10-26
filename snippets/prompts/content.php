<?php

use Kirby\Toolkit\Str;

/** @var \Kirby\Cms\Page $page
 ** @var \Kirby\Cms\Site $site */

$contentHtml = $page->render();

if ($contentHtml !== '' && class_exists('DOMDocument')) {
	$dom = new \DOMDocument('1.0', 'UTF-8');
	$previousLibxmlState = libxml_use_internal_errors(true);

	$encoded = mb_encode_numericentity($contentHtml, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

	$loaded = $dom->loadHTML('<?xml encoding="UTF-8"?>' . $encoded, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

	libxml_clear_errors();
	libxml_use_internal_errors($previousLibxmlState);

	if ($loaded !== false) {
		foreach (
			[
				'script',
				'style',
				'noscript',
				'template',
				'svg',
				'canvas',
				'iframe',
				'video',
				'audio',
				'object',
				'embed',
				'source',
				'track',
				'nav',
				'footer',
				'aside',
				'form',
				'button',
				'input',
				'select',
				'textarea',
				'label',
				'menu',
				'header',
			] as $tag
		) {
			$nodes = $dom->getElementsByTagName($tag);
			for ($i = $nodes->length - 1; $i >= 0; $i--) {
				$node = $nodes->item($i);
				if ($node !== null && $node->parentNode !== null) {
					$node->parentNode->removeChild($node);
				}
			}
		}

		$xpath = new \DOMXPath($dom);
		foreach (
			[
				'navigation',
				'banner',
				'contentinfo',
				'complementary',
				'search',
				'menu',
				'menubar',
				'toolbar',
			] as $role
		) {
			$nodes = $xpath->query("//*[@role='{$role}']");
			if ($nodes === false) {
				continue;
			}

			foreach ($nodes as $node) {
				if ($node->parentNode !== null) {
					$node->parentNode->removeChild($node);
				}
			}
		}

		$body = $dom->getElementsByTagName('body')->item(0) ?? $dom->documentElement;
		if ($body instanceof \DOMNode) {
			$innerHtml = '';
			foreach ($body->childNodes as $child) {
				$innerHtml .= $dom->saveHTML($child);
			}

			if ($innerHtml !== '') {
				$contentHtml = $innerHtml;
			}
		}
	}
}

$blockClosingPattern = 'p|div|section|article|main|aside|header|footer|li|ul|ol|dl|blockquote|pre|figure|figcaption|h[1-6]|table|thead|tbody|tfoot|tr|td|th|dd|dt';
$contentHtml = preg_replace('~<(?:br|hr)\b[^>]*?>~i', "\n", $contentHtml);
$contentHtml = preg_replace('~</(?:' . $blockClosingPattern . ')>~i', "\n", $contentHtml);

$text = Str::unhtml($contentHtml);
$text = Str::replace($text, "\r", "\n");
$text = preg_replace("/[ \t\x{00A0}\x{202F}\x{2007}\x{2060}]+/u", ' ', $text);
$text = preg_replace("/ *\n+ */", "\n", $text);
$text = preg_replace("/\n{3,}/", "\n\n", $text);

$content = trim($text);

?>

<content>
	<?= htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
</content>
