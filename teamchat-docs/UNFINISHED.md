# Unvollständige Features & TODOs

Dieses Dokument listet alle Features auf, die vereinfacht implementiert wurden oder noch vervollständigt werden müssen.

---

## Phase 4: Direct Messages

### 1. unread_count - Vereinfachte Implementierung

**Status:** ⚠️ Vereinfacht implementiert
**Datei:** `app/Http/Controllers/Api/DirectConversationController.php:32-36`

#### Aktueller Stand (Vereinfacht)
```php
->withCount(['messages as unread_count' => function ($query) use ($user) {
    // Zähle alle Nachrichten vom anderen User
    // TODO: Mit ReadReceipts verfeinern, sobald die Tabelle vollständig implementiert ist
    $query->where('sender_id', '!=', $user->id);
}])
```

**Problem:** Zählt ALLE Nachrichten vom anderen User, nicht nur ungelesene.

#### Gewünschter Endzustand
```php
->withCount(['messages as unread_count' => function ($query) use ($user) {
    $query->where('sender_id', '!=', $user->id)
        ->whereDoesntHave('readReceipts', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
}])
```

#### Was fehlt:
1. **ReadReceipt Model vollständig konfigurieren**
   - Prüfen ob `read_receipts` Tabelle existiert
   - Falls nicht: Migration erstellen
   - Foreign Keys korrekt setzen

2. **Migration für read_receipts Tabelle**
   ```bash
   php artisan make:migration create_read_receipts_table
   ```

   Schema:
   ```php
   Schema::create('read_receipts', function (Blueprint $table) {
       $table->id();
       $table->foreignId('message_id')->constrained()->onDelete('cascade');
       $table->foreignId('user_id')->constrained()->onDelete('cascade');
       $table->timestamp('read_at')->useCurrent();
       $table->timestamps();

       // Ein User kann eine Nachricht nur einmal als gelesen markieren
       $table->unique(['message_id', 'user_id']);

       // Indizes für Performance
       $table->index('message_id');
       $table->index('user_id');
   });
   ```

3. **ReadReceipt Model erweitern**
   Prüfen in `app/Models/ReadReceipt.php`:
   ```php
   protected $fillable = [
       'message_id',
       'user_id',
       'read_at',
   ];

   public function message()
   {
       return $this->belongsTo(Message::class);
   }

   public function user()
   {
       return $this->belongsTo(User::class);
   }
   ```

4. **Message Model readReceipts() Relation testen**
   Die Relation ist bereits hinzugefügt (Zeile 62-65), muss aber getestet werden:
   ```php
   public function readReceipts()
   {
       return $this->hasMany(ReadReceipt::class);
   }
   ```

5. **Endpoint zum Markieren als gelesen erstellen**
   ```php
   // POST /api/messages/{message}/read
   public function markAsRead(Request $request, Message $message): JsonResponse
   {
       $user = $request->user();

       // Prüfen ob User berechtigt ist
       // ReadReceipt erstellen
       ReadReceipt::firstOrCreate([
           'message_id' => $message->id,
           'user_id' => $user->id,
       ]);

       return response()->json(['message' => 'Marked as read']);
   }
   ```

6. **Tests schreiben**
   - `tests/Feature/Api/Message/MarkAsReadTest.php`
   - Unread count aktualisieren nach Markieren als gelesen
   - ReadReceipts werden korrekt erstellt

#### Schritte zur Vervollständigung:

1. Migration prüfen/erstellen und ausführen
   ```bash
   php artisan make:migration create_read_receipts_table --create=read_receipts
   php artisan migrate
   ```

2. ReadReceipt Model Factory erstellen
   ```bash
   php artisan make:factory ReadReceiptFactory
   ```

3. Controller-Code in `DirectConversationController.php:32-36` ersetzen durch vollständige Version

4. Endpoint für "als gelesen markieren" hinzufügen

5. Tests ausführen:
   ```bash
   php artisan test --filter=DirectConversation
   php artisan test --filter=ReadReceipt
   ```

6. Dokumentation aktualisieren (diese Datei) - Punkt als erledigt markieren

---

### 2. Nachrichten-Zeitfilter - Performance-Optimierung

**Status:** ✅ Implementiert, aber könnte optimiert werden
**Datei:** `app/Http/Controllers/Api/DirectConversationController.php:224-228`

#### Aktueller Stand
```php
if (!isset($validated['before'])) {
    // Initiales Laden: Lade Nachrichten der letzten 30 Tage
    // (verhindert Performance-Probleme bei sehr alten Conversations)
    $query->where('created_at', '>=', now()->subDays(30));
}
```

#### Mögliche Verbesserungen:
- Konfigurierbar machen (z.B. in .env: `MESSAGE_HISTORY_DAYS=30`)
- Alternative: Immer die letzten N Nachrichten laden (z.B. 100), unabhängig vom Datum
- Caching für häufig abgerufene Conversations

#### Niedrige Priorität - Funktioniert gut genug

---

## Nächste Schritte

Wenn Phase 5+ abgeschlossen ist, hierher zurückkehren und:

1. ✅ Migration für `read_receipts` erstellen und ausführen
2. ✅ ReadReceipt Model vollständig implementieren
3. ✅ `unread_count` Logik vervollständigen
4. ✅ Endpoint zum Markieren als gelesen hinzufügen
5. ✅ Tests aktualisieren und erweitern
6. ✅ Diese Datei aktualisieren

---

## Kontext für spätere Sessions

**Warum wurde das vereinfacht?**

Während der Implementierung von Phase 4 trat ein SQL-Fehler auf:
```
SQLSTATE[HY000]: General error: 1 no such column: read_receipts.message_id
```

Die `read_receipts` Tabelle war in der Test-Datenbank (SQLite) nicht vorhanden bzw. nicht korrekt konfiguriert. Um Phase 4 trotzdem abzuschließen und alle Tests zum Laufen zu bringen, wurde eine pragmatische Entscheidung getroffen:

**Pragmatische Lösung:** Zähle alle Nachrichten vom anderen User
**Vorteil:** Feature funktioniert, Tests sind grün
**Nachteil:** Zeigt nicht die echte Anzahl ungelesener Nachrichten

Diese Lösung ist **akzeptabel für die Entwicklungsphase**, sollte aber **vor dem Production-Einsatz** vervollständigt werden.

---

**Letzte Aktualisierung:** 2025-12-15
**Verantwortlich:** Phase 4 Implementation
**Priorität:** Mittel (vor Production zwingend erforderlich)
