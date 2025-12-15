# TeamChat Security Checklist

## Pre-Deployment

- [ ] APP_DEBUG=false in production
- [ ] APP_ENV=production
- [ ] Unique APP_KEY and APP_CIPHER_KEY generated
- [ ] Strong database passwords (min. 24 characters)
- [ ] Redis password set
- [ ] HTTPS enforced
- [ ] CORS properly configured

## Server Security

- [ ] Firewall configured (only ports 80, 443, 22)
- [ ] SSH key authentication only
- [ ] Fail2ban installed
- [ ] Automatic security updates enabled
- [ ] Non-root user for deployment

## Application Security

- [ ] All dependencies up to date
- [ ] No known vulnerabilities (npm audit, composer audit)
- [ ] Rate limiting enabled
- [ ] CSRF protection active
- [ ] SQL injection prevention (parameterized queries)
- [ ] XSS prevention (output encoding)
- [ ] File upload validation

## Data Security

- [ ] Messages encrypted at rest
- [ ] Passwords hashed with bcrypt
- [ ] Sensitive data not logged
- [ ] Database backups encrypted
- [ ] S3 bucket private

## Monitoring

- [ ] Error tracking configured
- [ ] Security logging enabled
- [ ] Alerts for suspicious activity
- [ ] Regular security audits scheduled

## Compliance

- [ ] Privacy policy published
- [ ] Terms of service published
- [ ] Data deletion process documented
- [ ] GDPR compliance (if applicable)
