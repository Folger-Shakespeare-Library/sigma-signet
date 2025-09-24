# Sigma Signet

A WordPress plugin that provides OpenID Connect (OIDC) authentication integration with the SIGMA access management system, enabling seamless single sign-on for academic and library content management systems.

## Features

- **Complete OIDC Authentication Flow**: Handles authorization, token exchange, and user info retrieval
- **Real JWT Token Generation**: Creates properly encrypted JWT tokens using SIGMA's public keys
- **Automatic User Management**: Creates WordPress users from SIGMA identities with no email conflicts
- **WordPress Admin Interface**: Easy configuration through WordPress Settings
- **Debug Logging Toggle**: Enable detailed logging for development, disable for production
- **Comprehensive Testing**: 20+ passing tests using Pest PHP testing framework
- **Production Ready**: Built with proper error handling, input validation, and security practices

## Requirements

- WordPress 5.8 or higher
- PHP 8.0 or higher
- Composer (for development)
- Access to a SIGMA OIDC Identity Provider

## Installation

### For Users

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/sigma-signet/` directory
3. Run `composer install --no-dev` in the plugin directory to install dependencies
4. Activate the plugin through WordPress admin
5. Configure your SIGMA credentials in Settings > SIGMA OIDC

### For Developers

1. Clone this repository:
   ```bash
   git clone https://github.com/folger-shakespeare-library/sigma-signet.git
   cd sigma-signet
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests:
   ```bash
   ./vendor/bin/pest
   ```

4. Symlink to your WordPress plugins directory:
   ```bash
   ln -s /path/to/sigma-signet /path/to/wordpress/wp-content/plugins/sigma-signet
   ```

## Configuration

### Required Settings

1. **Identity Provider URL**: Your SIGMA IDP endpoint (e.g., `https://idp.sams-sigma.com`)
2. **Client ID**: Your licensed website identifier from SIGMA
3. **Client Secret**: Your client secret from SIGMA
4. **Redirect URI**: Callback URL (defaults to `https://yoursite.com/sigma-callback`)

### Optional Settings

- **Debug Logging**: Enable for development, disable for production

### SIGMA Configuration

In your SIGMA licensed website configuration, set the redirect URI to match your WordPress callback URL (typically `https://yoursite.com/sigma-callback`).

## How It Works

1. **User clicks login** → Redirects to SIGMA with encrypted JWT auth token
2. **User authenticates with SIGMA** → SIGMA redirects back with authorization code
3. **Plugin exchanges code for tokens** → Gets access token and user info
4. **WordPress user created/updated** → User logged into WordPress automatically

## User Management

- **Username**: Uses SIGMA `sub` ID directly (guaranteed unique)
- **Email**: Generates `{sub_id}@sigma.local` to avoid conflicts
- **Real Email**: Stored in user meta as `sigma_email` for reference
- **Display Name**: Uses name from SIGMA user info
- **User Linking**: Users are found by SIGMA ID, not email

## Development

### Architecture

```
src/
├── Plugin.php           # Main plugin class
├── Settings.php         # Settings management
├── Admin.php           # WordPress admin interface
├── OidcClient.php      # OIDC authorization URL builder
├── TokenGenerator.php   # JWT token generation
├── TokenExchange.php   # Authorization code → access token exchange
├── UserManager.php     # WordPress user creation/login
└── WordPressIntegration.php # WordPress hooks and routing
```

### Testing

Run the test suite:
```bash
./vendor/bin/pest
```

The plugin includes comprehensive unit tests covering all major functionality with mocked WordPress functions.

### Debug Logging

Enable debug logging in the admin interface to see detailed authentication flow information. Debug logs are prefixed with `[SIGMA Debug]`.

### Adding New Features

The plugin is designed to be extensible:

- Add new SIGMA scopes by modifying `OidcClient::buildAuthorizationUrl()`
- Extend user creation logic in `UserManager::createNewUser()`
- Add custom user role mapping based on SIGMA license info

## Security Considerations

- JWT tokens are properly encrypted using SIGMA's public keys
- All user input is sanitized using WordPress functions
- Client secrets are stored securely in WordPress options
- CSRF protection handled by WordPress Settings API
- User authentication flows follow OIDC security best practices

## Troubleshooting

### Common Issues

1. **"Failed to build authorization URL"**
   - Check that all required settings are configured
   - Verify SIGMA IDP URL is accessible
   - Enable debug logging to see specific error

2. **"Authentication failed"**
   - Verify client ID and secret are correct
   - Check that redirect URI matches SIGMA configuration
   - Ensure SIGMA user has proper access

3. **Users not being created**
   - Check WordPress debug logs for user creation errors
   - Verify WordPress user creation permissions

### Debug Information

Enable debug logging and check for messages prefixed with `[SIGMA Debug]` in your WordPress debug log.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass: `./vendor/bin/pest`
5. Submit a pull request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by [Folger Shakespeare Library](https://www.folger.edu) for integration with the SIGMA access management system.

## Changelog

### v1.0.0
- Initial release
- Complete OIDC authentication flow
- WordPress user management
- Admin configuration interface
- Debug logging toggle
- Comprehensive test coverage
