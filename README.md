# KaamConnect - Local Worker Hiring and Employment Marketplace

**B.Tech Computer Science and Engineering | Final-Year Major Project**

KaamConnect is a location-based employment and worker hiring marketplace that connects skilled local workers with residential and commercial customers.

The platform helps customers find and hire local professionals such as electricians, plumbers, carpenters, painters, masons, cleaners and mechanics. Workers can create professional profiles, discover job opportunities and connect directly with customers.

---

## 1. Project Objectives

- Connect local workers directly with customers.
- Reduce dependency on middlemen and commission-based services.
- Help workers build professional digital profiles.
- Allow customers to post jobs and find suitable workers.
- Support communication between customers and workers.
- Provide identity verification and administrative management.
- Support both English and Hindi languages.

---

## 2. Technology Stack

| Component | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript |
| UI Framework | Bootstrap 5.3 |
| Icons | Font Awesome |
| Backend | PHP 8+ |
| Database | MySQL / MariaDB |
| Database Connectivity | PDO |
| Web Server | Apache |
| Local Development | XAMPP |
| Code Editor | Visual Studio Code |
| Version Control | Git and GitHub |

### Technical Highlights

- PHP-based backend architecture.
- PDO-based database connectivity.
- Prepared SQL statements.
- Role-based access for different user types.
- Responsive user interface.
- AJAX-based functionality where implemented.

---

## 3. Main Features

### Worker Module

- Worker registration and login.
- Manage professional information and profile.
- Select trade categories and skills.
- Add service location and wage expectations.
- Browse available customer job postings.
- Submit job applications.
- Track application status.
- Communicate with customers.
- Receive notifications.

### Customer Module

- Customer registration and login.
- Manage customer profile.
- Post new job requirements.
- Specify job category, budget and location.
- Browse and search available workers.
- View worker profiles.
- Review job applications.
- Hire suitable workers.
- Communicate with workers.
- Receive notifications.

### Administrator Module

- Admin authentication and dashboard.
- Monitor platform activity.
- Manage customer and worker accounts.
- Review worker verification requests.
- Manage job categories.
- Monitor job postings.
- Manage platform-related reports and activities.

---

## 4. User Roles

KaamConnect supports three primary user roles:

| Role | Responsibility |
|---|---|
| Worker | Find jobs and connect with customers |
| Customer | Post jobs and hire workers |
| Administrator | Manage and monitor the platform |

---

## 5. UI/UX Design

The application uses a modern blue-and-white visual theme.

| Design Element | Color |
|---|---|
| Primary Blue | `#1E40AF` |
| Secondary Blue | `#2563EB` |
| Accent Orange | `#F59E0B` |
| Background | `#F8FAFC` |
| Surface | `#FFFFFF` |
| Primary Text | `#1E293B` |
| Muted Text | `#64748B` |

The interface is designed to be responsive and accessible across different screen sizes.

---

## 6. Local Installation and Setup

### Prerequisites

- Windows 10 or Windows 11
- XAMPP
- PHP 8+
- MySQL or MariaDB
- Visual Studio Code
- Web browser

### Step 1: Clone the Repository

Open your terminal and run:

```bash
git clone https://github.com/mkmkushwahaa/KaamConnect.git
```

### Step 2: Move the Project

Place the project folder inside your XAMPP `htdocs` directory.

Example:

```text
C:\xampp\htdocs\KAAMCONNECT
```

### Step 3: Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### Step 4: Create the Database

1. Open phpMyAdmin.
2. Create a new database named `kaamconnect_db`.
3. Import the project's database SQL file if it is available in your local project.
4. Configure the database connection in your local configuration file.

**Note:** The production database export is intentionally excluded from this public repository. Use a clean development database schema or an authorized project backup.

### Step 5: Configure Database Connection

Configure your local database settings in the appropriate configuration file.

Do not upload database passwords, hosting credentials or email credentials to GitHub.

### Step 6: Run the Application

Open your browser:

```text
http://localhost:8080/kaamconnect/
```

---

## 7. Project Directory Structure

```text
KAAMCONNECT/
│
├── admin/
│   ├── includes/
│   ├── index.php
│   ├── notifications.php
│   ├── workers.php
│   ├── customers.php
│   ├── jobs.php
│   ├── categories.php
│   └── reports.php
│
├── api/
│   ├── messages.php
│   └── notifications.php
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/
│
├── config/
│   └── database.php
│
├── customer/
│   ├── dashboard.php
│   ├── profile.php
│   ├── post-job.php
│   ├── manage-jobs.php
│   ├── workers.php
│   ├── messages.php
│   └── notifications.php
│
├── database/
│   └── .htaccess
│
├── includes/
│   ├── auth.php
│   ├── functions.php
│   ├── mailer.php
│   ├── navbar.php
│   ├── header.php
│   └── footer.php
│
├── worker/
│   ├── dashboard.php
│   ├── profile.php
│   ├── jobs.php
│   ├── my-applications.php
│   ├── messages.php
│   └── notifications.php
│
├── login.php
├── register.php
├── logout.php
├── index.php
├── workers.php
├── jobs.php
├── about.php
├── contact.php
└── README.md
```

---

## 8. Database

KaamConnect uses **MySQL / MariaDB** as its relational database management system.

The database stores information related to:

- Users and authentication
- Customer profiles
- Worker profiles
- Job categories
- Job postings
- Job applications
- Notifications
- Messaging
- Identity verification

PHP communicates with the database using PDO.

---

## 9. Security Considerations

The project includes security-related measures such as:

- Password hashing.
- Prepared SQL statements.
- Role-based access control.
- Session-based authentication.
- Input validation.
- Output escaping.
- Protected configuration files.

**Important:** Never commit production database credentials, Gmail App Passwords, private documents, session data or application logs to a public repository.

---

## 10. Future Scope - Version 2.0

The planned 8th-semester extension will explore AI-based features.

### Planned AI Features

**1. AI Chat Assistant**

A multilingual assistant that helps users navigate the platform and understand its features.

**2. Smart Job Matching**

An intelligent matching system that recommends suitable jobs to workers based on their skills, experience and location.

**3. AI Profile Builder**

A voice-based profile creation assistant that converts spoken information into structured professional profiles.

**4. Natural Language Job Search**

Allow users to search for jobs and workers using everyday language.

### Planned Technology

- Python
- FastAPI
- AI and Natural Language Processing
- Machine Learning

These features are planned for a future version and are not presented as completed functionality.

---

## 11. Project Information

| Detail | Information |
|---|---|
| Project Name | KaamConnect |
| Project Type | Final-Year Major Project |
| Degree | B.Tech |
| Branch | Computer Science and Engineering |
| Backend | PHP |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript |
| Development Environment | XAMPP and VS Code |

---

## 12. Live Project

**Website:** https://kaamconnect.site.je

**GitHub Repository:** https://github.com/mkmkushwahaa/KaamConnect

---

## 13. Author

**Mukesh Kumar**

B.Tech Computer Science and Engineering

Katihar Engineering College

Bihar Engineering University

---

*KaamConnect aims to make local employment opportunities more accessible by connecting workers and customers through a simple digital platform.*