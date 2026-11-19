# KaamConnect - Local Worker Hiring and Employment Marketplace

> **B.Tech Computer Science and Engineering — Final-Year Major Project**  
> Developed using PHP 8+ (PDO), MySQL/MariaDB, Vanilla JavaScript, HTML5, CSS3, Bootstrap 5.3, and Font Awesome.

---

## 1. Project Overview

**KaamConnect** is a localized employment and worker hiring marketplace that directly bridges skilled and unorganized blue-collar artisans (electricians, plumbers, carpenters, painters, masons, cleaners, mechanics, etc.) with residential and commercial customers within their neighbourhood.

### Key Objectives
- Eliminate middleman commission exploitation by providing direct customer-worker connections.
- Empower local workers with professional, shareable digital portfolios and verified credential badges.
- Enable customers to post localized tasks with budgets, compare bids, hire verified talent, and rate work transparently.
- Real-time in-app private messaging with AJAX polling for direct negotiations.
- Full bilingual English and Hindi localization with instant language switching.

---

## 2. Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+ Vanilla), Bootstrap 5.3.3, Font Awesome 6.5.2
- **Backend**: PHP 8.0+ with PHP Data Objects (PDO), Strict Typing
- **Database**: MySQL 8.0 / MariaDB 10.3+ (Engine: InnoDB, Collation: `utf8mb4_unicode_ci`)
- **Web Server**: Apache (XAMPP for Windows 10/11)
- **Architecture**: Modular Multi-Tier MVC-patterned Monolith with RESTful AJAX Endpoints

### Strict Stack Constraints Adhered To
- No Node.js backend.
- No React or heavy frontend framework.
- No Laravel or third-party framework overhead.
- Native PDO with 100% prepared statement parameter binding.

---

## 3. UI/UX Design System

The application features a clean, high-contrast, modern blue-and-white visual identity:
- **Primary Blue**: `#1E40AF`
- **Secondary Blue**: `#2563EB`
- **Accent Orange**: `#F59E0B`
- **Background**: `#F8FAFC`
- **Surface White**: `#FFFFFF`
- **Primary Text**: `#1E293B`
- **Muted Text**: `#64748B`

---

## 4. User Roles & Capabilities

### A. Worker
1. Register, log in, manage professional profile and trade categories.
2. Upload profile photo and portfolio pictures of previous client projects.
3. Set hourly or daily wage expectations, availability status, and service locality.
4. Search customer jobs with live category, city, locality, and budget filters.
5. Submit job applications with custom wage proposals and cover notes.
6. Track application lifecycle (Pending, Accepted, Rejected, Withdrawn).
7. One-to-one private chat with customers.
8. Receive genuine star ratings (1–5) and customer reviews upon job completion.

### B. Customer
1. Register, log in, manage customer profile and home address.
2. Post jobs specifying category, required skills, budget, locality, and preferred date.
3. Search and filter verified workers by trade, rating, experience, and locality.
4. Review received bids, inspect worker portfolios, accept/reject proposals.
5. Hire worker (automatically closes new bids and transitions job to `assigned`).
6. Private in-app messaging with hired/bidding workers.
7. Mark jobs completed and submit verified ratings and written feedback.

### C. Administrator
1. Secure role-protected control center (`/admin/`).
2. Live platform statistics: total users, active workers, customer accounts, and jobs.
3. Review worker verification requests and toggle Aadhaar/skill verification status.
4. User management: suspend or activate accounts.
5. Category management: add, edit, or toggle trade specialties.
6. Moderation: inspect user complaints, dispute reports, and job listings.

---

## 5. Local Setup & Installation (XAMPP on Windows)

