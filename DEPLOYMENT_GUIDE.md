# Complete Workflow Testing & Deployment Guide

## Pre-Deployment Checklist

### 1. Database Setup
- [ ] Import `cprtl.sql` into database
- [ ] Verify all tables created successfully
- [ ] Verify parent_staff_id and is_sub_staff fields exist in users table
- [ ] Verify sub_staff_status and reassigned_to fields exist in tickets table
- [ ] Verify SubStaffApprovals table exists with all columns

### 2. File Upload
- [ ] Upload all PHP files to server
- [ ] Upload NMIMS Logo.jpg
- [ ] Verify PHPMailer directory uploaded completely
- [ ] Verify permissions are correct (755 for dirs, 644 for files)

### 3. Email Configuration
- [ ] Open `send_email.php`
- [ ] Verify Office365 SMTP credentials are correct
- [ ] Test email sending with test account
- [ ] Verify email headers and formatting

### 4. Database Connection
- [ ] Open `db_connect.php`
- [ ] Verify database host, user, password correct
- [ ] Verify database name is "cprtl"
- [ ] Test connection with test login

---

## Complete Workflow Test Steps

### Phase 1: User Setup
1. **Create Test Users**
   - Create Student (role: student)
   - Create Admin (role: admin)
   - Create Parent Staff - Security (role: security)
   - Create Sub-Staff - Security (role: sub_security, parent_staff_id: security_user_id)

   **Verification:**
   - [ ] All users created with correct roles
   - [ ] Sub-staff has parent_staff_id assigned
   - [ ] is_sub_staff = 1 for sub-staff
   - [ ] Passwords hashed correctly

### Phase 2: Ticket Creation
1. **Student Creates Ticket**
   - Login as student
   - Create ticket with title, description, location
   - No assignment
   
   **Verification:**
   - [ ] Ticket created in database
   - [ ] Status = "Received"
   - [ ] StatusHistory record created for "Received"
   - [ ] Notifications sent to admins

2. **Admin Assigns to Parent Staff**
   - Login as admin
   - Assign ticket to security staff member
   
   **Verification:**
   - [ ] Ticket.assigned_to = security staff id
   - [ ] Parent staff receives email notification

### Phase 3: Parent Staff Reassignment
1. **Parent Staff Reassigns to Sub-Staff**
   - Login as security staff
   - Go to "My Assignments" section
   - Select sub-staff from dropdown
   - Click "Assign"
   
   **Verification:**
   - [ ] Ticket.reassigned_to = sub_staff id
   - [ ] Ticket appears in sub_staff dashboard
   - [ ] Sub-staff receives email notification

### Phase 4: Sub-Staff Work Process
1. **Sub-Staff Updates Status**
   - Login as sub-staff
   - See ticket in "My Assigned Tasks"
   - Change status to "In Progress"
   - Click "Update"
   
   **Verification:**
   - [ ] Status changed to "In Progress"
   - [ ] StatusHistory record created
   - [ ] No "Resolved" option in dropdown
   - [ ] Parent staff "Reports To" shows correctly

2. **Sub-Staff Changes to Solution Proposed**
   - Change status to "Solution Proposed"
   - Click "Update"
   
   **Verification:**
   - [ ] Status changed to "Solution Proposed"
   - [ ] "Request Approval for Resolution" button appears
   - [ ] Update dropdown disappears
   - [ ] StatusHistory updated

### Phase 5: Approval Request
1. **Sub-Staff Requests Approval**
   - Click "Request Approval for Resolution" button
   
   **Verification:**
   - [ ] SubStaffApprovals record created with status = 'pending'
   - [ ] sub_staff_status = 'pending_approval'
   - [ ] Parent staff receives email: "Work Approval Request"
   - [ ] Button changes to show "Pending Approval" status

2. **Parent Staff Sees Pending Approval**
   - Login as parent staff (security)
   - Go to "My Tickets & Assignments"
   - See "Pending Approvals" section
   
   **Verification:**
   - [ ] Ticket appears in "Pending Approvals"
   - [ ] Shows ticket ID, title, submitted by, timestamp
   - [ ] Has "Approve" and "Reject" buttons

### Phase 6: Approval or Rejection

#### Test Approval:
1. **Approve Request**
   - Parent staff clicks "Approve" button
   
   **Verification:**
   - [ ] SubStaffApprovals.status = 'approved'
   - [ ] approved_at timestamp set
   - [ ] Ticket.status = 'Resolved' ✅ **NEW**
   - [ ] sub_staff_status = 'approved_by_parent'
   - [ ] StatusHistory record created for "Resolved"
   - [ ] Sub-staff receives email: "Work Approved: Ticket #X"
   - [ ] Ticket no longer in pending approvals
   - [ ] Ticket appears as "Resolved" in sub-staff dashboard

