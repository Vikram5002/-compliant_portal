# Sub-Staff Workflow - Complete Implementation Guide

## ✅ Navigation System Implemented

All pages now use a centralized navigation helper (`nav_helper.php`) that displays role-appropriate links with active page highlighting.

### Role-Based Navigation:

**Sub-Staff Users (sub_security, sub_staff, etc.)**
- Dashboard
- **My Tasks** → sub_staff_dashboard.php ⭐
- Notifications
- Logout

**Parent Staff (security, maintenance, warden, etc.)**
- Dashboard
- **My Tickets** → staff_tickets_with_sub.php ⭐
- Notifications
- Logout

**Rector/IT Team**
- Dashboard
- **Assigned Tickets** → staff_tickets.php ⭐
- Notifications
- Logout

**Admin/Super Visor**
- Dashboard
- Admin Dashboard
- Add Users
- Notifications
- Logout

**Students**
- Dashboard
- My Tickets → view_tickets.php
- Notifications
- Logout

---

## 📋 Complete Workflow: Admin → Parent Staff → Sub-Staff

### Step 1: Admin Assigns Ticket
**File:** `admin_dashboard.php`
```
1. Admin logs in (sees "Admin Dashboard" in nav)
2. Creates/selects Ticket #101
3. Assigns to Security Officer (parent staff)
4. Saves ticket with assigned_to = Security Officer ID
5. Email notification sent to Security Officer
```

### Step 2: Security Officer (Parent Staff) Logs In
**File:** `staff_tickets_with_sub.php`
```
Navigation shows: "My Tickets" (active highlight)

Page displays:
├── Pending Approvals (if any)
├── My Assignments
│   └── Ticket #101 from Admin
│       ├── Status: Received
│       └── Action: Reassign dropdown with sub-staff options
└── Sub-Staff Assignments
    └── Tickets previously assigned to sub-staff
```

### Step 3: Security Officer Reassigns to Sub-Security
**File:** `staff_tickets_with_sub.php`
```
Form action:
1. Select Sub-Security from dropdown
2. Click "Assign" button
3. Updates: Tickets.reassigned_to = Sub-Security ID
4. Creates: Notification for Sub-Security
5. Sends: Email to sub-staff with ticket details
6. Sub-Staff Assignment shows: "Sub-Security" with ticket status
```

### Step 4: Sub-Security Logs In
**File:** `sub_staff_dashboard.php`
```
Navigation shows: "My Tasks" (active highlight)

Sidebar shows: "Reports To: Security Officer"

Page displays:
├── Task List
│   └── Ticket #101
│       ├── Current Status: Received
│       ├── Status Update Dropdown
│       │   ├── In Progress
│       │   ├── Solution Proposed
│       │   └── Resolved
│       └── "Request Approval" button (appears only when Status=Resolved)
└── Updates to StatusHistory table
```

### Step 5: Sub-Security Updates Status
**File:** `sub_staff_dashboard.php`
```
Sub-Security workflow:
1. Selects "In Progress" → Click Update
   └── Saved to Tickets table, recorded in StatusHistory
   
2. Selects "Solution Proposed" → Click Update
   └── Saved to Tickets table, recorded in StatusHistory
   
3. Selects "Resolved" → Click Update
   ├── Sets: Tickets.status = "Resolved"
   └── "Request Approval" button now visible
   
4. Clicks "Request Approval"
   ├── Creates: SubStaffApprovals record (status='pending')
   ├── Sets: Tickets.sub_staff_status = 'pending_approval'
   ├── Sends: Email to parent staff with approval link
   └── Shows: "Pending Approval" status badge
```

### Step 6: Security Officer Reviews Approval
**File:** `staff_tickets_with_sub.php`
```
Security Officer sees:
├── Pending Approvals Section (with count)
│   └── Ticket #101 - Sub-Security submitted work
│       ├── Status indicator: Yellow badge "Pending Approval"
│       ├── Submitted by: Sub-Security SAP ID
│       ├── Submitted at: Timestamp
│       ├── Button: [✓ Approve]
│       └── Button: [✗ Reject]

If "Reject" clicked:
├── Shows revision notes textarea
├── Updates: SubStaffApprovals.status = 'rejected'
├── Sets: Tickets.sub_staff_status = NULL (allows rework)
└── Sends: Email to Sub-Security with revision notes
```

### Step 7: Sub-Security Notified
**File:** Email & Notifications
```
If Approved:
├── Email: "Work Approved: Ticket #101"
├── Message: "Congratulations! Your work has been approved"
├── Next: Ticket continues to admin workflow
└── Status: "approved_by_parent"

If Rejected:
├── Email: "Revision Needed: Ticket #101"
├── Shows: Revision notes from Security Officer
├── Action: Sub-Security must make changes
├── Next: Re-update status and request approval again
└── Status: NULL (allows rework)
```

