import { Amplify } from 'aws-amplify';

export const appConfig = {
  apiBaseUrl: import.meta.env.VITE_API_BASE_URL ?? '',
  region: import.meta.env.VITE_APP_REGION ?? 'us-east-1',
  userPoolId: import.meta.env.VITE_COGNITO_USER_POOL_ID ?? '',
  userPoolClientId: import.meta.env.VITE_COGNITO_APP_CLIENT_ID ?? '',
};

export const isAmplifyConfigured =
  Boolean(appConfig.userPoolId) && Boolean(appConfig.userPoolClientId);

if (isAmplifyConfigured) {
  Amplify.configure({
    Auth: {
      Cognito: {
        userPoolId: appConfig.userPoolId,
        userPoolClientId: appConfig.userPoolClientId,
        loginWith: {
          username: true,
        },
      },
    },
  });
}
