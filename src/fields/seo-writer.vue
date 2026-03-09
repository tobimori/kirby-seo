<template>
	<k-field
		v-bind="$props"
		:counter="counterOptions"
		:class="['k-writer-field', $attrs.class]"
		:input="id"
		:style="$attrs.style"
	>
		<template v-if="!disabled" #options>
			<k-button-group
				ref="buttons"
				:buttons="buttons"
				layout="collapsed"
				size="xs"
				variant="filled"
				class="k-field-options"
			/>
		</template>

		<k-input
			v-bind="$props"
			ref="input"
			:after="after"
			:before="before"
			:icon="icon"
			type="seo-writer"
			@input="$emit('input', $event)"
		/>
	</k-field>
</template>

<script>
/* global AbortController */
import { fetchSseStream, openAiCustomizeDialog, getAiEndpointUrl } from "../helpers/ai-stream.js"

export default {
	extends: "k-writer-field",
	props: {
		ai: [String, Boolean]
	},
	data() {
		return {
			aiStreaming: false,
			aiAbortController: null
		}
	},
	computed: {
		buttons() {
			if (!this.ai) {
				return []
			}

			if (this.aiStreaming) {
				return [
					{
						icon: "loader",
						text: this.$t("seo.ai.action.stop"),
						disabled: this.disabled || !this.aiEndpointUrl,
						theme: "red",
						click: () => this.abortAiStream()
					}
				]
			}

			const buttons = [
				{
					icon: this.value === "" ? "seo-ai" : "refresh",
					text:
						this.value === ""
							? this.$t("seo.ai.action.generate")
							: this.$t("seo.ai.action.regenerate"),
					disabled: this.disabled || !this.aiEndpointUrl,
					click: () => this.startAiStream()
				},
				{
					icon: "cog",
					title: this.$t("seo.ai.action.customize"),
					disabled: this.disabled || !this.aiEndpointUrl,
					click: () => this.openCustomizeDialog()
				}
			]

			if (this.value !== "") {
				return [
					{
						icon: "seo-ai",
						text: this.$t("seo.ai.action.edit"),
						disabled: this.disabled || !this.aiEndpointUrl,
						click: () => this.openEditDialog()
					},
					...buttons
				]
			}

			return buttons
		},
		aiEndpointUrl() {
			return getAiEndpointUrl(this.endpoints)
		}
	},
	beforeDestroy() {
		this.abortAiStream()
	},
	methods: {
		async startAiStream(options = {}) {
			const endpoint = this.aiEndpointUrl

			if (!endpoint || this.disabled || this.aiStreaming) {
				return
			}

			if (this.$refs.input?.focus) {
				this.$refs.input.focus()
			}

			const editor = this.getEditor()
			if (editor) {
				editor.clearContent()
			}

			const controller = new AbortController()
			this.aiAbortController = controller
			this.aiStreaming = true

			try {
				await fetchSseStream({
					url: endpoint,
					body: { instructions: options.instructions, edit: options.edit },
					signal: controller.signal,
					onEvent: (data) => {
						if (data.type === "text-delta") {
							this.applyAiDelta(data.text || "")
						}
					}
				})
			} catch (error) {
				if (error?.name === "AbortError") {
					return
				}

				console.error(error)
				this.$panel.notification.error(error?.message || this.$t("seo.ai.error.request"))
			} finally {
				this.aiAbortController = null
				this.aiStreaming = false
			}
		},
		applyAiDelta(text) {
			if (!text) return

			const editor = this.getEditor()
			if (!editor) return

			const { state, view } = editor
			if (!state || !view) return

			const endPos = state.doc.content.size
			const textNode = state.schema.text(text)
			const transaction = state.tr.insert(endPos, textNode)
			view.dispatch(transaction)
		},
		getEditor() {
			const input = this.$refs.input?.$refs?.input
			return input?.editor || null
		},
		abortAiStream() {
			if (this.aiAbortController) {
				this.aiAbortController.abort()
				this.aiAbortController = null
			}

			this.aiStreaming = false
		},
		openEditDialog() {
			this.$panel.dialog.open({
				component: "k-form-dialog",
				props: {
					fields: {
						instructions: {
							label: this.$t("seo.ai.dialog.instructions.label"),
							type: "textarea",
							buttons: false,
							placeholder: this.$t("seo.ai.dialog.instructions.placeholder"),
							required: true
						}
					},
					submitButton: this.$t("seo.ai.dialog.edit.submit")
				},
				on: {
					submit: (values) => {
						this.$panel.dialog.close()
						this.startAiStream({
							edit: this.value,
							instructions: values.instructions
						})
					}
				}
			})
		},
		openCustomizeDialog() {
			openAiCustomizeDialog((values) => {
				this.startAiStream({
					instructions: values.instructions
				})
			})
		}
	}
}
</script>
