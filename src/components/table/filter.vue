<script setup>
import { ref } from "kirbyuse"

/**
 * Filter of a table: the button shows the active filter (& resets it),
 * the dropdown lists the filters with the number of rows they match.
 * `filter` is emitted with the selected filter, an empty string for all rows
 */
defineProps({
	/** Text of the button, e.g. the active filter */
	label: String,
	/** Tooltip of the button, e.g. the full text of a long filter */
	title: String,
	/** Whether a filter is active */
	active: Boolean,
	/** Whether the active filter can be reset next to the button */
	clearable: Boolean,
	/** Tooltip of the reset button */
	clear: String,
	/** First item of the dropdown, which shows all rows: `{ text, icon, current? }` */
	all: Object,
	/** The active filter */
	current: String,
	/** `[{ type, count, text, icon, theme }]` */
	filters: Array
})

const emit = defineEmits(["filter"])

const dropdown = ref(null)
</script>

<template>
	<div class="k-seo-filter">
		<!-- the active filter & its reset belong together -->
		<k-button-group layout="collapsed">
			<k-button
				:text="label"
				:title="title"
				:theme="active ? 'info' : null"
				:dropdown="true"
				icon="checklist"
				size="xs"
				variant="filled"
				class="k-seo-filter-button"
				@click="dropdown.toggle()"
			/>
			<k-button
				v-if="clearable"
				:title="clear"
				icon="cancel-small"
				size="xs"
				variant="filled"
				theme="info"
				@click="emit('filter', '')"
			/>
		</k-button-group>

		<k-dropdown-content ref="dropdown" align-x="end">
			<k-dropdown-item
				:current="all.current ?? !current"
				:icon="all.icon"
				@click="emit('filter', '')"
			>
				{{ all.text }}
			</k-dropdown-item>
			<!-- e.g. a filter combining others -->
			<slot name="before" />
			<hr />
			<k-dropdown-item
				v-for="filter in filters"
				:key="filter.type"
				:current="current === filter.type"
				:disabled="filter.count === 0 && current !== filter.type"
				:theme="filter.theme"
				:icon="filter.icon"
				class="k-seo-filter-item"
				@click="emit('filter', filter.type)"
			>
				{{ filter.text }}
				<span class="k-seo-filter-count">{{ filter.count }}</span>
			</k-dropdown-item>
			<slot name="after" />
		</k-dropdown-content>
	</div>
</template>

<style>
/* the buttons are part of the toolbar, the dropdowns are positioned at them */
.k-seo-filter {
	display: contents;
}

.k-seo-filter-button .k-button-text {
	max-width: 24ch;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* the number of rows, aligned to the end of the dropdown item */
.k-seo-filter-item .k-button-text {
	display: flex;
	flex-grow: 1;
	gap: var(--spacing-6);
}

.k-seo-filter-count {
	margin-inline-start: auto;
	font-variant-numeric: tabular-nums;
}
</style>
