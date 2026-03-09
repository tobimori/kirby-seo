/* global fetch */
import { usePanel } from "kirbyuse"

import { consumeSSEStream } from "./sse.js"

/**
 * Performs a POST request to an SSE endpoint and consumes the stream.
 *
 * @param {object} options
 * @param {string} options.url
 * @param {object} options.body
 * @param {AbortSignal} options.signal
 * @param {(data: object) => void} options.onEvent
 */
export async function fetchSseStream({ url, body, signal, onEvent }) {
	const panel = usePanel()

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
		signal
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

	await consumeSSEStream(response.body.getReader(), (data) => {
		if (data.type === "error") {
			throw new Error(data.payload?.message || panel.t("seo.ai.error.request"))
		}

		onEvent(data)
	})
}

/**
 * Opens the AI customize dialog.
 *
 * @param {(values: object) => void} callback
 */
export function openAiCustomizeDialog(callback) {
	const panel = usePanel()

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
