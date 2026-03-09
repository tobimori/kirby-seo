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
import { computed } from "kirbyuse"
import { useAiStream, getAiEndpointUrl } from "../helpers/useAiStream.js"

export default {
	extends: "k-writer-field",
	props: {
		ai: [String, Boolean],
		disabled: Boolean,
		endpoints: Object
	},
	setup(props) {
		const aiEndpointUrl = computed(() => getAiEndpointUrl(props.endpoints))

		let onBeforeStream = () => {}
		let onEvent = () => {}

		const { streaming, start, abort, openCustomizeDialog } = useAiStream({
			endpoint: () => aiEndpointUrl.value,
			disabled: () => props.disabled,
			onBeforeStream: () => onBeforeStream(),
			onEvent: (data) => onEvent(data)
		})

		function connectStreamHandlers(handlers) {
			onBeforeStream = handlers.onBeforeStream
			onEvent = handlers.onEvent
		}

		return {
			aiStreaming: streaming,
			aiEndpointUrl,
			startAiStream: start,
			abortAiStream: abort,
			_openAiCustomizeDialog: openCustomizeDialog,
			_connectStreamHandlers: connectStreamHandlers
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
		}
	},
	created() {
		this._connectStreamHandlers({
			onBeforeStream: () => {
				this.$refs.input?.focus?.()
				this.getEditor()?.clearContent()
			},
			onEvent: (data) => this.handleAiEvent(data)
		})
	},
	beforeDestroy() {
		this.abortAiStream()
	},
	methods: {
		handleAiEvent(data) {
			if (data.type === "text-start") {
				return
			}

			if (data.type === "text-delta") {
				this.applyAiDelta(data.text || "")
				return
			}

			if (data.type === "thinking-delta") {
				return
			}

			if (data.type === "tool-call" || data.type === "tool-result") {
				return
			}

			if (data.type === "stream-end") {
				return
			}

			if (data.type === "error") {
				throw new Error(
					data.payload?.message || this.$t("seo.ai.error.request")
				)
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
			this._openAiCustomizeDialog((values) => {
				this.startAiStream({
					instructions: values.instructions
				})
			})
		}
	}
}
</script>
