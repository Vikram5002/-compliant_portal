# Windows Local Setup for AWS Migration

Use this guide once on your Windows machine so migration scripts run smoothly.

## 1) Install Required Tools

### Install Git (if needed)

```powershell
winget install --id Git.Git -e --source winget
```

### Install AWS CLI v2

```powershell
winget install --id Amazon.AWSCLI -e --source winget
```

### Install PHP CLI

Option A (recommended): Use official PHP via winget.

```powershell
winget install --id PHP.PHP -e --source winget
```

Option B: Use XAMPP PHP binary if you already use XAMPP.
- Typical path: `C:\xampp\php\php.exe`

### Install Node.js (for Amplify frontend app)

```powershell
winget install --id OpenJS.NodeJS.LTS -e --source winget
```

### Install Amplify CLI

```powershell
npm install -g @aws-amplify/cli
```

## 2) Verify Installations

```powershell
git --version
aws --version
php --version
node --version
npm --version
amplify --version
```

If `php` is not found but XAMPP is installed, run with full path:

```powershell
& "C:\xampp\php\php.exe" --version
```

## 3) Configure AWS Credentials

```powershell
aws configure
```

Provide:
- AWS Access Key ID
- AWS Secret Access Key
- Default region (example: `ap-south-1`)
- Output format: `json`

## 4) Run Migration Commands

From project root:

### 4.1 Deploy DynamoDB tables

```powershell
aws cloudformation deploy `
  --stack-name cprtl-dynamodb-stack `
  --template-file aws_migration/cloudformation/dynamodb-tables.yaml `
  --capabilities CAPABILITY_NAMED_IAM `
  --parameter-overrides TablePrefix=cprtl `
  --region ap-south-1
```

### 4.2 Export MySQL data to DynamoDB JSON

If PHP is in PATH:

```powershell
php aws_migration/scripts/export_mysql_to_dynamodb.php `
  --host=localhost `
  --user=root `
  --password= `
  --database=cprtl `
  --output=aws_migration/out `
  --prefix=cprtl
```

If using XAMPP PHP path:

```powershell
& "C:\xampp\php\php.exe" aws_migration/scripts/export_mysql_to_dynamodb.php `
  --host=localhost `
  --user=root `
  --password= `
  --database=cprtl `
  --output=aws_migration/out `
  --prefix=cprtl
```

### 4.3 Import to DynamoDB

```powershell
powershell -ExecutionPolicy Bypass -File aws_migration/scripts/batch_write_all.ps1 `
  -InputDirectory aws_migration/out `
  -Region ap-south-1
```

## 5) Amplify Hosting Setup

1. Push your frontend app code to GitHub.
2. Open AWS Amplify Console.
3. Choose Host web app -> GitHub.
4. Select repository: `Vikram5002/-compliant_portal`.
5. Select branch: `main`.
6. Set build settings using your frontend app path and `amplify.yml`.
7. Deploy and verify app URL.

## 6) Security Reminders

- Do not commit exported data files in `aws_migration/out/`.
- Rotate AWS keys if accidentally exposed.
- Use IAM least-privilege users for deployment.
