CREATE TABLE IF NOT EXISTS queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    current INT NOT NULL,
    last_position INT NOT NULL,
    icon VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- INSERT INTO queue (
--     name, 
--     current, 
--     last_position, 
--     icon
-- ) VALUES(
--     "sample", 
--     0, 
--     5, 
--     "payments_outlined"
-- );
