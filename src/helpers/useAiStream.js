/* global AbortController, fetch */
import { ref, usePanel } from "kirbyuse"

import { consumeSSEStream } from "./sse.js"

/**
 * Composable for AI SSE streaming in panel fields.
 *
 * @param {object} options
 * @param {() => string|null} options.endpoint
 * @param {() => boolean} options.disabled
 * @param {(data: object) => void} options.onEvent
 * @param {() => void} [options.onBeforeStream]
 */
export function useAiStream({ endpoint, disabled, onEvent, onBeforeStream }) {
	const panel = usePanel()
	const streaming = ref(false)
	const controller = ref(null)

	/**
	 * @param {object} body
	 */
	async function start(body = {}) {
		const url = endpoint()
		if (!url || disabled() || streaming.value) return

		onBeforeStream?.()

		controller.value = new AbortController()
		streaming.value = true

		try {
			const response = await fetch(url, {
				method: "POST",
				headers: {
					"Content-Type": "application/json",
					Accept: "text/event-stream",
					"X-CSRF": panel.system?.csrf,
					"X-Language": panel.language.code
				},
				body: JSON.stringify(body),
				credentials: "same-origin",
				signal: controller.value.signal
			})

			if (!response.ok) {
				let message = panel.t("seo.ai.error.request")
				try {
					const data = await response.json()
					message = data?.message || message
				} catch {
					// not JSON
				}
				throw new Error(message)
			}

			if (!response.body) {
				throw new Error(panel.t("seo.ai.error.request"))
			}

			await consumeSSEStream(response.body.getReader(), onEvent)
		} catch (error) {
			if (error?.name === "AbortError") return

			console.error(error)
			panel.notification.error(error?.message || panel.t("seo.ai.error.request"))
		} finally {
			controller.value = null
			streaming.value = false
		}
	}

	function abort() {
		if (controller.value) {
			controller.value.abort()
			controller.value = null
		}
		streaming.value = false
	}

	/**
	 * @param {(values: object) => void} callback
	 */
	function openCustomizeDialog(callback) {
		panel.dialog.open({
			component: "k-form-dialog",
			props: {
				fields: {
					instructions: {
						label: panel.t("seo.ai.dialog.custom.label"),
						type: "textarea",
						buttons: false,
						placeholder: panel.t("seo.ai.dialog.custom.placeholder"),
						required: true
					}
				},
				submitButton: panel.t("seo.ai.dialog.custom.submit")
			},
			on: {
				submit: (values) => {
					panel.dialog.close()
					callback(values)
				}
			}
		})
	}

	return { streaming, start, abort, openCustomizeDialog }
}

/**
 * Builds the AI stream endpoint URL from panel API base and field endpoint.
 *
 * @param {{ field?: string }} endpoints
 */
export function getAiEndpointUrl(endpoints) {
	const panel = usePanel()
	const apiBase = panel.urls?.api
	const fieldEndpoint = endpoints?.field
	if (!apiBase || !fieldEndpoint) return null
	return `${apiBase}/${fieldEndpoint}/ai/stream`.replace(/([^:]\/)\/+/g, "$1")
}
