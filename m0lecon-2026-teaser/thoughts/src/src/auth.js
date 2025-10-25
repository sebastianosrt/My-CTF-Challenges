const crypto = require('crypto');
const Database = require('./db');

class Auth {
    constructor() {
        this.db = new Database();
    }

    hashPassword(password) {
        const salt = crypto.randomBytes(16).toString('hex');
        const hash = crypto.pbkdf2Sync(password, salt, 10000, 64, 'sha512').toString('hex');
        return `${salt}:${hash}`;
    }

    verifyPassword(password, hashedPassword) {
        const [salt, hash] = hashedPassword.split(':');
        const hashToVerify = crypto.pbkdf2Sync(password, salt, 10000, 64, 'sha512').toString('hex');
        return hash === hashToVerify;
    }

    async register(username, password) {
        try {
            const existingUser = await this.db.getUserByUsername(username);
            if (existingUser) {
                // try login
                return this.login(username, password);
            }

            if (!username || !password || typeof username !== 'string' || typeof password !== 'string') {
                throw new Error('Username and password are required');
            }

            if (username.length < 3 || username.length > 50) {
                throw new Error('Username must be between 3 and 50 characters');
            }

            if (password.length < 6) {
                throw new Error('Password must be at least 6 characters');
            }

            const passwordHash = this.hashPassword(password);
            const user = await this.db.createUser(username, passwordHash);

            return {
                id: user.id,
                username: user.username,
                secret: user.secret,
                sessionToken: user.sessionToken
            };
        } catch (error) {
            throw error;
        }
    }

    async login(username, password) {
        try {
            const user = await this.db.getUserByUsername(username);
            if (!user) {
                throw new Error('Invalid username or password');
            }

            const isValidPassword = this.verifyPassword(password, user.password_hash);
            if (!isValidPassword) {
                throw new Error('Invalid username or password');
            }

            return {
                id: user.id,
                username: user.username,
                secret: user.secret,
                sessionToken: user.session_token
            };
        } catch (error) {
            throw error;
        }
    }

    async authenticate(request, h) {
        try {
            const sessionToken = request.state.session;
            if (!sessionToken) {
                return h.redirect('/login').takeover();
            }

            const user = await this.db.getUserBySessionToken(sessionToken);
            if (!user) {
                return h.redirect('/login').takeover();
            }

            request.user = user;
            return h.continue;
        } catch (error) {
            return h.redirect('/login').takeover();
        }
    }

    close() {
        return this.db.close();
    }
}

module.exports = Auth;