<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import {
  ArcElement,
  CategoryScale,
  Chart as ChartJS,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Tooltip, Legend, Filler)

// ── Types ──────────────────────────────────────────────────
interface StatusStat {
  id: number
  name: string
  color: string
  count: number
  is_closed: boolean
}

interface MonthlyData {
  month: string  // 'YYYY-MM'
  count: number
}

const props = defineProps<{
  stats: {
    total: number
    open: number
    closed: number
    todayReminders: number
    statuses: StatusStat[]
  }
  trend: MonthlyData[]
}>()

// ── Month formatter ────────────────────────────────────────
const MONTHS_IT = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic']

function formatMonth(ym: string): string {
  const [y, m] = ym.split('-')
  return `${MONTHS_IT[parseInt(m) - 1]} '${y.slice(2)}`
}

// ── Stat cards ─────────────────────────────────────────────
const cards = computed(() => [
  {
    label: 'Pratiche Totali',
    value: props.stats.total,
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    bg: 'bg-indigo-50',
    iconColor: 'text-indigo-600',
    valueColor: 'text-indigo-700',
  },
  {
    label: 'Pratiche Aperte',
    value: props.stats.open,
    icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    bg: 'bg-sky-50',
    iconColor: 'text-sky-600',
    valueColor: 'text-sky-700',
  },
  {
    label: 'Pratiche Chiuse',
    value: props.stats.closed,
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    bg: 'bg-emerald-50',
    iconColor: 'text-emerald-600',
    valueColor: 'text-emerald-700',
  },
  {
    label: 'Avvisi Oggi',
    value: props.stats.todayReminders,
    icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    bg: 'bg-amber-50',
    iconColor: 'text-amber-600',
    valueColor: 'text-amber-700',
  },
])

// ── Chart refs ─────────────────────────────────────────────
const trendCanvas = ref<HTMLCanvasElement | null>(null)
const donutCanvas = ref<HTMLCanvasElement | null>(null)
let trendChart: ChartJS | null = null
let donutChart:  ChartJS | null = null

const hasData = computed(() => props.stats.total > 0)

function buildTrendChart() {
  if (!trendCanvas.value || !props.trend.length) return
  trendChart?.destroy()

  const labels = props.trend.map(d => formatMonth(d.month))
  const data   = props.trend.map(d => d.count)

  trendChart = new ChartJS(trendCanvas.value, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Pratiche create',
        data,
        fill: true,
        tension: 0.4,
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99,102,241,0.12)',
        pointBackgroundColor: '#6366f1',
        pointRadius: 4,
        pointHoverRadius: 6,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.parsed.y} pratiche`,
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#94a3b8', font: { size: 11 } },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: '#94a3b8',
            font: { size: 11 },
            stepSize: 1,
            callback: v => Number.isInteger(v) ? v : '',
          },
          grid: { color: 'rgba(148,163,184,0.15)' },
        },
      },
    },
  })
}

function buildDonutChart() {
  if (!donutCanvas.value || !props.stats.statuses.length) return
  donutChart?.destroy()

  const statuses = props.stats.statuses.filter(s => s.count > 0)
  if (!statuses.length) return

  donutChart = new ChartJS(donutCanvas.value, {
    type: 'doughnut',
    data: {
      labels: statuses.map(s => s.name),
      datasets: [{
        data: statuses.map(s => s.count),
        backgroundColor: statuses.map(s => s.color),
        borderWidth: 2,
        borderColor: '#fff',
        hoverBorderWidth: 3,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            padding: 16,
            usePointStyle: true,
            pointStyleWidth: 8,
            font: { size: 12 },
            color: '#475569',
          },
        },
        tooltip: {
          callbacks: {
            label: ctx => {
              const pct = props.stats.total
                ? Math.round((ctx.parsed / props.stats.total) * 100)
                : 0
              return ` ${ctx.parsed} pratiche (${pct}%)`
            },
          },
        },
      },
    },
  })
}

onMounted(() => {
  buildTrendChart()
  buildDonutChart()
})

// Rebuild charts if props change (e.g. page re-visit)
watch(() => props.trend, buildTrendChart)
watch(() => props.stats.statuses, buildDonutChart)

onBeforeUnmount(() => {
  trendChart?.destroy()
  donutChart?.destroy()
})
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <div class="p-6 space-y-6">

      <!-- ── Page title ──────────────────────────────────────── -->
      <div>
        <h1 class="text-lg font-semibold text-slate-800">Dashboard</h1>
        <p class="text-sm text-slate-500 mt-0.5">Panoramica delle attività del tuo workspace.</p>
      </div>

      <!-- ── Stat cards ──────────────────────────────────────── -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div
          v-for="card in cards"
          :key="card.label"
          class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex items-start gap-4"
        >
          <div :class="[card.bg, 'p-2.5 rounded-xl shrink-0']">
            <svg class="w-5 h-5" :class="card.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.icon"/>
            </svg>
          </div>
          <div>
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ card.label }}</p>
            <p class="text-2xl font-bold mt-0.5" :class="card.valueColor">{{ card.value }}</p>
          </div>
        </div>
      </div>

      <!-- ── Empty state (no data yet) ──────────────────────── -->
      <div
        v-if="!hasData"
        class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center"
      >
        <svg class="w-12 h-12 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <h3 class="text-sm font-semibold text-slate-600 mb-1">Nessuna pratica ancora</h3>
        <p class="text-xs text-slate-400">I grafici appariranno non appena verranno create le prime pratiche.</p>
      </div>

      <!-- ── Charts row ──────────────────────────────────────── -->
      <div v-else class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        <!-- Trend (area) -->
        <div class="xl:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm font-semibold text-slate-800">Andamento Pratiche</h2>
              <p class="text-xs text-slate-400 mt-0.5">Ultimi 12 mesi</p>
            </div>
            <div class="w-2.5 h-2.5 rounded-full bg-indigo-500"></div>
          </div>
          <div class="h-56">
            <canvas ref="trendCanvas" />
          </div>
        </div>

        <!-- Distribution (doughnut) -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm font-semibold text-slate-800">Distribuzione per Stato</h2>
              <p class="text-xs text-slate-400 mt-0.5">{{ stats.total }} pratiche totali</p>
            </div>
          </div>
          <div class="h-56">
            <canvas ref="donutCanvas" />
          </div>
        </div>

      </div>

      <!-- ── Status breakdown table ─────────────────────────── -->
      <div v-if="hasData && stats.statuses.length" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-800">Dettaglio per Stato</h2>
        </div>
        <div class="divide-y divide-slate-100">
          <div
            v-for="s in stats.statuses"
            :key="s.id"
            class="flex items-center gap-4 px-5 py-3"
          >
            <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: s.color }"></div>
            <span class="text-sm text-slate-700 flex-1">{{ s.name }}</span>
            <div class="flex items-center gap-3">
              <!-- Progress bar -->
              <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden hidden sm:block">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{
                    width: stats.total ? `${Math.round((s.count / stats.total) * 100)}%` : '0%',
                    backgroundColor: s.color,
                  }"
                />
              </div>
              <span class="text-sm font-semibold text-slate-700 tabular-nums w-6 text-right">{{ s.count }}</span>
              <span class="text-xs text-slate-400 tabular-nums w-8 text-right">
                {{ stats.total ? Math.round((s.count / stats.total) * 100) : 0 }}%
              </span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>
