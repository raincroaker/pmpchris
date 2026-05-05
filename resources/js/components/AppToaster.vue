<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import { APP_TOASTER_ID, APP_TOASTER_PROPS } from '@/config/app-toast';
</script>

<template>
    <Toaster :id="APP_TOASTER_ID" v-bind="APP_TOASTER_PROPS" />
</template>
