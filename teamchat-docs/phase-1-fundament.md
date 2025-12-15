# Phase 1: Fundament (Woche 1-2)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Funktionierende Docker-Entwicklungsumgebung
- Laravel 12 Backend mit allen Datenbank-Tabellen
- Alle Eloquent Models mit Relationships
- Funktionierende User-Registrierung und Login
- TinyPNG Integration für Bildkomprimierung
- Vollständige Test-Suite für alle Komponenten

---

## 1.1 Entwicklungsumgebung [INFRA]

### 1.1.1 Projektstruktur anlegen
- [x] **Erledigt**

**Beschreibung:** Hauptverzeichnis mit Unterordnern erstellen und Git initialisieren.

**Durchführung:**
```bash
mkdir teamchat
cd teamchat
mkdir backend frontend docker docs
git init
```

**Datei erstellen:** `.gitignore`
```
# Laravel
/backend/vendor/
/backend/node_modules/
/backend/.env
/backend/storage/*.key
/backend/storage/logs/*
/backend/bootstrap/cache/*

# Frontend
/frontend/node_modules/
/frontend/dist/

# IDE
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Docker
docker/mysql_data/
docker/redis_data/
```

**Akzeptanzkriterien:**
- [ ] Verzeichnisstruktur existiert: `backend/`, `frontend/`, `docker/`, `docs/`
- [ ] Git ist initialisiert (`git status` funktioniert)
- [ ] `.gitignore` ist erstellt mit allen Einträgen

**Verifizierung:**
```bash
ls -la
git status
cat .gitignore
```

---

### 1.1.2 Docker Compose konfigurieren
- [x] **Erledigt**

→ *Abhängig von 1.1.1*

**Beschreibung:** Docker Compose für MySQL, Redis, phpMyAdmin und Mailpit erstellen.

**Datei erstellen:** `docker-compose.yml`
```yaml
version: '3.8'

services:
  mysql:
    image: mysql:8.0
    container_name: teamchat-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: root_secret
      MYSQL_DATABASE: teamchat
      MYSQL_USER: teamchat
      MYSQL_PASSWORD: teamchat_secret
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    command: --default-authentication-plugin=mysql_native_password
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: teamchat-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  phpmyadmin:
    image: phpmyadmin:latest
    container_name: teamchat-phpmyadmin
    restart: unless-stopped
    environment:
      PMA_HOST: mysql
      PMA_USER: teamchat
      PMA_PASSWORD: teamchat_secret
    ports:
      - "8080:80"
    depends_on:
      mysql:
        condition: service_healthy

  mailpit:
    image: axllent/mailpit:latest
    container_name: teamchat-mailpit
    restart: unless-stopped
    ports:
      - "8025:8025"
      - "1025:1025"

volumes:
  mysql_data:
  redis_data:
```

**Akzeptanzkriterien:**
- [ ] `docker-compose.yml` existiert im Root-Verzeichnis
- [ ] Alle 4 Services sind definiert (mysql, redis, phpmyadmin, mailpit)
- [ ] Healthchecks für mysql und redis konfiguriert
- [ ] Volumes für Persistenz definiert

---

### 1.1.3 Docker starten und verifizieren
- [x] **Erledigt**

→ *Abhängig von 1.1.2*

**Durchführung:**
```bash
# Container starten
docker-compose up -d

# Status prüfen (alle sollten "Up" sein)
docker-compose ps

# MySQL testen
docker exec -it teamchat-mysql mysql -u teamchat -pteamchat_secret -e "SELECT 1 as test"

# Redis testen
docker exec -it teamchat-redis redis-cli ping
```

**Akzeptanzkriterien:**
- [ ] `docker-compose ps` zeigt alle Container als "Up"
- [ ] MySQL antwortet mit "1" auf SELECT
- [ ] Redis antwortet mit "PONG"
- [ ] phpMyAdmin erreichbar: http://localhost:8080
- [ ] Mailpit erreichbar: http://localhost:8025

**Fehlerbehebung bei Problemen:**
| Problem | Lösung |
|---------|--------|
| Port bereits belegt | Port in docker-compose.yml ändern |
| Container startet nicht | `docker-compose logs mysql` prüfen |
| Permission denied | `sudo` verwenden oder Docker-Gruppe |

---

## 1.2 Laravel Backend Setup [BE]

### 1.2.1 Laravel 12 Projekt erstellen
- [x] **Erledigt**

→ *Abhängig von 1.1.3*

**Durchführung:**
```bash
cd teamchat
composer create-project laravel/laravel:^12.0 backend
cd backend
php artisan --version
```

**Akzeptanzkriterien:**
- [ ] Ausgabe zeigt "Laravel Framework 12.x.x"
- [ ] `php artisan serve` startet Server auf Port 8000
- [ ] http://localhost:8000 zeigt Laravel Welcome Page

---

### 1.2.2 Environment konfigurieren
- [x] **Erledigt**

→ *Abhängig von 1.2.1*

