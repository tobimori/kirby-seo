<script setup>
import { computed, usePanel } from "kirbyuse"

import { SEVERITY_ICONS } from "../../utils/checks.js"

const props = defineProps({
	column: Object,
	row: Object,
	/** `{ issues: [...] }` or `{ skipped: "draft" | "untranslated" | "noindex" }` */
	value: Object
})

const panel = usePanel()

const label = (issue) =>
	panel.t(
		issue.variant
			? `seo.overview.checks.${issue.type}.${issue.variant}`
			: `seo.overview.checks.${issue.type}`
	)

// short info, shown next to the issue like the codes in Kirby's languages dropdown
const info = (issue) => {
	if (issue.group) {
		return panel.t("seo.overview.checks.duplicate.show", { count: issue.count })
	}

	if (issue.variant) {
		return panel.t(`seo.overview.checks.length.info.${issue.variant}`, issue)
	}

	if (issue.source) {
		return panel.t(`seo.overview.sourceLabel.${issue.source}`)
	}

	return null
}

const issues = computed(() =>
	(props.value.issues ?? []).map((issue) => ({
		...issue,
		text: label(issue),
		info: info(issue)
	}))
)

// the most severe issue decides the indicator
const severity = computed(() =>
	issues.value.some((issue) => issue.severity === "negative") ? "negative" : "notice"
)

// duplicates filter by the pages sharing the same value, other issues by all pages with the same issue
const onFilter = (issue, close) => {
	close()

	if (issue.group) {
		props.column.filterGroup?.(issue.group)
	} else {
		props.column.filterIssue?.(issue.type)
	}
}
</script>

<template>
	<span
		v-if="value.skipped"
		:title="`${$t(`seo.overview.checks.skipped.${value.skipped}`)}: ${$t('seo.overview.checks.skipped')}`"
		:aria-label="$t(`seo.overview.checks.skipped.${value.skipped}`)"
		role="img"
		class="k-seo-checks-cell k-seo-checks-cell-skipped"
	>
		<k-icon type="circle-nested" />
	</span>
	<span
		v-else-if="issues.length === 0"
		:title="$t('seo.overview.checks.ok')"
		:aria-label="$t('seo.overview.checks.ok')"
		role="img"
		class="k-seo-checks-cell k-seo-checks-cell-ok"
	>
		<k-icon type="check" />
	</span>
	<k-seo-popover
		v-else
		:button="{
			class: 'k-button',
			'aria-label': $t('seo.overview.checks.count', { count: issues.length }),
			'data-theme': `${severity}-icon`,
			'data-has-icon': 'true',
			'data-size': 'md'
		}"
		class="k-seo-checks-cell"
	>
		<template #button>
			<span class="k-button-icon"><k-icon :type="SEVERITY_ICONS[severity]" /></span>
		</template>
		<template #default="{ close }">
			<k-dropdown-item
				v-for="issue in issues"
				:key="issue.type"
				:theme="`${issue.severity}-icon`"
				:icon="SEVERITY_ICONS[issue.severity]"
				class="k-seo-popover-item"
				@click="onFilter(issue, close)"
			>
				{{ issue.text }}
				<span v-if="issue.info" class="k-seo-popover-info">{{ issue.info }}</span>
			</k-dropdown-item>
			<hr />
			<k-dropdown-item :link="`${row.link}?tab=seo`" icon="open" class="k-seo-popover-item">
				{{ $t("seo.overview.checks.open") }}
			</k-dropdown-item>
		</template>
	</k-seo-popover>
</template>

<style>
.k-seo-checks-cell {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	min-height: var(--table-row-height);

	> .k-button {
		--button-height: var(--table-row-height);
		--button-width: 100%;
		outline-offset: -2px;
	}
}

/* the span must be the hovered element, see page-cell */
.k-seo-checks-cell-ok,
.k-seo-checks-cell-skipped {
	.k-icon {
		pointer-events: none;
	}
}

.k-seo-checks-cell-ok {
	--icon-color: var(--color-positive);
}

.k-seo-checks-cell-skipped {
	--icon-color: var(--color-gray-400);
}
</style>
