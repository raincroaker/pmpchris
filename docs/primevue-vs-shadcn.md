# PrimeVue vs shadcn-vue (curated list)

When to use PrimeVue (complex data handling) vs shadcn-vue (customizability and design-system alignment). PrimeVue is already installed and registered with the **Aura** theme preset in `resources/js/app.ts` (`@primeuix/themes/aura`, `darkModeSelector: 'html.dark'`). Use **Tailwind / shared theme tokens** so data-heavy views sit visually next to shadcn-vue layout and forms; add **pass-through (`pt`)** or small overrides when a control needs a closer match.

---

## Prefer PrimeVue for (complex data / feature-rich)

### Data display and grids

| Component | Use for | Why PrimeVue over shadcn |
|-----------|---------|---------------------------|
| **TreeTable** | Hierarchical rows (e.g. parent/child records) with expand/collapse and columns | shadcn has no tree-table. |
| **Tree** | Tree view (folders, categories, nested options) with checkboxes/lazy load | shadcn has no Tree. |
| **DataView** | Switchable list/grid layouts over the same dataset with pagination | shadcn has no equivalent. |
| **OrderList** / **PickList** | Reorderable lists or dual-list pick (source → target) | shadcn has no built-in reorder/pick. |

### Selection and autocomplete

| Component | Use for | Why PrimeVue over shadcn |
|-----------|---------|---------------------------|
| **AutoComplete** | Type-ahead with async suggestions, virtual scroll, option groups | shadcn Combobox/Command are more minimal; PrimeVue fits large/async datasets. |
| **MultiSelect** | Multi-select with chips, filter, select-all, virtual scroll | shadcn Select is single-select focused; PrimeVue handles multi-select and density. |
| **Chips** | Input as tags/chips with add/remove | shadcn has Tags Input; PrimeVue Chips is an alternative for tokenized input. |

### Files and media

| Component | Use for | Why PrimeVue over shadcn |
|-----------|---------|---------------------------|
| **FileUpload** | Drag-drop, multi-file, progress, validation, custom/advanced UI | shadcn has no full upload component. |
| **Galleria** | Image gallery with thumbnails, indicators, fullscreen | shadcn Carousel is simpler; PrimeVue for full gallery UX. |
| **ImageCompare** | Before/after image slider | shadcn has no equivalent. |

### Charts and visualization

| Component | Use for | Why PrimeVue over shadcn |
|-----------|---------|---------------------------|
| **Chart** | Line, bar, pie, doughnut, radar, polar (Chart.js-based) | Built-in; use for dashboards and reports. shadcn Charts is extended/separate. |

### Structure and hierarchy

| Component | Use for | Why PrimeVue over shadcn |
|-----------|---------|---------------------------|
| **OrganizationChart** | Org hierarchy / tree of nodes (e.g. reporting structure) | shadcn has no equivalent. |
| **Timeline** | Vertical/horizontal event timeline with markers | shadcn has no Timeline. |

### Other rich controls

| Component | Use for | Why PrimeVue over shadcn |
|-----------|---------|---------------------------|
| **ColorPicker** | Color selection (swatch, overlay, inline) | shadcn has no ColorPicker. |
| **Rating** | Star (or custom) rating input | shadcn has no Rating. |
| **Knob** | Radial numeric input (e.g. dial) | shadcn has no equivalent. |
| **VirtualScroller** | Large lists with virtual scrolling | Use with list UIs when you need performance; shadcn has no built-in virtual scroller. |

---

## What stays with shadcn-vue

Use shadcn-vue (Reka UI) for: **layout** (Sidebar, Sheet, Dialog), **navigation** (Dropdown Menu, Tabs, Breadcrumb), **simple forms** (Input, Label, Button, Checkbox, Select for basic single-select), **feedback** (Toast/Sonner, Alert), **tabular data tables** (see below), and any UI where customizability and design-system control matter more than a monolithic third-party grid.

**Data tables / list pages (required):** build sortable, filterable, paginated **tables** with **`@tanstack/vue-table`** (`useVueTable`, `ColumnDef`, `FlexRender`, row models) and the project’s **shadcn-vue `Table`** primitives in [`resources/js/components/ui/table/`](../resources/js/components/ui/table/) (see [`utils.ts`](../resources/js/components/ui/table/utils.ts) for the `valueUpdater` helper used with TanStack state). Follow the official recipe: [shadcn-vue Data Table](https://www.shadcn-vue.com/docs/components/data-table) (headless table logic + your markup—not a single drop-in `<DataTable>`). **Do not** use PrimeVue **`DataTable`** for normal list or index pages; stay on one table stack with theme tokens next to the rest of the shell.

**Dates and calendars on forms (required):** use the project’s **shadcn-vue `Calendar`** pattern (Popover + `Calendar` from [`resources/js/components/ui/calendar/`](../resources/js/components/ui/calendar/) — same approach as the employee wizard and other forms). **Do not** use PrimeVue **DatePicker** for in-app forms or wizards; keep one date stack aligned with Reka + theme tokens. PrimeVue remains for **non-tabular** data components in **Prefer PrimeVue** above (e.g. TreeTable, Tree, DataView, PickList, FileUpload, Chart)—not for generic tabular lists or primary form date picking.
