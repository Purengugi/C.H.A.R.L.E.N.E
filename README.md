# C.H.A.R.L.E.N.E

🧬 C.H.A.R.L.E.N.E
Clinical Hub for Accurate Results, Lab Efficiency & Notification Enhancement

C.H.A.R.L.E.N.E is a secure and intuitive web-based system designed to digitize and optimize hospital laboratory workflows. It enhances communication between doctors, lab technicians, and administrators while ensuring accurate and timely delivery of lab results.

📌 Features
🩺 Patient Management
Register new patients and view their medical history.

🧾 Test Requests
Doctors can submit test requests directly through the system.

🧪 Sample Tracking
Track each lab sample from collection to result delivery.

📊 Results Entry
Lab staff can enter, validate, and manage test results efficiently.

🔐 Role-Based Dashboards
Secure access and workflows tailored for doctors, lab staff, and admins.

📈 Reporting & Audits
Generate analytical reports and maintain an audit trail of all activities.

🛠️ Technologies Used
Backend: PHP 7.4+

Frontend: HTML5, CSS3, JavaScript

Framework: Bootstrap 4 (responsive design)

Database: MySQL 8.0+

Security: Session-based authentication, prepared statements, input sanitization

🧬 Database Schema
users — System users (doctors, lab techs, admins)

patients — Patient demographics and records

test_requests — Doctor-generated test forms

test_results — Lab-entered test outcomes

test_catalog — Master list of supported tests

samples — Sample tracking and status

audit_log — All logged actions for traceability

Import the schema from database/lims_database.sql

🎨 UI Theme
Primary: Black #000000

Highlight: Gold #FFD700

Accent: White #FFFFFF

Background: Dark Gray #1a1a1a

Typography: Clean and professional white/gold on dark UI

⚙️ Setup Instructions
Clone the repository:

bash
Copy
Edit
git clone https://github.com/YourUsername/charlene.git
Import the database:

Use lims_database.sql located in the database/ folder via phpMyAdmin or MySQL CLI.

Update configuration:

Open config/database.php and set your DB credentials.

Start your local server:

Recommended: XAMPP / WAMP

Launch the application:

Navigate to http://localhost/charlene/ in your browser.

🚀 Planned Enhancements
📱 Mobile-friendly design

💬 Real-time notifications for test results

📤 PDF export and printing of results

📡 Integration with hospital API for centralized data

📊 Interactive dashboard with charts and KPIs

🤝 Contributions
We welcome all contributions!
Feel free to fork the repository, create a feature branch, and open a pull reques
