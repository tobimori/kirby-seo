<template>
	<div
		ref="editor"
		v-direction
		:class="['k-writer', 'k-writer-input', $attrs.class]"
		:data-disabled="disabled"
		:data-empty="isEmpty"
		:data-placeholder="placeholder"
		:spellcheck="spellcheck"
		:style="$attrs.style"
	>
		<k-writer-toolbar
			v-if="editor && !disabled"
			ref="toolbar"
			v-bind="toolbarOptions"
			@command="onCommand"
		/>

		<textarea
			:id="$props.id"
			ref="output"
			:name="name"
			:required="required"
			:value="value"
			class="input-hidden"
			tabindex="-1"
		/>
	</div>
</template>

<script>
// extends `k-writer-input` (async component), @see src/index.js
export default {
	methods: {
		createNodes() {
			const data = this.$options.extends.methods.createNodes.call(this)
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
		padding-inline: 1.25rem;
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
