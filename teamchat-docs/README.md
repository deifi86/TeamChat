# TeamChat - Entwicklungsplan

## Projektübersicht

**Name:** TeamChat
**Ziel:** Schlanke Microsoft Teams Alternative für ~20 User in 5 Firmen

**Tech Stack:**
- Frontend: Electron + Vue 3 + Vite + TypeScript
- Backend: Laravel 12 API (PHP 8.2+)
- Datenbank: MySQL 8
- Real-Time: Laravel Reverb (WebSockets)
- Entwicklung: Docker Desktop

---

## Phasen-Übersicht

| Phase | Name | Dauer | Dokument |
|-------|------|-------|----------|
| 1 | Fundament | Woche 1-2 | `phase-1-fundament.md` |
| 2 | Firmen-System | Woche 3-4 | `phase-2-firmen.md` |
| 3 | Real-Time Chat | Woche 5-7 | `phase-3-chat.md` |
| 4 | Direct Messages | Woche 8-9 | `phase-4-direct-messages.md` |
| 5 | Emojis & Reaktionen | Woche 10 | `phase-5-emojis.md` |
| 6 | Datei-System | Woche 11-13 | `phase-6-files.md` |
| 7 | Frontend Setup | Woche 14-16 | `phase-7-frontend-setup.md` |
| 8 | Frontend UI | Woche 17-20 | `phase-8-frontend-ui.md` |
| 9 | Polish & Deployment | Woche 21-24 | `phase-9-deployment.md` |

---

## Legende (gilt für alle Phasen-Dokumente)

- **[BE]** = Backend-Task (Laravel)
- **[FE]** = Frontend-Task (Electron/Vue)
- **[INFRA]** = Infrastruktur/Setup
- **[TEST]** = Test-Task
- **→** = Abhängigkeit (muss vorher erledigt sein)
- **[ ]** = Offener Task
- **[x]** = Erledigter Task

---

## Konventionen

### API Response Format
```json
{
    "message": "Human readable message",
    "data_key": { ... },
    "errors": { ... }
}
```

### HTTP Status Codes
- 200: Erfolg
- 201: Erstellt
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error

### Git Workflow
- `main` – Produktiv
- `develop` – Entwicklung
- `feature/{name}` – Feature-Branches
- `fix/{name}` – Bugfix-Branches

### Test-Namenskonvention
- Feature Tests: `tests/Feature/Api/{Resource}/{Action}Test.php`
- Unit Tests: `tests/Unit/{Type}/{Class}Test.php`

---

## Projektstruktur

```
teamchat/
├── backend/                 # Laravel 12 API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Events/
│   ├── database/migrations/
│   ├── routes/api.php
│   └── tests/
├── frontend/                # Electron + Vue
│   ├── src/
│   │   ├── components/
│   │   ├── views/
│   │   ├── stores/
│   │   └── services/
│   └── electron/
├── docker/
├── docs/
│   ├── phase-1-fundament.md
│   ├── phase-2-firmen.md
│   └── ...
└── docker-compose.yml
```

---

## Fortschritt

### Phase 1: Fundament
- [ ] 1.1 Entwicklungsumgebung
- [ ] 1.2 Laravel Backend Setup
- [ ] 1.3 Datenbank-Schema
- [ ] 1.4 Eloquent Models
- [ ] 1.5 Services
- [ ] 1.6 API Authentication
- [ ] 1.7 Tests & Abschluss

### Phase 2: Firmen-System
- [ ] 2.1 Company Controller
- [ ] 2.2 Channel Controller
- [ ] 2.3 Routes & Tests

### Phase 3: Real-Time Chat
- [ ] 3.1 WebSocket Setup
- [ ] 3.2 Encryption Service
- [ ] 3.3 Message Events
- [ ] 3.4 Message Controller
- [ ] 3.5 Tests

### Phase 4: Direct Messages
- [ ] 4.1 Conversation Controller
- [ ] 4.2 Chat Request Flow
- [ ] 4.3 Tests

### Phase 5: Emojis & Reaktionen
- [ ] 5.1 Reaction Endpoints
- [ ] 5.2 Emoji in Messages
- [ ] 5.3 Tests

### Phase 6: Datei-System
- [ ] 6.1 File Upload Service
- [ ] 6.2 File Controller
- [ ] 6.3 File Browser API
- [ ] 6.4 Tests

### Phase 7: Frontend Setup
- [ ] 7.1 Electron + Vue Setup
- [ ] 7.2 Pinia Stores
- [ ] 7.3 API Service Layer
- [ ] 7.4 Router & Auth Flow

### Phase 8: Frontend UI
- [ ] 8.1 Layout & Navigation
- [ ] 8.2 Auth Views
- [ ] 8.3 Company Views
- [ ] 8.4 Chat Views
- [ ] 8.5 File Browser

### Phase 9: Polish & Deployment
- [ ] 9.1 Read Receipts & Status
- [ ] 9.2 Notifications
- [ ] 9.3 Server Setup
- [ ] 9.4 CI/CD & Builds
