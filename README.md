# Zorbl

A crossword puzzle platform for constructors and solvers. Build puzzles with a visual grid editor, solve community puzzles with auto-saved progress, run contests, and share your creations — all from the browser.

Built with Laravel 13, Livewire 4, Alpine.js, and Tailwind CSS 4.

---

## Features

### Construction

- **Visual Grid Editor** — Click to toggle blocks, type to place letters, automatic rotational symmetry
- **Word Suggestions** — Pattern-matched recommendations from a 210K+ word dictionary
- **AI Autofill** — Fill the entire grid with a backtracking heuristic solver or Anthropic Claude for thematic fills
- **AI Clue Generation** — Generate clever crossword clues with a single click
- **Any Grid Shape** — Standard grids, barred puzzles, non-rectangular shapes with voids
- **Clue Library** — Browse community-submitted clues for any answer word
- **Prefilled Letters** — Mark cells as given to create guided puzzles
- **Import/Export** — Full support for `.ipuz`, `.puz` (Across Lite), `.jpz` (Crossword Compiler), and `.pdf`
- **One-Click Publish** — Completeness checks, difficulty rating, and instant publishing

### Solving

- **Interactive Solver** — Keyboard navigation, pencil mode, check/reveal tools, timer
- **Auto-Save Progress** — Never lose your place; progress syncs automatically
- **Guest Solving** — Try one puzzle before signing up, with localStorage persistence
- **Embeddable Puzzles** — Embed any published puzzle on external sites with `<iframe>`

### Community

- **Public Browse** — Discover puzzles by difficulty, type, constructor, or trending activity
- **Contests** — Timed competitions with meta-answer puzzles and leaderboards
- **Likes & Comments** — Rate and review puzzles
- **Favorite Lists** — Organize puzzles into custom collections
- **Constructor Profiles** — Follow constructors and browse their published work
- **Achievements** — Earn badges for solving milestones and streaks

### Platform

- **REST API** — 30-endpoint JSON:API at `/api/v1/` with Sanctum token auth and auto-generated OpenAPI docs
- **Admin Panel** — Filament-powered dashboard for user management, contests, support tickets, and content moderation
- **Monitoring** — Laravel Pulse for real-time performance insights

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3+, Laravel 13, Livewire 4 |
| Frontend | Alpine.js 3, Tailwind CSS 4, Vite 8 |
| UI Components | Flux UI 2, Heroicons |
| Auth | Laravel Fortify (2FA/TOTP), Laravel Sanctum (API tokens) |
| Admin | Filament 5 |
| Testing | Pest 4 |
| API Docs | Scramble (OpenAPI 3.1) |
| Query Building | Spatie Laravel Query Builder 7 |
| Authorization | Spatie Laravel Permission |
| Monitoring | Laravel Pulse |
| AI | Anthropic Claude API (optional) |

---

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- Node.js 20+
- SQLite (default) or MySQL/PostgreSQL

### Quick Setup

```bash
git clone <repository-url> zorbl
cd zorbl
composer setup
```

This runs `composer install`, copies `.env.example`, generates an app key, runs migrations, installs npm dependencies, and builds frontend assets.

### Manual Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Seed the Word Dictionary

The 210K+ word list powers word suggestions and the autofill engine:

```bash
php artisan wordlist:generate
```

### Configure AI Features (Optional)

Add your Anthropic API key to `.env` to enable AI-powered grid filling and clue generation:

```
ANTHROPIC_API_KEY=sk-ant-...
```

### Development Server

```bash
composer dev
```

This starts the Laravel server, queue worker, log viewer (Pail), and Vite dev server concurrently.