**Datei bearbeiten:** `backend/.env`
```env
APP_NAME=TeamChat
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teamchat
DB_USERNAME=teamchat
DB_PASSWORD=teamchat_secret

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@teamchat.local"
MAIL_FROM_NAME="${APP_NAME}"

BROADCAST_CONNECTION=reverb

TINYPNG_API_KEY=
```

**Durchführung:**
```bash
cd backend
php artisan key:generate
php artisan config:clear
php artisan db:show
```

**Akzeptanzkriterien:**
- [ ] APP_KEY ist generiert (nicht leer)
- [ ] `php artisan db:show` zeigt "teamchat" Datenbank ohne Fehler
- [ ] `php artisan config:cache` läuft ohne Fehler

---

### 1.2.3 Basis-Packages installieren
- [x] **Erledigt**

→ *Abhängig von 1.2.2*

**Durchführung:**
```bash
cd backend

# API Authentication
php artisan install:api

# Broadcasting (Reverb)
php artisan install:broadcasting

# Bildverarbeitung
composer require intervention/image-laravel

# TinyPNG
composer require tinify/tinify

# IDE Helper (Dev)
composer require --dev barryvdh/laravel-ide-helper

# IDE Helper generieren
php artisan ide-helper:generate
php artisan ide-helper:models --nowrite
php artisan ide-helper:meta
```

**Akzeptanzkriterien:**
- [ ] `config/sanctum.php` existiert
- [ ] `config/reverb.php` existiert
- [ ] `config/image.php` existiert
- [ ] `_ide_helper.php` existiert im backend-Root
- [ ] `composer.json` enthält alle Packages

---

### 1.2.4 Storage Link erstellen
- [x] **Erledigt**

→ *Abhängig von 1.2.1*

**Durchführung:**
```bash
cd backend
php artisan storage:link
```

**Akzeptanzkriterien:**
- [ ] `public/storage` ist Symlink zu `storage/app/public`
- [ ] Test: Datei in `storage/app/public/test.txt` erstellen → via http://localhost:8000/storage/test.txt erreichbar

---

### 1.2.5 Services Config erweitern
- [x] **Erledigt**

→ *Abhängig von 1.2.3*

**Datei bearbeiten:** `backend/config/services.php` - am Ende hinzufügen:
```php
'tinypng' => [
    'api_key' => env('TINYPNG_API_KEY'),
],
```

**Akzeptanzkriterien:**
- [ ] `config('services.tinypng.api_key')` gibt null zurück (noch kein Key gesetzt)

---

## 1.3 Datenbank-Schema [BE]

### 1.3.1 Standard-Migrationen löschen
- [x] **Erledigt**

→ *Abhängig von 1.2.2*

**Durchführung:**
```bash
cd backend
rm database/migrations/0001_01_01_000000_create_users_table.php
rm database/migrations/0001_01_01_000001_create_cache_table.php
rm database/migrations/0001_01_01_000002_create_jobs_table.php
```

**Akzeptanzkriterien:**
- [ ] Nur noch `create_personal_access_tokens_table.php` in migrations/

---

### 1.3.2 Migration: users Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.1*

**Durchführung:**
```bash
php artisan make:migration create_users_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000001_create_users_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('username', 100);
            $table->string('avatar_path', 500)->nullable();
            $table->enum('status', ['available', 'busy', 'away', 'offline'])->default('offline');
            $table->string('status_text', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
```

**Akzeptanzkriterien:**
- [ ] Migration erstellt
- [ ] Enthält: id, email (unique), password, username, avatar_path, status (enum), status_text
- [ ] Indizes auf email und status

**Unit Test:** `tests/Feature/Database/UsersMigrationTest.php`
```php
<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsersMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'email', 'password', 'username', 'avatar_path',
            'status', 'status_text', 'email_verified_at',
            'remember_token', 'created_at', 'updated_at'
        ]));
    }

    public function test_password_reset_tokens_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
    }

    public function test_sessions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
    }
}
```

---

### 1.3.3 Migration: companies Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.2*

