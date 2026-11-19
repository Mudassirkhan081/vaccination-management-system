# TeekaCard – Vaccination Management System

TeekaCard is a PHP and MySQL web application that turns a child's paper
vaccination card into a live schedule. Parents see every due date, book a
hospital visit, and follow each dose until the hospital records it. The
administrator approves bookings, manages hospitals and vaccines, and prints
date-wise reports. Hospitals see their confirmed appointments and record the
result of every vaccine.

Aptech eProject: **Vaccination Management System** (PHP, MySQL, Apache/XAMPP)

---

## 1. Features

| Module | What it does |
|---|---|
| **Administrator** | Dashboard with key numbers and a six-month chart · all child details with filters and a card view · upcoming vaccination dates of all children and confirmed date/time of visits · date-wise and child-wise vaccination report (print and CSV) · vaccine list with Available/Unavailable switch (add, edit, delete) · approve or reject parent requests · add, update, delete, approve and deactivate hospitals · all booking details with a timeline · parent accounts (block/unblock) · contact messages |
| **Parent** | Register and log in · add, edit and delete children · digital vaccination card with due dates · reminders for overdue and upcoming visits on the dashboard and as notifications · book a hospital (choose vaccines, hospital, date and time) · cancel a booking · record earlier doses from the paper card · printable vaccination report · my profile and password |
| **Hospital** | Register (name, address, location, timings) and log in after approval · today's, upcoming and past appointments · record the result of each vaccine (Given / Not given, with a remark) · dashboard · my profile and password |
| **Public site** | Home page, routine vaccine schedule with a due-date calculator, hospital directory with search, contact form |

---

## 2. Requirements

* **XAMPP** (Windows, Linux or macOS) with Apache and MySQL/MariaDB
  * PHP **7.4 or newer** (tested on PHP 8.3)
  * MySQL 5.7+ or MariaDB 10.4+ (tested on MariaDB 10.11)
* Any modern browser (Chrome, Edge, Firefox)
* No internet connection is needed. Bootstrap, Bootstrap Icons, Chart.js and
  the fonts are included in `assets/`.

---

## 3. Installation (XAMPP)

1. Copy the project folder **`vms`** into `C:\xampp\htdocs\`
   (so that `C:\xampp\htdocs\vms\index.php` exists).
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Open **http://localhost/phpmyadmin**, click **Import**, choose
   `vms/database/vaccination_db.sql` and press **Go**.
   The script creates the database `vaccination_db`, all 10 tables and the demo data.
4. Open **http://localhost/vms/** in the browser.

**Command-line import (optional):**

```
cd C:\xampp\mysql\bin
mysql -u root -p < C:\xampp\htdocs\vms\database\vaccination_db.sql
```

(Press Enter at the password prompt if the root password is empty.)

### Database settings

The connection settings are in `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'vaccination_db');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP default: empty password
```

Change them only if your MySQL user or password is different.

---

## 4. Login credentials

Open **http://localhost/vms/login.php** and choose the account type first.

| Role | Email | Password | Notes |
|---|---|---|---|
| Administrator | `admin@teekacard.pk` | `Admin@123` | Full access |
| Parent | `parent@teekacard.pk` | `Parent@123` | Ayesha Khan – children Hania and Zayan |
| Hospital | `hospital@teekacard.pk` | `Hospital@123` | Crescent Children's Hospital, Karachi |

More demo accounts (same passwords):

| Role | Email | Status |
|---|---|---|
| Parent | `bilal.ahmed@example.com` | Active – child Inaya (9-month visit overdue) |
| Parent | `sana.tariq@example.com` | Active – child Rayan (visit booked tomorrow) |
| Parent | `usman.raza@example.com` | Active – child Mahnoor (rejected, missed and new request) |
| Hospital | `nazimabad@example.com` | Active |
| Hospital | `seaview@example.com` | Active |
| Hospital | `mallroad@example.com` | Active (Lahore) |
| Hospital | `kotri.mcc@example.com` | **Pending** – cannot log in until the admin approves it |

**Database:** user `root`, empty password, database `vaccination_db` (XAMPP default).

> Demo dates are stored relative to the day the SQL file is imported, so the
> sample data always looks current. Re-import the file to refresh the demo.

---

## 5. Five-minute demo walkthrough

1. **Parent** (`parent@teekacard.pk`): the dashboard shows Hania's 14-week visit
   booked for **today** and Zayan's 6-week request waiting for approval.
   Open *My children → Hania* to see the vaccination card with stamped visits.
2. **Administrator** (`admin@teekacard.pk`): open *Requests*, approve
   **TC-00006** (you may move the time) and reject or approve **TC-00020**.
   The parent and the hospital receive notifications.
3. **Hospital** (`hospital@teekacard.pk`): the approved visits now appear under
   *Next 7 days*. Open *Appointments → Today* and press **Record result** for
   Hania. Mark IPV-1 as *Not given* and add a remark, then save.
4. **Parent** again: the notification bell shows the result. The card shows the
   given doses and "Not given on …" for IPV-1, which can be booked again.
   *Vaccination report* prints the full record.
5. **Administrator**: *Vaccination report* (date-wise / child-wise, CSV),
   *Vaccines* (switch TCV to Available), *Hospitals* (approve Kotri Mother &
   Child Centre), *Vaccination dates* (all upcoming and overdue visits).

---

## 6. Folder structure

```
vms/
├── index.php, schedule.php, hospitals.php, contact.php   Public pages
├── login.php, register.php, hospital-register.php, logout.php
├── admin/        Administrator panel (dashboard, requests, children, due dates,
│                 reports, bookings, vaccines, hospitals, parents, messages)
├── parent/       Parent panel (dashboard, children, child card, booking,
│                 bookings, report)
├── hospital/     Hospital panel (dashboard, appointments)
├── includes/
│   ├── init.php          Loads settings, session and helper files
│   ├── db.php            PDO connection and query helpers
│   ├── functions.php     Escaping, links, CSRF, validation, dates, pagination
│   ├── auth.php          Login, logout, role checks, login lock
│   ├── schedule.php      Vaccination schedule engine (due dates and states)
│   ├── bookings.php      Booking helpers (slots, stages, vaccine chips)
│   ├── accounts.php      Shared account form rules
│   ├── notify.php        Notifications
│   ├── layout/           Page headers and footers
│   ├── partials/         Vaccination card
│   └── pages/            Notifications and profile pages shared by all roles
├── assets/       css/app.css, js/app.js, img/, fonts/, vendor/ (Bootstrap,
│                 Bootstrap Icons, Chart.js – all offline)
├── config/config.php
└── database/
    ├── vaccination_db.sql          Create + demo data (import this)
    └── backups/                    mysqldump backup of the database
