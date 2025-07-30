# 🧬 Clinical Hub for Accurate Results, Lab Efficiency & Notification Enhancement (C.H.A.R.L.E.N.E)

C.H.A.R.L.E.N.E is a secure and intuitive web-based system designed to digitize and optimize hospital laboratory workflows. It enhances communication between doctors, lab technicians, and administrators while ensuring accurate and timely delivery of lab results.

# 📌 Features

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

# 🛠️ Technologies Used
1. Backend: PHP 7.4+

2. Frontend: HTML5, CSS3, JavaScript

3. Framework: Bootstrap 4 (responsive design)

4. Database: MySQL 8.0+

5. Security: Session-based authentication, prepared statements, input sanitization

# 🧬 Database Schema

1. users — System users (doctors, lab techs, admins)

2. patients — Patient demographics and records

3. test_requests — Doctor-generated test forms

4. test_results — Lab-entered test outcomes

5. test_catalog — Master list of supported tests

6. samples — Sample tracking and status

7. audit_log — All logged actions for traceability

- Import the schema from database/lims_database.sql

# 🎨 UI Theme
1. Primary: Black #000000

2. Highlight: Gold #FFD700

3. Accent: White #FFFFFF

4. Background: Dark Gray #1a1a1a

5. Typography: Clean and professional white/gold on dark UI

# ⚙️ Setup Instructions
1. Clone the repository:

   git clone https://github.com/Purengugi/C.H.A.R.L.E.N.E.git

2. Import the database:

   Use lims_database.sql located in the database/ folder via phpMyAdmin or MySQL CLI.

3. Update configuration:

   Open config/database.php and set your DB credentials.

4. Start your local server:

   Recommended: XAMPP / WAMP

5. Launch the application:

   Navigate to http://localhost/charlene/ in your browser.

# 🚀 Planned Enhancements

📱 Mobile-friendly design

💬 Real-time notifications for test results

📤 PDF export and printing of results

📡 Integration with hospital API for centralized data

📊 Interactive dashboard with charts and KPIs

# 🤝 Contributions
We welcome all contributions!
Feel free to fork the repository, create a feature branch, and open a pull request
