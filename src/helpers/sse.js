/* global TextDecoder */

/**
 * Reads an SSE ReadableStream, parses events, and calls the handler for each parsed JSON payload.
 *
 * @param {ReadableStreamDefaultReader} reader
 * @param {(data: object) => void} onEvent
 */
export async function consumeSSEStream(reader, onEvent) {
	const decoder = new TextDecoder()
	let buffer = ""

	try {
		while (true) {
			const { value, done } = await reader.read()
			if (done) break

			if (value) {
				buffer += decoder.decode(value, { stream: true })
				buffer = processBuffer(buffer, onEvent)
			}
		}

		buffer += decoder.decode()
		processBuffer(buffer, onEvent)
	} finally {
		if (reader && typeof reader.releaseLock === "function") {
			reader.releaseLock()
		}
	}
}

/**
 * @param {string} buffer
 * @param {(data: object) => void} onEvent
 */
function processBuffer(buffer, onEvent) {
	let rest = buffer

	while (true) {
		const delimiterIndex = rest.indexOf("\n\n")
		if (delimiterIndex === -1) return rest

		const raw = rest.slice(0, delimiterIndex)
		rest = rest.slice(delimiterIndex + 2)

		if (raw.trim() === "") continue

		const payload = raw
			.split("\n")
			.filter((line) => line.trim().startsWith("data:"))
			.map((line) => line.trim().slice(5))
			.join("\n")
			.trim()

		if (payload === "") continue

		let data
		try {
			data = JSON.parse(payload)
		} catch (error) {
			console.error("Failed to parse SSE event", error, payload)
			continue
		}

		onEvent(data)
	}
}