**Durchführung:**
```bash
php artisan make:migration create_companies_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000002_create_companies_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('join_password');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('logo_path', 500)->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->fullText('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

**Akzeptanzkriterien:**
- [ ] Foreign Key zu users mit CASCADE DELETE
- [ ] slug ist unique
- [ ] Fulltext-Index auf name für Suche

---

### 1.3.4 Migration: company_members Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.3*

**Durchführung:**
```bash
php artisan make:migration create_company_members_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000003_create_company_members_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['company_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_members');
    }
};
```

**Akzeptanzkriterien:**
- [ ] Unique Constraint auf (company_id, user_id)
- [ ] role ist enum mit admin/user

---

### 1.3.5 Migration: channels Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.3*

**Durchführung:**
```bash
php artisan make:migration create_channels_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000004_create_channels_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
```

**Akzeptanzkriterien:**
- [ ] FK zu companies mit CASCADE DELETE
- [ ] is_private als boolean mit default true

---

### 1.3.6 Migration: channel_members Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.5*

**Durchführung:**
```bash
php artisan make:migration create_channel_members_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000005_create_channel_members_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['channel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_members');
    }
};
```

---

### 1.3.7 Migration: channel_join_requests Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.5*

**Durchführung:**
```bash
php artisan make:migration create_channel_join_requests_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000006_create_channel_join_requests_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('message')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_join_requests');
    }
};
```

---

### 1.3.8 Migration: direct_conversations Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.2*

**Durchführung:**
```bash
php artisan make:migration create_direct_conversations_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000007_create_direct_conversations_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('user_one_accepted')->default(false);
            $table->boolean('user_two_accepted')->default(false);
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id']);
            $table->index('user_one_id');
            $table->index('user_two_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_conversations');
    }
};
```

**Wichtig:** user_one_id ist IMMER die kleinere ID (Normalisierung erfolgt im Model)

---

### 1.3.9 Migration: messages Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.5, 1.3.8*

**Durchführung:**
```bash
php artisan make:migration create_messages_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000008_create_messages_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('messageable_type', 20);
            $table->unsignedBigInteger('messageable_id');
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->string('content_iv', 32)->nullable();
            $table->enum('content_type', ['text', 'file', 'image', 'emoji'])->default('text');
            $table->foreignId('parent_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['messageable_type', 'messageable_id'], 'idx_messageable');
            $table->index('sender_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
```

**Akzeptanzkriterien:**
- [ ] Polymorphe Beziehung (messageable_type/id)
- [ ] Soft Deletes (deleted_at)
- [ ] content_iv für Verschlüsselung

---

### 1.3.10 Migration: message_reactions Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.9*

**Durchführung:**
```bash
php artisan make:migration create_message_reactions_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000009_create_message_reactions_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 50);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
```

---

### 1.3.11 Migration: files Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.9*

**Durchführung:**
```bash
php artisan make:migration create_files_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000010_create_files_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->string('fileable_type', 20);
            $table->unsignedBigInteger('fileable_id');
            $table->string('original_name', 500);
            $table->string('stored_name', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('file_path', 1000);
            $table->string('thumbnail_path', 1000)->nullable();
            $table->boolean('is_compressed')->default(false);
            $table->unsignedBigInteger('original_size')->nullable();
            $table->timestamps();

            $table->index(['fileable_type', 'fileable_id'], 'idx_fileable');
            $table->index('uploader_id');
            $table->index('mime_type');
            $table->index('created_at');
            $table->fullText('original_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
```

---

### 1.3.12 Migration: read_receipts Tabelle
- [ ] **Erledigt**

→ *Abhängig von 1.3.9*

**Durchführung:**
```bash
php artisan make:migration create_read_receipts_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000011_create_read_receipts_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('read_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('messageable_type', 20);
            $table->unsignedBigInteger('messageable_id');
            $table->foreignId('last_read_message_id')->constrained('messages')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['user_id', 'messageable_type', 'messageable_id'], 'unique_read_receipt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('read_receipts');
    }
};
```

---

### 1.3.13 Migration: cache und jobs Tabellen
- [ ] **Erledigt**

→ *Abhängig von 1.3.2*

**Durchführung:**
```bash
php artisan make:migration create_cache_table
php artisan make:migration create_jobs_table
```

**Datei:** `database/migrations/xxxx_xx_xx_000012_create_cache_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
```

**Datei:** `database/migrations/xxxx_xx_xx_000013_create_jobs_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
    }
};
```

---

### 1.3.14 Alle Migrationen ausführen
- [ ] **Erledigt**

→ *Abhängig von 1.3.1 bis 1.3.13*

**Durchführung:**
```bash
cd backend
php artisan migrate
php artisan migrate:status
```

**Akzeptanzkriterien:**
- [ ] Keine Fehler bei Migration
- [ ] `php artisan migrate:status` zeigt alle als "Ran"
- [ ] In phpMyAdmin: Alle Tabellen sichtbar mit korrekter Struktur

**Unit Test:** `tests/Feature/Database/AllTablesTest.php`
```php
<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AllTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_tables_exist(): void
    {
        $tables = [
            'users', 'password_reset_tokens', 'sessions',
            'companies', 'company_members',
            'channels', 'channel_members', 'channel_join_requests',
            'direct_conversations', 'messages', 'message_reactions',
            'files', 'read_receipts',
            'cache', 'cache_locks',
            'jobs', 'job_batches', 'failed_jobs',
            'personal_access_tokens'
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Table {$table} does not exist"
            );
        }
    }
}
```

---

## 1.4 Eloquent Models [BE]

### 1.4.1 User Model erweitern
- [ ] **Erledigt**

→ *Abhängig von 1.3.14*

**Datei:** `app/Models/User.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'username',
        'avatar_path',
        'status',
        'status_text',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ========== Relationships ==========

    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_members')
            ->withPivot('added_by', 'joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'uploader_id');
    }

    // ========== Helper Methods ==========

    public function isAdminOf(Company $company): bool
    {
        return $this->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function isMemberOf(Company $company): bool
    {
        return $this->companies()
            ->where('companies.id', $company->id)
            ->exists();
    }

    public function isMemberOfChannel(Channel $channel): bool
    {
        return $this->channels()
            ->where('channels.id', $channel->id)
            ->exists();
    }

    // ========== Accessors ==========

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            return asset('storage/' . $this->avatar_path);
        }
        
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=mp&s=200";
    }
}
```

**Unit Test:** `tests/Unit/Models/UserTest.php`
```php
<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_own_companies(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($user->ownedCompanies->contains($company));
    }

    public function test_is_admin_of_returns_true_for_admin(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id, ['role' => 'admin']);

        $this->assertTrue($user->isAdminOf($company));
    }

    public function test_is_admin_of_returns_false_for_user(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id, ['role' => 'user']);

        $this->assertFalse($user->isAdminOf($company));
    }

    public function test_is_member_of_returns_true_when_member(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id);

        $this->assertTrue($user->isMemberOf($company));
    }

    public function test_is_member_of_returns_false_when_not_member(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->assertFalse($user->isMemberOf($company));
    }

    public function test_avatar_url_returns_gravatar_when_no_avatar(): void
    {
        $user = User::factory()->create(['avatar_path' => null]);

        $this->assertStringContainsString('gravatar.com', $user->avatar_url);
    }

    public function test_avatar_url_returns_storage_path_when_avatar_set(): void
    {
        $user = User::factory()->create(['avatar_path' => 'avatars/test.jpg']);

        $this->assertStringContainsString('storage/avatars/test.jpg', $user->avatar_url);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Alle Relationships definiert
- [ ] Helper-Methoden implementiert
- [ ] Accessor für avatar_url funktioniert
- [ ] Alle Unit Tests grün

---

### 1.4.2 Company Model erstellen
- [ ] **Erledigt**

→ *Abhängig von 1.3.14*

**Durchführung:**
```bash
php artisan make:model Company
```

**Datei:** `app/Models/Company.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'join_password',
        'owner_id',
        'logo_path',
    ];

    protected $hidden = [
        'join_password',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
                
                $originalSlug = $company->slug;
                $counter = 1;
                while (static::where('slug', $company->slug)->exists()) {
                    $company->slug = $originalSlug . '-' . $counter++;
                }
            }
        });
    }

    // ========== Relationships ==========

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'admin');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    // ========== Helper Methods ==========

    public function checkJoinPassword(string $password): bool
    {
        return Hash::check($password, $this->join_password);
    }

    public function addMember(User $user, string $role = 'user'): void
    {
        if (!$this->members()->where('user_id', $user->id)->exists()) {
            $this->members()->attach($user->id, [
                'role' => $role,
                'joined_at' => now(),
            ]);
        }
    }

    public function removeMember(User $user): void
    {
        if ($user->id === $this->owner_id) {
            throw new \Exception('Owner cannot be removed from company');
        }

        $this->members()->detach($user->id);

        $this->channels->each(function ($channel) use ($user) {
            $channel->members()->detach($user->id);
        });
    }

    // ========== Accessors ==========

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? asset('storage/' . $this->logo_path)
            : null;
    }
}
```

**Unit Test:** `tests/Unit/Models/CompanyTest.php`
```php
<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_auto_generated(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Test GmbH',
            'join_password' => Hash::make('secret'),
            'owner_id' => $user->id,
        ]);

        $this->assertEquals('test-gmbh', $company->slug);
    }

    public function test_slug_is_unique_with_counter(): void
    {
        $user = User::factory()->create();
        
        $company1 = Company::create([
            'name' => 'Test GmbH',
            'join_password' => Hash::make('secret'),
            'owner_id' => $user->id,
        ]);
        
        $company2 = Company::create([
            'name' => 'Test GmbH',
            'join_password' => Hash::make('secret'),
            'owner_id' => $user->id,
        ]);

        $this->assertEquals('test-gmbh', $company1->slug);
        $this->assertEquals('test-gmbh-1', $company2->slug);
    }

    public function test_check_join_password_returns_true_for_correct(): void
    {
        $company = Company::factory()->create([
            'join_password' => Hash::make('secret123'),
        ]);

        $this->assertTrue($company->checkJoinPassword('secret123'));
    }

    public function test_check_join_password_returns_false_for_incorrect(): void
    {
        $company = Company::factory()->create([
            'join_password' => Hash::make('secret123'),
        ]);

        $this->assertFalse($company->checkJoinPassword('wrongpassword'));
    }

    public function test_add_member_adds_user(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $company->addMember($user, 'user');

        $this->assertTrue($user->isMemberOf($company));
    }

    public function test_add_member_does_not_duplicate(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $company->addMember($user);
        $company->addMember($user);

        $this->assertEquals(1, $company->members()->count());
    }

    public function test_remove_member_throws_exception_for_owner(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Owner cannot be removed');
        
        $company->removeMember($owner);
    }

    public function test_remove_member_also_removes_from_channels(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['company_id' => $company->id]);

        $company->addMember($user);
        $channel->members()->attach($user->id);

        $this->assertTrue($user->isMemberOfChannel($channel));

        $company->removeMember($user);

        $this->assertFalse($user->fresh()->isMemberOfChannel($channel));
    }
}
```

---

### 1.4.3 Channel Model erstellen
- [ ] **Erledigt**

→ *Abhängig von 1.4.2*

**Durchführung:**
```bash
php artisan make:model Channel
```

**Datei:** `app/Models/Channel.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'is_private',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }

    // ========== Relationships ==========

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_members')
            ->withPivot('added_by', 'joined_at');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ChannelJoinRequest::class);
    }

    public function pendingJoinRequests(): HasMany
    {
        return $this->joinRequests()->where('status', 'pending');
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    // ========== Helper Methods ==========

    public function addMember(User $user, ?User $addedBy = null): void
    {
        if (!$this->members()->where('user_id', $user->id)->exists()) {
            $this->members()->attach($user->id, [
                'added_by' => $addedBy?->id,
                'joined_at' => now(),
            ]);
        }
    }

    public function getRecentMessages(int $days = 3, int $limit = 100)
    {
        return $this->messages()
            ->where('created_at', '>=', now()->subDays($days))
            ->with(['sender:id,username,avatar_path,status', 'reactions'])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}
```

---

### 1.4.4 ChannelJoinRequest Model erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:model ChannelJoinRequest
```

**Datei:** `app/Models/ChannelJoinRequest.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelJoinRequest extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'channel_id',
        'user_id',
        'status',
        'message',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approve(User $reviewer): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->channel->addMember($this->user, $reviewer);
    }

    public function reject(User $reviewer): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }
}
```

---

### 1.4.5 DirectConversation Model erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:model DirectConversation
```

