# TeamChat API Documentation

## API Documentation Setup

Die TeamChat API ist automatisch dokumentiert und kann über Swagger/OpenAPI eingesehen werden.

### Installation (Optional)

Für automatische API-Dokumentation kann das Package `dedoc/scramble` installiert werden:

```bash
cd backend
composer require dedoc/scramble
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider"
```

Nach der Installation ist die API-Dokumentation unter folgender URL verfügbar:
```
https://api.teamchat.example.com/docs/api
```

## API Base URL

**Production:** `https://api.teamchat.example.com/api`
**Development:** `http://localhost:8000/api`

## Authentication

Alle geschützten Endpoints benötigen einen Bearer Token im Authorization Header:

```
Authorization: Bearer {token}
```

Token wird beim Login erhalten:
```bash
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "password"
}
```

## Main Endpoints

### Authentication
- `POST /api/auth/register` - Registrierung
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Aktueller User

### Companies
- `GET /api/my-companies` - Meine Firmen
- `POST /api/companies` - Firma erstellen
- `GET /api/companies/{id}` - Firma Details
- `POST /api/companies/{id}/join` - Firma beitreten

### Channels
- `GET /api/companies/{id}/channels` - Channels einer Firma
- `POST /api/companies/{id}/channels` - Channel erstellen
- `GET /api/channels/{id}` - Channel Details
- `GET /api/channels/{id}/messages` - Channel-Nachrichten
- `POST /api/channels/{id}/messages` - Nachricht senden

### Direct Messages
- `GET /api/conversations` - Meine Konversationen
- `POST /api/conversations` - Konversation starten
- `GET /api/conversations/{id}/messages` - DM-Nachrichten
- `POST /api/conversations/{id}/messages` - DM senden

### Files
- `POST /api/channels/{id}/files` - Datei in Channel hochladen
- `POST /api/conversations/{id}/files` - Datei in DM hochladen
- `GET /api/channels/{id}/files` - Channel-Dateien
- `GET /api/files/{id}/download` - Datei herunterladen

### Reactions
- `GET /api/emojis` - Verfügbare Emojis
- `POST /api/messages/{id}/reactions` - Reaction hinzufügen
- `POST /api/messages/{id}/reactions/toggle` - Reaction toggle
- `DELETE /api/messages/{id}/reactions/{emoji}` - Reaction entfernen

## Response Format

### Success Response
```json
{
  "message": "Success message",
  "data": { ... }
}
```

### Error Response
```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

## HTTP Status Codes

- `200` - OK
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

## Rate Limiting

| Endpoint Type | Limit |
|---------------|-------|
| Authentication | 5 req/min |
| API Standard | 60 req/min |
| Messages | 30 req/min |
| File Uploads | 50 req/hour |

## WebSocket Events

TeamChat verwendet Laravel Reverb für Real-Time Updates:

### Private Channel Events
- `message.created` - Neue Nachricht
- `message.updated` - Nachricht bearbeitet
- `message.deleted` - Nachricht gelöscht
- `reaction.added` - Reaction hinzugefügt
- `typing` - User tippt

### Presence Channel Events
- `user.online` - User online
- `user.offline` - User offline
- `user.status.updated` - Status geändert

## Examples

### Login
```bash
curl -X POST https://api.teamchat.example.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

### Send Message
```bash
curl -X POST https://api.teamchat.example.com/api/channels/1/messages \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"content":"Hello World!"}'
```

### Upload File
```bash
curl -X POST https://api.teamchat.example.com/api/channels/1/files \
  -H "Authorization: Bearer {token}" \
  -F "file=@/path/to/file.pdf"
```

## Health Check

```bash
curl https://api.teamchat.example.com/api/health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2024-12-15T14:30:00Z",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "storage": "ok",
    "queue": "ok (pending: 0)"
  },
  "version": "1.0.0"
}
```

## Support

Bei Fragen zur API:
- GitHub Issues: https://github.com/your-org/teamchat/issues
- API Docs: https://api.teamchat.example.com/docs/api
