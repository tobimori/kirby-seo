export const createTemplateVariableNode = (options) => {
	const config = {
		theme: "blue",
		...options
	}

	return {
		get button() {
			return {
				id: config.name,
				icon: config.icon,
				label: window.panel?.$t?.(config.label),
				name: config.name,
				inline: true
			}
		},

		get schema() {
			return {
				group: "inline",
				inline: true,
				atom: true,
				selectable: false,
				attrs: {
					variable: {
						default: config.variable
					}
				},
				leafText: (node) => `{{ ${node.attrs.variable} }}`,
				parseDOM: [
					{
						tag: `span[data-seo-template-variable="${config.variable}"]`,
						getAttrs: (dom) => ({
							variable: dom.dataset.seoTemplateVariable ?? config.variable
						})
					}
				],
				toDOM: (node) => [
					"span",
					{
						"data-seo-template-variable": node.attrs.variable
					},
					`{{ ${node.attrs.variable} }}`
				]
			}
		},

		commands({ type }) {
			return () => (state, dispatch) => {
				if (!dispatch) {
					return false
				}

				const { from, to } = state.selection
				const node = type.create({ variable: config.variable })
				const tr = state.tr

				tr.delete(from, to)
				tr.insert(from, node)
				tr.insertText(" ", from + node.nodeSize)

				const TextSelection = state.selection.constructor
				const cursor = from + node.nodeSize + 1
				tr.setSelection(TextSelection.near(tr.doc.resolve(cursor)))

				dispatch(tr.scrollIntoView())
				return true
			}
		},

		view(node) {
			const dom = document.createElement("span")
			dom.className = "k-seo-template-variable"
			dom.dataset.theme = config.theme
			dom.dataset.seoTemplateVariable = node.attrs.variable
			dom.setAttribute("contenteditable", "false")
			dom.textContent = window.panel?.$t?.(config.label)

			return {
				dom,
				update(updatedNode) {
					dom.dataset.seoTemplateVariable = updatedNode.attrs.variable
					dom.textContent = window.panel?.$t?.(config.label)
					return true
				},
				ignoreMutation: () => true
			}
		}
	}
}