**Datei:** `app/Models/DirectConversation.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DirectConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'user_one_accepted',
        'user_two_accepted',
    ];

    protected function casts(): array
    {
        return [
            'user_one_accepted' => 'boolean',
            'user_two_accepted' => 'boolean',
        ];
    }

    // ========== Relationships ==========

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    // ========== Static Methods ==========

    public static function findOrCreateBetween(User $userA, User $userB): self
    {
        $userOneId = min($userA->id, $userB->id);
        $userTwoId = max($userA->id, $userB->id);

        return self::firstOrCreate(
            [
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ],
            [
                'user_one_accepted' => $userA->id === $userOneId,
                'user_two_accepted' => $userA->id === $userTwoId,
            ]
        );
    }

    // ========== Helper Methods ==========

    public function getOtherUser(User $user): User
    {
        return $user->id === $this->user_one_id
            ? $this->userTwo
            : $this->userOne;
    }

    public function isAccepted(): bool
    {
        return $this->user_one_accepted && $this->user_two_accepted;
    }

    public function acceptBy(User $user): void
    {
        if ($user->id === $this->user_one_id) {
            $this->update(['user_one_accepted' => true]);
        } else {
            $this->update(['user_two_accepted' => true]);
        }
    }

    public function hasUser(User $user): bool
    {
        return $this->user_one_id === $user->id || $this->user_two_id === $user->id;
    }

    public function getRecentMessages(int $days = 3, int $limit = 100)
    {
        return $this->messages()
            ->where('created_at', '>=', now()->subDays($days))
            ->with(['sender:id,username,avatar_path,status', 'reactions'])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}
```

