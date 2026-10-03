<script setup>
import { computed, usePanel } from "kirbyuse"

/**
 * Pages linking to a URL: the first one & the number of others,
 * the popover lists the first pages (links in the content first)
 */
const props = defineProps({
	column: Object,
	row: Object,
	/** `{ text, total, items: [{ text, link, info }] }` */
	value: Object
})

const panel = usePanel()

// "+3" is read as "plus 3", screen readers get the number of other pages as text
const name = computed(() =>
	props.value.total > 1
		? `${props.value.text}, ${panel.t("seo.overview.links.pages.more", { count: props.value.total - 1 })}`
		: null
)
</script>

<template>
	<k-seo-popover
		:button="{
			class: 'k-seo-link-pages-cell-button',
			title: $t('seo.overview.links.pages.show'),
			'aria-label': name
		}"
		class="k-seo-link-pages-cell"
	>
		<template #button>
			<span class="k-seo-link-pages-cell-text">{{ value.text }}</span>
			<span v-if="value.total > 1" class="k-seo-link-pages-cell-more" aria-hidden="true">
				+{{ value.total - 1 }}
			</span>
		</template>

		<k-dropdown-item
			v-for="page in value.items"
			:key="page.link"
			:link="page.link"
			icon="page"
			class="k-seo-popover-item"
		>
			<span class="k-seo-popover-text">{{ page.text }}</span>
			<span class="k-seo-popover-info">{{ page.info }}</span>
		</k-dropdown-item>
		<p v-if="value.total > value.items.length" class="k-seo-link-pages-cell-rest">
			{{ $t("seo.overview.links.pages.more", { count: value.total - value.items.length }) }}
		</p>
	</k-seo-popover>
</template>

<style>
.k-seo-link-pages-cell {
	height: 100%;
}

.k-seo-link-pages-cell-button {
	display: flex;
	align-items: center;
	gap: var(--spacing-2);
	width: 100%;
	height: 100%;
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

/* the pages that aren't listed */
.k-seo-link-pages-cell-rest {
	padding: var(--spacing-1) var(--spacing-3);
	font-size: var(--text-xs);
	color: var(--color-gray-500);
}
</style>
