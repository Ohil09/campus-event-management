# Development Setup Guide

This guide explains how a new team member can set up the **Campus Event & Volunteer Management System** from a fresh Windows machine and start development using the same environment as the rest of the team.

---

## 1. Technology Stack

| Component | Technology |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Backend | PHP |
| Web Server | Apache |
| Database | MariaDB |
| Database Management | phpMyAdmin |
| Local Development Stack | XAMPP |
| Version Control | Git + GitHub |

### Standard Team Environment

The current team environment is:

- **XAMPP:** 8.2.12
- **PHP:** 8.2.12
- **Apache:** 2.4.58
- **MariaDB:** 10.4.32
- **phpMyAdmin:** 5.2.1

Use the same XAMPP version as the rest of the team wherever possible.

Official XAMPP website:

https://www.apachefriends.org/download.html

---

# 2. Prerequisites

Install the following:

1. Git
2. Visual Studio Code (recommended)
3. XAMPP 8.2.12

### No separate MySQL installation

Do **not** install a separate MySQL server for this project.

The project uses the **MariaDB database bundled with XAMPP**.

---

# 3. Install XAMPP

## Step 1 — Download XAMPP

Open:

https://www.apachefriends.org/download.html

Under **XAMPP for Windows**, download:

**XAMPP 8.2.12 / PHP 8.2.12 (64-bit)**

## Step 2 — Install XAMPP

Use:

```text
C:\xampp
```

as the installation directory.

Avoid:

```text
C:\Program Files\
```

to reduce Windows permission/UAC problems.

## Step 3 — Components

For this project, the required components are:

- Apache
- MySQL (MariaDB)
- PHP
- phpMyAdmin

The other XAMPP services are not required for normal project development.

## Step 4 — Start XAMPP

Open:

```text
C:\xampp\xampp-control.exe
```

Start:

```text
Apache
MySQL
```

---

# 4. Verify XAMPP

Open the following URLs.

### XAMPP Dashboard

```text
http://localhost/
```

### phpMyAdmin

```text
http://localhost/phpmyadmin/
```

Both should open successfully.

You should also see **Apache** and **MySQL** running in the XAMPP Control Panel.

---

# 5. Clone the GitHub Repository

## Step 1 — Open PowerShell

Open PowerShell or the VS Code terminal.

## Step 2 — Move to XAMPP's htdocs directory

```powershell
cd C:\xampp\htdocs
```

## Step 3 — Clone the repository

Replace `<REPOSITORY_URL>` with the team's actual GitHub repository URL.

```powershell
git clone <REPOSITORY_URL> Event_management
```

The final location should be:

```text
C:\xampp\htdocs\Event_management
```

## Step 4 — Enter the project

```powershell
cd C:\xampp\htdocs\Event_management
```

## Step 5 — Switch to the development branch

The project uses:

```text
main   → stable code
develop → active development
```

Run:

```powershell
git switch develop
git pull origin develop
```

## Step 6 — Verify Git

```powershell
git status
```

A clean repository should show that the working tree is clean.

---

# 6. Project Directory Structure

The current project follows this modular structure:

```text
Event_management/
│
├── admin/
├── auth/
├── config/
├── database/
├── docs/
├── organizer/
├── public/
├── student/
├── volunteer/
│
├── .env.example
├── .gitignore
├── index.php
└── README.md
```

Do not rename or reorganize shared folders without discussing the change with the team.

---

# 7. Environment Configuration

The repository contains:

```text
.env.example
```

Create your own local:

```text
.env
```

in the project root.

Use:

```env
APP_NAME=Campus Event Management System
APP_ENV=development

DB_HOST=localhost
DB_PORT=3306
DB_NAME=event_management_db
DB_USER=root
DB_PASSWORD=
```

The actual `.env` file is intentionally ignored by Git.

### Important

Never commit:

```text
.env
```

to GitHub.

Only:

```text
.env.example
```

should be committed.

---

# 8. Database Setup

The project uses:

```text
Database: event_management_db
Engine: MariaDB
Port: 3306
```

The repository contains:

```text
database/
├── schema.sql
└── sample_data.sql
```

Run the scripts in exactly this order:

```text
1. schema.sql
2. sample_data.sql
```

Do not reverse the order.

---

# 9. Run `schema.sql`

Open:

```text
http://localhost/phpmyadmin/
```

Then:

1. Open the **SQL** tab.
2. Open `database/schema.sql` from the cloned repository.
3. Copy the complete contents.
4. Paste the SQL into phpMyAdmin.
5. Execute the script.

The script creates:

```text
event_management_db
```

with the project's core tables:

```text
users
categories
events
event_registrations
volunteer_opportunities
volunteer_applications
volunteer_assignments
attendance
feedback
```

---

# 10. Verify the Schema

Select:

```text
event_management_db
```

in phpMyAdmin.

Or run:

```sql
SHOW TABLES;
```

You should see:

```text
attendance
categories
event_registrations
events
feedback
users
volunteer_applications
volunteer_assignments
volunteer_opportunities
```

---

# 11. Run `sample_data.sql`

Only after `schema.sql` executes successfully:

1. Select `event_management_db`.
2. Open the **SQL** tab.
3. Open `database/sample_data.sql`.
4. Copy the complete contents.
5. Paste it into phpMyAdmin.
6. Execute it.

This provides common development/test data for the team.

It populates:

- Users
- Categories
- Events
- Event registrations
- Volunteer opportunities
- Volunteer applications
- Volunteer assignments
- Attendance
- Feedback

---

# 12. Verify Sample Data

Run:

```sql
SELECT COUNT(*) AS total_users
FROM users;
```

and:

```sql
SELECT COUNT(*) AS total_events
FROM events;
```

