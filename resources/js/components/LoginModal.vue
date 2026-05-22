<script setup>
import AuthModal from '@/components/AuthModal.vue';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <AuthModal v-bind="$attrs" />
</template>
