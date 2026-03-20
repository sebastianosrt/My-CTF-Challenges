<?php
$isNew = empty($page);
$title = $isNew ? 'New Page' : 'Edit: ' . htmlspecialchars($page['title'] ?? '');
ob_start();
?>

<p style="margin-bottom:1rem;">
    <a href="/pages">&larr; Back to pages</a>
</p>

<h1><?= $isNew ? 'Create New Page' : 'Edit Page' ?></h1>
<p class="subtitle"><?= $isNew ? 'Add a new content page' : 'Update page content and settings' ?></p>

<div class="editor-container">
    <div class="editor-main">
        <form method="POST" action="<?= $isNew ? '/pages' : '/pages/' . (int)$page['id'] ?>">
            <div class="card">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($page['title'] ?? '') ?>" required>
                </div>

                <?php if (!$isNew): ?>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" value="<?= htmlspecialchars($page['slug'] ?? '') ?>" disabled
                           style="background:#f5f5f5;color:var(--text-muted);">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Body</label>
                    <textarea name="body" rows="15" style="font-family:monospace;font-size:0.9rem;"><?= htmlspecialchars($page['body'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Tags</label>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.3rem;">
                        <?php
                        $pageTags = [];
                        if (!empty($page['tags'])) {
                            foreach ($page['tags'] as $t) {
                                $pageTags[] = (int) $t['id'];
                            }
                        }
                        foreach ($all_tags as $tag): ?>
                            <label style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.9rem;cursor:pointer;">
                                <input type="checkbox" name="tag_ids[]" value="<?= (int)$tag['id'] ?>"
                                    <?= in_array((int)$tag['id'], $pageTags) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($tag['name']) ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($all_tags)): ?>
                            <span style="color:var(--text-muted);font-size:0.85rem;">No tags available. <a href="/tags">Create tags</a></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display:flex;gap:0.8rem;align-items:center;">
                    <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Page' : 'Save Changes' ?></button>
                    <?php if ($isNew): ?>
                        <select name="status" style="padding:0.5rem;border:1px solid var(--border);border-radius:var(--radius);font-family:inherit;">
                            <option value="draft">Save as Draft</option>
                            <option value="published">Publish Immediately</option>
                        </select>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if (!$isNew): ?>
    <div class="editor-sidebar">
        <div class="card">
            <h2>Status</h2>
            <p>
                <span class="badge badge-<?= $page['status'] === 'published' ? 'published' : ($page['status'] === 'draft' ? 'draft' : 'archived') ?>" style="font-size:0.8rem;">
                    <?= htmlspecialchars($page['status']) ?>
                </span>
            </p>

            <?php
            $transitions = [];
            if ($page['status'] === 'draft') $transitions[] = 'published';
            if ($page['status'] === 'published') $transitions[] = 'archived';
            if ($page['status'] === 'archived') $transitions[] = 'draft';
            ?>

            <?php foreach ($transitions as $next): ?>
            <form method="POST" action="/pages/<?= (int)$page['id'] ?>/status" style="margin-top:0.5rem;">
                <input type="hidden" name="status" value="<?= $next ?>">
                <button type="submit" class="btn btn-sm <?= $next === 'published' ? 'btn-primary' : 'btn-secondary' ?>" style="width:100%;">
                    <?php if ($next === 'published'): ?>Publish<?php endif; ?>
                    <?php if ($next === 'archived'): ?>Archive<?php endif; ?>
                    <?php if ($next === 'draft'): ?>Revert to Draft<?php endif; ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>Details</h2>
            <div class="detail-item" style="margin-bottom:0.5rem;">
                <label>Created</label>
                <span style="font-size:0.85rem;"><?= htmlspecialchars($page['created_at'] ?? '-') ?></span>
            </div>
            <div class="detail-item" style="margin-bottom:0.5rem;">
                <label>Updated</label>
                <span style="font-size:0.85rem;"><?= htmlspecialchars($page['updated_at'] ?? '-') ?></span>
            </div>
            <?php if ($page['published_at']): ?>
            <div class="detail-item" style="margin-bottom:0.5rem;">
                <label>Published</label>
                <span style="font-size:0.85rem;"><?= htmlspecialchars($page['published_at']) ?></span>
            </div>
            <?php endif; ?>
            <div style="margin-top:0.8rem;">
                <a href="/pages/<?= (int)$page['id'] ?>/revisions" class="btn btn-sm btn-secondary" style="width:100%;">View Revisions</a>
            </div>
        </div>

        <div class="card">
            <h2>Schedule</h2>
            <form method="POST" action="/pages/<?= (int)$page['id'] ?>/schedule">
                <div class="form-group" style="margin-bottom:0.5rem;">
                    <label>Action</label>
                    <select name="action" style="width:100%;padding:0.5rem;border:1px solid var(--border);border-radius:var(--radius);font-family:inherit;">
                        <option value="published">Publish</option>
                        <option value="archived">Archive</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0.5rem;">
                    <label>Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_for" required
                           style="width:100%;padding:0.5rem;border:1px solid var(--border);border-radius:var(--radius);font-family:inherit;">
                </div>
                <button type="submit" class="btn btn-sm btn-secondary" style="width:100%;">Schedule Action</button>
            </form>
        </div>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <div class="card">
            <h2>Danger Zone</h2>
            <form method="POST" action="/pages/<?= (int)$page['id'] ?>/delete"
                  onsubmit="return confirm('Are you sure you want to delete this page?');">
                <button type="submit" class="btn btn-sm btn-danger" style="width:100%;">Delete Page</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
