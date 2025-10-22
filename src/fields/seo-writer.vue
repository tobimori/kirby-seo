<template>
	<k-field
		v-bind="$props"
		:counter="counterOptions"
		:class="['k-writer-field', $attrs.class]"
		:input="id"
		:style="$attrs.style"
	>
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
	extends: "k-writer-field"
}
</script>