Or if using [Laravel Herd](https://herd.laravel.com), the site is automatically available at `https://zorbl.test`.

---

## Architecture

### Directory Structure

```
app/
  Http/
    Controllers/Api/V1/   # REST API controllers (JSON:API)
    Resources/Api/V1/     # JSON:API resource transformers
    Requests/Api/V1/      # API form request validation
  Models/                  # 16 Eloquent models
  Policies/                # Authorization policies
  Services/                # Domain services (19 total)
  Filament/                # Admin panel resources & widgets
database/
  data/wordlist.txt        # 210K+ word dictionary
resources/
  js/
    crossword-grid.js      # Grid editor (Alpine.js component)
    crossword-solver.js    # Solver interface (Alpine.js component)
    embed.js               # Embeddable widget
  views/
    pages/                 # Livewire SFC pages
    components/            # Reusable Livewire components
    layouts/               # App, public, and embed layouts
routes/
  web.php                  # Web routes (Livewire pages)
  api.php                  # REST API v1 routes
```

### Key Services

| Service | Purpose |
|---------|---------|
| `GridFiller` | Backtracking constraint-propagation solver for heuristic autofill |
| `AiGridFiller` | Claude API integration for thematic grid filling |
| `AiClueGenerator` | Claude API integration for clue writing |
| `WordSuggester` | Pattern-matched word lookup against the dictionary |
| `GridNumberer` | Computes clue numbers and slot positions from any grid shape |
| `DifficultyRater` | Rates puzzle difficulty based on word frequency and grid structure |
| `ClueHarvester` | Extracts and indexes clues from published puzzles |
| `IpuzImporter` / `IpuzExporter` | `.ipuz` format support |
| `PuzImporter` / `PuzExporter` | `.puz` (Across Lite) binary format support |
| `JpzImporter` / `JpzExporter` | `.jpz` (Crossword Compiler) XML format support |
| `PdfExporter` | Print-ready PDF generation with grid and clues |
| `GridTemplateProvider` | Predefined grid shapes and patterns |
| `ContestService` | Contest lifecycle management |
| `AchievementService` | Badge and streak tracking |

### Data Model

```
User
 ├── Crossword (construct)
 │    ├── PuzzleAttempt (solve progress)
 │    ├── CrosswordLike
 │    ├── PuzzleComment
 │    └── ClueEntry
 ├── FavoriteList ──── Crossword (many-to-many)
 ├── Achievement
 ├── Follow ↔ User
 ├── ContestEntry ──── Contest ──── Crossword (many-to-many)
 └── SupportTicket ──── TicketResponse
```

---

## API

The REST API follows the [JSON:API specification](https://jsonapi.org/format/) and is documented automatically via Scramble at `/docs/api`.

### Authentication

```bash
# Get a token
curl -X POST https://zorbl.test/api/v1/tokens \
  -H "Content-Type: application/json" \
  -d '{"email": "you@example.com", "password": "secret", "device_name": "cli"}'

# Use the token
curl https://zorbl.test/api/v1/me \
  -H "Authorization: Bearer {token}"
```

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/crosswords` | Browse published puzzles |
| `GET` | `/crosswords/{id}` | Puzzle detail with grid and clues |
| `GET` | `/crosswords/{id}/comments` | Puzzle comments |
| `GET` | `/constructors/{id}` | Constructor profile |
| `GET` | `/constructors/{id}/crosswords` | Constructor's puzzles |
| `GET` | `/contests` | Browse contests |
| `GET` | `/contests/{slug}` | Contest detail |
| `GET` | `/contests/{slug}/leaderboard` | Rankings |
| `GET` | `/clues` | Search clue database |

### Authenticated Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/me` | Current user profile |
| `PATCH` | `/me` | Update profile |
| `GET` | `/me/stats` | Solving statistics |
| `GET` | `/me/achievements` | Earned badges |
| `GET` | `/me/attempts` | Solve history |
| `PUT` | `/crosswords/{id}/attempt` | Save solve progress |
| `POST` | `/crosswords/{id}/like` | Like a puzzle |
| `POST` | `/crosswords/{id}/comments` | Post a comment |
| `POST` | `/contests/{slug}/register` | Join a contest |
| `POST` | `/contests/{slug}/meta` | Submit meta answer |
| `GET/POST/DELETE` | `/favorites/...` | Manage favorite lists |

### Filtering & Sorting

Powered by [Spatie Laravel Query Builder](https://spatie.be/docs/laravel-query-builder):

```
GET /api/v1/crosswords?filter[difficulty_label]=easy&sort=-created_at&include=user
GET /api/v1/clues?filter[answer]=CRANE&sort=created_at
GET /api/v1/me/attempts?filter[is_completed]=true&sort=-updated_at&include=crossword
```

---

## Testing

```bash
# Run the full suite
php artisan test

# Run with compact output
php artisan test --compact

# Filter by name
php artisan test --filter=CrosswordTest

# Run only API tests
php artisan test --filter="Api\\V1"
```

The test suite uses [Pest](https://pestphp.com) with 500+ tests covering models, services, Livewire components, and API endpoints.

---

## Code Style

The project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
# Fix formatting
composer lint

# Check without fixing
composer lint:check
```

---

## Embedding Puzzles

Any published puzzle can be embedded on external sites:

```html
<iframe
  src="https://zorbl.test/embed/{crossword-id}"
  width="800"
  height="600"
  frameborder="0"
></iframe>
```

The embed includes the full interactive solver with localStorage persistence. A JSON API endpoint at `/api/embed/{id}` provides raw puzzle data for custom integrations.

---

## Admin Panel

Access the Filament admin panel at `/admin` (requires the Admin role). Manage:

- Users, roles, and permissions
- Contest creation and lifecycle
- Support ticket triage and response
- Clue report moderation
- Roadmap items

---

## Monitoring

[Laravel Pulse](https://laravel.com/docs/pulse) is available at `/pulse` for real-time application monitoring:

- Slow queries, requests, and jobs
- Cache hit rates
- Exception tracking
- Queue throughput
- Server resource usage

---

## License

MIT
