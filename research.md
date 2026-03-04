# Webinar Platform Research Report

## 1. Project Overview

This project is a specialized webinar hosting platform built with **Laravel 12**, **Filament 4**, and **Inertia.js (Vue 3)**. It is designed as a multi-tenant system where different "Clients" can host their own webinars on custom subdomains (e.g., `client.templet.io`).

**Key Technologies:**
- **Backend Framework:** Laravel 12.x
- **Admin Panel:** Filament 4.x
- **Frontend:** Inertia.js 2.0 + Vue 3 + Vite
- **Database:** SQLite (local/dev), customizable for production.
- **Styling:** Tailwind CSS (inferred from Filament/Inertia stack).

## 2. Core Architecture

### 2.1 Multi-Tenancy Strategy
The platform uses a subdomain-based strategy to identify clients.
- **Middleware:** `App\Http\Middleware\DetectClientFromDomain` handles the logic. It extracts the subdomain from the request host (or custom headers like `X-Original-Host` for proxy setups) and finds the corresponding `Client` model by its `slug`.
- **Routes:**
  - **Production:** `http://{client-subdomain}.domain.com/webinars/{webinar-slug}`
  - **Development/Fallback:** `http://domain.com/client/{client-slug}/webinars/{webinar-slug}` (useful for local testing without subdomain configuration).

### 2.2 Data Models

* **Client (`App\Models\Client`)**
  - Represents a tenant/business.
  - Key attributes: `name`, `slug`, `logo`.
  - Relationships: Has many `Webinars` and `SocialMediaLinks`.

* **Webinar (`App\Models\Webinar`)**
  - Represents a specific event.
  - Key attributes: `title`, `slug`, `description`, `zoom_webinar_id`.
  - **Dynamic Features:**
    - `form_schema` (JSON): Defines the fields shown on the registration form.
    - `tracking_scripts` (JSON): Allows custom scripts (e.g., GTM, Pixel) per webinar.
    - `clay_webhook_url`: URL for the Clay integration.
  - Relationships: Belongs to `Client`, Has many `Submissions`.

* **Submission (`App\Models\Submission`)**
  - Represents a user registration.
  - Key attributes:
    - `data` (JSON): Stores the form responses based on the webinar's schema.
    - `utm_*` columns: Captures marketing tracking parameters (source, medium, campaign, etc.).
    - `sent_to_clay_at`: Timestamp for the Clay integration sync status.
  - Relationships: Belongs to `Webinar`.

* **SocialMediaLink (`App\Models\SocialMediaLink`)**
  - specific social media URLs for a client (e.g. LinkedIn, Twitter).

### 2.3 Integration: Clay
The platform features a specific integration with **Clay** (data enrichment platform).
- **Mechanism:** Webhook-based.
- **Trigger:** When a `Submission` is created.
- **Data Flow:** Registration data + UTM parameters + Context (Client/Webinar info) are sent to a webhook URL defined on the `Webinar` model.
- **Documentation:** `docs/INTEGRACION_CLAY.md` provides setup detailed instructions.

## 3. Application Logic

### 3.1 Webinar Controller (`WebinarController`)
- **`show` method:**
  - Resolves the `Client` (via middleware or route param).
  - Resolves the `Webinar`.
  - Renders the `Webinar/Show` Inertia page.
- **`store` method:**
  - Validates the request based on the dynamic `form_schema` stored in the webinar configuration.
  - Creates a `Submission` record.
  - Likely triggers the Clay webhook (logic to be confirmed in Observer or Service, but indicated by schema).

### 3.2 Admin Panel (Filament)
Located at `/admin`.
- **Resources:**
  - `Clients`: Manage tenants.
  - `Webinars`: Configure events, including the dynamic form schema.
  - `Submissions`: View registrations.
  - `Users`: Manage admin access.

## 4. Frontend Structure
- **Stack:** Vue 3 via Inertia.js.
- **Entry Point:** `resources/js/app.ts`.
- **Pages:** `resources/js/Pages/Webinar/Show.vue` is the main public-facing component for displaying the webinar and registration form.
- **Assets:** Configured via `vite.config.ts`.

## 5. Deployment & Infrastructure
- **Web Server Configuration:** Requires specific web server (Nginx/Apache) or Proxy setup to handle wildcard subdomains and pass the `X-Original-Host` header if necessary, as hinted by the `DetectClientFromDomain` middleware.
- **Docs:** `docs/CONFIGURACION_SUBDOMINIOS.md` and `docs/htaccess-client.md` contain critical infrastructure setup info.

## 6. Recent Developments (2026)
- **Feb 2026:** Specific enhancements for Clay integration (webhook URL field, sent timestamps).
- **Jan 2026:** Core table creations (Clients, Webinars, Submissions) and social media link support.
