# CLAUDE.md - TeamChat Entwicklungsanleitung

> Diese Datei dient als Anleitung für Claude Code, um das TeamChat-Projekt strukturiert und phasenweise zu entwickeln.

## 🎯 Projektübersicht

**TeamChat** ist eine Microsoft Teams Alternative mit:
- Laravel 12 Backend (PHP 8.3)
- Vue 3 + Electron Frontend
- WebSocket Real-Time (Laravel Reverb)
- AES-256 Nachrichtenverschlüsselung

## 📁 Dokumentationsstruktur

```
teamchat-docs/
├── CLAUDE.md              ← DU BIST HIER
├── README.md              ← Projektübersicht
├── phase-1-fundament.md   ← Laravel Setup, Auth, User
├── phase-2-firmen.md      ← Companies, Channels
├── phase-3-chat.md        ← WebSocket, Messages
├── phase-4-direct-messages.md ← DM-System
├── phase-5-emojis.md      ← Reactions
├── phase-6-files.md       ← File Upload
├── phase-7-frontend-setup.md ← Electron, Vue, Pinia
├── phase-8-frontend-ui.md ← UI-Komponenten
└── phase-9-deployment.md  ← Docker, CI/CD
```

## ⚠️ WICHTIGE REGELN

### 1. Phasen-Reihenfolge einhalten
```
Phase 1 → Phase 2 → Phase 3 → ... → Phase 9
```
**NIEMALS** eine Phase überspringen! Jede Phase baut auf der vorherigen auf.

### 2. Tasks innerhalb einer Phase
Jede Phase enthält nummerierte Tasks (z.B. `5.1.1`, `5.1.2`). Diese **in Reihenfolge** abarbeiten.

### 3. Checkbox-System
In den Phasendateien gibt es Checkboxen:
```markdown
- [ ] **Erledigt**  ← Noch offen
- [x] **Erledigt**  ← Abgeschlossen
```
Nach Abschluss eines Tasks die Checkbox auf `[x]` setzen.

### 4. Git Commits
Nach **jeder abgeschlossenen Task-Gruppe** committen:
```bash
git add .
git commit -m "Phase X.Y: Kurzbeschreibung"
```

Nach **jeder abgeschlossenen Phase** taggen:
```bash
git tag v0.X.0
```

### 5. Tests zuerst
Bevor du zur nächsten Task gehst:
1. Tests schreiben (wie in der Doku)
2. Tests ausführen
3. Alle Tests müssen grün sein

---

## 🚀 Wie du eine Phase abarbeitest

### Schritt 1: Phase-Datei öffnen
```bash
cat teamchat-docs/phase-X-name.md
```

### Schritt 2: Erste offene Task finden
Suche nach `- [ ] **Erledigt**`

### Schritt 3: Code implementieren
Kopiere den Code aus der Dokumentation und passe ihn an.

### Schritt 4: Tests ausführen
```bash
# Backend
cd backend && php artisan test

# Frontend
cd frontend && npm run test:unit
```

### Schritt 5: Checkbox aktualisieren
Markiere die Task als erledigt: `- [x] **Erledigt**`

### Schritt 6: Commit
```bash
git add .
git commit -m "Phase X.Y.Z: Task-Beschreibung"
```

### Schritt 7: Nächste Task
Wiederhole ab Schritt 2.

---

## 📋 Aktueller Fortschritt

### Phase 1: Fundament [Backend]
- Status: ⬜ Nicht begonnen
- Datei: `phase-1-fundament.md`
- Inhalt: Laravel Setup, Migrations, Auth, User-System
- Geschätzte Zeit: 2 Wochen

### Phase 2: Firmen-System [Backend]
- Status: ⬜ Nicht begonnen
- Datei: `phase-2-firmen.md`
- Inhalt: Companies, Channels, Mitgliederverwaltung
- Geschätzte Zeit: 2 Wochen
- Abhängig von: Phase 1

### Phase 3: Real-Time Chat [Backend]
- Status: ⬜ Nicht begonnen
- Datei: `phase-3-chat.md`
- Inhalt: WebSocket, Verschlüsselung, Messages
- Geschätzte Zeit: 3 Wochen
- Abhängig von: Phase 2

### Phase 4: Direct Messages [Backend]
- Status: ⬜ Nicht begonnen
- Datei: `phase-4-direct-messages.md`
- Inhalt: DM-System mit Request-Flow
- Geschätzte Zeit: 2 Wochen
- Abhängig von: Phase 3

### Phase 5: Emojis & Reactions [Backend]
- Status: ⬜ Nicht begonnen
- Datei: `phase-5-emojis.md`
- Inhalt: Reactions, EmojiService
- Geschätzte Zeit: 1 Woche
- Abhängig von: Phase 4

### Phase 6: Datei-System [Backend]
- Status: ⬜ Nicht begonnen
- Datei: `phase-6-files.md`
- Inhalt: File Upload, Thumbnails, TinyPNG
- Geschätzte Zeit: 2 Wochen
- Abhängig von: Phase 5

### Phase 7: Frontend Setup [Frontend]
- Status: ⬜ Nicht begonnen
- Datei: `phase-7-frontend-setup.md`
- Inhalt: Electron, Vue 3, Pinia, WebSocket
- Geschätzte Zeit: 3 Wochen
- Abhängig von: Phase 6

### Phase 8: Frontend UI [Frontend]
- Status: ⬜ Nicht begonnen
- Datei: `phase-8-frontend-ui.md`
- Inhalt: Alle UI-Komponenten, Views
- Geschätzte Zeit: 4 Wochen
- Abhängig von: Phase 7

