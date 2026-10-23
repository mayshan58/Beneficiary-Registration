# NGOs Beneficiary Registration System (South Sudan)

> **Final-Year Project:** Diploma in Information Communication Technology (ICT)  
> **Domain:** Humanitarian Assistance, Disaster Response & Beneficiary Information Management  
> **Target Context:** Humanitarian Relief Operations across the 10 States & Administrative Areas of South Sudan  

---

## 1. System Overview

The **NGOs Beneficiary Registration System** is a secure, full-stack web-based platform engineered to digitize the registration, verification, de-duplication, tracking, and aid distribution for humanitarian organizations operating in South Sudan. 

By replacing error-prone manual paper-based registries, the system enhances data integrity, prevents duplicate beneficiary registrations across clusters, prevents double-collection of relief supplies, and generates transparent reporting for donor accountability (WFP, UNHCR, UNICEF, RRC).

---

## 2. Technology Stack

- **Frontend:** HTML5, CSS3, JavaScript (ES6+), Bootstrap 5 (CSS & JS), Bootstrap Icons
- **Data Visualizations:** Chart.js (interactive state demographics & sector distributions)
- **Backend:** PHP 8.0+ (Modular, Object-Oriented PDO database abstraction)
- **Database:** MySQL / MariaDB (Prepared statements, relational integrity, foreign keys)
- **Web Server:** Apache via XAMPP on Windows
- **Exports & Printing:** Printable HTML slips/cards with `@media print` CSS optimization and CSV data export

---

## 3. Key Modules & Features

### A. Authentication & Role-Based Access Control (RBAC)
- Secure session management with session ID regeneration on login (`session_regenerate_id(true)`)
- Password hashing using `password_hash()` and verification using `password_verify()` (BCrypt)
- Cross-Site Request Forgery (CSRF) token validation across all mutations
- Fine-grained role enforcement (`admin`, `registration_officer`, `distribution_officer`)

### B. Interactive Dashboard
- Real-time MySQL statistics: Registered Beneficiaries, Active Programs, Registered NGOs, Aid Distributions
- Verification status breakdown (Verified, Pending, Requires Correction)
- Live interactive Chart.js charts:
  - Beneficiaries reach across South Sudan States & Counties
  - Gender ratio & vulnerability distribution
- Tables for recent registrations and recent relief distributions

### C. Beneficiary Registration & Management
- Unique Identification Number generation (`SSD-BEN-YYYY-XXXX`)
- Personal & Demographic profiles: Full name, gender, exact DOB / estimated age, phone, national ID / chief recommendation letter
- Location structure matching South Sudan: State, County (with dynamic client-side dropdown filtering), Payam, Boma, and Community/IDP Settlement
- Detailed household breakdown: Total household size, children under 5, elderly (60+), persons with disability (PWD), pregnant/lactating mothers
- Vulnerability categories: IDP, Returnee, Host Community Vulnerable, Female-Headed, Child-Headed, Elderly-Headed, PWD, Chronically Ill
- Printable official Beneficiary ID & Ration Slip with barcode/QR simulation, stamp box, and signatures

### D. Duplicate Registration Detection Engine
- Real-time client-side AJAX detection while typing (checks Name, DOB, Phone, and National ID)
- Multi-tier matching algorithm:
  - 100% Match: Identical official ID document number
  - 95% Match: Identical Full Name and Date of Birth
  - 85% Match: Matching contact phone number
  - 70% Match: Soundex / phonetic name similarity within the same County
- **Dedicated Duplicate Review Hub:** Side-by-side comparison of suspected profiles without automatic record deletion, in strict compliance with humanitarian protection standards

### E. Beneficiary Verification Workflow
- Multi-status queue: `Pending`, `Verified`, `Requires Correction`, `Rejected`
- Verification audit trails recording verifying officer ID, date/time, and method of validation (biometric, chief letter, physical assessment)

