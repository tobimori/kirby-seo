<?php

namespace tobimori\Seo\Buttons;

use Kirby\Cms\Page;
use Kirby\Panel\Ui\Buttons\ViewButton;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;

class RobotsViewButton extends ViewButton
{
	public const THEMES = [
		'index' => 'positive-icon',
		'any' => 'notice-icon',
		'noindex' => 'negative-icon',
	];

	public function __construct(Page $model)
	{
		['icon' => $icon, 'text' => $text, 'theme' => $theme] = static::indicator($model->robots());

		parent::__construct(
			model: $model,
			icon: $icon,
			text: $text,
			theme: $theme,
			link: $model->panel()->url() . '?tab=seo',
			responsive: true
		);
	}

	/**
	 * @return array{state: string, icon: string, text: string, theme: string}
	 */
	public static function indicator(string $robots): array
	{
		$state = match (true) {
			Str::contains($robots, 'noindex') => 'noindex',
			Str::contains($robots, 'no') => 'any',
			default => 'index',
		};

		return [
			'state' => $state,
			'icon' => $state === 'index' ? 'robots' : 'robots-off',
			'text' => I18n::translate("seo.fields.robots.indicator.{$state}"),
			'theme' => self::THEMES[$state],
		];
	}
}
