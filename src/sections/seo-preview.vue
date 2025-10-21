<script setup>
import {
	ref,
	watch,
	computed,
	onMounted,
	onUnmounted,
	usePanel,
	useSection,
	useApp
} from "kirbyuse"
import { section } from "kirbyuse/props"

import FacebookPreview from "../components/previews/facebook-preview.vue"
import GooglePreview from "../components/previews/google-preview.vue"
import SlackPreview from "../components/previews/slack-preview.vue"

const props = defineProps(section)

const panel = usePanel()
const app = useApp()
const { load } = useSection()

const meta = ref(null)
const options = ref([])
const isSiteParent = computed(() => props.parent === "site")
const baseLabel = computed(
	() => props.label || panel?.t?.("seo.sections.preview.title") || "Preview"
)
const headerLabel = computed(() => {
	const pageTitle = meta.value?.pageTitle

	if (isSiteParent.value && pageTitle) {
		const safeTitle = pageTitle.replaceAll('"', '\\"')
		return `${baseLabel.value} (shows "${safeTitle}")`
	}

	return baseLabel.value
})

const type = ref(window.localStorage.getItem("kSEOPreviewType") ?? "google")
watch(type, (newType) => {
	window.localStorage.setItem("kSEOPreviewType", newType)
})

const handleLoad = () => {
	load({
		parent: props.parent,
		name: props.name
	}).then((response) => {
		meta.value = response.meta
		options.value = response.options
		// set default type if not already set and options are available
		if (
			!window.localStorage.getItem("kSEOPreviewType") &&
			response.options.length > 0
		) {
			type.value = response.options[0].value
		}
	})
}

const openPanelTarget = () => {
	if (!meta.value?.panelUrl) {
		return
	}

	app.$go(meta.value.panelUrl)
}

onMounted(() => {
	handleLoad()

	// this will trigger after the server has finished processing the request as changes
	panel.events.on("content.save", (_event) => {
		handleLoad()
	})
})

onUnmounted(() => panel.events.off("content.save"))
</script>

<template>
	<div class="k-section k-seo-preview">
		<div class="k-field-header k-seo-preview__label k-label k-field-label">
			<k-icon type="preview" />
			<span class="k-label-text">
				{{ headerLabel }}
			</span>
			<k-button
				v-if="isSiteParent && meta?.panelUrl"
				class="k-seo-preview__panel-button"
				variant="filled"
				size="xs"
				icon="edit"
				@click="openPanelTarget"
			>
				View page in panel
			</k-button>
		</div>
		<k-select-field
			v-model="type"
			type="select"
			name="seo-preview-type"
			:before="$t('seo.sections.preview.showFor')"
			:options="options"
			:required="true"
			:empty="false"
		/>
		<div v-if="meta" class="k-seo-preview__inner">
			<google-preview v-if="type === 'google'" v-bind="meta" />
			<facebook-preview v-if="type === 'facebook'" v-bind="meta" />
			<slack-preview v-if="type === 'slack'" v-bind="meta" />
		</div>
	</div>
</template>

<style>
.k-field-name-seo-preview-type .k-field-header {
	display: none;
}

.k-seo-preview__inner {
	margin-top: var(--spacing-2);
}

.k-seo-preview__debugger {
	margin-top: 1rem;
	display: flex;
	font-size: var(--text-sm);
	color: var(--color-text-dimmed);
	line-height: 1.25rem;
	width: max-content;
	margin-left: auto;

	&:hover {
		text-decoration: underline;
		color: var(--theme-color-text);
	}

	> .k-icon {
		margin-left: var(--spacing-2);
	}
}

.k-seo-preview__label {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: var(--spacing-2);

	> .k-icon {
		color: var(--theme-color-icon);
	}
}

.k-seo-preview__panel-button {
	margin-left: auto;
}
</style>