#### Test Rejection:
1. **Reject Request**
   - Parent staff clicks "Reject" button
   - Textarea appears for revision notes
   - Enter notes: "Please check calculations"
   - Click "Confirm Rejection"
   
   **Verification:**
   - [ ] Textarea appears when clicking Reject
   - [ ] Cannot submit without notes (validation works)
   - [ ] SubStaffApprovals.status = 'rejected'
   - [ ] parent_notes saved with notes
   - [ ] Ticket.sub_staff_status reverted to NULL
   - [ ] Ticket appears back in sub-staff dashboard with "Solution Proposed" status
   - [ ] Sub-staff receives email with rejection notes
   - [ ] Ticket no longer in pending approvals

### Phase 7: View Details Navigation
1. **Test View Details Links**
   - In sub_staff_dashboard: Click "📋 View Details"
   
   **Verification:**
   - [ ] Navigates to ticket_details.php
   - [ ] Correct ticket displayed
   - [ ] Link color is red (#c41e3a)

2. **Test from Parent Dashboard**
   - In staff_tickets_with_sub: Click "📋 View Details" in Direct Assignments
   - Click "📋 View Details" in Sub-Staff Assignments
   
   **Verification:**
   - [ ] Both navigate to correct ticket_details
   - [ ] Link color is red (#c41e3a)

### Phase 8: Navigation & UI
1. **Test Navigation Active State**
   - Sub-staff: Should show "My Tasks" highlighted
   - Parent staff: Should show "My Tickets" highlighted
   - Admin: Should show navigation correctly
   
   **Verification:**
   - [ ] Active page has yellow left border
   - [ ] Correct role name displayed
   - [ ] Parent staff name shown in sidebar

2. **Test UI Consistency**
   - All staff pages should have red gradient sidebar
   - All buttons should be red gradient
   - Tables should be styled consistently
   
   **Verification:**
   - [ ] sub_staff_dashboard sidebar: red gradient
   - [ ] staff_tickets_with_sub sidebar: red gradient
   - [ ] All buttons: red gradient
   - [ ] All links: red color

---

## Database State Verification

### After Approval Flow:
```sql
-- Verify Ticket Status
SELECT ticket_id, status, sub_staff_status, reassigned_to 
FROM Tickets WHERE ticket_id = [TEST_TICKET_ID];
-- Expected: Resolved, approved_by_parent, [sub_staff_id]

-- Verify StatusHistory
SELECT * FROM StatusHistory WHERE ticket_id = [TEST_TICKET_ID] 
ORDER BY timestamp DESC LIMIT 5;
-- Expected: Received, In Progress, Solution Proposed, Resolved

-- Verify Approval Record
SELECT * FROM SubStaffApprovals WHERE ticket_id = [TEST_TICKET_ID];
-- Expected: status = 'approved', approved_at filled, sub_staff_status matches
```

### After Rejection Flow:
```sql
-- Verify Ticket Status
SELECT ticket_id, status, sub_staff_status 
FROM Tickets WHERE ticket_id = [TEST_TICKET_ID];
-- Expected: Solution Proposed, NULL (reverted)

-- Verify Approval Record
SELECT * FROM SubStaffApprovals WHERE ticket_id = [TEST_TICKET_ID];
-- Expected: status = 'rejected', parent_notes filled, approved_at NULL
```

---

## Email Template Verification

### Email 1: Assignment Notification
- **Recipient:** Sub-staff
- **Subject:** "New Task Assignment: Ticket #[ID]"
- **Contains:** Ticket ID, supervisor name, login instructions

### Email 2: Approval Request
- **Recipient:** Parent staff
- **Subject:** "Approval Request: Ticket #[ID]"
- **Contains:** Sub-staff name, ticket ID, action request

### Email 3: Approval Confirmation
- **Recipient:** Sub-staff
- **Subject:** "Work Approved: Ticket #[ID]"
- **Contains:** Congratulations, ticket ID, processing info

### Email 4: Rejection with Notes
- **Recipient:** Sub-staff
- **Subject:** "Revision Needed: Ticket #[ID]"
- **Contains:** Ticket ID, supervisor notes in blockquote, revision request

---

## Rollback Plan

If issues occur:
1. Restore database backup (cprtl.sql)
2. Restore PHP files from backup
3. Clear browser cache (CTRL+SHIFT+DELETE)
4. Test login again

---

## Deployment Success Criteria

✅ All checks above pass  
✅ Workflow from student → admin → parent → sub-staff → approval works  
✅ Status changes tracked in StatusHistory  
✅ Emails sent and received correctly  
✅ UI consistent and professional  
✅ No 500 errors in browser  
✅ Database connections stable  
✅ Navigation working correctly  

---

## Post-Deployment Monitoring

1. **Check Server Logs**
   - Monitor error_log for PHP errors
   - Monitor MySQL logs for query issues
   - Monitor email service logs

2. **Monitor Database**
   - Track SubStaffApprovals records
   - Monitor StatusHistory growth
   - Check for NULL values where unexpected

3. **User Feedback**
   - Collect feedback from test users
   - Monitor email delivery
   - Check notification system

---

**Last Updated:** January 13, 2026  
**Version:** 1.0 Final

