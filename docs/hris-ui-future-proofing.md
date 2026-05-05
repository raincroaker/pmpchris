# HRIS UI: future-proofing and conventions

**Audience:** Everyone who touches the Inertia/Vue frontend (including AI agents).  
**Purpose:** Reduce drift, duplicate patterns, and silent failures when extending the shell, toasts, navigation, or branch context.

This document is the **canonical “how to extend safely”** guide. [`PROJECT_CHANGELOG.md`](../PROJECT_CHANGELOG.md) records **what shipped**; this doc records **rules and pitfalls**.

---

## Table of contents

1. [Toasts (Sonner)](#toasts-sonner)
2. [Shell and layouts](#shell-and-layouts)
3. [Sidebar navigation](#sidebar-navigation)
4. [Branch switcher](#branch-switcher)
5. [Tooling](#tooling)
6. [In-app notifications (removed)](#in-app-notifications-removed)
7. [Changelog vs this document](#changelog-vs-this-document)

---

## Toasts (Sonner)

### Rules

- **Must** use **`appToast`** from [`resources/js/lib/app-toast-client.ts`](../resources/js/lib/app-toast-client.ts) for any toast shown in the main authenticated app chrome (branch switcher, future actions, etc.).
- **Must not** import **`toast`** from **`vue-sonner`** in feature components, pages, or composables. The **only** module that may import `toast` from `vue-sonner` is **`app-toast-client.ts`** (the wrapper implementation).

### Why

- [`AppToaster.vue`](../resources/js/components/AppToaster.vue) mounts the UI `Toaster` with **`id`** = [`APP_TOASTER_ID`](../resources/js/config/app-toast.ts) (`'app'`).
- In vue-sonner, a Toaster with an **`id`** only displays toasts whose options include **`toasterId`** matching that id. The wrapper merges **`toasterId: APP_TOASTER_ID`** on every call.
- If you call raw **`toast()`** without **`toasterId`**, those toasts are associated with the **default** (unscoped) toaster path and **will not appear** on `AppToaster`—they look “broken” or invisible.

### Configuration

- Global toaster appearance and position: [`resources/js/config/app-toast.ts`](../resources/js/config/app-toast.ts) (`APP_TOASTER_PROPS`, `APP_TOASTER_POSITION`, etc.).
- Adjust **`offset`** here if **`top-center`** overlaps the fixed header (e.g. match `AppSidebarHeader` height).
- Global Sonner **CSS** (**required** for layout): `import 'vue-sonner/style.css'` in [`resources/js/app.ts`](../resources/js/app.ts). Do not remove it.

### Second toaster (e.g. modal-only or embed)

- **Must** use a **new** stable `id` (not `'app'`) on a separate `<Toaster />` instance.
- **Must** either:
  - add a **second** small wrapper (e.g. `modalToast`) that sets a different `toasterId`, or  
  - pass **`toasterId`** explicitly on each call (error-prone).
- **Must not** mount two `<Toaster :id="APP_TOASTER_ID" />` instances.

### `dismiss`

- `appToast.dismiss(id)` forwards to vue-sonner. Behavior (global vs per-toaster) depends on the library version—re-verify when upgrading **`vue-sonner`**.

### Wrapper coverage

- `appToast` exposes the methods implemented in [`app-toast-client.ts`](../resources/js/lib/app-toast-client.ts). If vue-sonner adds new APIs, **extend the wrapper** in one place rather than scattering raw imports.

---

## Shell and layouts

- [`AppShell.vue`](../resources/js/components/AppShell.vue) is the **single** place [`AppToaster.vue`](../resources/js/components/AppToaster.vue) is mounted for the main HRIS chrome.
- Layouts that use **`AppLayout`** → sidebar layout → **`AppShell`** inherit the toaster automatically.
- Any **future layout** that **does not** render `AppShell` (minimal page, public view, iframe) **must not** assume `appToast` is visible unless you mount a toaster there or use a different UX (inline alert, etc.).

---

## Sidebar navigation

- Collapsible **open/closed** state is stored in **sessionStorage** via [`useSidebarNavOpenState.ts`](../resources/js/composables/useSidebarNavOpenState.ts). Keys are derived from **section `title`** strings in [`app-navigation.ts`](../resources/js/config/app-navigation.ts).
- **Risk:** Renaming a collapsible’s **title** leaves **stale keys** in storage (usually harmless but confusing). Prefer **stable** identifiers in config if you refactor (e.g. optional `storageKey` in the tree—would require a small code change).
- Multiple instances of [`AppSidebarNav.vue`](../resources/js/components/AppSidebarNav.vue) (e.g. sidebar + mobile sheet) **intentionally share** the same reactive map—do not fork state unless product requires it.
- **Admin visibility:** [`filterTree`](../resources/js/components/AppSidebarNav.vue) and Inertia prop **`showHrAdminNav`** must stay aligned with backend rules (e.g. [`HandleInertiaRequests`](../app/Http/Middleware/HandleInertiaRequests.php)) to avoid showing links that 403 or hiding allowed routes.

---

## Branch switcher

- Today: [`BranchSwitcher.vue`](../resources/js/components/BranchSwitcher.vue) uses **local** `ref` state for `branches` and `selectedBranch`; toasts are **UX-only** (`appToast.promise` / `appToast.success`).
- **Future:** Persist **current branch** in a **global store** (or server session) + API; UI toasts should **reflect** domain changes, not replace authoritative state.

---

## Tooling

- **Wayfinder:** After adding or renaming **named Laravel routes**, run **`php artisan wayfinder:generate`** so [`resources/js/routes`](../resources/js/routes) matches [`routes/web.php`](../routes/web.php). Broken Wayfinder imports fail at build/typecheck.
- **UI stacks:** PrimeVue vs shadcn-vue guidance lives in [`primevue-vs-shadcn.md`](primevue-vs-shadcn.md). Avoid mixing incompatible primitives for the same concern without a deliberate rule.

---

## In-app notifications (removed)

- The HRIS **mock** notifications UI (header bell, popover, `/notifications` page) was removed. If you add notifications again, use **one** data pipeline (e.g. Laravel notifications + Inertia shared props or API) and a single place for unread counts and lists.

---

## Changelog vs this document

| Artifact | Role |
|----------|------|
| [`PROJECT_CHANGELOG.md`](../PROJECT_CHANGELOG.md) | Dated product / UI **changes** (“what we built”). |
| **This file** | **Conventions and hazards** (“how to extend without breaking things”). |

Update the changelog when behavior **ships**; update this doc when you **change rules** (e.g. second toaster, branch store).
