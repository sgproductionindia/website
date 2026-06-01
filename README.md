# SG Production Website

Music download platform built with PHP and Apache.

## Environment Variables Required

SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_FROM=
SMTP_FROM_NAME=
CONTACT_TO=
ADMIN_PASSWORD=

## Deployment

Deployed via Coolify with Docker.

Persistent volumes:
- /var/www/html/uploads
- /var/www/html/data

## Tech Stack

- PHP 8.2
- Apache
- Docker / Coolify