**Unit Test:** `tests/Unit/Models/DirectConversationTest.php`
```php
<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_or_create_normalizes_user_ids(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        
        // Sicherstellen dass wir wissen welche ID größer ist
        $smallerId = min($userA->id, $userB->id);
        $largerId = max($userA->id, $userB->id);

        $conversation = DirectConversation::findOrCreateBetween($userA, $userB);

        $this->assertEquals($smallerId, $conversation->user_one_id);
        $this->assertEquals($largerId, $conversation->user_two_id);
    }

    public function test_find_or_create_does_not_duplicate(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conv1 = DirectConversation::findOrCreateBetween($userA, $userB);
        $conv2 = DirectConversation::findOrCreateBetween($userB, $userA);

        $this->assertEquals($conv1->id, $conv2->id);
        $this->assertEquals(1, DirectConversation::count());
    }

    public function test_initiator_has_accepted_true(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($userA, $userB);

        if ($userA->id < $userB->id) {
            $this->assertTrue($conversation->user_one_accepted);
            $this->assertFalse($conversation->user_two_accepted);
        } else {
            $this->assertFalse($conversation->user_one_accepted);
            $this->assertTrue($conversation->user_two_accepted);
        }
    }

    public function test_is_accepted_only_when_both_true(): void
    {
        $conversation = DirectConversation::factory()->create([
            'user_one_accepted' => true,
            'user_two_accepted' => false,
        ]);

        $this->assertFalse($conversation->isAccepted());

        $conversation->update(['user_two_accepted' => true]);

        $this->assertTrue($conversation->fresh()->isAccepted());
    }

    public function test_get_other_user_returns_correct_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($userA, $userB);

        $this->assertEquals($userB->id, $conversation->getOtherUser($userA)->id);
        $this->assertEquals($userA->id, $conversation->getOtherUser($userB)->id);
    }
}
```

