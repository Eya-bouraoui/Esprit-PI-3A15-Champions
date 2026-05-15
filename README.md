# 📈 FinTrack — Fintech Trading Platform

> A full-stack trading and financial management platform built with Symfony 6.4, featuring AI-powered fraud detection, secure authentication, and a rich set of fintech integrations.

---

## 📋 Table of Contents

- [About the Project](#about-the-project)
- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Team & Modules](#team--modules)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
  - [Environment Setup](#environment-setup)
  - [Running the Application](#running-the-application)
- [Branch Strategy](#branch-strategy)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## About the Project

**FinTrack** is a collaborative academic project developed at **ESPRIT** (École Supérieure Privée d'Ingénierie et de Technologies) by team **3A15 – Champions**. It is a comprehensive fintech trading platform that integrates stock trading, payment processing, fraud detection, user authentication, and portfolio management into a single web application.

The platform combines a **PHP/Symfony** backend with a dedicated **Python-based fraud detection microservice**, delivering a robust and secure financial environment.

---

## Key Features

- 🔐 **Secure Authentication** — JWT tokens, Google OAuth 2.0, Auth0, and Two-Factor Authentication (TOTP-based 2FA)
- 💳 **Payment Processing** — Stripe integration for secure online transactions
- 🤖 **AI Fraud Detection** — Dedicated Python microservice for real-time fraud analysis
- 📊 **Portfolio & Trade Management** — Track holdings, execute trades, and monitor performance
- 📅 **Calendar & Scheduling** — Built-in calendar for events and reminders
- 📄 **PDF Generation** — On-demand document export with DomPDF
- 📱 **SMS Notifications** — Twilio integration for real-time alerts
- 📧 **Email Notifications** — Gmail mailer integration
- ☁️ **Media Management** — Cloudinary for image uploads and storage
- 🔢 **QR Code Support** — QR code generation for 2FA and sharing
- 🌐 **Internationalization** — Multi-language support via Symfony Translation
- 📄 **Pagination** — KnpPaginator for efficient data navigation

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend Framework** | Symfony 6.4 (PHP ≥ 8.1) |
| **Fraud Detection API** | Python (FastAPI / Flask microservice) |
| **Database** | PostgreSQL 16 (via Docker) |
| **ORM** | Doctrine ORM 3.x |
| **Templating** | Twig 3.x |
| **Frontend Assets** | Symfony AssetMapper, Stimulus, Turbo |
| **Authentication** | JWT (LexikJWT), OAuth2 (Google/Auth0), 2FA (scheb/2fa) |
| **Payments** | Stripe PHP SDK |
| **Media Storage** | Cloudinary |
| **Notifications** | Twilio (SMS), Symfony Mailer (Gmail) |
| **PDF Generation** | DomPDF |
| **QR Codes** | endroid/qr-code |
| **Containerization** | Docker & Docker Compose |
| **Testing** | PHPUnit 11 |
| **Static Analysis** | PHPStan |

---

## Project Structure

```
.
├── assets/              # Frontend assets (JS, CSS)
├── bin/                 # Symfony console entry point
├── config/              # Symfony configuration files
├── fraud_api/           # Python fraud detection microservice
├── migrations/          # Doctrine database migrations
├── public/              # Web server document root
├── src/                 # PHP application source code
│   ├── Controller/      # Symfony controllers
│   ├── Entity/          # Doctrine entities
│   ├── Form/            # Symfony form types
│   ├── Repository/      # Doctrine repositories
│   └── Service/         # Business logic services
├── templates/           # Twig templates
├── tests/               # PHPUnit test suites
├── translations/        # i18n translation files
├── compose.yaml         # Docker Compose (production)
├── compose.override.yaml# Docker Compose (development overrides)
└── composer.json        # PHP dependencies
```

---

## Team & Modules

This project was built collaboratively. Each team member owned a dedicated feature branch and module:

| # | Module | Description |
|---|--------|-------------|
| 1 | **User Management & Auth** | Registration, login, JWT, OAuth2 (Google/Auth0), 2FA |
| 2 | **Trading & Portfolio** | Asset management, trade execution, portfolio tracking |
| 3 | **Payments & Transactions** | Stripe payments, transaction history, invoicing |
| 4 | **Fraud Detection** | Python microservice for anomaly detection on transactions |
| 5 | **Notifications & Alerts** | Email (Gmail), SMS (Twilio), and in-app notifications |
| 6 | **Dashboard & Reporting** | Charts, PDF exports, QR codes, calendar integration |

> The integrated and final version of the application lives on the **`dev`** branch.

---

## Getting Started

### Prerequisites

Make sure you have the following installed:

- [PHP](https://www.php.net/) >= 8.1
- [Composer](https://getcomposer.org/)
- [Docker](https://www.docker.com/) & [Docker Compose](https://docs.docker.com/compose/)
- [Symfony CLI](https://symfony.com/download) (recommended)
- [Python](https://www.python.org/) >= 3.9 (for the fraud detection API)
- [Node.js](https://nodejs.org/) >= 18 (for frontend assets)

### Installation

1. **Clone the repository and switch to the `dev` branch:**

```bash
git clone https://github.com/Aziz-Khaled/Esprit-PI-3A15-Champions.git
cd Esprit-PI-3A15-Champions
git checkout dev
```

2. **Install PHP dependencies:**

```bash
composer install
```

3. **Install the Python fraud detection API dependencies:**

```bash
cd fraud_api
pip install -r requirements.txt
cd ..
```

### Environment Setup

1. **Copy and configure the environment file:**

```bash
cp .env .env.local
```

2. **Edit `.env.local`** and fill in the required values:

```env
# Database
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"

# JWT Authentication
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret

# Auth0
AUTH0_DOMAIN=your_auth0_domain
AUTH0_CLIENT_ID=your_auth0_client_id
AUTH0_CLIENT_SECRET=your_auth0_client_secret

# Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLIC_KEY=pk_test_...

# Cloudinary
CLOUDINARY_URL=cloudinary://api_key:api_secret@cloud_name

# Twilio
TWILIO_ACCOUNT_SID=your_sid
TWILIO_AUTH_TOKEN=your_token
TWILIO_FROM=+1234567890

# Mailer
MAILER_DSN=gmail://user:password@default
```

3. **Generate JWT keys:**

```bash
php bin/console lexik:jwt:generate-keypair
```

### Running the Application

1. **Start the PostgreSQL database via Docker:**

```bash
docker compose up -d
```

2. **Run database migrations:**

```bash
php bin/console doctrine:migrations:migrate
```

3. **Start the Symfony development server:**

```bash
symfony server:start
```

4. **Start the Python fraud detection API** (in a separate terminal):

```bash
cd fraud_api
python app.py
```

5. **Access the application** at [http://localhost:8000](http://localhost:8000)

---

## Branch Strategy

| Branch | Purpose |
|--------|---------|
| `main` | Initial scaffolding / base setup |
| `dev` | ✅ **Latest integrated version** — all modules merged |
| `feature/*` | Individual module branches per team member |

> **Always use the `dev` branch** for the most up-to-date and complete version of the application.

---

## Testing

Run the full test suite with PHPUnit:

```bash
php bin/phpunit
```

Run static analysis with PHPStan:

```bash
vendor/bin/phpstan analyse
```

---

## Contributing

This is an academic project. Contributions from team members should follow this workflow:

1. Branch off from `dev` with a descriptive branch name
2. Implement your feature and write tests
3. Open a pull request targeting `dev`
4. Request a review from at least one teammate before merging

---

## License

This project is proprietary and developed for academic purposes at **ESPRIT – École Supérieure Privée d'Ingénierie et de Technologies**. All rights reserved.

---

<p align="center">
  Made with ❤️ by <strong>Team 3A15 – Champions</strong> · ESPRIT 2025/2026
</p>
