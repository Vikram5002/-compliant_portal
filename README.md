# NMIMS Issue Tracker - Complete Documentation Index

## 📋 Quick Start Guide

**New to the system?** Start here:
1. Read [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) for overview
2. Follow [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for setup
3. Review [WORKFLOW_GUIDE.md](WORKFLOW_GUIDE.md) for user flows

---

## 📚 Documentation Files

### System Documentation

| Document | Purpose | Audience |
|----------|---------|----------|
| [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) | Complete system overview, architecture, features | Developers, Admins |
| [WORKSPACE_VERIFICATION.md](WORKSPACE_VERIFICATION.md) | Verification checklist, files modified, database schema | Developers, QA |
| [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) | Step-by-step testing and deployment instructions | DevOps, Deployment |
| [WORKFLOW_GUIDE.md](WORKFLOW_GUIDE.md) | Complete workflow with diagrams and testing steps | All users, QA |
| [DETAILS_WORKFLOW.md](DETAILS_WORKFLOW.md) | Ticket details and approval workflow specifics | Developers |

### Database
| File | Purpose |
|------|---------|
| [cprtl.sql](cprtl.sql) | Complete database schema and sample data |

---

## 🎯 Feature Overview

### ✅ Completed Features

**Authentication & Authorization**
- Multi-role login system (11 roles)
- Session management
- CSRF token protection
- Password hashing

**Ticket Management**
- Create, view, edit, delete tickets
- Category and priority assignment
- File attachments
- Status tracking with history
- Feedback and ratings

**Sub-Staff Workflow** ✨ NEW
- Hierarchical assignment (Admin → Parent Staff → Sub-Staff)
- Status transitions with restrictions
- Approval request system
- Automatic status updates on approval
- Revision tracking with rejection notes

**Email Notifications**
- Office365 SMTP integration
- Template-based emails
- Notifications for:
  - Ticket creation
  - Assignments
  - Approvals/rejections
  - Reassignments

**User Interface**
- Responsive design
- Red gradient theme consistency
- Navigation auto-detection
- Active page highlighting
- Font Awesome icons

**Reporting & Export**
- Closed tickets CSV export
- Notification history
- Status history tracking

---

## 📁 File Structure

### Application Files
```
Dashboard Pages:
- index.php                    (Main dashboard)
- admin_dashboard.php          (Admin management)
- staff_tickets_with_sub.php   (Parent staff approvals)
- sub_staff_dashboard.php      (Sub-staff tasks)
- staff_tickets.php            (Rector/IT assignments)
- view_tickets.php             (Student tickets)
- ticket_details.php           (Full ticket view)
- notifications.php            (Notification center)

Authentication:
- login.php
- logout.php
- signup.php
- forgot_password.php
- reset_password.php
- verify_otp.php

Support:
- create_ticket.php
- delete_ticket.php
- closed_tickets.php
- download_closed_tickets.php
- bulk_import.php

System:
- db_connect.php               (Database & session)
- send_email.php               (Email service)
- nav_helper.php               (Navigation)
```

### Documentation
```
- SYSTEM_SUMMARY.md            (This overview)
- DEPLOYMENT_GUIDE.md          (Setup & testing)
- WORKFLOW_GUIDE.md            (User workflows)
- DETAILS_WORKFLOW.md          (Technical workflows)
- WORKSPACE_VERIFICATION.md    (Verification checks)
- README.md                    (Index - this file)
```

### Database & Assets
```
- cprtl.sql                    (Database schema)
- NMIMS Logo.jpg               (Branding)
- PHPMailer/                   (Email library)
- uploads/                     (File attachments)
```

---

## 🔄 Complete Workflow

### User Journey: Student → Ticket Resolution

```
1. STUDENT
   └─ Creates ticket on index.php
   └─ Ticket Status: RECEIVED
   └─ Admins notified via email

2. ADMIN
   └─ Views ticket on admin_dashboard.php
   └─ Assigns to Parent Staff (security/staff/etc.)
   └─ Parent Staff receives email

3. PARENT STAFF
   └─ Views on staff_tickets_with_sub.php
   └─ Sees ticket in "My Assignments"
   └─ Reassigns to Sub-Staff
   └─ Sub-Staff receives email

4. SUB-STAFF
   └─ Views on sub_staff_dashboard.php
   └─ Updates status → "In Progress"
   └─ Updates status → "Solution Proposed"
   └─ Clicks "Request Approval for Resolution"
   └─ Approval request created

5. PARENT STAFF (APPROVAL)
   └─ Sees "Pending Approvals" section
   └─ Reviews ticket details
   └─ OPTIONS:
      a) APPROVE → Ticket Status: RESOLVED ✅
      b) REJECT → Shows notes, Sub-Staff revises

6. STUDENT (FINAL)
   └─ Views resolved ticket
   └─ Provides rating/feedback
   └─ Ticket Status: CLOSED
```

---

## 🛡️ Security Features

**Authentication**
- ✅ Session-based login
- ✅ Password hashing (PASSWORD_DEFAULT)
- ✅ OTP verification
- ✅ CSRF token protection

**Database**
- ✅ Prepared statements (no SQL injection)
- ✅ Foreign key constraints
- ✅ Input sanitization
- ✅ Email validation

**Authorization**
- ✅ Role-based access control
- ✅ Page-level permission checks
- ✅ Ownership verification
- ✅ Relationship validation

**Data**
- ✅ Status history audit trail
- ✅ Approval workflow tracking
- ✅ Change timestamps
- ✅ User attribution

---

## 🗄️ Database Tables

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| users | User accounts | user_id, role, parent_staff_id, is_sub_staff |
| tickets | Issue tracking | ticket_id, status, assigned_to, reassigned_to |
| substaffapprovals | Approval workflow | approval_id, status, parent_notes, approved_at |
| statushistory | Audit trail | history_id, status, timestamp |
| notifications | User alerts | notification_id, is_read, created_at |
| attachments | File uploads | attachment_id, file_path, file_type |
| feedback | User ratings | feedback_id, rating, feedback_text |

---

## 🚀 Getting Started

### For Developers

1. **Setup**
   - Import cprtl.sql into database
   - Configure db_connect.php
   - Configure send_email.php (SMTP)
   - Upload all files to server

2. **Testing**
   - Follow DEPLOYMENT_GUIDE.md Phase by Phase
   - Create test users for each role
   - Test complete workflow
   - Verify email notifications

3. **Customization**
   - Colors in CSS sections (--primary: #c41e3a)
   - Email templates in send_email.php
   - Roles in bulk_import.php
   - Navigation in nav_helper.php

### For Admins

1. **User Management**
   - Create staff members (bulk_import.php or CSV)
   - Assign parent-child relationships
   - Monitor user activity

2. **Ticket Management**
   - Review open tickets
   - Assign to appropriate staff
   - Monitor resolution SLA

3. **Reporting**
   - Export closed tickets
   - Track status history
   - Monitor approval workflow

### For Staff

1. **Sub-Staff Assignment**
   - Login to staff_tickets_with_sub.php
   - Reassign tickets to sub-staff
   - Review pending approvals

2. **Task Management**
   - Sub-staff works on sub_staff_dashboard.php
   - Updates status through workflow
   - Requests approval for resolution

---

## 📊 Key Statistics

| Metric | Value |
|--------|-------|
| Total Pages | 20+ |
| Database Tables | 10 |
| Roles Supported | 11 |
| User Fields | 9 |
| Ticket Fields | 14 |
| Status States | 5 |
| Email Templates | 6 |
| API Endpoints | 0 (Form-based) |

---

## ✅ Verification Checklist

**Database**
- [ ] cprtl.sql imported
- [ ] users table has parent_staff_id, is_sub_staff
- [ ] tickets table has reassigned_to, sub_staff_status
- [ ] substaffapprovals table exists
- [ ] Foreign keys configured

**Files**
- [ ] All 20+ PHP files uploaded
- [ ] NMIMS Logo.jpg present
- [ ] PHPMailer directory complete
- [ ] uploads/ directory writable
- [ ] Permissions set correctly

**Configuration**
- [ ] db_connect.php configured
- [ ] send_email.php configured (SMTP)
- [ ] Database connection tested
- [ ] Email service tested
- [ ] File upload tested

**Functionality**
- [ ] Login works
- [ ] Ticket creation works
- [ ] Assignment works
- [ ] Sub-staff workflow works
- [ ] Approval workflow works ✅ NEW
- [ ] Emails send correctly
- [ ] Navigation works
- [ ] Views render correctly

---

## 🔗 Related Files

**For Approval Workflow Details:**
- See: [DETAILS_WORKFLOW.md](DETAILS_WORKFLOW.md)
- Code: [staff_tickets_with_sub.php](staff_tickets_with_sub.php) (lines 70-172)
- Code: [sub_staff_dashboard.php](sub_staff_dashboard.php) (lines 30-70)

**For UI Consistency:**
- All pages use red gradient: #c41e3a to #8B0000
- See: CSS sections in [staff_tickets_with_sub.php](staff_tickets_with_sub.php) and [sub_staff_dashboard.php](sub_staff_dashboard.php)

**For Navigation:**
- See: [nav_helper.php](nav_helper.php)
- Integrated in all dashboard pages

**For Email Templates:**
- See: [send_email.php](send_email.php)
- Usage throughout application

---

## 📞 Support Resources

| Issue | Location | Action |
|-------|----------|--------|
| Workflow questions | [WORKFLOW_GUIDE.md](WORKFLOW_GUIDE.md) | Read workflow section |
| Deployment help | [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) | Follow step-by-step |
| System overview | [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) | Read feature overview |
| Verification issues | [WORKSPACE_VERIFICATION.md](WORKSPACE_VERIFICATION.md) | Check verification list |
| Code issues | See individual files | Read comments and docstrings |

---

## 📅 Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Jan 13, 2026 | Initial complete system with approval workflow |

---

## 🎓 Learning Path

**Beginner (1 hour)**
1. Read SYSTEM_SUMMARY.md (15 min)
2. Review WORKFLOW_GUIDE.md (30 min)
3. Check DEPLOYMENT_GUIDE.md structure (15 min)

**Intermediate (2 hours)**
1. Read DETAILS_WORKFLOW.md (30 min)
2. Study code in staff_tickets_with_sub.php (60 min)
3. Review database schema in cprtl.sql (30 min)

**Advanced (3+ hours)**
1. Deep dive into all PHP files
2. Study security implementation
3. Plan customizations
4. Review deployment checklist

---

## 🎯 Next Steps

1. **Setup:** Follow DEPLOYMENT_GUIDE.md
2. **Test:** Execute all test cases
3. **Deploy:** Upload to production
4. **Monitor:** Check error logs
5. **Feedback:** Collect user feedback
6. **Improve:** Plan enhancements

---

## 📝 Notes

- System uses vanilla PHP (no framework)
- PHPMailer for email service
- MariaDB for database
- Custom CSS3 for styling
- Responsive mobile design
- CSRF protection enabled
- Prepared statements throughout

---

## 🎉 Summary

The NMIMS Issue Tracker is a complete, production-ready ticket management system with:
- ✅ Multi-role user hierarchy
- ✅ Sub-staff assignment workflow
- ✅ Approval request system
- ✅ Email notifications
- ✅ Responsive UI
- ✅ Secure database
- ✅ Complete audit trail
- ✅ Comprehensive documentation

**Status:** Ready for Production Deployment ✅

---

**Last Updated:** January 13, 2026  
**Documentation Version:** 1.0  
**System Version:** 1.0 Complete

For questions or issues, refer to the appropriate documentation file above.

