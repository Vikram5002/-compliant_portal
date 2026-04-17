# CPRTL Frontend App (React + Vite)

This app is the Amplify-hosted frontend for the CPRTL cloud migration.

It supports:
- SAP ID + password sign-in through Cognito User Pool
- JWT-protected API calls to API Gateway
- Basic ticket and notification route testing from the UI

## Local Run

1. Copy `.env.example` to `.env.local`
2. Update values if needed
3. Install and run:

```bash
npm install
npm run dev
```

## Production Build

```bash
npm run build
```

## Required Environment Variables

- `VITE_API_BASE_URL`
- `VITE_APP_REGION`
- `VITE_COGNITO_USER_POOL_ID`
- `VITE_COGNITO_APP_CLIENT_ID`

Amplify app-level variables are converted into these `VITE_` values by root `amplify.yml` during build.

## Current API Routes Used

- `GET /tickets/me`
- `GET /notifications`
- `POST /tickets`
