<?php

use Herbarium\Core\Database;
use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Core\AuditLogger;

$router->get('/api/admin/audit', RouteGuard::wrap(
    function () {
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $action = $_GET['action'] ?? '';
        $where  = '';
        $params = [];
        if (!empty($action)) {
            $where    = "WHERE a.action = ?";
            $params[] = $action;
        }

        $total = Database::prepared("SELECT COUNT(*) as cnt FROM audit_log a {$where}", $params);
        $rows  = Database::prepared("
            SELECT a.*, u.username
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            {$where}
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));

        json_response([
            'entries'    => $rows,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => (int) $total[0]['cnt'],
            ],
        ]);
    },
    [RouteGuard::admin(), RouteGuard::audit('view_audit_log')]
));

$router->post('/api/admin/cache/flush', RouteGuard::wrap(
    function () use ($audit, $cache) {
        $claims = JwtAuth::requireAuth();
        $userId = (int) $claims->sub;

        $expired = $cache->flush();

        $audit->record('cache_flush', $userId, "expired={$expired}");
        json_response(['message' => 'Expired cache entries removed', 'expired' => $expired]);
    },
    [RouteGuard::admin()]
));

$router->post('/api/admin/cache/clear', RouteGuard::wrap(
    function () use ($audit, $cache) {
        $claims = JwtAuth::requireAuth();
        $userId = (int) $claims->sub;

        $cleared = $cache->clear();

        $audit->record('cache_clear', $userId, "cleared={$cleared}");
        json_response(['message' => 'All cache entries removed', 'entries_removed' => $cleared]);
    },
    [RouteGuard::admin()]
));

$router->get('/api/admin/cache/stats', RouteGuard::wrap(
    function () use ($cache) {
        json_response([
            'active_entries' => $cache->count(),
            'keys'           => $cache->keys(),
        ]);
    },
    [RouteGuard::admin()]
));

$router->post('/api/admin/users', RouteGuard::wrap(
    function () use ($audit) {
        $claims  = JwtAuth::requireAuth();
        $adminId = (int) $claims->sub;
        $body    = json_decode(file_get_contents('php://input'), true);

        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $display  = trim($body['display_name'] ?? '');
        $role     = $body['role'] ?? 'user';

        if (empty($username) || empty($password)) {
            json_response(['error' => 'Username and password are required'], 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
            json_response(['error' => 'Username may only contain letters, numbers, hyphens, and underscores'], 400);
        }
        if (strlen($password) < 8) {
            json_response(['error' => 'Password must be at least 8 characters'], 400);
        }
        if (!in_array($role, ['user', 'admin'], true)) {
            json_response(['error' => 'Role must be "user" or "admin"'], 400);
        }

        $exists = Database::preparedExists("SELECT 1 FROM users WHERE username = ?", [$username]);
        if ($exists) {
            json_response(['error' => 'Username already taken'], 409);
        }

        if (empty($display)) {
            $display = $username;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        Database::preparedExec(
            "INSERT INTO users (username, password, display_name, role) VALUES (?, ?, ?, ?)",
            [$username, $hash, $display, $role]
        );
        $newId = (int) Database::lastInsertId();

        $audit->record('user_created', $adminId, "new_user={$newId},username={$username},role={$role}");
        json_response([
            'message' => 'User created',
            'user'    => [
                'id'           => $newId,
                'username'     => $username,
                'display_name' => $display,
                'role'         => $role,
            ],
        ], 201);
    },
    [RouteGuard::admin()]
));

$router->get('/api/admin/users', RouteGuard::wrap(
    function () {
        $rows = Database::prepared(
            "SELECT id, username, display_name, role, avatar, created_at FROM users ORDER BY created_at ASC"
        );
        json_response(['users' => $rows]);
    },
    [RouteGuard::admin(), RouteGuard::audit('view_users')]
));

$router->delete('/api/admin/users/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims  = JwtAuth::requireAuth();
        $adminId = (int) $claims->sub;
        $targetId = (int) $id;

        if ($adminId === $targetId) {
            json_response(['error' => 'Cannot delete your own account'], 400);
        }

        $affected = Database::preparedExec("DELETE FROM users WHERE id = ?", [$targetId]);
        if ($affected === 0) {
            json_response(['error' => 'User not found'], 404);
        }

        $audit->record('user_deleted', $adminId, "deleted_user={$targetId}");
        json_response(['message' => 'User deleted']);
    },
    [RouteGuard::admin()]
));

$router->put('/api/admin/users/{id}/role', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::requireAuth();
        $adminId = (int) $claims->sub;
        $body = json_decode(file_get_contents('php://input'), true);
        $role = $body['role'] ?? '';

        if (!in_array($role, ['user', 'admin'], true)) {
            json_response(['error' => 'Role must be "user" or "admin"'], 400);
        }

        $affected = Database::preparedExec(
            "UPDATE users SET role = ? WHERE id = ?",
            [$role, (int) $id]
        );

        if ($affected === 0) {
            json_response(['error' => 'User not found'], 404);
        }

        $audit->record('role_change', $adminId, "target_user={$id},new_role={$role}");
        json_response(['message' => 'Role updated']);
    },
    [RouteGuard::admin()]
));
