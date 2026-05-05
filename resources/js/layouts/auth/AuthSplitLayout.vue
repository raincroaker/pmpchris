<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
    logoSrc?: string;
    logoAlt?: string;
    logoClass?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-left">
                    <img
                        v-if="logoSrc"
                        :src="logoSrc"
                        :alt="logoAlt || 'Logo'"
                        decoding="async"
                        loading="eager"
                        class="mx-auto mb-7 h-auto w-full max-w-[270px] object-contain"
                        :class="logoClass"
                    />
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
        <div class="relative hidden h-full flex-col lg:flex dark:border-l">
            <div
                class="absolute inset-0 bg-[url('/images/PMPC.jpg')] bg-cover bg-center"
                aria-hidden="true"
            />
            <div
                class="absolute inset-0 bg-black/10 backdrop-blur-[0.8px]"
                aria-hidden="true"
            />
        </div>
    </div>
</template>
