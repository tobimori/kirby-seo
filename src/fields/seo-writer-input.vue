<script>
export default {
	extends: "k-writer-input",
	methods: {
		createNodes() {
			const data =
				// eslint-disable-next-line no-undef
				Vue.component("k-writer-input").options.methods.createNodes.call(this)
			return data.filter((ext) => ext.name !== "hardBreak")
		}
	}
}
</script>

<style>
.k-field-type-seo-writer {
	img.ProseMirror-separator {
		display: inline-block;
		width: 0;
		height: 0;
		margin: 0;
		padding: 0;
		border: 0;
		overflow: hidden;
	}

	br.ProseMirror-trailingBreak {
		display: none;
	}

	.k-writer-input .k-toolbar-button {
		padding-inline: var(--spacing-5);
		--button-width: auto;
		flex-basis: max-content;

		&::after {
			content: attr(title);
		}

		&:not(:first-child) {
			border-left: 1px solid var(--toolbar-border);
		}
	}
}
</style>