You can also inspect:

```sql
SELECT * FROM users;
```

```sql
SELECT * FROM events;
```

```sql
SELECT * FROM event_registrations;
```

---

# 13. Database Connection Configuration

Shared PHP database connection logic is stored in:

```text
config/database.php
```

Local database settings are stored in:

```text
.env
```

The connection flow is:

```text
.env
  ↓
config/database.php
  ↓
mysqli
  ↓
MariaDB
```

Do not place personal database credentials directly inside application pages.

---

# 14. Run the Project

Make sure XAMPP has:

```text
Apache → Running
MySQL  → Running
```

Then open:

```text
http://localhost/Event_management/
```

The application should load through Apache and PHP.

---

# 15. Test Authentication

### Register

```text
http://localhost/Event_management/auth/register.php
```

### Login

```text
http://localhost/Event_management/auth/login.php
```

The application uses:

```text
Email
Password
```

and redirects according to the stored role.

Current role destinations:

```text
Student
→ /student/

Organizer
→ /organizer/organizer-dashboard.php

Administrator
→ /admin/
```

If a destination module has not yet been developed, Apache may display the directory instead of a dashboard. This does not necessarily indicate an authentication problem.

---

# 16. Git / GitHub Workflow

## Branches

```text
main
develop
```

Use:

- `main` for stable milestones
- `develop` for ongoing development and integration

Avoid direct development on `main`.

## Before starting work

Always update your local development branch:

```powershell
git switch develop
git pull origin develop
```

## Feature branches

Create your feature branch from the latest `develop`:

```powershell
git switch develop
git pull origin develop
git switch -c feature/your-feature-name
```

Examples:

```text
feature/member1-student
feature/member2-organizer
feature/member3-volunteer
feature/member4-admin
```

## Commit and push

```powershell
git add .
git commit -m "feat: describe your change"
git push -u origin feature/your-feature-name
```

Use meaningful commit messages such as:

```text
feat: add organizer event form
fix: prevent duplicate event registration
style: improve mobile event layout
docs: update development setup
```

---

# 17. Working With Shared Files

Some parts of the project are shared by multiple team members:

```text
config/database.php
public/css/
public/js/
auth/
database/
shared layout/components
```

Before changing shared functionality:

1. Pull the latest `develop`.
2. Check whether another member is already changing the same file.
3. Make focused commits.
4. Test your changes before pushing.

Do not overwrite another member's work by copying an older local version over a newer repository version.

---

# 18. New Team Member Setup Checklist

A new team member should be able to complete:

```text
[ ] Install Git
[ ] Install VS Code
[ ] Install XAMPP 8.2.12
[ ] Start Apache
[ ] Start MariaDB/MySQL
[ ] Verify http://localhost/
[ ] Verify http://localhost/phpmyadmin/
[ ] Clone repository into C:\xampp\htdocs\
[ ] Switch to develop
[ ] Create local .env
[ ] Run database/schema.sql
[ ] Run database/sample_data.sql
[ ] Verify database tables
[ ] Verify sample data
[ ] Open http://localhost/Event_management/
[ ] Test registration
[ ] Test login
[ ] Create a feature branch
[ ] Start assigned module work
```

---

# 19. Exact First-Day Setup Order

```text
Install Git
    ↓
Install VS Code
    ↓
Install XAMPP 8.2.12
    ↓
Start Apache + MariaDB
    ↓
Verify localhost
    ↓
Verify phpMyAdmin
    ↓
Clone GitHub repository
    ↓
Switch to develop
    ↓
Create .env
    ↓
Run schema.sql
    ↓
Run sample_data.sql
    ↓
Verify tables and data
    ↓
Run PHP application
    ↓
Test Register
    ↓
Test Login
    ↓
Create feature branch
    ↓
Start assigned development
```

---

# 20. Troubleshooting

## Apache does not start

Check whether another application is using port 80:

```powershell
netstat -ano | findstr :80
```

## MariaDB/MySQL does not start

Check port 3306:

```powershell
netstat -ano | findstr :3306
```

Do not immediately delete MariaDB data files or reinstall XAMPP. Diagnose the error first.

## `.env` is not loading

Make sure the file is exactly:

```text
C:\xampp\htdocs\Event_management\.env
```

and not:

```text
.env.txt
```

## Project does not open

Verify that:

```text
C:\xampp\htdocs\Event_management
```

exists and Apache is running.

Then open:

```text
http://localhost/Event_management/
```

## Database connection fails

Check:

```text
DB_HOST=localhost
DB_PORT=3306
DB_NAME=event_management_db
DB_USER=root
DB_PASSWORD=
```

and verify that MariaDB is running in XAMPP.

---

# 21. Final Pre-Development Checklist

Before starting module work, confirm:

```text
[ ] Apache is running
[ ] MariaDB is running
[ ] event_management_db exists
[ ] All 9 core tables exist
[ ] Sample data exists
[ ] Local .env exists
[ ] PHP can connect to MariaDB
[ ] Project opens through localhost
[ ] Git branch is up to date
[ ] Work is being done on develop or a feature branch
```

Once these checks pass, the project is ready for feature development.

---

# 22. Final Project Architecture

```text
Campus Event & Volunteer Management System
│
├── Module 1
│   └── Authentication & Common Components
│
├── Module 2
│   └── Student Event Participation
│
├── Module 3
│   └── Organizer Event Management
│
├── Module 4
│   └── Volunteer Management
│
└── Module 5
    └── Administration, Attendance & Feedback
```

Authentication is shared by all modules.

The database is shared by all modules.

The project should remain focused on the requirements of the MCA Web Technologies mini project and should not introduce unnecessary frameworks or services without a clear need.
