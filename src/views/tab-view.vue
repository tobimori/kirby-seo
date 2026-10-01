<script setup>
import { usePanel } from "kirbyuse"

/**
 * Tabs of the SEO area that don't have their own view yet
 */
defineProps({
	buttons: Array,
	stats: Object,
	tab: String,
	tabs: Array,
	// only read by `panel.content` (e.g. when switching languages), declared so they don't end up as attributes
	api: String,
	lock: Object,
	versions: Object
})

const panel = usePanel()

// the stats filter the pages
const onFilter = (issue) => panel.view.open("seo/pages", { query: { issue } })
</script>

<template>
	<k-seo-view :buttons="buttons" :stats="stats" :tab="tab" :tabs="tabs" @filter="onFilter">
		<k-empty icon="clock" layout="table">{{ $t("seo.overview.comingSoon") }}</k-empty>
	</k-seo-view>
</template>
