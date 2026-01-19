<script setup>
import { ref, watch, onMounted, usePanel, useApi } from "kirbyuse"
import { section } from "kirbyuse/props"

const MESSAGES = {
	NO_CREDENTIALS: "seo.sections.searchConsole.noCredentials",
	NOT_CONNECTED: "seo.sections.searchConsole.notConnected",
	SELECT_PROPERTY: "seo.sections.searchConsole.selectProperty"
}

const props = defineProps(section)
const panel = usePanel()
const api = useApi()

const status = ref(null)
const data = ref([])
const displayMetric = ref("clicks")
const dropdown = ref(null)

const metric = ref("clicks")
const metricOptions = [
	{ value: "clicks", text: panel.t("seo.sections.searchConsole.clicks") },
	{ value: "impressions", text: panel.t("seo.sections.searchConsole.impressions") },
	{ value: "ctr", text: panel.t("seo.sections.searchConsole.ctr") },
	{ value: "position", text: panel.t("seo.sections.searchConsole.position") }
]

const handleLoad = async () => {
	const response = await api.get(`${props.parent}/sections/${props.name}`, {
		metric: metric.value,
		limit: 10
	})

	status.value = response.status
	data.value = response.data ?? []
	displayMetric.value = metric.value
}

watch(metric, () => handleLoad())

onMounted(() => {
	handleLoad()

	panel.events.on("gsc.propertySelected", () => {
		handleLoad()
	})
})

const getBarWidth = (row) => {
	if (!data.value.length) return 0

	const m = displayMetric.value
	const values = data.value.map((r) => r[m])
	const min = Math.min(...values)
	const max = Math.max(...values)
	const value = row[m]

	if (max === min) return 100

	let ratio
	if (m === "position") {
		ratio = (max - value) / (max - min)
	} else {
		ratio = (value - min) / (max - min)
	}

	return 10 + ratio * 90
}

const formatValue = (row) => {
	const m = displayMetric.value
	const value = row[m]
	const locale = panel.translation.code

	if (m === "ctr") {
		return new Intl.NumberFormat(locale, {
			style: "percent",
			minimumFractionDigits: 1,
			maximumFractionDigits: 1
		}).format(value)
	}
	if (m === "position") {
		return new Intl.NumberFormat(locale, {
			minimumFractionDigits: 1,
			maximumFractionDigits: 1
		}).format(value)
	}
	return new Intl.NumberFormat(locale).format(value)
}

const handleConnect = () => {
	const returnUrl = encodeURIComponent(window.location.href)
	window.location.href = `/__seo/gsc/auth?return=${returnUrl}`
}

const handleSelectProperty = () => panel.dialog.open("seo/gsc/select-property")
</script>