---

### 1.4.6 Message Model erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:model Message
```

**Datei:** `app/Models/Message.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'messageable_type',
        'messageable_id',
        'sender_id',
        'content',
        'content_iv',
        'content_type',
        'parent_id',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    // ========== Relationships ==========

    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function file(): HasOne
    {
        return $this->hasOne(File::class);
    }

    // ========== Helper Methods ==========

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function edit(string $newContent, ?string $newIv = null): void
    {
        $this->update([
            'content' => $newContent,
            'content_iv' => $newIv ?? $this->content_iv,
            'edited_at' => now(),
        ]);
    }
}
```

---

### 1.4.7 MessageReaction Model erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:model MessageReaction
```

**Datei:** `app/Models/MessageReaction.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

### 1.4.8 File Model erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:model File
```

**Datei:** `app/Models/File.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'uploader_id',
        'fileable_type',
        'fileable_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
        'file_path',
        'thumbnail_path',
        'is_compressed',
        'original_size',
    ];

    protected function casts(): array
    {
        return [
            'is_compressed' => 'boolean',
            'file_size' => 'integer',
            'original_size' => 'integer',
        ];
    }

    // ========== Relationships ==========

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    // ========== Accessors ==========

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path
            ? Storage::disk('public')->url($this->thumbnail_path)
            : null;
    }

    public function getHumanFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getExtensionAttribute(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }

    // ========== Helper Methods ==========

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
```

---

### 1.4.9 ReadReceipt Model erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:model ReadReceipt
```

**Datei:** `app/Models/ReadReceipt.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReadReceipt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'messageable_type',
        'messageable_id',
        'last_read_message_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }

    public static function updateFor(User $user, $messageable, Message $message): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $user->id,
                'messageable_type' => $messageable->getMorphClass(),
                'messageable_id' => $messageable->id,
            ],
            [
                'last_read_message_id' => $message->id,
                'read_at' => now(),
            ]
        );
    }
}
```

---

### 1.4.10 Morph Map registrieren
- [ ] **Erledigt**

→ *Abhängig von 1.4.1 bis 1.4.9*

**Datei bearbeiten:** `app/Providers/AppServiceProvider.php`
```php
<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'channel' => \App\Models\Channel::class,
            'direct' => \App\Models\DirectConversation::class,
            'user' => \App\Models\User::class,
        ]);
    }
}
```

**Akzeptanzkriterien:**
- [ ] `messageable_type` speichert 'channel' statt 'App\Models\Channel'

---

### 1.4.11 Model Factories erstellen
- [ ] **Erledigt**

→ *Abhängig von 1.4.1 bis 1.4.9*

**Durchführung:**
```bash
php artisan make:factory CompanyFactory
php artisan make:factory ChannelFactory
php artisan make:factory ChannelJoinRequestFactory
php artisan make:factory DirectConversationFactory
php artisan make:factory MessageFactory
php artisan make:factory MessageReactionFactory
php artisan make:factory FileFactory
php artisan make:factory ReadReceiptFactory
```

**Datei:** `database/factories/CompanyFactory.php`
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => null,
            'join_password' => Hash::make('password'),
            'owner_id' => User::factory(),
            'logo_path' => null,
        ];
    }
}
```

**Datei:** `database/factories/ChannelFactory.php`
```php
<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_private' => true,
            'created_by' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => false,
        ]);
    }
}
```

