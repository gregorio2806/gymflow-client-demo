<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import type { ChartPoint } from '@/types/dashboard';

const props = defineProps<{
    points: ChartPoint[];
    title: string;
    money?: boolean;
    bars?: boolean;
}>();
const gradient = useId();
const chart = ref<SVGSVGElement | null>(null);
const width = ref(620);
const height = ref(144);
let observer: ResizeObserver | null = null;
onMounted(() => {
    observer = new ResizeObserver(() => {
        const bounds = chart.value?.getBoundingClientRect();
        if (!bounds) return;
        width.value = Math.max(240, bounds.width);
        height.value = Math.max(96, bounds.height);
    });
    if (chart.value) observer.observe(chart.value);
});
onBeforeUnmount(() => observer?.disconnect());
const left = 48;
const right = computed(() => width.value - 12);
const bottom = computed(() => height.value - 26);
const plotHeight = computed(() => height.value - 40);
const labelStep = computed(() =>
    Math.max(
        1,
        Math.ceil(
            props.points.length /
                Math.max(3, Math.floor((width.value - 60) / 44)),
        ),
    ),
);
const top = computed(
    () => Math.max(1, ...props.points.map((point) => point.value)) * 1.2,
);
const positions = computed(() =>
    props.points.map((point, index) => ({
        ...point,
        x: props.bars
            ? left +
              ((index + 0.5) * (right.value - left)) / props.points.length
            : left +
              (index * (right.value - left)) /
                  Math.max(1, props.points.length - 1),
        y: bottom.value - (point.value / top.value) * plotHeight.value,
    })),
);
const line = computed(() =>
    positions.value
        .map((point, index) => `${index ? 'L' : 'M'}${point.x},${point.y}`)
        .join(' '),
);
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 2,
});
const display = (value: number) =>
    props.money ? currency.format(value / 100) : value.toLocaleString('en-PH');
const tick = (value: number) =>
    props.money
        ? value >= 100000
            ? `₱${Math.round(value / 100000)}k`
            : currency.format(value / 100)
        : String(Math.ceil(value));
</script>
<template>
    <figure class="@container">
        <svg
            ref="chart"
            :viewBox="`0 0 ${width} ${height}`"
            class="h-32 w-full overflow-visible text-primary md:h-36"
            aria-hidden="true"
        >
            <defs>
                <linearGradient :id="gradient" x1="0" x2="0" y1="0" y2="1">
                    <stop
                        offset="0%"
                        stop-color="currentColor"
                        stop-opacity=".2"
                    />
                    <stop
                        offset="100%"
                        stop-color="currentColor"
                        stop-opacity=".015"
                    />
                </linearGradient>
            </defs>
            <g v-for="i in 4" :key="i">
                <line
                    :x1="left"
                    :x2="right"
                    :y1="bottom - ((i - 1) * plotHeight) / 3"
                    :y2="bottom - ((i - 1) * plotHeight) / 3"
                    class="stroke-border"
                    stroke-dasharray="4 4"
                />
                <text
                    :x="left - 6"
                    :y="bottom + 4 - ((i - 1) * plotHeight) / 3"
                    text-anchor="end"
                    class="dashboard-axis fill-muted-foreground"
                >
                    {{ tick((top * (i - 1)) / 3) }}
                </text>
            </g>
            <template v-if="!bars">
                <path
                    v-if="positions.length > 1"
                    :d="`${line} L${right},${bottom} L${left},${bottom} Z`"
                    :fill="`url(#${gradient})`"
                />
                <path
                    :d="line"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                />
            </template>
            <g v-for="(point, index) in positions" :key="point.label">
                <rect
                    v-if="bars"
                    :x="point.x - Math.min(20, 400 / points.length) / 2"
                    :y="point.y"
                    :width="Math.min(20, 400 / points.length)"
                    :height="bottom - point.y"
                    rx="3"
                    fill="currentColor"
                    fill-opacity=".8"
                >
                    <title>{{ point.label }}: {{ display(point.value) }}</title>
                </rect>
                <circle
                    v-else
                    :cx="point.x"
                    :cy="point.y"
                    r="4"
                    fill="currentColor"
                    class="stroke-card"
                    stroke-width="1.5"
                >
                    <title>{{ point.label }}: {{ display(point.value) }}</title>
                </circle>
                <text
                    v-if="
                        index === points.length - 1 ||
                        (index % labelStep === 0 &&
                            index <= points.length - 1 - labelStep)
                    "
                    :x="point.x"
                    :y="height - 7"
                    text-anchor="middle"
                    class="dashboard-axis fill-muted-foreground"
                >
                    {{ point.label }}
                </text>
            </g>
        </svg>
        <figcaption class="sr-only">{{ title }}</figcaption>
        <details class="mt-1 text-xs text-muted-foreground">
            <summary
                class="flex min-h-11 cursor-pointer items-center md:min-h-9"
            >
                View chart data
            </summary>
            <ul class="grid grid-cols-2 gap-2 p-2 sm:grid-cols-3">
                <li v-for="point in points" :key="point.label">
                    {{ point.label }}: {{ display(point.value) }}
                </li>
            </ul>
        </details>
    </figure>
</template>

<style scoped>
.dashboard-axis {
    font-size: var(--dashboard-axis-size, 11px);
}
</style>
