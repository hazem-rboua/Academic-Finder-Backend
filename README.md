# Academic Finder Backend

A Laravel-based API backend for Academic Finder with admin/company authentication, invitation system, and comprehensive monitoring.

## Features

- **Authentication System**: Laravel Sanctum token-based authentication
- **User Types**: Admin and Company users with role-based access control
- **Invitation System**: Secure invitation links with unique tokens for company registration
- **Admin Panel**: Complete company management (enable/disable, view, delete)
- **Multilanguage Support**: Arabic and English support for all API responses
- **Telescope Monitoring**: Real-time application monitoring (admin-only access)
- **API Documentation**: Auto-generated Swagger/OpenAPI documentation
- **Rate Limiting**: Configurable API rate limiting for security
- **Email Notifications**: Queue-based email system for invitations
- **CORS Support**: Configured for separate frontend application

## Requirements

- PHP 8.2 or higher
- MySQL 8.0 or higher
- Composer
- Node.js & NPM (for asset compilation, if needed)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/hazem-rboua/Academic-Finder-Backend.git
cd academic-finder-backend
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Update the `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=academic_finder
DB_USERNAME=root
DB_PASSWORD=your_password
```

Configure mail settings (use Mailtrap for development):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
```

Set your frontend URL:

```env
FRONTEND_URL=http://localhost:3000
```

### 4. Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE academic_finder"

# Run migrations
php artisan migrate

# Seed admin user
php artisan db:seed
```

### 5. Start the Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`

## Default Admin Credentials

After running the seeder, you can login with:

- **Email**: `admin@academicfinder.com`
- **Password**: `Admin@123`

**⚠️ Important**: Change the admin password after first login in production!

## API Documentation

### Swagger UI

Access the interactive API documentation at:

```
http://localhost:8000/api/documentation
```

To regenerate the documentation after making changes:

```bash
php artisan l5-swagger:generate
```

### Telescope Monitoring

Access Telescope (admin only) at:

```
http://localhost:8000/telescope
```

## API Endpoints

### Authentication

- `POST /api/auth/login` - Login user
- `POST /api/auth/logout` - Logout user
- `GET /api/auth/me` - Get authenticated user
- `POST /api/auth/refresh` - Refresh token

### Invitations (Public)

- `GET /api/invitations/validate/{token}` - Validate invitation token
- `POST /api/invitations/accept/{token}` - Accept invitation and register

### Admin Endpoints (Requires admin role)

**Companies:**
- `GET /api/admin/companies` - List all companies
- `GET /api/admin/companies/{id}` - View company details
- `PUT /api/admin/companies/{id}/enable` - Enable company
- `PUT /api/admin/companies/{id}/disable` - Disable company
- `DELETE /api/admin/companies/{id}` - Delete company

**Invitations:**
- `GET /api/admin/invitations` - List all invitations
- `POST /api/admin/invitations` - Send new invitation
- `DELETE /api/admin/invitations/{id}` - Cancel invitation

### Company Endpoints (Requires company role)

- `GET /api/company/profile` - Get company profile
- `PUT /api/company/profile` - Update company profile

## Authentication

The API uses Laravel Sanctum for authentication. Include the token in the Authorization header:

```
Authorization: Bearer {your-token}
```

### Token Abilities

- **Admin tokens**: Have `admin` ability
- **Company tokens**: Have `company` ability

## Multilanguage Support

The API supports multiple languages (Arabic and English). To specify the language, include the `Accept-Language` header in your requests:

```
Accept-Language: ar
```

or

```
Accept-Language: en
```

Alternatively, you can use the `lang` query parameter:

```
GET /api/auth/login?lang=ar
```

**Supported Languages:**
- `en` - English (default)
- `ar` - Arabic (العربية)

All API responses, validation messages, and error messages will be returned in the requested language.

## Testing

Run the test suite:

```bash
php artisan test
```

Run specific test file:

```bash
php artisan test --filter=AuthenticationTest
```

## Queue Workers

For email notifications to work properly, run the queue worker:

```bash
php artisan queue:work
```

For development, you can use:

```bash
php artisan queue:listen
```

## Rate Limiting

Default rate limits:
- **Auth endpoints**: 5 attempts per minute
- **General API**: 60 requests per minute
- **Admin endpoints**: 100 requests per minute

## Security Features

- Token-based authentication with abilities/scopes
- Unique, hashed invitation tokens
- Token expiration (7 days default)
- One-time use invitation tokens
- Password hashing with bcrypt
- CORS configuration
- Rate limiting
- SQL injection prevention (Eloquent ORM)
- XSS protection

## Project Structure

```
academic-finder-backend/
├── app/
│   ├── Enums/              # Enum classes (UserType, InvitationStatus)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/       # Authentication controllers
│   │   │   ├── Admin/      # Admin controllers
│   │   │   └── Company/    # Company controllers
│   │   ├── Middleware/     # Custom middleware
│   │   └── Requests/       # Form request validation
│   ├── Models/             # Eloquent models
│   ├── Notifications/      # Email notifications
│   └── Services/           # Business logic services
├── config/                 # Configuration files
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/           # Database seeders
├── routes/
│   └── api.php            # API routes
└── tests/                 # Test files
```

## Environment Variables

Key environment variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_URL` | Application URL | `http://localhost:8000` |
| `FRONTEND_URL` | Frontend application URL | `http://localhost:3000` |
| `INVITATION_EXPIRY_DAYS` | Days before invitation expires | `7` |
| `TELESCOPE_ENABLED` | Enable/disable Telescope | `true` |
| `DB_CONNECTION` | Database driver | `mysql` |
| `MAIL_MAILER` | Mail driver | `smtp` |

## Troubleshooting

### Database Connection Issues

If you encounter database connection errors:

1. Verify MySQL is running
2. Check database credentials in `.env`
3. Ensure the database exists
4. Check MySQL user permissions

### Queue Not Processing

If emails aren't being sent:

1. Make sure queue worker is running: `php artisan queue:work`
2. Check mail configuration in `.env`
3. View failed jobs: `php artisan queue:failed`
4. Retry failed jobs: `php artisan queue:retry all`

### Telescope Not Loading

If Telescope is not accessible:

1. Ensure migrations are run: `php artisan migrate`
2. Clear cache: `php artisan config:clear`
3. Check you're logged in as admin

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is proprietary software.

## Support

For support, email support@academicfinder.com or open an issue in the repository.

## Roadmap

- [ ] Password reset functionality
- [ ] Email verification for companies
- [ ] Advanced logging and monitoring
- [ ] API versioning
- [ ] Multi-language support
- [ ] Company-specific features (TBD)

---

**Built with Laravel 11 & Sanctum**
