<script setup>
import { computed, usePanel } from "kirbyuse"

import { SEVERITY_ICONS } from "../utils/checks.js"

/**
 * All pages linking to a URL of the links tab, with the state of the link
 */
const props = defineProps({
	url: String,
	/** `broken`, `anchor`, `redirect`, `unknown` or `ok` */
	state: String,
	/** Why the link is broken, the target of redirects, … */
	details: String,
	items: {
		type: Array,
		default: () => []
	},
	pagination: Object,
	// drawer props
	visible: Boolean,
	current: Boolean,
	icon: String,
	title: String,
	breadcrumb: Array,
	tabs: Object,
	tab: String
})

const emit = defineEmits(["cancel", "crumb", "submit", "tab"])

const panel = usePanel()

const THEMES = {
	broken: "negative",
	anchor: "notice",
	redirect: "notice",
	unknown: "empty",
	ok: "positive"
}

const ICONS = {
	broken: SEVERITY_ICONS.negative,
	anchor: SEVERITY_ICONS.notice,
	redirect: SEVERITY_ICONS.notice,
	unknown: "question",
	ok: SEVERITY_ICONS.ok
}

const options = computed(() => [
	{
		icon: "open",
		title: panel.t("seo.overview.links.options.open"),
		link: props.url,
		target: "_blank"
	}
])

// k-box only shows its icon without slot content
const text = computed(() => {
	const label = panel.t(`seo.overview.links.status.${props.state}`)
	return props.details ? `${label}: ${props.details}` : label
})

const onPaginate = ({ page }) => panel.drawer.refresh({ query: { url: props.url, page } })
</script>

<template>
	<k-drawer
		:visible="visible"
		:current="current"
		:icon="icon"
		:title="title"
		:breadcrumb="breadcrumb"
		:tabs="tabs"
		:tab="tab"
		:options="options"
		class="k-seo-link-drawer"
		@cancel="emit('cancel')"
		@crumb="emit('crumb', $event)"
		@submit="emit('cancel')"
		@tab="emit('tab', $event)"
	>
		<k-box
			:theme="THEMES[state]"
			:icon="ICONS[state]"
			:text="text"
			class="k-seo-link-drawer-state"
		/>

		<k-section :label="$t('seo.overview.links.columns.pages')">
			<k-collection
				:items="items"
				:pagination="{ ...pagination, details: true }"
				layout="list"
				@paginate="onPaginate"
			/>
		</k-section>
	</k-drawer>
</template>

<style>
.k-seo-link-drawer-state {
	margin-bottom: var(--spacing-6);
}
</style>
