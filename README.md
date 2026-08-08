# Job Backoffice

Admin and company management console for the job portal platform. Built with **Laravel 12**, this app is where **admins** manage the whole platform (users, companies, job categories) and **company owners** manage their own job vacancies and review applicants — including AI-generated resume scores produced by the companion [job-app](https://github.com/YoussefSayed-cs/job-app).

This repository is one of three that make up the platform:

| Repo | Role |
|---|---|
| **job-backoffice** *(this repo)* | Admin & company-owner dashboard |
| [job-app](https://github.com/YoussefSayed-cs/job-app) | Public-facing app for job seekers, with AI résumé screening |
| [job-shared](https://github.com/YoussefSayed-cs/job-shared) | Shared Eloquent models & notifications used by both apps |

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Database Schema](#database-schema)
- [Route Map](#route-map)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [Roles & Permissions](#roles--permissions)
- [Testing](#testing)
- [Related Repositories](#related-repositories)

---

## Features

- **Role-based access control** — three roles (`admin`, `company-owner`, `job-seeker`) enforced via a custom `RoleMiddleware`, with route groups scoped per role.
- **Company management** — admins can manage every company; company owners manage their own company profile (`/my-company`).
- **Job vacancy management** — full CRUD with soft deletes and restore, categories, and vacancy types (Full-Time, Contract, Remote, Hybrid).
- **Applicant review** — company owners review applications submitted through job-app, including the AI-generated compatibility score and feedback, and can accept or reject candidates.
- **Role-aware analytics dashboard** — a Repository + Service layer decides what data to load depending on who's logged in: admins see platform-wide stats, company owners see stats scoped to their own company only (applications, vacancy views, conversion rate).
- **In-app notifications** — database-backed notifications (e.g. new applications) with a read/unread dropdown, mark-as-read and delete actions.
- **User management** — admin-only CRUD over every platform user, with soft delete/restore.
- **Job category management** — admin-only CRUD used to tag and filter vacancies.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP ^8.2 |
| Frontend | Blade templates, Tailwind CSS 3, Alpine.js, Vite |
| Database | MariaDB / MySQL — UUID primary keys throughout |
| Auth | Laravel Breeze (session-based) |
| File storage | S3-compatible cloud storage via `league/flysystem-aws-s3-v3` |
| Real-time deps | Pusher / Laravel Echo installed (channel scaffolding present; not wired to a live feature yet) |
| Testing | Pest 4 |
| Shared domain layer | [`job/shared`](https://github.com/YoussefSayed-cs/job-shared) Composer package |

## Architecture

### Authorization
Every route sits behind Laravel's `auth` middleware plus a custom `role:` middleware, e.g. `role:admin,company-owner` or `role:admin`. The middleware checks the logged-in user's `role` column (`admin` / `company-owner` / `job-seeker`) against the roles allowed for that route group, so the three role groups in `routes/web.php` map directly to what each type of user can reach.

### Dashboard analytics — Repository + Service pattern
The dashboard doesn't hardcode "if admin do X, else do Y" in the controller. Instead:

1. `DashboardRepositoryInterface` defines a common contract (e.g. total vacancies, total applications, recent activity).
2. `RepositoryServiceProvider` binds that interface **at runtime**, based on the authenticated user's role:
   - `admin` → `AdminDashboardRepository` (platform-wide queries)
   - `company-owner` → `CompanyDashboardRepository`, pre-scoped to that user's own job vacancy IDs
3. `DashboardService` consumes the bound repository and layers business logic on top of it (e.g. computing a conversion rate from applications vs. views) — logic that belongs in the service, not the repository or the controller.
4. `DashboardController` just calls the service and passes the result to the view, with no idea which repository is actually behind it.

This means adding a new role-specific dashboard later just means writing a new repository class and adding one line to the provider — no controller or view changes needed.

### Shared domain layer
User, Company, JobVacancy, JobCategory, JobApplication, and Resume are defined **once**, in the [`job/shared`](https://github.com/YoussefSayed-cs/job-shared) Composer package (namespaced as `App\Models\*`, same as if they lived in this app), and pulled into both this app and job-app as a dependency. Both codebases always see the exact same schema and relationships.

## Database Schema

All tables use **UUID primary keys** and **soft deletes** (except the notifications/sessions/cache infrastructure tables).

| Table | Key columns | Relationships |
|---|---|---|
| `users` | `name`, `email` (unique), `password`, `role` (`admin` \| `company-owner` \| `job-seeker`), `last_login_at` | has one `company` (as owner) · has many `resumes` · has many `job_applications` |
| `companies` | `name`, `address`, `industry`, `website`, `description`, `ownerID` → `users.id` | belongs to `User` (owner) · has many `job_vacancies` · has many `job_applications` (through `job_vacancies`) |
| `job_categories` | `name` | has many `job_vacancies` |
| `job_vacancies` | `title`, `description`, `location`, `salary`, `type` (Full-Time \| Contract \| Remote \| Hybrid), `views_count`, `companyID` → `companies.id`, `categoryID` → `job_categories.id` | belongs to `Company` and `JobCategory` · has many `job_applications` |
| `resumes` | `filename`, `fileUri`, `contactDetails`, `education`, `experience`, `skills`, `summary` (all JSON-cast), `userId` → `users.id` | belongs to `User` · has many `job_applications` |
| `job_applications` | `status` (pending \| accepted \| rejected), `aiGeneratedScore`, `aiGeneratedFeedback`, `jobVacancyID`, `resumeID`, `userID` | belongs to `JobVacancy`, `Resume`, and `User` (applicant) |
| `notifications` | standard Laravel database-notifications table | polymorphic, tied to `users` |

## Route Map

All routes below additionally require `auth`.

### Admin + Company-owner (`role:admin,company-owner`)

| Method | URI | Action |
|---|---|---|
| GET | `/` | Dashboard |
| GET/POST/PUT/DELETE | `/job-applications...` | Full resource — review, update status, delete |
| PUT | `/job-applications/{id}/restore` | Restore a soft-deleted application |
| GET | `/job-applications/{id}/resume` | View applicant's résumé |
| GET/POST/PUT/DELETE | `/job-vacancies...` | Full resource — create, edit, delete vacancies |
| PUT | `/job-vacancies/{id}/restore` | Restore a soft-deleted vacancy |
| DELETE | `/notifications/{id}` | Delete a notification |
| POST | `/notifications/{id}/read` | Mark a notification as read |

### Company-owner only (`role:company-owner`)

| Method | URI | Action |
|---|---|---|
| GET | `/my-company` | View own company profile |
| GET | `/my-company/edit` | Edit form |
| PUT | `/my-company` | Update own company profile |

### Admin only (`role:admin`)

| Method | URI | Action |
|---|---|---|
| GET/POST/PUT/DELETE | `/users...` | Full resource — manage platform users |
| PUT | `/users/{id}/restore` | Restore a soft-deleted user |
| GET/POST/PUT/DELETE | `/companies...` | Full resource — manage every company |
| PUT | `/companies/{id}/restore` | Restore a soft-deleted company |
| GET/POST/PUT/DELETE | `/job-categories...` | Full resource — manage categories |
| PUT | `/job-categories/{id}/restore` | Restore a soft-deleted category |

Plus the standard Breeze auth routes (`/login`, `/register`, `/forgot-password`, email verification, etc.) from `routes/auth.php`.

## Project Structure

```
app/
├── Http/
│   ├── Controllers/        # ApplicationController, JobVacancyController, CompanyController,
│   │                        # UserController, categoryController, DashboardController
│   ├── Middleware/          # RoleMiddleware
│   └── Requests/            # Per-model Form Requests (Company, JobVacancy, JobCategory, User)
├── Providers/
│   ├── AppServiceProvider.php
│   └── RepositoryServiceProvider.php   # role-based repository binding
├── Repositories/
│   ├── Interfaces/DashboardRepositoryInterface.php
│   ├── AdminDashboardRepository.php
│   └── CompanyDashboardRepository.php
├── Services/
│   └── DashboardService.php
└── View/Components/         # AppLayout, AuthLayout, GuestLayout, Notifications

resources/views/
├── Dashboard/
├── Company/
├── Job Vacancy/
├── Job Category/
├── JobApplication/
├── User/
├── layouts/ & components/
└── auth/ & profile/

database/migrations/          # users, companies, job_categories, job_vacancies, resumes, job_applications, ...
routes/web.php                 # role-scoped route groups
```

(Models, and the `newJobApply` notification, live in the separate [`job/shared`](https://github.com/YoussefSayed-cs/job-shared) package.)

## Getting Started

### Prerequisites

- PHP >= 8.2
- Composer
- Node.js & npm
- MariaDB/MySQL

### Installation

```bash
git clone https://github.com/YoussefSayed-cs/job-backoffice.git
cd job-backoffice

composer install
cp .env.example .env
php artisan key:generate

# configure your database in .env, then:
php artisan migrate

npm install
npm run build
```

### Running locally

```bash
composer dev
```
This single command runs four processes concurrently:

| Process | What it does |
|---|---|
| `php artisan serve` | The Laravel dev server |
| `php artisan queue:listen --tries=1` | Processes queued jobs (notifications, etc.) |
| `php artisan pail --timeout=0` | Live-tails the application log in your terminal |
| `npm run dev` | Vite dev server with hot module reload |

### ⚠️ Important — shared database

**job-app does not ship its own migrations for the core domain tables** (users, companies, job vacancies, applications, resumes) — it relies entirely on the models from `job/shared`. Run the migrations **from this repo (job-backoffice)** to create the schema, then point job-app's `.env` at the **same database** (same `DB_HOST` / `DB_DATABASE` / credentials). Running each app against a separate, freshly-migrated database will leave job-app without the tables it needs.

### Environment variables

| Variable | Purpose |
|---|---|
| `APP_NAME`, `APP_ENV`, `APP_URL`, `APP_DEBUG` | Standard Laravel app config |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection — **must match job-app's `.env`**, see note above |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | Session storage (database-backed by default) |
| `QUEUE_CONNECTION` | Queue driver (`database` by default) |
| `CACHE_STORE` | Cache driver (`database` by default) |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Optional Redis config |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS` | Outgoing mail (logged locally by default) |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` | S3-compatible bucket for file storage, shared with job-app |
| `VITE_APP_NAME` | App name exposed to the frontend build |

## Roles & Permissions

| Role | Access |
|---|---|
| `admin` | Full access: users, companies, job categories, all vacancies & applications |
| `company-owner` | Their own company's vacancies and applications only |
| `job-seeker` | No access to this app — see [job-app](https://github.com/YoussefSayed-cs/job-app) |

## Testing

```bash
composer test
```
Runs the Pest test suite (`tests/Feature`, `tests/Unit`) after clearing cached config.

## Related Repositories

- [job-app](https://github.com/YoussefSayed-cs/job-app) — job seeker-facing application with AI-powered résumé screening
- [job-shared](https://github.com/YoussefSayed-cs/job-shared) — shared models and notifications package

## License

MIT
