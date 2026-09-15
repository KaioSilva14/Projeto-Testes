-- Esquema do Painel de Gastos (SQLite).
--
-- Convencoes:
--   * valores monetarios sao INTEGER em centavos (nunca REAL);
--   * datas ficam em TEXT ISO-8601 ("YYYY-MM-DD"), ordenaveis lexicograficamente;
--   * competencias mensais ficam em TEXT "YYYY-MM".

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- E-mail unico independente de caixa alta/baixa.
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users (lower(email));

CREATE TABLE IF NOT EXISTS categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    name       TEXT NOT NULL,
    color      TEXT NOT NULL DEFAULT '#2563eb',
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_categories_user_name
    ON categories (user_id, lower(name));

CREATE TABLE IF NOT EXISTS expenses (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    -- ON DELETE RESTRICT: a exclusao da categoria e barrada no controller com
    -- uma mensagem clara, em vez de orfanar ou apagar despesas em cascata.
    category_id    INTEGER NOT NULL REFERENCES categories (id) ON DELETE RESTRICT,
    description    TEXT NOT NULL,
    amount_cents   INTEGER NOT NULL CHECK (amount_cents > 0),
    spent_at       TEXT NOT NULL,
    payment_method TEXT NOT NULL DEFAULT 'pix',
    notes          TEXT,
    created_at     TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- Praticamente toda consulta filtra por usuario + intervalo de datas.
CREATE INDEX IF NOT EXISTS idx_expenses_user_date  ON expenses (user_id, spent_at);
CREATE INDEX IF NOT EXISTS idx_expenses_category   ON expenses (category_id);
CREATE INDEX IF NOT EXISTS idx_expenses_user_value ON expenses (user_id, amount_cents);

CREATE TABLE IF NOT EXISTS budgets (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    category_id  INTEGER NOT NULL REFERENCES categories (id) ON DELETE CASCADE,
    period       TEXT NOT NULL,
    amount_cents INTEGER NOT NULL CHECK (amount_cents >= 0),
    created_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- Um orcamento por categoria em cada competencia (base do UPSERT).
CREATE UNIQUE INDEX IF NOT EXISTS idx_budgets_unique
    ON budgets (user_id, category_id, period);

CREATE INDEX IF NOT EXISTS idx_budgets_period ON budgets (user_id, period);
