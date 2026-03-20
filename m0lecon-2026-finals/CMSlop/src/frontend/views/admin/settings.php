<?php
$title = 'Site Settings';
ob_start();
?>

<h1>Site Settings</h1>
<p class="subtitle">Configure site-wide settings</p>

<form method="POST" action="/admin/settings">
    <div class="settings-form">
        <?php foreach ($settings as $setting): ?>
        <div class="card">
            <div class="form-group" style="margin-bottom:0;">
                <label><?= htmlspecialchars($setting['key']) ?></label>
                <?php if (!empty($setting['description'])): ?>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.5rem;"><?= htmlspecialchars($setting['description']) ?></p>
                <?php endif; ?>

                <?php if (in_array($setting['key'], ['maintenance_mode', 'allow_public_api'])): ?>
                    <select name="setting_<?= htmlspecialchars($setting['key']) ?>"
                            style="padding:0.6rem;border:1px solid var(--border);border-radius:var(--radius);font-family:inherit;width:100%;">
                        <option value="1" <?= $setting['value'] === '1' ? 'selected' : '' ?>>Enabled</option>
                        <option value="0" <?= $setting['value'] === '0' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                <?php elseif ($setting['key'] === 'items_per_page'): ?>
                    <input type="number" name="setting_<?= htmlspecialchars($setting['key']) ?>"
                           value="<?= htmlspecialchars($setting['value']) ?>"
                           min="5" max="100" step="5">
                <?php else: ?>
                    <input type="text" name="setting_<?= htmlspecialchars($setting['key']) ?>"
                           value="<?= htmlspecialchars($setting['value']) ?>">
                <?php endif; ?>

                <?php if ($setting['updated_at']): ?>
                    <span style="font-size:0.75rem;color:var(--text-muted);display:block;margin-top:0.3rem;">
                        Last updated: <?= htmlspecialchars($setting['updated_at']) ?>
                        <?php if ($setting['updated_by_name']): ?>
                            by <?= htmlspecialchars($setting['updated_by_name']) ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
