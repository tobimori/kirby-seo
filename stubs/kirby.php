<?php

// Kirby magic methods for Mago, never loaded. Mago can take a stub as the class declaration, so the stubs repeat the real class headers

namespace Kirby\Cms {
	use Kirby\Content\Field;
	use tobimori\Seo\Meta;

	/**
	 * @method Meta metadata(Language|null $lang = null)
	 * @method string robots(Language|null $lang = null)
	 * @method mixed schema(string $type)
	 * @method array<string, \Stringable> schemas()
	 * @method string indexUrl()
	 * @method Field metaInherit()
	 * @method Field useTitleTemplate()
	 * @method Field useOgTemplate()
	 * @method Field text()
	 */
	class Page extends ModelWithContent
	{
		use HasChildren;
		use HasFiles;
		use HasMethods;
		use HasModels;
		use HasSiblings;
		use PageActions;
		use PageSiblings;
		public static array $methods = [];
	}

	/**
	 * @method mixed schema(string $type)
	 * @method array<string, \Stringable> schemas()
	 * @method string lang()
	 * @method string canonicalFor(string $url, bool $useRootUrl = false, string|null $languageCode = null)
	 * @method Field title()
	 * @method Field description()
	 * @method Field metaDescription()
	 * @method Field ogDescription()
	 * @method Field ogSiteName()
	 */
	class Site extends ModelWithContent
	{
		use HasChildren;
		use HasFiles;
		use HasMethods;
		use SiteActions;
	}

	/**
	 * @method Field title()
	 */
	class User extends ModelWithContent
	{
		use HasFiles;
		use HasMethods;
		use HasModels;
		use HasSiblings;
		use UserActions;
		public static array $methods = [];
	}

	/**
	 * @method string extension()
	 * @method string name()
	 * @method string|false read()
	 * @method bool isResizable()
	 * @method int width()
	 * @method int height()
	 * @method Field alt()
	 */
	class File extends ModelWithContent
	{
		use FileActions;
		use FileModifications;
		use HasMethods;
		use HasSiblings;
		use \Kirby\Filesystem\IsFile;
		public static array $methods = [];
	}

	/**
	 * @method string|false read()
	 * @method int width()
	 * @method int height()
	 */
	class FileVersion
	{
		use \Kirby\Filesystem\IsFile;
	}

	/**
	 * @method \Kirby\Form\Field|\Kirby\Form\FieldClass field()
	 */
	class Api extends \Kirby\Api\Api {}
}

namespace Kirby\Filesystem {
	/**
	 * @method string|false read()
	 * @method string url()
	 * @method int width()
	 * @method int height()
	 */
	class Asset
	{
		use IsFile;
		use \Kirby\Cms\FileModifications;
		use \Kirby\Cms\HasMethods;
	}
}

namespace Kirby\Content {
	use Kirby\Cms\File;

	/**
	 * @method bool toBool(bool $default = false)
	 * @method File|null toFile()
	 * @method Content toObject()
	 * @method array split(string $separator = ',')
	 * @method Field escape(string $context = 'html')
	 */
	class Field implements \Stringable
	{
		public static array $methods = [];
		public static array $aliases = [];
		protected \Kirby\Cms\ModelWithContent|null $parent;
		protected string $key;
		public mixed $value;
	}

	/**
	 * @method Field mastodon()
	 */
	class Content {}
}

namespace Kirby\Http {
	/**
	 * @method string|null host()
	 * @method Path path()
	 * @method int|false|null port()
	 * @method Query query()
	 * @method string|null scheme()
	 * @method bool slash()
	 */
	class Uri implements \Stringable
	{
		public static Uri|null $current = null;
		protected static array $schemes = ['http', 'https', 'ftp'];
	}

	/**
	 * @method static Remote post(string $url, array $options = [])
	 */
	class Remote
	{
		public static array $defaults = [];
		public array $options = [];
	}
}

namespace Kirby\Toolkit {
	/**
	 * @method static bool email(mixed $value)
	 * @method static bool url(mixed $value)
	 */
	class V
	{
		public static array $validators = [];
	}
}
