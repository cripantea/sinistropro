<template>
  <span :class="badgeClass" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ role: string }>()

const roleMap: Record<string, { label: string; cls: string }> = {
  'superadmin':   { label: 'Superadmin',   cls: 'bg-red-100 text-red-700' },
  'tenant-admin': { label: 'Tenant Admin', cls: 'bg-indigo-100 text-indigo-700' },
  'user':         { label: 'User',         cls: 'bg-slate-100 text-slate-600' },
}

const badgeClass = computed(() => roleMap[props.role]?.cls ?? 'bg-gray-100 text-gray-600')
const label      = computed(() => roleMap[props.role]?.label ?? props.role)
</script>
