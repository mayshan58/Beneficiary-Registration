<?php
/**
 * Authentication and Role-Based Access Control (RBAC) Library
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

/**
 * Checks if a user is currently authenticated.
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/**
 * Returns current authenticated user session data.
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => (int)$_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
        'ngo_id'    => $_SESSION['ngo_id'] ?? null,
        'ngo_name'  => $_SESSION['ngo_name'] ?? 'Humanitarian Core'
    ];
}

/**
 * Enforces authentication. Redirects to login page if unauthenticated.
 */
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? BASE_URL;
        flash_set('warning', 'Please sign in to access this module.');
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}

/**
 * Checks if current user matches one or more specified roles.
 */
function has_role($allowed_roles): bool {
    if (!is_logged_in()) return false;
    $userRole = $_SESSION['role'] ?? '';
    
    if (is_array($allowed_roles)) {
        return in_array($userRole, $allowed_roles, true);
    }
    return $userRole === $allowed_roles;
}

/**
 * Enforces role permission check.
 */
function require_role($allowed_roles): void {
    require_login();
    if (!has_role($allowed_roles)) {
        http_response_code(403);
        $roleTitle = is_array($allowed_roles) ? implode(' or ', array_map(fn($r) => ROLES[$r] ?? $r, $allowed_roles)) : (ROLES[$allowed_roles] ?? $allowed_roles);
        
        // Render user-friendly permission error
        $pageTitle = "Access Denied";
        require_once __DIR__ . '/header.php';
        ?>
        <div class="row justify-content-center mt-5">
            <div class="col-md-7">
                <div class="card border-danger shadow-sm text-center p-4">
                    <div class="display-3 text-danger mb-3">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h3 class="card-title text-danger">Access Restricted</h3>
                    <p class="text-muted mt-2">
                        Your account role (<strong><?= e(ROLES[$_SESSION['role']] ?? $_SESSION['role']) ?></strong>) 
                        does not have authorization to access this specific module.
                    </p>
                    <p class="small text-muted">
                        Required role(s): <span class="badge bg-secondary"><?= e($roleTitle) ?></span>
                    </p>
                    <div class="mt-4">
                        <a href="<?= BASE_URL ?>" class="btn btn-primary px-4 me-2">
                            <i class="bi bi-house-door-fill me-1"></i> Return to Dashboard
                        </a>
                        <a href="javascript:history.back()" class="btn btn-outline-secondary px-3">
                            <i class="bi bi-arrow-left me-1"></i> Go Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/footer.php';
        exit();
    }
}

/**
 * Handles user login setup, session security, and audit logging.
 */
function login_user(array $user): void {
    // Regenerate session id to protect against session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['ngo_id']    = $user['ngo_id'] ?? null;
    
    // Fetch NGO acronym/name if affiliated
    if (!empty($user['ngo_id'])) {
        $db = getDB();
        $stmt = $db->prepare("SELECT acronym, name FROM ngos WHERE id = ?");
        $stmt->execute([$user['ngo_id']]);
        $ngo = $stmt->fetch();
        $_SESSION['ngo_name'] = $ngo ? ($ngo['acronym'] . ' - ' . $ngo['name']) : 'Humanitarian Partner';
    } else {
        $_SESSION['ngo_name'] = 'General Administration';
    }

    // Update last_login
    $db = getDB();
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    log_audit('USER_LOGIN', 'Authentication', (string)$user['id'], "User '{$user['username']}' logged in successfully.");
}

/**
 * Destroys user session and signs out.
 */
function logout_user(): void {
    if (isset($_SESSION['user_id'])) {
        log_audit('USER_LOGOUT', 'Authentication', (string)$_SESSION['user_id'], "User '{$_SESSION['username']}' logged out.");
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

