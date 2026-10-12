---
name: ui-component
description: Use when building any user-facing screen or component.
---
1. Use only tokens from Section 7 (CSS variables / Tailwind theme). No raw hex in Blade.
2. Reuse `resources/views/components/ui/*` (button, input, select, card, badge, table, modal, tabs, stat, empty-state, skeleton, toast, amount, asset-icon, pnl).
3. Every screen ships: loading skeleton, empty state, error state, mobile layout (≥ 360px), keyboard focus states, aria labels.
4. Numbers: tabular-nums, right-aligned in tables, formatted with <x-ui.amount> (asset precision aware). Gains `text-up`, losses `text-down`, never color alone — add ▲/▼ or +/− sign.
5. Realtime values update via Echo listeners with a 300ms flash (bg-up/10 or bg-down/10).
6. Verify at 360, 768, 1280, 1920 widths before marking done.
