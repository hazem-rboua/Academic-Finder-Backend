# Password Reset Feature

## Overview
The password reset feature allows users to reset their passwords via email. This implementation follows Laravel's standard password reset flow with custom email notifications supporting English and Arabic languages.

## Implementation Details

### Database
- **Table**: `password_reset_tokens`
- **Columns**:
  - `email` (string, indexed)
  - `token` (string)
  - `created_at` (timestamp)

### API Endpoints

#### 1. Request Password Reset
**Endpoint**: `POST /api/auth/forgot-password`

**Request Body**:
```json
{
  "email": "user@example.com"
}
```

**Success Response (200)**:
```json
{
  "message": "We have emailed your password reset link."
}
```

**Note**: For security reasons, this endpoint always returns a success message, even if the email doesn't exist in the system.

#### 2. Reset Password
**Endpoint**: `POST /api/auth/reset-password`

**Request Body**:
```json
{
  "token": "abc123def456ghi789",
  "email": "user@example.com",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

**Success Response (200)**:
```json
{
  "message": "Your password has been reset."
}
```

**Error Response (422)**:
```json
{
  "message": "This password reset token is invalid.",
  "errors": {
    "email": [
      "This password reset token is invalid."
    ]
  }
}
```

## Configuration

### Environment Variables
Add the following to your `.env` file:

```env
# Frontend URL for password reset page
FRONTEND_URL=https://your-frontend-url.com

# Mail Configuration (if not already set)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Password Reset Settings
Token expiry and throttling are configured in `config/auth.php`:
- Token expires after **60 minutes**
- Rate limited to **1 request per minute** per email

## Email Notification
Users receive an email with:
- Personalized greeting with their name
- Explanation of the password reset request
- Reset button linking to: `{FRONTEND_URL}/reset-password?token={TOKEN}&email={EMAIL}`
- Expiry time notice (60 minutes)
- Security notice about ignoring unwanted emails

The email template supports both English and Arabic based on the app locale.

## Security Features
1. **Tokens are hashed** before storage in the database
2. **Tokens expire** after 60 minutes
3. **Rate limiting** prevents abuse (1 request per minute)
4. **Password validation**: Minimum 8 characters, must contain letters and numbers
5. **Secure responses**: Always returns success for forgot password to prevent email enumeration
6. **Old tokens invalidated** after successful password reset

## Testing

### Manual Testing with cURL

1. **Request password reset**:
```bash
curl -X POST http://your-api-url/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'
```

2. **Reset password** (use token from email):
```bash
curl -X POST http://your-api-url/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "token":"TOKEN_FROM_EMAIL",
    "email":"user@example.com",
    "password":"NewPassword123",
    "password_confirmation":"NewPassword123"
  }'
```

### Testing with Postman/Insomnia
Import the Swagger documentation available at `/api/documentation` to test the endpoints with the built-in interface.

## Deployment Checklist

- [ ] Run migration: `php artisan migrate`
- [ ] Configure mail settings in `.env`
- [ ] Set `FRONTEND_URL` in `.env`
- [ ] Clear config cache: `php artisan config:clear`
- [ ] Test email delivery
- [ ] Regenerate Swagger docs: `php artisan l5-swagger:generate`
- [ ] Verify password reset flow end-to-end

## Troubleshooting

### Emails not sending
1. Check mail configuration in `.env`
2. Verify SMTP credentials
3. Check Laravel logs: `storage/logs/laravel.log`
4. Test mail connection: `php artisan tinker` then `Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });`

### Token expired or invalid
- Tokens expire after 60 minutes
- Each token can only be used once
- Make sure the email and token match exactly

### 422 Validation Errors
- Password must be at least 8 characters
- Password must contain letters AND numbers
- Password and password_confirmation must match

## Multi-language Support
The feature supports both English and Arabic:
- English: Default language
- Arabic: Set `Accept-Language: ar` header in API requests

Translation files:
- `lang/en/passwords.php`
- `lang/ar/passwords.php`

## Files Created/Modified

### New Files
- `app/Http/Controllers/Auth/PasswordResetController.php` - Controller handling password reset
- `app/Http/Requests/Auth/ForgotPasswordRequest.php` - Validation for forgot password
- `app/Http/Requests/Auth/ResetPasswordRequest.php` - Validation for reset password
- `app/Notifications/ResetPasswordNotification.php` - Custom email notification
- `database/migrations/2026_01_14_101425_create_password_reset_tokens_table.php` - Database migration
- `lang/ar/passwords.php` - Arabic translations
- `tests/Feature/PasswordResetTest.php` - Test suite

### Modified Files
- `app/Models/User.php` - Added custom notification method
- `routes/api.php` - Added password reset routes
- `config/auth.php` - Added password reset configuration
- `lang/en/passwords.php` - Added email notification strings

## Support
For issues or questions, refer to the Laravel password reset documentation:
https://laravel.com/docs/11.x/passwords

