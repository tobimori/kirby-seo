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
/* global AbortController, fetch, TextDecoder */
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
					title: this.$t("seo.ai.action.customize"), // TODO: figure out what 'title' and 'text' means
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
			const apiBase = this.$panel?.urls?.api
			const fieldEndpoint = this.endpoints?.field

			if (!apiBase || !fieldEndpoint) {
				return null
			}

			return `${apiBase}/${fieldEndpoint}/ai/stream`.replace(/([^:]\/)\/+/g, "$1")
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
				const response = await fetch(endpoint, {
					method: "POST",
					headers: {
						"Content-Type": "application/json",
						Accept: "text/event-stream",
						"X-CSRF": this.$panel?.system?.csrf,
						"X-Language": this.$panel?.language.code
					},
					body: JSON.stringify({
						instructions: options.instructions,
						edit: options.edit
					}),
					credentials: "same-origin",
					signal: controller.signal
				})

				if (!response.ok) {
					try {
						const data = await response.json()
						throw new Error(data?.message || this.$t("seo.ai.error.request"))
					} catch {
						throw new Error(this.$t("seo.ai.error.request"))
					}
				}

				if (!response.body) {
					throw new Error(this.$t("seo.ai.error.request"))
				}

				await this.consumeStream(response.body.getReader())
			} catch (error) {
				if (error && error.name === "AbortError") {
					return
				}

				console.error(error)
				const message = error && error.message ? error.message : this.$t("seo.ai.error.request")
				this.$panel.notification.error(message)
			} finally {
				this.aiAbortController = null
				this.aiStreaming = false
			}
		},
		async consumeStream(reader) {
			const decoder = new TextDecoder()
			let buffer = ""

			try {
				while (true) {
					const { value, done } = await reader.read()

					if (done) {
						break
					}

					if (value) {
						buffer += decoder.decode(value, { stream: true })
						buffer = this.processStreamBuffer(buffer)
					}
				}

				buffer += decoder.decode()
				this.processStreamBuffer(buffer)
			} finally {
				if (reader && typeof reader.releaseLock === "function") {
					reader.releaseLock()
				}
			}
		},
		processStreamBuffer(buffer) {
			let rest = buffer

			while (true) {
				const delimiterIndex = rest.indexOf("\n\n")

				if (delimiterIndex === -1) {
					return rest
				}

				const raw = rest.slice(0, delimiterIndex)
				rest = rest.slice(delimiterIndex + 2)

				if (raw.trim() === "") {
					continue
				}

				const payload = raw
					.split("\n")
					.filter((line) => line.trim().startsWith("data:"))
					.map((line) => line.trim().slice(5))
					.join("\n")
					.trim()

				if (payload === "") {
					continue
				}

				this.handleAiEvent(payload)
			}
		},
		handleAiEvent(payload) {
			let data
			try {
				data = JSON.parse(payload)
			} catch (error) {
				console.error("Failed to parse AI chunk", error, payload)
				return
			}

			if (data.type === "text-start") {
				return
			}

			if (data.type === "text-delta") {
				this.applyAiDelta(data.text || "")
				return
			}

			if (data.type === "thinking-delta") {
				// future: surface in UI, for now ignore
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
					data.payload && data.payload.message
						? data.payload.message
						: this.$t("seo.ai.error.request")
				)
			}
		},
		applyAiDelta(text) {
			if (!text) {
				return
			}

			const editor = this.getEditor()
			if (!editor) {
				return
			}

			// append text to the end of the document
			const { state, view } = editor
			if (!state || !view) {
				return
			}

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
			this.$panel.dialog.open({
				component: "k-form-dialog",
				props: {
					fields: {
						instructions: {
							label: this.$t("seo.ai.dialog.custom.label"),
							type: "textarea",
							buttons: false,
							placeholder: this.$t("seo.ai.dialog.custom.placeholder"),
							required: true
						}
					},
					submitButton: this.$t("seo.ai.dialog.custom.submit")
				},
				on: {
					submit: (values) => {
						this.$panel.dialog.close()
						this.startAiStream({
							instructions: values.instructions
						})
					}
				}
			})
		}
	}
}
</script>
