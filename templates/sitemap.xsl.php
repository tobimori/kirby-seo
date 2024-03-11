<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<xsl:stylesheet version="2.0" xmlns:html="http://www.w3.org/TR/REC-html40" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes" />
	<xsl:template match="/">
		<html>

		<head>
			<title><?= $page->metadata()->title()->escape() ?></title>
			<style>
				/* Document styles */
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
					font-size: 0.875rem;
					color: #000;
					background-color: #f0f0f0;
					-webkit-font-smoothing: antialiased;
					-moz-osx-font-smoothing: grayscale;
					display: flex;
					align-items: center;
					justify-content: center;
					padding: 2rem;
					flex-direction: column;
				}

				.k-sitemap-body {
					max-width: 64rem;
					width: 100%;
				}

				/* Font styles */
				h1 {
					font-size: 1.5rem;
					font-weight: 400;
					margin-top: 0;
					margin-bottom: 0.5rem;
				}

				.k-sitemap-body>p {
					color: rgb(115, 115, 115);
					margin-bottom: 2rem;
				}

				a {
					color: rgb(29, 84, 139);
				}

				.k-sitemap-credits {
					font-size: 0.75rem;
					margin-top: 1.5rem;
				}

				/* Table styles */
				table {
					font-size: 0.875rem;
					border-collapse: collapse;
					width: 100%;
					border-radius: 0.25rem;
					overflow: hidden;
					box-shadow: 0 1px 3px 0 #0000000d, 0 1px 2px 0 #00000006;
				}

				.k-sitemap-table-empty {
					box-shadow: none;
					border: rgb(204, 204, 204) 1px dashed;
					height: 2.25rem;
					padding: 0 0.75rem;
					border-radius: 0.25rem;
					color: rgb(104, 104, 104);
					display: flex;
					align-items: center;
				}

				th {
					text-align: left;
					font-family: "SFMono-Regular", Consolas, Liberation Mono, Menlo, Courier, monospace;
					color: rgb(115 115 115);
					background: rgb(250 250 250);
					font-weight: 400;
					font-size: 0.75rem;
				}

				th:not(:last-child),
				td:not(:last-child) {
					border-inline-end: 1px solid rgb(240 240 240);
				}

				th,
				tr:not(:last-child) td {
					border-block-end: 1px solid rgb(240 240 240);
				}

				th,
				td {
					height: 2.25rem;
					padding: 0 0.75rem;
				}

				td {
					background: #fff;
				}

				@media only screen and (max-width: 48rem) {
					.k-sitemap-secondary {
						display: none;
					}
				}
			</style>
		</head>

		<body>
			<div class="k-sitemap-body">
				<h1><?= $page->title() ?></h1>
				<p><?= t('sitemap-description') ?></p>

				<xsl:if test="count(sitemap:sitemapindex/sitemap:sitemap) > 0">
					<table>
						<thead>
							<tr>
								<th width="66%"><?= t('sitemap') ?></th>
								<th width="33%" class="k-sitemap-secondary"><?= t('sitemap-last-updated') ?></th>
							</tr>
						</thead>
						<tbody>
							<xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
								<tr>
									<td>
										<xsl:variable name="link">
											<xsl:value-of select="sitemap:loc" />
										</xsl:variable>
										<a href="{$link}"><xsl:value-of select="sitemap:loc" /></a>
									</td>
									<td class="k-sitemap-secondary">
										<xsl:value-of select="concat(substring(sitemap:lastmod,0,11),concat(' ', substring(sitemap:lastmod,12,5)))" />
									</td>
								</tr>
							</xsl:for-each>
						</tbody>
					</table>
				</xsl:if>

				<xsl:if test="count(sitemap:urlset/sitemap:url) > 0">
					<table>
						<thead>
							<tr>
								<th width="55%"><?= t('sitemap-url') ?></th>
								<th width="10%" class="k-sitemap-secondary"><?= t('sitemap-priority') ?></th>
								<th width="15%" class="k-sitemap-secondary"><?= t('sitemap-changefreq') ?></th>
								<th width="20%" class="k-sitemap-secondary"><?= t('sitemap-last-updated') ?></th>
							</tr>
						</thead>
						<tbody>
							<xsl:for-each select="sitemap:urlset/sitemap:url">
								<tr>
									<td>
										<xsl:variable name="link">
											<xsl:value-of select="sitemap:loc" />
										</xsl:variable>
										<a target="_blank" rel="noopener nofollow" href="{$link}"><xsl:value-of select="sitemap:loc" /></a>
									</td>
									<td class="k-sitemap-secondary">
										<xsl:value-of select="sitemap:priority" />
									</td>
									<td class="k-sitemap-secondary">
										<xsl:value-of select="sitemap:changefreq" />
									</td>
									<td class="k-sitemap-secondary">
										<xsl:value-of select="concat(substring(sitemap:lastmod,0,11),concat(' ', substring(sitemap:lastmod,12,5)))" />
									</td>
								</tr>
							</xsl:for-each>
						</tbody>
					</table>
				</xsl:if>

				<xsl:if test="(sitemap:urlset and count(sitemap:urlset/sitemap:url) = 0) or (sitemap:sitemapindex and count(sitemap:sitemapindex/sitemap:sitemap) = 0)">
					<div class="k-sitemap-table-empty">
						<?= t('sitemap-no-entries') ?>
					</div>
				</xsl:if>

				<p class="k-sitemap-credits">
					<a target="_blank" rel="noopener nofollow" href="https://getkirby.com/plugins/tobimori/seo">Kirby SEO</a>
					<xsl:if test="sitemap:urlset">
						v<xsl:value-of select="sitemap:urlset/@seo-version" />
					</xsl:if>
					<xsl:if test="sitemap:sitemapindex">
						v<xsl:value-of select="sitemap:sitemapindex/@seo-version" />
					</xsl:if>
					<?= t('sitemap-by') ?> <a target="_blank" rel="noopener nofollow" href="https://moeritz.io/">Tobias Möritz</a>
				</p>
			</div>
		</body>

		</html>
	</xsl:template>
</xsl:stylesheet>