```

---

## 7. Security measures

* All SQL uses **PDO prepared statements** (no values are joined into SQL).
* Passwords are stored with **bcrypt** (`password_hash`, `password_verify`).
* Every form that changes data has a **CSRF token**; logout works only by POST.
* All output is escaped with `htmlspecialchars` (protection against XSS).
* Session id is regenerated at login; cookies are `HttpOnly` and `SameSite=Lax`.
* Five failed logins lock the login form for 60 seconds.
* Every protected page re-checks the role and account status in the database,
  so a blocked parent or an inactive hospital is logged out immediately.
* Parents and hospitals can only open their own records.
* Security headers stop other sites from framing the pages
  (`X-Frame-Options`) and stop MIME sniffing (`X-Content-Type-Options`).
* The contact form has a hidden spam trap and a 30-second limit.

---

## 8. Troubleshooting

| Problem | Solution |
|---|---|
| "The database is not ready yet" | Start MySQL in XAMPP, import `database/vaccination_db.sql`, and check `config/config.php`. |
| Page without styles, or broken links | The web path is detected automatically. If needed, set `BASE_PATH_OVERRIDE` in `config/config.php`, e.g. `'/vms'`. |
| MySQL does not start (port 3306 busy) | Stop the other MySQL service, or change the port in XAMPP and in `DB_PORT`. |
| Demo dates look old | Re-import `database/vaccination_db.sql`; the dates are created relative to the import day. |
| "Too many failed attempts" | Wait 60 seconds and try again. |
| Want to hide PHP error details | Set `APP_DEBUG` to `false` in `config/config.php`. |

---

## 9. Configuration options (`config/config.php`)

| Setting | Default | Meaning |
|---|---|---|
| `APP_TIMEZONE` | `Asia/Karachi` | Time zone for all dates |
| `PER_PAGE` | `15` | Rows per page in long tables |
| `DUE_SOON_DAYS` | `14` | A dose counts as "due soon" inside this many days |
| `MAX_BOOKING_DAYS` | `180` | How far ahead parents can book |
| `SLOT_CAPACITY` | `3` | Children per hospital per half-hour slot |
| `MAX_CHILD_AGE_YEARS` | `12` | Oldest child that can be added |
| `LOGIN_MAX_ATTEMPTS` / `LOGIN_LOCK_SECONDS` | `5` / `60` | Login lock rules |

---

## 10. Credits

* Bootstrap 5.3 and Bootstrap Icons – MIT License
* Chart.js 4 – MIT License
* Atkinson Hyperlegible Next and Zilla Slab fonts – SIL Open Font License
  (licence texts in `assets/fonts/`)
* The vaccine schedule is modelled on Pakistan's Expanded Programme on
  Immunization (EPI) for learning purposes only; it is not medical advice.
