# Prova-01 — Simple persistent login throttle for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/beliven-it/prova-01.svg?style=flat-square)](https://packagist.org/packages/beliven-it/prova-01)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/beliven-it/prova-01/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/beliven-it/prova-01/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/beliven-it/prova-01.svg?style=flat-square)](https://packagist.org/packages/beliven-it/prova-01)

Questo pacchetto fornisce una semplice logica di login-throttle pensata per demo: dopo N tentativi falliti di login l'identificatore (email/username o IP) viene bloccato per un intervallo di tempo. La differenza principale rispetto a throttling basato solo su cache è che lo stato di lock è persistente su database (`login_locks`), quindi sopravvive a riavvii e cache flush.

Indice
- Requisiti
- Installazione
  - Packagist / composer
  - Installazione da repository Git (pubblico / privato)
  - Installazione in sviluppo (metodo `path`)
- Sail (esecuzione dentro container)
- Pubblicare migration & config
- Applicare il middleware
- Dettagli di implementazione (DB)
- Debug & ispezione
- Eseguire i test
- Contribuire

Requisiti
- PHP >= 8.4
- Laravel 11/12 compatibile
- Una connessione DB (per la persistenza dei lock). Per ambienti di sviluppo può andare SQLite.

Installazione

1) Da Packagist (pacchetto pubblico)
```bash
composer require beliven-it/prova-01
```

2) Dal repository Git (se hai pushato la repo)
A) Aggiungi la repository VCS (opzionale se il pacchetto è su Packagist):
```bash
composer config repositories.prova01 vcs https://github.com/youruser/prova-01.git
composer require beliven-it/prova-01:dev-main --prefer-source
```
- Se il repo è privato: configura autenticazione (SSH o token) affinché Composer nel tuo ambiente/container possa clonarlo.

3) Metodo per sviluppo locale (consigliato se stai lavorando al pacchetto)
- Metti il codice del package dentro il progetto Laravel in `packages/beliven/prova-01` o usa una repository `path`:
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/beliven/prova-01",
      "options": { "symlink": true }
    }
  ]
}
```
Poi:
```bash
composer require beliven-it/prova-01:dev-main
```
Vantaggi: modifiche al package sono visibili immediatamente nel progetto.

Laravel Sail
Se stai usando Sail, esegui i comandi Composer / Artisan dentro il container Sail. Esempi:
```bash
# Avvia i container (se non già in esecuzione)
./vendor/bin/sail up -d

# Installazione via composer dentro Sail
./vendor/bin/sail composer require beliven-it/prova-01:dev-main --prefer-source

# Pubblica e esegui le migration dentro Sail
./vendor/bin/sail artisan vendor:publish --tag="prova-01-migrations"
./vendor/bin/sail artisan migrate

# Pubblica config (opzionale)
./vendor/bin/sail artisan vendor:publish --tag="prova-01-config"
```

Pubblicare migration & config
Il package include:
- migration che crea la tabella `login_locks`
- file di configurazione `config/prova-01.php` con parametri:
  - `max_attempts` — numero di tentativi falliti prima del lock
  - `decay_minutes` — (solo per compatibilità) tempo dopo cui il contatore si resettarebbe
  - `lockout_duration` — minuti di lock
  - `lockout_message` — messaggio mostrato all'utente

Per pubblicare:
```bash
php artisan vendor:publish --provider="Beliven\Prova01\Prova01ServiceProvider" --tag="prova-01-migrations"
php artisan vendor:publish --provider="Beliven\Prova01\Prova01ServiceProvider" --tag="prova-01-config"
```
Poi esegui:
```bash
php artisan migrate
```
(se usi Sail, prefissa i comandi con `./vendor/bin/sail`).

Applicare il middleware
Il package registra un alias middleware:
- `prova01.login.throttle`

Applica questo middleware alla rotta POST che riceve le richieste di login:
```php
// routes/web.php o dove hai la rotta di login
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login'])
    ->middleware('prova01.login.throttle');
```
Comportamento:
- Il middleware controlla lo stato persistente in DB per l'identificatore (email, username, IP) e blocca la richiesta con HTTP 429 se è in lockout.
- Il conteggio dei tentativi falliti è fatto dai listener che si agganciano agli eventi `Illuminate\Auth\Events\Failed` e `Illuminate\Auth\Events\Login`:
  - `RecordFailedLoginAttempt` incrementa attempts e imposta `locked_until` quando la soglia viene raggiunta.
  - `ResetLoginAttemptsOnSuccess` resetta attempts e rimuove lock all'accesso riuscito.

Dettagli di implementazione (DB)
- Tabella: `login_locks`
  - `identifier` (string, unico) e.g. `email|user@example.com` o `ip|127.0.0.1`
  - `attempts` (unsigned int)
  - `locked_until` (timestamp nullable)
  - timestamps
- Modello Eloquent: `Beliven\Prova01\Models\LoginLock`
  - metodi utili: `incrementAttempts($max, $decay, $duration)`, `resetAttempts()`, `isLocked()`, `secondsUntilUnlock()`

Debug & ispezione
- Verifica che le migration siano state eseguite e che la tabella `login_locks` esista.
- Per ispezionare lo stato di lock per un identificatore:
```php
# tinker
php artisan tinker
>>> DB::table('login_locks')->where('identifier', 'email|user@example.com')->first();
# o con il modello
>>> Beliven\Prova01\Models\LoginLock::where('identifier', 'email|user@example.com')->first();
```
- Se il tuo flusso di login non emette gli eventi standard `Failed` / `Login`, il package non riuscirà a tracciare i tentativi: assicurati di usare il meccanismo di auth standard o emettere manualmente gli eventi.

Eseguire i test (pacchetto)
- Da dentro la cartella del package:
```bash
composer install
composer test
# oppure
vendor/bin/pest
```
- Se usi Sail per il progetto consumatore e vuoi eseguire test dentro Sail, adatta i comandi (es. `./vendor/bin/sail exec <service> composer test`).

Suggerimenti pratici per la demo
- Per demo rapide in locale usa SQLite (file) o il DB di sviluppo in modo che la tabella `login_locks` sia persistente e facilmente ispezionabile.
- Se vuoi modificare i valori per demo (es. 3 tentativi e lock 1 minuto), pubblica il config ed edita `config/prova-01.php`:
```php
'login_throttle' => [
    'max_attempts' => 3,
    'decay_minutes' => 1,
    'lockout_duration' => 1,
    'lockout_message' => 'Too many login attempts. Please try again.',
],
```

Installazione da repository privato (note)
- Se il repository è privato e stai lavorando dentro Sail, assicurati che il container possa usare le tue credenziali Git (SSH agent o token).
- Alternativa comoda: copiare/symlinkare il pacchetto in `packages/` del progetto e usare la repository `path` (eviti problemi di autenticazione durante lo sviluppo).

Contribuire
- Apri una PR per bugfix o migliorie.
- Se aggiungi modifiche breaking, aggiorna il CHANGELOG.

Contatti
- Autore: Fabrizio Gortani
- Repo: (sostituisci con l'URL del tuo repository se pubblico)

License
- MIT
