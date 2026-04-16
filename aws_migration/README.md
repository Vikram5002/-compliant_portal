# CPRTL AWS Migration Starter (XAMPP/MySQL -> DynamoDB + Amplify)

This folder gives you a practical starting point to move your current project from:
- Database: XAMPP MySQL/MariaDB
- Frontend/Backend: PHP pages

to:
- Database: AWS DynamoDB
- Frontend hosting: AWS Amplify Hosting
- Source control and CI/CD: Git + Amplify

## 1) Target Architecture

Recommended production architecture:
- Amplify Hosting: deploy frontend web app
- Amazon Cognito: user authentication
- API Gateway + Lambda: backend APIs
- DynamoDB: application data
- S3: attachment storage
- SES (optional): email notifications (replace SMTP password-based email)

## 2) What Is Included Here

- `cloudformation/dynamodb-tables.yaml`
  - Creates DynamoDB tables and GSIs aligned to your current SQL query patterns.
- `scripts/export_mysql_to_dynamodb.php`
  - Reads your local MySQL data and generates BatchWrite JSON files.
- `scripts/batch_write_all.ps1`
  - Imports those JSON files into DynamoDB with retry for unprocessed items.
- `frontend/amplify.yml`
  - Amplify build spec template for a future frontend app.
- `LOCAL_SETUP_WINDOWS.md`
  - One-time installation and verification steps for Git, AWS CLI, PHP, Node, and Amplify CLI.

## 3) One-Time Prerequisites

Install and configure:
1. AWS CLI v2 (`aws configure`)
2. PHP 8+ CLI
3. PowerShell 5+ (already available on Windows)
4. Git
5. (Later for frontend) Node.js 20+ and Amplify CLI (`npm i -g @aws-amplify/cli`)

## 4) Create a Safe Git Branch

From repo root:

```powershell
git checkout -b feature/aws-dynamodb-amplify-migration
```

## 5) Deploy DynamoDB Tables

From repo root:

```powershell
aws cloudformation deploy `
  --stack-name cprtl-dynamodb-stack `
  --template-file aws_migration/cloudformation/dynamodb-tables.yaml `
  --capabilities CAPABILITY_NAMED_IAM `
  --parameter-overrides TablePrefix=cprtl `
  --region ap-south-1
```

## 6) Export MySQL Data (XAMPP) to DynamoDB Batch Files

From repo root:

```powershell
php aws_migration/scripts/export_mysql_to_dynamodb.php `
  --host=localhost `
  --user=root `
  --password= `
  --database=cprtl `
  --output=aws_migration/out `
  --prefix=cprtl
```

This generates files like:
- `aws_migration/out/cprtl_tickets_batch_001.json`
- `aws_migration/out/cprtl_users_batch_001.json`
- `aws_migration/out/manifest.json`

## 7) Import Batch Files into DynamoDB

From repo root:

```powershell
powershell -ExecutionPolicy Bypass -File aws_migration/scripts/batch_write_all.ps1 `
  -InputDirectory aws_migration/out `
  -Region ap-south-1
```

Optional profile usage:

```powershell
powershell -ExecutionPolicy Bypass -File aws_migration/scripts/batch_write_all.ps1 `
  -InputDirectory aws_migration/out `
  -Region ap-south-1 `
  -AwsProfile default
```

## 8) Frontend Migration with Amplify

Your current UI is PHP-rendered. Amplify Hosting is designed for static/SPA frontends (React/Vue/Angular/Next static).

Suggested migration order:
1. Keep PHP app running while data moves to AWS.
2. Build new frontend in a separate folder (for example `frontend_app/`).
3. Implement APIs (Lambda + API Gateway) for:
   - Auth/session replacement with Cognito
   - Ticket create/list/update
   - Sub-staff approval workflow
   - Notifications
4. Connect Amplify app to GitHub/Git branch.
5. Enable continuous deployment using `frontend/amplify.yml`.

## 9) SQL Table to DynamoDB Table Mapping

- `users` -> `cprtl_users`
- `tickets` -> `cprtl_tickets`
- `substaffapprovals` -> `cprtl_substaffapprovals`
- `statushistory` -> `cprtl_statushistory`
- `notifications` -> `cprtl_notifications`
- `feedback` -> `cprtl_feedback`
- `attachments` -> `cprtl_attachments`
- `allowed_roles` -> `cprtl_allowed_roles`

## 10) Important Migration Notes

- DynamoDB does not support SQL joins. Related data is fetched via keys/GSIs and composed in API code.
- Auto-increment IDs do not exist in DynamoDB. During migration we keep existing numeric IDs.
- `notifications` table includes a derived attribute `user_read_key` to support unread/read queries.
- For file uploads, move from local `uploads/` to S3 and store S3 object metadata in DynamoDB.

## 11) Recommended Next Step

After loading data, start by migrating one flow first:
- Student creates ticket
- Admin assigns ticket

Once stable, migrate sub-staff approval flow.
