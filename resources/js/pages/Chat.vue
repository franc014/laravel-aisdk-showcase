<template>
    <div class="min-h-screen bg-gray-50 p-4">
        <div class="mx-auto max-w-3xl">
            <!-- Header -->
            <div class="mb-6 rounded-lg bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-bold">AI Chat</h1>
                <p class="text-gray-600">Ask me anything!</p>
            </div>

            <!-- Messages Area -->
            <div class="mb-4 rounded-lg bg-white shadow-sm">
                <div ref="messagesContainer" class="h-96 overflow-y-auto p-4">
                    <div
                        v-if="messages.length === 0"
                        class="flex h-full items-center justify-center text-gray-400"
                    >
                        Start a conversation by typing below...
                    </div>

                    <div
                        v-for="(msg, index) in messages"
                        :key="index"
                        class="mb-4"
                    >
                        <!-- User messages -->
                        <div
                            v-if="msg.role === 'user'"
                            class="flex justify-end"
                        >
                            <div
                                class="max-w-[80%] rounded-lg bg-blue-600 px-4 py-2 text-white"
                            >
                                {{ msg.content }}
                            </div>
                        </div>

                        <!-- AI messages -->
                        <div v-else class="flex justify-start">
                            <div
                                class="max-w-[80%] rounded-lg bg-gray-200 px-4 py-2"
                            >
                                <!-- Loading animation -->
                                <div
                                    v-if="msg.isLoading"
                                    class="flex space-x-1"
                                >
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-gray-400"
                                    ></div>
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-gray-400"
                                        style="animation-delay: 0.1s"
                                    ></div>
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-gray-400"
                                        style="animation-delay: 0.2s"
                                    ></div>
                                </div>
                                <!-- Message content -->
                                <div v-else>{{ msg.content }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <form @submit.prevent="sendMessage" class="flex space-x-2">
                    <input
                        v-model="newMessage"
                        type="text"
                        placeholder="Type your message..."
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                        :disabled="isLoading"
                    />
                    <button
                        type="submit"
                        :disabled="isLoading || !newMessage.trim()"
                        class="rounded-lg bg-blue-600 px-6 py-2 text-white disabled:bg-gray-400"
                    >
                        Send
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';

const messages = ref([]);
const newMessage = ref('');
const isLoading = ref(false);
const messagesContainer = ref(null);

const sendMessage = async () => {
    if (!newMessage.value.trim() || isLoading.value) return;

    const userMessage = newMessage.value.trim();

    // Add user message
    messages.value.push({
        role: 'user',
        content: userMessage,
    });

    newMessage.value = '';

    // Add loading placeholder for AI response
    const loadingIndex =
        messages.value.push({
            role: 'assistant',
            content: '',
            isLoading: true,
        }) - 1;

    isLoading.value = true;

    try {
        await nextTick();
        scrollToBottom();

        // Use streaming endpoint
        const response = await fetch(
            `/api/stream-chat?message=${encodeURIComponent(userMessage)}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'text/event-stream',
                },
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Get the reader from the response body stream
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        // Remove loading state once we start receiving data
        messages.value[loadingIndex].isLoading = false;

        while (true) {
            const { done, value } = await reader.read();

            if (done) break;

            // Decode the chunk and add to buffer
            buffer += decoder.decode(value, { stream: true });

            // Process complete lines from buffer
            const lines = buffer.split('\n');
            buffer = lines.pop(); // Keep incomplete line in buffer

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    const data = line.slice(6);

                    if (data === '[DONE]') {
                        // Stream complete
                        continue;
                    }

                    try {
                        const parsed = JSON.parse(data);

                        if (parsed.text) {
                            // Append new text chunk to message
                            messages.value[loadingIndex].content += parsed.text;
                            await nextTick();
                            scrollToBottom();
                        }
                    } catch (e) {
                        // Not valid JSON, might be plain text
                        messages.value[loadingIndex].content += data;
                    }
                }
            }
        }

        // Process any remaining data in buffer
        if (buffer.startsWith('data: ')) {
            const data = buffer.slice(6);
            if (data && data !== '[DONE]') {
                try {
                    const parsed = JSON.parse(data);
                    if (parsed.text) {
                        messages.value[loadingIndex].content += parsed.text;
                    }
                } catch (e) {
                    messages.value[loadingIndex].content += data;
                }
            }
        }
    } catch (error) {
        console.error('Stream error:', error);
        messages.value[loadingIndex] = {
            role: 'assistant',
            content: 'Error: Failed to get response from AI',
            isLoading: false,
        };
    } finally {
        isLoading.value = false;
        await nextTick();
        scrollToBottom();
    }
};

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight;
    }
};
</script>

<style scoped></style>
