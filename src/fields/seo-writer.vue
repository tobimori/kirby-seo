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
			aiAbortController: null,
			aiError: null
		}
	},
	computed: {
		buttons() {
			if (!this.aiTask) {
				return []
			}

			// if the content is empty, we want two buttons
			// - Generate
			// - Customize...

			// if the content is filled, we want three buttons
			// - Edit...
			// - Regenerate
			// - Customize...

			return [
				{
					icon: this.aiStreaming ? "loader" : "seo-ai",
					text: this.aiStreaming
						? this.$t("seo.ai.action.stop")
						: this.$t("seo.ai.action.generate"),
					disabled: this.disabled || !this.aiEndpointUrl,
					theme: this.aiStreaming ? "red" : null,
					click: () => this.toggleAiStream()
				},
				{
					icon: "cog",
					title: this.$t("seo.ai.action.generate"), // TOOD: figure out what 'title' and 'text' means
					disabled: this.disabled || !this.aiEndpointUrl,
					click: () => {} // TODO: open dropdown
				}
			]
		},
		aiTask() {
			if (!this.ai || typeof this.ai !== "string") {
				return null
			}

			const value = this.ai.trim()

			if (value === "" || value === "false") {
				return null
			}

			return value
		},
		aiEndpointUrl() {
			const apiBase = this.$panel?.urls?.api
			const fieldEndpoint = this.endpoints?.field

			if (!apiBase || !fieldEndpoint) {
				return null
			}

			return `${apiBase}/${fieldEndpoint}/ai/stream`.replace(
				/([^:]\/)\/+/g,
				"$1"
			)
		}
	},
	beforeDestroy() {
		this.abortAiStream()
	},
	methods: {
		toggleAiStream() {
			if (this.aiStreaming) {
				this.abortAiStream()
				return
			}

			this.startAiStream()
		},
		async startAiStream() {
			const task = this.aiTask
			const endpoint = this.aiEndpointUrl

			if (!task || !endpoint || this.disabled || this.aiStreaming) {
				return
			}

			this.aiError = null
			this.focusWriter()

			const controller = new AbortController()
			this.aiAbortController = controller
			this.aiStreaming = true

			try {
				const response = await fetch(
					endpoint,
					this.aiRequestOptions(controller.signal, task)
				)

				if (!response.ok) {
					throw new Error(await this.extractErrorMessage(response))
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
				this.handleAiError(error)
			} finally {
				this.cleanupAiStream()
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
			let data = null

			try {
				data = JSON.parse(payload)
			} catch (error) {
				console.error("Failed to parse AI chunk", error, payload)
				return
			}

			if (data.type === "text-start") {
				this.aiError = null
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

			const input =
				this.$refs.input &&
				this.$refs.input.$refs &&
				this.$refs.input.$refs.input

			if (input && typeof input.insertAiText === "function") {
				input.insertAiText(text)
			}
		},
		aiRequestOptions(signal) {
			return {
				method: "POST",
				headers: this.aiHeaders(),
				body: JSON.stringify({
					lang: this.$panel?.language.code
				}),
				credentials: "same-origin",
				signal
			}
		},
		aiHeaders() {
			const headers = {
				"Content-Type": "application/json",
				Accept: "text/event-stream"
			}

			const token = this.$panel?.system?.csrf || this.$config?.csrf
			const language = this.$panel?.language || this.$config?.language

			if (token) {
				headers["X-CSRF"] = token
			}

			if (language) {
				headers["X-Language"] = language
			}

			return headers
		},
		focusWriter() {
			if (this.$refs.input && typeof this.$refs.input.focus === "function") {
				this.$refs.input.focus()
			}
		},
		abortAiStream() {
			if (this.aiAbortController) {
				this.aiAbortController.abort()
				this.aiAbortController = null
			}

			this.aiStreaming = false
		},
		cleanupAiStream() {
			this.aiAbortController = null
			this.aiStreaming = false
		},
		async extractErrorMessage(response) {
			try {
				const data = await response.json()
				if (data && typeof data.message === "string") {
					return data.message
				}
			} catch {
				// ignore JSON parsing errors
			}

			return this.$t("seo.ai.error.request")
		},
		handleAiError(error) {
			const message =
				error && error.message ? error.message : this.$t("seo.ai.error.request")

			this.aiError = message

			if (
				this.$panel &&
				this.$panel.notification &&
				typeof this.$panel.notification.error === "function"
			) {
				this.$panel.notification.error(message)
				return
			}

			console.error(message)
		}
	}
}
</script>
