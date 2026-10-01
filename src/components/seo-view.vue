<script setup>
import { computed, usePanel } from "kirbyuse"

import { SEVERITY_ICONS } from "../utils/checks.js"

/**
 * Layout of the SEO area, like Retour's view: header, stats & tabs,
 * with buttons for the current tab next to the tabs
 */
const props = defineProps({
	/** Header buttons, e.g. the languages dropdown */
	buttons: {
		type: Array,
		default: () => []
	},
	/** `{ checked, title, description, images: { total, missing, ai } }` */
	stats: Object,
	tab: String,
	tabs: {
		type: Array,
		default: () => []
	}
})

const emit = defineEmits(["filter"])

const panel = usePanel()

const SEVERITIES = ["ok", "notice", "negative"]

/**
 * Stat card with the distribution of values by severity, shown as bar & legend
 */
const toCard = ({ key, icon, distribution, legend, click, highlight = ["notice", "negative"] }) => {
	const total = SEVERITIES.reduce((sum, severity) => sum + distribution[severity], 0)

	return {
		key,
		icon,
		label: panel.t(`seo.overview.stats.${key}`),
		total,
		highlight: highlight.map((severity) => ({ severity, count: distribution[severity] })),
		segments: SEVERITIES.filter((severity) => distribution[severity] > 0).map((severity) => ({
			severity,
			count: distribution[severity],
			label: panel.t(`seo.overview.stats.${legend}.${severity}`)
		})),
		// filtering only makes sense if there's something to fix
		click: click && total - distribution.ok > 0 ? click : null
	}
}

const cards = computed(() => {
	if (!props.stats) {
		return []
	}

	const { total, missing, ai } = props.stats.images

	return [
		toCard({
			key: "title",
			icon: "title",
			distribution: props.stats.title,
			legend: "pages",
			click: () => emit("filter", "title")
		}),
		toCard({
			key: "description",
			icon: "text",
			distribution: props.stats.description,
			legend: "pages",
			click: () => emit("filter", "description")
		}),
		toCard({
			key: "images",
			icon: "image",
			distribution: { ok: total - missing - ai, notice: ai, negative: missing },
			legend: "images",
			highlight: ["negative"]
		})
	]
})
</script>

<template>
	<k-panel-inside class="k-seo-view">
		<k-header>
			{{ $t("seo.overview.title") }}

			<template #buttons>
				<k-view-buttons :buttons="buttons" />
				<slot name="buttons" />
			</template>
		</k-header>

		<!-- Kirby's stat cards, with the distribution as bar & legend -->
		<dl v-if="cards.length" class="k-stats k-seo-view-stats" data-size="large">
			<component
				:is="card.click ? 'button' : 'div'"
				v-for="card in cards"
				:key="card.key"
				:type="card.click ? 'button' : null"
				class="k-stat k-seo-stat"
				@click="card.click?.()"
			>
				<dt class="k-stat-label"><k-icon :type="card.icon" />{{ card.label }}</dt>
				<dd v-if="card.total" class="k-stat-value k-seo-stat-value" aria-hidden="true">
					<span
						v-for="value in card.highlight"
						:key="value.severity"
						:data-severity="value.severity"
					>
						{{ value.count }}
					</span>
				</dd>
				<dd v-if="card.total" class="k-seo-stat-bar" aria-hidden="true">
					<span
						v-for="segment in card.segments"
						:key="segment.severity"
						:data-severity="segment.severity"
						:style="{ flexGrow: segment.count }"
					/>
				</dd>
				<dd class="k-stat-info k-seo-stat-legend">
					<span
						v-for="segment in card.segments"
						:key="segment.severity"
						:data-severity="segment.severity"
					>
						<k-icon :type="SEVERITY_ICONS[segment.severity]" />{{ segment.count }}
						{{ segment.label }}
					</span>
					<span v-if="!card.total">{{ $t(`seo.overview.stats.${card.key}.none`) }}</span>
				</dd>
			</component>
		</dl>

		<div class="k-seo-view-tabs">
			<k-tabs :tab="tab" :tabs="tabs" />
			<k-button-group>
				<slot name="toolbar" />
			</k-button-group>
		</div>

		<slot />
	</k-panel-inside>
</template>

<style>
/* stats right below the header (without divider), its title already has a margin below */
.k-seo-view .k-header:has(+ .k-seo-view-stats) {
	margin-bottom: 0;
	border-bottom: 0;
}

.k-seo-view-stats {
	margin-bottom: var(--spacing-4);
}

.k-seo-stat {
	/* same colors as the icons of the checks, a bit darker as background of the bar */
	--severity-ok: var(--color-green-500);
	--severity-ok-back: var(--color-green-600);
	--severity-notice: var(--color-orange-500);
	--severity-notice-back: var(--color-orange-600);
	--severity-negative: var(--color-red-500);
	--severity-negative-back: var(--color-red-600);
	text-align: start;
}

button.k-seo-stat {
	cursor: pointer;

	&:hover {
		background: var(--stat-color-hover-back);
	}

	&:focus-visible {
		outline: var(--outline);
	}
}

/* label, value, bar & legend (Kirby shows the value first) */
.k-seo-stat .k-stat-label {
	order: 1;
	margin-bottom: var(--spacing-1);
}

.k-seo-stat-value.k-stat-value {
	order: 2;
	display: flex;
	align-items: baseline;
	gap: var(--spacing-2);
	margin-bottom: 0;
	font-variant-numeric: tabular-nums;

	[data-severity] {
		color: light-dark(var(--color-back), var(--color));
	}
}

.k-seo-stat-value span + span::before {
	content: "/";
	margin-inline-end: var(--spacing-2);
	color: var(--color-text-dimmed);
}

.k-seo-stat-bar {
	order: 3;
	display: flex;
	gap: 2px;
	height: 0.375rem;
	margin-block: var(--spacing-2);
	overflow: hidden;
	border-radius: var(--rounded-xs);

	span {
		flex-basis: 0;
		min-width: 2px;
	}
}

.k-seo-stat-legend {
	order: 4;
	display: flex;
	flex-wrap: wrap;
	column-gap: var(--spacing-3);

	span {
		display: inline-flex;
		align-items: center;
		gap: var(--spacing-1);
	}

	.k-icon {
		--icon-size: 0.875rem;
		color: var(--color);
	}
}

.k-seo-stat [data-severity="ok"] {
	--color: var(--severity-ok);
	--color-back: var(--severity-ok-back);
}

.k-seo-stat [data-severity="notice"] {
	--color: var(--severity-notice);
	--color-back: var(--severity-notice-back);
}

.k-seo-stat [data-severity="negative"] {
	--color: var(--severity-negative);
	--color-back: var(--severity-negative-back);
}

.k-seo-stat-bar span {
	background:
		linear-gradient(rgb(255 255 255 / 20%) 0%, rgb(255 255 255 / 0%) 100%), var(--color-back);
}

/* same as Retour: tabs & the buttons of the current tab in one row */
.k-seo-view-tabs {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	align-items: center;
	gap: var(--spacing-3);
	margin-bottom: var(--spacing-4);

	.k-tabs {
		flex-grow: 1;
		margin-bottom: 0;
	}

	> .k-button-group {
		flex-wrap: nowrap;
	}
}
</style>
