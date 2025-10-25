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
export default {
	extends: "k-writer-field",
	props: {
		ai: [String, Boolean]
	},
	computed: {
		buttons() {
			if (!this.ai || typeof this.ai !== "string" || this.ai === "false")
				return []

			return [
				{
					icon: "seo-ai", // "loader",  - for loading
					text: "Write with AI",
					disabled: false, // true, - for loading
					click: () => {}
				},
				{
					icon: "angle-dropdown"
				}
			]
		}
	}
}
</script>
