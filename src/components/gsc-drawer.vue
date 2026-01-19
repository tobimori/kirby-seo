<script setup>
import { computed } from "vue"

const props = defineProps({
	columns: Object,
	rows: Array,
	parent: String,
	metric: String,
	sortAsc: Boolean,
	total: Number,
	page: Number,
	limit: Number,
	// drawer props
	visible: Boolean,
	current: Boolean,
	icon: String,
	title: String,
	breadcrumb: Array,
	tabs: Object,
	tab: String,
	options: Array
})

const emit = defineEmits(["cancel", "crumb", "submit", "tab"])

const pagination = computed(() => ({
	page: props.page,
	limit: props.limit,
	total: props.total,
	details: true
}))

const reload = (overrides = {}) => {
	window.panel.drawer.refresh({
		query: {
			metric: overrides.metric ?? props.metric,
			asc: (overrides.asc ?? props.sortAsc) ? 1 : 0,
			page: overrides.page ?? props.page
		}
	})
}

const handlePaginate = (e) => reload({ page: e.page })

const handleHeader = ({ columnIndex }) => {
	const asc =
		props.metric === columnIndex
			? !props.sortAsc
			: columnIndex === "position" || columnIndex === "query"

	reload({ metric: columnIndex, asc, page: 1 })
}
</script>

<template>
	<k-drawer
		v-bind="$props"
		class="k-gsc-drawer"
		@cancel="emit('cancel')"
		@crumb="emit('crumb', $event)"
		@submit="emit('cancel')"
		@tab="emit('tab', $event)"
	>
		<k-box v-if="!props.rows?.length" theme="empty">
			<k-text>{{ $t("seo.sections.searchConsole.noData") }}</k-text>
		</k-box>
		<k-table
			v-else
			:columns="props.columns"
			:rows="props.rows"
			:index="false"
			:pagination="pagination"
			@header="handleHeader"
			@paginate="handlePaginate"
		>
			<template #header="{ columnIndex, label }">
				<span>
					{{ label }}
					<k-icon
						v-if="props.metric === columnIndex"
						:type="props.sortAsc ? 'angle-up' : 'angle-down'"
					/>
				</span>
			</template>
		</k-table>
	</k-drawer>
</template>

<style>
.k-gsc-drawer {
	.k-table th {
		cursor: pointer;

		&:hover {
			background: light-dark(var(--color-gray-200), var(--color-gray-700));
		}

		> span {
			display: flex;
			align-items: center;
			justify-content: space-between;
			width: 100%;
		}
	}
}
</style>