### F. Humanitarian NGO Partner Management
- Register and maintain humanitarian agencies (WFP, Save the Children, Oxfam, SSRC, IRC)
- South Sudan Relief & Rehabilitation Commission (RRC) registration licenses
- Link programs and staff users to specific NGOs
- One-click NGO account activation / deactivation

### G. Aid Program Management
- Create humanitarian programs across sectors: Food Assistance (GFD), Cash & Voucher Assistance (CVA), WASH, Health & Nutrition, Shelter & NFI, Livelihoods & Agriculture, Protection
- Program quotas, item descriptions, target goals, and geographic state/county targets
- Beneficiary enrollment module preventing double-enrollment

### H. Aid Distribution & Receipting
- Record aid handovers: Program, Beneficiary, Cycle/Round, Items, Quantity, Site, and Handover type
- Support for **Direct Beneficiary Collection** and **Authorized Proxy** (with proxy caregiver details)
- **Double-Dipping Prevention:** Prevents issuing duplicate relief assistance to the same beneficiary in the same distribution cycle
- Automated generation of official printable **Aid Distribution Vouchers / Receipts** (`DIST-YYYY-XXXX`)

### I. Reports & Donor Accountability
- **Beneficiary Demographics Report:** Disaggregated population and vulnerability rosters
- **Distribution Accountability Ledger:** Auditable relief transactions by NGO, date range, and site
- **Program Coverage & Performance Evaluation:** Target goals vs actual enrollment rates and fulfillment percentages
- 1-click **Export to CSV** and clean **Printable HTML** format

### J. System Administration & Audit Trail
- User management with role assignment and account suspension
- User self-service profile and password update
- Comprehensive **System Audit Logs** recording every authentication, creation, update, and verification with timestamps and client IP addresses
- Built-in **Diagnostic & Database Setup Tool** (`setup.php`)

---

## 4. User Roles & Permission Matrix

| Module / Action | Administrator | Registration Officer | Distribution Officer |
| :--- | :---: | :---: | :---: |
| Dashboard Access & Metrics | Yes | Yes | Yes |
| Beneficiary Registration & Edit | Yes | Yes | View Only |
| Beneficiary Verification Workflow | Yes | Yes | No |
| Duplicate Review Hub | Yes | Yes | No |
| Aid Program Creation & Edit | Yes | View Only | View Only |
| Program Beneficiary Enrollment | Yes | Yes | No |
| Record Aid Distribution | Yes | No | Yes |
| Print Receipts & ID Cards | Yes | Yes | Yes |
| Reports & CSV Data Exports | Yes | Yes | Yes (Distributions) |
| NGO Partner Management | Yes | No | No |
| System User Management & Roles | Yes | No | No |
| Security Audit Logs | Yes | No | No |

---

## 5. Local Setup & Installation (XAMPP on Windows)

### Step 1: Ensure Apache & MySQL are Running
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache** and **MySQL**.

### Step 2: Access the Application
The project is located in your workspace and accessible via localhost:
- Open your web browser (Chrome, Edge, Firefox).
- Navigate to: **`http://localhost/beneficiary/`**

### Step 3: Database Initialization (If Needed)
If you ever need to reset or re-populate the sample humanitarian data:
- Navigate to: **`http://localhost/beneficiary/setup.php`**
- Click **"Install / Reset Database & Seed Data"**.

---

## 6. Demo Project Accounts for Evaluation & Presentation

All accounts are pre-configured with the default password: **`Password@123`**

