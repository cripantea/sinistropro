<template>
  <div class="flex flex-col h-full">
    <div class="px-4 py-3 border-b border-gray-200 shrink-0">
      <h3 class="text-sm font-semibold text-gray-700">Chat</h3>
    </div>

    <div v-if="conversations.length === 0" class="flex-1 flex items-center justify-center px-4 text-center text-sm text-gray-400">
      Nessuna conversazione ancora. Quando un cliente scrive al numero collegato, la chat apparirà qui.
    </div>

    <ul v-else class="flex-1 overflow-y-auto divide-y divide-gray-100">
      <li
        v-for="c in conversations"
        :key="c.id"
        @click="$emit('select', c.id)"
        class="px-4 py-3 cursor-pointer transition-colors flex items-center gap-3"
        :class="c.id === selectedId ? 'bg-green-50' : 'hover:bg-gray-50'"
      >
        <div class="w-10 h-10 rounded-full bg-[#075E54] text-white flex items-center justify-center text-sm font-semibold shrink-0">
          {{ initials(c.contactName || c.phoneNumber) }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium text-gray-800 truncate">{{ c.contactName || formatPhone(c.phoneNumber) }}</span>
            <span v-if="c.lastMessageAt" class="text-[11px] text-gray-400 shrink-0">{{ timeAgo(c.lastMessageAt) }}</span>
          </div>
          <div class="flex items-center justify-between gap-2 mt-0.5">
            <span class="text-xs text-gray-500 truncate">{{ c.lastMessagePreview || '—' }}</span>
            <span
              v-if="c.unreadCount > 0"
              class="shrink-0 bg-green-500 text-white text-[11px] font-semibold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"
            >
              {{ c.unreadCount }}
            </span>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
export interface ConversationSummary {
  id: number
  phoneNumber: string
  contactName: string | null
  lastMessagePreview: string | null
  lastMessageAt: string | null
  unreadCount: number
}

defineProps<{ conversations: ConversationSummary[]; selectedId: number | null }>()
defineEmits<{ select: [id: number] }>()

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
}

function formatPhone(phone: string): string {
  return `+${phone}`
}

function timeAgo(iso: string): string {
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (seconds < 60) return 'ora'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes} min`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h`
  const days = Math.floor(hours / 24)
  return days === 1 ? 'ieri' : `${days}g`
}
</script>
