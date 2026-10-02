<script setup>
import { usePanel } from "kirbyuse"

/**
 * Pages linking to a URL: the first one & the number of others,
 * opens a drawer with all of them
 */
defineProps({
	column: Object,
	row: Object,
	/** `{ text, total }` */
	value: Object
})

const panel = usePanel()
</script>

<template>
	<button
		:title="$t('seo.overview.links.pages.show')"
		type="button"
		class="k-seo-link-pages-cell"
		@click.stop="panel.drawer.open('seo/links/pages', { query: { url: row.id } })"
	>
		<span class="k-seo-link-pages-cell-text">{{ value.text }}</span>
		<span v-if="value.total > 1" class="k-seo-link-pages-cell-more">+{{ value.total - 1 }}</span>
	</button>
</template>

<style>
.k-seo-link-pages-cell {
	display: flex;
	align-items: center;
	gap: var(--spacing-2);
	width: 100%;
	min-height: var(--table-row-height);
	padding-inline: var(--table-cell-padding);
	text-align: start;
	cursor: pointer;
	outline-offset: -2px;

	&:hover .k-seo-link-pages-cell-text {
		text-decoration: underline;
	}

	&:focus-visible {
		outline: var(--outline);
	}
}

.k-seo-link-pages-cell-text {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	color: var(--link-color);
}

.k-seo-link-pages-cell-more {
	flex-shrink: 0;
	font-size: var(--text-xs);
	font-variant-numeric: tabular-nums;
	color: var(--color-text-dimmed);
}
</style>
