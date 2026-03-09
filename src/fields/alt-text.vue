<script setup>
/* global AbortController, Event */
import {
	ref,
	computed,
	watch,
	nextTick,
	onMounted,
	onBeforeUnmount,
	usePanel,
	useLibrary
} from "kirbyuse"
import { fetchSseStream, openAiCustomizeDialog, getAiEndpointUrl } from "../helpers/ai-stream.js"

const props = defineProps({
	ai: Boolean,
	autogenerate: Boolean,
	disabled: Boolean,
	endpoints: Object,
	id: String,
	label: String,
	help: String,
	name: String,
	placeholder: String,
	required: Boolean,
	value: {
		type: Object,
		default: () => ({ text: "", decorative: false, source: "manual" })
	}
})

const $emit = defineEmits(["input"])

const panel = usePanel()
const library = useLibrary()
const input = ref(null)
const streaming = ref(false)
let controller = null
let streamedText = ""

// computed
const text = computed(() => props.value?.text ?? "")
const isDecorative = computed(() => props.value?.decorative ?? false)
const source = computed(() => props.value?.source ?? "manual")
const aiEndpointUrl = computed(() => getAiEndpointUrl(props.endpoints))

async function startAiStream(body = {}) {
	const endpoint = aiEndpointUrl.value

	if (!endpoint || props.disabled || streaming.value) {
		return
	}

	streamedText = ""
	emit({ text: "", source: "reviewed" })

	controller = new AbortController()
	streaming.value = true

	try {
		await fetchSseStream({
			url: endpoint,
			body,
			signal: controller.signal,
			onEvent: (data) => {
				if (data.type === "text-delta") {
					streamedText += data.text || ""
					emit({ text: streamedText, source: "reviewed" })
				}
			}
		})
	} catch (error) {
		if (error?.name === "AbortError") {
			return
		}

		console.error(error)
		panel.notification.error(error?.message || panel.t("seo.ai.error.request"))
	} finally {
		controller = null
		streaming.value = false
	}
}

function abortAiStream() {
	if (controller) {
		controller.abort()
		controller = null
	}

	streaming.value = false
}

const buttons = computed(() => {
	if (streaming.value) {
		return [
			{
				icon: "loader",
				text: panel.t("seo.ai.action.stop"),
				theme: "red",
				click: () => abortAiStream()
			}
		]
	}

	const btns = [
		{
			icon: text.value === "" ? "seo-ai" : "refresh",
			text:
				text.value === "" ? panel.t("seo.ai.action.generate") : panel.t("seo.ai.action.regenerate"),
			disabled: props.disabled || isDecorative.value || !aiEndpointUrl.value,
			click: () => startAiStream()
		}
	]

	if (text.value !== "") {
		btns.push({
			icon: "cog",
			title: panel.t("seo.ai.action.customize"),
			disabled: props.disabled || isDecorative.value || !aiEndpointUrl.value,
			click: () =>
				openAiCustomizeDialog((values) => {
					startAiStream({ instructions: values.instructions })
				})
		})
	}

	return btns
})

// emit helper
function emit(changes) {
	$emit("input", {
		text: text.value,
		decorative: isDecorative.value,
		source: source.value,
		...changes
	})
}

// input handlers
function onTextInput(value) {
	const s = source.value === "ai" || source.value === "reviewed" ? "reviewed" : "manual"
	emit({ text: value, source: s })
}

function onBeforeInput(event) {
	if (event.inputType === "insertLineBreak" || event.inputType === "insertParagraph") {
		event.preventDefault()
		return
	}

	const data = event.data ?? event.dataTransfer?.getData("text/plain")
	if (data && /\n/.test(data)) {
		event.preventDefault()
		const textarea = event.target
		const clean = data.replace(/[\r\n]+/g, " ")
		const start = textarea.selectionStart
		const end = textarea.selectionEnd
		textarea.setRangeText(clean, start, end, "end")
		textarea.dispatchEvent(new Event("input", { bubbles: true }))
	}
}

// autosize
watch(text, () => {
	if (streaming.value) return
	nextTick(() => library.autosize.update(input.value))
})

onMounted(() => {
	nextTick(() => library.autosize(input.value))
})

onBeforeUnmount(() => {
	library.autosize.destroy(input.value)
	abortAiStream()
})
</script>

<template>
	<k-field
		v-bind="$props"
		:class="['k-alt-text-field', { 'is-decorative': isDecorative }]"
		:input="id"
	>
		<template v-if="ai && !disabled" #options>
			<k-button-group
				:buttons="buttons"
				layout="collapsed"
				size="xs"
				variant="filled"
				class="k-field-options"
			/>
		</template>

		<k-input :icon="false" :disabled="disabled">
			<div class="k-alt-text-header">
				<k-button
					class="k-alt-text-toggle"
					:disabled="disabled"
					:icon="isDecorative ? 'toggle-off' : 'toggle-on'"
					:theme="isDecorative ? null : 'positive-icon'"
					:title="
						panel.t(isDecorative ? 'seo.altText.decorative.on' : 'seo.altText.decorative.off')
					"
					variant="filled"
					@click="emit({ decorative: !isDecorative })"
				>
					<template v-if="isDecorative">
						{{ panel.t("seo.altText.decorative.on") }}
					</template>
				</k-button>

				<textarea
					:id="id"
					ref="input"
					:value="text"
					:disabled="disabled || isDecorative"
					:placeholder="isDecorative ? '' : placeholder"
					class="k-textarea-input-native"
					rows="1"
					@input="onTextInput($event.target.value)"
					@beforeinput="onBeforeInput"
				/>
			</div>
		</k-input>
	</k-field>
</template>

<style>
.k-alt-text-field {
	& .k-input {
		min-height: var(--input-height);
	}

	& .k-textarea-input-native {
		min-width: 0;
		padding: var(--input-padding);
		resize: none;
		margin-block: -1.5px;
	}

	&.is-decorative {
		& .k-alt-text-header {
			grid-template-columns: 1fr;
		}

		& .k-alt-text-toggle.k-button {
			--button-align: flex-start;
			margin-inline: 0.25rem;
		}

		& .k-textarea-input-native {
			display: none;
		}
	}
}

.k-alt-text-header {
	display: grid;
	grid-template-columns: max-content minmax(0, 1fr);
	align-items: center;
	min-height: inherit;
}

.k-alt-text-toggle.k-button {
	--button-height: var(--height-sm);
	--button-rounded: var(--rounded-sm);
	--button-color-back: var(--panel-color-back);
	margin-inline-start: 0.25rem;
}
</style>
