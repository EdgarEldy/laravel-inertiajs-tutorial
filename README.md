# laravel-inertia-vue-tutorial

A complete tutorial for building a full-stack application with **Laravel 12**, **Inertia.js v3**, and **Vue 3**, using **Laravel Jetstream** for authentication, a custom role/permission layer for authorization, and an e-commerce domain (categories, products, customers, orders) built on top.

This document is the **complete specification** of the project: it is meant to be followed step by step to implement each branch.

## Table of contents

- [How Inertia ties Laravel and Vue together](#how-inertia-ties-laravel-and-vue-together)
- [Jetstream for authentication, a custom layer for authorization](#jetstream-for-authentication-a-custom-layer-for-authorization)
- [How permissions are checked](#how-permissions-are-checked)
- [Assignment direction: permissions onto roles, roles onto users](#assignment-direction-permissions-onto-roles-roles-onto-users)
- [Design system: reusing Jetstream's own components, not a new one](#design-system-reusing-jetstreams-own-components-not-a-new-one)
- [Create/Edit as modals, not pages](#createedit-as-modals-not-pages)
- [Tech stack](#tech-stack)
- [Data model](#data-model)
- [Branching strategy](#branching-strategy)
- [Project structure](#project-structure)
- [Conventions for success, validation, and errors](#conventions-for-success-validation-and-errors)
- [Testing strategy](#testing-strategy)
- [feature/core-architecture](#featurecore-architecture)
- [feature/jetstream-auth](#featurejetstream-auth)
- [feature/rbac](#featurerbac)
- [feature/categories](#featurecategories)
- [feature/products](#featureproducts)
- [feature/customers](#featurecustomers)
- [feature/orders](#featureorders)
- [Order of work](#order-of-work)
- [Code conventions](#code-conventions)
- [Concepts covered](#concepts-covered)
- [How to follow this tutorial](#how-to-follow-this-tutorial)

## How Inertia ties Laravel and Vue together

Inertia is not a JSON API layer and not a traditional SPA framework - it's a **glue layer** that lets a Laravel controller return a Vue page component directly, with server-side data as props, without either building a REST API or giving up client-side routing and reactivity.

- A route maps to a controller method exactly like classic Laravel; instead of returning a Blade view, it returns `Inertia::render('Products/Index', ['products' => ...])`
- The first request to any page is a normal full-page load (server-rendered HTML shell); every subsequent navigation is an XHR request that swaps only the page component, using Vue Router-like client-side transitions without Inertia needing its own router
- Props are just PHP arrays/Eloquent collections serialized to JSON and handed to the Vue component as `defineProps()` - there is no separate DTO layer, no OpenAPI contract, no generated client, because there's no API being consumed by anything other than this app's own frontend
- Forms use Inertia's `useForm()` composable, which posts to a normal Laravel route and handles validation errors, redirects, and progress state automatically - no manual `fetch`/`axios` wiring

## Jetstream for authentication, a custom layer for authorization

**Laravel Jetstream** (Inertia/Vue stack) provides registration, login, email verification, password reset, profile management, two-factor authentication (TOTP), and browser session management out of the box - all backed by Laravel's native **session-based authentication**, not a token. Jetstream also rate-limits login attempts by default (`throttle:login`, via Fortify), so brute-force protection doesn't need to be added separately in this project. Jetstream answers *who is this user*; it has no concept of roles or permissions, and doesn't try to.

This project adds **role-based access control** as its own layer on top, independent of Jetstream:

- `roles`, `permissions`, and two pivot tables (`role_user`, `role_permission`) - plain Eloquent models and migrations, not a third-party package, so the schema stays exactly what this project needs (resource/action permissions, not a generic tagging system)
- Jetstream's `User` model gains a `roles()` relationship and a `hasPermission()` method - the only point of contact between the two layers
- No JWT, no token blacklist: authorization checks read from the database through Eloquent relationships, consistent with the rest of this project staying in Laravel's own session-and-database idiom
- Jetstream's optional **Teams** feature is still **not enabled** - roles in this project are global (a user has a role, full stop), not scoped per team

## How permissions are checked

Resource/action permissions (`CATEGORY:WRITE`, `ORDER:READ`, ...) are checked through Laravel's own authorization primitives (`Gate`, `can`), not a custom middleware reinventing them:

- `AuthServiceProvider` registers a single `Gate::before` closure: if the ability being checked looks like `RESOURCE:ACTION`, it defers to `$user->hasPermission($resource, $action)`; any other ability falls through to Laravel's normal Gate/Policy resolution untouched
- Because this is a `Gate::before` hook, every one of Laravel's usual authorization surfaces works without extra wiring: route middleware (`->middleware('can:CATEGORY:WRITE')`), controller checks (`$this->authorize('CATEGORY:WRITE')`), and Vue-side conditional rendering (permissions shared globally via `HandleInertiaRequests`, checked with a small `can()` helper in the frontend)
- `User::hasPermission()` loads the user's roles and their permissions through Eloquent eager loading, resolved fresh on each request - this project's traffic and role-change frequency don't justify caching permissions across requests
- **Bootstrapping the first admin**: a listener on Jetstream's `Registered` event checks whether this is the very first user in the system and, if so, assigns the `ADMIN` role automatically. Every other new registration gets the `USER` role. Without this, nobody could ever create the first role assignment, since doing so requires a permission nobody yet holds.

## Assignment direction: permissions onto roles, roles onto users

Both relationships in this system are managed from one deliberate direction, never the other:

- **Permissions are assigned onto a role** (`POST /roles/{role}/permissions/{permission}`), managed from the `Roles` pages. There is no "assign this permission to a role" flow initiated from a `Permissions` page - a permission is a fixed, catalog-like thing (`CATEGORY:WRITE` always means the same thing); a role is the thing being composed out of permissions, so the role is where that composition happens.
- **Roles are assigned onto a user** (`POST /users/{user}/roles/{role}`), managed from the `Users` pages. There is no "assign this role to a user" flow initiated from a `Roles` page - a role is again the fixed, catalog-like thing here; the user is what's being granted access, so the user is where that grant happens.
- The pattern is consistent: the *lower-level, catalog* concept (`Permission`, then `Role`) is never the page that manages an assignment; the *higher-level, composed* concept (`Role`, then `User`) always is.

## Design system: reusing Jetstream's own components, not a new one

Every page this tutorial adds (users, roles, permissions, categories, products, customers, orders) is built with **Jetstream's own scaffolded Vue components**, not a new component library introduced on top:

- `PrimaryButton.vue`, `SecondaryButton.vue`, `DangerButton.vue`, `TextInput.vue`, `InputLabel.vue`, `InputError.vue`, `Modal.vue`, `DialogModal.vue`, `ActionSection.vue`, `FormSection.vue` - all generated by Jetstream's installer, already used by its own profile/security pages
- New pages follow the same layout components Jetstream's own pages use (`AppLayout.vue`), so a `Products/Index.vue` page looks and behaves consistently with `Profile/Show.vue` without extra work
- No new CSS framework, icon set, or design tokens are introduced - Jetstream ships with Tailwind CSS already configured, and this project stays inside that

## Create/Edit as modals, not pages

Every resource with a Create/Edit flow (`Roles`, `Permissions`, `Categories`, `Products`, `Customers`, `Orders`) uses **Jetstream's own `Modal.vue`/`DialogModal.vue`** for both, rather than separate full-page navigations - the same pattern Jetstream's own scaffolded pages already use (e.g. its team member management), not a new interaction model introduced on top:

- Each resource's `Pages/{Resource}/Index.vue` is a thin orchestrator: it holds `showModal`/`editingX` local state, renders `Partials/Data.vue` (the table - search, pagination, row actions) and `Partials/Form.vue` (the modal, a single `useForm()` covering both create and edit), and wires `Data`'s `@create`/`@edit` events to open the modal.
- `Partials/Form.vue` posts to the resource's `store` route on create and `update` route on edit (`form.post(...)`/`form.put(...)`), closing the modal via an emitted `close` event on success - validation errors surface inline the same way a full-page form would (`form.errors.<field>`), Inertia's error-sharing is unaffected by the form living in a modal.
- Because Create/Edit no longer need their own route to render a distinct page, there is **no `GET /{resource}/create` or `GET /{resource}/{id}/edit` route** - only `index`, `store`, `update`, `destroy` (plus whatever a specific resource's routes table below adds, like `Roles/Permissions`). The routes tables below reflect this.

## Tech stack

| Component | Choice |
|---|---|
| Backend framework | Laravel 12 |
| Language | PHP 8.2 |
| Authentication | Laravel Jetstream (Inertia/Vue stack), session-based, built-in login rate limiting |
| Authorization | Custom `roles`/`permissions` layer, wired into Laravel's `Gate` |
| Frontend glue | Inertia.js v3 |
| Frontend framework | Vue 3 (Composition API, TypeScript) |
| Styling | Tailwind CSS 4 (via Jetstream's scaffolding) |
| Build tool | Vite |
| Route generation for JS | Laravel Wayfinder (typed route helpers generated from PHP routes, used in Vue instead of hand-written URL strings) |
| Database | MySQL 8.0 (via Docker Compose) |
| Mail (local dev) | MailHog (via Docker Compose) - SMTP catcher, real registration/verification/reset emails inspectable in its web UI |
| ORM | Eloquent |
| Migrations | Laravel migrations, each feature's own permissions seeded alongside its own tables |
| Business logic organization | Service classes (one per resource: `RoleService`, `CategoryService`, ...), concrete classes with no interface, keeping controllers thin - see [Code conventions](#code-conventions) |
| Validation | Laravel Form Requests |
| Code style | Laravel Pint |
| Tests | Pest v4 (feature tests via Laravel's testing helpers and Inertia's assertion macros, plus Pest's browser testing plugin for true end-to-end Vue page tests) **and** Vitest + Vue Test Utils (isolated unit tests per Vue component, `resources/js/Pages/{Resource}/Partials/*.spec.js`) - two different layers, not a replacement for one another: Pest browser tests drive a real browser against the fully rendered app end to end, Vitest tests one component's logic/rendering in isolation without a server or a browser |
| CI/CD | GitHub Actions |
| Containerization | Docker, docker-compose |

## Data model

Jetstream owns `users` and its own supporting tables (not modeled here, out of this project's direct control). Everything below is this project's own.

```
roles (id, role_name)
    │ N──N (via role_user, user_id references Jetstream's users.id)
    │ N──N (via role_permission)
permissions (id, resource, action)

audit_logs (id, actor_user_id, action, entity_type, entity_id, details, created_at)

categories (id, category_name)
    │ 1
    │
    │ N
products (id, category_id, product_name, unit_price)
    │ 1
    │
    │ N
orders (id, customer_id, product_id, quantity, total)
    │ N
    │
    │ 1
customers (id, first_name, last_name, telephone, email, address)
```

## Branching strategy

| Branch | Role |
|---|---|
| `master` | Stable, production-ready code. No direct commits, only merges from `develop`. |
| `develop` | Integration branch. |
| `feature/core-architecture` | Laravel project setup, MySQL configuration, Docker, CI. |
| `feature/jetstream-auth` | Jetstream installation and configuration (Inertia/Vue stack, Teams disabled). |
| `feature/rbac` | `roles`/`permissions`/`role_permission`/`role_user` tables, `Gate::before` check, complete CRUD for roles/permissions/users, audit logging. |
| `feature/categories` | Category management pages, permission-protected, seeds its own permissions. |
| `feature/products` | Product management pages, depends on `categories`, seeds its own permissions. |
| `feature/customers` | Customer management pages, seeds its own permissions. |
| `feature/orders` | Order placement and listing pages, depends on `products`/`customers`, seeds its own permissions. |

## Project structure

```
laravel-inertia-vue-tutorial/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── RoleController.php, PermissionController.php, UserController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── ProductController.php
│   │   │   ├── CustomerController.php
│   │   │   └── OrderController.php
│   │   ├── Requests/
│   │   │   ├── StoreRoleRequest.php, UpdateRoleRequest.php
│   │   │   ├── StorePermissionRequest.php, UpdatePermissionRequest.php
│   │   │   ├── StoreCategoryRequest.php, UpdateCategoryRequest.php
│   │   │   ├── StoreProductRequest.php, UpdateProductRequest.php
│   │   │   ├── StoreCustomerRequest.php, UpdateCustomerRequest.php
│   │   │   └── StoreOrderRequest.php
│   │   ├── Resources/                          (JsonResource - shapes a model/collection into the array handed to Inertia::render as a prop; still a plain array on the wire, not a new object layer)
│   │   │   ├── RoleResource.php, PermissionResource.php, UserResource.php
│   │   │   ├── CategoryResource.php, ProductResource.php
│   │   │   ├── CustomerResource.php, OrderResource.php
│   │   └── Middleware/
│   │       └── HandleInertiaRequests.php      (Jetstream-generated; also shares the current user's permissions globally)
│   ├── Listeners/
│   │   └── AssignDefaultRoleOnRegistration.php  (listens to Jetstream's Registered event)
│   ├── Models/
│   │   ├── Role.php, Permission.php, AuditLog.php
│   │   ├── Category.php, Product.php, Customer.php, Order.php
│   │   └── User.php                              (Jetstream's default model, extended with roles()/hasPermission())
│   ├── Services/
│   │   ├── RoleService.php, PermissionService.php, UserService.php   (RBAC: CRUD + assignment, see feature/rbac)
│   │   ├── CategoryService.php, ProductService.php
│   │   ├── CustomerService.php, OrderService.php
│   │   ├── AuditLogger.php                       (single log(action, entityType, entityId, details) method)
│   │   └── Concerns/
│   │       └── LogsAuditEvents.php               (trait: shared logCreated/logUpdated/logDeleted/logAssigned/logRemoved helpers over AuditLogger, used by RoleService/PermissionService/UserService)
│   └── Providers/
│       └── AuthServiceProvider.php               (registers the Gate::before permission hook)
├── database/
│   ├── migrations/
│   │   ├── ..._create_roles_table.php, ..._create_permissions_table.php
│   │   ├── ..._create_role_user_table.php, ..._create_role_permission_table.php
│   │   ├── ..._create_audit_logs_table.php
│   │   ├── ..._create_categories_table.php   (feature/categories: also seeds CATEGORY:READ/WRITE, assigned to ADMIN)
│   │   ├── ..._create_products_table.php     (feature/products: also seeds PRODUCT:READ/WRITE, assigned to ADMIN)
│   │   ├── ..._create_customers_table.php    (feature/customers: also seeds CUSTOMER:READ/WRITE, assigned to ADMIN)
│   │   └── ..._create_orders_table.php       (feature/orders: also seeds ORDER:READ/WRITE, assigned to ADMIN)
│   ├── seeders/
│   │   └── RbacPermissionSeeder.php          (feature/rbac's own permissions only: ROLE:*, PERMISSION:*, USER:*)
│   └── factories/
│       ├── RoleFactory.php, PermissionFactory.php
│       ├── CategoryFactory.php, ProductFactory.php, CustomerFactory.php, OrderFactory.php
├── resources/
│   └── js/
│       ├── Components/                          (Jetstream-generated, reused as-is - see "Design system")
│       ├── Layouts/
│       │   └── AppLayout.vue                     (Jetstream-generated)
│       └── Pages/                                  (Index.vue per resource is a thin orchestrator; Create/Edit are a shared modal in Partials/Form.vue, not separate pages/routes - see "Create/Edit as modals, not pages")
│           ├── Users/ (Index.vue, Show.vue)
│           ├── Roles/ (Index.vue, Partials/Data.vue, Partials/Form.vue, Permissions.vue)
│           ├── Permissions/ (Index.vue, Partials/Data.vue, Partials/Form.vue)
│           ├── Categories/ (Index.vue, Partials/Data.vue, Partials/Form.vue)
│           ├── Products/ (Index.vue, Partials/Data.vue, Partials/Form.vue)
│           ├── Customers/ (Index.vue, Partials/Data.vue, Partials/Form.vue)
│           └── Orders/ (Index.vue, Partials/Data.vue, Partials/Form.vue)
├── routes/
│   └── web.php                                    (all routes, Jetstream's own + this project's, session-authenticated and permission-protected)
├── tests/
│   ├── Feature/
│   │   ├── UserManagementTest.php, RoleTest.php, PermissionTest.php
│   │   ├── CategoryTest.php, ProductTest.php, CustomerTest.php, OrderTest.php
│   └── Browser/                                   (Pest v4 browser testing plugin, full Vue page interactions)
├── docker-compose.yml
├── Dockerfile
├── .github/workflows/ci.yml
└── composer.json / package.json
```

## Conventions for success, validation, and errors

There is no JSON response envelope in this project - Inertia's own conventions are used directly, the same way the rest of this stack (Jetstream's own pages) already works:

- A successful action (create/update/delete) redirects back to the relevant Inertia page, with a flash message set via `session()->flash('flash.banner', '...')` - the same mechanism Jetstream's own profile update page uses, surfaced by the same shared `<Banner />` component in `AppLayout.vue`
- Validation failures are handled entirely by Laravel's Form Requests: a failed validation redirects back with errors automatically shared to Inertia via `HandleInertiaRequests`, and `useForm()` on the Vue side exposes them as `form.errors.<field>` - bound directly to `InputError.vue`, no manual error-handling code in any page component
- An authentication failure (a route requiring a session, hit without one) redirects to the login page - Laravel's default `auth` middleware behavior, unchanged
- An authorization failure (an authenticated user lacking the required permission) renders Inertia's default 403 error page - the result of `Gate::before` denying the ability
- A "not found" (e.g. `Product::findOrFail()` on a missing id) renders Inertia's default 404 error page
- A rejected business rule (category still has products, last-admin removal, entity still referenced elsewhere) redirects back with a validation-style error message on the relevant field, surfaced the same way a Form Request failure would be - the user never sees a raw exception

## Testing strategy

Every branch's checklist includes tests on both sides of the stack, at every layer - not just whichever is fastest to write:

| Layer | Tool | What it covers |
|---|---|---|
| Laravel unit | Pest (`tests/Unit/`) | A `Service` method's logic in isolation where isolating it is actually informative (e.g. `OrderService::placeOrder()`'s total computation) |
| Laravel feature/integration | Pest (`tests/Feature/`) | Every route, permission-denied/unauthenticated cases, validation failures, business-rule rejections, `AuditLogger` calls - against the real HTTP stack and a real MySQL database, never mocked |
| Laravel/Inertia E2E | Pest v4 browser plugin (`tests/Browser/`) | Real create/edit/delete flows through the actually-rendered Vue pages, from `feature/categories` onward |
| Vue unit | Vitest + Vue Test Utils (`resources/js/Pages/{Resource}/Partials/*.spec.js`) | One component in isolation - `Form.vue`'s `useForm()` state transitions, `Data.vue`'s emitted events - from `feature/rbac` onward |
| Vue integration | Vitest + Vue Test Utils | Multiple real (not mocked) components composed together - `Index.vue` + `Data.vue` + `Form.vue` - catching a wiring bug between components that each pass in isolation |

Pest feature/browser tests and Vitest unit/integration tests are not redundant with each other: a browser test proves the whole stack works together for a real user flow but is slow and expensive to write for every edge case, while a Vitest unit test proves one component's own logic quickly but says nothing about whether the backend actually returns what that component expects. Both layers are written for every branch from `feature/rbac` onward (the first branch with `Data.vue`/`Form.vue` components to unit-test).

## feature/core-architecture

### Tasks

- [ ] Create the project (`laravel new laravel-inertia-vue-tutorial`), PHP 8.2, Laravel 12
- [ ] Configure MySQL as the default database connection
- [ ] Laravel Pint configured, Pest installed as the default test runner (`pest` replacing PHPUnit's default assertions)
- [ ] `docker-compose.yml` (app + MySQL)
- [ ] `.github/workflows/ci.yml`: `composer install`, `npm install`, `php artisan test` (Pest), `npm run build`
- [ ] Base `.env.example` with database and mail (SMTP against a local MailHog instance) configuration

## feature/jetstream-auth

### Tasks

- [ ] Install Jetstream with the Inertia stack and Vue: `php artisan jetstream:install inertia`
- [ ] `npm install && npm run build`, run the generated migrations (`users`, Jetstream's supporting tables)
- [ ] Teams feature left **disabled** (`Jetstream::useTeams()` not called)
- [ ] Two-factor authentication enabled in Jetstream's feature flags (`Features::twoFactorAuthentication()`)
- [ ] Mail driver set to `smtp` against a local **MailHog** instance (`docker-compose.yml` service, SMTP on `1025`, web UI on `8025`) for local development - verification/reset emails are genuinely sent and inspectable in MailHog's inbox, without requiring a real mail provider
- [ ] Verify Jetstream's own pages work end to end before building anything new on top: register → verify email → login → enable 2FA → update profile
- [ ] Pest feature tests (Jetstream ships with its own, but confirm they run) covering registration, login, and email verification

## feature/rbac

Full CRUD for roles and permissions, plus user-role management. No page ever manages an assignment from the "lower-level" side - see [Assignment direction](#assignment-direction-permissions-onto-roles-roles-onto-users).

### Routes

| Method | URL | Page rendered | Description | Access |
|---|---|---|---|---|
| GET | `/users` | `Users/Index` | Paginated list | `can:USER:READ` |
| GET | `/users/{user}` | `Users/Show` | Detail, including assigned roles, with a form to assign another | `can:USER:READ` |
| POST | `/users/{user}/roles/{role}` | redirect | Assign a role to a user | `can:USER:WRITE` |
| DELETE | `/users/{user}/roles/{role}` | redirect | Remove a role from a user | `can:USER:WRITE` |
| GET | `/roles` | `Roles/Index` | List, with a modal (`Partials/Form.vue`) for create/edit | `can:ROLE:READ` |
| POST | `/roles` | redirect | Create | `can:ROLE:WRITE` |
| PUT | `/roles/{role}` | redirect | Update the role's name only | `can:ROLE:WRITE` |
| DELETE | `/roles/{role}` | redirect | Delete | `can:ROLE:WRITE` |
| GET | `/roles/{role}/permissions` | `Roles/Permissions` | Dedicated page: assign/remove this role's permissions | `can:ROLE:WRITE` |
| POST | `/roles/{role}/permissions/{permission}` | redirect | Assign a permission to a role | `can:ROLE:WRITE` |
| DELETE | `/roles/{role}/permissions/{permission}` | redirect | Remove a permission from a role | `can:ROLE:WRITE` |
| GET | `/permissions` | `Permissions/Index` | List, with a modal (`Partials/Form.vue`) for create/edit | `can:PERMISSION:READ` |
| POST | `/permissions` | redirect | Create | `can:PERMISSION:WRITE` |
| PUT | `/permissions/{permission}` | redirect | Update | `can:PERMISSION:WRITE` |
| DELETE | `/permissions/{permission}` | redirect | Delete | `can:PERMISSION:WRITE` |

### Tasks

- [ ] `roles`, `permissions`, `role_user`, `role_permission` migrations - `role_user.user_id` references Jetstream's `users.id`
- [ ] `audit_logs` migration
- [ ] `Role`, `Permission`, `AuditLog` Eloquent models, with `belongsToMany` relationships in both directions on `Role`/`Permission`/`User`
- [ ] `User` model extended: `roles()` (`belongsToMany(Role::class, 'role_user')`), `hasPermission(string $resource, string $action): bool`
- [ ] `AuthServiceProvider`: `Gate::before` closure implementing the `RESOURCE:ACTION` permission check described [above](#how-permissions-are-checked)
- [ ] `AssignDefaultRoleOnRegistration` listener, registered against Jetstream's `Registered` event: assigns `ADMIN` to the first user ever created, `USER` to everyone after
- [ ] `RbacPermissionSeeder`: seeds only this branch's own permissions (`ROLE:READ`, `ROLE:WRITE`, `PERMISSION:READ`, `PERMISSION:WRITE`, `USER:READ`, `USER:WRITE`), assigned to a seeded `ADMIN` role - later branches seed their own resource's permissions from their own migrations rather than all being declared here up front
- [ ] `AuditLogger` service, with a single `log(string $action, string $entityType, int $entityId, array $details = [])` method
- [ ] `Services/Concerns/LogsAuditEvents.php` trait: `logCreated`/`logUpdated`/`logDeleted`/`logAssigned`/`logRemoved` helpers wrapping `AuditLogger::log()`, mixed into `RoleService`, `PermissionService`, `UserService`
- [ ] `RoleService::createRole()`, `updateRole()`, `deleteRole()` - `deleteRole` rejects if any user is still assigned this role (must be unassigned first)
- [ ] `PermissionService::createPermission()`, `updatePermission()`, `deletePermission()` - `deletePermission` rejects if any role still has this permission assigned
- [ ] `RoleService::assignPermissionToRole()`, `removePermissionFromRole()` - `removePermissionFromRole` rejects removing `ROLE:WRITE` from a role if doing so would leave **zero** users anywhere holding a role that grants `ROLE:WRITE` (the role being edited is only one of possibly several sources)
- [ ] `UserService::assignRoleToUser()`, `removeRoleFromUser()` - `removeRoleFromUser` applies the same last-admin check as above at the point of removal from a specific user, so a self-lockout is caught however it's attempted (removing the permission from the role, or removing the role from the last user who has it)
- [ ] `RoleController`, `PermissionController`, `UserController` (`index`, `show` only - user *creation* stays exclusively Jetstream's registration flow, this controller only manages role assignment on existing users)
- [ ] `Users/Index.vue`, `Show.vue` (role assignment); `Roles/Index.vue` + `Partials/Data.vue` + `Partials/Form.vue` (create/edit modal, name only), `Permissions.vue` (dedicated page, permission checkboxes); `Permissions/Index.vue` + `Partials/Data.vue` + `Partials/Form.vue`
- [ ] `HandleInertiaRequests` shares the current user's permission list globally (`auth.permissions`), so Vue pages can conditionally render actions the user isn't allowed to take, backed by a small `can(permission)` helper - a UI convenience only, the server-side `Gate::before` check is what's actually authoritative
- [ ] Pest unit tests on the RBAC services' own logic where isolation is informative (e.g. the `LogsAuditEvents` payload shape); Pest feature tests: full CRUD on roles and permissions, role assignment/removal on a user, permission assignment/removal on a role, the `Gate::before` hook allowing/denying correctly, the registration listener assigning `ADMIN` only to the first user, and specifically the last-admin rejection triggered both ways (removing the role from the last user, and removing the permission from the role that was their only source of it)
- [ ] Vitest unit tests for `Roles/Partials/Data.vue`, `Roles/Partials/Form.vue`, `Permissions/Partials/Data.vue`, `Permissions/Partials/Form.vue`; a Vitest integration test composing `Index.vue` with its real `Data`/`Form` children for at least one resource

## feature/categories

### Routes

| Method | URL | Page rendered | Description | Access |
|---|---|---|---|---|
| GET | `/categories` | `Categories/Index` | List, with a modal (`Partials/Form.vue`) for create/edit | `can:CATEGORY:READ` |
| POST | `/categories` | redirect | Create | `can:CATEGORY:WRITE` |
| PUT | `/categories/{category}` | redirect | Update | `can:CATEGORY:WRITE` |
| DELETE | `/categories/{category}` | redirect | Delete | `can:CATEGORY:WRITE` |

### Tasks

- [ ] `Category` model, migration - the same migration seeds `CATEGORY:READ`/`CATEGORY:WRITE` permissions and assigns both to `ADMIN`, following the incremental-seeding convention set in `feature/rbac`
- [ ] Factory
- [ ] `StoreCategoryRequest`/`UpdateCategoryRequest`
- [ ] `CategoryService::createCategory()`, `updateCategory()`, `deleteCategory()` - `deleteCategory` rejects if the category still has products (once `feature/products` exists)
- [ ] `CategoryController` (`index`, `store`, `update`, `destroy`), each method thin: validate via the Form Request, delegate to `CategoryService`, redirect
- [ ] Every route protected as listed above
- [ ] `Categories/Index.vue` + `Partials/Data.vue` + `Partials/Form.vue` (create/edit modal - see [Create/Edit as modals, not pages](#createedit-as-modals-not-pages)), built from Jetstream's existing components, write actions hidden via the shared `can()` helper for users without `CATEGORY:WRITE`
- [ ] Pest feature tests for every route including a permission-denied case, a browser test (Pest v4) covering create → edit → delete through the actual rendered Vue pages
- [ ] Vitest unit tests for `Categories/Partials/Data.vue` and `Partials/Form.vue`; a Vitest integration test composing `Index.vue` with both

## feature/products

Depends on `feature/categories` existing, since every product references one.

### Routes

| Method | URL | Page rendered | Description | Access |
|---|---|---|---|---|
| GET | `/products` | `Products/Index` | List, filterable by category, with a modal (`Partials/Form.vue`) for create/edit | `can:PRODUCT:READ` |
| POST | `/products` | redirect | Create | `can:PRODUCT:WRITE` |
| PUT | `/products/{product}` | redirect | Update | `can:PRODUCT:WRITE` |
| DELETE | `/products/{product}` | redirect | Delete | `can:PRODUCT:WRITE` |

### Tasks

- [ ] `Product` model, migration - also seeds `PRODUCT:READ`/`PRODUCT:WRITE`, assigned to `ADMIN`
- [ ] Factory
- [ ] `StoreProductRequest`/`UpdateProductRequest`
- [ ] `ProductService::createProduct()`, `updateProduct()`, `deleteProduct()`
- [ ] `CategoryService::deleteCategory()` updated: rejects deletion if the category still has products
- [ ] `ProductController`, routes protected as listed above
- [ ] `Products/Index.vue` (with a category filter dropdown) + `Partials/Data.vue` + `Partials/Form.vue` (create/edit modal)
- [ ] Pest feature and browser tests, including the category-deletion rejection case and a permission-denied case
- [ ] Vitest unit tests for `Products/Partials/Data.vue` (including the category filter) and `Partials/Form.vue`; a Vitest integration test composing `Index.vue` with both

## feature/customers

### Routes

| Method | URL | Page rendered | Description | Access |
|---|---|---|---|---|
| GET | `/customers` | `Customers/Index` | List, with a modal (`Partials/Form.vue`) for create/edit | `can:CUSTOMER:READ` |
| POST | `/customers` | redirect | Create | `can:CUSTOMER:WRITE` |
| PUT | `/customers/{customer}` | redirect | Update | `can:CUSTOMER:WRITE` |
| DELETE | `/customers/{customer}` | redirect | Delete | `can:CUSTOMER:WRITE` |

### Tasks

- [ ] `Customer` model, migration - also seeds `CUSTOMER:READ`/`CUSTOMER:WRITE`, assigned to `ADMIN`
- [ ] Factory
- [ ] `StoreCustomerRequest`/`UpdateCustomerRequest`
- [ ] `CustomerService::createCustomer()`, `updateCustomer()`, `deleteCustomer()`
- [ ] `CustomerController`, routes protected as listed above
- [ ] `Customers/Index.vue` + `Partials/Data.vue` + `Partials/Form.vue` (create/edit modal)
- [ ] Pest feature and browser tests, including a permission-denied case
- [ ] Vitest unit tests for `Customers/Partials/Data.vue` and `Partials/Form.vue`; a Vitest integration test composing `Index.vue` with both

## feature/orders

### Routes

| Method | URL | Page rendered | Description | Access |
|---|---|---|---|---|
| GET | `/orders` | `Orders/Index` | List, with a modal (`Partials/Form.vue`) for create (product + customer selects - no edit/delete route exists for orders) | `can:ORDER:READ` |
| POST | `/orders` | redirect | Create (computes `total`) | `can:ORDER:WRITE` |

### Tasks

- [ ] `Order` model, migration - also seeds `ORDER:READ`/`ORDER:WRITE`, assigned to `ADMIN`
- [ ] Factory
- [ ] `StoreOrderRequest`
- [ ] `OrderService::placeOrder()`: computes `total = quantity * product.unit_price`, persists the order
- [ ] `OrderController`, routes protected as listed above
- [ ] `Orders/Index.vue` (displaying customer and product names, not just ids, via Eloquent eager loading - `Order::with(['customer', 'product'])`) + `Partials/Data.vue` + `Partials/Form.vue` (create-only modal, no edit - orders have no update/delete route)
- [ ] Pest feature and browser tests, including a full flow: create a category → a product → a customer → an order, verified through the rendered pages, plus a permission-denied case
- [ ] Vitest unit tests for `Orders/Partials/Data.vue` and `Partials/Form.vue` (create-only); a Vitest integration test composing `Index.vue` with both

## Order of work

1. `feature/core-architecture` → Pull Request to `develop`
2. `feature/jetstream-auth` (depends on `core-architecture`) → Pull Request to `develop`
3. `feature/rbac` (depends on `jetstream-auth`) → Pull Request to `develop`
4. `feature/categories` (depends on `rbac`) → Pull Request to `develop`
5. `feature/products` (depends on `categories`) → Pull Request to `develop`
6. `feature/customers` (depends on `rbac`) → Pull Request to `develop`
7. `feature/orders` (depends on `products`, `customers`) → Pull Request to `develop`
8. `develop` → `master`

## Code conventions

- **Service classes, concrete, no interface**: business logic lives in one `Service` class per resource (`RoleService`, `PermissionService`, `UserService`, `CategoryService`, `ProductService`, `CustomerService`, `OrderService`), each with one public method per business operation (`createRole`, `deleteRole`, `assignPermissionToRole`, ...). No `Interfaces`/`Implementations` split, no contract - the class is bound to the container as itself and injected directly into the controller that needs it. This is a deliberate choice to stay inside Laravel's own idiom (concrete class, constructor injection resolved automatically by the container) rather than importing a Java-style contract/implementation pattern this project has no actual need for (there is only ever one implementation of `RoleService`).
- Controllers only validate (via a Form Request), call exactly one method on the relevant Service, and redirect or render - no business logic in a controller method.
- Every write route is protected with `->middleware('can:RESOURCE:ACTION')`; no controller method re-implements a permission check that route middleware should already have handled.
- Assignments always flow in one direction, never the other - see [Assignment direction](#assignment-direction-permissions-onto-roles-roles-onto-users). `PermissionController`/`PermissionService` never gain a "roles" relationship-management endpoint/method, and `RoleController`/`RoleService` never gain a "users" one.
- Every RBAC mutation (role/permission CRUD, any assignment or removal) calls `AuditLogger` - this is not optional per-method, it's a property of `RoleService`/`PermissionService`/`UserService` as a whole. `LogsAuditEvents` (a trait, `app/Services/Concerns/`) is shared by all three so the same `log*` helper signatures are used consistently rather than each service hand-rolling its own `AuditLogger::log(...)` calls.
- Every payload handed to `Inertia::render()` as a prop that comes from an Eloquent model or collection goes through a `JsonResource` (`app/Http/Resources/`) rather than being passed raw - keeps the exact shape reaching the Vue page explicit and stable (which relations, which fields) instead of whatever `toArray()` happens to produce today. This is still a plain array on the wire, not a new object/DTO layer between the database and the frontend - a `Resource` is a formatting function, nothing more.
- Every new Vue page reuses Jetstream's existing components (`Components/`) and layout (`Layouts/AppLayout.vue`) - no new design system is introduced.
- Routes are referenced from Vue via Laravel Wayfinder's generated helpers, never a hand-typed URL string.
- Every Eloquent relationship that will be displayed together is eager-loaded explicitly (`with(...)`) - no N+1 query left to Eloquent's lazy loading in a list page.
- Server-side permission checks (route middleware, `Gate::before`) are always authoritative; any client-side `can()` check in Vue is a UI convenience only, never the actual security boundary.
- Each feature branch seeds only the permissions its own resource needs, from its own migration - no branch pre-declares permissions for a resource that doesn't exist yet.

## Concepts covered

- Inertia.js as a glue layer between Laravel controllers and Vue pages, without a JSON API
- Laravel Jetstream: registration, email verification, login, password reset, two-factor authentication, session-based auth, built-in rate limiting
- Separating authentication (Jetstream) from authorization (a custom role/permission layer), and the single point where they connect
- Resource/action permissions checked through Laravel's own `Gate::before`, without a dedicated third-party package
- Directional relationship management (permissions onto roles, roles onto users) as a deliberate, consistently-applied convention
- Bootstrapping the first administrative user without a circular permission dependency, and preventing a subsequent self-lockout
- Audit logging as a first-class concern for every sensitive mutation
- Incremental permission seeding, one resource at a time, from the migration that introduces it
- Reusing a starter kit's own design system instead of introducing a new one
- Service classes for organizing business logic outside of controllers, without an interface the project has no real use for
- Traits for sharing behavior across classes that don't share a base class (`LogsAuditEvents` across the three RBAC services)
- `JsonResource` for shaping what an Eloquent model/collection actually sends to the frontend as an Inertia prop
- Laravel Form Requests for validation, and Inertia's automatic error-sharing on failed validation
- Eloquent relationships and eager loading to avoid N+1 queries in list pages
- Laravel Wayfinder for typed route references from the frontend
- Testing an Inertia/Vue application at every layer: Laravel unit and feature tests, Pest browser-driven E2E tests of rendered pages and permission-denied cases, and isolated/composed Vue component tests with Vitest
- Containerization (Docker, docker-compose)
- Continuous integration (GitHub Actions)

## How to follow this tutorial

1. Clone the repository and check out `develop`
2. Follow the branches in order: `feature/core-architecture` → `feature/jetstream-auth` → `feature/rbac` → `feature/categories` → `feature/products` → `feature/customers` → `feature/orders`
3. Run `docker-compose up`, `php artisan migrate --seed`, `npm run dev`
4. Register the first account through Jetstream's own UI (automatically granted `ADMIN`) - the verification email is genuinely sent via SMTP and lands in MailHog's web UI at `http://localhost:8025`, not just written to a log file
5. Navigate to `/users` to promote a second account, or to `/roles`, `/categories`, `/products`, `/customers`, `/orders` directly