**Datei:** `database/factories/DirectConversationFactory.php`
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DirectConversationFactory extends Factory
{
    public function definition(): array
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();
        
        return [
            'user_one_id' => min($userOne->id, $userTwo->id),
            'user_two_id' => max($userOne->id, $userTwo->id),
            'user_one_accepted' => false,
            'user_two_accepted' => false,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);
    }
}
```

**Datei:** `database/factories/MessageFactory.php`
```php
<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'messageable_type' => 'channel',
            'messageable_id' => Channel::factory(),
            'sender_id' => User::factory(),
            'content' => fake()->paragraph(),
            'content_iv' => null,
            'content_type' => 'text',
            'parent_id' => null,
            'edited_at' => null,
        ];
    }
}
```

**Akzeptanzkriterien:**
- [ ] Alle Factories erstellt
- [ ] `Model::factory()->create()` funktioniert für alle Models

---

## 1.5 Services [BE]

### 1.5.1 ImageCompressionService erstellen
- [ ] **Erledigt**

→ *Abhängig von 1.2.5*

**Datei erstellen:** `app/Services/ImageCompressionService.php`
```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageCompressionService
{
    private bool $enabled;

    public function __construct()
    {
        $apiKey = config('services.tinypng.api_key');
        $this->enabled = !empty($apiKey);

        if ($this->enabled) {
            \Tinify\setKey($apiKey);
        }
    }

    public function compress(string $sourcePath, ?string $destinationPath = null): array
    {
        $destinationPath = $destinationPath ?? $sourcePath;
        $originalSize = filesize($sourcePath);

        if (!$this->enabled) {
            Log::warning('TinyPNG API key not configured, skipping compression');
            return [
                'success' => false,
                'original_size' => $originalSize,
                'compressed_size' => $originalSize,
                'saved_bytes' => 0,
                'error' => 'API key not configured',
            ];
        }

        try {
            $source = \Tinify\fromFile($sourcePath);
            $source->toFile($destinationPath);

            $compressedSize = filesize($destinationPath);

            Log::info('Image compressed successfully', [
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'saved_percent' => round((1 - $compressedSize / $originalSize) * 100, 2),
            ]);

            return [
                'success' => true,
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'saved_bytes' => $originalSize - $compressedSize,
            ];
        } catch (\Tinify\AccountException $e) {
            Log::error('TinyPNG account error', ['message' => $e->getMessage()]);
            return $this->errorResponse($originalSize, 'Account limit reached or invalid API key');
        } catch (\Tinify\ClientException $e) {
            Log::error('TinyPNG client error', ['message' => $e->getMessage()]);
            return $this->errorResponse($originalSize, 'Invalid image or unsupported format');
        } catch (\Exception $e) {
            Log::error('TinyPNG error', ['message' => $e->getMessage()]);
            return $this->errorResponse($originalSize, $e->getMessage());
        }
    }

    public function compressUploadedFile(
        UploadedFile $file,
        string $storagePath,
        string $disk = 'public'
    ): array {
        $tempPath = $file->store('temp', 'local');
        $fullTempPath = Storage::disk('local')->path($tempPath);

        $result = $this->compress($fullTempPath);

        $finalPath = Storage::disk($disk)->putFileAs(
            dirname($storagePath),
            new \Illuminate\Http\File($fullTempPath),
            basename($storagePath)
        );

        Storage::disk('local')->delete($tempPath);

        return array_merge($result, [
            'path' => $finalPath,
            'url' => Storage::disk($disk)->url($finalPath),
        ]);
    }

    public function isCompressible(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/webp',
        ]);
    }

    public function getCompressionCount(): ?int
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            \Tinify\validate();
            return \Tinify\compressionCount();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function errorResponse(int $originalSize, string $error): array
    {
        return [
            'success' => false,
            'original_size' => $originalSize,
            'compressed_size' => $originalSize,
            'saved_bytes' => 0,
            'error' => $error,
        ];
    }
}
```

---

### 1.5.2 Service registrieren
- [ ] **Erledigt**

→ *Abhängig von 1.5.1*

**Datei bearbeiten:** `app/Providers/AppServiceProvider.php`
```php
use App\Services\ImageCompressionService;

public function register(): void
{
    $this->app->singleton(ImageCompressionService::class);
}
```

**Unit Test:** `tests/Unit/Services/ImageCompressionServiceTest.php`
```php
<?php

namespace Tests\Unit\Services;

use App\Services\ImageCompressionService;
use Tests\TestCase;

class ImageCompressionServiceTest extends TestCase
{
    public function test_is_compressible_returns_true_for_supported_types(): void
    {
        $service = app(ImageCompressionService::class);

        $this->assertTrue($service->isCompressible('image/png'));
        $this->assertTrue($service->isCompressible('image/jpeg'));
        $this->assertTrue($service->isCompressible('image/webp'));
    }

    public function test_is_compressible_returns_false_for_unsupported_types(): void
    {
        $service = app(ImageCompressionService::class);

        $this->assertFalse($service->isCompressible('image/gif'));
        $this->assertFalse($service->isCompressible('application/pdf'));
        $this->assertFalse($service->isCompressible('text/plain'));
    }

    public function test_compress_returns_error_without_api_key(): void
    {
        config(['services.tinypng.api_key' => null]);
        $service = new ImageCompressionService();

        // Erstelle temporäre Testdatei
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $result = $service->compress($tempFile);

        $this->assertFalse($result['success']);
        $this->assertEquals('API key not configured', $result['error']);

        unlink($tempFile);
    }
}
```

---

## 1.6 API Authentication [BE]

### 1.6.1 AuthController erstellen
- [ ] **Erledigt**

→ *Abhängig von 1.4.1*

**Durchführung:**
```bash
php artisan make:controller Api/AuthController
```

**Datei:** `app/Http/Controllers/Api/AuthController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'username' => ['required', 'string', 'min:3', 'max:100'],
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'username' => $validated['username'],
            'status' => 'available',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $this->formatUser($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();
        $user->update(['status' => 'available']);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['status' => 'offline']);
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'status_text' => $user->status_text,
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
```

---

### 1.6.2 UserController erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:controller Api/UserController
```

