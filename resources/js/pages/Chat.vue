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
                    <!-- Messages will go here -->
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
                        <!-- User messages - right side, blue bubble -->
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

                        <!-- AI messages - left side, gray bubble -->
                        <div v-else class="flex justify-start">
                            <div
                                class="max-w-[80%] rounded-lg bg-gray-200 px-4 py-2"
                            >
                                <!-- Loading animation while AI is thinking -->
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

                                <!-- Actual message text -->
                                <div v-else>{{ msg.content }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <!-- Form will go here -->
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

<script setup lang="ts">
import { ref, nextTick } from 'vue';

const messages = ref([]);

const newMessage = ref('');

const isLoading = ref(false);

const messagesContainer = ref(null);

const sendMessage = async () => {
    // Guard clause: exit if input empty or already loading
    if (!newMessage.value.trim() || isLoading.value) return;

    // Capture the message before clearing input
    const userMessage = newMessage.value.trim();

    // Add user message to conversation (appears immediately on right)
    messages.value.push({
        role: 'user',
        content: userMessage,
    });

    // Clear the input field for next message
    newMessage.value = '';

    // Add placeholder for AI response with loading state
    // Store the index so we can update it later with the actual response
    const loadingIndex =
        messages.value.push({
            role: 'assistant',
            content: '',
            isLoading: true,
        }) - 1;

    // Lock the UI to prevent double-sends
    isLoading.value = true;

    try {
        // Wait for Vue to update DOM, then scroll to show new messages
        await nextTick();
        scrollToBottom();

        // Call the backend API
        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')
                    .content,
            },
            body: JSON.stringify({ message: userMessage }),
        });

        const data = await response.json();

        console.log(data);

        // Replace loading placeholder with actual AI response
        if (data.success) {
            messages.value[loadingIndex] = {
                role: 'assistant',
                content: data.message,
                isLoading: false,
            };
        } else {
            // Handle API error response
            messages.value[loadingIndex] = {
                role: 'assistant',
                content: 'Error: ' + data.error,
                isLoading: false,
            };
        }
    } catch (error) {
        console.log(error);
        // Handle network or other errors
        messages.value[loadingIndex] = {
            role: 'assistant',
            content: 'Error: Failed to send message',
            isLoading: false,
        };
    } finally {
        // Always unlock the UI when done (success or error)
        isLoading.value = false;
        await nextTick();
        scrollToBottom();
    }
};

// Auto-scroll to show the newest messages
const scrollToBottom = () => {
    if (messagesContainer.value) {
        // Set scroll position to the total height of content (bottom)
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight;
    }
};
</script>

<style scoped></style>
