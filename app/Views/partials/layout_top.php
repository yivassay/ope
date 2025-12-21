<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
$locale = $_SESSION['locale'] ?? 'uz';
if (!in_array($locale, ['uz', 'ru'], true)) $locale = 'uz';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($locale) ?>" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($t->t('app.title')) ?></title>
  <link rel="stylesheet" href="assets/wowdash/css/remixicon.css">
  <link rel="stylesheet" href="assets/wowdash/css/lib/bootstrap.min.css">
  <link rel="stylesheet" href="assets/wowdash/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="body-overlay"></div>

<aside class="sidebar">
  <button type="button" class="sidebar-close-btn">
    <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
  </button>
  <div>
    <a href="?page=<?= (($auth->role() ?? '') === 'callcenter_manager') ? 'bugungi' : 'dashboard' ?>" class="sidebar-logo">
      <span class="fw-bold text-primary-600"><?= htmlspecialchars($t->t('app.title')) ?></span>
    </a>
  </div>
  <div class="sidebar-menu-area">
    <ul class="sidebar-menu" id="sidebar-menu">
      <?php if (($auth->role() ?? '') === 'admin'): ?>
        <li>
          <a href="?page=dashboard">
            <iconify-icon icon="solar:chart-2-outline" class="menu-icon"></iconify-icon>
            <span><?= htmlspecialchars($t->t('nav.dashboard')) ?></span>
          </a>
        </li>
      <?php endif; ?>
      <li>
        <a href="?page=bugungi">
          <iconify-icon icon="solar:calendar-date-outline" class="menu-icon"></iconify-icon>
          <span><?= htmlspecialchars($t->t('nav.bugungi', 'Bugungi')) ?></span>
        </a>
      </li>
      <li>
        <a href="?page=smartomato">
          <iconify-icon icon="solar:cloud-download-outline" class="menu-icon"></iconify-icon>
          <span><?= htmlspecialchars($t->t('nav.smartomato')) ?></span>
        </a>
      </li>
      <li>
        <a href="?page=others">
          <iconify-icon icon="solar:layers-outline" class="menu-icon"></iconify-icon>
          <span><?= htmlspecialchars($t->t('nav.others', 'Boshqalar')) ?></span>
        </a>
      </li>
      <li>
        <a href="?page=taxi">
          <iconify-icon icon="ri:taxi-line" class="menu-icon"></iconify-icon>
          <span><?= htmlspecialchars($t->t('nav.taxi', 'Taxi')) ?></span>
        </a>
      </li>
      <li>
        <a href="?page=errors">
          <iconify-icon icon="solar:shield-warning-outline" class="menu-icon"></iconify-icon>
          <span><?= htmlspecialchars($t->t('nav.errors', 'Xatolar')) ?></span>
        </a>
      </li>
      <li>
        <a href="?page=operator_sales">
          <iconify-icon icon="solar:users-group-rounded-outline" class="menu-icon"></iconify-icon>
          <span><?= htmlspecialchars($t->t('nav.operator_sales', 'Operator savdo')) ?></span>
        </a>
      </li>
      <li>
        <a href="?page=operators">
          <iconify-icon icon="solar:user-id-outline" class="menu-icon"></iconify-icon>
          <span><?= htmlspecialchars($t->t('nav.operators')) ?></span>
        </a>
      </li>
      <?php if (($auth->role() ?? '') === 'admin'): ?>
        <li>
          <a href="?page=settings">
            <iconify-icon icon="solar:settings-outline" class="menu-icon"></iconify-icon>
            <span><?= htmlspecialchars($t->t('nav.settings')) ?></span>
          </a>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</aside>