**Datei:** `app/Http/Controllers/Api/UserController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(
        private ImageCompressionService $imageService
    ) {}

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['sometimes', 'string', 'min:3', 'max:100'],
            'status_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['required', 'in:available,busy,away,offline'],
            'status_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Status updated',
            'status' => $user->status,
            'status_text' => $user->status_text,
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $filename = 'avatars/' . $user->id . '_' . Str::random(10) . '.' . $file->extension();

        if ($this->imageService->isCompressible($file->getMimeType())) {
            $result = $this->imageService->compressUploadedFile($file, $filename, 'public');
            $path = $result['path'];
        } else {
            $path = $file->storeAs('avatars', basename($filename), 'public');
        }

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'message' => 'Avatar uploaded',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json([
            'message' => 'Avatar deleted',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $query = $validated['q'];
        $currentUserId = $request->user()->id;

        $users = User::where('id', '!=', $currentUserId)
            ->where(function ($q) use ($query) {
                $q->where('username', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(20)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'status' => $user->status,
            ]);

        return response()->json([
            'users' => $users,
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'status_text' => $user->status_text,
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
```

---

### 1.6.3 API Routes definieren
- [ ] **Erledigt**

→ *Abhängig von 1.6.1, 1.6.2*

**Datei:** `routes/api.php`
```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::prefix('user')->group(function () {
        Route::put('profile', [UserController::class, 'updateProfile']);
        Route::put('status', [UserController::class, 'updateStatus']);
        Route::post('avatar', [UserController::class, 'uploadAvatar']);
        Route::delete('avatar', [UserController::class, 'deleteAvatar']);
    });

    Route::get('users/search', [UserController::class, 'search']);
});
```

---

### 1.6.4 CORS konfigurieren
- [ ] **Erledigt**

**Datei:** `config/cors.php`
```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:3000',
        'app://*',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

---

### 1.6.5 Auth Feature Tests erstellen
- [ ] **Erledigt**

**Datei:** `tests/Feature/Api/Auth/RegisterTest.php`
```php
<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'username' => 'TestUser',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'email', 'username', 'avatar_url', 'status'],
                'token',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'username' => 'TestUser',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_with_weak_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'username' => 'TestUser',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_fails_without_username(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }
}
```

**Datei:** `tests/Feature/Api/Auth/LoginTest.php`
```php
<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_sets_status_to_available(): void
    {
        $user = User::factory()->create([
            'status' => 'offline',
            'password' => Hash::make('Password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ]);

        $this->assertEquals('available', $user->fresh()->status);
    }
}
```

**Datei:** `tests/Feature/Api/Auth/LogoutTest.php`
```php
<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['status' => 'available']);

        $response = $this->actingAs($user)
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logout successful']);
        
        $this->assertEquals('offline', $user->fresh()->status);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}
```

---

## 1.7 Phase 1 Abschluss [TEST]

### 1.7.1 Alle Tests ausführen
- [ ] **Erledigt**

→ *Abhängig von allen vorherigen Tasks*

**Durchführung:**
```bash
cd backend
php artisan test
```

**Akzeptanzkriterien:**
- [ ] Alle Tests grün
- [ ] Keine Skipped oder Risky Tests
- [ ] Mindestens 30 Tests

---

### 1.7.2 API manuell testen
- [ ] **Erledigt**

**Durchführung:**
```bash
# Server starten
php artisan serve

# In neuem Terminal:

# 1. Registrierung
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"test@test.de","password":"Password123","password_confirmation":"Password123","username":"TestUser"}'

# Token aus Response kopieren
# TOKEN="1|xxx..."

# 2. Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.de","password":"Password123"}'

# 3. Me Endpoint (Token einsetzen)
curl http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# 4. Status ändern
curl -X PUT http://localhost:8000/api/user/status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"busy","status_text":"Im Meeting"}'
```

**Akzeptanzkriterien:**
- [ ] Registrierung gibt 201 mit Token
- [ ] Login gibt 200 mit Token
- [ ] Me gibt User-Daten
- [ ] Status-Update funktioniert

---

### 1.7.3 Code Cleanup
- [ ] **Erledigt**

**Checkliste:**
- [ ] Keine `dd()` oder `dump()` Statements
- [ ] Keine auskommentierten Code-Blöcke
- [ ] Alle Klassen haben Docblocks wo nötig
- [ ] `php artisan ide-helper:models --write` ausgeführt

---

### 1.7.4 Git Commit & Tag
- [ ] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 1: Backend Foundation - Auth, Users, Database Schema"
git tag v0.1.0
```

**Akzeptanzkriterien:**
- [ ] Alles committed
- [ ] Tag v0.1.0 erstellt
- [ ] `git status` zeigt "nothing to commit"

---

## Phase 1 Zusammenfassung

### Erstellte Dateien
- 13 Migrations
- 9 Models + 8 Factories
- 2 Controllers (AuthController, UserController)
- 1 Service (ImageCompressionService)
- ~15 Test-Dateien

### Verfügbare API Endpoints
| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| POST | /api/auth/register | Registrierung |
| POST | /api/auth/login | Login |
| POST | /api/auth/logout | Logout |
| POST | /api/auth/refresh | Token erneuern |
| GET | /api/auth/me | Aktueller User |
| PUT | /api/user/profile | Profil ändern |
| PUT | /api/user/status | Status ändern |
| POST | /api/user/avatar | Avatar hochladen |
| DELETE | /api/user/avatar | Avatar löschen |
| GET | /api/users/search | User suchen |

### Nächste Phase
→ Weiter mit `phase-2-firmen.md`
