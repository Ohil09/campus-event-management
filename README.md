<div align="center">

# CampusEvent

### A single place to discover, organize, and bring campus events to life.

**Campus Event & Volunteer Management System**
Built with PHP, MariaDB, HTML, CSS, and vanilla JavaScript.

<br>

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-10.4%2B-003545?style=for-the-badge&logo=mariadb&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Local%20Development-FB7A24?style=for-the-badge&logo=apachefriends&logoColor=white)

</div>

---

## What is CampusEvent?

CampusEvent is a role-based web application for managing the full lifecycle of college events. It connects students, event organizers, volunteers, and administrators through dedicated dashboards and workflows.

From publishing an event to collecting registrations, coordinating volunteers, and reviewing feedback, the platform keeps campus activities organized in one place.

## Highlights

| Area | What it provides |
| --- | --- |
| **Student experience** | Discover upcoming events, search by title or venue, filter by category, register, manage registrations, apply for volunteer opportunities, and submit feedback. |
| **Organizer workspace** | Create and manage events, monitor registrations, define volunteer roles, and review event feedback and reports. |
| **Volunteer coordination** | Browse available opportunities, submit applications, and track application or assignment status. |
| **Administration** | View system-wide statistics, monitor users and events, review registrations and applications, and create organizer accounts. |
| **Authentication** | Session-based login, role-based access control, password hashing, CSRF-protected forms, and student self-registration. |

## User roles

- **Student** — finds events, registers to attend, volunteers, and shares feedback.
- **Organizer** — plans events and manages participation and volunteer requirements.
- **Administrator** — oversees the platform and provisions organizer accounts.

## Technology stack

- **Backend:** PHP 8.2+
- **Frontend:** HTML5, CSS3, vanilla JavaScript
- **Database:** MariaDB 10.4+ / MySQL-compatible database
- **Local server:** Apache through XAMPP
- **Database tools:** phpMyAdmin and SQL scripts

## Project structure

```text
Event_management/
├── admin/                 # Administrator dashboard and reports
├── auth/                  # Login, registration, logout, and access checks
├── config/                # Database connection and environment loading
├── database/              # Schema, sample data, and cleanup scripts
├── docs/                  # Development and setup documentation
├── organizer/             # Organizer dashboard and event management
├── public/
│   ├── css/               # Role-specific stylesheets
│   ├── images/            # Static images
│   └── js/                # Shared browser-side JavaScript
├── student/               # Student event and volunteer workflows
├── volunteer/             # Volunteer management screens
├── .env.example           # Environment configuration template
└── index.php              # Role-aware application entry point
```

## Quick start with XAMPP

### 1. Prerequisites

Install:

- [XAMPP](https://www.apachefriends.org/download.html) with Apache, PHP, MariaDB, and phpMyAdmin
- Git
- A code editor such as Visual Studio Code

The project is developed against XAMPP 8.2.12, PHP 8.2.12, Apache 2.4.58, and MariaDB 10.4.32.

### 2. Place the project in `htdocs`

Clone or copy the repository to:

```text
C:\xampp\htdocs\Event_management
```

### 3. Start the services

Open the XAMPP Control Panel and start:

- **Apache**
- **MySQL** (the MariaDB service bundled with XAMPP)

### 4. Create the database

Open `http://localhost/phpmyadmin/`, then import these files in order:

1. `database/schema.sql`
2. `database/sample_data.sql` *(optional; useful for demonstration and testing)*

The schema creates a database named `event_management_db`.

### 5. Configure the application

Create `.env` in the project root by copying `.env.example`, then use values matching your local MariaDB installation:

```dotenv
APP_NAME=Campus Event Management System
APP_ENV=development

DB_HOST=localhost
DB_PORT=3306
DB_NAME=event_management_db
DB_USER=root
DB_PASSWORD=
```

Keep `.env` local and never commit passwords or other secrets.

### 6. Open CampusEvent

Visit:

```text
http://localhost/Event_management/
```

The application redirects authenticated users to the dashboard for their role. New visitors can use the login or student registration pages.

## Sample accounts

The optional `database/sample_data.sql` file includes demonstration users. Use the credentials configured for your copy of the sample data, and change or remove sample accounts before deploying the application.

> **Security note:** Sample passwords are for local development only. Do not use sample credentials in production.

## Database model

The core schema supports:

- Users and role/status management
- Event categories and event lifecycle states
- Event registrations
- Volunteer opportunities, applications, and assignments
- Event ratings and feedback

To reset the database during local development, review `database/remove_all.sql` before running it. It is destructive and should not be used against a production database.

## Documentation

For the detailed Windows/XAMPP development workflow, see [`docs/GUIDE.md`](docs/GUIDE.md).

## Development notes

- Use prepared statements for database queries.
- Keep authentication and authorization checks in place when adding protected pages.
- Preserve the existing role-based folder structure.
- Do not commit `.env` or other environment-specific secrets.
- Test changes with Apache and MariaDB running locally.

## Project status

CampusEvent is an active academic/project application under development. Features and interfaces may evolve as the system grows.

---

<div align="center">

**Plan better events. Connect your campus. Make participation easier.**

</div>
