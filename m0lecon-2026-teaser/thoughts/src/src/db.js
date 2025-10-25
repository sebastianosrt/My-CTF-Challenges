const sqlite3 = require('sqlite3').verbose();
const path = require('path');
const crypto = require('crypto');

const DB_PATH = path.join(__dirname, 'thoughts.db');

class Database {
    constructor() {
        this.db = new sqlite3.Database(DB_PATH);
        this.init();
    }

    init() {
        this.db.serialize(() => {
            this.db.run(`CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                secret TEXT UNIQUE NOT NULL,
                session_token TEXT UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )`);

            this.db.run(`CREATE TABLE IF NOT EXISTS thoughts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )`);
        });
    }

    createUser(username, passwordHash) {
        return new Promise((resolve, reject) => {
            const secret = crypto.randomBytes(8).toString('hex');
            const sessionToken = crypto.randomBytes(32).toString('hex');
            const stmt = this.db.prepare('INSERT INTO users (username, password_hash, secret, session_token) VALUES (?, ?, ?, ?)');
            stmt.run(username, passwordHash, secret, sessionToken, function(err) {
                if (err) {
                    reject(err);
                } else {
                    resolve({ id: this.lastID, username, secret, sessionToken });
                }
            });
            stmt.finalize();
        });
    }

    getUserByUsername(username) {
        return new Promise((resolve, reject) => {
            this.db.get('SELECT * FROM users WHERE username = ?', [username], (err, row) => {
                if (err) {
                    reject(err);
                } else {
                    resolve(row);
                }
            });
        });
    }

    getUserBySessionToken(sessionToken) {
        return new Promise((resolve, reject) => {
            this.db.get('SELECT * FROM users WHERE session_token = ?', [sessionToken], (err, row) => {
                if (err) {
                    reject(err);
                } else {
                    resolve(row);
                }
            });
        });
    }

    createThought(userId, title, content) {
        return new Promise((resolve, reject) => {
            const stmt = this.db.prepare('INSERT INTO thoughts (user_id, title, content) VALUES (?, ?, ?)');
            stmt.run(userId, title, content, function(err) {
                if (err) {
                    reject(err);
                } else {
                    resolve({ id: this.lastID, user_id: userId, title, content, created_at: new Date().toISOString() });
                }
            });
            stmt.finalize();
        });
    }

    getThoughtsByUserId(userId) {
        return new Promise((resolve, reject) => {
            this.db.all('SELECT * FROM thoughts WHERE user_id = ? ORDER BY created_at DESC', [userId], (err, rows) => {
                if (err) {
                    reject(err);
                } else {
                    resolve(rows);
                }
            });
        });
    }

    getThoughtById(thoughtId, userId) {
        return new Promise((resolve, reject) => {
            this.db.get('SELECT * FROM thoughts WHERE id = ? AND user_id = ?', [thoughtId, userId], (err, row) => {
                if (err) {
                    reject(err);
                } else {
                    resolve(row);
                }
            });
        });
    }

    close() {
        return new Promise((resolve) => {
            this.db.close(resolve);
        });
    }
}

module.exports = Database;