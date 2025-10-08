# 🧩 Customer Relationship Management System (CRM)  
**Modern Web-Based CRM with Admin & Staff Dashboards, Real-Time Chat, Client Assignment, and AI-Powered Analytics**

---

## 📘 Overview

This project is a **comprehensive Customer Relationship Management (CRM)** system developed using **PHP, MySQL, HTML, CSS, JavaScript**, and integrated with **AI-based analytics**.  
It provides a complete digital platform for managing customer data, tracking interactions, automating business workflows, and gaining intelligent insights through data analysis.

---

## 🚀 Features Overview

### 🧑‍💼 Admin Dashboard
The **Admin Panel** provides full control and visibility over all system operations:
- Overview of total **customers, staff, unread messages, and services**
- Visual charts showing **customer status** and **no-response distributions**
- **Recent customer list** with quick access
- **Real-time notifications system**
- **Staff management** (edit, deactivate, assign clients)
- Integration with **AI Analytics Page**

📊 **File:** `admin_dashboard.php`

---

### ⚙️ Status Management
The **admin_statuses.php** page allows the admin to manage customer and no-response statuses:
- Add / Edit / Delete customer statuses  
- Assign **colors** for visual identification  
- Reorder statuses using **drag & drop**
- Maintain clean data for AI analytics  
- Filter and search statuses  
- Visual confirmation messages after each action  

📁 **Database Tables:**  
`customer_statuses`, `no_response_statuses`

---

### 🤖 AI Analytics Page
The **ai_analysis.php** page acts as the analytical brain of the CRM:
- Users can type **free-text questions** or choose predefined analyses  
- Automatically generates:
  - **Customer Status Report**
  - **Employee Performance Report**
  - **No-Response Analysis**
- Displays summarized data (customer count, staff count, distributions)
- Shows **sample records** from main database tables
- Integrates with AI (NLP & Predictive Models)

🧠 **Main Purpose:** Transform raw CRM data into actionable insights.

---

### 👥 Client Assignment System
The **assign.php** page enables manual and automatic distribution of customers to staff:
- **Manual Assignment:** Admin selects customers and assigns them to specific staff.  
- **Automatic Assignment:** Based on:
  - Percentage distribution  
  - Fixed number per staff  
  - Equal distribution
- Displays two separate lists:
  - **Unassigned customers**
  - **Assigned customers**
- Sends automatic **notifications** to assigned staff.

📡 **Database Integration:**  
`customers`, `staff`, `notifications`

---

### 💬 Real-Time Chat System
The system includes two-way chat between admins and staff.

**Key Features:**
- **Dynamic user list** (shows online users)
- **Unread message indicator**
- **Instant message sending/receiving**
- Messages stored in the `messages` table
- Read/unread status tracking
- Integrated notifications system

📂 **Files:**
- `admin_chat.php` — Admin chat interface  
- `staff_chat.php` — Staff chat interface  

---

### 👨‍🔧 Staff Dashboard
Each employee (staff) has an independent dashboard:
- Displays **assigned customers**
- Allows adding **notes**, uploading **files**, and viewing customer data
- Real-time **notifications** about new clients or messages
- Integration with AI analytics data
- Clean, responsive UI using Tailwind CSS

📄 **Files:**
- `staff_dashboard.php`
- `staff_customers.php`
- `staff_chat.php`

---

### 👤 Client Page
The **client_profile.php** page displays all information related to each client:
- Personal details, contact information, address, status, and assigned staff  
- Linked notes, uploaded files, and communication history  
- Designed to allow both admins and staff to manage client data efficiently

---

## 🗄️ Database Structure

The database includes **11 interconnected tables**, designed in **Third Normal Form (3NF)** for performance and data consistency:

| Table | Description |
|--------|--------------|
| `admin` | Stores admin login credentials |
| `staff` | Stores staff information and access control |
| `customers` | Central table for all customer information |
| `activity_log` | Logs all staff and admin actions |
| `client_files` | Stores uploaded client documents |
| `client_notes` | Saves notes related to each client |
| `customer_statuses` | Manages customer status types |
| `no_response_statuses` | Tracks non-responsive customer categories |
| `messages` | Stores chat messages |
| `notifications` | Handles real-time system alerts |
| `customer_status_log` | Tracks all changes in customer status history |

💡 **Data Security:**  
All passwords are stored using **bcrypt encryption**, and SQL queries use **Prepared Statements** to prevent SQL Injection.

---

## 💡 Artificial Intelligence Integration

The AI integration enhances the CRM’s analytical capabilities:
- **Customer Segmentation:** Group customers based on city, status, or response behavior.  
- **Performance Analytics:** Evaluate staff efficiency using activity logs.  
- **Predictive Analytics:** Identify potential customer churn or growth opportunities.  
- **Natural Language Processing (NLP):** Analyze customer notes or files for keyword insights.

---

## 🧰 Technologies Used

| Layer | Technologies |
|--------|---------------|
| Frontend | HTML5, CSS3, JavaScript, Tailwind CSS |
| Backend | PHP (no framework) |
| Database | MySQL |
| Realtime Communication | WebSocket / AJAX (for chat & notifications) |
| AI Integration | Custom NLP & Analytical logic |
| Development Environment | XAMPP / Localhost |

---

## 🔐 Security & Best Practices
- Secure **login system** for Admin & Staff  
- Passwords hashed with **bcrypt**  
- SQL Injection protection via **Prepared Statements**  
- Session-based access control  
- Limited access based on **user roles**

---

## 📱 User Interface
The design follows a **modern responsive layout**, optimized for:
- Desktop & mobile devices  
- Clean visual hierarchy  
- Intuitive navigation sidebar  
- Interactive elements with hover effects  
- Real-time updates (messages, notifications)

---

## 📊 System Workflow Summary