---

## 📁 Files Updated with Navigation

| File | Changes |
|------|---------|
| `nav_helper.php` | ✨ NEW - Centralized navigation helper |
| `index.php` | ✅ Uses generateNavigation() |
| `admin_dashboard.php` | ✅ Uses generateNavigation() |
| `staff_tickets_with_sub.php` | ✅ Uses generateNavigation() |
| `sub_staff_dashboard.php` | ✅ Uses generateNavigation() |
| `bulk_import.php` | ✅ Uses generateNavigation() |

---

## 🔄 Database Tables Used

### Users Table
```
user_id
sap_id
email
role (staff, sub_staff, security, sub_security, etc.)
password
parent_staff_id (links to parent staff)
is_sub_staff (0=regular, 1=sub-staff)
```

### Tickets Table
```
ticket_id
title
status (Received, In Progress, Solution Proposed, Resolved, Closed)
assigned_to (original staff member)
reassigned_to (sub-staff member, if assigned)
sub_staff_status (NULL, pending_approval, approved_by_parent, rejected)
created_at
```

### SubStaffApprovals Table
```
approval_id
ticket_id
sub_staff_id
parent_staff_id
status (pending, approved, rejected)
parent_notes (revision notes if rejected)
submitted_at
approved_at
```

### StatusHistory Table
```
history_id
ticket_id
status
timestamp
```

### Notifications Table
```
notification_id
user_id
message
is_read
created_at
```

---

## ✨ Key Features

### 1. Role-Based Navigation
- ✅ Different links for different roles
- ✅ Active page highlighting
- ✅ Automatic badge count for notifications

### 2. Approval Workflow
- ✅ Pending approvals counter in "Pending Approvals" section
- ✅ Yellow status badge for pending items
- ✅ Approve/Reject buttons with revision notes

### 3. Email Notifications
- ✅ Task assignment notification
- ✅ Approval request notification
- ✅ Approval confirmation/rejection notification with notes
- ✅ All emails include login links to relevant pages

### 4. Status Tracking
- ✅ StatusHistory records every status change
- ✅ Timestamps for all transitions
- ✅ Color-coded status badges

### 5. Security
- ✅ CSRF token validation on all forms
- ✅ Role-based access control (RBAC)
- ✅ Parent-child relationship verification
- ✅ Prepared statements for SQL injection prevention

---

## 🧪 Testing Checklist

- [ ] Create parent staff (Security Officer)
- [ ] Create sub-staff (Sub-Security) and assign to parent
- [ ] Create ticket in bulk import or index.php
- [ ] Admin assigns ticket to Security Officer
- [ ] Security Officer sees "My Tickets" link (not "Assigned Tickets")
- [ ] Security Officer reassigns to Sub-Security
- [ ] Sub-Security receives email and sees "My Tasks" link
- [ ] Sub-Security updates status through workflow
- [ ] Sub-Security requests approval when status=Resolved
- [ ] Security Officer sees pending approval with yellow badge
- [ ] Security Officer clicks approve/reject
- [ ] Sub-Security receives approval/rejection email
- [ ] StatusHistory shows all transitions
- [ ] Navigation active links highlight correctly
- [ ] All email notifications sent successfully

---

## 🚀 Deployment Checklist

- [x] nav_helper.php created with generateNavigation() function
- [x] index.php updated with nav_helper.php include and generateNavigation()
- [x] admin_dashboard.php updated with nav_helper.php
- [x] staff_tickets_with_sub.php updated with nav_helper.php
- [x] sub_staff_dashboard.php updated with nav_helper.php
- [x] bulk_import.php updated with nav_helper.php
- [ ] Test all roles in browser
- [ ] Verify active links highlight
- [ ] Test complete workflow from admin to sub-staff
- [ ] Check all email notifications
- [ ] Verify StatusHistory records

---

## UI Consistency

All pages maintain the same visual style:
- **Red gradient sidebar**: Admin/parent staff views
- **Blue gradient sidebar**: Sub-staff views
- **Consistent card layouts** for forms
- **Matching button styles** (primary, secondary, danger)
- **Same notification badge styling**
- **Responsive design** across all pages
- **Font Awesome icons** for all menu items

---

## Support

For issues with the workflow:
1. Check `nav_helper.php` is included in all pages
2. Verify `parent_staff_id` is set correctly in Users table
3. Check `reassigned_to` field in Tickets table
4. Verify `SubStaffApprovals` table has correct records
5. Review email sending configuration in `send_email.php`
