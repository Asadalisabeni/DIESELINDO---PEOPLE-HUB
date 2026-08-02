# Identity and access operations runbook

## First deployment

Run these commands from the PeopleHub project directory. Never run them in the MES directory and do not use port 8877.

```powershell
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan iam:bootstrap-admin admin@dieselindo.co.id --name="Authorized Administrator"
```

The bootstrap command asks for the password twice using hidden terminal input. There is deliberately no password option, so a secret cannot be placed in shell history or process arguments. The command refuses to overwrite an existing email. After the first administrator is verified, provision all further users through the IAM screen.

## Environment checklist

For staging and production:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://peoplehub.example.com
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
IAM_PASSWORD_MIN_LENGTH=12
IAM_PASSWORD_COMPROMISED_CHECK=true
IAM_MAX_LOGIN_ATTEMPTS=5
IAM_LOGIN_RATE_LIMIT=5
IAM_ACCOUNT_LOCK_MINUTES=15
```

Confirm the reverse proxy forwards the trusted client IP correctly before using IP evidence. Configure a production mail transport and queue worker before inviting users; the local `log` mailer is not a delivery channel.

## Routine account operations

- **Provision:** Group HR Admin or Super Admin selects one approved role. The system starts a reset-password flow.
- **Deactivate/terminate:** Clear `is_active`; the model deletes all database sessions immediately. The global middleware invalidates any stale browser session on its next request.
- **Suspected compromise:** Deactivate the account, review authentication events, reset the password, re-enable only after validation, and require new 2FA setup when appropriate.
- **Lost authenticator:** Use one recovery code. If all codes are lost, an authorized, audited support process must verify the identity before disabling 2FA.
- **Temporary lock:** Wait for `locked_until`, or have an authorized administrator investigate. Do not clear a lock without reviewing recent failures.

## Deployment verification

1. `php artisan migrate:status` shows all four Phase 4 migrations as Ran.
2. `php artisan route:list` contains login, reset, verification, 2FA, security, IAM, and audit routes, with no registration route.
3. `php artisan test` and `composer quality` pass.
4. `npm run build` passes.
5. HTTPS responses set `Secure`, `HttpOnly`, and `SameSite=Lax` on the session cookie.
6. The application DB user cannot update/delete the two audit tables.
7. A non-IAM employee receives HTTP 403 from `/iam/users`; an auditor without `iam.manage` also receives 403.

## Rollback

Application rollback may revert PHP/assets while retaining Phase 4 tables and columns. Do not run migration rollback after identity data exists without an approved backup and incident plan, because it removes 2FA secrets, role assignments, sessions, and audit evidence. Database migrations are forward-fix by default.
