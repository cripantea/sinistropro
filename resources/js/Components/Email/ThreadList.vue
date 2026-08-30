<template>
  <div class="flex flex-col h-full">
    <div class="px-4 py-3 border-b border-gray-200 shrink-0 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-gray-700">Email</h3>
      <button
        type="button"
        @click="$emit('compose')"
        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
      >
        + Nuovo
      </button>
    </div>

    <div v-if="threads.length === 0" class="flex-1 flex items-center justify-center px-4 text-center text-sm text-gray-400">
      Nessuna email ancora. Quando arriva un messaggio nella casella collegata, comparirà qui.
    </div>

    <ul v-else class="flex-1 overflow-y-auto divide-y divide-gray-100">
      <li
        v-for="t in threads"
        :key="t.id"
        @click="$emit('select', t.id)"
        class="px-4 py-3 cursor-pointer transition-colors flex items-center gap-3"
        :class="t.id === selectedId ? 'bg-indigo-50' : 'hover:bg-gray-50'"
      >
        <div class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-semibold shrink-0">
          {{ initials(t.counterpartName || t.counterpartEmail) }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium text-gray-800 truncate">{{ t.counterpartName || t.counterpartEmail }}</span>
            <span v-if="t.lastMessageAt" class="text-[11px] text-gray-400 shrink-0">{{ timeAgo(t.lastMessageAt) }}</span>
          </div>
          <div class="flex items-center justify-between gap-2 mt-0.5">
            <span class="text-xs text-gray-500 truncate">{{ t.subject || t.lastMessagePreview || '—' }}</span>
            <span
              v-if="t.unreadCount > 0"
              class="shrink-0 bg-indigo-600 text-white text-[11px] font-semibold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"
            >
              {{ t.unreadCount }}
            </span>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
export interface ThreadSummary {
  id: number
  counterpartEmail: string
  counterpartName: string | null
  subject: string | null
  lastMessagePreview: string | null
  lastMessageAt: string | null
  unreadCount: number
}

defineProps<{ threads: ThreadSummary[]; selectedId: number | null }>()
defineEmits<{ select: [id: number]; compose: [] }>()

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
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