<template>
	<k-section v-if="status" class="k-search-console-section">
		<div class="k-field-header k-seo-preview__label k-label k-field-label">
			<k-icon type="google" />
			<span class="k-label-text">
				{{ label || "Google Search Console" }}
			</span>
			<k-button-group
				v-if="status === 'CONNECTED'"
				class="k-search-console__options"
				layout="collapsed"
			>
				<k-button
					size="xs"
					variant="filled"
					icon="table"
					@click="panel.drawer.open(`seo/gsc/data/${props.parent}`, { query: { metric } })"
				>
					{{ $t("seo.sections.searchConsole.showMore") }}
				</k-button>
				<k-button icon="dots" size="xs" variant="filled" @click="dropdown.toggle()" />
				<k-dropdown-content ref="dropdown" align-x="end">
					<k-dropdown-item icon="list-bullet" @click="handleSelectProperty">
						{{ $t("seo.sections.searchConsole.selectPropertyButton") }}
					</k-dropdown-item>
					<k-dropdown-item icon="refresh" @click="handleConnect">
						{{ $t("seo.sections.searchConsole.reconnect") }}
					</k-dropdown-item>
				</k-dropdown-content>
			</k-button-group>
		</div>

		<k-box
			v-if="status !== 'CONNECTED'"
			align="center"
			theme="empty"
			class="k-search-console-empty"
		>
			<k-text>{{ $t(MESSAGES[status]) }}</k-text>
			<k-button-group>
				<k-button
					v-if="status === 'NO_CREDENTIALS'"
					size="sm"
					variant="filled"
					icon="page"
					link="https://plugins.andkindness.com/seo/docs/get-started/feature-overview"
					target="_blank"
				>
					{{ $t("seo.sections.searchConsole.docs") }}
				</k-button>
				<k-button
					v-if="status === 'NOT_CONNECTED'"
					size="sm"
					variant="filled"
					icon="google"
					@click="handleConnect"
				>
					{{ $t("seo.sections.searchConsole.connect") }}
				</k-button>
				<k-button
					v-if="status === 'SELECT_PROPERTY'"
					size="sm"
					variant="filled"
					theme="positive"
					icon="list-bullet"
					@click="handleSelectProperty"
				>
					{{ $t("seo.sections.searchConsole.selectPropertyButton") }}
				</k-button>
				<k-button
					v-if="status === 'SELECT_PROPERTY'"
					size="sm"
					variant="filled"
					icon="refresh"
					@click="handleConnect"
				>
					{{ $t("seo.sections.searchConsole.reconnect") }}
				</k-button>
			</k-button-group>
		</k-box>

		<template v-else>
			<k-select-field
				v-model="metric"
				type="select"
				name="gsc-metric"
				:before="$t('seo.sections.searchConsole.sortBy')"
				:options="metricOptions"
				:required="true"
				:empty="false"
			/>

			<div class="k-search-console__inner">
				<k-box v-if="!data.length" theme="empty" class="k-search-console-empty">
					<k-text>{{ $t("seo.sections.searchConsole.noData") }}</k-text>
				</k-box>

				<div v-else class="k-search-console__list">
					<div v-for="row in data" :key="row.keys[0]" class="k-search-console__row">
						<div class="k-search-console__bar" :style="{ width: getBarWidth(row) + '%' }" />
						<span class="k-search-console__query">{{ row.keys[0] }}</span>
						<span class="k-search-console__value">{{ formatValue(row) }}</span>
					</div>
				</div>
			</div>

			<a
				class="k-search-console__link"
				href="https://search.google.com/search-console"
				target="_blank"
				rel="noopener noreferrer"
			>
				{{ $t("seo.sections.searchConsole.openInGsc") }}
				<k-icon type="open" />
			</a>
		</template>
	</k-section>
</template>

<style>
.k-search-console-empty {
	flex-direction: column;
	text-align: center;
	padding: var(--spacing-6) !important;
	gap: var(--spacing-3);
}

.k-search-console__inner {
	margin-top: var(--spacing-2);
}

.k-search-console__list {
	--table-color-back: light-dark(var(--color-white), var(--color-gray-850));
	--table-color-border: light-dark(rgba(0, 0, 0, 0.08), rgba(0, 0, 0, 0.375));

	background: var(--table-color-back);
	border-radius: var(--rounded);
	overflow: hidden;
	box-shadow: var(--shadow);
}

.k-search-console__row {
	position: relative;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: var(--spacing-2) var(--spacing-3);
	font-size: var(--text-sm);
	border-bottom: 1px solid var(--table-color-border);

	&:last-child {
		border-bottom: none;
	}

	> .k-search-console__bar {
		position: absolute;
		inset: 0;
		right: auto;
		background: light-dark(var(--color-blue-200), var(--color-blue-300));
		opacity: light-dark(0.75, 0.2);
		pointer-events: none;
	}

	> .k-search-console__query,
	> .k-search-console__value {
		position: relative;
	}

	> .k-search-console__query {
		flex: 1;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		padding-right: var(--spacing-3);
	}

	> .k-search-console__value {
		font-variant-numeric: tabular-nums;
		color: var(--color-text-dimmed);
	}
}

.k-search-console__options {
	margin-left: auto;
}

.k-search-console__link {
	display: flex;
	margin-top: var(--spacing-4);
	margin-left: auto;
	width: max-content;
	font-size: var(--text-sm);
	color: var(--color-text-dimmed);
	line-height: var(--spacing-5);

	&:hover {
		text-decoration: underline;
		color: var(--theme-color-text);
	}

	> .k-icon {
		margin-left: var(--spacing-2);
	}
}
</style>