<main class="dashboard-main">
  <div class="navbar-header">
    <div class="row align-items-center justify-content-between">
      <div class="col-auto">
        <div class="d-flex flex-wrap align-items-center gap-4">
          <button type="button" class="sidebar-toggle">
            <iconify-icon icon="heroicons:bars-3-solid" class="icon text-2xl non-active"></iconify-icon>
            <iconify-icon icon="iconoir:arrow-right" class="icon text-2xl active"></iconify-icon>
          </button>
          <button type="button" class="sidebar-mobile-toggle">
            <iconify-icon icon="heroicons:bars-3-solid" class="icon"></iconify-icon>
          </button>
        </div>
      </div>
      <div class="col-auto">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <a class="w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center text-neutral-900"
             href="#"
             id="maskToggle"
             title="<?= htmlspecialchars($t->t('mask.title', 'Mask')) ?>">
            <span class="fw-bold">*</span>
          </a>

          <div class="dropdown d-inline-block">
            <button class="w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="text-sm fw-semibold"><?= htmlspecialchars(strtoupper($locale)) ?></span>
            </button>
            <div class="dropdown-menu to-top dropdown-menu-sm">
              <div class="py-12 px-16 radius-8 bg-primary-50 mb-16">
                <h6 class="text-lg text-primary-light fw-semibold mb-0"><?= htmlspecialchars($t->t('lang.ru', 'Русский')) ?> / <?= htmlspecialchars($t->t('lang.uz', 'O‘zbekcha')) ?></h6>
              </div>
              <div class="px-16 pb-12">
                <a class="dropdown-item px-0 py-8 d-flex align-items-center gap-3"
                   href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'uz']))) ?>">
                  <span class="text-md fw-semibold mb-0"><?= htmlspecialchars($t->t('lang.uz', 'O‘zbekcha')) ?></span>
                </a>
                <a class="dropdown-item px-0 py-8 d-flex align-items-center gap-3"
                   href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'ru']))) ?>">
                  <span class="text-md fw-semibold mb-0"><?= htmlspecialchars($t->t('lang.ru', 'Русский')) ?></span>
                </a>
              </div>
            </div>
          </div>

          <a class="w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center text-neutral-900"
             href="?page=profile" title="<?= htmlspecialchars($t->t('nav.profile', 'Profil')) ?>">
            <iconify-icon icon="solar:user-circle-outline" class="icon"></iconify-icon>
          </a>

          <a class="w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center text-neutral-900"
             href="?page=logout" title="<?= htmlspecialchars($t->t('nav.logout')) ?>">
            <iconify-icon icon="lucide:power" class="icon"></iconify-icon>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="dashboard-main-body">
    <div class="container-fluid py-3">
      <script>
        (function () {
          const KEY = 'callc_mask_mode';
          const MASK = '***';
          const skipTags = new Set(['SCRIPT', 'STYLE', 'CANVAS', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'TH', 'LABEL']);
          let applying = false;
          let scheduled = false;
          const originals = new Map(); // TextNode -> original string

          function hasSkippedAncestor(node) {
            let el = node && node.parentElement;
            while (el) {
              if (skipTags.has(el.tagName)) return true;
              el = el.parentElement;
            }
            return false;
          }

          function shouldMaskText(text) {
            const txt = (text || '').trim();
            if (!txt) return false;
            if (!/\d/.test(txt)) return false;
            // Keep section numbering like "1) Foyda"
            if (/^\d+\)\s*/.test(txt)) return false;
            // Keep pure dates/times (avoid hiding page date)
            if (/^\d{4}-\d{2}-\d{2}$/.test(txt)) return false;
            if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(txt)) return false;
            // If the whole text is a date with label like "Kun: 2025-12-20" keep it
            if (/\b\d{4}-\d{2}-\d{2}\b/.test(txt) && txt.replace(/\b\d{4}-\d{2}-\d{2}\b/g, '').replace(/[^\p{L}]+/gu, '').length > 0) {
              return false;
            }
            return true;
          }

          function maskText(text) {
            // Replace any numeric group (including separators) with ***
            return (text || '').replace(/(\d[\d\s.,]*)/g, MASK);
          }

          function forEachTextNode(cb) {
            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
              acceptNode(node) {
                if (!node || !node.nodeValue) return NodeFilter.FILTER_REJECT;
                if (hasSkippedAncestor(node)) return NodeFilter.FILTER_REJECT;
                if (!shouldMaskText(node.nodeValue)) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
              }
            });
            let n;
            while ((n = walker.nextNode())) cb(n);
          }

          function applyMask() {
            if (applying) return;
            applying = true;
            try {
              forEachTextNode((node) => {
                if (!originals.has(node)) originals.set(node, node.nodeValue);
                node.nodeValue = maskText(node.nodeValue);
              });
            } finally {
              applying = false;
            }
          }

          function clearMask() {
            for (const [node, orig] of originals.entries()) {
              if (!node || !node.isConnected) {
                originals.delete(node);
                continue;
              }
              node.nodeValue = orig;
            }
          }

          function setEnabled(enabled) {
            try { localStorage.setItem(KEY, enabled ? '1' : '0'); } catch (e) {}
            if (enabled) applyMask(); else clearMask();
          }

          function getEnabled() {
            try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
          }

          document.addEventListener('click', (e) => {
            const a = e.target && e.target.closest ? e.target.closest('#maskToggle') : null;
            if (!a) return;
            e.preventDefault();
            setEnabled(!getEnabled());
          });

          // Apply on load (no refresh needed)
          if (getEnabled()) applyMask();

          // If page content changes (collapse/ajax), re-apply mask
          const obs = new MutationObserver(() => {
            if (!getEnabled()) return;
            if (applying || scheduled) return;
            scheduled = true;
            requestAnimationFrame(() => {
              scheduled = false;
              applyMask();
            });
          });
          obs.observe(document.body, { subtree: true, childList: true });
        })();
      </script>

