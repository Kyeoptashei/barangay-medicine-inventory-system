Barangay Medical Supplies & Resident Information Management System

A web-based inventory and healthcare management platform designed to streamline the tracking, monitoring, and distribution of medical supplies within a barangay. The system bridges the workflow between the Barangay Health Worker (BHW) and the Nurse, ensuring accurate inventory control using FIFO logic and precise, age-appropriate medicine distribution to residents.

Core Features

1. Inventory Management Module (BHW Side)
- Real-Time Stock Tracking: Monitors exact quantities of medicines and medical supplies.
- Live Stock Deductions: Automatically and instantly deducts quantities from the BHW inventory the exact moment the Nurse releases medicine during a consultation.
- Dynamic Restocking & FIFO Logic:
   - Allows restocking when a medicine's quantity reaches zero.
   - Supports restocking even while the current batch is still active. To strictly follow the FIFO (First In, First Out) protocol, new batches are held as Pending and cannot be released until the current stock is either fully consumed or expired.
- Out-of-Stock Visibility: Zero-quantity items are immediately logged and visible on the dashboard without distracting indicators.

2. Resident Management Module
- Automated Age & Category Classification: Automatically computes a resident's age based on their birthdate and classifies them into specific groups: Baby, Toddler, Child, Teens, Adults, Senior, alongside special categories like PWD or Pregnant.
- Smart Household Mapping:
   - New Families: Automatically generates a unique Household Number upon registration.
   - Existing Families: Easily add a new member to an existing family by simply entering their current Household Number.
   - Purok Assignment: Organizes residents systematically by location (Purok 1, Purok 2, Purok 3A, or Purok 3B).

3. Consultation & Smart Distribution Module (Nurse Side)
- Data Synchronization: Resident profiles and household records seamlessly sync from the BHW system to the Nurse system for real-time lookup during consultations.
- Automated Medicine Recommendation:
   - Automatically matches and selects the appropriate brand of medicine based on the resident's symptoms and precise age group (e.g., selecting Tempra Syrup for a toddler with a fever, vs. Biogesic for an adult).
   - Offers manual override flexibility if the default medicine is out of stock or if the resident requests an alternative (e.g., switching to Paracetamol).
- Instant Dispensing & Auto-Deduct: Once the Nurse confirms and releases the medicine, the system triggers a real-time database update that instantly reflects as a stock reduction on the BHW's side.
- Safety Filtering: The Nurse's interface strictly displays available medicines only. Expired and out-of-stock items are automatically hidden from the distribution panel to ensure patient safety, while remaining visible to the BHW for inventory maintenance.

4. Distribution & Request Logs
- Secure Audit Trails: Logs every dispensed item per household for full accountability and seamless administrative reporting.

Tech Stack

- Frontend: HTML5, CSS3, JavaScript
- Backend: PHP
- Database: MySQL
- Local Server Environment: XAMPP

Installation & Setup

1. Clone the Repository:
git clone https://github.com/Kyeoptashei/barangay-medicine-inventory-system.git
