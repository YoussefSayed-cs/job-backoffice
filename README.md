# Job Backoffice

Admin and company management console for the job portal platform. Built with **Laravel 12**, this app is where **admins** manage the whole platform (users, companies, job categories) and **company owners** manage their own job vacancies and review applicants — including AI-generated resume scores produced by the companion [job-app](https://github.com/YoussefSayed-cs/job-app).

This repository is one of three that make up the platform:

| Repo | Role |
|---|---|
| **job-backoffice** *(this repo)* | Admin & company-owner dashboard |
| [job-app](https://github.com/YoussefSayed-cs/job-app) | Public-facing app for job seekers |
| [job-shared](https://github.com/YoussefSayed-cs/job-shared) | Shared Eloquent models/notifications used by both apps |

---

## Features

- **Role-based access control** — three roles (`admin`, `company-owner`, `job-seeker`) enforced via a custom `RoleMiddleware`, with route groups scoped per role.
- **Company & job vacancy management** — full CRUD with soft deletes and restore, categories, and vacancy types (Full-Time, Contract, Remote, Hybrid).
- **Applicant review** — company owners review applications submitted through job-app, including the AI-generated compatibility score and feedback, and can accept/reject candidates.
- **Role-aware analytics dashboard** — built with a Repository + Service layer pattern; admins see platform-wide stats, company owners see stats scoped to their own company (applications, vacancy views, conversion rate).
- **In-app notifications** — database-backed notifications (new applications, etc.) with read/unread state.
- **User management** — admin-only CRUD for platform users.

## Tech Stack

- **Backend:** Laravel 12, PHP ^8.2
- **Frontend:** Blade, Tailwind CSS, Alpine.js, Vite
- **Database:** MariaDB/MySQL, UUID primary keys throughout
- **Auth:** Laravel Breeze (session-based)
- **Storage:** S3-compatible cloud storage (via `league/flysystem-aws-s3-v3`)
- **Testing:** Pest
- **Shared domain layer:** [`job/shared`](https://github.com/YoussefSayed-cs/job-shared) Composer package (Eloquent models & notifications)

## Architecture Notes

- **Repository + Service pattern for analytics.** `DashboardRepositoryInterface` is bound at runtime in `RepositoryServiceProvider` to either `AdminDashboardRepository` or `CompanyDashboardRepository` depending on the logged-in user's role, so `DashboardService` and the controller never need to know which one they're talking to.
- **Shared domain models.** User, Company, JobVacancy, JobCategory, JobApplication, and Resume are defined once in `job/shared` and consumed by both this app and job-app, so both codebases stay in sync on the data model.

---

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
This runs the PHP server, queue listener, log viewer (`pail`), and Vite dev server together.

### ⚠️ Important — shared database

**job-app does not ship its own migrations for the core domain tables** (users, companies, job vacancies, applications, resumes) — it relies entirely on the models from `job/shared`. Run the migrations **from this repo (job-backoffice)** to create the schema, then point job-app's `.env` at the **same database** (same `DB_HOST`/`DB_DATABASE`/credentials). Running each app against a separate, freshly-migrated database will leave job-app without the tables it needs.

### Key environment variables

| Variable | Purpose |
|---|---|
| `DB_*` | Database connection — must match job-app's `.env` (see note above) |
| `AWS_*` | S3-compatible bucket for resume/file storage, shared with job-app |
| `QUEUE_CONNECTION` | Queue driver (`database` by default) |

## Roles & Permissions

| Role | Access |
|---|---|
| `admin` | Full access: users, companies, job categories, all vacancies & applications |
| `company-owner` | Their own company's vacancies and applications only |
| `job-seeker` | No access to this app — see job-app |

## Testing

```bash
composer test
```

## Related Repositories

- [job-app](https://github.com/YoussefSayed-cs/job-app) — job seeker-facing application with AI-powered resume screening
- [job-shared](https://github.com/YoussefSayed-cs/job-shared) — shared models and notifications package

## License

MIT
