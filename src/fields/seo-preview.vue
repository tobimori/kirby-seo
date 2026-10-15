<script setup>
import { usePanel, useApi } from "kirbyuse"
import { ref, watch, computed, onMounted, onUnmounted } from "vue"

import FacebookPreview from "../components/previews/facebook-preview.vue"
import GooglePreview from "../components/previews/google-preview.vue"
import SlackPreview from "../components/previews/slack-preview.vue"

defineOptions({ inheritAttrs: false })

const props = defineProps({
	label: String,
	name: String,
	endpoints: Object,
	options: {
		type: Array,
		default: () => []
	}
})

// display fields store nothing, declaring the events keeps native events from bubbling into the form
defineEmits(["input", "focus", "submit"])

const panel = usePanel()
const api = useApi()

const meta = ref(null)

const isSiteParent = computed(() => props.endpoints?.model === "site")
const baseLabel = computed(() => props.label || panel.t("seo.sections.preview.title"))
const headerLabel = computed(() => {
	const pageTitle = meta.value?.pageTitle
	if (isSiteParent.value && pageTitle) {
		return panel.t("seo.sections.preview.titleWithPage", { title: pageTitle })
	}

	return baseLabel.value
})

const type = ref(
	window.localStorage.getItem("kSEOPreviewType") ?? props.options[0]?.value ?? "google"
)
watch(type, (newType) => {
	window.localStorage.setItem("kSEOPreviewType", newType)
})

const handleLoad = async () => {
	if (!props.endpoints?.field) {
		return
	}

	const response = await api.get(props.endpoints.field)
	meta.value = response.meta
}

const openPanelTarget = () => {
	if (!meta.value?.panelUrl) {
		return
	}

	panel.view.open(meta.value.panelUrl)
}

onMounted(() => {
	handleLoad()

	// this will trigger after the server has finished processing the request as changes
	panel.events.on("content.save", handleLoad)
})

onUnmounted(() => panel.events.off("content.save", handleLoad))
</script>

<template>
	<div v-if="meta" :class="['k-field k-seo-preview', $attrs.class]" :style="$attrs.style">
		<div class="k-field-header k-seo-preview__label k-label k-field-label">
			<k-icon type="preview" />
			<span class="k-label-text">
				{{ headerLabel }}
			</span>
			<k-button
				v-if="isSiteParent && meta.panelUrl"
				class="k-seo-preview__panel-button"
				variant="filled"
				size="xs"
				icon="edit"
				@click="openPanelTarget"
			>
				{{ $t("seo.sections.preview.viewPage") }}
			</k-button>
		</div>
		<k-select-field
			:value="type"
			type="select"
			name="seo-preview-type"
			:aria-label="headerLabel"
			:before="$t('seo.sections.preview.showFor')"
			:options="options"
			:required="true"
			:empty="false"
			@input="type = $event"
		/>
		<div class="k-seo-preview__inner">
			<google-preview v-if="type === 'google'" v-bind="meta" />
			<facebook-preview v-if="type === 'facebook'" v-bind="meta" />
			<slack-preview v-if="type === 'slack'" v-bind="meta" />
		</div>
	</div>
</template>

<style>
.k-seo-preview__inner {
	margin-top: var(--spacing-2);
}

.k-seo-preview__debugger {
	margin-top: var(--spacing-4);
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
