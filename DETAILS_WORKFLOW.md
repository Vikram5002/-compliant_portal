# Ticket Details & Verification Workflow

## ✅ View Details Implementation

Both `sub_staff_dashboard.php` and `staff_tickets_with_sub.php` now have "View Details" links for all tickets.

### Sub-Staff Dashboard
- **Link Location**: Actions column (📋 View Details)
- **Target**: `ticket_details.php?ticket_id={id}`
- **Shows**: Full ticket details, status history, attachments, comments
- **Purpose**: Sub-staff can review ticket requirements before updating status

### Staff Tickets (Parent Staff View)
- **Direct Assignments Table**: "📋 View Details" link in Actions column
- **Sub-Staff Assignments Table**: New "Action" column with "📋 View Details" link
- **Target**: `ticket_details.php?ticket_id={id}`
- **Shows**: Full ticket details to monitor sub-staff progress

---

## 🔄 Status Update Verification Workflow

### Step 1: Sub-Staff Updates Status
```
sub_staff_dashboard.php
├── Select status: "In Progress" or "Solution Proposed"
├── Click "Update"
└── Saved directly (no approval needed)
```

### Step 2: Sub-Staff Marks as Resolved
```
sub_staff_dashboard.php
├── Select status: "Resolved"
├── Click "Update"
├── Ticket saved with status="Resolved"
└── "Request Approval" button appears
```

### Step 3: Sub-Staff Requests Approval
```
sub_staff_dashboard.php
├── Click "Request Approval"
├── Creates SubStaffApprovals record (status='pending')
├── Sets sub_staff_status='pending_approval'
├── Email sent to parent staff
└── Shows "Pending Approval" badge
```

### Step 4: Parent Staff Reviews (staff_tickets_with_sub.php)
```
staff_tickets_with_sub.php
├── "Pending Approvals" section shows ticket
├── Can click "View Details" to see full ticket info
├── Click [✓ Approve] or [✗ Reject]
└── If Reject: Enter revision notes
```

### Step 5: Verification Complete
```
If Approved:
├── SubStaffApprovals.status = 'approved'
├── sub_staff_status = 'approved_by_parent'
├── Email sent to sub-staff
└── Ticket continues to closure workflow

If Rejected:
├── SubStaffApprovals.status = 'rejected'
├── sub_staff_status = NULL (allows rework)
├── Email sent with revision notes
└── Sub-staff can update and resubmit
```

---

## 📱 UI Updates

### Consistent Styling
- ✅ Same navigation helper across all pages
- ✅ Same button styling and colors
- ✅ Same table layouts
- ✅ Same status badge colors
- ✅ Same sidebar design

### Improved Forms
- ✅ Horizontal form layout (status dropdown + buttons on same line)
- ✅ Better spacing and alignment
- ✅ View Details link below forms
- ✅ Mobile-responsive design maintained

### Visual Indicators
- 📋 View Details icon for clarity
- 🟡 Yellow badge for "Pending Approval"
- 🔵 Blue for primary actions
- 🟢 Green for approve actions
- 🔴 Red for reject actions

---

## Database State Changes

### During Status Update (In Progress/Solution Proposed)
```sql
UPDATE Tickets 
SET status = 'In Progress' 
WHERE ticket_id = ?

INSERT INTO StatusHistory 
(ticket_id, status, timestamp) 
VALUES (?, 'In Progress', NOW())
```

### During Approval Request
```sql
INSERT INTO SubStaffApprovals 
(ticket_id, sub_staff_id, parent_staff_id, status) 
VALUES (?, ?, ?, 'pending')

UPDATE Tickets 
SET sub_staff_status = 'pending_approval' 
WHERE ticket_id = ?
```

### During Approval
```sql
UPDATE SubStaffApprovals 
SET status = 'approved', approved_at = NOW() 
WHERE approval_id = ?

UPDATE Tickets 
SET sub_staff_status = 'approved_by_parent' 
WHERE ticket_id = ?
```

### During Rejection
```sql
UPDATE SubStaffApprovals 
SET status = 'rejected', parent_notes = ? 
WHERE approval_id = ?

UPDATE Tickets 
SET sub_staff_status = NULL 
WHERE ticket_id = ?
```

---

## Testing Steps

1. ✅ Create ticket as admin
2. ✅ Assign to security officer (parent staff)
3. ✅ Security officer logs in and sees "My Tickets"
4. ✅ Security officer clicks "View Details" on ticket
5. ✅ Security officer reassigns to sub-security
6. ✅ Sub-security logs in and sees "My Tasks"
7. ✅ Sub-security clicks "View Details"
8. ✅ Sub-security updates status to "In Progress"
9. ✅ Sub-security updates status to "Solution Proposed"
10. ✅ Sub-security updates status to "Resolved"
11. ✅ "Request Approval" button appears
12. ✅ Sub-security clicks "Request Approval"
13. ✅ Ticket shows "Pending Approval" badge
14. ✅ Security officer refreshes and sees "Pending Approvals" section
15. ✅ Security officer clicks "View Details" on approval
16. ✅ Security officer clicks "Approve"
17. ✅ Sub-security receives approval email
18. ✅ Sub-security can now close ticket or continue workflow

---

## File Changes Summary

| File | Changes |
|------|---------|
| `sub_staff_dashboard.php` | Added "View Details" link, improved form styling |
| `staff_tickets_with_sub.php` | Added "View Details" links in both tables, added Action column header |
| Both files | Maintain consistent UI with other pages |

All changes preserve existing functionality while improving navigation and visual consistency!
