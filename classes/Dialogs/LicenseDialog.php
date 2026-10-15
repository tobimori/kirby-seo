<?php

namespace tobimori\Seo\Dialogs;

use Kirby\Exception\InvalidArgumentException;
use Kirby\Panel\Controller\DialogController;
use Kirby\Panel\Field;
use Kirby\Panel\Ui\Dialog\FormDialog;
use Kirby\Toolkit\HtmlString;
use Kirby\Toolkit\V;
use tobimori\Seo\License;

/**
 * Activates a license for the current domain
 */
class LicenseDialog extends DialogController
{
	public function load(): FormDialog
	{
		$isLocal = $this->kirby->system()->isLocal();

		return new FormDialog(
			fields: [
				'domain' => [
					'label' => $this->i18n('seo.license.activate.label'),
					'type' => 'info',
					'theme' => $isLocal ? 'warning' : 'info',
					'text' => $this->i18nHtml(
						'seo.license.activate.' . ($isLocal ? 'local' : 'domain'),
						['domain' => License::normalizeUrl($this->kirby->system()->indexUrl())]
					)
				],
				'email' => Field::email(['required' => true]),
				'license' => [
					'label' => $this->i18n('seo.license.key.label'),
					'type' => 'text',
					'required' => true,
					'counter' => false,
					'help' => new HtmlString($this->i18n('seo.license.key.help'))
				]
			],
			submitButton: [
				'icon' => 'key',
				'text' => $this->i18n('seo.license.activate'),
				'theme' => 'love'
			]
		);
	}

	public function submit(): array
	{
		$email = (string)$this->request->get('email');

		if (!V::email($email)) {
			throw new InvalidArgumentException($this->i18n('seo.license.error.email'));
		}

		License::activate(
			email: $email,
			license: trim((string)$this->request->get('license', ''))
		);

		return [
			'message' => $this->i18n('seo.license.activated')
		];
	}
}
