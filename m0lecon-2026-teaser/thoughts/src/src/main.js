const Hapi = require('@hapi/hapi');
const Vision = require('@hapi/vision');
const Handlebars = require('handlebars');
const Auth = require('./auth');
const Database = require('./db');
const redis = require('redis');
const fs = require('fs');
const path = require('path');

const FLAG = process.env.FLAG || 'flag{test}';
const REDIS_URL = process.env.REDIS_URL || 'redis://redis:6379';

const auth = new Auth();
const db = new Database();

const init = async () => {
    const tlsOptions = {
        key: fs.readFileSync(path.join(__dirname, '../../certs/key.pem')),
        cert: fs.readFileSync(path.join(__dirname, '../../certs/cert.pem'))
    };

    const server = Hapi.server({
        port: 3000,
        host: '0.0.0.0',
        tls: tlsOptions
    });

    await server.register([Vision]);

    server.views({
        engines: {
            html: Handlebars
        },
        relativeTo: __dirname,
        path: 'views'
    });

    server.ext('onPreResponse', (request, h) => {
        const response = request.response;

        response.header('Content-Security-Policy', "default-src 'none'; style-src-elem 'unsafe-inline'");
        response.header('X-Frame-Options', 'DENY');
        response.header('X-Content-Type-Options', 'nosniff');
        response.header('Referrer-Policy', 'no-referrer');

        return h.continue;
    });

    server.route({
        method: 'GET',
        path: '/',
        options: {
            pre: [{ method: auth.authenticate.bind(auth) }]
        },
        handler: async (request, h) => {
            try {
                const userThoughts = await db.getThoughtsByUserId(request.user.id);
                return h.view('index', {
                    title: 'Thoughts! Thoughts',
                    thoughts: userThoughts.slice(0, 10),
                    secret: request.state.secret || request.user.secret,
                    customize: request.query.customize || ''
                });
            } catch (error) {
                return h.view('error', {
                    title: 'Error',
                    message: 'Failed to load thoughts'
                });
            }
        }
    });

    server.route({
        method: 'GET',
        path: '/flag',
        options: {
            pre: [{ method: auth.authenticate.bind(auth) }]
        },
        handler: async (request, h) => {
            try {
                const client = redis.createClient({ url: REDIS_URL });
                await client.connect();

                const secret = request.query.secret;
                if (!secret) {
                    await client.disconnect();
                    return h.response('You are not authorized').type('text/plain');
                }

                const storedSecret = await client.getDel(`secret:${request.user.secret}`);
                await client.disconnect();

                if (secret !== storedSecret || !storedSecret) {
                    return h.response('You are not authorized').type('text/plain');
                }

                return h.response(FLAG).type('text/plain');
            } catch (e) {
                return h.response('Error accessing authorization service').type('text/plain');
            }
        }
    })

    server.route({
        method: 'GET',
        path: '/create',
        options: {
            pre: [{ method: auth.authenticate.bind(auth) }]
        },
        handler: (request, h) => {
            return h.view('create', {
                title: 'Create New Thought'
            });
        }
    });

    server.route({
        method: 'POST',
        path: '/create',
        options: {
            pre: [{ method: auth.authenticate.bind(auth) }]
        },
        handler: async (request, h) => {
            const { title, content } = request.payload;

            if (!title || !content || typeof title !== 'string' || typeof content !== 'string') {
                return h.view('create', {
                    title: 'Create New Thought',
                    error: 'Both title and content are required'
                });
            }

            try {
                const newThought = await db.createThought(
                    request.user.id,
                    title.substring(0, 50),
                    content.substring(0, 1000)
                );

                return h.redirect('/thought/' + newThought.id);
            } catch (error) {
                return h.view('create', {
                    title: 'Create New Thought',
                    error: 'Failed to create thought'
                });
            }
        }
    });

    server.route({
        method: 'GET',
        path: '/thought/{id}',
        options: {
            pre: [{ method: auth.authenticate.bind(auth) }]
        },
        handler: async (request, h) => {
            try {
                const thoughtId = parseInt(request.params.id);
                const thought = await db.getThoughtById(thoughtId, request.user.id);

                if (!thought) {
                    return h.view('error', {
                        title: 'Thought Not Found',
                        message: 'The thought you are looking for does not exist.'
                    });
                }

                return h.view('thought', {
                    title: thought.title,
                    thought,
                });
            } catch (error) {
                return h.view('error', {
                    title: 'Error',
                    message: 'Failed to load thought'
                });
            }
        }
    });

    server.route({
        method: 'GET',
        path: '/login',
        handler: (request, h) => {
            return h.view('login', { title: 'Login' });
        }
    });

    server.route({
        method: 'POST',
        path: '/login',
        handler: async (request, h) => {
            const { username, password } = request.payload;

            try {
                const user = await auth.login(username, password);
                return h.response('Login successful')
                    .state('session', user.sessionToken, {
                        ttl: null,
                        isSecure: false,
                        isHttpOnly: true,
                        isSameSite: false,
                        path: '/'
                    })
                    .redirect('/');
            } catch (error) {
                return h.view('login', {
                    title: 'Login',
                    error: error.message
                });
            }
        }
    });

    server.route({
        method: 'GET',
        path: '/register',
        handler: (request, h) => {
            return h.view('register', { title: 'Register' });
        }
    });

    server.route({
        method: 'POST',
        path: '/register',
        handler: async (request, h) => {
            const { username, password } = request.payload;

            try {
                const user = await auth.register(username, password);
                return h.response('Registration successful')
                    .state('session', user.sessionToken, {
                        ttl: null,
                        isSecure: false,
                        isHttpOnly: true,
                        isSameSite: false,
                        path: '/'
                    })
                    .redirect('/');
            } catch (error) {
                return h.view('register', {
                    title: 'Register',
                    error: error.message
                });
            }
        }
    });

    server.route({
        method: 'GET',
        path: '/logout',
        handler: (request, h) => {
            return h.response('Logged out')
                .unstate('session')
                .redirect('/login');
        }
    });

    await server.start();
    console.log('server running on %s', server.info.uri);
};

process.on('unhandledRejection', (err) => {
    console.log(err);
    process.exit(1);
});

init();