### Prerequisites
- Windows 10 or 11
- [XAMPP](https://www.apachefriends.org/) (with Apache and MySQL / PHP 8+)

### Step-by-Step Setup

1. **Place the Project in `htdocs`**  
   Clone or copy the `KaamConnect` project folder to your XAMPP web root:
   ```text
   C:\xampp\htdocs\kaamconnect
   ```
   *(Or if running directly as the root virtual host, point DocumentRoot to this folder).*

2. **Start Apache and MySQL**  
   Open the **XAMPP Control Panel** and click **Start** for both **Apache** and **MySQL**.

3. **Install the Database**  
   Choose either of the two easy options:
   
   - **Option A (One-Click Web Installer):**  
     Open your browser and navigate to:  
     `http://localhost/kaamconnect/install.php`  
     Click **Install Database & Seed Data**.
   
   - **Option B (phpMyAdmin):**  
     Open `http://localhost/phpmyadmin/`, create database `kaamconnect_db`, and import `database/kaamconnect.sql`.

4. **Access the Application**  
   Navigate to:  
   `http://localhost/kaamconnect/index.php`

---

## 6. Pre-Seeded Demonstration Accounts

For testing, presentation, and examiner demonstrations, pre-configured accounts are available:

| Role | Email / Login | Phone | Default Password |
|---|---|---|---|
| **Super Admin** | `admin@kaamconnect.com` | `9876543210` | `Admin@123` |
| **Worker (Electrician)** | `ramesh.worker@kaamconnect.com` | `9876500001` | `Password@123` |
| **Worker (Plumber)** | `aslam.worker@kaamconnect.com` | `9876500002` | `Password@123` |
| **Customer** | `sunita.customer@kaamconnect.com` | `9876500003` | `Password@123` |
| **Customer** | `vikram.customer@kaamconnect.com` | `9876500004` | `Password@123` |

---

## 7. Directory Structure

```text
KaamConnect/
├── index.php                      # Public Marketplace Homepage
├── login.php                      # Unified Secure Login Page
├── register.php                   # Dual-Role Registration Page
├── logout.php                     # Session Termination & Redirection
├── workers.php                    # Public Worker Directory & Search
├── jobs.php                       # Public Job Board & Search
├── about.php                      # About KaamConnect Mission
├── contact.php                    # Contact Support Form
├── install.php                    # Automated Database Diagnostic & Installer
│
├── config/
│   └── database.php               # PDO Database Connection & Global Query Helpers
│
├── assets/
│   ├── css/
│   │   └── style.css              # Custom Stylesheet & Design Tokens
│   ├── js/
│   │   └── main.js                # Client-Side Helpers & AJAX Polling
│   ├── images/                    # Platform Logos & SVGs
│   └── uploads/                   # User Avatars & Portfolio Photos
│
├── includes/
│   ├── header.php                 # Global HTML5 Header & Flash Alerts
│   ├── navbar.php                 # Navigation Bar & Dynamic Menus
│   ├── footer.php                 # Responsive Footer & Modals
│   ├── auth.php                   # Role-Based Route Guards (RBAC)
│   ├── functions.php              # Global Security, XSS, CSRF, Translations
│   └── languages/
│       ├── en.php                 # English Language Dictionary
│       └── hi.php                 # Hindi Language Dictionary
│
├── worker/
│   ├── dashboard.php              # Worker Overview & Quick Stats
│   ├── profile.php                # Worker Profile & Skills Editor
│   ├── portfolio.php              # Work Photo Showcase & Upload
│   ├── jobs.php                   # Browse & Filter Available Jobs
│   ├── job-details.php            # Detailed Job Description & Bid Submission
│   ├── my-applications.php        # Track Status & Withdraw Bids
│   ├── messages.php               # Live Chat with Customers
│   └── notifications.php          # Alert Inbox
│
├── customer/
│   ├── dashboard.php              # Customer Overview & Quick Stats
│   ├── profile.php                # Customer Profile & Address Editor
│   ├── post-job.php               # Publish New Job Requirement
│   ├── manage-jobs.php            # Manage Listings & Inspect Applicants
│   ├── job-applications.php       # Review Proposals & Hire Worker
│   ├── workers.php                # Search & Filter Local Workers
│   ├── worker-profile.php         # Worker Public Portfolio & Rating Details
│   ├── messages.php               # Live Chat with Workers
│   ├── review.php                 # Submit Post-Job Star Rating & Feedback
│   └── notifications.php          # Alert Inbox
│
├── admin/
│   ├── index.php                  # Superadmin Dashboard & KPI Metrics
│   ├── workers.php                # Worker Verification & Account Actions
│   ├── customers.php              # Customer Account Directory
│   ├── jobs.php                   # Platform Job Postings Moderation
│   ├── categories.php             # Trade Category CRUD
│   ├── reports.php                # Dispute & Complaint Management
│   └── analytics.php              # Growth, Completion & Engagement Analytics
│
├── api/
│   ├── messages.php               # Asynchronous AJAX Message Polling & Send
│   └── notifications.php          # Unread Notification Polling & Read Status
│
├── database/
│   └── kaamconnect.sql            # Full Relational Schema & Seed Data (14 Tables)
│
└── docs/
    ├── architecture.md            # Technical System Architecture & Mermaid Diagrams
    └── database_er.md             # Complete Entity-Relationship Model & Data Dictionary
```

---

## 8. Version 2.0 Roadmap (8th Semester AI Extension)

In accordance with project scope guidelines, Version 1.0 focuses on the rock-solid core transactional marketplace. Version 2.0 will connect an external Python FastAPI AI service:
- **AI Chat Assistant**: Multilingual voice/text conversational bot guiding unlettered artisans.
- **Smart Job Matcher**: Automated semantic matching between worker profile vectors and newly posted customer jobs.
- **AI Profile Builder**: Voice-to-text profile generator that transforms informal spoken Hindi/English into structured resumes.
- **Natural Language Search**: "Find me a plumber near Sector 15 who can fix a geyser tomorrow."
