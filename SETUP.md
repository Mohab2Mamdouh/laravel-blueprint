# laravel-blueprint — Private Package Setup Guide

How to publish the package to a private GitHub repo and install it in any project.

---

## Step 1 — Create the GitHub Repository

Go to [https://github.com/new](https://github.com/new) and create a repository with the following settings:

- **Name:** `laravel-blueprint`
- **Visibility:** Private

---

## Step 2 — Push Your Package Code

Inside your package directory on your local machine:

```bash
cd /path/to/your/laravel-blueprint

git init
git add .
git commit -m "Initial release v1.0.0"
git branch -M main
git remote add origin https://github.com/Mohab2Mamdouh/laravel-blueprint.git
git push -u origin main
```

---

## Step 3 — Tag the Release

Composer requires at least one version tag to resolve a stable version.

```bash
git tag v1.0.0
git push origin v1.0.0
```

---

## Step 4 — Generate a GitHub Access Token

Go to:
```
https://github.com/settings/personal-access-tokens/new
```

Set the following:
- **Name:** Composer
- **Expiration:** your preference
- **Repository access:** Only `laravel-blueprint`
- **Permissions:** Contents → Read-only

Copy the generated token.

---

## Step 5 — Configure Docker to Use the Token

In your project's `.env` file add:

```env
COMPOSER_GITHUB_TOKEN=your-token-here
```

In your `docker-compose.yml` under the `app` service add:

```yaml
services:
  app:
    environment:
      COMPOSER_AUTH: '{"github-oauth":{"github.com":"${COMPOSER_GITHUB_TOKEN}"}}'
```

> Make sure `.env` is in your `.gitignore` so the token is never committed.

---

## Step 6 — Configure Each New Project

In the project's `composer.json` add the `repositories` block:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/Mohab2Mamdouh/laravel-blueprint"
    }
]
```

Or add it globally on your host machine once so you never have to do it per project:

```bash
composer config --global repositories.laravel-blueprint vcs https://github.com/Mohab2Mamdouh/laravel-blueprint
```

---

## Step 7 — Install the Package

```bash
composer require mohab/laravel-blueprint
```

---

## Step 8 — Run Migrations

```bash
php artisan migrate
```

---

## Step 9 — Publish Config (Optional)

Only needed if you want to override the default supported locales:

```bash
php artisan vendor:publish --tag=blueprint-config
```

This publishes `config/blueprint.php` to your project where you can edit it:

```php
return [
    'supported_locales' => ['en', 'ar', 'fr'],
];
```

---

## Releasing a New Version

Whenever you make changes to the package:

```bash
git add .
git commit -m "Description of changes"
git tag v1.1.0
git push origin main
git push origin v1.1.0
```

Then in your projects run:

```bash
composer update mohab/laravel-blueprint
```