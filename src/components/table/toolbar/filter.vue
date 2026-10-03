<script setup>
import { ref } from "kirbyuse"

defineProps({
	label: String,
	title: String,
	active: Boolean,
	/** Whether the active filter can be reset next to the button */
	clearable: Boolean,
	/** Tooltip of the reset button */
	clear: String,
	/** First item of the dropdown, which shows all rows: `{ text, icon, current? }` */
	all: Object,
	current: String,
	/** `[{ type, count, text, icon, theme }]` */
	filters: Array
})

const emit = defineEmits(["filter"])

const dropdown = ref(null)
</script>

<template>
	<div class="k-seo-filter">
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
		</k-dropdown-content>
	</div>
</template>

<style>
.k-seo-filter {
	display: contents;
}

.k-seo-filter-button .k-button-text {
	max-width: 24ch;
	overflow: hidden;
	text-overflow: ellipsis;
}

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
