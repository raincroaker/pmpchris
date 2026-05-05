---
name: pmpchris-hris-stack
description: Reference for the HRIS app (Laravel 12, Vue 3, Inertia, TypeScript, Tailwind 4, Reka UI, Fortify, Wayfinder, PrimeVue). Use when implementing features, fixing bugs, answering stack/routing/auth/UI questions, using MCP, or working with docs and PrimeVue (TreeTable, Tree, FileUpload, Chart, etc.; see docs/primevue-vs-shadcn.md). Tabular tables: @tanstack/vue-table + shadcn Table, not PrimeVue DataTable. Form dates: shadcn-vue Calendar, not PrimeVue DatePicker.
---

# HRIS project stack (Laravel + Vue 3 + Inertia)

## Stack at a glance

| Layer | Tech |
|-------|------|
| Backend | Laravel 12, PHP 8.2+, Fortify, Inertia server, Wayfinder |
| Frontend | Vue 3, TypeScript, Inertia (Vue 3), Vite 7 |
| UI | Reka UI, Tailwind 4, Lucide Vue, VeeValidate + Zod, CVA, tailwind-merge, clsx |
| Complex UI | PrimeVue – TreeTable, Tree, DataView, OrderList, PickList, AutoComplete, MultiSelect, FileUpload, Chart, OrganizationChart, Timeline, etc.; **Aura-themed** in `resources/js/app.ts`. **Tabular tables:** `@tanstack/vue-table` + shadcn-vue **`Table`** (`resources/js/components/ui/table`). Form **dates** use **shadcn-vue Calendar** (see rule / docs). |
| Other UI | vaul-vue, embla-carousel-vue, vue-input-otp, vue-sonner |

## Documentation

- **Project:** `PROJECT_CHANGELOG.md` and `docs/` or root `*.md` for what's built and key files.
- **External:** Prefer official docs for Laravel, Vue, Inertia, Tailwind, Reka UI, PrimeVue. When MCP doc tools are available, use them for doc-heavy answers.
- **Updates:** After meaningful feature or architecture changes, update `PROJECT_CHANGELOG.md` or relevant docs (curated, not exhaustive).

## MCP

- **Use when:** The task benefits from external data (docs, DB, browser, etc.). Prefer MCP over unsourced assertions when a relevant tool is available.
- **Config:** `~/.cursor/mcp.json` (global) or `.cursor/mcp.json` (project). Do not assume a server or tool exists; use only what is listed or exposed.
- **Before calling a tool:** List or read the tool's schema/descriptor and call with correct parameters.
- **Doc-heavy questions:** Prefer available MCP doc tools when the question is about library/framework documentation.

## PrimeVue

- **Prefer PrimeVue for (vs shadcn-vue):** Specialized data grids (TreeTable, Tree, DataView, OrderList, PickList). Selection (AutoComplete, MultiSelect, Chips). Files/media (FileUpload, Galleria, ImageCompare). Charts (Chart). Structure (OrganizationChart, Timeline). Other (ColorPicker, Rating, Knob, VirtualScroller). **Tabular DataTables / list pages:** **`@tanstack/vue-table`** + shadcn-vue **`Table`** ([`docs/primevue-vs-shadcn.md`](../../../docs/primevue-vs-shadcn.md), [shadcn-vue Data Table](https://www.shadcn-vue.com/docs/components/data-table))—**not** PrimeVue DataTable. Use shadcn-vue for layout, **simple forms**, **navigation**, and **form date/calendar** fields (**`Calendar`** + Popover — **not** PrimeVue DatePicker).
- **Role:** Already installed; use for PrimeVue-listed controls; tabular lists use TanStack + shadcn Table per doc.
- **Theming:** **Aura** preset (`@primeuix/themes/aura`) in `resources/js/app.ts`—styled PrimeVue; align dense data UIs with shadcn-vue using shared **theme tokens** and Tailwind utilities; use `pt` / scoped CSS when a component needs tighter match.
- **Reference:** Use official PrimeVue docs (or MCP if available) for API and examples; full curated list in docs/primevue-vs-shadcn.md.
- **Docs for AI:** Prefer PrimeVue MCP. Fallback URLs: index `https://primevue.org/llms/llms.txt`; per-component `https://primevue.org/<component>.md` (see rule).

## Key paths and patterns

- **Alias:** `@/` → `resources/js/`.
- **Routes:** Wayfinder from `@/routes` (e.g. `dashboard()`, `login()`). Active state: `toUrl()` (supports `.url` / `.href`) and `useCurrentUrl()`.
- **Auth:** Fortify in `config/fortify.php`. Default landing and logout: `/login` (`routes/web.php` + Fortify redirects).
- **Layouts:** AppSidebarHeader (fixed), AppSidebar, SidebarFooter (branch switcher). Auth: AuthSplitLayout (form + image panel).

## How to prompt for this project

- **Be specific:** Name the component and library (e.g. TanStack Table + shadcn Table for a position list; PrimeVue TreeTable; Aura theme; or shadcn-vue Calendar for form dates) and integration (e.g. VeeValidate + Zod).
- **Reference project docs** when choosing components: e.g. "Per docs/primevue-vs-shadcn.md use @tanstack/vue-table and @/components/ui/table for the data grid."
- **For large or ambiguous features,** ask for a plan first (routes, DB, UI, then implement step by step).
- **To keep consistency,** state constraints: e.g. "use theme tokens only" or "no new UI libs."
- **When you want a paper trail,** ask to update PROJECT_CHANGELOG or relevant docs after implementing.
- **For API or library details,** ask to use MCP or official docs (e.g. PrimeVue MCP) when available.

Example prompts: [docs/cursor-prompts.md](docs/cursor-prompts.md).

## Reference

- **Changelog and key files:** `PROJECT_CHANGELOG.md` in the repo root.
