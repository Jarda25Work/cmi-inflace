<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Vyžaduje admin práva
requireAdmin();

$success = false;
$error = false;

// Zpracování akcí
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF ochrana
    requireCsrfToken();
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'change_role':
                    updateUserRole($_POST['user_id'], $_POST['role']);
                    $success = 'Role uživatele byla změněna.';
                    break;
                    
                case 'toggle_active':
                    toggleUserActive($_POST['user_id']);
                    $success = 'Stav uživatele byl změněn.';
                    break;
                    
                case 'delete':
                    deleteUser($_POST['user_id']);
                    $success = 'Uživatel byl smazán.';
                    break;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Získání všech uživatelů
$users = getAllUsers();

$pageTitle = 'Správa uživatelů - ' . APP_NAME;
?>

<?php include 'includes/header.php'; ?>

<div class="gov-breadcrumbs" style="margin-top: 1rem;">
    <ol class="gov-breadcrumbs__list">
        <li class="gov-breadcrumbs__item">
            <a href="index.php" class="gov-breadcrumbs__link">Přehled měřidel</a>
        </li>
        <li class="gov-breadcrumbs__item" aria-current="page">Správa uživatelů</li>
    </ol>
</div>

<h1 class="gov-heading--large" style="margin-top: 2rem;">Správa uživatelů</h1>

<?php if ($success): ?>
    <div class="gov-alert gov-alert--success">
        <div class="gov-alert__content">
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="gov-alert gov-alert--error">
        <div class="gov-alert__content">
            <p><strong>Chyba:</strong> <?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="meridla-table" style="margin-top: 2rem;">
    <div class="table-wrapper">
        <table class="gov-table">
            <thead>
                <tr>
                    <th>Uživatelské jméno</th>
                    <th>Celé jméno</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Vytvořen</th>
                    <th>Poslední přihlášení</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <select name="role" class="gov-form-control" style="width: auto; display: inline-block; padding: 0.25rem 0.5rem;" 
                                    onchange="this.form.submit()"
                                    <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                <option value="read" <?php echo $user['role'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <?php if ($user['active']): ?>
                            <span style="color: #28a745; font-weight: 600;">✓ Aktivní</span>
                        <?php else: ?>
                            <span style="color: #dc3545; font-weight: 600;">✗ Neaktivní</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                    <td>
                        <?php 
                        if ($user['last_login']) {
                            echo date('d.m.Y H:i', strtotime($user['last_login']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="gov-button gov-button--small gov-button--secondary" 
                                        style="margin-right: 0.5rem;">
                                    <?php echo $user['active'] ? 'Deaktivovat' : 'Aktivovat'; ?>
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Opravdu chcete smazat uživatele <?php echo htmlspecialchars($user['username']); ?>?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="gov-button gov-button--small gov-button--error">
                                    Smazat
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color: #6c757d; font-style: italic;">Aktuální uživatel</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 4px;">
    <h3 class="gov-heading--medium">Informace o uživatelích</h3>
    <ul style="margin: 0; padding-left: 1.5rem;">
        <li>Uživatelé se automaticky vytvoří při prvním přihlášení přes CMI účet</li>
        <li>Nový uživatelé mají výchozí roli <strong>Read</strong> (pouze čtení)</li>
        <li>Admin může změnit roli ostatních uživatelů na <strong>Admin</strong> (plná práva)</li>
        <li>Username a email se nastaví při vytvoření a nelze je později měnit</li>
        <li>Nemůžete smazat nebo upravit sám sebe</li>
        <li>Deaktivovaní uživatelé se nemohou přihlásit</li>
    </ul>
</div>

<?php include 'includes/footer.php'; ?>
