# Cursor prompt examples

Example prompts that work well with this project's stack and rules. Use or adapt these when asking the AI to implement features, fix bugs, or look up docs.

---

## Example prompts

**Add a date field**

> Add a date-of-birth field on the profile form using **shadcn-vue Calendar** (Popover + `Calendar` from `resources/js/components/ui/calendar`, same pattern as the employee wizard), wired with VeeValidate + Zod. Do not use PrimeVue DatePicker.

*Why it works:* Matches the project rule and [`docs/primevue-vs-shadcn.md`](primevue-vs-shadcn.md): form dates stay on the Reka/shadcn stack; validation stack is explicit.

---

**Add a data table**

> Add a sortable employee table. Per docs/primevue-vs-shadcn.md use **@tanstack/vue-table** with **@/components/ui/table** (FlexRender, column defs), following the [shadcn-vue Data Table](https://www.shadcn-vue.com/docs/components/data-table) guide. Match pagination to the page’s data pattern (e.g. Inertia props vs client `getPaginationRowModel`). Use theme tokens so it aligns with cards and the shell. Do not use PrimeVue DataTable.

*Why it works:* Matches the project doc: tabular lists stay on TanStack + shadcn Table; behavior and data wiring are explicit.

---

**New feature with a plan first**

> I want to add leave requests (apply, approve, list). Make a plan first: routes, DB, UI (which components from our stack), then we implement step by step.

*Why it works:* Ensures scope and stack alignment before any code; the AI can use PROJECT_CHANGELOG and the rule to stay consistent.

---

**Fix with constraints**

> Fix the layout on the dashboard on mobile. Keep using our theme tokens (e.g. bg-background, border-sidebar-border) and existing sidebar/header components; no new UI libs.

*Why it works:* Keeps styling and structure consistent and avoids introducing new dependencies.

---

**Implement and document**

> Add an employee list page with a TanStack-backed table (@tanstack/vue-table + @/components/ui/table, per docs/primevue-vs-shadcn.md). After implementing, add a short entry to PROJECT_CHANGELOG and mention the new route and page.

*Why it works:* Builds a paper trail; the rule already says to update PROJECT_CHANGELOG when adding features, and asking for it in the prompt makes it explicit.

---

**Use MCP for docs**

> Use MCP or official docs for **@tanstack/vue-table** (sorting, pagination APIs) and the [shadcn-vue Data Table](https://www.shadcn-vue.com/docs/components/data-table) pattern, then implement a sortable, paginated table with @/components/ui/table.

*Why it works:* Pushes up-to-date TanStack/shadcn guidance for tables; PrimeVue DataTable is out of scope for this stack’s tabular lists.

---

**List DB tables (mysql-manager)**

> Use the mysql-manager MCP to list tables in the pmpchris database, then summarize the schema for the users table.

*Why it works:* Uses project-specific MCP for live DB introspection; the rule says to prefer MCP when the task benefits from external data.

---

**Review for consistency**

> Review the new [component/page] for consistency with our theme (theme tokens, no raw colors) and conventions (Wayfinder routes, toUrl for active state). Fix any mismatches.

*Why it works:* Asks for an explicit check against the rule’s styling and routing conventions.

---

## Context the AI already has

The rule (`.cursor/rules/pmpchris-stack.mdc`) and this skill point to PROJECT_CHANGELOG.md and docs/primevue-vs-shadcn.md for project context and component choices. Mentioning those in a prompt reinforces consistency when the task touches routing, UI lib choice, or documentation.