| Role | Username | Email | Description |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@ngoreg.ss` | Full system governance, NGOs, users, audit logs |
| **Registration Officer** | `regofficer` | `regofficer@wfp.org` | Beneficiary registration, de-duplication, verification, enrollment |
| **Distribution Officer** | `distofficer` | `distofficer@wfp.org` | Program beneficiary lists, relief distribution, receipts |

*(Note: The login page includes quick 1-click demo autofill pills to speed up evaluation during live demonstrations).*

---

## 7. Directory Structure

```
NGOs Beneficiary Registration System/
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css         # Local Bootstrap 5 styles
│   │   ├── bootstrap-icons.min.css   # Local Bootstrap Icons styles
│   │   ├── style.css                 # Custom humanitarian theme & print CSS
│   │   └── fonts/                    # Offline icon fonts
│   └── js/
│       ├── app.js                    # Dynamic South Sudan locations & AJAX duplicate checker
│       ├── bootstrap.bundle.min.js   # Bootstrap JavaScript
│       └── chart.umd.min.js          # Chart.js library
├── auth/
│   ├── login.php                     # Secure login interface with demo autofill
│   └── logout.php                    # Session invalidation & logout
├── beneficiaries/
│   ├── index.php                     # Beneficiaries directory with search & filters
│   ├── add.php                       # Registration form with live duplicate check
│   ├── edit.php                      # Beneficiary record editor
│   ├── view.php                      # Comprehensive beneficiary profile & history
│   ├── verify.php                    # Verification queue & workflow
│   ├── id_card.php                   # Printable Beneficiary ID & Ration Slip
│   ├── check_duplicate.php           # Real-time AJAX duplicate detection endpoint
│   └── duplicates.php                # Duplicate review & resolution hub
├── config/
│   ├── constants.php                 # South Sudan 10 states, counties, vulnerability lists
│   └── database.php                  # PDO database connection & error handling
├── database/
│   ├── schema.sql                    # SQL tables, indexes, constraints
│   └── seed.sql                      # Realistic South Sudan humanitarian seed dataset
├── distributions/
│   ├── index.php                     # Distribution logs & search
│   ├── record.php                    # Record distribution transaction
│   ├── receipt.php                   # Official printable distribution voucher
│   └── view.php                      # Distribution transaction view
├── includes/
│   ├── auth.php                      # RBAC, login guards, role permission checks
│   ├── header.php                    # Top navigation, organization badge, flash alerts
│   ├── footer.php                    # Footer & script loaders
│   └── functions.php                 # Audit logger, CSRF tokens, duplicate algorithm
├── logs/
│   └── index.php                     # Security & operational audit logs
├── ngos/
│   ├── index.php                     # NGO partners list & status toggling
│   ├── add.php                       # Register NGO
│   ├── edit.php                      # Edit NGO
│   └── view.php                      # NGO profile & associated programs
├── programs/
│   ├── index.php                     # Aid programs directory
│   ├── add.php                       # Create humanitarian program
│   ├── edit.php                      # Edit program
│   ├── view.php                      # Program overview, enrolled list & distribution history
│   └── enroll.php                    # Beneficiary enrollment into programs
├── reports/
│   ├── index.php                     # Reports hub & cluster charts
│   ├── beneficiaries.php             # Demographics report (HTML print & CSV)
│   ├── distributions.php             # Distribution accountability report (HTML print & CSV)
│   └── programs.php                  # Program performance & coverage report
├── users/
│   ├── index.php                     # User accounts & role management
│   ├── add.php                       # Provision new user account
│   ├── edit.php                      # Edit user & reset password
│   └── profile.php                   # Self-service user profile & password change
├── index.php                         # Main Dashboard with live database stats & Chart.js
├── setup.php                         # One-click diagnostic & database reset tool
└── README.md                         # Project documentation
```

---

## 8. Academic Integrity & Project Suitability

This system is built specifically as a capstone project for a **Diploma in Information Communication Technology (ICT)**:
1. **Separation of Concerns:** Clean modular structure with dedicated configuration, authentication, business logic, presentation, and data layers.
2. **Security Compliance:** Prepared PDO statements for 100% SQL injection prevention, input sanitization against XSS, CSRF token validation, and secure BCrypt password hashing.
3. **No Hardcoded Sample Data:** All dashboard statistics, metrics, charts, and rosters are populated directly from live MySQL queries.
4. **Resilient Offline Architecture:** Complete local bundles of Bootstrap 5, Bootstrap Icons, and Chart.js ensuring full functionality in examination environments without internet connectivity.

