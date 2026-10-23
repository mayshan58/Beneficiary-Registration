<?php
/**
 * System Audit Logs
 * Role-restricted: Administrator
 */

$pageTitle = "System Audit Logs";
$activeNav = "logs";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

// Filters
$search = sanitize($_GET['q'] ?? '');
$moduleFilter = sanitize($_GET['module'] ?? '');
$userFilter = (int)($_GET['user_id'] ?? 0);

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(a.action LIKE ? OR a.description LIKE ? OR a.ip_address LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

if (!empty($moduleFilter)) {
    $where[] = "a.module = ?";
    $params[] = $moduleFilter;
}

if ($userFilter > 0) {
    $where[] = "a.user_id = ?";
    $params[] = $userFilter;
}

$whereClause = implode(" AND ", $where);

// Pagination
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(*) FROM audit_logs a WHERE {$whereClause}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

$query = "
    SELECT a.*, u.full_name as user_name, u.username, u.role as user_role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE {$whereClause}
    ORDER BY a.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Users list for dropdown
$usersList = $db->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC")->fetchAll();

// Available modules
$modules = ['Authentication', 'Beneficiaries', 'Verification', 'Duplicates', 'Programs', 'Distributions', 'NGOs', 'Users', 'System'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-journal-text text-secondary me-2"></i>System Security &amp; Activity Audit Logs</h4>
        <p class="text-muted small mb-0">Immutable records of data mutations, user authentications, and compliance events.</p>
    </div>

    <div>
        <span class="badge bg-light text-secondary border px-3 py-2">
            <i class="bi bi-database me-1"></i> Total Audit Records: <?= number_format($totalRecords) ?>
        </span>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search action, description, or IP..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="module" class="form-select form-select-sm">
                    <option value="">All System Modules</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= e($m) ?>" <?= $moduleFilter === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Staff / Users</option>
                    <?php foreach ($usersList as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userFilter === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= e($u['full_name']) ?> (@<?= e($u['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 16%;">Timestamp</th>
                        <th style="width: 18%;">User / Officer</th>
                        <th style="width: 14%;">Module &amp; Action</th>
                        <th style="width: 40%;">Activity Description</th>
                        <th style="width: 12%;" class="text-end pe-3">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No audit logs found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="small text-muted">
                                    <i class="bi bi-clock me-1"></i><?= format_datetime($log['created_at']) ?>
                                </td>

                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <div class="fw-semibold small text-dark"><?= e($log['user_name']) ?></div>
                                        <small class="text-muted" style="font-size: 0.72rem;">@<?= e($log['username']) ?> (<?= e($log['user_role']) ?>)</small>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border">Anonymous / System</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge bg-light text-primary border small"><?= e($log['module']) ?></span>
                                    <div class="font-monospace small text-secondary mt-1" style="font-size: 0.75rem;"><?= e($log['action']) ?></div>
                                </td>

                                <td class="small text-dark">
                                    <?= e($log['description']) ?>
                                    <?php if ($log['record_id']): ?>
                                        <span class="badge bg-light text-muted border ms-1">Record #<?= e($log['record_id']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end pe-3 small font-monospace text-muted">
                                    <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">
                Page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($totalRecords) ?> logs)
            </small>

            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