### Phase 9: Deployment [Infra]
- Status: ⬜ Nicht begonnen
- Datei: `phase-9-deployment.md`
- Inhalt: Docker, CI/CD, Backup, Monitoring
- Geschätzte Zeit: 2 Wochen
- Abhängig von: Phase 8

---

## 🛠️ Technologie-Stack

### Backend
- **Framework:** Laravel 12
- **PHP:** 8.3+
- **Datenbank:** MySQL 8.0
- **Cache/Queue:** Redis
- **WebSocket:** Laravel Reverb
- **Auth:** Laravel Sanctum
- **Bildkomprimierung:** TinyPNG API

### Frontend
- **Framework:** Vue 3 + TypeScript
- **Desktop:** Electron
- **State:** Pinia
- **Router:** Vue Router 4
- **Styling:** TailwindCSS
- **Icons:** Heroicons
- **UI:** HeadlessUI

### Infrastruktur
- **Container:** Docker
- **Reverse Proxy:** Traefik
- **SSL:** Let's Encrypt
- **CI/CD:** GitHub Actions

---

## 📝 Code-Konventionen

### PHP/Laravel
```php
// Controller-Methoden: camelCase
public function sendMessage(Request $request): JsonResponse

// Models: PascalCase, Singular
class Message extends Model

// Migrations: snake_case, Plural
create_messages_table

// Tests: test_ Prefix oder @test Annotation
public function test_user_can_send_message(): void
```

### TypeScript/Vue
```typescript
// Komponenten: PascalCase
MessageItem.vue

// Composables: use Prefix
useAuth(), useChat()

// Stores: use Prefix + Store Suffix
useAuthStore, useChatStore

// Props/Events: camelCase
@click="handleClick"
:is-loading="loading"
```

### Dateistruktur Backend
```
backend/
├── app/
│   ├── Http/Controllers/Api/    # API Controller
│   ├── Models/                  # Eloquent Models
│   ├── Services/                # Business Logic
│   └── Events/                  # Broadcast Events
├── database/
│   ├── migrations/              # Schema Migrations
│   └── factories/               # Test Factories
├── routes/
│   ├── api.php                  # API Routes
│   └── channels.php             # WebSocket Auth
└── tests/
    ├── Feature/Api/             # API Tests
    └── Unit/                    # Unit Tests
```

### Dateistruktur Frontend
```
frontend/
├── electron/                    # Electron Main Process
├── src/
│   ├── components/
│   │   ├── common/              # Button, Input, Modal...
│   │   ├── chat/                # Message, Input...
│   │   └── layout/              # Sidebars, Header...
│   ├── views/                   # Route Views
│   ├── stores/                  # Pinia Stores
│   ├── services/                # API, WebSocket
│   └── types/                   # TypeScript Types
└── tests/                       # Vitest Tests
```

---

## 🔧 Häufige Befehle

### Backend
```bash
# Laravel installieren
composer install

# Migrations ausführen
php artisan migrate

# Tests ausführen
php artisan test

# Einzelne Test-Klasse
php artisan test --filter=CreateCompanyTest

# Artisan Befehle
php artisan make:controller Api/NewController
php artisan make:model NewModel -mf  # mit Migration und Factory
php artisan make:event NewEvent

# Cache leeren
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Frontend
```bash
# Dependencies installieren
npm install

# Dev Server
npm run dev

# Electron Dev
npm run electron:dev

# Tests
npm run test:unit

# Build
npm run build
npm run electron:build
```

### Git
```bash
# Neuer Feature-Branch
git checkout -b feature/phase-X-task-name

# Commit
git add .
git commit -m "Phase X.Y: Beschreibung"

# Push
git push origin feature/phase-X-task-name

# Tag nach Phase-Abschluss
git tag v0.X.0
git push --tags
```

---

## ⚡ Quick Start für neue Session

Wenn du eine neue Session startest:

1. **Lies diese Datei** (CLAUDE.md)
2. **Prüfe den Fortschritt** (Checkboxen in den Phase-Dateien)
3. **Finde die aktuelle Task** (erste mit `- [ ]`)
4. **Lies die Task-Beschreibung** in der Phase-Datei
5. **Implementiere und teste**
6. **Markiere als erledigt** und committe

---

## 🚨 Wichtige Hinweise

### Verschlüsselung
- **APP_CIPHER_KEY** muss in `.env` gesetzt sein
- Nachrichten werden mit AES-256-CBC verschlüsselt
- IV wird pro Nachricht generiert und in `content_iv` gespeichert

### WebSocket
- Laravel Reverb läuft auf Port 8080
- Private Channels: `private-channel.{id}`, `private-conversation.{id}`
- Presence Channel: `presence-company.{id}`

### Dateien
- Max. 50MB für Dateien, 10MB für Bilder
- Bilder werden automatisch mit TinyPNG komprimiert
- Thumbnails: 300x300px

### Tests
- Mindestens 80% Code Coverage anstreben
- Jeder Endpoint braucht mindestens einen Test
- Factory für jedes Model erstellen

---

## 📞 Bei Problemen

1. **Lies die Fehlermeldung** genau
2. **Prüfe die Logs:** `storage/logs/laravel.log`
3. **Suche in der Dokumentation** der jeweiligen Phase
4. **Teste isoliert** mit `php artisan tinker`

---

## ✅ Checkliste vor Phasen-Abschluss

- [ ] Alle Tasks der Phase abgehakt
- [ ] Alle Tests grün (`php artisan test`)
- [ ] Code committed
- [ ] Phase-Tag gesetzt (`git tag v0.X.0`)
- [ ] README/Changelog aktualisiert (optional)

---

**Viel Erfolg beim Entwickeln! 🚀**
