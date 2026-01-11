<script setup>
import {
	ref,
	computed,
	watch,
	nextTick,
	onMounted,
	onUnmounted
} from "kirbyuse"

const props = defineProps({
	pageUrl: {
		type: String,
		required: true
	},
	visible: {
		type: Boolean,
		default: true
	},
	size: {
		type: String,
		default: "medium"
	}
})

const emit = defineEmits(["cancel"])

const params = ref({
	utm_source: "",
	utm_medium: "",
	utm_campaign: "",
	utm_content: "",
	utm_term: "",
	ref: ""
})

const fields = [
	{ key: "utm_source", icon: "globe", name: "source" },
	{ key: "utm_medium", icon: "dashboard", name: "medium" },
	{ key: "utm_campaign", icon: "megaphone", name: "campaign" },
	{ key: "utm_content", icon: "image", name: "content" },
	{ key: "utm_term", icon: "search", name: "term" },
	{ key: "ref", icon: "url", name: "ref" }
]

const copied = ref(false)
const urlInput = ref(null)

const generatedUrl = computed(() => {
	const url = new URL(props.pageUrl)

	for (const field of fields) {
		if (params.value[field.key]) {
			url.searchParams.set(field.key, params.value[field.key])
		}
	}

	return url.toString()
})

const copyToClipboard = async () => {
	try {
		await navigator.clipboard.writeText(generatedUrl.value)
		copied.value = true
		setTimeout(() => {
			copied.value = false
		}, 2000)
	} catch (err) {
		console.error("Failed to copy:", err)
	}
}

watch(generatedUrl, () => {
	nextTick(() => {
		if (urlInput.value) {
			urlInput.value.scrollLeft = urlInput.value.scrollWidth
		}
	})
})

const handleKeydown = (e) => {
	if (!props.visible) return
	if (
		(e.ctrlKey || e.metaKey) &&
		e.key === "c" &&
		!window.getSelection()?.toString()
	) {
		e.preventDefault()
		copyToClipboard()
	}
}

onMounted(() => {
	document.addEventListener("keydown", handleKeydown)
})

onUnmounted(() => {
	document.removeEventListener("keydown", handleKeydown)
})
</script>

<template>
	<k-dialog
		class="k-seo-utm-share-dialog"
		:size="size"
		:visible="visible"
		:cancel-button="false"
		:submit-button="false"
		@cancel="emit('cancel')"
	>
		<template #header>
			<k-button
				class="k-seo-utm-share-dialog__close"
				icon="cancel"
				@click="emit('cancel')"
			/>
		</template>

		<div class="k-seo-utm-share-dialog__url-wrapper">
			<k-label>{{ $t("seo.utmShare.button") }}</k-label>
			<div class="k-input k-seo-utm-share-dialog__url">
				<span class="k-input-element">
					<input
						ref="urlInput"
						type="text"
						:value="generatedUrl"
						readonly
						class="k-string-input"
						data-font="monospace"
						@focus="$event.target.select()"
					/>
				</span>
				<k-button
					class="k-seo-utm-share-dialog__copy"
					:icon="copied ? 'check' : 'copy'"
					:theme="copied ? 'positive' : 'notice'"
					variant="filled"
					@click="copyToClipboard"
				/>
			</div>
		</div>

		<k-label class="k-seo-utm-share-dialog__params-label">{{
			$t("seo.utmShare.parameters")
		}}</k-label>
		<div class="k-seo-utm-share-dialog__params">
			<div
				v-for="field in fields"
				:key="field.key"
				class="k-seo-utm-share-dialog__row"
			>
				<label class="k-seo-utm-share-dialog__label" :for="field.key">
					<k-icon :type="field.icon" />
					{{ $t(`seo.utmShare.${field.name}.label`) }}
				</label>
				<k-input class="k-seo-utm-share-dialog__input">
					<k-text-input
						:id="field.key"
						v-model="params[field.key]"
						:placeholder="$t(`seo.utmShare.${field.name}.placeholder`)"
					/>
				</k-input>
			</div>
		</div>
	</k-dialog>
</template>

<style>
.k-seo-utm-share-dialog__close {
	position: absolute;
	top: var(--spacing-2);
	right: var(--spacing-2);
	z-index: 1;
}

.k-seo-utm-share-dialog__url-wrapper {
	margin-bottom: var(--spacing-6);
}

.k-seo-utm-share-dialog__url {
	padding-right: var(--spacing-1);
}

.k-seo-utm-share-dialog__copy {
	--button-height: calc(var(--input-height) - var(--spacing-2));
	--button-rounded: var(--rounded-sm);
	flex-shrink: 0;
}

.k-seo-utm-share-dialog__params-label {
	margin-bottom: var(--spacing-2);
}

.k-seo-utm-share-dialog__params {
	display: flex;
	flex-direction: column;
	gap: var(--spacing-2);
}

.k-seo-utm-share-dialog__row {
	display: flex;
	align-items: center;
	background: light-dark(var(--color-gray-100), var(--color-gray-900));
	border: 1px solid var(--color-border);
	border-radius: var(--rounded);

	& .k-input,
	& .k-string-input {
		border-top-left-radius: 0;
		border-bottom-left-radius: 0;
	}

	& .k-input {
		--input-color-back: light-dark(var(--color-white), var(--color-gray-850));
	}
}

.k-seo-utm-share-dialog__label {
	display: flex;
	align-items: center;
	gap: var(--spacing-2);
	width: 7rem;
	flex-shrink: 0;
	padding: var(--input-padding);
	font-size: var(--text-sm);
	color: var(--color-text);

	& .k-icon {
		color: var(--color-text-dimmed);
	}
}

.k-seo-utm-share-dialog__input {
	flex: 1;
}
</style>
