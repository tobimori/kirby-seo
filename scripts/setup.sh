#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

playground=.playground

pnpm install --frozen-lockfile

# The repository ships production autoload files in vendor/, so development
# dependencies (Kirby, Mago, schema-org) go to a separate, ignored folder
COMPOSER_VENDOR_DIR=.repos/vendor composer install --no-interaction

kirby_version="$(php -r '
	$lock = json_decode(file_get_contents("composer.lock"), true);
	foreach (array_merge($lock["packages"], $lock["packages-dev"]) as $package) {
		if ($package["name"] === "getkirby/cms") echo $package["version"];
	}
')"

# Kirby Starterkit at the same Kirby version as the plugin's lock file
if [[ ! -d "$playground" ]]; then
	git -c advice.detachedHead=false clone --quiet --depth 1 --branch "$kirby_version" https://github.com/getkirby/starterkit.git "$playground"
	rm -rf "$playground/.git"
fi

# Optional packages that enable Schema.org and Markdown support. Kirby pins
# exact dependency versions, so a security advisory must not block the install
if php -r '
	$require = json_decode(file_get_contents($argv[1]), true)["require"];
	exit(($require["getkirby/cms"] ?? null) === $argv[2] && isset($require["spatie/schema-org"], $require["league/html-to-markdown"]) ? 0 : 1);
' "$playground/composer.json" "$kirby_version"; then
	composer install --working-dir="$playground" --no-interaction --no-security-blocking
else
	composer require --working-dir="$playground" --no-interaction --no-security-blocking --with-all-dependencies \
		"getkirby/cms:$kirby_version" "spatie/schema-org:^5.0" "league/html-to-markdown:^5.1"
fi

mkdir -p "$playground/site/plugins"
ln -sfn ../../.. "$playground/site/plugins/seo"

if ! grep -q 'scripts/setup.sh' "$playground/site/config/config.php"; then
	cat > "$playground/site/config/config.php" <<'PHP'
<?php

// Created by scripts/setup.sh
return [
	'debug' => true,
	// Portless forwards the public host, Kirby reads it from the proxy headers
	'url' => '*',
	'panel' => ['install' => false]
];
PHP
fi

# SEO tab in all blueprints with a layout, and SEO snippets in the templates
php -r '
	require $argv[1] . "/kirby/bootstrap.php";

	use Kirby\Data\Yaml;

	$blueprints = $argv[1] . "/site/blueprints";
	$files = ["site" => "$blueprints/site.yml"];
	foreach (glob("$blueprints/pages/*.yml") as $file) {
		$files[] = $file;
	}

	foreach ($files as $type => $file) {
		$blueprint = Yaml::read($file);
		$layout = array_intersect_key($blueprint, array_flip(["columns", "sections", "fields"]));

		if (isset($blueprint["tabs"]["seo"]) || (!isset($blueprint["tabs"]) && $layout === [])) {
			continue;
		}

		if (!isset($blueprint["tabs"])) {
			// root fields referenced by name from columns or sections stay at the root
			if (isset($layout["columns"]) || isset($layout["sections"])) {
				unset($layout["fields"]);
			}

			$blueprint = array_diff_key($blueprint, $layout);
			$blueprint["tabs"] = ["content" => ["label" => "Content", ...$layout]];
		}

		$blueprint["tabs"]["seo"] = $type === "site" ? "seo/site" : "seo/page";
		Yaml::write($file, $blueprint);
	}

	$snippets = $argv[1] . "/site/snippets";
	$header = file_get_contents("$snippets/header.php");
	$header = preg_replace("#<title>.*</title>#", "<?php snippet(\"seo/head\") ?>", $header);
	file_put_contents("$snippets/header.php", $header);

	$footer = file_get_contents("$snippets/footer.php");
	if (!str_contains($footer, "seo/schemas")) {
		file_put_contents("$snippets/footer.php", str_replace("</body>", "<?php snippet(\"seo/schemas\") ?>\n</body>", $footer));
	}
' "$playground"

# Panel account for people and agents, the credentials stay in the playground
if [[ ! -f "$playground/.panel-credentials" ]]; then
	php -r '
		require $argv[1] . "/kirby/bootstrap.php";

		$kirby = new Kirby\Cms\App(["roots" => ["index" => realpath($argv[1])]]);
		$email = "admin@seo.test";
		$password = bin2hex(random_bytes(12));

		$kirby->impersonate("kirby", function () use ($kirby, $email, $password) {
			$kirby->user($email)?->delete();
			Kirby\Cms\User::create([
				"email" => $email,
				"name" => "Admin",
				"role" => "admin",
				"password" => $password
			]);
		});

		file_put_contents($argv[1] . "/.panel-credentials", "PANEL_EMAIL=$email\nPANEL_PASSWORD=$password\n");
	' "$playground"
fi

echo "Playground ready. Start it with: pnpm playground (http://localhost:8000) or pnpm playground:portless"
cat "$playground/.panel-credentials"
