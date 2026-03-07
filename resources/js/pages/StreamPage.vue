<template>
    <div class="container mx-auto space-y-10 py-20">
        <h1 class="mb-4 text-4xl font-bold">Stream Page</h1>
        <p class="text-lg text-gray-700">
            This page is intended for testing streaming responses from the
            server.
        </p>
        <div v-if="data" class="rounded-lg bg-green-100 p-4 text-green-800">
            {{ data }}
        </div>
        <div v-if="isFetching">Connecting...</div>
        <div v-if="isStreaming">Generating...</div>
        <form @submit.prevent="submit">
            <textarea
                v-model="prompt"
                name="prompt"
                id="prompt"
                class="w-full rounded-lg border border-gray-300 p-4"
            ></textarea>
            <button
                type="submit"
                class="mt-4 rounded-lg bg-blue-600 px-6 py-3 text-white"
            >
                Submit
            </button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useStream } from '@laravel/stream-vue';

const csrfToken =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const { data, isFetching, isStreaming, send } = useStream('/stream', {
    csrfToken,
});

console.log({ data, isFetching, isStreaming });

const prompt = ref('');

async function submit() {
    await send({
        message: prompt.value,
        csrfToken,
    });

    console.log('Stream completed', data.value);
}
</script>

<style scoped></style>
